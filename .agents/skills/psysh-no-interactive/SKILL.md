---
name: psysh-no-interactive
description: Use when Codex needs to run a non-interactive PsySH verification snippet in a PHP package repo, especially for DTO hydration checks, regression PoCs, before/after stash validation, or quick package behavior probes. Use a file-based PsySH script workflow to avoid shell heredoc hangs, `$variable` interpolation bugs, multiline quoting errors, and interactive PsySH prompts. For Laravel application state, artisan bootstrapping, Eloquent models, database rows, services, queues, or config, prefer laravel-tinker-runner instead.
---

# PsySH No Interaction

## Rule

Use a file-based PsySH script. Do not send fragile inline `printf`,
`cat <<EOF`, or multiline shell-quoted PHP commands to the user.

PsySH stdin files must contain PHP statements without an opening `<?php` tag.
End the file with an expression when PsySH should print a value.

## Workflow

1. Create a temporary `.psysh.php` file for the snippet.
2. Put `require 'vendor/autoload.php';` as the first statement for package repos.
3. Put throwaway PoC classes inside `eval(<<<'CODE' ... CODE);` inside the file.
4. Assign the observed result to `$result`.
5. End the file with `$result;`.
6. Run the file through the bundled runner from the project root.

Use the bundled runner:

```bash
.agents/skills/psysh-no-interactive/scripts/run-psysh-file.sh /tmp/check_nullable_nested.psysh.php
```

If the command must run from outside the package root, set `PSYSH_PROJECT_ROOT`
to the package directory.

## Snippet Shape

```php
require 'vendor/autoload.php';

eval(<<<'CODE'
namespace Poc\Example;

class ExampleDto
{
    use \Axiom\DataForge\Concerns\AsDto;

    public function __construct(public readonly string $name) {}
}
CODE);

try {
    $dto = Poc\Example\ExampleDto::fromArray(['name' => 'demo']);

    $result = [
        'ok' => true,
        'class' => $dto::class,
    ];
} catch (\Throwable $e) {
    $result = [
        'ok' => false,
        'error' => $e::class,
        'message' => $e->getMessage(),
    ];
}

$result;
```

## Output Contract

Return or show the exact command and the actual output. If the snippet was used
for before/after validation, use the same script file for both runs and clearly
label each output.

## Hard Bans

- Do not rely on shell heredoc delimiters in commands sent to the user.
- Do not put PHP variables inside double-quoted shell strings.
- Do not use inline multiline `eval(...)` in shell commands.
- Do not include `<?php` in files passed directly to PsySH stdin.
- Do not use this instead of Laravel Tinker for application state or database checks.
- Do not leave the user at a `>` continuation prompt.
