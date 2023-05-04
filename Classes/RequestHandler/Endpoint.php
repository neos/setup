<?php

namespace Neos\Setup\RequestHandler;

use GuzzleHttp\Psr7\Uri;

enum Endpoint
{
    case BASE_ENDPOINT;

    case JS_ENDPOINT;

    case CSS_ENDPOINT;

    case COMPILE_TIME_ENDPOINT;

    public static function tryFromEnvironment(): ?self
    {
        $requestUriString = $_SERVER['REQUEST_URI'];
        $requestUriPath = (new Uri($requestUriString))->getPath();

        return match($requestUriPath) {
            '/setup/index.html',
            '/setup/index',
            '/setup/',
            '/setup' => self::BASE_ENDPOINT,
            '/setup/compiletime.json' => self::COMPILE_TIME_ENDPOINT,
            // using the correct extensions like `.js` and `.css` might lead to problems,
            // if the server is configured to read them directly from _Resources
            '/setup/main_js' => self::JS_ENDPOINT,
            '/setup/main_css' => self::CSS_ENDPOINT,
            default => null
        };
    }
}
