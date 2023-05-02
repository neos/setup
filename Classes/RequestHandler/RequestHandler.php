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
            && ($_SERVER['REQUEST_URI'] === self::BASE_ENDPOINT ||
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
<!DOCTYPE html>
<html lang="en">
<head>
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta charset="UTF-8">
<title>Neos Setup</title>
<script src="https://cdn.tailwindcss.com"></script>
<script>
    const dateNow = Date.now;
    const raf = window.requestAnimationFrame;
    const rafInterval = (callback, delay) => {
        let start = dateNow();
        let stop = false;
        const intervalFunc = () => {
            dateNow() - start < delay || ((start += delay), callback());
            stop || raf(intervalFunc);
        };
        raf(intervalFunc);
        return {
            clear: () => (stop = true),
        };
    };

    const rafTimeOut = (callback, delay) => {
        let start = dateNow();
        let stop = false;
        const timeoutFunc = () => {
            dateNow() - start < delay ? stop || raf(timeoutFunc) : callback();
        };
        raf(timeoutFunc);
        return {
            clear: () => (stop = true),
        };
    };


    window.addEventListener('alpine:init', () => {
        Alpine.data('health', () => ({
            cssClasses: {
                'OK': 'bg-green-700',
                'WARNING': 'bg-yellow-700',
                'ERROR': 'bg-red-700',
                'UNKNOWN': 'bg-[#323232]',
                'NOT_RUN': 'bg-[#323232]',
            },
            checks: {},
            canCopy: window.navigator.clipboard !== undefined,
            copiedCode: false,
            copyTimeout: null,
            copy() {
                this.copiedCode = [...this.$el.querySelectorAll('code')].map(el => el.innerText).join("\n");
                window.navigator.clipboard.writeText(this.copiedCode);
                if (this.copyTimeout) {
                    this.copyTimeout.clear();
                }
                this.copyTimeout = rafTimeOut(() => {
                    this.copiedCode = false;
                }, 5000);
            },
            fetch(type) {
                fetch(`/setup/${type}.json`).then(response => {
                    if (!response.ok && response.status !== 503) {
                        return {
                            errorResponse: {
                                status: 'ERROR',
                                title: 'Flow Framework',
                                message: "Flow didn't respond as expected."
                            }
                        }
                    }
                    return response.json()
                }).then(data => {
                    this.checks = {...this.checks, ...data};
                });
            },
            init() {
                this.fetch('compiletime');
                this.fetch('runtime');

                rafInterval(() => {
                    this.fetch('compiletime');
                    this.fetch('runtime');
                }, 5000);
            }
        }))
    });
</script>
<script src="//unpkg.com/alpinejs" defer></script>
</head>
<body x-data="health" class="p-8 flex flex-col gap-16 items-center bg-[#222] text-white">
    <h1 class="text-5xl font-bold flex gap-12 items-center text-[#00b5ff]">
        <svg class="fill-current w-12 h-12" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><!--! Font Awesome Pro 6.4.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license (Commercial License) Copyright 2023 Fonticons, Inc. --><path d="M415.44 512h-95.11L212.12 357.46v91.1L125.69 512H28V29.82L68.47 0h108.05l123.74 176.13V63.45L386.69 0h97.69v461.5zM38.77 35.27V496l72-52.88V194l215.5 307.64h84.79l52.35-38.17h-78.27L69 13zm82.54 466.61l80-58.78v-101l-79.76-114.4v220.94L49 501.89h72.34zM80.63 10.77l310.6 442.57h82.37V10.77h-79.75v317.56L170.91 10.77zM311 191.65l72 102.81V15.93l-72 53v122.72z"/></svg>
        Neos Setup
    </h1>
    <aside x-show="copiedCode" x-transition class="fixed top-4 right-4 bg-[#000c] px-4 py-2">
        <pre><code x-text="copiedCode"></code></pre>
        was copied to clipboard
    </aside>
    <ul class="flex flex-col gap-8 text-lg w-full max-w-xl">
        <template x-for="check in checks">
            <li class="block bg-opacity-50 relative" :class="cssClasses[check.status]">
                <span class="flex items-center gap-4 px-4 py-2 [&>svg]:fill-current [&>svg]:h-5 [&>svg]:w-5" :class="cssClasses[check.status]">
                    <svg x-show="check.status=='OK'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><!--! Font Awesome Pro 6.4.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license (Commercial License) Copyright 2023 Fonticons, Inc. --><path d="M269.4 2.9C265.2 1 260.7 0 256 0s-9.2 1-13.4 2.9L54.3 82.8c-22 9.3-38.4 31-38.3 57.2c.5 99.2 41.3 280.7 213.6 363.2c16.7 8 36.1 8 52.8 0C454.7 420.7 495.5 239.2 496 140c.1-26.2-16.3-47.9-38.3-57.2L269.4 2.9zM369 209L241 337c-9.4 9.4-24.6 9.4-33.9 0l-64-64c-9.4-9.4-9.4-24.6 0-33.9s24.6-9.4 33.9 0l47 47L335 175c9.4-9.4 24.6-9.4 33.9 0s9.4 24.6 0 33.9z"/></svg>
                    <svg x-show="check.status=='ERROR'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><!--! Font Awesome Pro 6.4.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license (Commercial License) Copyright 2023 Fonticons, Inc. --><path d="M256 32c14.2 0 27.3 7.5 34.5 19.8l216 368c7.3 12.4 7.3 27.7 .2 40.1S486.3 480 472 480H40c-14.3 0-27.6-7.7-34.7-20.1s-7-27.8 .2-40.1l216-368C228.7 39.5 241.8 32 256 32zm0 128c-13.3 0-24 10.7-24 24V296c0 13.3 10.7 24 24 24s24-10.7 24-24V184c0-13.3-10.7-24-24-24zm32 224a32 32 0 1 0 -64 0 32 32 0 1 0 64 0z"/></svg>
                    <svg x-show="check.status=='WARNING'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><!--! Font Awesome Pro 6.4.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license (Commercial License) Copyright 2023 Fonticons, Inc. --><path d="M256 512A256 256 0 1 0 256 0a256 256 0 1 0 0 512zm0-384c13.3 0 24 10.7 24 24V264c0 13.3-10.7 24-24 24s-24-10.7-24-24V152c0-13.3 10.7-24 24-24zM224 352a32 32 0 1 1 64 0 32 32 0 1 1 -64 0z"/></svg>
                    <svg x-show="check.status=='UNKNOWN'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><!--! Font Awesome Pro 6.4.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license (Commercial License) Copyright 2023 Fonticons, Inc. --><path d="M256 512A256 256 0 1 0 256 0a256 256 0 1 0 0 512zM169.8 165.3c7.9-22.3 29.1-37.3 52.8-37.3h58.3c34.9 0 63.1 28.3 63.1 63.1c0 22.6-12.1 43.5-31.7 54.8L280 264.4c-.2 13-10.9 23.6-24 23.6c-13.3 0-24-10.7-24-24V250.5c0-8.6 4.6-16.5 12.1-20.8l44.3-25.4c4.7-2.7 7.6-7.7 7.6-13.1c0-8.4-6.8-15.1-15.1-15.1H222.6c-3.4 0-6.4 2.1-7.5 5.3l-.4 1.2c-4.4 12.5-18.2 19-30.6 14.6s-19-18.2-14.6-30.6l.4-1.2zM224 352a32 32 0 1 1 64 0 32 32 0 1 1 -64 0z"/></svg>
                    <svg x-show="check.status=='NOT_RUN'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><!--! Font Awesome Pro 6.4.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license (Commercial License) Copyright 2023 Fonticons, Inc. --><path d="M367.2 412.5L99.5 144.8C77.1 176.1 64 214.5 64 256c0 106 86 192 192 192c41.5 0 79.9-13.1 111.2-35.5zm45.3-45.3C434.9 335.9 448 297.5 448 256c0-106-86-192-192-192c-41.5 0-79.9 13.1-111.2 35.5L412.5 367.2zM0 256a256 256 0 1 1 512 0A256 256 0 1 1 0 256z"/></svg>
                    <span x-text="check.title"></span>
                </span>
                <span x-show="check.message && !canCopy || !check.message.includes('<code>')" class="block px-4 py-2 empty:hidden" x-html="check.message"></span>
                <button x-show="check.message && canCopy && check.message.includes('<code>')" @click="copy" class="text-left block px-4 py-2 [&_code]:block empty:hidden peer" x-html="check.message" title="Copy command"></button>
                <span x-show="check.message && canCopy && check.message.includes('<code>')" class="absolute top-0 py-3 right-2 opacity-0 transition-opacity peer-hover:opacity-100 peer-focus:opacity-100 flex gap-2 items-center">
                    <span class="text-xs">Copy command</span>
                    <svg class="fill-current w-5 h-5 " xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><!--! Font Awesome Pro 6.4.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license (Commercial License) Copyright 2023 Fonticons, Inc. --><path d="M272 0H396.1c12.7 0 24.9 5.1 33.9 14.1l67.9 67.9c9 9 14.1 21.2 14.1 33.9V336c0 26.5-21.5 48-48 48H272c-26.5 0-48-21.5-48-48V48c0-26.5 21.5-48 48-48zM48 128H192v64H64V448H256V416h64v48c0 26.5-21.5 48-48 48H48c-26.5 0-48-21.5-48-48V176c0-26.5 21.5-48 48-48z"/></svg>
                </span>
            </li>
        </template>
    </ul>
</body>
</html>
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


        $response = match ($_SERVER['REQUEST_URI']) {
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
