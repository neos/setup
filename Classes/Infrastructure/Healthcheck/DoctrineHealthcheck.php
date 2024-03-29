<?php

namespace Neos\Setup\Infrastructure\Healthcheck;

use Doctrine\DBAL\Exception as DBALException;
use Neos\Flow\Persistence\Doctrine\Service as DoctrineService;
use Neos\Setup\Domain\Health;
use Neos\Setup\Domain\HealthcheckEnvironment;
use Neos\Setup\Domain\HealthcheckInterface;
use Neos\Setup\Domain\Status;

class DoctrineHealthcheck implements HealthcheckInterface
{
    private const ACCEPTABLE_NEW_MIGRATION_COUNT = 10;

    public function __construct(
        private DoctrineService $doctrineService
    ) {
    }

    public function getTitle(): string
    {
        return 'Doctrine';
    }

    public function execute(HealthcheckEnvironment $environment): Health
    {
        try {
            [
                'new' => $newMigrationCount,
                'executed' => $executedMigrationCount,
                'available' => $availableMigrationCount
            ] = $this->doctrineService->getMigrationStatus();
        } catch (DBALException | \PDOException) {
            throw new \RuntimeException('The DoctrineHealthcheck must be only executed, if the Database connection is know to work.', 1684075689386);
        }

        if ($executedMigrationCount === 0 && $availableMigrationCount > 0) {
            return new Health(
                <<<'MSG'
                No doctrine migrations have been executed. Please run <code>{{flowCommand}} doctrine:migrate</code>
                MSG,
                Status::ERROR()
            );
        }

        if ($newMigrationCount > self::ACCEPTABLE_NEW_MIGRATION_COUNT) {
            return new Health(
                <<<'MSG'
                Many doctrine migrations have yet to be executed. Please run <code>{{flowCommand}} doctrine:migrate</code>
                MSG,
                Status::ERROR()
            );
        }

        if ($newMigrationCount > 0 && $newMigrationCount <= self::ACCEPTABLE_NEW_MIGRATION_COUNT) {
            return new Health(
                <<<'MSG'
                Few doctrine migrations have yet to be executed. Please run <code>{{flowCommand}} doctrine:migrate</code>
                MSG,
                Status::WARNING()
            );
        }

        return new Health(
            <<<'MSG'
            All doctrine migrations have been executed.
            MSG,
            Status::OK()
        );
    }
}
