<?php

namespace App\Livewire\Admin;

use App\Actions\Companies\AttachUserToCompany;
use App\Actions\Companies\CreateInternalUserForCompany;
use App\Enums\RecordStatus;
use App\Livewire\Concerns\InteractsWithToast;
use App\Models\Company;
use App\Models\CompanyRole;
use App\Models\Permission;
use App\Models\User;
use App\Services\Plans\CompanyPlanResolver;
use App\Services\Plans\CompanyUserLimitGuard;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Component;

class RolesPage extends Component
{
    use InteractsWithToast;

    public ?int $editingRoleId = null;
    public string $roleCode = '';
    public string $displayName = '';
    public string $roleStatus = RecordStatus::Active->value;
    public array $selectedPermissionCodes = [];
    public array $memberships = [];
    public string $newUserIdentifier = '';
    public string $newUserCompanyRoleId = '';
    public string $newUserMode = 'existing';
    public string $newInternalName = '';
    public string $newInternalUsername = '';
    public string $newInternalPassword = '';
    public string $newInternalCompanyRoleId = '';

    public function mount(): void
    {
        $this->ensurePermission('roles.manage');
        $this->loadMemberships();
    }

    public function editRole(int $roleId): void
    {
        $role = $this->companyRoles()->with('permissions')->findOrFail($roleId);

        $this->editingRoleId = $role->id;
        $this->roleCode = $role->code;
        $this->displayName = $role->display_name;
        $this->roleStatus = $role->status;
        $this->selectedPermissionCodes = $role->permissions
            ->pluck('code')
            ->values()
            ->all();
        $this->resetValidation();
    }

    public function resetRoleForm(): void
    {
        $this->editingRoleId = null;
        $this->roleCode = '';
        $this->displayName = '';
        $this->roleStatus = RecordStatus::Active->value;
        $this->selectedPermissionCodes = [];
        $this->resetValidation();
    }

    public function saveRole(): void
    {
        $this->ensurePermission('roles.manage');

        $company = $this->currentCompany();

        $validated = $this->validate([
            'roleCode' => [
                'required',
                'string',
                'max:80',
                Rule::unique('company_roles', 'code')
                    ->where(fn ($query) => $query->where('company_id', $company->id))
                    ->ignore($this->editingRoleId),
            ],
            'displayName' => ['required', 'string', 'max:255'],
            'roleStatus' => ['required', Rule::in([
                RecordStatus::Active->value,
                RecordStatus::Inactive->value,
            ])],
            'selectedPermissionCodes' => ['required', 'array', 'min:1'],
            'selectedPermissionCodes.*' => [
                'string',
                Rule::exists('permissions', 'code')->where(fn ($query) => $query->where('status', RecordStatus::Active->value)),
            ],
        ]);

        DB::transaction(function () use ($company, $validated) {
            $role = CompanyRole::query()->updateOrCreate(
                [
                    'id' => $this->editingRoleId,
                    'company_id' => $company->id,
                ],
                [
                    'company_id' => $company->id,
                    'code' => Str::slug($validated['roleCode'], '_'),
                    'display_name' => trim($validated['displayName']),
                    'status' => $validated['roleStatus'],
                ]
            );

            $permissionIds = Permission::query()
                ->whereIn('code', $validated['selectedPermissionCodes'])
                ->pluck('id')
                ->all();

            $role->permissions()->sync($permissionIds);
        });

        $this->resetRoleForm();
        $this->loadMemberships();
        $this->dispatch('toast', message: 'Rol guardado correctamente.', type: 'success');
    }

    public function saveMembership(int $userId): void
    {
        $this->ensurePermission('roles.manage');

        $membership = $this->membershipRecord($userId);

        if (! $membership || $membership->company_role === 'owner') {
            return;
        }

        $state = $this->memberships[$userId] ?? [];
        $companyRoleId = $this->normalizeNullableInt($state['company_role_id'] ?? null);

        if ($companyRoleId === null) {
            $this->addError("memberships.{$userId}.company_role_id", 'Debes elegir un rol.');

            return;
        }

        $exists = $this->companyRoles()->find($companyRoleId);

        if (! $exists) {
            $this->addError("memberships.{$userId}.company_role_id", 'El rol seleccionado no existe para esta empresa.');

            return;
        }

        DB::table('company_user')
            ->where('company_id', $this->currentCompany()->id)
            ->where('user_id', $userId)
            ->update([
                'company_role_id' => $companyRoleId,
                'company_role' => 'custom',
                'updated_at' => now(),
            ]);

        $this->loadMemberships();
        $this->dispatch('toast', message: 'Asignacion actualizada correctamente.', type: 'success');
    }

    public function toggleUserStatus(int $userId, CompanyUserLimitGuard $companyUserLimitGuard): void
    {
        $this->ensurePermission('roles.manage');

        $membership = $this->membershipRecord($userId);

        if (! $membership || $membership->company_role === 'owner') {
            return;
        }

        $company = $this->currentCompany();

        if ($membership->status === RecordStatus::Active->value) {
            DB::table('company_user')
                ->where('company_id', $company->id)
                ->where('user_id', $userId)
                ->update(['status' => RecordStatus::Inactive->value, 'updated_at' => now()]);

            $this->dispatch('toast', message: 'Usuario desactivado correctamente.', type: 'success');

            return;
        }

        try {
            $companyUserLimitGuard->ensureCanActivateUser($company);
        } catch (InvalidArgumentException $exception) {
            $this->dispatch('toast', message: $exception->getMessage(), type: 'error');

            return;
        }

        DB::table('company_user')
            ->where('company_id', $company->id)
            ->where('user_id', $userId)
            ->update(['status' => RecordStatus::Active->value, 'updated_at' => now()]);

        $this->dispatch('toast', message: 'Usuario activado correctamente.', type: 'success');
    }

    public function activeUsersCount(): int
    {
        return $this->currentCompany()->users()
            ->wherePivot('status', RecordStatus::Active->value)
            ->count();
    }

    public function maxUsers(): ?int
    {
        return app(CompanyPlanResolver::class)->limit($this->currentCompany(), 'max_users');
    }

    public function addUserToCompany(AttachUserToCompany $attachUserToCompany): void
    {
        $this->ensurePermission('roles.manage');

        $validated = $this->validate([
            'newUserIdentifier' => ['required', 'string', 'max:255'],
            'newUserCompanyRoleId' => ['required', 'integer'],
        ]);

        $companyRoleId = $this->normalizeNullableInt($validated['newUserCompanyRoleId']);

        $identifier = trim($validated['newUserIdentifier']);
        $user = User::query()
            ->where('email', $identifier)
            ->orWhere('username', $identifier)
            ->first();

        if (! $user) {
            $this->addError('newUserIdentifier', 'No existe un usuario con ese correo o username.');

            return;
        }

        try {
            $attachUserToCompany->handle($this->currentCompany(), $user, [
                'company_role_id' => $companyRoleId,
            ]);
        } catch (InvalidArgumentException $exception) {
            $this->addError('newUserIdentifier', $exception->getMessage());

            return;
        }

        $this->newUserIdentifier = '';
        $this->newUserCompanyRoleId = '';
        $this->resetValidation(['newUserIdentifier', 'newUserCompanyRoleId']);
        $this->loadMemberships();
        $this->dispatch('toast', message: 'Usuario vinculado correctamente a la empresa.', type: 'success');
    }

    public function remainingUserSlots(): ?int
    {
        $maxUsers = $this->maxUsers();

        if ($maxUsers === null) {
            return null;
        }

        return max(0, $maxUsers - $this->activeUsersCount());
    }

    public function setNewUserMode(string $mode): void
    {
        $this->newUserMode = in_array($mode, ['existing', 'new'], true) ? $mode : 'existing';
    }

    public function createInternalUser(CreateInternalUserForCompany $createInternalUserForCompany): void
    {
        $this->ensurePermission('roles.manage');

        $company = $this->currentCompany();

        $validated = $this->validate([
            'newInternalName' => ['required', 'string', 'max:255'],
            'newInternalUsername' => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('users', 'username')],
            'newInternalPassword' => ['required', 'string', 'min:8'],
            'newInternalCompanyRoleId' => ['required', 'integer'],
        ]);

        try {
            $createInternalUserForCompany->handle($company, [
                'name' => $validated['newInternalName'],
                'username' => $validated['newInternalUsername'],
                'password' => $validated['newInternalPassword'],
                'company_role_id' => $this->normalizeNullableInt($validated['newInternalCompanyRoleId']),
            ]);
        } catch (InvalidArgumentException $exception) {
            $this->addError('newInternalUsername', $exception->getMessage());

            return;
        }

        $this->newInternalName = '';
        $this->newInternalUsername = '';
        $this->newInternalPassword = '';
        $this->newInternalCompanyRoleId = '';
        $this->resetValidation(['newInternalName', 'newInternalUsername', 'newInternalPassword', 'newInternalCompanyRoleId']);
        $this->loadMemberships();
        $this->dispatch('toast', message: 'Usuario creado y vinculado correctamente.', type: 'success');
    }

    public function users(): Collection
    {
        return $this->currentCompany()
            ->users()
            ->orderBy('name')
            ->get();
    }

    public function companyRoles(): \Illuminate\Database\Eloquent\Builder
    {
        return CompanyRole::query()
            ->where('company_id', $this->currentCompany()->id);
    }

    /**
     * Permission `module_code` values that correspond to an optional plan module.
     * Only these are hidden when the company's plan doesn't include the module.
     * Anything else (masters, suppliers, payables, settings, users, roles,
     * subscriptions) is a core/administrative capability and always shown.
     * `sales` permissions are gated by the plan module `pos`, not a module
     * literally named `sales` (there isn't one in PlanCatalog).
     */
    protected const PLAN_GATED_MODULE_CODES = [
        'products' => 'products',
        'inventory' => 'inventory',
        'purchases' => 'purchases',
        'cash' => 'cash',
        'credit' => 'credit',
        'loyalty' => 'loyalty',
        'promotions' => 'promotions',
        'reports' => 'reports',
        'sales' => 'pos',
    ];

    public function toggleModulePermissions(string $moduleCode): void
    {
        $moduleCodes = collect($this->permissionGroups()[$moduleCode] ?? [])
            ->pluck('code')
            ->all();

        if ($moduleCodes === []) {
            return;
        }

        $allSelected = collect($moduleCodes)->every(fn (string $code) => in_array($code, $this->selectedPermissionCodes, true));

        $this->selectedPermissionCodes = $allSelected
            ? array_values(array_diff($this->selectedPermissionCodes, $moduleCodes))
            : array_values(array_unique([...$this->selectedPermissionCodes, ...$moduleCodes]));
    }

    public function permissionGroups(): array
    {
        $company = $this->currentCompany();
        $resolver = app(CompanyPlanResolver::class);

        return Permission::query()
            ->where('status', RecordStatus::Active->value)
            ->orderBy('module_code')
            ->orderBy('name')
            ->get()
            ->groupBy('module_code')
            ->filter(function (Collection $permissions, string $moduleCode) use ($company, $resolver) {
                $planModule = self::PLAN_GATED_MODULE_CODES[$moduleCode] ?? null;

                return $planModule === null || $resolver->hasModule($company, $planModule);
            })
            ->map(fn (Collection $permissions) => $permissions->values())
            ->all();
    }

    public function currentAssignmentLabel(User $user): string
    {
        $pivot = $user->pivot;

        if (($pivot->company_role ?? null) === 'owner') {
            return 'Propietario';
        }

        if ($pivot->company_role_id) {
            $role = $this->companyRoles()->get()->firstWhere('id', (int) $pivot->company_role_id);

            if ($role) {
                return 'Rol: ' . $role->display_name;
            }
        }

        return 'Sin asignacion';
    }

    public function render(): View
    {
        $companyRoles = $this->companyRoles()
            ->with('permissions')
            ->orderBy('display_name')
            ->get();

        return view('livewire.admin.roles-page', [
            'users' => $this->users(),
            'companyRoles' => $companyRoles,
            'permissionGroups' => $this->permissionGroups(),
            'activeUsersCount' => $this->activeUsersCount(),
            'maxUsers' => $this->maxUsers(),
            'remainingUserSlots' => $this->remainingUserSlots(),
        ])->layout('layouts.app', [
            'header' => view('components.page-title', [
                'title' => 'Roles y Usuarios',
                'description' => 'Crea roles por empresa, asigna permisos y actualiza el rol operativo de cada usuario.',
            ]),
        ]);
    }

    protected function loadMemberships(): void
    {
        $this->memberships = $this->users()
            ->mapWithKeys(fn (User $user) => [
                $user->id => [
                    'company_role_id' => $user->pivot?->company_role_id ? (string) $user->pivot->company_role_id : '',
                ],
            ])
            ->all();
    }

    protected function membershipRecord(int $userId): ?object
    {
        return DB::table('company_user')
            ->where('company_id', $this->currentCompany()->id)
            ->where('user_id', $userId)
            ->first();
    }

    protected function normalizeNullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    protected function currentCompany(): Company
    {
        return app(CurrentCompany::class)->company()
            ?? abort(404, 'No hay una empresa activa seleccionada.');
    }

    protected function ensurePermission(string $permissionCode): void
    {
        abort_unless(
            auth()->user()?->hasCurrentCompanyPermission($permissionCode),
            403,
            'No tienes permiso para acceder a este modulo.'
        );
    }
}
