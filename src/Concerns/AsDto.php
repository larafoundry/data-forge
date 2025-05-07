<?php

declare(strict_types=1);

namespace Ws\DataBridge\Concerns;

trait AsDto
{
    public function fromArray(array $attributes) {}

    public function rules() {}

    public function messages() {}
}
