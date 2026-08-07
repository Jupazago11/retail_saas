<?php

namespace App\Livewire\Platform;

use App\Models\Company;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Component;

class DashboardPage extends Component
{
    public function mount(): void
    {
        abort_unless(auth()->user()?->is_platform_admin, 403);
    }

    public function stats(): array
    {
        $total    = Company::count();
        $active   = Subscription::whereNull('bundle_id')->where('status', 'active')->count();
        $pending  = Subscription::whereNull('bundle_id')->where('status', 'pending')->count();
        $newMonth = Company::whereMonth('created_at', now()->month)
                           ->whereYear('created_at', now()->year)
                           ->count();

        return compact('total', 'active', 'pending', 'newMonth');
    }

    public function recentCompanies(): \Illuminate\Database\Eloquent\Collection
    {
        return Company::query()
            ->with(['owner', 'subscriptions' => fn ($q) => $q->whereNull('bundle_id')->with('plan')->latest('id')->limit(1)])
            ->orderByDesc('created_at')
            ->limit(8)
            ->get();
    }

    public function pendingCompanies(): \Illuminate\Database\Eloquent\Collection
    {
        return Company::query()
            ->with(['owner', 'subscriptions' => fn ($q) => $q->whereNull('bundle_id')->with('plan')->latest('id')->limit(1)])
            ->whereHas('subscriptions', fn ($q) => $q->whereNull('bundle_id')->where('status', 'pending'))
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();
    }

    public function render(): View
    {
        return view('livewire.platform.dashboard-page', [
            'stats'            => $this->stats(),
            'recentCompanies'  => $this->recentCompanies(),
            'pendingCompanies' => $this->pendingCompanies(),
        ])->layout('layouts.platform');
    }
}
