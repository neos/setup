<?php
namespace Neos\Setup\Infrastructure\Healthcheck;

use Neos\Flow\Configuration\ConfigurationManager;
use Neos\Flow\Core\Booting\Exception\SubProcessException;
use Neos\Flow\Core\Booting\Scripts;
use Neos\Flow\Core\Bootstrap;
use Neos\Setup\Domain\CliEnvironment;
use Neos\Setup\Domain\EarlyBootTimeHealthcheckInterface;
use Neos\Setup\Domain\Health;
use Neos\Setup\Domain\HealthcheckEnvironment;
use Neos\Setup\Domain\Status;
use Neos\Utility\Files;
use Neos\Setup\Infrastructure\HealthcheckFailedError;

class BasicRequirementsHealthcheck implements EarlyBootTimeHealthcheckInterface
{
    private HealthcheckEnvironment $environment;

    public function __construct(
        private readonly ConfigurationManager $configurationManager
    ) {
    }

    public static function fromBootstrap(Bootstrap $bootstrap): self
    {
        return new self(
            $bootstrap->getEarlyInstance(ConfigurationManager::class)
        );
    }

    public function getTitle(): string
    {
        return 'Basic system requirements';
    }

    public function execute(HealthcheckEnvironment $environment): Health
    {
        $this->environment = $environment;
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
     * running the current (web server) PHP process.
     *
     * @throws HealthcheckFailedError
     */
    private function checkPhpBinaryVersion(): void
    {
        $flowSettings = $this->configurationManager->getConfiguration(
            ConfigurationManager::CONFIGURATION_TYPE_SETTINGS,
            'Neos.Flow'
        );

        $phpBinaryPathAndFilename = $flowSettings['core']['phpBinaryPathAndFilename'] ?? '';

        try {
            Scripts::buildPhpCommand($flowSettings);
        } catch (SubProcessException $subProcessException) {
            $possiblePhpBinary = '';
            if ($this->environment->executionEnvironment instanceof CliEnvironment && defined('PHP_BINARY') && PHP_BINARY !== '') {
                $possiblePhpBinary = sprintf(' You might want to configure it to: "%s".', PHP_BINARY);
            }
            throw new HealthcheckFailedError($this->environment->isSafeToLeakTechnicalDetails()
                ? sprintf('Could not start a flow subprocess. Maybe your PHP binary "%s" (see Configuration/Settings.yaml) is incorrect: "%s".%s Open <b>Data/Logs/Exceptions/%s.txt</b> for a full stack trace.', $phpBinaryPathAndFilename, $subProcessException->getMessage(), $subProcessException->getReferenceCode(), $possiblePhpBinary)
                : 'Could not start a flow subprocess. Maybe your PHP binary (see Configuration/Settings.yaml) is incorrect. Please check your log for more details.'
            );
        }
    }
}
