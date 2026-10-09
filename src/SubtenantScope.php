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

    private Closure|string|null $manageUrl = null;

    private ?string $manageLabel = null;

    private ?string $parentKey = null;

    private ?string $parentColumn = null;

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

    /**
     * Link rendered at the bottom of the dropdown so users can jump to where the
     * options are managed. A closure receives the authenticated user (or null)
     * and may return null to hide the link, e.g. when the user lacks permission.
     *
     * @param  Closure(mixed): ?string|string|null  $url
     */
    public function manageUrl(Closure|string|null $url, ?string $label = null): static
    {
        $this->manageUrl = $url;
        $this->manageLabel = $label;

        return $this;
    }

    /**
     * Nest this scope under another one, e.g. locations under a service area.
     * While the parent has a selection, this scope only offers options whose
     * `$parentColumn` matches it, and changing the parent drops a selection
     * that is no longer inside it.
     */
    public function dependsOn(string $parentKey, string $parentColumn): static
    {
        $this->parentKey = $parentKey;
        $this->parentColumn = $parentColumn;

        return $this;
    }

    public function getParentKey(): ?string
    {
        return $this->parentKey;
    }

    public function getParentColumn(): ?string
    {
        return $this->parentColumn;
    }

    public function hasParent(): bool
    {
        return $this->parentKey !== null;
    }

    public function resolveManageUrl(mixed $user): ?string
    {
        $url = $this->manageUrl instanceof Closure ? ($this->manageUrl)($user) : $this->manageUrl;

        return filled($url) ? $url : null;
    }

    public function getManageLabel(): string
    {
        return $this->manageLabel ?? 'Manage '.str($this->label)->plural();
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
