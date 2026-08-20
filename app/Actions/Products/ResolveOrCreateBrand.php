<?php

namespace App\Actions\Products;

use App\Enums\RecordStatus;
use App\Models\Brand;
use App\Models\Company;

class ResolveOrCreateBrand
{
    public function handle(Company $company, string $name): Brand
    {
        $name = trim($name);

        $existing = Brand::query()
            ->where('company_id', $company->id)
            ->whereRaw('upper(name) = ?', [mb_strtoupper($name)])
            ->whereNull('deleted_at')
            ->first();

        if ($existing) {
            return $existing;
        }

        return Brand::create([
            'company_id' => $company->id,
            'name' => $name,
            'status' => RecordStatus::Active->value,
        ]);
    }
}
