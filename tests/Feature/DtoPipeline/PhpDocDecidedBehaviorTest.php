<?php

declare(strict_types=1);

namespace Tests\Feature\DtoPipeline;

use Axiom\DataForge\Concerns\AsDto;
use Axiom\DataForge\Exceptions\InspectionException;
use Axiom\DataForge\Schema\DtoInspector;
use PHPUnit\Framework\TestCase;
use Tests\Feature\AsDto\Objects\BasicDto as Person;
use Tests\Feature\DtoPipeline\KnownLimitations\Aliased\AliasedConstructorDto;
use Tests\Feature\DtoPipeline\KnownLimitations\SecondBlockDto;

require_once __DIR__.'/Fixtures/phpdoc-known-limitations.php';

/**
 * PHPDoc item type behavior that was reviewed and kept on purpose.
 *
 * Each test states why. Reviewers (human or AI): these are decisions, not
 * bugs. Do not report them again unless the public contract in README.md or
 * AGENTS.md changes. A failing "known limitation" test means the upstream
 * library changed: update the test and the docs instead of working around it.
 */
final class PhpDocDecidedBehaviorTest extends TestCase
{
    private const PERSON = ['name' => 'Ada', 'age' => 36];

    /**
     * Only DTO object values are DTO items. A list of class names or callables
     * holds strings or callables, so it stays untouched like any non-DTO array.
     */
    public function test_class_strings_and_callables_of_a_dto_are_not_dto_items(): void
    {
        $dto = DecidedNonObjectItemsDto::fromArray(['classes' => [123], 'factories' => [123]]);

        $this->assertSame([123], $dto->classes);
        $this->assertSame([123], $dto->factories);
    }

    /**
     * Invalid PHPDoc that phpDocumentor normalizes before Data Forge sees it
     * cannot be detected without parsing the docblock ourselves, which the
     * project forbids. type-resolver drops the extra argument, so the type
     * becomes `array<int, string>`.
     */
    public function test_invalid_phpdoc_normalized_by_phpdocumentor_is_not_detected(): void
    {
        $this->assertNull((new DtoInspector(DecidedThreeArgumentArrayDto::class))->getArrayItemClassForKey('people'));
    }

    /**
     * Default values are written by the DTO author, like a direct
     * `new Dto(...)` call. They are trusted and used as they are.
     */
    public function test_default_values_are_not_hydrated(): void
    {
        $this->assertSame([self::PERSON], DecidedRawDefaultDto::fromArray([])->people);
    }

    /**
     * Odd nesting is rejected with a clear error instead of being supported.
     * `array|Dto[]|null` is the supported spelling.
     */
    public function test_parenthesized_nullable_union_is_rejected(): void
    {
        $this->expectException(InspectionException::class);
        $this->expectExceptionMessage('is not a supported DTO list');

        new DtoInspector(DecidedParenthesizedNullableDto::class);
    }

    /**
     * Any tag phpDocumentor cannot parse rejects the field, even an unrelated
     * one. Filtering by tag name is unsafe because phpDocumentor reports some
     * broken `@param` tags under the name `method`.
     */
    public function test_any_unparsable_tag_in_the_docblock_rejects_the_field(): void
    {
        $this->expectException(InspectionException::class);
        $this->expectExceptionMessage(
            "PHPDoc of field 'people' on ".DecidedUnrelatedBrokenTagDto::class." has a tag that cannot be parsed: '@throws'"
        );

        new DtoInspector(DecidedUnrelatedBrokenTagDto::class);
    }

    /**
     * A `@param` tag is matched to its parameter by name. After a rename, the
     * field has no item type PHPDoc and is left untouched, like any other
     * undocumented array. PHPStan and IDEs report the stale tag.
     */
    public function test_param_tag_with_a_stale_name_is_not_matched(): void
    {
        $dto = DecidedRenamedParameterDto::fromArray(['users' => [['age' => 25]]]);

        $this->assertSame([['age' => 25]], $dto->users);
    }

    /**
     * Known limitation of phpDocumentor's ContextFactory: when one file
     * declares the same namespace twice, every block uses the imports of the
     * first one. PSR-4 code has one class per file and never hits this.
     */
    public function test_known_limitation_repeated_namespace_block_uses_the_first_block_imports(): void
    {
        $this->assertSame(
            Person::class,
            (new DtoInspector(SecondBlockDto::class))->getArrayItemClassForKey('members')
        );
    }

    /**
     * Known limitation: a trait method aliased as `__construct` is reported
     * by reflection as a method of the class, so its PHPDoc is read with the
     * class file's imports. Supporting it would mean following trait alias
     * tables for a construct DTOs do not use. Here the trait imports `Person`
     * but the class file does not, so the name does not resolve.
     */
    public function test_known_limitation_trait_method_aliased_as_constructor_uses_the_class_imports(): void
    {
        $this->expectException(InspectionException::class);
        $this->expectExceptionMessage(
            "PHPDoc type 'Tests\\Feature\\DtoPipeline\\KnownLimitations\\Aliased\\Person' of field 'people' on "
            .AliasedConstructorDto::class.' does not exist'
        );

        new DtoInspector(AliasedConstructorDto::class);
    }
}

final class DecidedNonObjectItemsDto
{
    use AsDto;

    /**
     * @param  array<class-string<Person>>  $classes
     * @param  array<callable(): Person>  $factories
     */
    public function __construct(
        public readonly array $classes,
        public readonly array $factories,
    ) {}
}

final class DecidedThreeArgumentArrayDto
{
    use AsDto;

    /**
     * @param  array<Person, int, string>  $people
     */
    public function __construct(public readonly array $people) {}
}

final class DecidedRawDefaultDto
{
    use AsDto;

    /**
     * @param  Person[]  $people
     */
    public function __construct(public readonly array $people = [['name' => 'Ada', 'age' => 36]]) {}
}

final class DecidedParenthesizedNullableDto
{
    use AsDto;

    /**
     * @param  ?(array|Person[])  $people
     */
    public function __construct(public readonly ?array $people) {}
}

final class DecidedUnrelatedBrokenTagDto
{
    use AsDto;

    /**
     * @param  Person[]  $people
     *
     * @throws
     */
    public function __construct(public readonly array $people) {}
}

final class DecidedRenamedParameterDto
{
    use AsDto;

    /**
     * @param  Person[]  $members
     */
    public function __construct(public readonly array $users) {}
}
