<?php

namespace App\Actions\Subscriptions;

use App\Models\Subscription;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ProcessDueSubscriptions
{
    public function __construct(
        protected EndCompanySubscription $endCompanySubscription,
        protected ChangeCompanySubscription $changeCompanySubscription,
    ) {
    }

    public function handle(?Carbon $moment = null): int
    {
        $moment = $moment ?? now();

        $subscriptions = Subscription::query()
            ->with(['company', 'plan'])
            ->dueForExpiration($moment)
            ->get();

        $processed = 0;

        foreach ($subscriptions as $subscription) {
            $company = $subscription->company;

            if (! $company) {
                continue;
            }

            DB::transaction(function () use ($company, $subscription, $moment) {
                $this->endCompanySubscription->handle($company, $subscription, $subscription->ends_at);

                if ($company->auto_renew) {
                    $this->changeCompanySubscription->handle($company, [
                        'plan_id' => $subscription->plan_id,
                        'status' => 'active',
                        'starts_at' => $moment,
                        'ends_at' => $this->nextRenewalEndsAt($subscription, $moment),
                    ]);
                }
            });

            $processed++;
        }

        return $processed;
    }

    protected function nextRenewalEndsAt(Subscription $subscription, Carbon $from): Carbon
    {
        return match ($subscription->plan?->billing_period) {
            'annual', 'yearly' => $from->copy()->addYear(),
            default => $from->copy()->addMonth(),
        };
    }
}
