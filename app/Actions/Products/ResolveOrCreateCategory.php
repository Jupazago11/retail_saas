<?php

namespace App\Actions\Products;

use App\Enums\RecordStatus;
use App\Models\Category;
use App\Models\Company;
use Illuminate\Support\Str;

class ResolveOrCreateCategory
{
    public function handle(Company $company, string $name): Category
    {
        $name = trim($name);

        $existing = Category::query()
            ->where('company_id', $company->id)
            ->whereRaw('upper(name) = ?', [mb_strtoupper($name)])
            ->whereNull('deleted_at')
            ->first();

        if ($existing) {
            return $existing;
        }

        return Category::create([
            'company_id' => $company->id,
            'name' => $name,
            'code' => $this->uniqueCode($name, $company->id),
            'status' => RecordStatus::Active->value,
        ]);
    }

    protected function uniqueCode(string $name, int $companyId): string
    {
        $base = Str::upper(Str::slug($name, '_'));

        if ($base === '') {
            $base = Str::upper(Str::random(6));
        }

        $base = Str::substr($base, 0, 40);
        $code = $base;
        $n = 2;

        while (Category::where('company_id', $companyId)->where('code', $code)->exists()) {
            $code = Str::substr($base, 0, 37).'_'.$n++;
        }

        return $code;
    }
}
