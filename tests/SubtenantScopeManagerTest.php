<?php

use Illuminate\Support\Facades\Session;
use Leek\FilamentSubtenantScope\SubtenantScope;
use Leek\FilamentSubtenantScope\SubtenantScopeManager;
use Leek\FilamentSubtenantScope\Tests\Fixtures\Region;
use Leek\FilamentSubtenantScope\Tests\Fixtures\TestUser;

beforeEach(function (): void {
    Session::flush();
});

function makeManager(): SubtenantScopeManager
{
    $manager = new SubtenantScopeManager;
    $manager->registerScopes([
        SubtenantScope::make('region', 'Region', Region::class, 'region_id')
            ->optionsQuery(fn () => Region::query()),
    ]);

    return $manager;
}

it('returns null when nothing is selected', function (): void {
    expect(makeManager()->getSelectedId('region'))->toBeNull();
});

it('persists selected id to session', function (): void {
    $manager = makeManager();
    $manager->setSelectedId('region', 42);

    expect(Session::get('sub_scope.region'))->toBe(42)
        ->and((new SubtenantScopeManager)->getScopes())->toBe([]);

    // A fresh manager should resolve the value from session.
    $fresh = makeManager();
    expect($fresh->getSelectedId('region'))->toBe(42);
});

it('clears session when selected id is null', function (): void {
    Session::put('sub_scope.region', 7);

    $manager = makeManager();
    $manager->setSelectedId('region', null);

    expect(Session::has('sub_scope.region'))->toBeFalse();
});

it('reads selected id from URL query parameter and caches it to session', function (): void {
    request()->query->set('scope_region', '99');

    $manager = makeManager();

    expect($manager->getSelectedId('region'))->toBe(99)
        ->and(Session::get('sub_scope.region'))->toBe(99);
});

it('invokes persistence setter when storing id', function (): void {
    $stored = [];
    $user = new TestUser;
    $this->actingAs($user);

    $manager = makeManager();
    $manager->persistUsing(
        fn () => null,
        function ($u, string $key, ?int $id) use (&$stored): void {
            $stored = [$u, $key, $id];
        },
    );

    $manager->setSelectedId('region', 5);

    expect($stored)->toBe([$user, 'region', 5]);
});

it('falls back to persistence getter when session is empty', function (): void {
    $user = new TestUser;
    $this->actingAs($user);

    $manager = new SubtenantScopeManager;
    $manager->registerScopes([
        SubtenantScope::make('region', 'Region', Region::class, 'region_id')
            // Two options ensures the "single option = no filter" guard is skipped.
            ->optionsQuery(fn () => Region::query()),
    ]);

    Region::seedTwo();

    $manager->persistUsing(
        fn ($u, string $key) => $key === 'region' ? 13 : null,
        fn () => null,
    );

    expect($manager->getSelectedId('region'))->toBe(13)
        ->and(Session::get('sub_scope.region'))->toBe(13);
});
