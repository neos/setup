<?php

declare(strict_types=1);

namespace Neos\Setup\RequestHandler;

use Neos\Flow\Annotations as Flow;
use Psr\Http\Message\UriInterface;

/**
 * Sweet php enums - where you at?!!
 *
 * @Flow\Proxy(false)
 */
class Endpoint
{
    private static array $instances = [];

    private function __construct(
        private string $value
    ) {
        if (!in_array($this->value, [
            'BASE_ENDPOINT',
            'JS_ENDPOINT',
            'CSS_ENDPOINT',
            'COMPILE_TIME_ENDPOINT'
        ], true)) {
            throw new \InvalidArgumentException(__CLASS__ . ' enum doest allow value ' . $this->value);
        }
    }

    public static function tryFromUri(UriInterface $uri): ?self
    {
        $path = $uri->getPath();

        return match($path) {
            '/setup/index.html',
            '/setup/index',
            '/setup/',
            '/setup' => self::BASE_ENDPOINT(),
            '/setup/compiletime.json' => self::COMPILE_TIME_ENDPOINT(),
            // using the correct extensions like `.js` and `.css` might lead to problems,
            // if the server is configured to read them directly from _Resources
            '/setup/main_js' => self::JS_ENDPOINT(),
            '/setup/main_css' => self::CSS_ENDPOINT(),
            default => null
        };
    }

    public static function from(string $value): self
    {
        return static::$instances[$value] ??= new self($value);
    }

    public static function BASE_ENDPOINT()
    {
        return self::from('BASE_ENDPOINT');
    }

    public static function JS_ENDPOINT()
    {
        return self::from('JS_ENDPOINT');
    }

    public static function CSS_ENDPOINT()
    {
        return self::from('CSS_ENDPOINT');
    }

    public static function COMPILE_TIME_ENDPOINT()
    {
        return self::from('COMPILE_TIME_ENDPOINT');
    }
}
