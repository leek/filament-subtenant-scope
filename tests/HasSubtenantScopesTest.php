<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Leek\FilamentSubtenantScope\SubtenantScope;
use Leek\FilamentSubtenantScope\SubtenantScopeManager;
use Leek\FilamentSubtenantScope\Tests\Fixtures\Region;
use Leek\FilamentSubtenantScope\Tests\Fixtures\Site;
use Leek\FilamentSubtenantScope\Tests\Fixtures\SiteResource;

beforeEach(function (): void {
    Session::flush();
    Site::ensureTables();

    $manager = new SubtenantScopeManager;
    $manager->registerScopes([
        SubtenantScope::make('region', 'Region', Region::class, 'region_id')
            ->optionsQuery(fn () => Region::query()),
    ]);
    app()->instance(SubtenantScopeManager::class, $manager);
    $this->manager = $manager;
});

it('qualifies the scope column with the model table so joins on the same column stay unambiguous', function (): void {
    DB::table('buildings')->insert([['id' => 1, 'region_id' => 1], ['id' => 2, 'region_id' => 2]]);
    DB::table('sites')->insert([
        ['id' => 1, 'region_id' => 1, 'building_id' => 1],
        ['id' => 2, 'region_id' => 2, 'building_id' => 1],
        ['id' => 3, 'region_id' => 1, 'building_id' => 2],
    ]);

    $this->manager->setSelectedId('region', 1);

    $query = Site::query()->join('buildings', 'buildings.id', '=', 'sites.building_id');
    SiteResource::applySubTenantScopes($query);

    expect($query->toSql())->toContain('"sites"."region_id" = ?')
        ->and($query->pluck('sites.id')->all())->toBe([1, 3]);
});

it('applies nothing when no id is selected', function (): void {
    $query = Site::query();
    SiteResource::applySubTenantScopes($query);

    expect($query->toSql())->not->toContain('region_id');
});
