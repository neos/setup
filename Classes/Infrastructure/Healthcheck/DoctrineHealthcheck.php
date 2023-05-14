<?php

namespace Neos\Setup\Infrastructure\Healthcheck;

use Doctrine\DBAL\ConnectionException;
use Doctrine\DBAL\Exception as DBALException;
use Neos\Flow\Persistence\Doctrine\Service as DoctrineService;
use Neos\Setup\Domain\Health;
use Neos\Setup\Domain\HealthcheckInterface;
use Neos\Setup\Domain\Status;

class DoctrineHealthcheck implements HealthcheckInterface
{
    public function __construct(
        private readonly DoctrineService $doctrineService
    ) {
    }

    public function getTitle(): string
    {
        return 'Doctrine';
    }

    public function execute(): Health
    {
        try {
            [
                'new' => $newMigrationCount,
                'executed' => $executedMigrationCount,
                'available' => $availableMigrationCount
            ] = $this->doctrineService->getMigrationStatus();
        } catch (ConnectionException | DBALException $e) {
            return new Health(
                <<<'MSG'
                No doctrine migrations have been executed. Please run <code>./flow doctrine:migrate</code>
                MSG,
                Status::ERROR
            );
        }


        if ($executedMigrationCount === 0 && $availableMigrationCount > 0) {
            return new Health(
                <<<'MSG'
                No doctrine migrations have been executed. Please run <code>./flow doctrine:migrate</code>
                MSG,
                Status::ERROR
            );
        }

        if ($newMigrationCount > 0) {
            return new Health(
                <<<'MSG'
                Some doctrine migrations have yet to be executed. Please run <code>./flow doctrine:migrate</code>
                MSG,
                Status::WARNING
            );
        }

        return new Health(
            <<<'MSG'
            All doctrine migrations have been executed.
            MSG,
            Status::OK
        );
    }
}
