<?php

namespace Neos\Setup\Domain;

use Neos\Flow\Annotations as Flow;

/** @Flow\Proxy(false) */
class Health
{
    public function __construct(
        public readonly string $message,
        public readonly Status $status,
        public readonly string $title = '',
    ) {
    }
}
