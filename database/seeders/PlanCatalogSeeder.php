<?php

namespace Database\Seeders;

use App\Services\Plans\PlanCatalogBootstrapper;
use Illuminate\Database\Seeder;

class PlanCatalogSeeder extends Seeder
{
    public function run(): void
    {
        app(PlanCatalogBootstrapper::class)->ensureDefaults();
    }
}
