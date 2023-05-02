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
use Neos\Flow\Core\Booting\Scripts;
use Neos\Flow\Core\Bootstrap;
use Neos\Setup\Domain\Health;
use Neos\Setup\Domain\HealthCollection;
use Neos\Setup\Domain\Status;
use Neos\Setup\Infrastructure\HealthChecker;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;

/**
 * @Flow\Scope("singleton")
 */
class WelcomeCommandController extends CommandController
{

    private const NEOS = <<<EOT

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

    EOT;

    public function indexCommand(): void
    {
        $this->objectManager->get(Bootstrap::class);
        $bootstrap = $this->objectManager->get(Bootstrap::class);
        $configurationManager = $this->objectManager->get(ConfigurationManager::class);
        $healthchecksConfiguration = $configurationManager->getConfiguration(
            ConfigurationManager::CONFIGURATION_TYPE_SETTINGS,
            'Neos.Setup.healthchecks.compiletime'
        );
        $compiletimeHealthCollection = (new HealthChecker($bootstrap, $healthchecksConfiguration))->run();

        $formatter = $this->output->getOutput()->getFormatter();
        $formatter->setStyle('code', new OutputFormatterStyle('black', 'white'));
        $formatter->setStyle('warning', new OutputFormatterStyle('yellow'));
        $formatter->setStyle('neos', new OutputFormatterStyle('cyan'));

        $colorizedNeos = preg_replace('/#+/', '<neos>$0</neos>', self::NEOS);

        $this->outputLine($colorizedNeos);
        $this->printHealthCollection($compiletimeHealthCollection);

        $hasError = $compiletimeHealthCollection->hasError();

        if (!$hasError) {
            ob_start();
            $success = Scripts::executeCommand(
                'neos.setup:welcome:healthcheckruntime',
                $configurationManager->getConfiguration(ConfigurationManager::CONFIGURATION_TYPE_SETTINGS, 'Neos.Flow')
            );
            $json = ob_get_clean();

            if ($success) {
                $runtimeHealthCollection = HealthCollection::fromJsonString($json);
                $hasError = $runtimeHealthCollection->hasError();
                $this->printHealthCollection($runtimeHealthCollection);
            } else {
                $this->printHealthCollection(new HealthCollection(new Health(
                    message: "Flow didn't respond as expected.",
                    status: Status::ERROR,
                    title: 'Flow Framework'
                )));
            }
        }

        if ($hasError) {
            $this->outputLine('<error>Neos setup not complete.</error>');
        }

        $this->outputLine('You can rerun this command anytime via <code>./flow setup</code>');
    }

    public function healthcheckRuntimeCommand(): void
    {
        $this->objectManager->get(Bootstrap::class);
        $bootstrap = $this->objectManager->get(Bootstrap::class);
        $configurationManager = $this->objectManager->get(ConfigurationManager::class);

        $healthchecksConfiguration = $configurationManager->getConfiguration(
            ConfigurationManager::CONFIGURATION_TYPE_SETTINGS,
            'Neos.Setup.healthchecks.runtime'
        );

        $healthCollection = (new HealthChecker($bootstrap, $healthchecksConfiguration))->run();

        $this->output(json_encode($healthCollection, JSON_THROW_ON_ERROR));
    }

    private function printHealthCollection(HealthCollection $healthCollection): void
    {
        foreach ($healthCollection as $health) {
            $this->outputLine(match ($health->status) {
                Status::OK => '<success>' . $health->title . '</success>',
                Status::ERROR => '<error>' . $health->title . '</error>',
                Status::WARNING => '<warning>' . $health->title . '</warning>',
                Status::NOT_RUN,
                Status::UNKNOWN => '<b>' . $health->title . '</b>',
            });
            $this->outputFormatted($health->message, [], 2);
            $this->outputLine();
        }
    }
}
