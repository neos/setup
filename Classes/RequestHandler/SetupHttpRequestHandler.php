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
use GuzzleHttp\Psr7\ServerRequest;
use GuzzleHttp\Psr7\Stream;
use GuzzleHttp\Psr7\Uri;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Configuration\ConfigurationManager;
use Neos\Flow\Core\Booting\Scripts;
use Neos\Flow\Core\Bootstrap;
use Neos\Flow\Core\RequestHandlerInterface;
use Neos\Flow\Http\ContentStream;
use Neos\Flow\Http\Helper\ResponseInformationHelper;
use Neos\Setup\Domain\HealthcheckEnvironment;
use Neos\Setup\Domain\WebEnvironment;
use Neos\Setup\Infrastructure\HealthChecker;
use Psr\Http\Message\ResponseInterface;

/**
 * @Flow\Scope("singleton")
 * @Flow\Proxy(false)
 */
class SetupHttpRequestHandler implements RequestHandlerInterface
{
    private Bootstrap $bootstrap;

    private ConfigurationManager $configurationManager;

    private Uri $requestUri;

    private Endpoint $endpoint;

    public function __construct(Bootstrap $bootstrap)
    {
        $this->bootstrap = $bootstrap;
    }

    public function canHandleRequest(): bool
    {
        if (PHP_SAPI === 'cli') {
            return false;
        }
        $this->requestUri = ServerRequest::getUriFromGlobals();
        $endpoint = Endpoint::tryFromUri($this->requestUri);
        if ($endpoint === null) {
            return false;
        }
        $this->endpoint = $endpoint;
        return true;
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

        $healthcheckEnvironment = new HealthcheckEnvironment(
            applicationContext: $this->bootstrap->getContext(),
            executionEnvironment: new WebEnvironment(
                requestUri: $this->requestUri,
                isWindows: PHP_OS_FAMILY === 'Windows'
            )
        );

        $healthCollection = (new HealthChecker($this->bootstrap, $healthchecksConfiguration, $healthcheckEnvironment))->execute();

        $response = (new Response($healthCollection->hasError() ? 503 : 200))
            ->withHeader('Content-Type', 'application/json')
            ->withBody(ContentStream::fromContents(
                json_encode($healthCollection, JSON_THROW_ON_ERROR)
            ));

        assert($response instanceof Response);
        return $response;
    }

    private function responseFromFile(string $file, string $contentType): Response
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
        Scripts::initializeConfiguration($this->bootstrap, false);
        Scripts::initializeSystemLogger($this->bootstrap);

        $this->configurationManager = $this->bootstrap->getEarlyInstance(ConfigurationManager::class);

        $response = match ($this->endpoint) {
            Endpoint::COMPILE_TIME_ENDPOINT => $this->handleCompiletimeEndpoint(),
            Endpoint::BASE_ENDPOINT => $this->responseFromFile(__DIR__ . '/../../Resources/Private/SetupDashboard/build/index.html', 'text/html; charset=utf-8'),
            Endpoint::JS_ENDPOINT => $this->responseFromFile(__DIR__ . '/../../Resources/Private/SetupDashboard/build/main.js', 'application/js; charset=utf-8'),
            Endpoint::CSS_ENDPOINT => $this->responseFromFile(__DIR__ . '/../../Resources/Private/SetupDashboard/build/main.css', 'text/css; charset=utf-8'),
        };

        $this->sendResponse($response);
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
