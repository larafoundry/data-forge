<?php

declare(strict_types=1);

namespace Axiom\DataForge\Schema;

use Axiom\DataForge\Exceptions\InspectionException;
use Illuminate\Support\Collection;
use phpDocumentor\Reflection\DocBlock\Tags\Param;
use phpDocumentor\Reflection\DocBlockFactory;
use phpDocumentor\Reflection\DocBlockFactoryInterface;
use phpDocumentor\Reflection\PseudoTypes\Generic;
use phpDocumentor\Reflection\Type;
use phpDocumentor\Reflection\Types\AbstractList;
use phpDocumentor\Reflection\Types\Array_;
use phpDocumentor\Reflection\Types\Compound;
use phpDocumentor\Reflection\Types\Context;
use phpDocumentor\Reflection\Types\ContextFactory;
use phpDocumentor\Reflection\Types\Integer;
use phpDocumentor\Reflection\Types\Nullable;
use phpDocumentor\Reflection\Types\Object_;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionType;

/**
 * Reads the DTO item type of an `array` or `Collection` field from its
 * traditional PHPDoc (`@param` on the constructor, `@var` on the property).
 *
 * Parsing and name resolution are delegated to phpDocumentor. PHPStan and
 * Psalm specific tags (`@phpstan-param`, `@psalm-var`, ...) are not read.
 */
final class DocBlockItemType
{
    private const ARRAY = 'array';

    private const COLLECTION = 'collection';

    private static ?DocBlockFactoryInterface $factory = null;

    /** @var array<string, Context> */
    private static array $contexts = [];

    /**
     * @param  class-string  $class
     * @return class-string|null
     *
     * @throws InspectionException
     */
    public static function resolve(
        string $class,
        string $name,
        ?ReflectionType $nativeType,
        ?ReflectionProperty $property,
        ?ReflectionParameter $parameter
    ): ?string {
        $container = self::container($nativeType);
        if ($container === null) {
            return null;
        }

        $declared = [];
        if ($property !== null && ($type = self::varType($property)) !== null) {
            $declared['@var'] = self::itemClass($type, $container, $class, $name);
        }

        if ($parameter !== null && ($type = self::paramType($parameter)) !== null) {
            $declared['@param'] = self::itemClass($type, $container, $class, $name);
        }

        if (count(array_unique(array_map(static fn (?string $item): string => $item ?? '', $declared))) > 1) {
            throw new InspectionException(
                "The @var and @param PHPDoc of field '$name' on $class declare different item types"
            );
        }

        return $declared === [] ? null : reset($declared);
    }

    /**
     * @return self::ARRAY|self::COLLECTION|null
     */
    private static function container(?ReflectionType $nativeType): ?string
    {
        if (! ($nativeType instanceof ReflectionNamedType)) {
            return null;
        }

        if ($nativeType->getName() === 'array') {
            return self::ARRAY;
        }

        return is_a($nativeType->getName(), Collection::class, true) ? self::COLLECTION : null;
    }

    private static function varType(ReflectionProperty $property): ?Type
    {
        $docComment = $property->getDocComment();
        if ($docComment === false) {
            return null;
        }

        $tags = self::factory()
            ->create($docComment, self::context($property->getDeclaringClass()))
            ->getTagsWithTypeByName('var');

        return $tags === [] ? null : $tags[0]->getType();
    }

    private static function paramType(ReflectionParameter $parameter): ?Type
    {
        $docComment = $parameter->getDeclaringFunction()->getDocComment();
        $declaringClass = $parameter->getDeclaringClass();
        if ($docComment === false || $declaringClass === null) {
            return null;
        }

        $tags = self::factory()
            ->create($docComment, self::context($declaringClass))
            ->getTagsWithTypeByName('param');

        foreach ($tags as $tag) {
            if ($tag instanceof Param && $tag->getVariableName() === $parameter->getName()) {
                return $tag->getType();
            }
        }

        return null;
    }

    /**
     * @param  self::ARRAY|self::COLLECTION  $container
     * @param  class-string  $class
     * @return class-string|null
     *
     * @throws InspectionException
     */
    private static function itemClass(Type $type, string $container, string $class, string $name): ?string
    {
        $members = $type instanceof Compound ? iterator_to_array($type) : [$type];

        $items = [];
        foreach ($members as $member) {
            if ($member instanceof Nullable) {
                $member = $member->getActualType();
            }

            $item = self::memberItemClass($member, $container, $class, $name);
            if ($item !== null) {
                $items[$item] = true;
            }
        }

        if (count($items) > 1) {
            throw new InspectionException("Field '$name' on $class declares more than one DTO item type in PHPDoc '$type'");
        }

        /** @var class-string|null */
        return array_key_first($items);
    }

    /**
     * @param  self::ARRAY|self::COLLECTION  $container
     * @param  class-string  $class
     * @return class-string|null
     *
     * @throws InspectionException
     */
    private static function memberItemClass(Type $member, string $container, string $class, string $name): ?string
    {
        if ($member instanceof Generic) {
            $types = $member->getTypes();
            $item = self::dtoClass($types[array_key_last($types)], $class, $name);
            if ($item === null) {
                return null;
            }

            if ($container !== self::COLLECTION || ! is_a(ltrim((string) $member->getFqsen(), '\\'), Collection::class, true)) {
                throw new InspectionException(
                    "PHPDoc '$member' of field '$name' on $class does not match its native type; use ".
                    ($container === self::ARRAY ? "'{$item}[]' or 'array<int, $item>'" : "'Collection<int, $item>'")
                );
            }

            self::assertListKey(count($types) > 1 ? $types[0] : null, $member, $class, $name);

            return $item;
        }

        if ($member instanceof AbstractList) {
            $item = self::dtoClass($member->getValueType(), $class, $name);
            if ($item === null) {
                return null;
            }

            if ($member::class !== Array_::class) {
                throw new InspectionException(
                    "PHPDoc '$member' of field '$name' on $class is not supported; use '{$item}[]' or 'array<int, $item>'"
                );
            }

            self::assertListKey($member->getOriginalKeyType(), $member, $class, $name);

            return $item;
        }

        return null;
    }

    /**
     * @param  class-string  $class
     * @return class-string|null
     *
     * @throws InspectionException
     */
    private static function dtoClass(Type $value, string $class, string $name): ?string
    {
        if ($value instanceof Compound) {
            $items = [];
            foreach ($value as $member) {
                $item = self::dtoClass($member, $class, $name);
                if ($item !== null) {
                    $items[] = $item;
                }
            }

            if ($items === []) {
                return null;
            }

            throw new InspectionException(
                "Field '$name' on $class declares union item type '$value'; DTO collection items must have one DTO type"
            );
        }

        if ($value instanceof Nullable && self::dtoClass($value->getActualType(), $class, $name) !== null) {
            throw new InspectionException(
                "Field '$name' on $class declares nullable item type '$value'; DTO collection items cannot be null"
            );
        }

        if (! ($value instanceof Object_) || $value->getFqsen() === null) {
            return null;
        }

        $itemClass = ltrim((string) $value->getFqsen(), '\\');
        if (! class_exists($itemClass) && ! interface_exists($itemClass)) {
            throw new InspectionException(
                "PHPDoc item type '$itemClass' of field '$name' on $class does not exist; check the class name and its use import"
            );
        }

        return DtoClass::isDto($itemClass) ? $itemClass : null;
    }

    /**
     * @param  class-string  $class
     *
     * @throws InspectionException
     */
    private static function assertListKey(?Type $key, Type $member, string $class, string $name): void
    {
        if ($key === null || $key instanceof Integer) {
            return;
        }

        throw new InspectionException(
            "PHPDoc '$member' of field '$name' on $class must use int keys; DTO collections are lists"
        );
    }

    private static function factory(): DocBlockFactoryInterface
    {
        return self::$factory ??= DocBlockFactory::createInstance();
    }

    /**
     * @param  ReflectionClass<object>  $class
     */
    private static function context(ReflectionClass $class): Context
    {
        return self::$contexts[$class->getName()] ??= (new ContextFactory())->createFromReflector($class);
    }
}
