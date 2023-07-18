<?php

namespace Neos\Setup\Infrastructure\Healthcheck;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception as DBALException;
use Neos\Flow\Configuration\ConfigurationManager;
use Neos\Flow\Core\Bootstrap;
use Neos\Setup\Domain\EarlyBootTimeHealthcheckInterface;
use Neos\Setup\Domain\Health;
use Neos\Setup\Domain\HealthcheckEnvironment;
use Neos\Setup\Domain\Status;

class DatabaseHealthcheck implements EarlyBootTimeHealthcheckInterface
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

    public function execute(HealthcheckEnvironment $environment): Health
    {
        $connectionSettings = $this->configurationManager->getConfiguration(
            ConfigurationManager::CONFIGURATION_TYPE_SETTINGS,
            'Neos.Flow.persistence.backendOptions'
        );

        if (!$connectionSettings || !isset($connectionSettings['dbname'])) {
            return new Health(
                <<<'MSG'
                Please configure your database in the settings or use the command <code>{{flowCommand}} setup:database</code>
                MSG,
                Status::ERROR
            );
        }

        try {
            $connection = DriverManager::getConnection($connectionSettings);
            $connection->connect();
        } catch (DBALException | \PDOException $exception) {
            $additionalInfoInSafeContext = $environment->isSafeToLeakTechnicalDetails()
                ? ' Exception: "' . $exception->getMessage() . '"'
                : '';
            return new Health(
                <<<'MSG'
                Not connected. Please check your database connection settings <code>{{flowCommand}} configuration:show --path Neos.Flow.persistence.backendOptions</code>.
                You can also rerun <code>{{flowCommand}} setup:database</code>.
                MSG . $additionalInfoInSafeContext,
                Status::ERROR
            );
        }
        return new Health('Connection up', Status::OK);
    }
}
