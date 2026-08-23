<?php

declare(strict_types=1);

namespace Axiom\DataForge\Schema;

use Axiom\DataForge\Concerns\AsDto;
use Axiom\DataForge\Concerns\AsStrictInputDto;

final class DtoClass
{
    /** @var array<string, bool> */
    private static array $unknownInputKeyRejectionCache = [];

    /**
     * @phpstan-assert-if-true class-string $class
     */
    public static function isDto(string $class): bool
    {
        return class_exists($class) && self::usesTrait($class, AsDto::class);
    }

    public static function rejectsUnknownInputKeys(string $class): bool
    {
        if (! class_exists($class)) {
            return false;
        }

        return self::$unknownInputKeyRejectionCache[$class]
            ??= self::usesTraitOnClass($class, AsStrictInputDto::class);
    }

    private static function usesTrait(string $class, string $trait): bool
    {
        $classes = array_merge([$class], class_parents($class) ?: []);

        foreach ($classes as $candidate) {
            if (in_array($trait, self::traitsFor($candidate), true)) {
                return true;
            }
        }

        return false;
    }

    private static function usesTraitOnClass(string $class, string $trait): bool
    {
        return in_array($trait, self::traitsFor($class), true);
    }

    /**
     * @return array<string,string>
     */
    private static function traitsFor(string $classOrTrait): array
    {
        $traits = class_uses($classOrTrait) ?: [];

        foreach ($traits as $traitName) {
            $traits += self::traitsFor($traitName);
        }

        return $traits;
    }
}
