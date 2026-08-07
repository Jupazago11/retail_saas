<?php

namespace Database\Seeders;

use App\Services\Authorization\AuthorizationCatalogBootstrapper;
use Illuminate\Database\Seeder;

class AuthorizationCatalogSeeder extends Seeder
{
    public function run(): void
    {
        app(AuthorizationCatalogBootstrapper::class)->ensureDefaults();
    }
}
