<?php

namespace Neos\Setup\Infrastructure\Healthcheck;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception as DBALException;
use Neos\Flow\Configuration\ConfigurationManager;
use Neos\Flow\Core\Bootstrap;
use Neos\Setup\Domain\Health;
use Neos\Setup\Domain\HealthcheckInterface;
use Neos\Setup\Domain\Status;

class DatabaseHealthcheck implements HealthcheckInterface
{
    public function __construct(
        private readonly ConfigurationManager $configurationManager
    ) {
    }

    public static function fromBootstrap(Bootstrap $bootstrap): HealthcheckInterface
    {
        return new self(
            $bootstrap->getObjectManager()->get(ConfigurationManager::class)
        );
    }

    public function getTitle(): string
    {
        return 'Database';
    }

    public function execute(): Health
    {
        $settings = $this->configurationManager->getConfiguration(ConfigurationManager::CONFIGURATION_TYPE_SETTINGS, 'Neos.Flow');
        $connectionSettings = $settings['persistence']['backendOptions'] ?? null;

        if (!$connectionSettings) {
            return new Health(
                $this->getTitle(),
                <<<'MSG'
                please configure your database in the settings or use the command <i>./flow setup:database</i>
                MSG,
                Status::ERROR
            );
        }

        try {
            $connection = DriverManager::getConnection($connectionSettings);
            $connection->connect();
        } catch (DBALException | \PDOException) {
            return new Health(
                $this->getTitle(),
                <<<'MSG'
                please check your database settings. You can also rerun <i>./flow setup:database</i>
                MSG,
                Status::ERROR
            );
        }
        return new Health($this->getTitle(), 'Connection up', Status::OK);
    }
}
