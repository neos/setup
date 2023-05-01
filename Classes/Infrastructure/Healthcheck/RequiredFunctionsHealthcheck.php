<?php

namespace Neos\Setup\Infrastructure\Healthcheck;

use Neos\Flow\Core\Bootstrap;
use Neos\Setup\Domain\Health;
use Neos\Setup\Domain\HealthcheckInterface;
use Neos\Setup\Domain\Status;

class RequiredFunctionsHealthcheck implements HealthcheckInterface
{
    public static function fromBootstrap(Bootstrap $bootstrap): HealthcheckInterface
    {
        return new self();
    }

    public function getTitle(): string
    {
        return 'Required Functions';
    }

    public function execute(): Health
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
}
