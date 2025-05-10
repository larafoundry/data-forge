<?php

declare(strict_types=1);

namespace Ws\DataBridge\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;

final class DtoType implements ValidationRule
{
    public function __construct(private readonly ReflectionProperty $property) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $type = $this->property->getType();

        if ($type === null) {
            return; // No type declared, accept any value
        }

        if (! $this->matchType($type, $value)) {
            $fail('The :attribute field has an invalid type.');
        }
    }

    private function matchType(ReflectionType $type, mixed $v): bool
    {
        /** @param ReflectionType $t */
        $check = function (ReflectionType $t) use ($v): bool {
            if ($t instanceof ReflectionNamedType) {
                if ($t->isBuiltin()) {
                    return $this->normalize(gettype($v)) === $t->getName();
                }
                /** @var class-string $name */
                $name = $t->getName();

                return $v instanceof $name;
            }

            return false;
        };

        if ($type instanceof ReflectionUnionType) {
            foreach ($type->getTypes() as $t) {
                if ($check($t)) {
                    return true;
                }
            }

            return false;
        }

        return $check($type);
    }

    private function normalize(string $phpType): string
    {
        return match ($phpType) {
            'integer' => 'int',
            'boolean' => 'bool',
            'double' => 'float',
            default => $phpType,
        };
    }
}
