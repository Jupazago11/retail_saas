<?php

namespace App\Services\Plans;

use App\Enums\SaleStatus;
use App\Models\Company;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class CompanyOperationalLimitGuard
{
    public function __construct(
        protected CompanyPlanResolver $companyPlanResolver,
    ) {
    }

    public function ensureCanCreateProduct(Company $company): void
    {
        $limit = $this->companyPlanResolver->limit($company, 'max_products');

        if (! $limit || $limit < 1) {
            return;
        }

        $currentCount = Product::query()
            ->where('company_id', $company->id)
            ->whereNull('deleted_at')
            ->count();

        if ($currentCount >= $limit) {
            throw new InvalidArgumentException('El plan actual ya alcanzo el limite de productos activos permitidos.');
        }
    }

    public function ensureCanCreateConfirmedSale(Company $company, mixed $soldAt = null): void
    {
        $limit = $this->companyPlanResolver->limit($company, 'max_monthly_sales');

        if (! $limit || $limit < 1) {
            return;
        }

        $moment = $this->normalizeMoment($soldAt);

        $currentCount = Sale::query()
            ->where('company_id', $company->id)
            ->where('status', SaleStatus::Confirmed->value)
            ->whereBetween('sold_at', [
                $moment->copy()->startOfMonth(),
                $moment->copy()->endOfMonth(),
            ])
            ->count();

        if ($currentCount >= $limit) {
            throw new InvalidArgumentException('El plan actual ya alcanzo el limite de ventas confirmadas del mes.');
        }
    }

    public function ensureCanIssueElectronicDocument(Company $company, mixed $issuedAt = null): void
    {
        $limit = $this->companyPlanResolver->limit($company, 'max_electronic_documents');

        if (! $limit || $limit < 1) {
            return;
        }

        $issuedAt = $this->normalizeMoment($issuedAt);

        throw new InvalidArgumentException(
            'El limite de documentos electronicos esta catalogado, pero su enforcement operativo sigue pendiente hasta implementar la emision electronica real.'
        );
    }

    protected function normalizeMoment(mixed $value): Carbon
    {
        if ($value instanceof Carbon) {
            return $value->copy();
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value);
        }

        if (is_string($value) && trim($value) !== '') {
            return Carbon::parse($value);
        }

        return now();
    }
}
