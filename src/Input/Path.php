<?php

declare(strict_types=1);

namespace Axiom\DataForge\Input;

final class Path
{
    /**
     * @param  list<string>  $segments
     */
    private function __construct(private readonly array $segments) {}

    public static function fromString(string $path): self
    {
        if ($path === '') {
            return new self([]);
        }

        return new self(explode('.', $path));
    }

    public function relativeTo(self $prefix): ?self
    {
        if (count($prefix->segments) > count($this->segments)) {
            return null;
        }

        foreach ($prefix->segments as $index => $segment) {
            if (($this->segments[$index] ?? null) !== $segment) {
                return null;
            }
        }

        return new self(array_slice($this->segments, count($prefix->segments)));
    }

    public function collectionItemRelative(int $index): ?self
    {
        $first = $this->segments[0] ?? null;
        if ($first === (string) $index || $first === '*') {
            return new self(array_slice($this->segments, 1));
        }

        if (count($this->segments) === 1) {
            return $this;
        }

        return null;
    }

    public function isEmpty(): bool
    {
        return $this->segments === [];
    }

    public function containsNestedPath(): bool
    {
        return count($this->segments) > 1;
    }

    public function toString(): string
    {
        return implode('.', $this->segments);
    }
}
