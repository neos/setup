<?php
namespace Neos\Flow\Core\Migrations;

/*
 * This file is part of the Neos.Flow package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

/**
 * Adjusts settings path from TYPO3.Setup to Neos.Setup to Setup Renaming
 */
class Version20161125014759 extends AbstractMigration
{
    /**
     * @return string
     */
    public function getIdentifier()
    {
        return 'Neos.Setup-20161125014759';
    }

    /**
     * @return void
     */
    public function up()
    {
        $this->moveSettingsPaths('TYPO3.Setup', 'Neos.Setup');
    }
}
