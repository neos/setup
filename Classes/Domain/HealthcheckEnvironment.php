<?php

namespace Neos\Setup\Domain;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Core\ApplicationContext;

/** @Flow\Proxy(false) */

class HealthcheckEnvironment
{
    public function __construct(
        private ApplicationContext $applicationContext,
        /** @psalm-readonly */
        public CliEnvironment | WebEnvironment $executionEnvironment
    ) {
    }

    /**
     * While developing It's not critical to expose details about the setup like paths, stack-traces or configuration via web endpoints.
     * For example is this is already beeing done in exception messages.
     *
     * Each executed Healthcheck {@see HealthcheckInterface::execute()} is only allowed to return unsafe information,
     * if we are in a safe environment e.g. when this method returns true.
     */
    public function isSafeToLeakTechnicalDetails(): bool
    {
        return $this->executionEnvironment instanceof CliEnvironment
            || !$this->applicationContext->isProduction();
    }
}
