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
use Neos\FluidAdaptor\Core\Widget\AbstractWidgetViewHelper;

/**
 * Simple widget that checks given database credentials and returns a list of available database names via AJAX
 */
class DatabaseSelectorViewHelper extends AbstractWidgetViewHelper
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
     * Initialize the arguments.
     *
     * @return void
     * @throws \Neos\FluidAdaptor\Core\ViewHelper\Exception
     */
    public function initializeArguments()
    {
        parent::initializeArguments();
        $this->registerArgument('driverDropdownFieldId', 'string', 'id of the DB driver input field', true);
        $this->registerArgument('userFieldId', 'string', 'id of the DB username input field', true);
        $this->registerArgument('passwordFieldId', 'string', 'id of the DB password input field', true);
        $this->registerArgument('hostFieldId', 'string', 'id of the DB host input field', true);
        $this->registerArgument('dbNameTextFieldId', 'string', 'id of the input field for the db name (fallback)', true);
        $this->registerArgument('dbNameDropdownFieldId', 'string', 'id of the select field for the fetched db names (this is hidden by default)', true);
        $this->registerArgument('statusContainerId', 'string', 'id of the element displaying AJAX status (gets class "loading", "success" or "error" depending on the state)', true);
        $this->registerArgument('metadataStatusContainerId', 'string', 'id of the element displaying status information of the selected database (gets class "loading", "success" or "error" depending on the state)', true);
    }

    /**
     * @return string
     * @throws \Neos\Flow\Mvc\Exception\InfiniteLoopException
     * @throws \Neos\FluidAdaptor\Core\Widget\Exception\InvalidControllerException
     * @throws \Neos\FluidAdaptor\Core\Widget\Exception\MissingControllerException
     */
    public function render(): string
    {
        return $this->initiateSubRequest();
    }
}
