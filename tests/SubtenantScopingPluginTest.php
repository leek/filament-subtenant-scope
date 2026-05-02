<?php

use Filament\View\PanelsRenderHook;
use Leek\FilamentSubtenantScope\SubtenantScope;
use Leek\FilamentSubtenantScope\SubtenantScopingPlugin;
use Leek\FilamentSubtenantScope\Tests\Fixtures\Region;

it('exposes a stable plugin id', function (): void {
    expect(SubtenantScopingPlugin::make()->getId())->toBe(SubtenantScopingPlugin::PLUGIN_ID);
});

it('defaults render hook to topbar global search', function (): void {
    expect(SubtenantScopingPlugin::make()->getRenderHook())
        ->toBe(PanelsRenderHook::GLOBAL_SEARCH_BEFORE);
});

it('allows overriding the render hook', function (): void {
    $plugin = SubtenantScopingPlugin::make()->renderHook(PanelsRenderHook::TOPBAR_END);

    expect($plugin->getRenderHook())->toBe(PanelsRenderHook::TOPBAR_END);
});

it('stores scopes and exposes them by key', function (): void {
    $scope = SubtenantScope::make('region', 'Region', Region::class, 'region_id');

    $plugin = SubtenantScopingPlugin::make()->scopes([$scope]);

    expect($plugin->getScopes())->toBe([$scope])
        ->and($plugin->getScope('region'))->toBe($scope)
        ->and($plugin->getScope('missing'))->toBeNull();
});
