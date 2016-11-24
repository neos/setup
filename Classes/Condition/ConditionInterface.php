<?php
namespace Neos\Setup\Condition;

/*
 * This file is part of the Neos.Setup package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

/**
 * Contract for Step Conditions
 */
interface ConditionInterface
{
    /**
     * Sets options of this condition
     *
     * @param array $options
     * @return void
     */
    public function setOptions(array $options);

    /**
     * Returns TRUE if the condition is satisfied, otherwise FALSE
     *
     * @return boolean
     * @api
     */
    public function isMet();
}
