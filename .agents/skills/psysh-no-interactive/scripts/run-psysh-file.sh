#!/usr/bin/env bash
set -euo pipefail

if [[ $# -ne 1 ]]; then
  printf 'Usage: %s <psysh-script-file>\n' "$0" >&2
  exit 64
fi

script_file=$1

if [[ ! -f "$script_file" ]]; then
  printf 'PsySH script not found: %s\n' "$script_file" >&2
  exit 66
fi

script_file=$(cd "$(dirname "$script_file")" && pwd)/$(basename "$script_file")
project_root=${PSYSH_PROJECT_ROOT:-$(pwd)}

if [[ ! -d "$project_root" ]]; then
  printf 'PSYSH_PROJECT_ROOT is not a directory: %s\n' "$project_root" >&2
  exit 66
fi

project_root=$(cd "$project_root" && pwd)

if grep -Fq '<?php' "$script_file"; then
  printf 'PsySH stdin script must not include an opening <?php tag: %s\n' "$script_file" >&2
  exit 65
fi

if [[ ! -f "$project_root/vendor/autoload.php" ]]; then
  printf 'vendor/autoload.php is missing under project root: %s\n' "$project_root" >&2
  exit 69
fi

if [[ ! -x "$project_root/vendor/bin/psysh" ]]; then
  printf 'vendor/bin/psysh is missing or not executable under project root: %s\n' "$project_root" >&2
  exit 69
fi

cd "$project_root"
vendor/bin/psysh --no-interactive --raw-output --no-color --no-pager < "$script_file"
