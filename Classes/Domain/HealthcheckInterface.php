<?php

namespace Neos\Setup\Domain;

use Neos\Flow\Core\Bootstrap;

interface HealthcheckInterface
{
    /** Regrettably */
    public static function fromBootstrap(Bootstrap $bootstrap): self;

    public function getTitle(): string;

    public function execute(): Health;
}
