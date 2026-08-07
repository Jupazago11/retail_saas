<?php

namespace App\Actions\Loyalty;

use App\Models\Company;
use App\Models\LoyaltyAccount;
use App\Services\Audit\AuditLogger;
use App\Services\Loyalty\LoyaltyLedger;
use App\Services\Settings\CompanySettings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ExpireLoyaltyPoints
{
    public function __construct(
        protected LoyaltyLedger $loyaltyLedger,
        protected CompanySettings $companySettings,
        protected AuditLogger $auditLogger,
    ) {
    }

    public function handle(Company $company, mixed $asOf = null): Collection
    {
        $expirationDays = (int) $this->companySettings->get($company, 'loyalty', 'points_expiration_days');

        if ($expirationDays <= 0) {
            return collect();
        }

        $asOf = $asOf ? Carbon::parse($asOf) : now();

        return LoyaltyAccount::query()
            ->where('company_id', $company->id)
            ->where('status', 'active')
            ->where('points_balance', '>', 0)
            ->get()
            ->map(function (LoyaltyAccount $account) use ($company, $asOf) {
                $before = $account->fresh();
                $movement = $this->loyaltyLedger->expireAvailablePoints($company, $account, $asOf);

                if (! $movement) {
                    return null;
                }

                $after = $account->fresh();
                $this->auditLogger->logUpdated($company, 'loyalty.points_expired', $before, $after);

                return $movement;
            })
            ->filter()
            ->values();
    }
}
