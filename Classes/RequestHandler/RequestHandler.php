<?php
namespace Neos\Setup\RequestHandler;

/*
 * This file is part of the Neos.Setup package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use GuzzleHttp\Psr7\Response;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Configuration\ConfigurationManager;
use Neos\Flow\Core\Bootstrap;
use Neos\Flow\Core\RequestHandlerInterface;
use Neos\Flow\Http\ContentStream;
use Neos\Flow\Http\Helper\ResponseInformationHelper;
use Neos\Setup\Infrastructure\HealthChecker;
use Psr\Http\Message\ResponseInterface;

/**
 * @Flow\Scope("singleton")
 * @Flow\Proxy(false)
 */
class RequestHandler implements RequestHandlerInterface
{
    private Bootstrap $bootstrap;

    private ConfigurationManager $configurationManager;

    private const COMPILETIME_ENDPOINT = '/setup/compiletime.json';

    private const BASE_ENDPOINT = '/setup';

    public function __construct(Bootstrap $bootstrap)
    {
        $this->bootstrap = $bootstrap;
    }

    public function canHandleRequest(): bool
    {
        return (PHP_SAPI !== 'cli'
            && (
                $_SERVER['REQUEST_URI'] === self::BASE_ENDPOINT ||
                $_SERVER['REQUEST_URI'] === self::COMPILETIME_ENDPOINT
            ));
    }

    /**
     * Overrules the default http request chandler
     *
     * @return integer
     */
    public function getPriority()
    {
        return 200;
    }

    private function handleCompiletimeEndpoint(): ResponseInterface
    {
        $healthchecksConfiguration = $this->configurationManager->getConfiguration(
            ConfigurationManager::CONFIGURATION_TYPE_SETTINGS,
            'Neos.Setup.healthchecks.compiletime'
        );

        $healthCollection = (new HealthChecker($this->bootstrap, $healthchecksConfiguration))->run();

        $response = (new Response($healthCollection->hasError() ? 503 : 200))
            ->withHeader('Content-Type', 'application/json')
            ->withBody(ContentStream::fromContents(
                json_encode($healthCollection, JSON_THROW_ON_ERROR)
            ));

        assert($response instanceof Response);
        return $response;
    }

    private function handleBaseEndpoint(): ResponseInterface
    {
        $html = <<<'HTML'
<head>
<title>Setup</title>
<script defer>

(async function() {

    function render(healthcollection) {

        let html = '<h1>Neos Setup</h1>';

        for (const health of healthcollection) {

            html += `<h3>${health['status']}: ${health['title']}</h3>`;
            html += `${health['message']}`;
        }

        document.body.innerHTML = html;
    }

    const compiletimeResponse = await fetch('/setup/compiletime.json');
    const compiletimeHealthCollection = await compiletimeResponse.json();
    render(compiletimeHealthCollection)
    if (compiletimeResponse.ok) {
        const runtimeResponse = await fetch('/setup/runtime.json');

        if (runtimeResponse.ok || runtimeResponse.status === 503) {
            const runtimeHealthCollection = await runtimeResponse.json();
            render([...compiletimeHealthCollection, ...runtimeHealthCollection])
        } else {
            render([...compiletimeHealthCollection, {
                status: 'ERROR',
                title: 'Flow end to end',
                message: 'Flow didnt respond - kaputt'
            }])
        }
    }
})()
</script>
</head>
<body>
</body>
HTML;

        $response = (new Response(200))
            ->withBody(ContentStream::fromContents(
                $html
            ));

        assert($response instanceof Response);
        return $response;
    }

    public function handleRequest()
    {
        $sequence = $this->bootstrap->buildRuntimeSequence();
        $sequence->invoke($this->bootstrap);

        $this->configurationManager = $this->bootstrap->getObjectManager()->get(ConfigurationManager::class);


        $response = match($_SERVER['REQUEST_URI']) {
            self::COMPILETIME_ENDPOINT => $this->handleCompiletimeEndpoint(),
            self::BASE_ENDPOINT => $this->handleBaseEndpoint()
        };

        $this->sendResponse($response);
        $this->bootstrap->shutdown('Compiletime');
        die();
    }


    /**
     * Send the HttpResponse of the component context to the browser and flush all output buffers.
     *
     * @param ResponseInterface $response
     */
    protected function sendResponse(ResponseInterface $response)
    {
        ob_implicit_flush(1);
        foreach (ResponseInformationHelper::prepareHeaders($response) as $prepareHeader) {
            header($prepareHeader, false);
        }
        // Flush and stop all output buffers before sending the whole body in one go, as output buffering has no use any more
        // and just makes sending large files impossible without running out of memory
        while (ob_get_level() > 0) {
            ob_end_flush();
        }

        $body = $response->getBody()->detach() ?: $response->getBody()->getContents();
        if (is_resource($body)) {
            fpassthru($body);
            fclose($body);
        } else {
            echo $body;
        }
    }
}

