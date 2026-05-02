<?php

declare(strict_types=1);

namespace Leek\FilamentSubtenantScope;

use Closure;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\View\View;
use Leek\FilamentSubtenantScope\Concerns\HasSubtenantScopes;

final class SubtenantScopingPlugin implements Plugin
{
    public const PLUGIN_ID = 'subtenant-scoping';

    /** @var array<SubtenantScope> */
    private array $scopes = [];

    private string $renderHook = PanelsRenderHook::GLOBAL_SEARCH_BEFORE;

    private bool $renderDropdown = true;

    private ?Closure $persistGetter = null;

    private ?Closure $persistSetter = null;

    public static function make(): static
    {
        return new self;
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(static::PLUGIN_ID);

        return $plugin;
    }

    public function getId(): string
    {
        return self::PLUGIN_ID;
    }

    /**
     * @param  array<SubtenantScope>  $scopes
     */
    public function scopes(array $scopes): static
    {
        $this->scopes = $scopes;

        return $this;
    }

    /**
     * @return array<SubtenantScope>
     */
    public function getScopes(): array
    {
        return $this->scopes;
    }

    public function getScope(string $key): ?SubtenantScope
    {
        foreach ($this->scopes as $scope) {
            if ($scope->key === $key) {
                return $scope;
            }
        }

        return null;
    }

    /**
     * Render hook for the topnav dropdown. Defaults to GLOBAL_SEARCH_BEFORE
     * which places it next to the global search in the topbar.
     */
    public function renderHook(string $hook): static
    {
        $this->renderHook = $hook;

        return $this;
    }

    public function getRenderHook(): string
    {
        return $this->renderHook;
    }

    /**
     * Disable the topnav dropdown (e.g. if you want to render the selector
     * yourself somewhere custom).
     */
    public function withoutDropdown(): static
    {
        $this->renderDropdown = false;

        return $this;
    }

    /**
     * Configure user-level persistence so a selected scope sticks across
     * sessions/devices (in addition to the per-session storage).
     *
     * @param  Closure(Authenticatable, string): ?int  $get
     * @param  Closure(Authenticatable, string, ?int): void  $set
     */
    public function persistUsing(Closure $get, Closure $set): static
    {
        $this->persistGetter = $get;
        $this->persistSetter = $set;

        return $this;
    }

    public function register(Panel $panel): void
    {
        if (blank($this->scopes)) {
            return;
        }

        // Selected-value caches stay request-scoped; persistence lives in session/user storage.
        app()->scoped(SubtenantScopeManager::class);

        if ($this->renderDropdown) {
            $panel->renderHook(
                $this->renderHook,
                fn (): View => view('filament-subtenant-scope::selector'),
            );
        }
    }

    public function boot(Panel $panel): void
    {
        if (blank($this->scopes)) {
            return;
        }

        $manager = resolve(SubtenantScopeManager::class);
        $manager->registerScopes($this->scopes);

        if ($this->persistGetter || $this->persistSetter) {
            $manager->persistUsing($this->persistGetter, $this->persistSetter);
        }

        $panelId = $panel->getId();

        foreach ($panel->getResources() as $resource) {
            if (in_array(HasSubtenantScopes::class, class_uses_recursive($resource), true)) {
                $resource::registerSubTenantGlobalScopes($panelId);
            }
        }
    }
}
