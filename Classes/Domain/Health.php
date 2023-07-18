<?php

namespace Neos\Setup\Domain;

use Neos\Flow\Annotations as Flow;

/** @Flow\Proxy(false) */
class Health
{
    public function __construct(
        /** @psalm-readonly */ public string $message,
        /** @psalm-readonly */ public Status $status,
        /** @psalm-readonly */ public string $title = '',
    ) {
    }
}
