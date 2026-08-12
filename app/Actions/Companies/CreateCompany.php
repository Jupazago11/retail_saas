<?php

namespace App\Actions\Companies;

use App\Enums\RecordStatus;
use App\Actions\Subscriptions\ProvisionCompanySubscription;
use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\Company;
use App\Models\RoleTemplate;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Authorization\AuthorizationCatalogBootstrapper;
use App\Services\Plans\OwnerCompanyLimitGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateCompany
{
    public function __construct(
        protected AuthorizationCatalogBootstrapper $authorizationCatalogBootstrapper,
        protected ProvisionCompanySubscription $provisionCompanySubscription,
        protected OwnerCompanyLimitGuard $ownerCompanyLimitGuard,
    ) {
    }

    public function handle(User $owner, array $attributes): Company
    {
        $this->authorizationCatalogBootstrapper->ensureDefaults();
        $this->ownerCompanyLimitGuard->ensureCanCreateCompany($owner);

        return DB::transaction(function () use ($owner, $attributes) {
            $company = Company::create([
                'owner_user_id' => $owner->id,
                'legal_name' => $attributes['legal_name'],
                'display_name' => $attributes['display_name'] ?? $attributes['legal_name'],
                'slug' => $this->buildUniqueSlug($attributes['display_name'] ?? $attributes['legal_name']),
                'tax_id' => $attributes['tax_id'] ?? null,
                'status' => RecordStatus::Active->value,
                'auto_renew' => true,
            ]);

            $ownerTemplateId = RoleTemplate::query()
                ->where('code', 'owner')
                ->value('id');

            $company->users()->attach($owner->id, [
                'company_role' => 'owner',
                'role_template_id' => $ownerTemplateId,
                'status' => RecordStatus::Active->value,
                'joined_at' => now(),
            ]);

            $branch = Branch::create([
                'company_id' => $company->id,
                'name' => 'Sucursal Principal',
                'code' => 'MAIN',
                'status' => RecordStatus::Active->value,
                'is_primary' => true,
            ]);

            Warehouse::create([
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'name' => 'Bodega Principal',
                'code' => 'MAIN',
                'status' => RecordStatus::Active->value,
                'is_primary' => true,
            ]);

            CashRegister::create([
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'name' => 'Caja Principal',
                'code' => 'MAIN',
                'status' => RecordStatus::Active->value,
                'is_primary' => true,
            ]);

            $this->provisionCompanySubscription->handle($company);

            return $company->fresh(['branches', 'warehouses', 'cashRegisters', 'users']);
        });
    }

    protected function buildUniqueSlug(string $value): string
    {
        $baseSlug = Str::slug($value);
        $slug = $baseSlug;
        $counter = 2;

        while (Company::where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
