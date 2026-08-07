<?php

namespace App\Livewire\Platform;

use App\Livewire\Concerns\InteractsWithToast;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
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
