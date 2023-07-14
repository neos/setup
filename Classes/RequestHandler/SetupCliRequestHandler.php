<?php

namespace Neos\Setup\RequestHandler;

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
use Neos\Flow\Cli\ConsoleOutput;
use Neos\Flow\Configuration\ConfigurationManager;
use Neos\Flow\Core\Booting\Scripts;
use Neos\Flow\Core\Bootstrap;
use Neos\Flow\Core\RequestHandlerInterface;
use Neos\Setup\Domain\CliEnvironment;
use Neos\Setup\Domain\Health;
use Neos\Setup\Domain\HealthcheckEnvironment;
use Neos\Setup\Domain\HealthCollection;
use Neos\Setup\Domain\Status;
use Neos\Setup\Infrastructure\HealthChecker;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;

/**
 * @Flow\Scope("singleton")
 * @Flow\Proxy(false)
 */
class SetupCliRequestHandler implements RequestHandlerInterface
{
    private Bootstrap $bootstrap;

    private ConfigurationManager $configurationManager;

    private ConsoleOutput $output;

    public function __construct(Bootstrap $bootstrap)
    {
        $this->bootstrap = $bootstrap;
    }

    public function canHandleRequest(): bool
    {
        if (PHP_SAPI !== 'cli') {
            return false;
        }
        $commandIdentifier = $_SERVER['argv'][1] ?? null;
        return match($commandIdentifier) {
            'neos.setup:setup:index',
            'setup:setup:index',
            'setup:index' => true,
            default => false,
        };
    }

    /**
     * Overrules the default cli request chandler
     *
     * @return integer
     */
    public function getPriority()
    {
        return 300;
    }


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
        $healthchecksConfiguration = $this->configurationManager->getConfiguration(
            ConfigurationManager::CONFIGURATION_TYPE_SETTINGS,
            'Neos.Setup.healthchecks.compiletime'
        );
        $healthcheckEnvironment = new HealthcheckEnvironment(
            applicationContext: $this->bootstrap->getContext(),
            executionEnvironment: new CliEnvironment()
        );
        $compiletimeHealthCollection = (new HealthChecker($this->bootstrap, $healthchecksConfiguration, $healthcheckEnvironment))->execute();

        $formatter = $this->output->getOutput()->getFormatter();
        $formatter->setStyle('code', new OutputFormatterStyle('black', 'white'));
        $formatter->setStyle('warning', new OutputFormatterStyle('yellow'));

        // coloring the neos logo:
        $formatter->setStyle('neos', new OutputFormatterStyle('cyan'));
        $colorizedNeos = preg_replace('/#+/', '<neos>$0</neos>', self::NEOS);

        $this->output->outputLine($colorizedNeos);
        $this->printHealthCollection($compiletimeHealthCollection);

        $hasError = $compiletimeHealthCollection->hasError();

        if (!$hasError) {
            ob_start();
            $success = Scripts::executeCommand(
                'neos.setup:setup:executeruntimehealthchecks',
                $this->configurationManager->getConfiguration(ConfigurationManager::CONFIGURATION_TYPE_SETTINGS, 'Neos.Flow')
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
            $this->output->outputLine('<error>Neos setup not complete.</error>');
        }

        $this->output->outputLine('You can rerun this command anytime via <code>./flow setup</code>');
    }


    private function printHealthCollection(HealthCollection $healthCollection): void
    {
        foreach ($healthCollection as $health) {
            $this->output->outputLine(match ($health->status) {
                Status::OK => '<success>' . $health->title . '</success>',
                Status::ERROR => '<error>' . $health->title . '</error>',
                Status::WARNING => '<warning>' . $health->title . '</warning>',
                Status::NOT_RUN => '<b>' . $health->title . '</b> (not run)',
                Status::UNKNOWN => '<b>' . $health->title . '</b>',
            });

            if ($health->status === Status::NOT_RUN) {
                $this->output->outputLine();
                continue;
            }

            $this->output->outputLine($health->message);
            $this->output->outputLine();
        }
    }

    public function handleRequest()
    {
        Scripts::initializeConfiguration($this->bootstrap, false);
        Scripts::initializeSystemLogger($this->bootstrap);

        $this->configurationManager = $this->bootstrap->getEarlyInstance(ConfigurationManager::class);
        $this->output = new ConsoleOutput();

        $this->indexCommand();
        die();
    }
}
