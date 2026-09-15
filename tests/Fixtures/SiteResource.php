<?php

declare(strict_types=1);

namespace Leek\FilamentSubtenantScope\Tests\Fixtures;

use Leek\FilamentSubtenantScope\Concerns\HasSubtenantScopes;

class SiteResource
{
    use HasSubtenantScopes;

    /** @var array<string, string|null> */
    protected static array $subTenantScopes = ['region' => 'region_id'];

    public static function getModel(): string
    {
        return Site::class;
    }
}
