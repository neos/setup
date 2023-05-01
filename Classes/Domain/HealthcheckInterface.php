<?php

namespace Neos\Setup\Domain;

use Neos\Flow\Core\Bootstrap;

/**
 * This contract must be implemented by every healthcheck which can be registered in the configuration.
 */
interface HealthcheckInterface
{
    /** Since health checks can be registered to be run at compile time, we pass the $bootstrap along */
    public static function fromBootstrap(Bootstrap $bootstrap): self;

    public function getTitle(): string;

    public function execute(): Health;
}
