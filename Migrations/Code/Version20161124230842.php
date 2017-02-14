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
 * Adjusts code to Setup Renaming
 */
class Version20161124230842 extends AbstractMigration
{
    /**
     * @return string
     */
    public function getIdentifier()
    {
        return 'Neos.Setup-20161124230842';
    }

    /**
     * @return void
     */
    public function up()
    {
        $this->searchAndReplace('TYPO3\Setup', 'Neos\Setup');
        $this->searchAndReplace('TYPO3.Setup', 'Neos.Setup');
    }
}
