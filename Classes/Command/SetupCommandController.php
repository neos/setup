<?php
declare(strict_types=1);

namespace Neos\Setup\Command;

/*
 * This file is part of the Neos.CliSetup package.
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
use Neos\Setup\Domain\CliEnvironment;
use Neos\Setup\Domain\HealthcheckEnvironment;
use Neos\Setup\Infrastructure\HealthChecker;
use Neos\Setup\RequestHandler\SetupCliRequestHandler;
use Neos\Utility\Arrays;
use Neos\Setup\Exception as SetupException;
use Neos\Setup\Infrastructure\Database\DatabaseConnectionService;
use Symfony\Component\Yaml\Yaml;

class SetupCommandController extends CommandController
{
    #[Flow\Inject]
    protected DatabaseConnectionService $databaseConnectionService;

    #[Flow\InjectConfiguration(path: "persistence.backendOptions", package: "Neos.Flow")]
    protected array $persistenceConfiguration;

    #[Flow\Inject]
    protected Bootstrap $bootstrap;

    /**
     * Show information about the system health
     */
    public function indexCommand(): void
    {
        /**
         * This code is never reached, as we have a custom request handler.
         * We add this method for documentation and transparency.
         * @see SetupCliRequestHandler
         */
    }

    /**
     * Configure the database connection for flow persistence
     *
     * @param string|null $driver Driver
     * @param string|null $host Hostname or IP
     * @param string|null $dbname Database name
     * @param string|null $user Username
     * @param string|null $password Password
     */
    public function databaseCommand(?string $driver = null, ?string $host = null, ?string $dbname = null, ?string $user = null, ?string $password = null): void
    {
        $availableDrivers = $this->databaseConnectionService->getAvailableDrivers();
        if (count($availableDrivers) == 0) {
            $this->outputLine('No supported database driver found');
            $this->quit(1);
        }

        if (is_null($driver)) {
            $driver = $this->output->select(
                sprintf('DB Driver (<info>%s</info>): ', $this->persistenceConfiguration['driver'] ?? '---'),
                $availableDrivers,
                $this->persistenceConfiguration['driver']
            );
        }

        if (is_null($host)) {
            $host = $this->output->ask(
                sprintf('Host (<info>%s</info>): ', $this->persistenceConfiguration['host'] ?? '127.0.0.1'),
                $this->persistenceConfiguration['host'] ?? '127.0.0.1'
            );
        }

        if (is_null($dbname)) {
            $dbname = $this->output->ask(
                sprintf('Database (<info>%s</info>): ', $this->persistenceConfiguration['dbname'] ?? '---'),
                $this->persistenceConfiguration['dbname']
            );
        }

        if (is_null($user)) {
            $user = $this->output->ask(
                sprintf('Username (<info>%s</info>): ', $this->persistenceConfiguration['user'] ?? '---'),
                $this->persistenceConfiguration['user']
            );
        }

        if (is_null($password)) {
            $password = $this->output->ask(
                sprintf('Password (<info>%s</info>): ', $this->persistenceConfiguration['password'] ?? '---'),
                $this->persistenceConfiguration['password']
            );
        }

        $persistenceConfiguration = [
            'driver' => $driver,
            'host' => $host,
            'dbname' => $dbname,
            'user' => $user,
            'password' => $password
        ];

        // postgres does not know utf8mb4
        if ($driver == 'pdo_pgsql') {
            $persistenceConfiguration['charset'] = 'utf8';
            $persistenceConfiguration['defaultTableOptions']['charset'] = 'utf8';
        }

        $this->outputLine();

        try {
            $this->databaseConnectionService->verifyDatabaseConnectionWorks($persistenceConfiguration);
            $this->outputLine(sprintf('Database <info>%s</info> was connected sucessfully.', $persistenceConfiguration['dbname']));
        } catch (SetupException $exception) {
            try {
                $this->databaseConnectionService->createDatabaseAndVerifyDatabaseConnectionWorks($persistenceConfiguration);
                $this->outputLine(sprintf('Database <info>%s</info> was sucessfully created.', $persistenceConfiguration['dbname']));
            } catch (SetupException $exception) {
                $this->outputLine(sprintf(
                    'Database <info>%s</info> could not be created. Please check the permissions for user <info>%s</info>. Exception: <info>%s</info>',
                    $persistenceConfiguration['dbname'],
                    $persistenceConfiguration['user'],
                    $exception->getMessage()
                ));
                $this->quit(1);
            }
        }

        $filename = sprintf('%s%s/Settings.Database.yaml', FLOW_PATH_CONFIGURATION, $this->bootstrap->getContext()->__toString());

        $this->outputLine();
        $this->output(sprintf('<info>%s</info>', $this->writeSettings($filename, 'Neos.Flow.persistence.backendOptions', $persistenceConfiguration)));
        $this->outputLine();
        $this->outputLine(sprintf('The new database settings were written to <info>%s</info>', $filename));
    }

    /**
     * @Flow\Internal
     * @internal
     */
    public function executeRuntimeHealthchecksCommand(): void
    {
        $this->objectManager->get(Bootstrap::class);
        $bootstrap = $this->objectManager->get(Bootstrap::class);
        $configurationManager = $this->objectManager->get(ConfigurationManager::class);

        $healthchecksConfiguration = $configurationManager->getConfiguration(
            ConfigurationManager::CONFIGURATION_TYPE_SETTINGS,
            'Neos.Setup.healthchecks.runtime'
        );
        $healthcheckEnvironment = new HealthcheckEnvironment(
            applicationContext: $bootstrap->getContext(),
            executionEnvironment: new CliEnvironment(
                PHP_OS_FAMILY === 'Windows'
            )
        );
        $healthCollection = (new HealthChecker($bootstrap, $healthchecksConfiguration, $healthcheckEnvironment))->execute();

        $this->output(json_encode($healthCollection, JSON_THROW_ON_ERROR));
    }

    /**
     * Write the settings to the given path, existing configuration files are created or modified
     *
     * @param string $filename The filename the settings are stored in
     * @param string $path The configuration path
     * @param mixed $settings The actual settings to write
     * @return string The added yaml code
     */
    private function writeSettings(string $filename, string $path, $settings): string
    {
        if (file_exists($filename)) {
            $previousSettings = Yaml::parseFile($filename) ?? [];
        } else {
            $previousSettings = [];
        }
        $newSettings = Arrays::setValueByPath($previousSettings, $path, $settings);
        file_put_contents($filename, YAML::dump($newSettings, 10, 2));
        return YAML::dump(Arrays::setValueByPath([], $path, $settings), 10, 2);
    }
}
