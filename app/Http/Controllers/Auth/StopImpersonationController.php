<?php

namespace App\Http\Controllers\Auth;

use App\Enums\RecordStatus;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StopImpersonationController
{
    public function __invoke(Request $request, CurrentCompany $currentCompany): RedirectResponse
    {
        $adminId = $request->session()->pull('impersonator_id');

        abort_unless($adminId, 403);

        $admin = User::find($adminId);

        abort_unless($admin && $admin->is_platform_admin && $admin->status === RecordStatus::Active->value, 403);

        $impersonatedUser = $request->user();

        AuditLog::query()->create([
            'company_id'      => null,
            'actor_user_id'   => $admin->id,
            'action'          => 'platform.user_impersonation_stopped',
            'auditable_type'  => User::class,
            'auditable_id'    => $impersonatedUser->id,
            'before_snapshot' => null,
            'after_snapshot'  => [
                'impersonated_user_id'  => $impersonatedUser->id,
                'impersonated_username' => $impersonatedUser->username,
            ],
            'ip_address' => $request->ip(),
        ]);

        auth()->login($admin);
        $currentCompany->clear();
        $request->session()->regenerate();

        return redirect()->route('platform.companies');
    }
}
