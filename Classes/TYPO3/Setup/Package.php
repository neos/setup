<?php
namespace TYPO3\Setup;

/*
 * This file is part of the TYPO3.Setup package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use TYPO3\Flow\Package\Package as BasePackage;
use TYPO3\Flow\Annotations as Flow;

/**
 * Package base class of the TYPO3.Setup package.
 *
 * @Flow\Scope("singleton")
 */
class Package extends BasePackage {

	/**
	 * Invokes custom PHP code directly after the package manager has been initialized.
	 *
	 * @param \TYPO3\Flow\Core\Bootstrap $bootstrap The current bootstrap
	 * @return void
	 */
	public function boot(\TYPO3\Flow\Core\Bootstrap $bootstrap) {
		$bootstrap->registerRequestHandler(new \TYPO3\Setup\Core\RequestHandler($bootstrap));
	}

}
