<?php

namespace Neos\Setup\Infrastructure\Healthcheck;

use Neos\Setup\Domain\Health;
use Neos\Setup\Domain\HealthcheckEnvironment;
use Neos\Setup\Domain\HealthcheckInterface;
use Neos\Setup\Domain\Status;

class EndToEndHealthcheck implements HealthcheckInterface
{
    public function getTitle(): string
    {
        return 'End to end';
    }

    public function execute(HealthcheckEnvironment $environment): Health
    {
        return new Health('Flow is up and running', Status::OK);
    }
}
