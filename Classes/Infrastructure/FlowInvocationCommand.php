<?php

declare(strict_types=1);

namespace Neos\Setup\Infrastructure;

use Neos\Flow\Annotations as Flow;

/**
 * @Flow\Proxy(false)
 */
class FlowInvocationCommand
{
    public function __construct(
        private readonly bool $isWindows
    ) {
    }

    public static function forEnvironment(bool $isWindows): self
    {
        return new self($isWindows);
    }

    public function toCommandString(): string
    {
        return $this->isWindows ? '.\flow.bat' : './flow';
    }

    public function replaceCommandPlaceHolders(string $string): string
    {
        return str_replace(
            '{{flowCommand}}',
            $this->toCommandString(),
            $string
        );
    }
}
