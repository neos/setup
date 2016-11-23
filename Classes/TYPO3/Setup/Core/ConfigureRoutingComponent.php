<?php
namespace TYPO3\Setup\Core;

/*
 * This file is part of the TYPO3.Setup package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Configuration\ConfigurationManager;
use TYPO3\Flow\Http\Component\ComponentContext;
use TYPO3\Flow\Http\Component\ComponentInterface;
use TYPO3\Flow\Mvc\Routing\Router;
use TYPO3\Flow\ObjectManagement\ObjectManagerInterface;
use TYPO3\Flow\Package\PackageManagerInterface;

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
     * @var PackageManagerInterface
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
        $configurationSource = $this->objectManager->get(\TYPO3\Flow\Configuration\Source\YamlSource::class);
        $routesConfiguration = $configurationSource->load($this->packageManager->getPackage('TYPO3.Setup')->getConfigurationPath() . ConfigurationManager::CONFIGURATION_TYPE_ROUTES);
        $this->router->setRoutesConfiguration($routesConfiguration);
        $componentContext->setParameter(\TYPO3\Flow\Mvc\Routing\RoutingComponent::class, 'skipRouterInitialization', true);
    }
}
