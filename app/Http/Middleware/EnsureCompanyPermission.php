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

    public function handle(Request $request, Closure $next, string $permissionCode): Response
    {
        $user = $request->user();

        if (! $user || ! $this->permissionResolver->has($user, $permissionCode)) {
            abort(403, 'No tienes permiso para acceder a este modulo.');
        }

        return $next($request);
    }
}
