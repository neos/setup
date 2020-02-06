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
use Neos\Form\Core\Model\FormDefinition;

/**
 * @Flow\Scope("singleton")
 */
class FinalStep extends AbstractStep
{
    /**
     * Returns the form definitions for the step
     *
     * @param \Neos\Form\Core\Model\FormDefinition $formDefinition
     * @return void
     */
    protected function buildForm(FormDefinition $formDefinition)
    {
        $page1 = $formDefinition->createPage('page1');
        $page1->setRenderingOption('header', 'Setup complete');

        $title = $page1->createElement('connectionSection', 'Neos.Form:Section');
        $title->setLabel('Congratulations');

        $success = $title->createElement('success', 'Neos.Form:StaticText');
        $success->setProperty('text', 'You successfully completed the setup');
        $success->setProperty('elementClassAttribute', 'alert alert-success');

        $link = $title->createElement('link', 'Neos.Setup:LinkElement');
        $link->setLabel('Go to the homepage');
        $link->setProperty('href', '/');
        $link->setProperty('elementClassAttribute', 'btn btn-large btn-primary');

        $info = $title->createElement('info', 'Neos.Form:StaticText');
        $info->setProperty('text', 'If the homepage doesn\'t work, you might need configure routing in Configuration/Routes.yaml');
        $info->setProperty('elementClassAttribute', 'alert alert-info');

        $loggedOut = $page1->createElement('loggedOut', 'Neos.Form:StaticText');
        $loggedOut->setProperty('text', 'You have automatically been logged out for security reasons since this is the final step. Refresh the page to log in again if you missed something.');
        $loggedOut->setProperty('elementClassAttribute', 'alert alert-info');
    }
}
