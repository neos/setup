<?php

namespace Neos\Setup\Domain;

use Neos\Flow\Annotations as Flow;
use Traversable;

/** @Flow\Proxy(false) */
class HealthCollection implements \JsonSerializable, \IteratorAggregate
{
    /** @var array<string|int, Health> */
    private readonly array $items;

    public function __construct(
        Health ...$items
    ) {
        $this->items = $items;
    }

    public static function fromJsonString(string $json): self
    {
        return self::fromArray(json_decode($json, true,512, JSON_THROW_ON_ERROR));
    }

    public static function fromArray(array $array): self
    {
        $items = [];
        foreach ($array as $value) {
            $items[] = new Health(
                message: $value['message'],
                status: Status::from($value['status']),
                title: $value['title']
            );
        }
        return new self(...$items);
    }

    public static function empty(): self
    {
        return new self();
    }

    public function withEntry(string $identifier, Health $health): self
    {
        return new self(...$this->items, ...[$identifier => $health]);
    }

    public function hasError(): bool
    {
        foreach ($this->items as $item) {
            if ($item->status === Status::ERROR) {
                return true;
            }
        }
        return false;
    }

    public function jsonSerialize(): mixed
    {
        return $this->items;
    }

    /** @return Traversable<Health> */
    public function getIterator(): Traversable
    {
        yield from $this->items;
    }
}
