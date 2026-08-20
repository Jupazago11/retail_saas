<?php

namespace App\Livewire\Platform;

use App\Livewire\Concerns\HasResponsivePageSize;
use App\Livewire\Concerns\InteractsWithToast;
use App\Models\Company;
use App\Models\Subscription;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Component;
use Livewire\WithPagination;

class CompaniesPage extends Component
{
    use HasResponsivePageSize, InteractsWithToast, WithPagination;

    public int $perPage = 20;

    public string $filter = 'pending';
    public string $search = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->is_platform_admin, 403);
    }

    public function updatedSearch(): void { $this->resetPage(); }

    public function setFilter(string $filter): void
    {
        if (! in_array($filter, ['all', 'pending', 'active'], true)) {
            return;
        }

        $this->filter = $filter;
        $this->resetPage();
    }

    public function activate(int $companyId): void
    {
        abort_unless(auth()->user()?->is_platform_admin, 403);

        $company = Company::findOrFail($companyId);

        $subscription = $company->subscriptions()
            ->whereNull('bundle_id')
            ->latest('id')
            ->first();

        if (! $subscription || $subscription->status !== 'pending') {
            $this->toast('Esta empresa no tiene una suscripcion pendiente.', 'warning');
            return;
        }

        $subscription->update([
            'status'    => 'active',
            'starts_at' => now(),
        ]);

        $this->toast("Empresa \"{$company->display_name}\" activada correctamente.");
    }

    public function suspend(int $companyId): void
    {
        abort_unless(auth()->user()?->is_platform_admin, 403);

        $company = Company::findOrFail($companyId);

        $subscription = $company->subscriptions()
            ->whereNull('bundle_id')
            ->latest('id')
            ->first();

        if (! $subscription || $subscription->status === 'pending') {
            return;
        }

        $subscription->update(['status' => 'pending']);

        $this->toast("Empresa \"{$company->display_name}\" suspendida.", 'warning');
    }

    public function companies(): LengthAwarePaginator
    {
        return Company::query()
            ->with(['subscriptions' => fn ($q) => $q->whereNull('bundle_id')->latest('id')->limit(1), 'owner'])
            ->when($this->search !== '', function ($q) {
                $s = '%'.trim($this->search).'%';
                $q->where(fn ($inner) => $inner
                    ->whereLike('display_name', $s)
                    ->orWhereLike('legal_name', $s)
                    ->orWhereLike('tax_id', $s));
            })
            ->when($this->filter === 'pending', fn ($q) => $q->whereHas('subscriptions', fn ($sq) =>
                $sq->whereNull('bundle_id')->where('status', 'pending')
            ))
            ->when($this->filter === 'active', fn ($q) => $q->whereHas('subscriptions', fn ($sq) =>
                $sq->whereNull('bundle_id')->where('status', 'active')
            ))
            ->orderByDesc('created_at')
            ->paginate($this->perPage);
    }

    public function pendingCount(): int
    {
        return Subscription::query()
            ->whereNull('bundle_id')
            ->where('status', 'pending')
            ->count();
    }

    public function render(): View
    {
        return view('livewire.platform.companies-page', [
            'companies'    => $this->companies(),
            'pendingCount' => $this->pendingCount(),
        ])->layout('layouts.platform');
    }
}
