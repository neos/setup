<?php
namespace Neos\Setup\Controller;

/*
 * This file is part of the Neos.Setup package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\Error\Messages\Message;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Configuration\ConfigurationManager;
use Neos\Flow\Mvc\ActionResponse;
use Neos\Flow\Mvc\Controller\ActionController;
use Neos\Form\Core\Model\FinisherContext;
use Neos\Setup\Step\StepInterface;

/**
 * @Flow\Scope("singleton")
 */
class SetupController extends ActionController
{
    /**
     * The authentication manager
     *
     * @var \Neos\Flow\Security\Authentication\AuthenticationManagerInterface
     * @Flow\Inject
     */
    protected $authenticationManager;

    /**
     * @var \Neos\Flow\Configuration\Source\YamlSource
     * @Flow\Inject
     */
    protected $configurationSource;

    /**
     * The settings parsed from Settings.yaml
     *
     * @var array
     */
    protected $distributionSettings;

    /**
     * Contains the current step to be executed
     *
     * @see determineCurrentStepIndex()
     *
     * @var integer
     */
    protected $currentStepIndex = 0;

    /**
     * @return void
     */
    protected function initializeAction()
    {
        $this->distributionSettings = $this->configurationSource->load(FLOW_PATH_CONFIGURATION . ConfigurationManager::CONFIGURATION_TYPE_SETTINGS);
    }

    /**
     * @param integer $step
     * @return void
     * @Flow\SkipCsrfProtection
     */
    public function indexAction($step = 0)
    {
        $this->currentStepIndex = $step;
        $this->checkRequestedStepIndex();
        $currentStep = $this->instantiateCurrentStep();
        $controller = $this;
        $callback = function (FinisherContext $finisherContext) use ($controller, $currentStep) {
            $controller->postProcessStep($finisherContext->getFormValues(), $currentStep);
        };
        $formDefinition = $currentStep->getFormDefinition($callback);
        if ($this->currentStepIndex > 0) {
            $formDefinition->setRenderingOption('previousStepUri', $this->uriBuilder->uriFor('index', ['step' => $this->currentStepIndex - 1]));
        }
        if ($currentStep->isOptional()) {
            $formDefinition->setRenderingOption('nextStepUri', $this->uriBuilder->uriFor('index', ['step' => $this->currentStepIndex + 1]));
        }
        $totalAmountOfSteps = count($this->settings['steps']);
        if ($this->currentStepIndex === $totalAmountOfSteps - 1) {
            $formDefinition->setRenderingOption('finalStep', true);
            $this->authenticationManager->logout();
        }

        $form = $formDefinition->bind($this->request, $this->response);

        try {
            $renderedForm = $form->render();
        } catch (\Neos\Setup\Exception $exception) {
            $this->addFlashMessage($exception->getMessage(), 'Exception while executing setup step', Message::SEVERITY_ERROR);
            $this->redirect('index', null, null, ['step' => $this->currentStepIndex]);
        }
        $this->view->assignMultiple([
            'form' => $renderedForm,
            'totalAmountOfSteps' => $totalAmountOfSteps,
            'currentStepNumber' => $this->currentStepIndex + 1
        ]);
    }

    /**
     * @return void
     * @throws \Neos\Setup\Exception
     */
    protected function checkRequestedStepIndex()
    {
        if (!isset($this->settings['stepOrder']) || !is_array($this->settings['stepOrder'])) {
            throw new \Neos\Setup\Exception('No "stepOrder" configured, setup can\'t be invoked', 1332167136);
        }
        $stepOrder = $this->settings['stepOrder'];
        if (!array_key_exists($this->currentStepIndex, $stepOrder)) {
            // TODO instead of throwing an exception we might also quietly jump to another step
            throw new \Neos\Setup\Exception(sprintf('No setup step #%d configured, setup can\'t be invoked', $this->currentStepIndex), 1332167418);
        }
        while ($this->checkRequiredConditions($stepOrder[$this->currentStepIndex]) !== true) {
            if ($this->currentStepIndex === 0) {
                throw new \Neos\Setup\Exception('Not all requirements are met for the first setup step, aborting setup', 1332169088);
            }
            $this->addFlashMessage('Not all requirements are met for step "%s"', '', Message::SEVERITY_ERROR, [$stepOrder[$this->currentStepIndex]]);
            $this->redirect('index', null, null, ['step' => $this->currentStepIndex - 1]);
        }
    }

    /**
     * @return \Neos\Setup\Step\StepInterface
     * @throws \Neos\Setup\Exception
     */
    protected function instantiateCurrentStep()
    {
        $currentStepIdentifier = $this->settings['stepOrder'][$this->currentStepIndex];
        $currentStepConfiguration = $this->settings['steps'][$currentStepIdentifier];
        if (!isset($currentStepConfiguration['className'])) {
            throw new \Neos\Setup\Exception(sprintf('No className specified for setup step "%s", setup can\'t be invoked', $currentStepIdentifier), 1332169398);
        }
        $currentStep = new $currentStepConfiguration['className']();
        if (!$currentStep instanceof StepInterface) {
            throw new \Neos\Setup\Exception(sprintf('ClassName %s of setup step "%s" does not implement StepInterface, setup can\'t be invoked', $currentStepConfiguration['className'], $currentStepIdentifier), 1332169576);
        }
        if (isset($currentStepConfiguration['options'])) {
            $currentStep->setOptions($currentStepConfiguration['options']);
        }
        $currentStep->setDistributionSettings($this->distributionSettings);

        return $currentStep;
    }

    /**
     * @param string $stepIdentifier
     * @return boolean TRUE if all required conditions were met, otherwise FALSE
     * @throws \Neos\Setup\Exception
     */
    protected function checkRequiredConditions($stepIdentifier)
    {
        if (!isset($this->settings['steps'][$stepIdentifier]) || !is_array($this->settings['steps'][$stepIdentifier])) {
            throw new \Neos\Setup\Exception(sprintf('No configuration found for setup step "%s", setup can\'t be invoked', $stepIdentifier), 1332167685);
        }
        $stepConfiguration = $this->settings['steps'][$stepIdentifier];
        if (!isset($stepConfiguration['requiredConditions'])) {
            return true;
        }
        foreach ($stepConfiguration['requiredConditions'] as $index => $conditionConfiguration) {
            if (!isset($conditionConfiguration['className'])) {
                throw new \Neos\Setup\Exception(sprintf('No condition className specified for condition #%d in setup step "%s", setup can\'t be invoked', $index, $stepIdentifier), 1332168070);
            }
            $condition = new $conditionConfiguration['className']();
            if (!$condition instanceof \Neos\Setup\Condition\ConditionInterface) {
                throw new \Neos\Setup\Exception(sprintf('Condition #%d (%s) in setup step "%s" does not implement ConditionInterface, setup can\'t be invoked', $index, $conditionConfiguration['className'], $stepIdentifier), 1332168070);
            }
            if (isset($conditionConfiguration['options'])) {
                $condition->setOptions($conditionConfiguration['options']);
            }
            if ($condition->isMet() !== true) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array $formValues
     * @param \Neos\Setup\Step\StepInterface $currentStep
     * @return void
     */
    public function postProcessStep(array $formValues, StepInterface $currentStep)
    {
        try {
            $currentStep->postProcessFormValues($formValues);
        } catch (\Neos\Setup\Exception $exception) {
            $this->addFlashMessage($exception->getMessage(), 'Exception while executing setup step', Message::SEVERITY_ERROR);
            $this->redirect('index', null, null, ['step' => $this->currentStepIndex]);
        }
        $this->redirect('index', null, null, ['step' => $this->currentStepIndex + 1]);
    }
}
