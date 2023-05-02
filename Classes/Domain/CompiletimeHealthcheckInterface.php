<?php

namespace Neos\Setup\Domain;

use Neos\Flow\Core\Bootstrap;

/** Since health checks can be registered to be run at compile time, we pass the $bootstrap along */
interface CompiletimeHealthcheckInterface extends HealthcheckInterface
{
    public static function fromBootstrap(Bootstrap $bootstrap): self;
}
