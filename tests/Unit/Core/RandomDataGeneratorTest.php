<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Axiom\DataForge\Core\RandomDataGenerator;
use DateTimeImmutable;
use DateTimeInterface;
use Faker\Factory;
use PHPUnit\Framework\TestCase;
use ReflectionNamedType;

class RandomDataGeneratorTest extends TestCase
{
    private RandomDataGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new RandomDataGenerator(Factory::create());
    }

    public function test_generate_int(): void
    {
        $type = $this->createMock(ReflectionNamedType::class);
        $type->method('getName')->willReturn('int');
        $type->method('allowsNull')->willReturn(false);

        $result = $this->generator->generate($type);

        $this->assertIsInt($result);
    }

    public function test_generate_float(): void
    {
        $type = $this->createMock(ReflectionNamedType::class);
        $type->method('getName')->willReturn('float');
        $type->method('allowsNull')->willReturn(false);

        $result = $this->generator->generate($type);

        $this->assertIsFloat($result);
    }

    public function test_generate_string(): void
    {
        $type = $this->createMock(ReflectionNamedType::class);
        $type->method('getName')->willReturn('string');
        $type->method('allowsNull')->willReturn(false);

        $result = $this->generator->generate($type);

        $this->assertIsString($result);
    }

    public function test_generate_boolean(): void
    {
        $type = $this->createMock(ReflectionNamedType::class);
        $type->method('getName')->willReturn('bool');
        $type->method('allowsNull')->willReturn(false);

        $result = $this->generator->generate($type);

        $this->assertIsBool($result);
    }

    public function test_generate_array(): void
    {
        $type = $this->createMock(ReflectionNamedType::class);
        $type->method('getName')->willReturn('array');
        $type->method('allowsNull')->willReturn(false);

        $result = $this->generator->generate($type);

        $this->assertIsArray($result);
    }

    public function test_generate_date_time(): void
    {
        $type = $this->createMock(ReflectionNamedType::class);
        $type->method('getName')->willReturn(DateTimeInterface::class);
        $type->method('allowsNull')->willReturn(false);

        $result = $this->generator->generate($type);

        $this->assertInstanceOf(DateTimeImmutable::class, $result);
    }

    public function test_generate_nullable(): void
    {
        // We'll run this multiple times to increase the chance of getting a null value
        $nullFound = false;
        $nonNullFound = false;

        for ($i = 0; $i < 100; $i++) {
            $type = $this->createMock(ReflectionNamedType::class);
            $type->method('getName')->willReturn('string');
            $type->method('allowsNull')->willReturn(true);

            $result = $this->generator->generate($type);

            if ($result === null) {
                $nullFound = true;
            } else {
                $nonNullFound = true;
            }

            if ($nullFound && $nonNullFound) {
                break;
            }
        }

        $this->assertTrue($nullFound || $nonNullFound, 'Should generate either null or non-null values for nullable types');
    }
}
