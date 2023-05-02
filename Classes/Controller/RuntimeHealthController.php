<?php

namespace Neos\Setup\Controller;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Core\Bootstrap;
use Neos\Flow\Mvc\Controller\ActionController;
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
        $healthCollection = (new HealthChecker($this->bootstrap, $this->healthchecksConfiguration))->run();
        $this->response->setStatusCode($healthCollection->hasError() ? 503 : 200);
        return json_encode($healthCollection, JSON_THROW_ON_ERROR);
    }
}
