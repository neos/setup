<?php

declare(strict_types=1);

namespace Neos\Setup\Command;

/*
 * This file is part of the Neos.Setup package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;
use Neos\Flow\Configuration\ConfigurationManager;
use Neos\Flow\Core\Bootstrap;
use Neos\Setup\Domain\Status;
use Neos\Setup\Infrastructure\HealthChecker;

/**
 * @Flow\Scope("singleton")
 */
class WelcomeCommandController extends CommandController
{
    public function indexCommand(): void
    {
        $this->objectManager->get(Bootstrap::class);

        $bootstrap = $this->objectManager->get(Bootstrap::class);
        $configurationManager = $this->objectManager->get(ConfigurationManager::class);

        $healthchecksConfiguration = $configurationManager->getConfiguration(
            ConfigurationManager::CONFIGURATION_TYPE_SETTINGS,
            'Neos.Setup.healthchecks.compiletime'
        );

        $healthCollection = (new HealthChecker($bootstrap, $healthchecksConfiguration))->run();

        $this->outputLine(
            ($healthCollection->hasError() ? '<error>' : '<success>')
            . <<<EOT
                ....######          .######
                .....#######      ...######
                .......#######   ....######
                .........####### ....######
                ....#......#######...######
                ....##.......#######.######
                ....#####......############
                ....#####  ......##########
                ....#####    ......########
                ....#####      ......######
                .#######         ........

            Welcome to Neos.

            EOT . ($healthCollection->hasError() ? '</error>' : '</success>')
        );

        foreach ($healthCollection as $health) {
            $this->outputLine(match($health->status) {
                Status::OK => '<success>' . $health->title . '</success>',
                Status::ERROR => '<error>' . $health->title . '</error>',
                Status::UNKNOWN => '<b>' . $health->title . '</b>',
            });
            $this->outputLine($health->message);
            $this->outputLine('------');
        }
    }
}
