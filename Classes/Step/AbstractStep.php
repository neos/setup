<?php
namespace Neos\Setup\Step;

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
use Neos\Form\Core\Model\FormDefinition;
use Neos\Form\Exception\PresetNotFoundException;
use Neos\Form\Finishers\ClosureFinisher;
use Neos\Utility\Arrays;

/**
 * @Flow\Scope("singleton")
 */
abstract class AbstractStep implements StepInterface
{
    /**
     * @var boolean
     */
    protected $optional = false;

    /**
     * The settings of the Neos.Form package
     *
     * @var array
     */
    protected $formSettings;

    /**
     * @Flow\Inject
     * @var \Neos\Flow\Configuration\ConfigurationManager
     */
    protected $configurationManager;

    /**
     * @var array
     */
    protected $options;

    /**
     * @var array
     */
    protected $distributionSettings;

    /**
     * @var string
     */
    protected $presetName = 'neos.setup';

    /**
     * @return void
     * @internal
     */
    public function initializeObject()
    {
        $this->formSettings = $this->configurationManager->getConfiguration(ConfigurationManager::CONFIGURATION_TYPE_SETTINGS, 'Neos.Form');
    }

    /**
     * Sets options of this step
     *
     * @param array $options
     * @return void
     * @api
     */
    public function setOptions(array $options)
    {
        $this->options = $options;
    }

    /**
     * Sets global settings of the Flow distribution
     *
     * @param array $distributionSettings
     * @return void
     * @api
     */
    public function setDistributionSettings(array $distributionSettings)
    {
        $this->distributionSettings = $distributionSettings;
    }

    /**
     * Get the preset configuration by $presetName, taking the preset hierarchy
     * (specified by *parentPreset*) into account.
     *
     * @param string $presetName name of the preset to get the configuration for
     * @return array the preset configuration
     * @throws \Neos\Form\Exception\PresetNotFoundException if preset with the name $presetName was not found
     * @api
     */
    public function getPresetConfiguration($presetName)
    {
        if (!isset($this->formSettings['presets'][$presetName])) {
            throw new PresetNotFoundException(sprintf('The Preset "%s" was not found underneath Neos: Form: presets.', $presetName), 1332170104);
        }
        $preset = $this->formSettings['presets'][$presetName];
        if (isset($preset['parentPreset'])) {
            $parentPreset = $this->getPresetConfiguration($preset['parentPreset']);
            unset($preset['parentPreset']);
            $preset = Arrays::arrayMergeRecursiveOverrule($parentPreset, $preset);
        }

        return $preset;
    }

    /**
     * Returns the form definitions for the step
     *
     * @param \Closure $callback closure to be invoked when the form has been submitted successfully
     * @return \Neos\Form\Core\Model\FormDefinition
     * @api
     */
    final public function getFormDefinition(\Closure $callback)
    {
        $fullyQualifiedClassName = get_class($this);
        $formIdentifier = lcfirst(substr($fullyQualifiedClassName, strrpos($fullyQualifiedClassName, '\\') + 1));
        $formConfiguration = $this->getPresetConfiguration($this->presetName);
        $formDefinition = new FormDefinition($formIdentifier, $formConfiguration);
        $this->buildForm($formDefinition);

        $closureFinisher = new ClosureFinisher();
        $closureFinisher->setOption('closure', $callback);
        $formDefinition->addFinisher($closureFinisher);

        return $formDefinition;
    }

    /**
     * @abstract
     * @param \Neos\Form\Core\Model\FormDefinition $formDefinition
     * @return void
     * @api
     */
    abstract protected function buildForm(FormDefinition $formDefinition);

    /**
     * This method is called when the form of this step has been submitted
     * You can override it in your concrete implementation
     *
     * @param array $formValues
     * @return void
     * @api
     */
    public function postProcessFormValues(array $formValues)
    {
    }

    /**
     * @return boolean
     * @api
     */
    public function isOptional()
    {
        return $this->optional;
    }
}
