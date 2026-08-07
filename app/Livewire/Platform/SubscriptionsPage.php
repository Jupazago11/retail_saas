<?php

namespace App\Livewire\Platform;

use App\Actions\Subscriptions\ChangeCompanySubscription;
use App\Livewire\Concerns\InteractsWithToast;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;
use Livewire\Component;
use Livewire\WithPagination;

class SubscriptionsPage extends Component
{
    use InteractsWithToast, WithPagination;

    public string $search   = '';
    public string $filter   = 'all';
    public bool   $showModal = false;

    public ?int   $editingId  = null;
    public string $editPlanId = '';
    public string $editEndsAt = '';

    public bool   $showActivateModal   = false;
    public ?int   $activateCompanyId   = null;
    public string $activatePlanId      = '';
    public string $activateStartsAt    = '';
    public string $activateEndsAt      = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->is_platform_admin, 403);
    }

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedFilter(): void { $this->resetPage(); }

    public function startEdit(int $id): void
    {
        $sub = Subscription::with('plan')->findOrFail($id);
        $this->editingId  = $sub->id;
        $this->editPlanId = (string) ($sub->plan_id ?? '');
        $this->editEndsAt = $sub->ends_at?->format('Y-m-d') ?? '';
        $this->showModal  = true;
    }

    public function saveEdit(): void
    {
        abort_unless(auth()->user()?->is_platform_admin, 403);

        $this->validate([
            'editPlanId' => ['required', 'exists:plans,id'],
            'editEndsAt' => ['nullable', 'date'],
        ]);

        $sub = Subscription::findOrFail($this->editingId);
        $sub->update([
            'plan_id'  => (int) $this->editPlanId,
            'ends_at'  => $this->editEndsAt !== '' ? $this->editEndsAt : null,
        ]);

        $this->showModal = false;
        $this->toast('Suscripción actualizada.');
    }

    public function closeModal(): void
    {
        $this->showModal  = false;
        $this->editingId  = null;
        $this->editPlanId = '';
        $this->editEndsAt = '';
    }

    public function toggleAutoRenew(int $companyId): void
    {
        abort_unless(auth()->user()?->is_platform_admin, 403);

        $company = Company::findOrFail($companyId);
        $company->update(['auto_renew' => ! $company->auto_renew]);

        $this->toast($company->auto_renew
            ? 'Renovación automática activada.'
            : 'Renovación automática desactivada.');
    }

    public function startActivate(int $companyId): void
    {
        $this->activateCompanyId = $companyId;
        $this->activatePlanId    = '';
        $this->activateStartsAt  = now()->format('Y-m-d');
        $this->activateEndsAt    = '';
        $this->showActivateModal = true;
    }

    public function saveActivate(ChangeCompanySubscription $changeCompanySubscription): void
    {
        abort_unless(auth()->user()?->is_platform_admin, 403);

        $this->validate([
            'activatePlanId'   => ['required', 'exists:plans,id'],
            'activateStartsAt' => ['nullable', 'date'],
            'activateEndsAt'   => ['nullable', 'date'],
        ]);

        $company = Company::findOrFail($this->activateCompanyId);

        try {
            $changeCompanySubscription->handle($company, [
                'plan_id'   => (int) $this->activatePlanId,
                'status'    => 'active',
                'starts_at' => $this->activateStartsAt !== '' ? $this->activateStartsAt : now(),
                'ends_at'   => $this->activateEndsAt !== '' ? $this->activateEndsAt : null,
            ], auth()->user());
        } catch (InvalidArgumentException $exception) {
            $this->addError('activatePlanId', $exception->getMessage());

            return;
        }

        $this->closeActivateModal();
        $this->toast('Nuevo plan activado.');
    }

    public function closeActivateModal(): void
    {
        $this->showActivateModal = false;
        $this->activateCompanyId = null;
        $this->activatePlanId    = '';
        $this->activateStartsAt  = '';
        $this->activateEndsAt    = '';
    }

    public function plans(): Collection
    {
        return Plan::orderBy('id')->get();
    }

    public function render(): View
    {
        $subscriptions = Subscription::query()
            ->with(['company.owner', 'plan'])
            ->whereNull('bundle_id')
            ->when($this->filter === 'vencida', fn ($q) => $q->dueForExpiration(now()))
            ->when(! in_array($this->filter, ['all', 'vencida'], true), fn ($q) => $q->where('status', $this->filter))
            ->when($this->search !== '', function ($q) {
                $s = '%'.trim($this->search).'%';
                $q->whereHas('company', fn ($cq) => $cq
                    ->whereLike('display_name', $s)
                    ->orWhereLike('legal_name', $s));
            })
            ->orderByDesc('id')
            ->paginate(20);

        $latestSubscriptionIds = Subscription::query()
            ->whereNull('bundle_id')
            ->selectRaw('MAX(id) as id')
            ->groupBy('company_id')
            ->pluck('id')
            ->all();

        return view('livewire.platform.subscriptions-page', [
            'subscriptions'          => $subscriptions,
            'plans'                  => $this->plans(),
            'latestSubscriptionIds'  => $latestSubscriptionIds,
        ])->layout('layouts.platform');
    }
}
