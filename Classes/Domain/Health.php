<?php

namespace Neos\Setup\Domain;

class Health
{
    public string $title = '';

    public function __construct(
        public readonly string $message,
        public readonly Status $status
    ) {
    }
}
