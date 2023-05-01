<?php

namespace Neos\Setup\Infrastructure\Healthcheck;

use Neos\Flow\Core\Bootstrap;
use Neos\Setup\Domain\Health;
use Neos\Setup\Domain\HealthcheckInterface;
use Neos\Setup\Domain\Status;

class EndToEndHealthcheck implements HealthcheckInterface
{
    public static function fromBootstrap(Bootstrap $bootstrap): HealthcheckInterface
    {
        return new self();
    }

    public function getTitle(): string
    {
        return 'End to end';
    }

    public function execute(): Health
    {
        return new Health($this->getTitle(), 'Flow is up and running.', Status::OK);
    }
}
