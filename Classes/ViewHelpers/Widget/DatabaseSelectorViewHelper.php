<?php
namespace Neos\Setup\ViewHelpers\Widget;

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

/**
 * Simple widget that checks given database credentials and returns a list of available database names via AJAX
 */
class DatabaseSelectorViewHelper extends \Neos\FluidAdaptor\Core\Widget\AbstractWidgetViewHelper
{
    /**
     * @var boolean
     */
    protected $ajaxWidget = true;

    /**
     * @Flow\Inject
     * @var \Neos\Setup\ViewHelpers\Widget\Controller\DatabaseSelectorController
     */
    protected $controller;

    /**
     * Don't create a session for this widget
     * Note: You then need to manually add the serialized configuration data to your links, by
     * setting "includeWidgetContext" to TRUE in the widget link and URI ViewHelpers!
     *
     * @var boolean
     */
    protected $storeConfigurationInSession = false;

    /**
     *
     * @param string $driverDropdownFieldId id of the DB driver input field
     * @param string $userFieldId id of the DB username input field
     * @param string $passwordFieldId id of the DB password input field
     * @param string $hostFieldId id of the DB host input field
     * @param string $dbNameTextFieldId id of the input field for the db name (fallback)
     * @param string $dbNameDropdownFieldId id of the select field for the fetched db names (this is hidden by default)
     * @param string $statusContainerId id of the element displaying AJAX status (gets class "loading", "success" or "error" depending on the state)
     * @param string $metadataStatusContainerId id of the element displaying status information of the selected database (gets class "loading", "success" or "error" depending on the state)
     * @return string
     */
    public function render($driverDropdownFieldId, $userFieldId, $passwordFieldId, $hostFieldId, $dbNameTextFieldId, $dbNameDropdownFieldId, $statusContainerId, $metadataStatusContainerId)
    {
        return $this->initiateSubRequest();
    }
}
