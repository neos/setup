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
use GuzzleHttp\Psr7\Stream;
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
    private const COMPILETIME_ENDPOINT = '/setup/compiletime.json';

    private const BASE_ENDPOINT = '/setup';

    private const JS_ENDPOINT = '/setup/main.js';

    private const CSS_ENDPOINT = '/setup/main.css';

    private const ALL_ENDPOINTS = [
        self::BASE_ENDPOINT,
        self::COMPILETIME_ENDPOINT,
        self::JS_ENDPOINT,
        self::CSS_ENDPOINT
    ];

    private Bootstrap $bootstrap;

    private ConfigurationManager $configurationManager;

    private string $requestUri;

    public function __construct(Bootstrap $bootstrap)
    {
        $this->bootstrap = $bootstrap;;
    }

    public function canHandleRequest(): bool
    {
        return (PHP_SAPI !== 'cli'
            && (in_array($_SERVER['REQUEST_URI'], self::ALL_ENDPOINTS, true)));
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

    private function sendFile(string $file, string $contentType): Response
    {
        $resource = fopen($file, 'r');

        $response = (new Response(200))
            ->withHeader('Content-Type', $contentType)
            ->withBody(new Stream($resource));

        assert($response instanceof Response);
        return $response;
    }

    public function handleRequest()
    {
        $sequence = $this->bootstrap->buildRuntimeSequence();
        $sequence->invoke($this->bootstrap);

        $this->configurationManager = $this->bootstrap->getObjectManager()->get(ConfigurationManager::class);

        $response = match ($_SERVER['REQUEST_URI']) {
            self::COMPILETIME_ENDPOINT => $this->handleCompiletimeEndpoint(),
            self::BASE_ENDPOINT => $this->sendFile(__DIR__ . '/../../Resources/Public/SetupDashboard/index.html', 'text/html; charset=utf-8'),
            self::JS_ENDPOINT => $this->sendFile(__DIR__ . '/../../Resources/Public/SetupDashboard/main.js', 'application/js; charset=utf-8'),
            self::CSS_ENDPOINT => $this->sendFile(__DIR__ . '/../../Resources/Public/SetupDashboard/main.css', 'text/css; charset=utf-8'),
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
