<?php

namespace App\Http\Middleware;

use App\Services\Tenancy\CurrentCompany;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanyContext
{
    public function __construct(
        protected CurrentCompany $currentCompany,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            return $next($request);
        }

        $user = $request->user();

        if ($user->is_platform_admin) {
            if ($this->currentCompany->company()) {
                return $next($request);
            }
            return redirect()->route('platform.companies');
        }

        $this->currentCompany->clearIfInvalidForUser($user);

        if ($this->currentCompany->company()) {
            return $next($request);
        }

        $companies = $user->companies()
            ->orderBy('display_name')
            ->limit(2)
            ->get();

        if ($companies->count() === 1) {
            $this->currentCompany->setForUser($user, $companies->first());

            return $next($request);
        }

        return redirect()->route('companies.select');
    }
}
