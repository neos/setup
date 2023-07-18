<?php

namespace Neos\Setup\Domain;

use GuzzleHttp\Psr7\Uri;
use Neos\Flow\Annotations as Flow;

/** @Flow\Proxy(false) */
class WebEnvironment
{
    public function __construct(
        /** @psalm-readonly */ public Uri $requestUri,
        /** @psalm-readonly */ public bool $isWindows
    ) {
    }
}
