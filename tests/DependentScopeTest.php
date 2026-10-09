<?php

use Illuminate\Support\Facades\Session;
use Leek\FilamentSubtenantScope\SubtenantScope;
use Leek\FilamentSubtenantScope\SubtenantScopeManager;
use Leek\FilamentSubtenantScope\Tests\Fixtures\Region;
use Leek\FilamentSubtenantScope\Tests\Fixtures\Site;
use Leek\FilamentSubtenantScope\Tests\Fixtures\TestUser;

beforeEach(function (): void {
    Session::flush();
    Region::seedTwo();
    Site::ensureTables();

    $this->north = Region::query()->where('name', 'North')->value('id');
    $this->south = Region::query()->where('name', 'South')->value('id');

    $this->northSite = Site::query()->create(['region_id' => $this->north])->id;
    $this->otherNorthSite = Site::query()->create(['region_id' => $this->north])->id;
    $this->southSite = Site::query()->create(['region_id' => $this->south])->id;

    $this->actingAs(new TestUser);
});

function nestedManager(): SubtenantScopeManager
{
    $manager = new SubtenantScopeManager;
    $manager->registerScopes([
        SubtenantScope::make('region', 'Region', Region::class, 'region_id'),
        SubtenantScope::make('site', 'Site', Site::class, 'site_id')->dependsOn('region', 'region_id'),
    ]);

    return $manager;
}

function siteOptionIds(SubtenantScopeManager $manager): array
{
    return $manager->getOptionsForScope($manager->getScope('site'), auth()->user())
        ->modelKeys();
}

it('offers every child option while the parent shows all', function (): void {
    expect(siteOptionIds(nestedManager()))
        ->toEqualCanonicalizing([$this->northSite, $this->otherNorthSite, $this->southSite]);
});

it('narrows child options to the selected parent', function (): void {
    $manager = nestedManager();
    $manager->setSelectedId('region', $this->north);

    expect(siteOptionIds($manager))->toEqualCanonicalizing([$this->northSite, $this->otherNorthSite]);
});

it('keeps a child selection that is still inside the new parent', function (): void {
    $manager = nestedManager();
    $manager->setSelectedId('site', $this->northSite);
    $manager->setSelectedId('region', $this->north);

    expect($manager->getSelectedId('site'))->toBe($this->northSite);
});

it('clears a child selection outside the new parent', function (): void {
    $manager = nestedManager();
    $manager->setSelectedId('region', $this->north);
    $manager->setSelectedId('site', $this->northSite);

    $manager->setSelectedId('region', $this->south);

    expect($manager->getSelectedId('site'))->toBeNull()
        ->and(Session::has('sub_scope.site'))->toBeFalse()
        ->and(siteOptionIds($manager))->toBe([$this->southSite]);
});

it('clears the child selection when the parent goes back to all', function (): void {
    $manager = nestedManager();
    $manager->setSelectedId('region', $this->north);
    $manager->setSelectedId('site', $this->northSite);

    $manager->setSelectedId('region', null);

    expect($manager->getSelectedId('site'))->toBeNull();
});

it('ignores a stored child selection that falls outside the parent', function (): void {
    Session::put('sub_scope.region', $this->south);
    Session::put('sub_scope.site', $this->northSite);

    expect(nestedManager()->getSelectedId('site'))->toBeNull();
});

it('honours a persisted child selection even when it is the parent\'s only option', function (): void {
    $manager = nestedManager();
    $manager->persistUsing(
        fn ($user, string $key) => ['region' => $this->south, 'site' => $this->southSite][$key],
        fn () => null,
    );

    expect($manager->getSelectedId('site'))->toBe($this->southSite);
});
