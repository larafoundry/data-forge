<?php

declare(strict_types=1);

namespace Axiom\DataForge\Schema;

use Axiom\DataForge\Exceptions\InspectionException;
use Closure;
use Illuminate\Support\Collection;
use phpDocumentor\Reflection\DocBlock\Tags\InvalidTag;
use phpDocumentor\Reflection\DocBlock\Tags\Param;
use phpDocumentor\Reflection\DocBlock\Tags\Var_;
use phpDocumentor\Reflection\DocBlockFactory;
use phpDocumentor\Reflection\DocBlockFactoryInterface;
use phpDocumentor\Reflection\PseudoTypes\ArrayShape;
use phpDocumentor\Reflection\PseudoTypes\Generic;
use phpDocumentor\Reflection\PseudoTypes\ObjectShape;
use phpDocumentor\Reflection\Type;
use phpDocumentor\Reflection\Types\AbstractList;
use phpDocumentor\Reflection\Types\AggregatedType;
use phpDocumentor\Reflection\Types\Array_;
use phpDocumentor\Reflection\Types\Compound;
use phpDocumentor\Reflection\Types\Context;
use phpDocumentor\Reflection\Types\ContextFactory;
use phpDocumentor\Reflection\Types\Integer;
use phpDocumentor\Reflection\Types\Null_;
use phpDocumentor\Reflection\Types\Nullable;
use phpDocumentor\Reflection\Types\Object_;
use phpDocumentor\Reflection\Types\Parent_;
use phpDocumentor\Reflection\Types\Self_;
use phpDocumentor\Reflection\Types\Static_;
use phpDocumentor\Reflection\Types\This;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;

/**
 * Reads the DTO item type of an `array` or `Collection` field from its
 * traditional PHPDoc (`@param` on the constructor, `@var` on the property).
 *
 * Parsing and name resolution are delegated to phpDocumentor. PHPStan and
 * Psalm specific tags (`@phpstan-param`, `@psalm-var`, ...) are not read.
 *
 * Rule: when the PHPDoc of such a field names a DTO anywhere, it must be
 * exactly one supported DTO list form. Anything else is an InspectionException,
 * never silently ignored.
 */
final class DocBlockItemType
{
    private const ARRAY = 'array';

    private const SUPPORTED_FORMS = "'Dto[]', 'array<int, Dto>', or 'Collection<int, Dto>'";

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

        self::assertDocCommentsAvailable();

        $declared = [];
        if ($property !== null && ($type = self::varType($property, $class, $name)) !== null) {
            $declared['@var'] = self::itemClass($type, $container, $property->getDeclaringClass()->getName(), $class, $name);
        }

        if ($parameter !== null && ($type = self::paramType($parameter, $class, $name)) !== null) {
            $declared['@param'] = self::itemClass($type, $container, $parameter->getDeclaringClass()?->getName() ?? $class, $class, $name);
        }

        if (count(array_unique(array_map(static fn (?string $item): string => $item ?? '', $declared))) > 1) {
            throw new InspectionException(
                "The @var and @param PHPDoc of field '$name' on $class declare different item types"
            );
        }

        return $declared === [] ? null : reset($declared);
    }

    /**
     * `array`, a Collection class, false for an unsupported native union that
     * contains one of them, or null when the field is not a collection at all.
     *
     * @return self::ARRAY|class-string<Collection<array-key, mixed>>|false|null
     */
    private static function container(?ReflectionType $nativeType): string|false|null
    {
        if ($nativeType instanceof ReflectionUnionType) {
            foreach ($nativeType->getTypes() as $type) {
                if (self::container($type) !== null) {
                    return false;
                }
            }

            return null;
        }

        if (! ($nativeType instanceof ReflectionNamedType)) {
            return null;
        }

        $typeName = $nativeType->getName();
        if ($typeName === 'array') {
            return self::ARRAY;
        }

        return is_a($typeName, Collection::class, true) ? $typeName : null;
    }

    /**
     * @throws InspectionException
     */
    private static function assertDocCommentsAvailable(): void
    {
        (new ReflectionClass(self::class))->getDocComment() !== false || throw new InspectionException('PHPDoc comments are not available at runtime (opcache.save_comments=0); Data Forge reads collection item types from PHPDoc, so enable opcache.save_comments');
    }

    /**
     * @param  class-string  $class
     *
     * @throws InspectionException
     */
    private static function varType(ReflectionProperty $property, string $class, string $name): ?Type
    {
        $docComment = $property->getDocComment();
        if ($docComment === false) {
            return null;
        }

        $propertyName = $property->getName();

        return self::docType(
            $docComment,
            null,
            $property->getDeclaringClass(),
            static fn (ReflectionClass $declarer): bool => $declarer->hasProperty($propertyName)
                && $declarer->getProperty($propertyName)->getDocComment() === $docComment,
            $class,
            $name
        );
    }

    /**
     * @param  class-string  $class
     *
     * @throws InspectionException
     */
    private static function paramType(ReflectionParameter $parameter, string $class, string $name): ?Type
    {
        $constructor = $parameter->getDeclaringFunction();
        $docComment = $constructor->getDocComment();
        if ($docComment === false || ! ($constructor instanceof ReflectionMethod)) {
            return null;
        }

        $methodName = $constructor->getName();

        return self::docType(
            $docComment,
            $parameter->getName(),
            $constructor->getDeclaringClass(),
            static fn (ReflectionClass $declarer): bool => $declarer->hasMethod($methodName)
                && $declarer->getMethod($methodName)->getDocComment() === $docComment,
            $class,
            $name
        );
    }

    /**
     * The type of the field's `@var` tag (when $parameterName is null) or of
     * its `@param $parameterName` tag.
     *
     * PHP reports a trait member as declared by the class using the trait, so
     * reflection cannot tell which file's `use` imports apply to its PHPDoc.
     * The PHPDoc is therefore resolved in every class and trait that may
     * declare the member, and must mean the same type in all of them.
     *
     * @param  ReflectionClass<object>  $class
     * @param  Closure(ReflectionClass<object>): bool  $declares
     * @param  class-string  $fieldClass
     *
     * @throws InspectionException
     */
    private static function docType(
        string $docComment,
        ?string $parameterName,
        ReflectionClass $class,
        Closure $declares,
        string $fieldClass,
        string $name
    ): ?Type {
        $resolved = [];
        foreach (self::declarers($class, $declares) as $declarer) {
            $types = [];
            foreach (self::factory()->create($docComment, self::context($declarer))->getTags() as $tag) {
                if ($tag instanceof InvalidTag) {
                    throw new InspectionException(
                        "PHPDoc of field '$name' on $fieldClass has a tag that cannot be parsed: '{$tag}' (".$tag->getException()?->getMessage().')'
                    );
                }

                if (($parameterName === null && $tag instanceof Var_)
                    || ($tag instanceof Param && $tag->getVariableName() === $parameterName)) {
                    $types[] = $tag->getType();
                }
            }

            if (count($types) > 1) {
                $tagName = $parameterName === null ? '@var' : '@param';

                throw new InspectionException("PHPDoc of field '$name' on $fieldClass has more than one $tagName tag for it");
            }

            if ($types !== [] && $types[0] !== null) {
                $resolved[$declarer->getName()] = $types[0];
            }
        }

        $distinct = array_unique(array_map('strval', $resolved));
        if (count($distinct) > 1) {
            throw new InspectionException(
                "PHPDoc of field '$name' on $fieldClass resolves to different types in "
                .implode(' and ', array_keys($distinct)).": '".implode("' and '", $distinct)."'; use fully-qualified class names"
            );
        }

        return $resolved === [] ? null : reset($resolved);
    }

    /**
     * $class and every trait it uses, directly or through other traits, that
     * may declare the member.
     *
     * @param  ReflectionClass<object>  $class
     * @param  Closure(ReflectionClass<object>): bool  $declares
     * @return list<ReflectionClass<object>>
     */
    private static function declarers(ReflectionClass $class, Closure $declares): array
    {
        $declarers = [$class];
        foreach ($class->getTraits() as $trait) {
            if ($declares($trait)) {
                array_push($declarers, ...self::declarers($trait, $declares));
            }
        }

        return $declarers;
    }

    /**
     * @param  self::ARRAY|class-string<Collection<array-key, mixed>>|false  $container
     * @param  class-string  $selfClass  the class `self` refers to
     * @param  class-string  $class
     * @return class-string|null
     *
     * @throws InspectionException
     */
    private static function itemClass(Type $type, string|false $container, string $selfClass, string $class, string $name): ?string
    {
        if (! self::namesDto($type, $selfClass, $class, $name)) {
            return null;
        }

        if ($container === false) {
            throw new InspectionException(
                "Field '$name' on $class declares DTO items in PHPDoc '$type' but has a union native type; use 'array' or a Collection class"
            );
        }

        $items = [];
        $members = $type instanceof Compound ? iterator_to_array($type) : [$type];
        foreach ($members as $member) {
            $member = $member instanceof Nullable ? $member->getActualType() : $member;

            $item = self::listItemClass($member, $container, $selfClass);
            if ($item !== null) {
                $items[$item] = true;

                continue;
            }

            if ($member instanceof Null_ || self::isContainerType($member, $container)) {
                continue;
            }

            $nativeType = $container === self::ARRAY ? 'array' : $container;

            throw new InspectionException(
                "PHPDoc '$type' of field '$name' on $class is not a supported DTO list for native type '$nativeType' (at '$member'); use ".self::SUPPORTED_FORMS
            );
        }

        if (count($items) > 1) {
            throw new InspectionException("Field '$name' on $class declares more than one DTO item type in PHPDoc '$type'");
        }

        /** @var class-string */
        return array_key_first($items);
    }

    /**
     * The DTO item class when $member is exactly a supported DTO list form.
     *
     * @param  self::ARRAY|class-string<Collection<array-key, mixed>>  $container
     * @param  class-string  $selfClass
     * @return class-string|null
     */
    private static function listItemClass(Type $member, string $container, string $selfClass): ?string
    {
        if ($member::class === Array_::class) {
            /** @var Array_ $member */
            return self::isListKey($member->getOriginalKeyType())
                ? self::dtoReference($member->getValueType(), $selfClass)
                : null;
        }

        if (! ($member instanceof Generic) || $container === self::ARRAY) {
            return null;
        }

        $arguments = $member->getTypes();
        $annotatedClass = ltrim((string) $member->getFqsen(), '\\');
        if (! is_a($container, $annotatedClass, true) || count($arguments) > 2) {
            return null;
        }

        return self::isListKey(count($arguments) === 2 ? $arguments[0] : null)
            ? self::dtoReference($arguments[array_key_last($arguments)], $selfClass)
            : null;
    }

    private static function isListKey(?Type $key): bool
    {
        return $key === null || $key::class === Integer::class;
    }

    /**
     * @param  class-string  $selfClass
     * @return class-string|null
     */
    private static function dtoReference(Type $type, string $selfClass): ?string
    {
        if ($type instanceof Self_) {
            return DtoClass::isDto($selfClass) ? $selfClass : null;
        }

        if ($type::class !== Object_::class) {
            return null;
        }

        /** @var Object_ $type */
        $itemClass = ltrim((string) $type->getFqsen(), '\\');

        return DtoClass::isDto($itemClass) ? $itemClass : null;
    }

    /**
     * `array` on an array field, or the plain collection class in
     * `Collection|Dto[]`.
     *
     * @param  self::ARRAY|class-string<Collection<array-key, mixed>>  $container
     */
    private static function isContainerType(Type $member, string $container): bool
    {
        if ($container === self::ARRAY) {
            return $member::class === Array_::class && $member->getOriginalValueType() === null;
        }

        return $member::class === Object_::class
            && $member->getFqsen() !== null
            && is_a($container, ltrim((string) $member->getFqsen(), '\\'), true);
    }

    /**
     * Whether $type names a DTO anywhere. Also rejects unknown classes, which
     * usually mean a typo or a missing `use` import.
     *
     * @param  class-string  $selfClass
     * @param  class-string  $class
     *
     * @throws InspectionException
     */
    private static function namesDto(Type $type, string $selfClass, string $class, string $name): bool
    {
        $children = match (true) {
            $type instanceof AggregatedType => iterator_to_array($type),
            $type instanceof Nullable => [$type->getActualType()],
            $type instanceof ArrayShape, $type instanceof ObjectShape => array_map(
                static fn (object $item): Type => $item->getValue(),
                $type->getItems()
            ),
            $type instanceof AbstractList => array_filter([$type->getOriginalKeyType(), $type->getOriginalValueType()]),
            $type instanceof Generic => $type->getTypes(),
            default => [],
        };

        foreach ($children as $child) {
            if (self::namesDto($child, $selfClass, $class, $name)) {
                return true;
            }
        }

        if ($type instanceof Self_ || $type instanceof Static_ || $type instanceof This) {
            return DtoClass::isDto($selfClass);
        }

        if ($type instanceof Parent_) {
            $parentClass = get_parent_class($selfClass);

            return $parentClass !== false && DtoClass::isDto($parentClass);
        }

        if (! ($type instanceof Object_) || $type->getFqsen() === null) {
            return false;
        }

        $referencedClass = ltrim((string) $type->getFqsen(), '\\');
        if (! class_exists($referencedClass) && ! interface_exists($referencedClass)) {
            throw new InspectionException(
                "PHPDoc type '$referencedClass' of field '$name' on $class does not exist; check the class name and its use import"
            );
        }

        return DtoClass::isDto($referencedClass);
    }

    private static function factory(): DocBlockFactoryInterface
    {
        return self::$factory ??= DocBlockFactory::createInstance();
    }

    /**
     * @param  ReflectionClass<object>  $owner
     */
    private static function context(ReflectionClass $owner): Context
    {
        return self::$contexts[$owner->getName()] ??= (new ContextFactory())->createFromReflector($owner);
    }
}
