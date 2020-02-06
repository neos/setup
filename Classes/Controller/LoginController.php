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
use Neos\Flow\Mvc\Controller\ActionController;
use Neos\Flow\Security\Exception\AuthenticationRequiredException;
use Neos\Utility\Files;

/**
 * @Flow\Scope("singleton")
 */
class LoginController extends ActionController
{
    /**
     * @var string
     */
    protected $keyName;

    /**
     * The authentication manager
     *
     * @var \Neos\Flow\Security\Authentication\AuthenticationManagerInterface
     * @Flow\Inject
     */
    protected $authenticationManager;

    /**
     * @var \Neos\Flow\Security\Cryptography\FileBasedSimpleKeyService
     * @Flow\Inject
     */
    protected $fileBasedSimpleKeyService;

    /**
     * @Flow\Inject
     * @var ConfigurationManager
     */
    protected $configurationManager;

    /**
     * Gets the authentication provider configuration needed
     *
     * @return void
     */
    public function initializeObject()
    {
        $settings = $this->configurationManager->getConfiguration(ConfigurationManager::CONFIGURATION_TYPE_SETTINGS, 'Neos.Flow');
        if (isset($settings['security']['authentication']['providers']['Neos.Setup:Login']['providerOptions']['keyName'])) {
            $this->keyName = $settings['security']['authentication']['providers']['Neos.Setup:Login']['providerOptions']['keyName'];
        }
    }

    /**
     * @param integer $step The requested setup step
     * @return void
     */
    public function loginAction($step = 0)
    {
        if ($this->fileBasedSimpleKeyService->keyExists($this->keyName) === false || file_exists($this->settings['initialPasswordFile'])) {
            $setupPassword = $this->fileBasedSimpleKeyService->generateKey($this->keyName);

            $initialPasswordFileContents = 'The setup password is:' . PHP_EOL;
            $initialPasswordFileContents .= PHP_EOL;
            $initialPasswordFileContents .= $setupPassword . PHP_EOL;
            $initialPasswordFileContents .= PHP_EOL;
            $initialPasswordFileContents .= 'After you successfully logged in, this file is automatically deleted for security reasons.' . PHP_EOL;
            $initialPasswordFileContents .= 'Make sure to save the setup password for later use.' . PHP_EOL;

            $result = file_put_contents($this->settings['initialPasswordFile'], $initialPasswordFileContents);
            if ($result === false) {
                $this->addFlashMessage('It was not possible to save the initial setup password to file "%s". Check file permissions and retry.', 'Password Generation Failure', Message::SEVERITY_ERROR, [$this->settings['initialPasswordFile']]);
            } else {
                $this->view->assign('initialPasswordFile', $this->settings['initialPasswordFile']);
            }
        }
        $this->view->assign('step', $step);
    }

    /**
     * @param integer $step The requested setup step
     * @return void
     * @Flow\SkipCsrfProtection
     */
    public function authenticateAction($step)
    {
        try {
            $this->authenticationManager->authenticate();

            if (file_exists($this->settings['initialPasswordFile'])) {
                unlink($this->settings['initialPasswordFile']);
            }
            $this->redirect('index', 'Setup', null, ['step' => $step]);
        } catch (AuthenticationRequiredException $exception) {
            $this->addFlashMessage('Sorry, you were not able to authenticate.', 'Authentication error', Message::SEVERITY_ERROR);
            $this->redirect('login', null, null, ['step' => $step]);
        }
    }

    /**
     * Removes the existing password and starts over by generating a new one.
     *
     * @param integer $step The requested setup step
     * @return void
     * @Flow\SkipCsrfProtection
     */
    public function generateNewPasswordAction($step = 0)
    {
        $existingPasswordFile = Files::concatenatePaths([FLOW_PATH_DATA, 'Persistent', 'FileBasedSimpleKeyService', $this->keyName]);
        if (file_exists($existingPasswordFile)) {
            unlink($existingPasswordFile);
            $this->addFlashMessage('A new password has been generated.', 'Password reset');
        }
        $this->redirect('login', null, null, ['step' => $step]);
    }

    /**
     * Logout all active authentication tokens.
     *
     * @return void
     */
    public function logoutAction()
    {
        $this->authenticationManager->logout();
        $this->addFlashMessage('Successfully logged out.', 'Logged out');
        $this->redirect('login');
    }
}
