<?php

declare(strict_types=1);

namespace Tests\Unit\Input;

use Axiom\DataForge\Input\Path;
use PHPUnit\Framework\TestCase;

final class PathTest extends TestCase
{
    public function test_empty_string_is_the_empty_path(): void
    {
        $path = Path::fromString('');

        $this->assertTrue($path->isEmpty());
        $this->assertFalse($path->containsNestedPath());
        $this->assertSame('', $path->toString());
    }

    public function test_dotted_string_round_trips(): void
    {
        $path = Path::fromString('address.city');

        $this->assertFalse($path->isEmpty());
        $this->assertTrue($path->containsNestedPath());
        $this->assertSame('address.city', $path->toString());
    }

    public function test_relative_to_strips_a_matching_prefix(): void
    {
        $relative = Path::fromString('address.city.name')->relativeTo(Path::fromString('address'));

        $this->assertSame('city.name', $relative?->toString());
    }

    public function test_relative_to_returns_empty_path_for_the_prefix_itself(): void
    {
        $relative = Path::fromString('address')->relativeTo(Path::fromString('address'));

        $this->assertTrue($relative?->isEmpty());
    }

    public function test_relative_to_rejects_a_longer_prefix(): void
    {
        $this->assertNull(Path::fromString('address')->relativeTo(Path::fromString('address.city')));
    }

    public function test_relative_to_rejects_a_different_prefix(): void
    {
        $this->assertNull(Path::fromString('name')->relativeTo(Path::fromString('address')));
    }

    public function test_collection_item_relative_strips_the_matching_index_or_wildcard(): void
    {
        $this->assertSame('email', Path::fromString('2.email')->collectionItemRelative(2)?->toString());
        $this->assertSame('email', Path::fromString('*.email')->collectionItemRelative(2)?->toString());
    }

    public function test_collection_item_relative_keeps_a_single_non_index_segment(): void
    {
        $this->assertSame('email', Path::fromString('email')->collectionItemRelative(0)?->toString());
    }

    public function test_collection_item_relative_rejects_other_indexes(): void
    {
        $this->assertNull(Path::fromString('1.email')->collectionItemRelative(0));
    }
}
