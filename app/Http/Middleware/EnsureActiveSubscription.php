<?php

namespace App\Http\Middleware;

use App\Services\Plans\CompanyPlanResolver;
use App\Services\Tenancy\CurrentCompany;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveSubscription
{
    public function __construct(
        protected CurrentCompany $currentCompany,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        // El superadmin de plataforma nunca queda bloqueado.
        if ($user->is_platform_admin) {
            return $next($request);
        }

        $company = $this->currentCompany->company();

        if (! $company) {
            return $next($request);
        }

        $subscription = $company->subscriptions()
            ->whereNull('bundle_id')
            ->latest('id')
            ->first();

        if ($subscription && $subscription->status === 'pending') {
            return redirect()->route('subscription.pending');
        }

        return $next($request);
    }
}
