# Filament Subtenant Scope

Second-level tenancy for [Filament](https://filamentphp.com/) panels. Adds a topnav dropdown that scopes every Eloquent query in the panel to a sub-tenant — service area, region, location, branch, department — without touching individual resources.

Filament's built-in tenancy gives you one tenant. This plugin adds *another* level on top: pick a sub-scope, and resources, widgets, navigation badges, and global search all auto-filter.

<img width="2052" height="726" alt="screenshot" src="https://github.com/user-attachments/assets/f38cdd23-2c9d-4749-b4f8-b53ebc01534a" />

## Why

You already have multi-tenancy (e.g. `Company`). Inside each company you also need a soft filter — "show me only the North service area" — that:

- persists across navigation
- survives logout/login
- is shareable via URL
- applies to every model query without per-resource code

This plugin does that with one trait + one `->scopes([...])` array.

## Requirements

- PHP 8.2+
- Filament v4.x or v5.x
- Livewire v3 or v4

## Installation

```bash
composer require leek/filament-subtenant-scope
```

### Styles

Tell your panel theme to compile the plugin's blade utility classes by adding a `@source` directive to the panel theme configured with `->viteTheme(...)`:

```css
@import '../../../../vendor/filament/filament/resources/css/theme.css';

@source '../../../../vendor/leek/filament-subtenant-scope/resources/views/**/*.blade.php';
```

Then rebuild your app assets:

```bash
npm run build
```

Without this, responsive utilities like `hidden sm:inline` used inside the dropdown won't be compiled into your panel CSS and the dropdown label may collapse on wide screens.

### Register the plugin

Register the plugin on your panel and define one or more scopes:

```php
use Filament\Panel;
use Leek\FilamentSubtenantScope\SubtenantScope;
use Leek\FilamentSubtenantScope\SubtenantScopingPlugin;
use App\Models\ServiceArea;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->plugin(
            SubtenantScopingPlugin::make()
                ->scopes([
                    SubtenantScope::make('service_area', 'Service Area', ServiceArea::class, 'service_area_id')
                        ->icon('heroicon-o-map-pin')
                        ->labelAttribute('name')
                        ->optionsQuery(fn ($user) => ServiceArea::query()
                            ->where('company_id', $user->company_id)
                            ->where('is_active', true)
                            ->orderBy('name')),
                ]),
        );
}
```

That's the whole topnav setup. The dropdown renders next to the global search.

### Manage link

Add a link at the bottom of the dropdown pointing to wherever the options are managed. The closure receives the authenticated user; return `null` to hide the link (e.g. when the user lacks permission). The label defaults to "Manage {plural label}".

```php
SubtenantScope::make('service_area', 'Service Area', ServiceArea::class, 'service_area_id')
    ->manageUrl(fn ($user) => $user?->can('viewAny', ServiceArea::class)
        ? ServiceAreaResource::getUrl('index')
        : null),
```

## Opt resources into the scope

Add the `HasSubtenantScopes` trait and map each scope key to the FK column on the resource's model:

```php
use Filament\Resources\Resource;
use Leek\FilamentSubtenantScope\Concerns\HasSubtenantScopes;

class AppointmentResource extends Resource
{
    use HasSubtenantScopes;

    /** @var array<string, string|null> */
    protected static array $subTenantScopes = [
        'service_area' => 'service_area_id',
    ];
}
```

The plugin walks every resource in the panel during `boot()` and registers an Eloquent global scope on the model. **Once any resource opts in, all queries on that model auto-filter** — list pages, relation managers, navigation badges, widgets, global search.

### Custom join logic

If the FK isn't on the model directly, pass `null` and define a static method named `scopeSubTenant{Key}`:

```php
class ClientProfileResource extends Resource
{
    use HasSubtenantScopes;

    protected static array $subTenantScopes = ['service_area' => null];

    public static function scopeSubTenantServiceArea(Builder $query, int $id): void
    {
        $query->where(function ($q) use ($id) {
            $q->where('primary_service_area_id', $id)
                ->orWhereHas('serviceAreas', fn ($q) => $q->where('service_areas.id', $id));
        });
    }
}
```

## Behavior

- **URL bookmarks**: append `?scope_<key>=<id>` to any panel URL — the value is read, persisted, then stripped from the URL on the next render so it's sticky.
- **Single option**: when the user has access to exactly one option, the scope renders as a static label (no dropdown) and applies no filter — there's nothing to filter between.
- **Multiple options**: dropdown with "All …" plus each option.
- **No options**: nothing renders.

## Persistence

By default, selections persist for the session. To make them sticky across sessions/devices, register get/set callbacks. The classic pattern is a JSON column on `users`:

```php
SubtenantScopingPlugin::make()
    ->scopes([/* ... */])
    ->persistUsing(
        get: fn ($user, string $key) => $user->settings['sub_tenant_scopes'][$key] ?? null,
        set: function ($user, string $key, ?int $id): void {
            $settings = $user->settings ?? [];
            $settings['sub_tenant_scopes'][$key] = $id;
            $user->settings = $settings;
            $user->saveQuietly();
        },
    );
```

Resolution order: URL param → session → user storage. First non-null wins.

## Customizing the dropdown

### Render hook

Override where the dropdown renders:

```php
use Filament\View\PanelsRenderHook;

SubtenantScopingPlugin::make()
    ->scopes([/* ... */])
    ->renderHook(PanelsRenderHook::TOPBAR_END);
```

### Render the selector yourself

Disable the built-in render hook and embed the Livewire component anywhere:

```php
SubtenantScopingPlugin::make()
    ->scopes([/* ... */])
    ->withoutDropdown();
```

```blade
@livewire(\Leek\FilamentSubtenantScope\Livewire\SubtenantScopeSelector::class)
```

### Override the view

Publish and edit the dropdown blade:

```bash
php artisan vendor:publish --tag=filament-subtenant-scope-views
```

## Multiple scopes

Stack as many as you need. Each gets its own dropdown and storage key:

```php
SubtenantScopingPlugin::make()
    ->scopes([
        SubtenantScope::make('region', 'Region', Region::class, 'region_id'),
        SubtenantScope::make('location', 'Location', Location::class, 'location_id'),
    ]);
```

Resources can opt into one or both:

```php
protected static array $subTenantScopes = [
    'region' => 'region_id',
    'location' => 'location_id',
];
```

### Nested scopes

When one scope lives inside another (locations inside a region), declare the
parent with `dependsOn()`, naming the parent scope's key and the child model's
column that points at it:

```php
SubtenantScope::make('location', 'Location', Location::class, 'location_id')
    ->dependsOn('region', 'region_id'),
```

- While a region is selected, the location dropdown offers only that region's
  locations, and its "All" option reads "All Locations in North".
- Picking a different region keeps the selected location only if it is inside
  the new region; going back to "All Regions" clears it.
- A stored location that falls outside the selected region (stale session, URL
  or user setting) is ignored, so a mismatched pair can never filter to nothing.
- A nested scope keeps its dropdown even with a single option, since picking the
  region's only location is still narrower than the region alone.

## Listening for changes

The Livewire component dispatches `sub-scope-changed` after every selection (it also triggers a full page reload to refresh server-rendered scoped data):

```js
Livewire.on('sub-scope-changed', ({ scopeKey, value }) => {
    // ...
});
```

## Testing

```bash
composer test
```

## How it works

1. **Plugin** registers a render hook that pulls the dropdown into the topbar, scopes the manager request-singleton, and walks panel resources during `boot()` to attach Eloquent global scopes.
2. **Manager** resolves the active selection per scope (URL → session → user storage), caches per request.
3. **Trait** (`HasSubtenantScopes`) adds a panel-aware Eloquent global scope to the model. The scope is no-op outside the panel the plugin is registered on.
4. **Livewire selector** renders one dropdown per registered scope, writes the selection through the manager, and reloads the page so server-rendered data picks up the new filter.

## More Filament plugins by Leek

**Premium**

- [**Filament UI Plus**](https://filamentphp.com/plugins/leek-ui-plus) — Enhanced UI components: dual sub-navigation, animated sidebar, horizontal-scroll tables, loading bar, and more.
- [**Filament Workflow Engine**](https://filamentphp.com/plugins/leek-workflow-engine) — Automated workflows with a visual builder, triggers/actions, async execution, and audit logging.
- [**Filament Decision Tables**](https://filamentphp.com/plugins/leek-decision-tables) — Business rules engine with spreadsheet-style decision tables.

**Free & open source**

- [**Filament Right Click**](https://github.com/leek/filament-right-click) — Right-click context menus for table rows.
- [**Filament Header Filters**](https://github.com/leek/filament-header-filters) — Inline filters attached to table column headers.
- [**Filament DiceBear**](https://github.com/leek/filament-dicebear) — DiceBear avatar provider with 31 styles.

## License

MIT. See [LICENSE.md](LICENSE.md).
