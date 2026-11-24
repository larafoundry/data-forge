<?php

declare(strict_types=1);

namespace Tests\Feature\DtoPipeline;

use ArrayObject;
use Axiom\DataForge\Concerns\AsDto;
use Axiom\DataForge\Exceptions\InspectionException;
use Axiom\DataForge\Schema\DtoInspector;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;
use stdClass;
use Tests\Feature\AsDto\Objects\BasicDto as Person;

/**
 * DTO item types of `array` and `Collection` fields come from traditional
 * PHPDoc (`@param` / `@var`), parsed and resolved by phpDocumentor.
 */
final class PhpDocCollectionItemTypeTest extends TestCase
{
    private const PEOPLE = [
        ['name' => 'Ada', 'age' => 36],
        ['name' => 'Grace', 'age' => 85],
    ];

    /**
     * @return iterable<string, array{class-string, string}>
     */
    public static function invalidDeclarations(): iterable
    {
        yield 'var and param disagree' => [
            DocConflictingTagsFixtureDto::class,
            "The @var and @param PHPDoc of field 'people' on ".DocConflictingTagsFixtureDto::class.' declare different item types',
        ];
        yield 'union item type' => [
            DocUnionItemFixtureDto::class,
            "Field 'people' on ".DocUnionItemFixtureDto::class." declares union item type '\\".Person::class.'|\\'.DocOtherItemDto::class."'; DTO collection items must have one DTO type",
        ];
        yield 'two DTO containers' => [
            DocTwoContainersFixtureDto::class,
            "Field 'people' on ".DocTwoContainersFixtureDto::class." declares more than one DTO item type in PHPDoc '\\".Person::class.'[]|\\'.DocOtherItemDto::class."[]'",
        ];
        yield 'nullable item type' => [
            DocNullableItemFixtureDto::class,
            "Field 'people' on ".DocNullableItemFixtureDto::class." declares nullable item type '?\\".Person::class."'; DTO collection items cannot be null",
        ];
        yield 'collection generic on an array field' => [
            DocGenericOnArrayFixtureDto::class,
            "PHPDoc '\\".Collection::class.'<int, \\'.Person::class.">' of field 'people' on ".DocGenericOnArrayFixtureDto::class
                ." does not match its native type; use '".Person::class."[]' or 'array<int, ".Person::class.">'",
        ];
        yield 'non-collection generic on a collection field' => [
            DocForeignGenericFixtureDto::class,
            "PHPDoc '\\".ArrayObject::class.'<int, \\'.Person::class.">' of field 'people' on ".DocForeignGenericFixtureDto::class
                ." does not match its native type; use 'Collection<int, ".Person::class.">'",
        ];
        yield 'list pseudo type' => [
            DocListFixtureDto::class,
            "PHPDoc 'list<\\".Person::class.">' of field 'people' on ".DocListFixtureDto::class
                ." is not supported; use '".Person::class."[]' or 'array<int, ".Person::class.">'",
        ];
        yield 'string keys on an array' => [
            DocStringKeyArrayFixtureDto::class,
            "PHPDoc 'array<string, \\".Person::class.">' of field 'people' on ".DocStringKeyArrayFixtureDto::class
                .' must use int keys; DTO collections are lists',
        ];
        yield 'string keys on a collection' => [
            DocStringKeyCollectionFixtureDto::class,
            "PHPDoc '\\".Collection::class.'<string, \\'.Person::class.">' of field 'people' on ".DocStringKeyCollectionFixtureDto::class
                .' must use int keys; DTO collections are lists',
        ];
    }

    public function test_supported_collection_forms_hydrate_a_collection_of_dtos(): void
    {
        $dto = DocCollectionFormsFixtureDto::fromArray([
            'generic' => self::PEOPLE,
            'keyed' => self::PEOPLE,
            'brackets' => self::PEOPLE,
            'union' => self::PEOPLE,
            'nullable' => self::PEOPLE,
            'prefixed' => self::PEOPLE,
            'qualified' => self::PEOPLE,
        ]);

        foreach (['generic', 'keyed', 'brackets', 'union', 'nullable', 'prefixed', 'qualified'] as $field) {
            $this->assertInstanceOf(Collection::class, $dto->{$field}, $field);
            $this->assertContainsOnlyInstancesOf(Person::class, $dto->{$field}, $field);
            $this->assertSame('Grace', $dto->{$field}[1]->name, $field);
        }
    }

    public function test_supported_array_forms_hydrate_a_list_of_dtos(): void
    {
        $dto = DocArrayFormsFixtureDto::fromArray([
            'brackets' => self::PEOPLE,
            'generic' => self::PEOPLE,
            'keyed' => self::PEOPLE,
            'nullable' => self::PEOPLE,
        ]);

        foreach (['brackets', 'generic', 'keyed', 'nullable'] as $field) {
            $this->assertIsArray($dto->{$field}, $field);
            $this->assertContainsOnlyInstancesOf(Person::class, $dto->{$field}, $field);
            $this->assertSame('Ada', $dto->{$field}[0]->name, $field);
        }
    }

    public function test_nullable_fields_accept_null(): void
    {
        $dto = DocArrayFormsFixtureDto::fromArray([
            'brackets' => self::PEOPLE,
            'generic' => self::PEOPLE,
            'keyed' => self::PEOPLE,
            'nullable' => null,
        ]);

        $this->assertNull($dto->nullable);
    }

    public function test_var_tags_on_properties_are_read(): void
    {
        $dto = DocVarTagFixtureDto::fromArray([
            'promoted' => self::PEOPLE,
            'members' => self::PEOPLE,
        ]);

        $this->assertContainsOnlyInstancesOf(Person::class, $dto->promoted);
        $this->assertContainsOnlyInstancesOf(Person::class, $dto->members);
    }

    public function test_matching_var_and_param_tags_are_accepted(): void
    {
        $inspector = new DtoInspector(DocMatchingTagsFixtureDto::class);

        $this->assertSame(Person::class, $inspector->getArrayItemClassForKey('people'));
    }

    public function test_collection_subclasses_are_hydrated_as_the_declared_class(): void
    {
        $dto = DocCollectionSubclassFixtureDto::fromArray(['people' => self::PEOPLE]);

        $this->assertInstanceOf(DocPeopleCollection::class, $dto->people);
        $this->assertContainsOnlyInstancesOf(Person::class, $dto->people);
    }

    public function test_phpstan_and_psalm_tags_are_ignored(): void
    {
        $inspector = new DtoInspector(DocToolSpecificTagsFixtureDto::class);

        $this->assertSame(Person::class, $inspector->getArrayItemClassForKey('people'));
        $this->assertNull($inspector->getArrayItemClassForKey('tags'));
    }

    public function test_non_dto_item_types_leave_the_field_untouched(): void
    {
        $inspector = new DtoInspector(DocNonDtoItemsFixtureDto::class);

        foreach (['objects', 'scalars', 'mixedScalars', 'nullableScalars', 'anyObjects', 'plain', 'undocumented', 'described', 'unionNative'] as $field) {
            $this->assertNull($inspector->getArrayItemClassForKey($field), $field);
        }

        $objects = new Collection([new stdClass()]);
        $dto = DocNonDtoItemsFixtureDto::fromArray([
            'objects' => $objects,
            'scalars' => ['a'],
            'mixedScalars' => [1, 'a'],
            'nullableScalars' => [null],
            'anyObjects' => [new stdClass()],
            'plain' => [['name' => 'raw']],
            'undocumented' => [['name' => 'raw']],
            'described' => [['name' => 'raw']],
            'unionNative' => [['name' => 'raw']],
        ]);

        $this->assertSame($objects, $dto->objects);
        $this->assertSame([['name' => 'raw']], $dto->plain);
    }

    /**
     * @param  class-string  $class
     *
     * @dataProvider invalidDeclarations
     */
    public function test_invalid_declarations_are_configuration_errors(string $class, string $message): void
    {
        $this->expectException(InspectionException::class);
        $this->expectExceptionMessage($message);

        new DtoInspector($class);
    }
}

final class DocOtherItemDto
{
    use AsDto;

    public function __construct(public readonly string $title) {}
}

/**
 * @extends Collection<int, Person>
 */
final class DocPeopleCollection extends Collection {}

final class DocCollectionFormsFixtureDto
{
    use AsDto;

    /**
     * @param  Collection<Person>  $generic
     * @param  Collection<int, Person>  $keyed
     * @param  Person[]  $brackets
     * @param  Collection|Person[]  $union
     * @param  Collection<int, Person>|null  $nullable
     * @param  ?Collection<int, Person>  $prefixed
     * @param  Collection<int, Person>  $qualified
     */
    public function __construct(
        public readonly Collection $generic,
        public readonly Collection $keyed,
        public readonly Collection $brackets,
        public readonly Collection $union,
        public readonly ?Collection $nullable,
        public readonly ?Collection $prefixed,
        public readonly Collection $qualified,
    ) {}
}

final class DocArrayFormsFixtureDto
{
    use AsDto;

    /**
     * @param  Person[]  $brackets
     * @param  array<Person>  $generic
     * @param  array<int, Person>  $keyed
     * @param  Person[]|null  $nullable
     */
    public function __construct(
        public readonly array $brackets,
        public readonly array $generic,
        public readonly array $keyed,
        public readonly ?array $nullable,
    ) {}
}

final class DocVarTagFixtureDto
{
    use AsDto;

    /** @var Person[] */
    public array $members = [];

    public function __construct(
        /** @var Collection<int, Person> */
        public readonly Collection $promoted,
    ) {}
}

final class DocMatchingTagsFixtureDto
{
    use AsDto;

    /**
     * @param  Person[]  $people
     */
    public function __construct(
        /** @var Person[] */
        public readonly array $people,
    ) {}
}

final class DocCollectionSubclassFixtureDto
{
    use AsDto;

    /**
     * @param  DocPeopleCollection<int, Person>  $people
     */
    public function __construct(public readonly DocPeopleCollection $people) {}
}

final class DocToolSpecificTagsFixtureDto
{
    use AsDto;

    /**
     * @param  Person[]  $people
     *
     * @phpstan-param  list<Person>  $people
     * @phpstan-param  list<Person>  $tags
     *
     * @psalm-param  list<Person>  $tags
     */
    public function __construct(
        public readonly array $people,
        public readonly array $tags,
    ) {}
}

final class DocNonDtoItemsFixtureDto
{
    use AsDto;

    /**
     * @param  Collection<int, stdClass>  $objects
     * @param  array<int, string>  $scalars
     * @param  array<int, int|string>  $mixedScalars
     * @param  array<int, ?string>  $nullableScalars
     * @param  object[]  $anyObjects
     * @param  array<int, Person>|Collection<int, Person>  $unionNative
     */
    public function __construct(
        public readonly Collection $objects,
        public readonly array $scalars,
        public readonly array $mixedScalars,
        public readonly array $nullableScalars,
        public readonly array $anyObjects,
        public readonly array $plain,
        public readonly array $undocumented,
        /** Raw rows, kept as they are. */
        public readonly array $described,
        public readonly array|Collection $unionNative,
    ) {}
}

final class DocConflictingTagsFixtureDto
{
    use AsDto;

    /**
     * @param  DocOtherItemDto[]  $people
     */
    public function __construct(
        /** @var Person[] */
        public readonly array $people,
    ) {}
}

final class DocUnionItemFixtureDto
{
    use AsDto;

    /**
     * @param  array<int, Person|DocOtherItemDto>  $people
     */
    public function __construct(public readonly array $people) {}
}

final class DocTwoContainersFixtureDto
{
    use AsDto;

    /**
     * @param  Person[]|DocOtherItemDto[]  $people
     */
    public function __construct(public readonly array $people) {}
}

final class DocNullableItemFixtureDto
{
    use AsDto;

    /**
     * @param  array<int, ?Person>  $people
     */
    public function __construct(public readonly array $people) {}
}

final class DocGenericOnArrayFixtureDto
{
    use AsDto;

    /**
     * @param  Collection<int, Person>  $people
     */
    public function __construct(public readonly array $people) {}
}

final class DocForeignGenericFixtureDto
{
    use AsDto;

    /**
     * @param  ArrayObject<int, Person>  $people
     */
    public function __construct(public readonly Collection $people) {}
}

final class DocListFixtureDto
{
    use AsDto;

    /**
     * @param  list<Person>  $people
     */
    public function __construct(public readonly array $people) {}
}

final class DocStringKeyArrayFixtureDto
{
    use AsDto;

    /**
     * @param  array<string, Person>  $people
     */
    public function __construct(public readonly array $people) {}
}

final class DocStringKeyCollectionFixtureDto
{
    use AsDto;

    /**
     * @param  Collection<string, Person>  $people
     */
    public function __construct(public readonly Collection $people) {}
}
