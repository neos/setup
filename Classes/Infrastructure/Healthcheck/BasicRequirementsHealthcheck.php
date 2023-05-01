<?php
namespace Neos\Setup\Infrastructure\Healthcheck;

use Neos\Flow\Core\Bootstrap;
use Neos\Setup\Domain\Health;
use Neos\Setup\Domain\HealthcheckInterface;
use Neos\Setup\Domain\HealthCollection;
use Neos\Setup\Domain\Status;
use Neos\Utility\Files;

class BasicRequirementsHealthcheck implements HealthcheckInterface
{
    public static function fromBootstrap(Bootstrap $bootstrap): HealthcheckInterface
    {
        return new self();
    }

    public function getTitle(): string
    {
        return 'Basic system requirements';
    }

    public function execute(): Health
    {
        $checks = function (): \Generator {
            yield $this->checkDirectorySeparator();
            yield $this->requiredFunctionsAvailable();
            yield $this->checkFilePermissions();
            yield $this->checkSessionAutostart();
            yield $this->checkReflectionStatus();
        };

        foreach ($checks() as $check) {
            if ($check->status === Status::ERROR) {
                return $check;
            }
        }

        return new Health($this->getTitle(), 'All basic requirements are fullfilled.', Status::OK);
    }

    private function checkDirectorySeparator(): Health
    {
        if (DIRECTORY_SEPARATOR !== '/' && PHP_WINDOWS_VERSION_MAJOR < 6) {
            return new Health(
                $this->getTitle(),
                <<<MSG
                    Flow does not support Windows versions older than Windows Vista or Windows Server 2008, because they lack proper support for symbolic links.
                    MSG,
                Status::ERROR
            );
        }

        return new Health($this->getTitle(), 'Directory separator and/or windows version are suitable.', Status::OK);
    }

    private function checkFilePermissions(): Health
    {
        $requiredWritableFolders = ['Configuration', 'Data', 'Packages', 'Web/_Resources'];

        foreach ($requiredWritableFolders as $folder) {
            $folderPath = FLOW_PATH_ROOT . $folder;
            if (!is_dir($folderPath) && !Files::is_link($folderPath)) {
                try {
                    Files::createDirectoryRecursively($folderPath);
                } catch (\Neos\Flow\Utility\Exception $_) {
                    return new Health(
                        $this->getTitle(),
                        <<<MSG
                    The folder "$folder" does not exist and could not be created but we need it.
                    MSG,
                        Status::ERROR
                    );
                }
            }

            if (!is_writable($folderPath)) {
                return new Health(
                    $this->getTitle(),
                    <<<MSG
                    The folder "$folder" is not writeable but should be.
                    MSG,
                    Status::ERROR
                );
            }
        }

        return new Health($this->getTitle(), 'All required folders exist and are writeable', Status::OK);
    }

    private function requiredFunctionsAvailable(): Health
    {
        $requiredFunctions = [
            'exec',
            'shell_exec',
            'escapeshellcmd',
            'escapeshellarg'
        ];

        foreach ($requiredFunctions as $requiredFunction) {
            if (!is_callable($requiredFunction)) {
                return new Health(
                    $this->getTitle(),
                    <<<MSG
                    Function $requiredFunction is not callable but required.
                    MSG,
                    Status::ERROR
                );
            }
        }

        return new Health($this->getTitle(), 'All required functions existing', Status::OK);
    }

    private function checkSessionAutostart(): Health
    {
        if (ini_get('session.auto_start')) {
            return new Health(
                $this->getTitle(),
                <<<MSG
                    "session.auto_start" is enabled in your php.ini. This is not supported and will cause problems.
                    MSG,
                Status::ERROR
            );
        }

        return new Health($this->getTitle(), 'session.auto_start disabled.', Status::OK);
    }

    /**
     * This doccomment is used to check if doc comments are available.
     * DO NOT REMOVE
     *
     * @return Health
     */
    private function checkReflectionStatus(): Health
    {
        $method = new \ReflectionMethod(__CLASS__, __FUNCTION__);
        $docComment = $method->getDocComment();
        if ($docComment === false || $docComment === '') {
            return new Health(
                $this->getTitle(),
                <<<MSG
                    Reflection of doc comments is not supported by your PHP setup. Please check if you have installed an accelerator which removes doc comments.
                    MSG,
                Status::ERROR
            );
        }

        return new Health($this->getTitle(), 'Reflection of doc comments is supported.', Status::OK);
    }
}
