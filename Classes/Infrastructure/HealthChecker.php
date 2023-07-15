<?php

namespace Neos\Setup\Infrastructure;

use Neos\Flow\Core\Bootstrap;
use Neos\Flow\Log\ThrowableStorageInterface;
use Neos\Setup\Domain\EarlyBootTimeHealthcheckInterface;
use Neos\Setup\Domain\Health;
use Neos\Setup\Domain\HealthcheckEnvironment;
use Neos\Setup\Domain\HealthcheckInterface;
use Neos\Setup\Domain\HealthCollection;
use Neos\Setup\Domain\Status;
use Neos\Utility\PositionalArraySorter;

class HealthChecker
{
    public function __construct(
        private readonly Bootstrap $bootstrap,
        private readonly array $configuredHealthchecks,
        private readonly HealthcheckEnvironment $healthcheckEnvironment
    ) {
    }

    public function execute(): HealthCollection
    {
        $sortedConfiguredHealthchecks = (new PositionalArraySorter($this->configuredHealthchecks, 'position'))->toArray();

        $healthCollection = HealthCollection::empty();
        foreach ($sortedConfiguredHealthchecks as $identifier => $configuration) {
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

            if ($healthCollection->hasError()) {
                $healthCollection = $healthCollection->withEntry(
                    $identifier,
                    new Health(
                        message: '',
                        status: Status::NOT_RUN,
                        title: $healthcheck->getTitle()
                    )
                );
                continue;
            }

            try {
                $health = $healthcheck->execute($this->healthcheckEnvironment);
            } catch (\Throwable $throwable) {
                $message = $this->bootstrap->getEarlyInstance(ThrowableStorageInterface::class)->logThrowable($throwable);

                $healthCollection = $healthCollection->withEntry(
                    $identifier,
                    new Health(
                        message: nl2br($message),
                        status: Status::ERROR,
                        title: $healthcheck->getTitle()
                    )
                );
                continue;
            }

            $healthCollection = $healthCollection->withEntry(
                $identifier,
                new Health(
                    message: FlowInvocationCommand::forEnvironment(isWindows: $this->healthcheckEnvironment->executionEnvironment->isWindows)
                        ->replaceCommandPlaceHolders($health->message),
                    status: $health->status,
                    title: $healthcheck->getTitle()
                )
            );
        }

        return $healthCollection;
    }
}
