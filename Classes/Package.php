<?php
namespace Neos\Setup;

/*
 * This file is part of the Neos.Setup package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\Flow\Core\Bootstrap;
use Neos\Flow\Package\Package as BasePackage;
use Neos\Setup\RequestHandler\SetupCliRequestHandler;
use Neos\Setup\RequestHandler\SetupHttpRequestHandler;

class Package extends BasePackage
{
    /**
     * To be redirected ambiguous or legacy command identifiers with their fully qualified replacement.
     * @var array<string, string>
     */
    private const COMMAND_IDENTIFIER_ALIAS_MAP = [
        // short alias instead of `setup:index`
        'setup' => 'neos.setup:setup:index',
        // legacy redirect from `Neos.CliSetup`
        'welcome' => 'neos.setup:setup:index',
        // ambiguous command identifiers, if `Neos.CliSetup` is also installed, we prefer the new commands.
        'setup:database' => 'neos.setup:setup:database',
        'setup:imagehandler' => 'neos.setup:setup:imagehandler'
    ];

    public function boot(Bootstrap $bootstrap): void
    {
        $this->registerCommandIdentifierAlias(self::COMMAND_IDENTIFIER_ALIAS_MAP);
        $bootstrap->registerRequestHandler(new SetupHttpRequestHandler($bootstrap));
        $bootstrap->registerRequestHandler(new SetupCliRequestHandler($bootstrap));
    }

    /**
     * Allows shortcut commands like `./flow setup` and expands ambiguous commands to avoid conflicts with another legacy package.
     *
     * The command identifier will be rewritten, before Flows command request handler is started.
     * Also in case an aliased command is looked up via help like `./flow help setup`, we expand the to be looked up identifier also.
     *
     * @param array<string, string> $commandIdentifierAliasMap To be redirected ambiguous or legacy command identifiers with their fully qualified replacement.
     */
    private function registerCommandIdentifierAlias(array $commandIdentifierAliasMap): void
    {
        if (PHP_SAPI !== 'cli') {
            return;
        }
        $commandIdentifier = $_SERVER['argv'][1] ?? null;
        if ($commandIdentifier === 'help') {
            $commandIdentifierToShowHelp = $_SERVER['argv'][2] ?? null;
            $alias = $commandIdentifierAliasMap[$commandIdentifierToShowHelp] ?? null;
            if ($alias) {
                $_SERVER['argv'][2] = $alias;
            }
            return;
        }
        $alias = $commandIdentifierAliasMap[$commandIdentifier] ?? null;
        if ($alias) {
            $_SERVER['argv'][1] = $alias;
        }
    }
}
