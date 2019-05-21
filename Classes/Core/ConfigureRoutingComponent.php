<?php
namespace Neos\Setup\Core;

/*
 * This file is part of the Neos.Setup package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Configuration\ConfigurationManager;
use Neos\Flow\Http\Component\ComponentContext;
use Neos\Flow\Http\Component\ComponentInterface;
use Neos\Flow\Mvc\Routing\Router;
use Neos\Flow\ObjectManagement\ObjectManagerInterface;
use Neos\Flow\Package\PackageManager;

/**
 * Configure routing HTTP component for Neos setup
 */
class ConfigureRoutingComponent implements ComponentInterface
{
    /**
     * @Flow\Inject
     * @var Router
     */
    protected $router;

    /**
     * @Flow\Inject
     * @var ConfigurationManager
     */
    protected $configurationManager;

    /**
     * @Flow\Inject
     * @var ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @Flow\Inject
     * @var PackageManager
     */
    protected $packageManager;

    /**
     * @var array
     */
    protected $options;

    /**
     * @param array $options
     */
    public function __construct(array $options = [])
    {
        $this->options = $options;
    }

    /**
     * Set the routes configuration for the Neos setup and configures the routing component
     * to skip initialisation, which would overwrite the specific settings again.
     *
     * @param ComponentContext $componentContext
     * @return void
     */
    public function handle(ComponentContext $componentContext)
    {
        $configurationSource = $this->objectManager->get(\Neos\Flow\Configuration\Source\YamlSource::class);
        $routesConfiguration = $configurationSource->load($this->packageManager->getPackage('Neos.Setup')->getConfigurationPath() . ConfigurationManager::CONFIGURATION_TYPE_ROUTES);
        $this->router->setRoutesConfiguration($routesConfiguration);
        $componentContext->setParameter(\Neos\Flow\Mvc\Routing\RoutingComponent::class, 'skipRouterInitialization', true);
    }
}
