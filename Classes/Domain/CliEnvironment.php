<?php

namespace Neos\Setup\Domain;

use Neos\Flow\Annotations as Flow;

/** @Flow\Proxy(false) */
class CliEnvironment
{
    public function __construct(
        /** @psalm-readonly */
        public bool $isWindows
    ) {
    }
}
