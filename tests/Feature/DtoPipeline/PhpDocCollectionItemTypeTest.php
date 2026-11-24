<?php

declare(strict_types=1);

namespace Tests\Feature\DtoPipeline;

use ArgumentCountError;
use ArrayObject;
use Axiom\DataForge\Concerns\AsDto;
use Axiom\DataForge\Exceptions\InspectionException;
use Axiom\DataForge\Exceptions\InstantiationException;
use Axiom\DataForge\Exceptions\ValidationException;
use Axiom\DataForge\Schema\DtoInspector;
use Illuminate\Support\Collection;
use IteratorAggregate;
use PHPUnit\Framework\TestCase;
use stdClass;
use Tests\Feature\AsDto\Objects\BasicDto as Person;
use Tests\Feature\DtoPipeline\Objects\PhpDocMemberConstructorTrait;
use Tests\Feature\DtoPipeline\Objects\PhpDocMemberPropertyTrait;

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
        $unsupported = static fn (string $class, string $native): array => [
            $class,
            "of field 'people' on $class is not a supported DTO list for native type '$native'",
        ];

        yield 'var and param disagree' => [
            DocConflictingTagsFixtureDto::class,
            "The @var and @param PHPDoc of field 'people' on ".DocConflictingTagsFixtureDto::class.' declare different item types',
        ];
        yield 'two DTO item types' => [
            DocTwoContainersFixtureDto::class,
            "Field 'people' on ".DocTwoContainersFixtureDto::class.' declares more than one DTO item type in PHPDoc',
        ];
        yield 'union native type' => [
            DocUnionNativeFixtureDto::class,
            "Field 'people' on ".DocUnionNativeFixtureDto::class." declares DTO items in PHPDoc '\\".Person::class
                .'[]\' but has a union or intersection native type; use \'array\' or a Collection class',
        ];
        yield 'intersection native type' => [
            DocIntersectionNativeFixtureDto::class,
            "Field 'people' on ".DocIntersectionNativeFixtureDto::class." declares DTO items in PHPDoc '\\".Person::class
                .'[]\' but has a union or intersection native type; use \'array\' or a Collection class',
        ];
        yield 'two @param tags for the field' => [
            DocDuplicateParamFixtureDto::class,
            "PHPDoc of field 'people' on ".DocDuplicateParamFixtureDto::class.' has more than one @param tag for it',
        ];
        yield 'two @var tags for the field' => [
            DocDuplicateVarFixtureDto::class,
            "PHPDoc of field 'people' on ".DocDuplicateVarFixtureDto::class.' has more than one @var tag for it',
        ];
        yield 'trait PHPDoc that resolves differently in the class' => [
            DocTraitPropertyFixtureDto::class,
            "PHPDoc of field 'members' on ".DocTraitPropertyFixtureDto::class.' resolves to different types in '
                .DocTraitPropertyFixtureDto::class.' and '.PhpDocMemberPropertyTrait::class
                .": '\\Tests\\Feature\\DtoPipeline\\Member[]' and '\\".Person::class."[]'; use fully-qualified class names",
        ];
        // phpDocumentor reports this broken @param under the tag name "method".
        yield 'tag phpDocumentor cannot parse' => [
            DocUnparsableShapeFixtureDto::class,
            "PHPDoc of field 'people' on ".DocUnparsableShapeFixtureDto::class
                ." has a tag that cannot be parsed: '@method non-empty-array{first: Person} \$people' (Unsupported array shape kind)",
        ];
        yield 'tag phpDocumentor refuses to read' => [
            DocTagTypoFixtureDto::class,
            "PHPDoc of field 'people' on ".DocTagTypoFixtureDto::class.' cannot be parsed: The tag "@param\\',
        ];
        yield 'suggested forms for an array field' => [
            DocGenericOnArrayFixtureDto::class,
            "; use 'Dto[]' or 'array<int, Dto>'",
        ];
        yield 'suggested forms for a collection field' => [
            DocStringKeyCollectionFixtureDto::class,
            "; use 'Dto[]', 'array<int, Dto>', or 'Collection<int, Dto>'",
        ];
        yield 'unknown class' => [
            DocUnknownClassFixtureDto::class,
            "PHPDoc type 'Tests\\Feature\\DtoPipeline\\Persn' of field 'people' on ".DocUnknownClassFixtureDto::class
                .' does not exist; check the class name and its use import',
        ];
        yield 'union item type' => $unsupported(DocUnionItemFixtureDto::class, 'array');
        yield 'nullable item type' => $unsupported(DocNullableItemFixtureDto::class, 'array');
        yield 'nested list' => $unsupported(DocNestedListFixtureDto::class, 'array');
        yield 'static item type' => $unsupported(DocStaticItemFixtureDto::class, 'array');
        yield 'parent item type' => $unsupported(DocParentItemFixtureDto::class, 'array');
        yield 'conditional type' => $unsupported(DocConditionalFixtureDto::class, 'array');
        yield 'DTO list mixed with another list' => $unsupported(DocMixedListFixtureDto::class, 'array');
        yield 'list pseudo type' => $unsupported(DocListFixtureDto::class, 'array');
        yield 'array shape' => $unsupported(DocShapeFixtureDto::class, 'array');
        yield 'string keys on an array' => $unsupported(DocStringKeyArrayFixtureDto::class, 'array');
        yield 'positive-int keys on an array' => $unsupported(DocPositiveIntKeyFixtureDto::class, 'array');
        yield 'collection generic on an array field' => $unsupported(DocGenericOnArrayFixtureDto::class, 'array');
        yield 'string keys on a collection' => $unsupported(DocStringKeyCollectionFixtureDto::class, Collection::class);
        yield 'three generic arguments' => $unsupported(DocThreeArgumentsFixtureDto::class, Collection::class);
        yield 'non-collection generic' => $unsupported(DocForeignGenericFixtureDto::class, Collection::class);
        yield 'unrelated collection generic' => $unsupported(DocUnrelatedCollectionFixtureDto::class, DocPeopleCollection::class);
    }

    public function test_supported_collection_forms_hydrate_a_collection_of_dtos(): void
    {
        $fields = ['generic', 'keyed', 'brackets', 'union', 'nullable', 'prefixed', 'qualified', 'interface'];
        $dto = DocCollectionFormsFixtureDto::fromArray(array_fill_keys($fields, self::PEOPLE));

        foreach ($fields as $field) {
            $this->assertInstanceOf(Collection::class, $dto->{$field}, $field);
            $this->assertContainsOnlyInstancesOf(Person::class, $dto->{$field}, $field);
            $this->assertSame('Grace', $dto->{$field}[1]->name, $field);
        }
    }

    public function test_supported_array_forms_hydrate_a_list_of_dtos(): void
    {
        $fields = ['brackets', 'generic', 'keyed', 'nullable', 'withArray'];
        $dto = DocArrayFormsFixtureDto::fromArray(array_fill_keys($fields, self::PEOPLE));

        foreach ($fields as $field) {
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
            'withArray' => self::PEOPLE,
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

    public function test_trait_phpdoc_with_fully_qualified_names_is_read(): void
    {
        $direct = DocTraitConstructorFixtureDto::fromArray(['members' => self::PEOPLE]);
        $nested = DocNestedTraitConstructorFixtureDto::fromArray(['members' => self::PEOPLE]);

        $this->assertContainsOnlyInstancesOf(Person::class, $direct->members);
        $this->assertContainsOnlyInstancesOf(Person::class, $nested->members);
    }

    public function test_self_item_type_refers_to_the_declaring_dto(): void
    {
        $tree = DocTreeFixtureDto::fromArray([
            'id' => 1,
            'children' => [['id' => 2, 'children' => [['id' => 3]]]],
        ]);

        $this->assertInstanceOf(DocTreeFixtureDto::class, $tree->children[0]);
        $this->assertSame(3, $tree->children[0]->children[0]->id);

        try {
            DocTreeFixtureDto::fromArray(['id' => 1, 'children' => [['id' => 'bad']]]);

            $this->fail('Validation should have failed');
        } catch (ValidationException $e) {
            $this->assertSame(['children.0.id' => ['The id field has an invalid type.']], $e->errors);
        }
    }

    public function test_collection_subclasses_are_hydrated_as_the_declared_class(): void
    {
        $dto = DocCollectionSubclassFixtureDto::fromArray(['people' => self::PEOPLE]);

        $this->assertInstanceOf(DocPeopleCollection::class, $dto->people);
        $this->assertContainsOnlyInstancesOf(Person::class, $dto->people);
    }

    public function test_collection_class_that_cannot_be_constructed_from_items_is_an_instantiation_error(): void
    {
        try {
            DocModeCollectionFixtureDto::fromArray(['people' => self::PEOPLE]);

            $this->fail('Instantiation should have failed');
        } catch (InstantiationException $e) {
            $this->assertSame('Failed to create '.DocModeCollection::class." for field 'people'", $e->getMessage());
            $this->assertInstanceOf(ArgumentCountError::class, $e->getPrevious());
        }
    }

    public function test_collection_class_that_drops_its_items_is_an_instantiation_error(): void
    {
        $this->expectException(InstantiationException::class);
        $this->expectExceptionMessage(
            DocEmptyCollection::class." did not keep the items passed to its constructor for field 'people'"
        );

        DocEmptyCollectionFixtureDto::fromArray(['people' => self::PEOPLE]);
    }

    public function test_phpstan_and_psalm_tags_are_ignored(): void
    {
        $inspector = new DtoInspector(DocToolSpecificTagsFixtureDto::class);

        $this->assertSame(Person::class, $inspector->getArrayItemClassForKey('people'));
        $this->assertNull($inspector->getArrayItemClassForKey('tags'));
    }

    public function test_non_dto_item_types_leave_the_field_untouched(): void
    {
        $fields = ['objects', 'scalars', 'mixedScalars', 'nullableScalars', 'anyObjects', 'plain', 'undocumented', 'described', 'otherTag', 'rawUnion', 'untyped'];
        $inspector = new DtoInspector(DocNonDtoItemsFixtureDto::class);

        foreach ($fields as $field) {
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
            'otherTag' => [['name' => 'raw']],
            'rawUnion' => [['name' => 'raw']],
        ]);

        $this->assertSame($objects, $dto->objects);
        $this->assertSame([['name' => 'raw']], $dto->plain);
        $this->assertNull($dto->untyped);
    }

    public function test_malformed_tags_are_configuration_errors(): void
    {
        $this->expectException(InspectionException::class);
        $this->expectExceptionMessageMatches(
            "/^PHPDoc of field 'people' on ".preg_quote(DocMalformedTagFixtureDto::class, '/')." has a tag that cannot be parsed: '@param array<int, Person  \\\$people' \\(.+\\)$/"
        );

        new DtoInspector(DocMalformedTagFixtureDto::class);
    }

    public function test_stripped_doc_comments_are_a_configuration_error(): void
    {
        $script = <<<'PHP'
            <?php
            require $argv[1].'/vendor/autoload.php';
            if (! function_exists('opcache_get_status') || ! (opcache_get_status(false)['opcache_enabled'] ?? false)) {
                echo 'skip: opcache is not available';
                exit;
            }
            try {
                new Axiom\DataForge\Schema\DtoInspector(Tests\Feature\AsDto\Objects\NestedArrayDto::class);
                echo 'no exception';
            } catch (Axiom\DataForge\Exceptions\InspectionException $e) {
                echo $e->getMessage();
            }
            PHP;
        $file = tempnam(sys_get_temp_dir(), 'data-forge-opcache-');
        file_put_contents($file, $script);

        $command = escapeshellarg(PHP_BINARY).' -d opcache.enable_cli=1 -d opcache.save_comments=0 '
            .escapeshellarg($file).' '.escapeshellarg(dirname(__DIR__, 3));
        $output = (string) shell_exec($command);
        unlink($file);

        if (str_starts_with($output, 'skip: ')) {
            $this->markTestSkipped(substr($output, 6));
        }

        $this->assertSame(
            'PHPDoc comments are not available at runtime (opcache.save_comments=0); Data Forge reads collection item types from PHPDoc, so enable opcache.save_comments',
            $output
        );
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
class DocPeopleCollection extends Collection {}

/**
 * @extends Collection<int, Person>
 */
final class DocOtherCollection extends Collection {}

/**
 * @extends Collection<int, Person>
 */
final class DocModeCollection extends Collection
{
    /**
     * @param  array<int, Person>  $items
     */
    public function __construct(array $items, public readonly string $mode)
    {
        parent::__construct($items);
    }
}

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
     * @param  IteratorAggregate<int, Person>  $interface
     */
    public function __construct(
        public readonly Collection $generic,
        public readonly Collection $keyed,
        public readonly Collection $brackets,
        public readonly Collection $union,
        public readonly ?Collection $nullable,
        public readonly ?Collection $prefixed,
        public readonly Collection $qualified,
        public readonly Collection $interface,
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
     * @param  array|Person[]  $withArray
     */
    public function __construct(
        public readonly array $brackets,
        public readonly array $generic,
        public readonly array $keyed,
        public readonly ?array $nullable,
        public readonly array $withArray,
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

final class DocTraitPropertyFixtureDto
{
    use AsDto;
    use PhpDocMemberPropertyTrait;
}

final class DocTraitConstructorFixtureDto
{
    use AsDto;
    use PhpDocMemberConstructorTrait;
}

trait DocMemberConstructorWrapperTrait
{
    use PhpDocMemberConstructorTrait;
}

final class DocNestedTraitConstructorFixtureDto
{
    use AsDto;
    use DocMemberConstructorWrapperTrait;
}

final class DocEmptyCollection extends Collection
{
    public function __construct()
    {
        parent::__construct([]);
    }
}

final class DocEmptyCollectionFixtureDto
{
    use AsDto;

    /**
     * @param  DocEmptyCollection<int, Person>  $people
     */
    public function __construct(public readonly DocEmptyCollection $people) {}
}

final class DocDuplicateParamFixtureDto
{
    use AsDto;

    /**
     * @param  string[]  $people
     * @param  Person[]  $people
     */
    public function __construct(public readonly array $people) {}
}

final class DocDuplicateVarFixtureDto
{
    use AsDto;

    /**
     * @var string[]
     * @var Person[]
     */
    public array $people = [];
}

class DocParentDto
{
    use AsDto;
}

final class DocParentItemFixtureDto extends DocParentDto
{
    /**
     * @param  parent[]  $people
     */
    public function __construct(public readonly array $people) {}
}

final class DocPositiveIntKeyFixtureDto
{
    use AsDto;

    /**
     * @param  array<positive-int, Person>  $people
     */
    public function __construct(public readonly array $people) {}
}

final class DocTreeFixtureDto
{
    use AsDto;

    /**
     * @param  self[]  $children
     */
    public function __construct(
        public readonly int $id,
        public readonly array $children = [],
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

final class DocModeCollectionFixtureDto
{
    use AsDto;

    /**
     * @param  DocModeCollection<int, Person>  $people
     */
    public function __construct(public readonly DocModeCollection $people) {}
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
     * Untyped fields are never hydrated, whatever their PHPDoc says.
     *
     * @var Person[]
     */
    public $untyped = null;

    /**
     * @param  Collection<int, stdClass>  $objects
     * @param  array<int, string>  $scalars
     * @param  array<int, int|string>  $mixedScalars
     * @param  array<int, ?string>  $nullableScalars
     * @param  object[]  $anyObjects
     * @param  array<int, array<string, string>>|Collection<int, string>  $rawUnion
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
        /** @see Person */
        public readonly array $otherTag,
        public readonly array|Collection $rawUnion,
    ) {}
}

final class DocMalformedTagFixtureDto
{
    use AsDto;

    /**
     * @param  array<int, Person  $people
     */
    public function __construct(public readonly array $people) {}
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

final class DocTwoContainersFixtureDto
{
    use AsDto;

    /**
     * @param  Person[]|DocOtherItemDto[]  $people
     */
    public function __construct(public readonly array $people) {}
}

final class DocUnionNativeFixtureDto
{
    use AsDto;

    /**
     * @param  Person[]  $people
     */
    public function __construct(public readonly array|Collection $people) {}
}

final class DocUnknownClassFixtureDto
{
    use AsDto;

    /**
     * @param  Persn[]  $people
     */
    public function __construct(public readonly array $people) {}
}

final class DocUnionItemFixtureDto
{
    use AsDto;

    /**
     * @param  array<int, Person|DocOtherItemDto>  $people
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

final class DocNestedListFixtureDto
{
    use AsDto;

    /**
     * @param  Person[][]  $people
     */
    public function __construct(public readonly array $people) {}
}

final class DocStaticItemFixtureDto
{
    use AsDto;

    /**
     * @param  static[]  $people
     */
    public function __construct(public readonly array $people) {}
}

final class DocMixedListFixtureDto
{
    use AsDto;

    /**
     * @param  Person[]|string[]  $people
     */
    public function __construct(public readonly array $people) {}
}

final class DocListFixtureDto
{
    use AsDto;

    /**
     * @param  list<Person>  $people
     */
    public function __construct(public readonly array $people) {}
}

final class DocShapeFixtureDto
{
    use AsDto;

    /**
     * @param  array{first: Person}  $people
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

final class DocGenericOnArrayFixtureDto
{
    use AsDto;

    /**
     * @param  Collection<int, Person>  $people
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

final class DocThreeArgumentsFixtureDto
{
    use AsDto;

    /**
     * @param  Collection<int, string, Person>  $people
     */
    public function __construct(public readonly Collection $people) {}
}

final class DocForeignGenericFixtureDto
{
    use AsDto;

    /**
     * @param  ArrayObject<int, Person>  $people
     */
    public function __construct(public readonly Collection $people) {}
}

final class DocUnrelatedCollectionFixtureDto
{
    use AsDto;

    /**
     * @param  DocOtherCollection<int, Person>  $people
     */
    public function __construct(public readonly DocPeopleCollection $people) {}
}

final class DocUnparsableShapeFixtureDto
{
    use AsDto;

    /**
     * @param  non-empty-array{first: Person}  $people
     */
    public function __construct(public readonly array $people) {}
}

interface DocMarker {}

final class DocIntersectionNativeFixtureDto
{
    use AsDto;

    /**
     * @param  Person[]  $people
     */
    public function __construct(public readonly Collection&DocMarker $people) {}
}

final class DocTagTypoFixtureDto
{
    use AsDto;

    /**
     * @param\Tests\Feature\AsDto\Objects\BasicDto[] $people
     */
    public function __construct(public readonly array $people) {}
}

final class DocConditionalFixtureDto
{
    use AsDto;

    /**
     * @param  ($flag is true ? Person[] : string[])  $people
     */
    public function __construct(public readonly bool $flag, public readonly array $people) {}
}
