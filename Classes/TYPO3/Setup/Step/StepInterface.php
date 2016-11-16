<?php
namespace TYPO3\Setup\Step;

/*
 * This file is part of the TYPO3.Setup package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

/**
 * A contract for setup steps.
 */
interface StepInterface
{
    /**
     * Sets options of this step
     *
     * @param array $options
     * @return void
     */
    public function setOptions(array $options);

    /**
     * Sets global settings of the Flow distribution
     *
     * @param array $distributionSettings
     * @return void
     */
    public function setDistributionSettings(array $distributionSettings);

    /**
     * Returns the form definitions for the step
     *
     * @param \Closure $callback
     * @return \TYPO3\Form\Core\Model\FormDefinition
     */
    public function getFormDefinition(\Closure $callback);

    /**
     * This method is called when the form of this step has been submitted
     *
     * @param array $formValues
     * @return void
     */
    public function postProcessFormValues(array $formValues);

    /**
     * @return boolean
     */
    public function isOptional();
}
