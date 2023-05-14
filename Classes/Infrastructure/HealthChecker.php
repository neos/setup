<?php

namespace Neos\Setup\Infrastructure;

use GuzzleHttp\Psr7\ServerRequest;
use Neos\Flow\Core\Bootstrap;
use Neos\Flow\Http\HttpRequestHandlerInterface;
use Neos\Setup\Domain\CliEnvironment;
use Neos\Setup\Domain\EarlyBootTimeHealthcheckInterface;
use Neos\Setup\Domain\Health;
use Neos\Setup\Domain\HealthcheckEnvironment;
use Neos\Setup\Domain\HealthcheckInterface;
use Neos\Setup\Domain\HealthCollection;
use Neos\Setup\Domain\Status;
use Neos\Setup\Domain\WebEnvironment;
use Neos\Utility\PositionalArraySorter;

class HealthChecker
{
    private readonly HealthcheckEnvironment $healthcheckEnvironment;

    public function __construct(
        private readonly Bootstrap $bootstrap,
        private readonly array $healthchecksConfiguration
    ) {
        if (PHP_SAPI === 'cli') {
            $executionEnvironment = new CliEnvironment();
        } else {
            $activeRequestHandler = $this->bootstrap->getActiveRequestHandler();
            $requestUri = $activeRequestHandler instanceof HttpRequestHandlerInterface
                ? $activeRequestHandler->getHttpRequest()->getUri()
                : ServerRequest::getUriFromGlobals();
            $executionEnvironment = new WebEnvironment(
                requestUri: $requestUri
            );
        }
        $this->healthcheckEnvironment = new HealthcheckEnvironment(
            applicationContext: $this->bootstrap->getContext(),
            executionEnvironment: $executionEnvironment
        );
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
            $health = $healthCollection->hasError()
                ? new Health('', Status::NOT_RUN)
                : $healthcheck->execute($this->healthcheckEnvironment);
            $health->title = $healthcheck->getTitle();

            $healthCollection = $healthCollection->withEntry(
                $identifier,
                $health
            );
        }

        return $healthCollection;
    }
}
