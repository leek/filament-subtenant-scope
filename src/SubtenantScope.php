<?php

declare(strict_types=1);

namespace Leek\FilamentSubtenantScope;

use BackedEnum;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class SubtenantScope
{
    private string $labelAttribute = 'name';

    private string|BackedEnum|null $icon = null;

    private ?Closure $optionsQuery = null;

    /**
     * @param  class-string<Model>  $modelClass
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $modelClass,
        public readonly string $column,
    ) {}

    /**
     * @param  class-string<Model>  $modelClass
     */
    public static function make(string $key, string $label, string $modelClass, string $column): static
    {
        return new self($key, $label, $modelClass, $column);
    }

    public function icon(string|BackedEnum $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    public function labelAttribute(string $attribute): static
    {
        $this->labelAttribute = $attribute;

        return $this;
    }

    /**
     * Closure receives the authenticated user (or null) and must return a Builder
     * limited to the options the current user is allowed to see.
     *
     * @param  Closure(mixed): Builder  $callback
     */
    public function optionsQuery(Closure $callback): static
    {
        $this->optionsQuery = $callback;

        return $this;
    }

    public function getIcon(): string|BackedEnum|null
    {
        return $this->icon;
    }

    public function getLabelAttribute(): string
    {
        return $this->labelAttribute;
    }

    public function getOptionsQuery(): ?Closure
    {
        return $this->optionsQuery;
    }

    /**
     * @return Builder<Model>
     */
    public function resolveOptionsQuery(mixed $user): Builder
    {
        if ($this->optionsQuery) {
            return ($this->optionsQuery)($user);
        }

        return $this->modelClass::query();
    }
}
