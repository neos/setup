<?php

namespace Neos\Setup\Domain;

use Neos\Flow\Core\Bootstrap;

/** Since health checks can be registered to be run at boot time, we pass the $bootstrap along */
interface EarlyBootTimeHealthcheckInterface extends HealthcheckInterface
{
    public static function fromBootstrap(Bootstrap $bootstrap): self;
}
