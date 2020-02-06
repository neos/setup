<?php
namespace Neos\Setup\Core;

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
use Neos\Flow\Core\Bootstrap;
use Neos\Flow\Package\PackageManager;

/**
 * Rendering class for displaying messages before the Flow proxy classes are built.
 *
 * Because this class is extremely low-level, we cannot rely on most of Flow's
 * magic: There are no caches built yet, no resources published and the object
 * manager is not yet initialized. Only package management is loaded so far.
 *
 * @Flow\Proxy(false)
 * @Flow\Scope("singleton")
 */
class MessageRenderer
{
    /**
     * @var \Neos\Flow\Core\Bootstrap
     */
    protected $bootstrap;

    /**
     * Constructor.
     *
     * @param \Neos\Flow\Core\Bootstrap $bootstrap
     */
    public function __construct(Bootstrap $bootstrap)
    {
        $this->bootstrap = $bootstrap;
    }

    /**
     * Display a message. As we cannot rely on any Flow requirements being fulfilled here,
     * we have to statically include the CSS styles at this point, and have to in-line the Neos logo.
     *
     * @param array <\Neos\Error\Messages\Message> $messages Array of messages (at least one message must be passed)
     * @param string $extraHeaderHtml extra HTML code to include at the end of the head tag
     * @return void This method never returns.
     */
    public function showMessages(array $messages, $extraHeaderHtml = '')
    {
        if ($messages === []) {
            throw new \InvalidArgumentException('No messages given for rendering', 1416914970);
        }

        /** @var PackageManager $packageManager */
        $packageManager = $this->bootstrap->getEarlyInstance(PackageManager::class);

        $css = '';
        if ($packageManager->isPackageAvailable('Neos.Twitter.Bootstrap')) {
            $css .= file_get_contents($packageManager->getPackage('Neos.Twitter.Bootstrap')->getResourcesPath() . 'Public/3/css/bootstrap.min.css');
            $css = str_replace('url(../', 'url(/_Resources/Static/Packages/Neos.Twitter.Bootstrap/3.0/', $css);
        }
        if ($packageManager->isPackageAvailable('Neos.Setup')) {
            $css .= file_get_contents($packageManager->getPackage('Neos.Setup')->getResourcesPath() . 'Public/Styles/Setup.css');
            $css = str_replace('url(\'../', 'url(\'/_Resources/Static/Packages/Neos.Setup/', $css);
        }

        echo '<html>';
        echo '<head>';
        echo '<title>Setup message</title>';
        echo '<style type="text/css">';
        echo $css;
        echo '</style>';
        echo $extraHeaderHtml;
        echo '</head>';
        echo '<body>';

        $renderedMessages = $this->renderMessages($messages);
        $lastMessage = end($messages);

        echo sprintf('
			<div class="logo"></div>
			<div class="well">
				<div class="container">
					<ul class="breadcrumb">
						<li><a class="active">Setup</a></li>
					</ul>
					<h3>%s</h3>
                    %s
				</div>
			</div>
			', $lastMessage->getTitle(), $renderedMessages);
        echo '</body></html>';
        exit(0);
    }

    /**
     * @param array $messages
     * @return string Rendered messages
     */
    protected function renderMessages(array $messages)
    {
        $content = '';
        foreach ($messages as $message) {
            switch ($message->getSeverity()) {
                case Message::SEVERITY_ERROR:
                    $severity = 'error';
                    $icon = '<span class="glyphicon glyphicon glyphicon-ban-circle"></span>';
                break;
                case Message::SEVERITY_WARNING:
                    $severity = 'warning';
                    $icon = '<span class="glyphicon glyphicon-warning-sign"></span>';
                break;
                case Message::SEVERITY_OK:
                    $severity = 'success';
                    $icon = '<span class="glyphicon glyphicon-refresh glyphicon-spin"></span>';
                break;
                case Message::SEVERITY_NOTICE:
                default:
                    $severity = 'info';
                    $icon = '<span class="glyphicon glyphicon-info-sign"></span>';
                break;
            }

            $messageBody = $message->render();
            $content .= sprintf('
			<div class="alert alert-%s">
				%s
				%s
			</div>
			', $severity, $icon, $messageBody);
        }

        return $content;
    }
}
