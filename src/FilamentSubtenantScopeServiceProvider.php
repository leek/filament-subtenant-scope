<?php

declare(strict_types=1);

namespace Leek\FilamentSubtenantScope;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilamentSubtenantScopeServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-subtenant-scope';

    public function configurePackage(Package $package): void
    {
        $package
            ->name(static::$name)
            ->hasViews('filament-subtenant-scope');
    }
}
