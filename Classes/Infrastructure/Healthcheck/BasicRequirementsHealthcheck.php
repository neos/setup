<?php
namespace Neos\Setup\Infrastructure\Healthcheck;

use Error;
use Neos\Error\Messages\Message;
use Neos\Error\Messages\Warning;
use Neos\Flow\Configuration\ConfigurationManager;
use Neos\Flow\Core\Bootstrap;
use Neos\Setup\Domain\CompiletimeHealthcheckInterface;
use Neos\Setup\Domain\Health;
use Neos\Setup\Domain\Status;
use Neos\Utility\Files;

class BasicRequirementsHealthcheck implements CompiletimeHealthcheckInterface
{
    public function __construct(
        private readonly ConfigurationManager $configurationManager
    ) {
    }

    public static function fromBootstrap(Bootstrap $bootstrap): self
    {
        return new self(
            $bootstrap->getObjectManager()->get(ConfigurationManager::class)
        );
    }

    public function getTitle(): string
    {
        return 'Basic system requirements';
    }

    public function execute(): Health
    {
        try {
            $this->checkDirectorySeparator();
            $this->checkPhpBinaryVersion();
            $this->requiredFunctionsAvailable();
            $this->checkFilePermissions();
            $this->checkSessionAutostart();
            $this->checkReflectionStatus();
        } catch (Error $error) {
            return new Health($error->getMessage(), Status::ERROR);
        }

        return new Health('All basic requirements are fullfilled.', Status::OK);
    }

    private function checkDirectorySeparator(): void
    {
        if (DIRECTORY_SEPARATOR !== '/' && PHP_WINDOWS_VERSION_MAJOR < 6) {
            throw new Error('Flow does not support Windows versions older than Windows Vista or Windows Server 2008, because they lack proper support for symbolic links.');
        }
    }

    private function checkFilePermissions(): void
    {
        $requiredWritableFolders = ['Configuration', 'Data', 'Packages', 'Web/_Resources'];

        foreach ($requiredWritableFolders as $folder) {
            $folderPath = FLOW_PATH_ROOT . $folder;
            if (!is_dir($folderPath) && !Files::is_link($folderPath)) {
                try {
                    Files::createDirectoryRecursively($folderPath);
                } catch (\Neos\Flow\Utility\Exception) {
                    throw new Error('The folder "' . $folder . '" does not exist and could not be created but we need it.');
                }
            }

            if (!is_writable($folderPath)) {
                throw new Error('The folder "' . $folder . '" is not writeable but should be.');
            }
        }
    }

    private function requiredFunctionsAvailable(): void
    {
        $requiredFunctions = [
            'exec',
            'shell_exec',
            'escapeshellcmd',
            'escapeshellarg'
        ];

        foreach ($requiredFunctions as $requiredFunction) {
            if (!is_callable($requiredFunction)) {
                throw new Error('Function "' . $requiredFunction . '" is not callable but required.');
            }
        }
    }

    private function checkSessionAutostart(): void
    {
        if (ini_get('session.auto_start')) {
            throw new Error('"session.auto_start" is enabled in your php.ini. This is not supported and will cause problems.');
        }
    }

    /**
     * This doc-comment is used to check if doc comments are available.
     * DO NOT REMOVE
     */
    private function checkReflectionStatus(): void
    {
        $method = new \ReflectionMethod(__CLASS__, __FUNCTION__);
        $docComment = $method->getDocComment();

        if ($docComment === false || $docComment === '') {
            throw new Error('Reflection of doc comments is not supported by your PHP setup. Please check if you have installed an accelerator which removes doc comments.');
        }
    }

    /**
     * Checks if the configured PHP binary is executable and the same version as the one
     * running the current (web server) PHP process. If not or if there is no binary configured,
     * tries to find the correct one on the PATH.
     *
     * @throw Error
     */
    private function checkPhpBinaryVersion(): void
    {
        if (PHP_SAPI === 'cli') {
            // this check can only be run via web request
            return;
        }

        $phpBinaryPathAndFilename = $this->configurationManager->getConfiguration(
            ConfigurationManager::CONFIGURATION_TYPE_SETTINGS,
            'Neos.Flow.core.phpBinaryPathAndFilename'
        );

        $message = null;
        if (!$phpBinaryPathAndFilename) {
            [$phpBinaryPathAndFilename, $message] = $this->detectPhpBinaryPathAndFilename();
        }

        if ($message instanceof Message) {
            throw new Error($message->getMessage());
        }

        $message = $this->checkPhpBinary($phpBinaryPathAndFilename ?? PHP_BINARY);

        if ($message instanceof Message) {
            throw new Error($message->getMessage());
        }
    }

    /**
     * Checks if the given PHP binary is executable and of the same version as the currently running one.
     *
     * @return Message An error or warning message or NULL if the PHP binary was detected successfully
     */
    private function checkPhpBinary(string $phpBinaryPathAndFilename): ?Message
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
                return new \Neos\Error\Messages\Error('The specified path to your PHP binary (see Configuration/Settings.yaml) is incorrect or not a PHP command line (cli) version.', 1341839376, [], 'Environment requirements not fulfilled');
            }
            $versionStringParts = explode(' ', $phpVersionString[0]);
            $phpVersion = isset($versionStringParts[1]) ? trim($versionStringParts[1]) : null;
            if ($phpVersion === PHP_VERSION) {
                return null;
            }
        }
        if ($phpVersion === null) {
            return new \Neos\Error\Messages\Error('The specified path to your PHP binary (see Configuration/Settings.yaml) is incorrect: not found at "%s"', 1341839376, [$phpBinaryPathAndFilename], 'Environment requirements not fulfilled');
        }

        $phpMinorVersionMatch = array_slice(explode('.', $phpVersion), 0, 2) === array_slice(explode('.', PHP_VERSION), 0, 2);
        if ($phpMinorVersionMatch) {
            return new Warning('The specified path to your PHP binary (see Configuration/Settings.yaml) points to a PHP binary with the version "%s". This is not the exact same version as is currently running ("%s").', 1416913501, [
                $phpVersion,
                PHP_VERSION
            ], 'Possible PHP version mismatch');
        }

        return new \Neos\Error\Messages\Error('The specified path to your PHP binary (see Configuration/Settings.yaml) points to a PHP binary with the version "%s". This is not compatible to the version that is currently running ("%s").', 1341839377, [
            $phpVersion,
            PHP_VERSION
        ], 'Environment requirements not fulfilled');
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
    private function detectPhpBinaryPathAndFilename(): array
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
     */
    private function phpBinaryExistsAndIsExecutableFile(string $phpBinaryPathAndFilename): bool
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
