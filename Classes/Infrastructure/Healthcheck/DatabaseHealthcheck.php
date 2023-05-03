<?php

namespace Neos\Setup\Infrastructure\Healthcheck;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception as DBALException;
use Neos\Flow\Configuration\ConfigurationManager;
use Neos\Flow\Core\Bootstrap;
use Neos\Setup\Domain\CompiletimeHealthcheckInterface;
use Neos\Setup\Domain\Health;
use Neos\Setup\Domain\Status;

class DatabaseHealthcheck implements CompiletimeHealthcheckInterface
{
    public function __construct(
        private readonly ConfigurationManager $configurationManager
    ) {
    }

    public static function fromBootstrap(Bootstrap $bootstrap): self
    {
        return new self(
            $bootstrap->getEarlyInstance(ConfigurationManager::class)
        );
    }

    public function getTitle(): string
    {
        return 'Database';
    }

    public function execute(): Health
    {
        $connectionSettings = $this->configurationManager->getConfiguration(
            ConfigurationManager::CONFIGURATION_TYPE_SETTINGS,
            'Neos.Flow.persistence.backendOptions'
        );

        if (!$connectionSettings) {
            return new Health(
                <<<'MSG'
                Please configure your database in the settings or use the command <code>./flow setup:database</code>
                MSG,
                Status::ERROR
            );
        }

        try {
            $connection = DriverManager::getConnection($connectionSettings);
            $connection->connect();
        } catch (DBALException | \PDOException) {
            return new Health(
                <<<'MSG'
                Please check your database settings. You can also rerun <code>./flow setup:database</code>
                MSG,
                Status::ERROR
            );
        }
        return new Health('Connection up', Status::OK);
    }
}
