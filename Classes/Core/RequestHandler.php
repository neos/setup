<?php
namespace Neos\Setup\Core;

/*
 * This file is part of the Neos.Setup package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use GuzzleHttp\Psr7\Response;
use Neos\Error\Messages\Warning;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Configuration\ConfigurationManager;
use Neos\Flow\Configuration\Source\YamlSource;
use Neos\Error\Messages\Error;
use Neos\Error\Messages\Message;
use Neos\Flow\Core\Bootstrap;
use Neos\Flow\Core\RequestHandlerInterface;
use Neos\Flow\Http\ContentStream;
use Neos\Utility\Arrays;
use Neos\Utility\Files;

/**
 * A request handler which can handle HTTP requests.
 *
 * @Flow\Scope("singleton")
 * @Flow\Proxy(false)
 */
class RequestHandler implements RequestHandlerInterface
{
    private Bootstrap $bootstrap;

    public function __construct(Bootstrap $bootstrap)
    {
        $this->bootstrap = $bootstrap;
        $this->exit = function () {
            exit();
        };
    }

    public function canHandleRequest(): bool
    {
        $uriPrefix = '/setup';
        return (PHP_SAPI !== 'cli'
            && (
                $_SERVER['REQUEST_URI'] === $uriPrefix ||
                $_SERVER['REQUEST_URI'] === $uriPrefix . '_compiletime.json'
            ));
    }

    /**
     * Returns the priority - how eager the handler is to actually handle the
     * request.
     *
     * @return integer The priority of the request handler.
     */
    public function getPriority()
    {
        return 200;
    }

    public function handleRequest()
    {
        $this->boot();

        $response = (new Response(200))->withBody(ContentStream::fromContents('YOLO'));
        $this->sendResponse($response);
        $this->bootstrap->shutdown('Compiletime');
        $this->exit->__invoke();
    }
    /**
     * Checks if the configured PHP binary is executable and the same version as the one
     * running the current (web server) PHP process. If not or if there is no binary configured,
     * tries to find the correct one on the PATH.
     *
     * Once found, the binary will be written to the configuration, if it is not the default one
     * (PHP_BINARY or in PHP_BINDIR).
     *
     * @return Message An error or warning message or NULL if PHP was detected successfully
     */
    protected function checkAndSetPhpBinaryIfNeeded()
    {
        $configurationSource = new YamlSource();
        $distributionSettings = $configurationSource->load(FLOW_PATH_CONFIGURATION . ConfigurationManager::CONFIGURATION_TYPE_SETTINGS);
        if (isset($distributionSettings['Neos']['Flow']['core']['phpBinaryPathAndFilename'])) {
            return $this->checkPhpBinary($distributionSettings['Neos']['Flow']['core']['phpBinaryPathAndFilename']);
        }
        [$phpBinaryPathAndFilename, $message] = $this->detectPhpBinaryPathAndFilename();
        if ($phpBinaryPathAndFilename !== null) {
            $defaultPhpBinaryPathAndFilename = PHP_BINDIR . '/php';
            if (DIRECTORY_SEPARATOR !== '/') {
                $defaultPhpBinaryPathAndFilename = str_replace('\\', '/', $defaultPhpBinaryPathAndFilename) . '.exe';
            }
            if ($phpBinaryPathAndFilename !== $defaultPhpBinaryPathAndFilename) {
                $distributionSettings = Arrays::setValueByPath($distributionSettings, 'Neos.Flow.core.phpBinaryPathAndFilename', $phpBinaryPathAndFilename);
                $configurationSource->save(FLOW_PATH_CONFIGURATION . ConfigurationManager::CONFIGURATION_TYPE_SETTINGS, $distributionSettings);
            }
        }

        return $message;
    }

    /**
     * Checks if the given PHP binary is executable and of the same version as the currently running one.
     *
     * @param string $phpBinaryPathAndFilename
     * @return Message An error or warning message or NULL if the PHP binary was detected successfully
     */
    protected function checkPhpBinary($phpBinaryPathAndFilename)
    {
        $phpVersion = null;
        if ($this->phpBinaryExistsAndIsExecutableFile($phpBinaryPathAndFilename)) {
            if (DIRECTORY_SEPARATOR === '/') {
                $phpCommand = '"' . escapeshellcmd(Files::getUnixStylePath($phpBinaryPathAndFilename)) . '"';
            } else {
                $phpCommand = escapeshellarg(Files::getUnixStylePath($phpBinaryPathAndFilename));
            }
            // If the tested binary is a CGI binary that also runs the current request the SCRIPT_FILENAME would take precedence and create an endless recursion.
            $possibleScriptFilenameValue = getenv('SCRIPT_FILENAME');
            putenv('SCRIPT_FILENAME');
            exec($phpCommand . ' -r "echo \'(\' . php_sapi_name() . \') \' . PHP_VERSION;"', $phpVersionString);
            if ($possibleScriptFilenameValue !== false) {
                putenv('SCRIPT_FILENAME=' . (string)$possibleScriptFilenameValue);
            }
            if (!isset($phpVersionString[0]) || strpos($phpVersionString[0], '(cli)') === false) {
                return new Error('The specified path to your PHP binary (see Configuration/Settings.yaml) is incorrect or not a PHP command line (cli) version.', 1341839376, [], 'Environment requirements not fulfilled');
            }
            $versionStringParts = explode(' ', $phpVersionString[0]);
            $phpVersion = isset($versionStringParts[1]) ? trim($versionStringParts[1]) : null;
            if ($phpVersion === PHP_VERSION) {
                return null;
            }
        }
        if ($phpVersion === null) {
            return new Error('The specified path to your PHP binary (see Configuration/Settings.yaml) is incorrect: not found at "%s"', 1341839376, [$phpBinaryPathAndFilename], 'Environment requirements not fulfilled');
        }

        $phpMinorVersionMatch = array_slice(explode('.', $phpVersion), 0, 2) === array_slice(explode('.', PHP_VERSION), 0, 2);
        if ($phpMinorVersionMatch) {
            return new Warning('The specified path to your PHP binary (see Configuration/Settings.yaml) points to a PHP binary with the version "%s". This is not the exact same version as is currently running ("%s").', 1416913501, [$phpVersion, PHP_VERSION], 'Possible PHP version mismatch');
        }

        return new Error('The specified path to your PHP binary (see Configuration/Settings.yaml) points to a PHP binary with the version "%s". This is not compatible to the version that is currently running ("%s").', 1341839377, [$phpVersion, PHP_VERSION], 'Environment requirements not fulfilled');
    }

    /**
     * Traverse the PATH locations and check for the existence of a valid PHP binary.
     * If found, the path and filename are returned, if not NULL is returned.
     *
     * We only use PHP_BINARY if it's set to a file in the path PHP_BINDIR.
     * This is because PHP_BINARY might, for example, be "/opt/local/sbin/php54-fpm"
     * while PHP_BINDIR contains "/opt/local/bin" and the actual CLI binary is "/opt/local/bin/php".
     *
     * @return array PHP binary path as string or NULL if not found and a possible Message
     */
    protected function detectPhpBinaryPathAndFilename()
    {
        if (defined('PHP_BINARY') && PHP_BINARY !== '' && dirname(PHP_BINARY) === PHP_BINDIR) {
            if ($this->checkPhpBinary(PHP_BINARY) === null) {
                return [PHP_BINARY, null];
            }
        }

        $environmentPaths = explode(PATH_SEPARATOR, getenv('PATH'));
        $environmentPaths[] = PHP_BINDIR;
        $lastCheckMessage = null;
        foreach ($environmentPaths as $path) {
            $path = rtrim(str_replace('\\', '/', $path), '/');
            if ($path === '') {
                continue;
            }
            $phpBinaryPathAndFilename = $path . '/php' . (DIRECTORY_SEPARATOR !== '/' ? '.exe' : '');
            $lastCheckMessage = $this->checkPhpBinary($phpBinaryPathAndFilename);
            if (!$lastCheckMessage instanceof Error) {
                return [$phpBinaryPathAndFilename, $lastCheckMessage];
            }
        }

        return [null, $lastCheckMessage];
    }

    /**
     * Checks if PHP binary file exists bypassing open_basedir violation.
     *
     * If PHP binary is not within open_basedir path,
     * it is impossible to access this binary in any other way than exec() or system().
     * So we must check existence of this file with system tools.
     *
     * @param string $phpBinaryPathAndFilename
     * @return boolean
     */
    protected function phpBinaryExistsAndIsExecutableFile($phpBinaryPathAndFilename)
    {
        $phpBinaryPathAndFilename = escapeshellarg(Files::getUnixStylePath($phpBinaryPathAndFilename));
        if (DIRECTORY_SEPARATOR === '/') {
            $command = sprintf('test -f %s && test -x %s', $phpBinaryPathAndFilename, $phpBinaryPathAndFilename);
        } else {
            $command = sprintf('IF EXIST %s (IF NOT EXIST %s\* (EXIT 0) ELSE (EXIT 1)) ELSE (EXIT 1)', $phpBinaryPathAndFilename, $phpBinaryPathAndFilename);
        }

        exec($command, $outputLines, $exitCode);
        return $exitCode === 0;
    }
}
