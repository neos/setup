<?php

namespace Neos\Setup\Domain;

class Health
{
    public function __construct(
        public readonly string $title,
        public readonly string $message,
        public readonly Status $status
    ) {
    }
}
