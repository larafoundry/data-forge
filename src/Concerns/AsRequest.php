<?php

declare(strict_types=1);

namespace Ws\DataBridge\Concerns;

use Carbon\CarbonImmutable;
use Illuminate\Container\Container;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Stringable;

trait AsRequest
{
    protected ?Request $_request = null;

    /** @throws BindingResolutionException */
    public function exists(string|array $key): bool
    {
        return $this->request()->exists($key);
    }

    /** @throws BindingResolutionException */
    public function has(string|array $key): bool
    {
        return $this->request()->has($key);
    }

    public function filled(string|array $key): bool
    {
        return $this->request()->filled($key);
    }

    /** @throws BindingResolutionException */
    public function hasAny(array|string $keys): bool
    {
        return $this->request()->hasAny($keys);
    }

    /** @throws BindingResolutionException */
    public function whenHas(string $key, callable $callback, ?callable $default = null): mixed
    {
        return $this->request()->whenHas($key, $callback, $default);
    }

    /** @throws BindingResolutionException */
    public function isNotFilled(string|array $key): bool
    {
        return $this->request()->isNotFilled($key);
    }

    /** @throws BindingResolutionException */
    public function anyFilled(array|string $keys): bool
    {
        return $this->request()->anyFilled($keys);
    }

    /** @throws BindingResolutionException */
    public function whenFilled(string $key, callable $callback, ?callable $default = null): mixed
    {
        return $this->request()->whenFilled($key, $callback, $default);
    }

    /** @throws BindingResolutionException */
    public function missing(string|array $key): bool
    {
        return $this->request()->missing($key);
    }

    /** @throws BindingResolutionException */
    public function whenMissing(string $key, callable $callback, ?callable $default = null): mixed
    {
        return $this->request()->whenMissing($key, $callback, $default);
    }

    /** @throws BindingResolutionException */
    public function keys(): array
    {
        return $this->request()->keys();
    }

    /** @throws BindingResolutionException */
    public function all(mixed $keys = null): array
    {
        return $this->request()->all($keys);
    }

    /** @throws BindingResolutionException */
    public function input(?string $key = null, mixed $default = null): mixed
    {
        return $this->request()->input($key, $default);
    }

    /** @throws BindingResolutionException */
    public function str(string $key, mixed $default = null): Stringable
    {
        return $this->request()->str($key, $default);
    }

    /** @throws BindingResolutionException */
    public function integer(string $key, int $default = 0): int
    {
        return $this->request()->integer($key, $default);
    }

    /** @throws BindingResolutionException */
    public function boolean(string $key, bool $default = false): bool
    {
        return $this->request()->boolean($key, $default);
    }

    /** @throws BindingResolutionException */
    public function float(string $key, float $default = 0.0): float
    {
        return $this->request()->float($key, $default);
    }

    /** @throws BindingResolutionException */
    public function date(string $key, ?string $format = null, ?string $tz = null): Carbon|CarbonImmutable|null
    {
        return $this->request()->date($key, $format, $tz);
    }

    /** @throws BindingResolutionException */
    public function enum(string $key, string $enumClass): ?object
    {
        return $this->request()->enum($key, $enumClass);
    }

    /** @throws BindingResolutionException */
    public function collect(string|array|null $key = null): Collection
    {
        return $this->request()->collect($key);
    }

    /** @throws BindingResolutionException */
    public function only(array|string $keys): array
    {
        return $this->request()->only($keys);
    }

    /** @throws BindingResolutionException */
    public function except(array|string $keys): array
    {
        return $this->request()->except($keys);
    }

    /** @throws BindingResolutionException */
    public function query(?string $key = null, mixed $default = null): string|array|null
    {
        return $this->request()->query($key, $default);
    }

    /** @throws BindingResolutionException */
    public function file(?string $key = null, mixed $default = null): UploadedFile|array|null
    {
        return $this->request()->file($key, $default);
    }

    /** @throws BindingResolutionException */
    public function hasFile(string $key): bool
    {
        return $this->request()->hasFile($key);
    }

    /** @throws BindingResolutionException */
    public function allFiles(): array
    {
        return $this->request()->allFiles();
    }

    /**
     * @throws BindingResolutionException
     */
    protected function request(): Request
    {
        if ($this->_request === null) {
            $this->_request = Container::getInstance()->make(Request::class);
        }

        return $this->_request;
    }
}
