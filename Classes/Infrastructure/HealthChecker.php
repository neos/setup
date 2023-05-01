<?php

namespace Neos\Setup\Infrastructure;

use Neos\Flow\Core\Bootstrap;
use Neos\Setup\Domain\Health;
use Neos\Setup\Domain\HealthcheckInterface;
use Neos\Setup\Domain\HealthCollection;
use Neos\Setup\Domain\Status;
use Neos\Utility\PositionalArraySorter;

class HealthChecker
{
    public function __construct(
        private readonly Bootstrap $bootstrap,
        private readonly array $healthchecksConfiguration
    ) {
    }

    public function run(): HealthCollection
    {
        $sortedHealthchecksConfiguration = (new PositionalArraySorter($this->healthchecksConfiguration, 'position'))->toArray();

        $healthCollection = HealthCollection::empty();
        foreach ($sortedHealthchecksConfiguration as $identifier => $configuration) {
            $className = $configuration['className'] ?? null;
            if (!$className) {
                continue;
            }

            if (!in_array(HealthcheckInterface::class, class_implements($className), true)) {
                throw new \RuntimeException('ClassName ' . $className . ' does not implement HealthcheckInterface', 1682947890221);
            }

            /** @var HealthcheckInterface $className */
            $healthcheck = $className::fromBootstrap($this->bootstrap);
            $healt = $healthCollection->hasError()
                ? new Health('', Status::NOT_RUN)
                : $healthcheck->execute();
            $healt->title = $healthcheck->getTitle();

            $healthCollection = $healthCollection->append(
                $healt
            );
        }

        return $healthCollection;
    }
}
