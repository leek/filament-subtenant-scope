<?php

declare(strict_types=1);

namespace Leek\FilamentSubtenantScope\Concerns;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Leek\FilamentSubtenantScope\SubtenantScopeManager;
use Leek\FilamentSubtenantScope\SubtenantScopingPlugin;

/**
 * Trait for Filament resources to opt into sub-tenant scoping.
 *
 * The using class must define:
 *
 *     protected static array $subTenantScopes = ['service_area' => 'service_area_id'];
 *
 * Use null as column for custom method: scopeSubTenant{Key}(Builder $query, int $id)
 */
trait HasSubtenantScopes
{
    /**
     * @return array<string, string|null>
     */
    public static function getSubTenantScopes(): array
    {
        return static::$subTenantScopes;
    }

    /**
     * Apply all active sub-tenant scopes to a query.
     */
    public static function applySubTenantScopes(Builder $builder): Builder
    {
        $manager = resolve(SubtenantScopeManager::class);

        foreach (static::$subTenantScopes as $scopeKey => $column) {
            $selectedId = $manager->getSelectedId($scopeKey);

            if ($selectedId === null) {
                continue;
            }

            if ($column !== null) {
                // Qualify with the model's table: an unqualified column is
                // ambiguous the moment the query joins another table that
                // carries the same foreign key (e.g. appointments ⋈ locations).
                $builder->where($builder->qualifyColumn($column), $selectedId);

                continue;
            }

            $method = 'scopeSubTenant'.Str::studly($scopeKey);
            if (method_exists(static::class, $method)) {
                static::$method($builder, $selectedId);
            }
        }

        return $builder;
    }

    /**
     * Register Eloquent global scope on this resource's model.
     * Called by SubtenantScopingPlugin during boot. Scope is panel-aware:
     * it only applies inside the panel the plugin was registered on.
     */
    public static function registerSubTenantGlobalScopes(string $panelId): void
    {
        $model = static::getModel();

        $model::addGlobalScope('sub-tenant-scoping', function (Builder $builder) use ($panelId): void {
            $panel = Filament::getCurrentPanel();
            if (! $panel || $panel->getId() !== $panelId) {
                return;
            }

            // Skip if plugin hasn't been registered on this panel (defensive).
            if (! $panel->hasPlugin(SubtenantScopingPlugin::PLUGIN_ID)) {
                return;
            }

            static::applySubTenantScopes($builder);
        });
    }
}
