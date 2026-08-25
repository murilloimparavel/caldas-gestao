<?php

namespace Database\Seeders;

use App\Support\OwnerPermissionCatalog;
use Illuminate\Database\Seeder;

final class PermissionCatalogSeeder extends Seeder
{
    /**
     * Seed the stable permission catalog without creating or changing schema.
     */
    public function run(): void
    {
        app(OwnerPermissionCatalog::class)->ensureCatalog();
    }
}
