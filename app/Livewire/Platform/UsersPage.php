<?php

namespace App\Livewire\Platform;

use App\Enums\RecordStatus;
use App\Livewire\Concerns\InteractsWithToast;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class UsersPage extends Component
{
    use InteractsWithToast, WithPagination;

    public string $search = '';

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

    public function render(): View
    {
        $users = User::query()
            ->withCount('companies')
            ->when($this->search !== '', function ($q) {
                $s = '%'.trim($this->search).'%';
                $q->where(fn ($inner) => $inner
                    ->whereLike('name', $s)
                    ->orWhereLike('email', $s)
                    ->orWhereLike('username', $s));
            })
            ->orderByDesc('created_at')
            ->paginate(25);

        return view('livewire.platform.users-page', compact('users'))
            ->layout('layouts.platform');
    }
}
