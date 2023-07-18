<?php

declare(strict_types=1);

namespace Neos\Setup\Domain;

use Neos\Flow\Annotations as Flow;

/**
 * Sweet php enums - where you at?!!
 *
 * @Flow\Proxy(false)
 */
class Status implements \JsonSerializable
{
    private static array $instances = [];

    private function __construct(
        private string $value
    ) {
        if (!in_array($this->value, [
            'OK',
            'ERROR',
            'WARNING',
            'UNKNOWN',
            'NOT_RUN'
        ], true)) {
            throw new \InvalidArgumentException(__CLASS__ . ' enum doest allow value ' . $this->value);
        }
    }

    public static function from(string $value): self
    {
        return static::$instances[$value] ??= new self($value);
    }

    public static function OK()
    {
        return self::from('OK');
    }

    public static function ERROR()
    {
        return self::from('ERROR');
    }

    public static function WARNING()
    {
        return self::from('WARNING');
    }

    public static function UNKNOWN()
    {
        return self::from('UNKNOWN');
    }

    public static function NOT_RUN()
    {
        return self::from('NOT_RUN');
    }

    public function jsonSerialize(): string
    {
        return $this->value;
    }
}
