<?php
namespace TYPO3\Setup\Condition;

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

/**
 * Condition that checks if the PDO driver is loaded and if there are available drivers
 */
class PdoDriverCondition extends AbstractCondition {

	/**
	 * Returns TRUE if the condition is satisfied, otherwise FALSE
	 *
	 * @return boolean
	 */
	public function isMet() {
		if (defined('PDO::ATTR_DRIVER_NAME') === FALSE || \PDO::getAvailableDrivers() === array()) {
			return FALSE;
		}
		return TRUE;
	}

}
