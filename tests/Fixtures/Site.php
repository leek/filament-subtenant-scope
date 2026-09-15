<?php

declare(strict_types=1);

namespace Leek\FilamentSubtenantScope\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/** A scoped model whose region_id also exists on a joinable table (`buildings`). */
class Site extends Model
{
    protected $guarded = [];

    public $timestamps = false;

    public static function ensureTables(): void
    {
        if (! Schema::hasTable('buildings')) {
            Schema::create('buildings', function ($table): void {
                $table->id();
                $table->unsignedBigInteger('region_id')->nullable();
            });
        }

        if (! Schema::hasTable('sites')) {
            Schema::create('sites', function ($table): void {
                $table->id();
                $table->unsignedBigInteger('region_id')->nullable();
                $table->unsignedBigInteger('building_id')->nullable();
            });
        }
    }
}
