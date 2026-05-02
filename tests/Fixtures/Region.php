<?php

declare(strict_types=1);

namespace Leek\FilamentSubtenantScope\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class Region extends Model
{
    protected $guarded = [];

    public $timestamps = false;

    public static function ensureTable(): void
    {
        if (Schema::hasTable('regions')) {
            return;
        }

        Schema::create('regions', function ($table): void {
            $table->id();
            $table->string('name');
            $table->boolean('active')->default(true);
        });
    }

    public static function seedTwo(): void
    {
        static::ensureTable();

        static::query()->insert([
            ['name' => 'North', 'active' => true],
            ['name' => 'South', 'active' => true],
        ]);
    }
}
