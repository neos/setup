<?php

namespace Neos\Setup\Infrastructure;

use Neos\Flow\Core\Bootstrap;
use Neos\Setup\Domain\EarlyBootTimeHealthcheckInterface;
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

            $interfacesClassIsImplementing = class_implements($className);
            if (!in_array(HealthcheckInterface::class, $interfacesClassIsImplementing, true)) {
                throw new \RuntimeException('ClassName ' . $className . ' does not implement HealthcheckInterface', 1682947890221);
            }
            if (in_array(EarlyBootTimeHealthcheckInterface::class, $interfacesClassIsImplementing, true)) {
                /** @var class-string<EarlyBootTimeHealthcheckInterface>|EarlyBootTimeHealthcheckInterface $className */
                $healthcheck = $className::fromBootstrap($this->bootstrap);
            } else {
                /** @var class-string<HealthcheckInterface> $className */
                $healthcheck = $this->bootstrap->getObjectManager()->get($className);
            }
            $healt = $healthCollection->hasError()
                ? new Health('', Status::NOT_RUN)
                : $healthcheck->execute();
            $healt->title = $healthcheck->getTitle();

            $healthCollection = $healthCollection->withEntry(
                $identifier,
                $healt
            );
        }

        return $healthCollection;
    }
}
