<?php

namespace Neos\Setup\Domain;

/**
 * This contract must be implemented by every healthcheck which can be registered in the configuration.
 */
interface HealthcheckInterface
{
    public function getTitle(): string;

    public function execute(HealthcheckEnvironment $environment): Health;
}
