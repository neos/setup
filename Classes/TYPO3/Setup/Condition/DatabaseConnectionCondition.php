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
 * Condition that checks whether connection to the configured database can be established
 */
class DatabaseConnectionCondition extends AbstractCondition
{
    /**
     * Returns TRUE if the condition is satisfied, otherwise FALSE
     *
     * @return boolean
     */
    public function isMet()
    {
        $settings = $this->configurationManager->getConfiguration(\TYPO3\Flow\Configuration\ConfigurationManager::CONFIGURATION_TYPE_SETTINGS, 'TYPO3.Flow');
        try {
            \Doctrine\DBAL\DriverManager::getConnection($settings['persistence']['backendOptions'])->connect();
        } catch (\PDOException $exception) {
            return false;
        }

        return true;
    }
}
