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

    public static function empty(): self
    {
        return new self();
    }

    public function append(Health $health): self
    {
        return new self(...$this->items, ...[$health]);
    }

    public function hasError(): bool
    {
        return $this->getFirstError() ? true : false;
    }

    public function getFirstError(): ?Health
    {
        foreach ($this->items as $item) {
            if ($item->status === Status::ERROR) {
                return $item;
            }
        }
        return null;
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
