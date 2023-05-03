<?php
namespace Neos\Setup\Infrastructure\Healthcheck;

use Neos\Flow\Configuration\ConfigurationManager;
use Neos\Flow\Core\Bootstrap;
use Neos\Flow\Log\PsrLoggerFactoryInterface;
use Neos\Setup\Domain\EarlyBootTimeHealthcheckInterface;
use Neos\Setup\Domain\Health;
use Neos\Setup\Domain\Status;
use Neos\Utility\Files;
use Neos\Setup\Infrastructure\HealthcheckFailedError;
use Psr\Log\LoggerInterface;

class BasicRequirementsHealthcheck implements EarlyBootTimeHealthcheckInterface
{
    public function __construct(
        private readonly ConfigurationManager $configurationManager,
        private readonly LoggerInterface $logger
    ) {
    }

    public static function fromBootstrap(Bootstrap $bootstrap): self
    {
        return new self(
            $bootstrap->getEarlyInstance(ConfigurationManager::class),
            $bootstrap->getEarlyInstance(PsrLoggerFactoryInterface::class)->get('systemLogger')
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
        } catch (HealthcheckFailedError $error) {
            return new Health($error->getMessage(), Status::ERROR);
        }

        return new Health('All basic requirements are fullfilled.', Status::OK);
    }

    private function checkDirectorySeparator(): void
    {
        if (DIRECTORY_SEPARATOR !== '/' && PHP_WINDOWS_VERSION_MAJOR < 6) {
            throw new HealthcheckFailedError('Flow does not support Windows versions older than Windows Vista or Windows Server 2008, because they lack proper support for symbolic links.');
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
                    throw new HealthcheckFailedError('The folder "' . $folder . '" does not exist and could not be created but we need it.');
                }
            }

            if (!is_writable($folderPath)) {
                throw new HealthcheckFailedError('The folder "' . $folder . '" is not writeable but should be.');
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
                throw new HealthcheckFailedError('Function "' . $requiredFunction . '" is not callable but required.');
            }
        }
    }

    private function checkSessionAutostart(): void
    {
        if (ini_get('session.auto_start')) {
            throw new HealthcheckFailedError('"session.auto_start" is enabled in your php.ini. This is not supported and will cause problems.');
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
            throw new HealthcheckFailedError('Reflection of doc comments is not supported by your PHP setup. Please check if you have installed an accelerator which removes doc comments.');
        }
    }

    /**
     * Checks if the configured PHP binary is executable and the same version as the one
     * running the current (web server) PHP process. If not or if there is no binary configured,
     * tries to find the correct one on the PATH.
     *
     * @throws HealthcheckFailedError
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

        if ($phpBinaryPathAndFilename !== null) {
            $this->checkPhpBinary($phpBinaryPathAndFilename);
            return;
        }

        $this->detectPhpBinaryPathAndFilename();
    }

    /**
     * Checks if the given PHP binary is executable and of the same version as the currently running one.
     *
     * @throws HealthcheckFailedError
     */
    private function checkPhpBinary(string $phpBinaryPathAndFilename): void
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
                throw new HealthcheckFailedError('The specified path to your PHP binary (see Configuration/Settings.yaml) is incorrect or not a PHP command line (cli) version.');
            }
            $versionStringParts = explode(' ', $phpVersionString[0]);
            $phpVersion = isset($versionStringParts[1]) ? trim($versionStringParts[1]) : null;
            if ($phpVersion === PHP_VERSION) {
                return;
            }
        }
        if ($phpVersion === null) {
            $this->logger->error('The specified path to your PHP binary (see Configuration/Settings.yaml) is incorrect: not found at "%s"', [$phpBinaryPathAndFilename]);
            throw new HealthcheckFailedError('The specified path to your PHP binary (see Configuration/Settings.yaml) is incorrect: was not found. Please check your log for more details.');
        }

        $phpMinorVersionMatch = array_slice(explode('.', $phpVersion), 0, 2) === array_slice(explode('.', PHP_VERSION), 0, 2);
        if ($phpMinorVersionMatch) {
            $this->logger->warning('The specified path to your PHP binary (see Configuration/Settings.yaml) points to a PHP binary with the version "%s". This is not the exact same version as is currently running ("%s").', [
                $phpVersion,
                PHP_VERSION
            ]);

            return;
        }

        $this->logger->error('The specified path to your PHP binary (see Configuration/Settings.yaml) points to a PHP binary with the version "%s". This is not compatible to the version that is currently running ("%s").', [
            $phpVersion,
            PHP_VERSION
        ]);
        throw new HealthcheckFailedError('The specified path to your PHP binary (see Configuration/Settings.yaml) points to a PHP binary with an incompatible version. Please check your log for more details.');
    }

    /**
     * Traverse the PATH locations and check for the existence of a valid PHP binary.
     * If found, the path and filename are returned, if not NULL is returned.
     *
     * We only use PHP_BINARY if it's set to a file in the path PHP_BINDIR.
     * This is because PHP_BINARY might, for example, be "/opt/local/sbin/php54-fpm"
     * while PHP_BINDIR contains "/opt/local/bin" and the actual CLI binary is "/opt/local/bin/php".
     *
     * @throws HealthcheckFailedError
     */
    private function detectPhpBinaryPathAndFilename(): void
    {
        if (defined('PHP_BINARY') && PHP_BINARY !== '' && dirname(PHP_BINARY) === PHP_BINDIR) {
            try {
                $this->checkPhpBinary(PHP_BINARY);
                $this->logger->info('Please set the correct path to your PHP binary (detected is "%s") in the configuration setting Neos.Flow.core.phpBinaryPathAndFilename.', [PHP_BINARY]);
                throw new HealthcheckFailedError('Please set the correct path to your PHP binary in the configuration setting Neos.Flow.core.phpBinaryPathAndFilename. See your logs for details.');
            } catch (HealthcheckFailedError) {
                // we ignore this result as that only means PHP_BINARY is not the correct binary and we want to check alternatives below.
            }
        }

        $environmentPaths = explode(PATH_SEPARATOR, getenv('PATH'));
        $environmentPaths[] = PHP_BINDIR;
        foreach ($environmentPaths as $path) {
            $path = rtrim(str_replace('\\', '/', $path), '/');
            if ($path === '') {
                continue;
            }
            $phpBinaryPathAndFilename = $path . '/php' . (DIRECTORY_SEPARATOR !== '/' ? '.exe' : '');
            try {
                $this->checkPhpBinary($phpBinaryPathAndFilename);
            } catch (HealthcheckFailedError) {
                // The binary we tried was not a valid one, which we can ignore and continue with the next one.
                continue;
            }

            $this->logger->info('Please set the correct path to your PHP binary (detected is "%s") in the configuration setting Neos.Flow.core.phpBinaryPathAndFilename.', [$phpBinaryPathAndFilename]);
            throw new HealthcheckFailedError('Please set the correct path to your PHP binary in the configuration setting Neos.Flow.core.phpBinaryPathAndFilename. See your logs for details.');
        }

        throw new HealthcheckFailedError('We could not find any valid PHP binary in your PATH or configuration, please ensure you set the correct path to your PHP CLI binary in the configuration setting Neos.Flow.core.phpBinaryPathAndFilename.');
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
