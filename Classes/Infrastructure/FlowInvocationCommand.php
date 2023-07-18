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
        private bool $isWindows
    ) {
    }

    public static function forEnvironment(bool $isWindows): self
    {
        return new self($isWindows);
    }

    public function toCommandString(): string
    {
        if ($this->isWindows) {
            return  '.\flow.bat';
        }

        if ($home = getenv('HOME')) {
            $flowOhMyZshPluginExists = @file_exists($home . '/.oh-my-zsh/custom/plugins/flow');
            if ($flowOhMyZshPluginExists) {
                // support for https://github.com/sandstorm/oh-my-zsh-flow-plugin
                // we assume anyone who installed this plugin, is also brave enough to have it enabled ;)
                return 'flow';
            }
        }

        return './flow';
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
