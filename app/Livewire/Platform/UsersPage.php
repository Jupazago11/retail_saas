<?php

namespace App\Livewire\Platform;

use App\Enums\RecordStatus;
use App\Livewire\Concerns\HasResponsivePageSize;
use App\Livewire\Concerns\InteractsWithToast;
use App\Models\AuditLog;
use App\Models\BusinessType;
use App\Models\User;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class UsersPage extends Component
{
    use HasResponsivePageSize, InteractsWithToast, WithPagination;

    public int $perPage = 25;

    public string $search = '';

    public string $statusFilter = 'all';

    public string $businessTypeFilter = 'all';

    public string $roleFilter = 'all';

    public bool   $showResetModal    = false;
    public ?int   $resettingUserId   = null;
    public string $resettingUserName = '';
    public string $newPassword       = '';
    public bool   $passwordSaved     = false;
    public bool   $showPasswordText  = false;

    public bool   $showEditModal = false;
    public ?int   $editingUserId = null;
    public string $editName      = '';
    public string $editUsername  = '';
    public string $editEmail     = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->is_platform_admin, 403);
    }

    public function updatedSearch(): void { $this->resetPage(); }

    public function setStatusFilter(string $filter): void
    {
        if (! in_array($filter, ['all', 'active', 'inactive'], true)) {
            return;
        }

        $this->statusFilter = $filter;
        $this->resetPage();
    }

    public function setBusinessTypeFilter(string $filter): void
    {
        if ($filter !== 'all' && ! BusinessType::where('code', $filter)->exists()) {
            return;
        }

        $this->businessTypeFilter = $filter;
        $this->resetPage();
    }

    public function setRoleFilter(string $filter): void
    {
        if (! in_array($filter, ['all', 'admin', 'user'], true)) {
            return;
        }

        $this->roleFilter = $filter;
        $this->resetPage();
    }

    public function startResetPassword(int $id): void
    {
        abort_unless(auth()->user()?->is_platform_admin, 403);

        $user = User::findOrFail($id);
        $this->resettingUserId   = $user->id;
        $this->resettingUserName = $user->name;
        $this->newPassword       = Str::password(12, symbols: false);
        $this->passwordSaved     = false;
        $this->showPasswordText  = false;
        $this->showResetModal    = true;
        $this->resetValidation();
    }

    public function regeneratePassword(): void
    {
        $this->newPassword = Str::password(12, symbols: false);
    }

    public function togglePasswordVisibility(): void
    {
        $this->showPasswordText = ! $this->showPasswordText;
    }

    public function confirmResetPassword(): void
    {
        abort_unless(auth()->user()?->is_platform_admin, 403);

        $this->validate([
            'newPassword' => ['required', 'string', 'min:8'],
        ]);

        $user = User::findOrFail($this->resettingUserId);
        $user->update(['password' => $this->newPassword]);

        $this->passwordSaved    = true;
        $this->showPasswordText = true;
    }

    public function closeResetModal(): void
    {
        $this->showResetModal    = false;
        $this->resettingUserId   = null;
        $this->resettingUserName = '';
        $this->newPassword       = '';
        $this->passwordSaved     = false;
        $this->showPasswordText  = false;
    }

    public function startEditUser(int $id): void
    {
        abort_unless(auth()->user()?->is_platform_admin, 403);

        $user = User::findOrFail($id);
        $this->editingUserId = $user->id;
        $this->editName      = $user->name;
        $this->editUsername  = $user->username;
        $this->editEmail     = $user->email ?? '';
        $this->showEditModal = true;
        $this->resetValidation();
    }

    public function saveEditUser(): void
    {
        abort_unless(auth()->user()?->is_platform_admin, 403);

        $validated = $this->validate([
            'editName'     => ['required', 'string', 'max:255'],
            'editUsername' => ['required', 'string', 'lowercase', 'max:255', Rule::unique(User::class, 'username')->ignore($this->editingUserId)],
            'editEmail'    => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class, 'email')->ignore($this->editingUserId)],
        ], [], [
            'editName' => 'nombre',
            'editUsername' => 'usuario',
            'editEmail' => 'correo',
        ]);

        $user = User::findOrFail($this->editingUserId);
        $user->update([
            'name'     => $validated['editName'],
            'username' => $validated['editUsername'],
            'email'    => $validated['editEmail'],
        ]);

        $this->closeEditModal();
        $this->toast('Usuario actualizado correctamente.');
    }

    public function closeEditModal(): void
    {
        $this->showEditModal = false;
        $this->editingUserId = null;
        $this->editName      = '';
        $this->editUsername  = '';
        $this->editEmail     = '';
        $this->resetValidation();
    }

    public function toggleStatus(int $id): void
    {
        abort_unless(auth()->user()?->is_platform_admin, 403);

        if ($id === auth()->id()) {
            $this->toast('No puedes desactivar tu propia cuenta.', type: 'error');

            return;
        }

        $user = User::findOrFail($id);

        $user->update([
            'status' => $user->status === RecordStatus::Active->value
                ? RecordStatus::Inactive->value
                : RecordStatus::Active->value,
        ]);

        $this->toast($user->status === RecordStatus::Active->value
            ? 'Usuario activado.'
            : 'Usuario desactivado.');
    }

    public function impersonate(int $id, CurrentCompany $currentCompany)
    {
        abort_unless(auth()->user()?->is_platform_admin, 403);

        $admin  = auth()->user();
        $target = User::findOrFail($id);

        if ($target->id === $admin->id) {
            $this->toast('No puedes entrar como tu propia cuenta.', type: 'error');

            return;
        }

        if ($target->is_platform_admin) {
            $this->toast('No puedes entrar como otro administrador de plataforma.', type: 'error');

            return;
        }

        if ($target->status !== RecordStatus::Active->value) {
            $this->toast('No puedes entrar como un usuario inactivo.', type: 'error');

            return;
        }

        AuditLog::query()->create([
            'company_id'      => null,
            'actor_user_id'   => $admin->id,
            'action'          => 'platform.user_impersonation_started',
            'auditable_type'  => User::class,
            'auditable_id'    => $target->id,
            'before_snapshot' => null,
            'after_snapshot'  => [
                'impersonated_user_id'  => $target->id,
                'impersonated_username' => $target->username,
            ],
            'ip_address' => request()->ip(),
        ]);

        // El id del admin queda guardado en la propia sesion (no en el
        // usuario autenticado) para poder volver despues con
        // StopImpersonationController; auth()->login() reemplaza el usuario
        // autenticado pero conserva el resto de datos de sesion.
        session()->put('impersonator_id', $admin->id);
        $currentCompany->clear();
        auth()->login($target);
        session()->regenerate();

        $this->flashToast('Ahora estas viendo la cuenta de '.$target->name.'.');

        return $this->redirectRoute('dashboard', navigate: true);
    }

    public function render(): View
    {
        $users = User::query()
            ->withCount('companies')
            ->with(['companies' => fn ($q) => $q->with('businessType')])
            ->when($this->search !== '', function ($q) {
                $s = '%'.trim($this->search).'%';
                $q->where(fn ($inner) => $inner
                    ->whereLike('name', $s)
                    ->orWhereLike('email', $s)
                    ->orWhereLike('username', $s));
            })
            ->when($this->statusFilter !== 'all', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->roleFilter !== 'all', fn ($q) => $q->where('is_platform_admin', $this->roleFilter === 'admin'))
            ->when($this->businessTypeFilter !== 'all', fn ($q) => $q->whereHas(
                'companies',
                fn ($cq) => $cq->whereHas('businessType', fn ($bq) => $bq->where('code', $this->businessTypeFilter))
            ))
            ->orderByDesc('created_at')
            ->paginate($this->perPage);

        return view('livewire.platform.users-page', [
            'users' => $users,
            'businessTypes' => BusinessType::where('status', RecordStatus::Active->value)->orderBy('id')->get(),
        ])->layout('layouts.platform');
    }
}
