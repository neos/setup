<?php

namespace Neos\Setup\Controller;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Core\Bootstrap;
use Neos\Flow\Mvc\Controller\ActionController;
use Neos\Setup\Domain\HealthcheckEnvironment;
use Neos\Setup\Domain\WebEnvironment;
use Neos\Setup\Infrastructure\HealthChecker;

class RuntimeHealthController extends ActionController
{
    /**
     * @Flow\InjectConfiguration(package="Neos.Setup", path="healthchecks.runtime")
     */
    protected array $healthchecksConfiguration = [];

    /**
     * @Flow\Inject
     */
    protected Bootstrap $bootstrap;

    public function indexAction(): string
    {
        $healthcheckEnvironment = new HealthcheckEnvironment(
            applicationContext: $this->bootstrap->getContext(),
            executionEnvironment: new WebEnvironment(
                requestUri: $this->request->getHttpRequest()->getUri(),
                isWindows: PHP_OS_FAMILY === 'Windows'
            )
        );
        $healthCollection = (new HealthChecker($this->bootstrap, $this->healthchecksConfiguration, $healthcheckEnvironment))->execute();
        $this->response->setStatusCode($healthCollection->hasError() ? 503 : 200);
        return json_encode($healthCollection, JSON_THROW_ON_ERROR);
    }
}
