<?php

namespace App\Http\Middleware;

use App\Services\Authorization\CurrentCompanyPermissionResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanyPermission
{
    public function __construct(
        protected CurrentCompanyPermissionResolver $permissionResolver,
    ) {
    }

    public function handle(Request $request, Closure $next, string ...$permissionCodes): Response
    {
        $user = $request->user();

        if (! $user || ! $this->permissionResolver->hasAny($user, $permissionCodes)) {
            abort(403, 'No tienes permiso para acceder a este modulo.');
        }

        return $next($request);
    }
}
