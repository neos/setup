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

use GuzzleHttp\Psr7\ServerRequest;
use Neos\Error\Messages\Warning;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Configuration\ConfigurationManager;
use Neos\Flow\Configuration\Source\YamlSource;
use Neos\Error\Messages\Error;
use Neos\Error\Messages\Message;
use Neos\Flow\Http\Middleware\MiddlewaresChainFactory;
use Neos\Flow\Http\RequestHandler as FlowRequestHandler;
use Neos\Utility\Arrays;
use Neos\Utility\Files;
use Psr\Http\Message\ServerRequestInterface;

/**
 * A request handler which can handle HTTP requests.
 *
 * @Flow\Scope("singleton")
 */
class RequestHandler extends FlowRequestHandler
{
    /**
     * This request handler can handle any web request.
     *
     * @return boolean If the request is a web request, TRUE otherwise FALSE
     */
    public function canHandleRequest()
    {
        return (PHP_SAPI !== 'cli'
            && (
                (strlen($_SERVER['REQUEST_URI']) === 6 && $_SERVER['REQUEST_URI'] === '/setup')
                || in_array(substr($_SERVER['REQUEST_URI'], 0, 7), ['/setup/', '/setup?'])
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

    /**
     * Handles a HTTP request
     *
     * @return void
     */
    public function handleRequest()
    {
        $this->httpRequest = ServerRequest::fromGlobals();

        $this->checkBasicRequirementsAndDisplayLoadingScreen();

        $this->boot();
        $this->resolveDependencies();
        $this->middlewaresChain->onStep(function (ServerRequestInterface $request) {
            $this->httpRequest = $request;
        });
        $response = $this->middlewaresChain->handle($this->httpRequest);

        $this->sendResponse($response);
        $this->bootstrap->shutdown('Runtime');
        $this->exit->__invoke();
    }

    /**
     * Check the basic requirements, and display a loading screen on initial request.
     *
     * @return void
     */
    protected function checkBasicRequirementsAndDisplayLoadingScreen()
    {
        $messageRenderer = new MessageRenderer($this->bootstrap);
        $basicRequirements = new BasicRequirements();
        $result = $basicRequirements->findError();
        if ($result instanceof Error) {
            $messageRenderer->showMessages([$result]);

            return;
        }

        $phpBinaryDetectionMessage = $this->checkAndSetPhpBinaryIfNeeded();
        if ($phpBinaryDetectionMessage instanceof Error) {
            $messageRenderer->showMessages([$phpBinaryDetectionMessage]);

            return;
        }

        $currentUriPath = $this->getHttpRequest()->getUri()->getPath();
        if ($currentUriPath === '/setup' || $currentUriPath === '/setup/') {
            $redirectUri = '/setup/index';
            $messages = [new Message('We are now redirecting you to the setup. <b>This might take 10-60 seconds on the first run,</b> because the application needs to build up various caches.', null, [], 'Initialising Setup ...')];
            if ($phpBinaryDetectionMessage !== null) {
                array_unshift($messages, $phpBinaryDetectionMessage);
            }
            $messageRenderer->showMessages($messages, '<meta http-equiv="refresh" content="2;URL=\'' . $redirectUri . '\'">');
        }
    }

    /**
     * Create a HTTP component chain that adds our own routing configuration component
     * only for this request handler.
     *
     * @return void
     */
    protected function resolveDependencies()
    {
        $objectManager = $this->bootstrap->getObjectManager();
        $configurationManager = $objectManager->get(ConfigurationManager::class);
        $this->settings = $configurationManager->getConfiguration(ConfigurationManager::CONFIGURATION_TYPE_SETTINGS, 'Neos.Flow');
        $setupSettings = $configurationManager->getConfiguration(ConfigurationManager::CONFIGURATION_TYPE_SETTINGS, 'Neos.Setup');
        $httpChainSettings = Arrays::arrayMergeRecursiveOverrule($this->settings['http']['middlewares'], $setupSettings['http']['middlewares']);
        $factory = $objectManager->get(MiddlewaresChainFactory::class);
        $this->middlewaresChain = $factory->create($httpChainSettings);
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
        list($phpBinaryPathAndFilename, $message) = $this->detectPhpBinaryPathAndFilename();
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
