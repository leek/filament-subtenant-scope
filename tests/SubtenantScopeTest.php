<?php

use Leek\FilamentSubtenantScope\SubtenantScope;
use Leek\FilamentSubtenantScope\Tests\Fixtures\Region;

it('exposes constructor values', function (): void {
    $scope = SubtenantScope::make('region', 'Region', Region::class, 'region_id');

    expect($scope->key)->toBe('region')
        ->and($scope->label)->toBe('Region')
        ->and($scope->modelClass)->toBe(Region::class)
        ->and($scope->column)->toBe('region_id');
});

it('supports fluent configuration', function (): void {
    $scope = SubtenantScope::make('region', 'Region', Region::class, 'region_id')
        ->icon('heroicon-o-map')
        ->labelAttribute('display_name')
        ->optionsQuery(fn () => Region::query()->where('active', true));

    expect($scope->getIcon())->toBe('heroicon-o-map')
        ->and($scope->getLabelAttribute())->toBe('display_name')
        ->and($scope->getOptionsQuery())->toBeInstanceOf(Closure::class);
});

it('defaults label attribute to name', function (): void {
    $scope = SubtenantScope::make('region', 'Region', Region::class, 'region_id');

    expect($scope->getLabelAttribute())->toBe('name');
});

it('falls back to model query when no options query provided', function (): void {
    $scope = SubtenantScope::make('region', 'Region', Region::class, 'region_id');

    $builder = $scope->resolveOptionsQuery(null);

    expect($builder->getModel())->toBeInstanceOf(Region::class);
});

it('has no manage link by default', function (): void {
    $scope = SubtenantScope::make('region', 'Region', Region::class, 'region_id');

    expect($scope->resolveManageUrl(null))->toBeNull()
        ->and($scope->getManageLabel())->toBe('Manage Regions');
});

it('resolves a static manage url and custom label', function (): void {
    $scope = SubtenantScope::make('region', 'Region', Region::class, 'region_id')
        ->manageUrl('/regions', 'Edit regions');

    expect($scope->resolveManageUrl(null))->toBe('/regions')
        ->and($scope->getManageLabel())->toBe('Edit regions');
});

it('passes the user to a manage url closure and hides the link when it returns null', function (): void {
    $scope = SubtenantScope::make('region', 'Region', Region::class, 'region_id')
        ->manageUrl(fn (?string $user): ?string => $user === 'admin' ? '/regions' : null);

    expect($scope->resolveManageUrl('admin'))->toBe('/regions')
        ->and($scope->resolveManageUrl('staff'))->toBeNull();
});
