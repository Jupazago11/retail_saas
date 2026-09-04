<?php

namespace App\Livewire\Platform;

use App\Actions\Companies\ProvisionDefaultRestaurantRoles;
use App\Actions\Subscriptions\RealignSubscriptionPlanForBusinessType;
use App\Enums\RecordStatus;
use App\Livewire\Concerns\HasResponsivePageSize;
use App\Livewire\Concerns\InteractsWithToast;
use App\Models\BusinessType;
use App\Models\Company;
use App\Models\Subscription;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Component;
use Livewire\WithPagination;

class CompaniesPage extends Component
{
    use HasResponsivePageSize, InteractsWithToast, WithPagination;

    public int $perPage = 20;

    public string $filter = 'pending';
    public string $search = '';

    public bool $showTypeModal = false;
    public string $typeModalMode = 'activate';
    public ?int $typeModalCompanyId = null;
    public ?int $selectedBusinessTypeId = null;

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

    public function suspendCompany(int $companyId): void
    {
        abort_unless(auth()->user()?->is_platform_admin, 403);

        $company = Company::findOrFail($companyId);

        $subscription = $company->subscriptions()
            ->whereNull('bundle_id')
            ->latest('id')
            ->first();

        if (! $subscription || $subscription->status !== 'active') {
            $this->toast('Esta empresa no tiene una suscripcion activa.', 'warning');
            return;
        }

        $subscription->update(['status' => 'pending']);

        $this->toast("Empresa \"{$company->display_name}\" suspendida.", 'warning');
    }

    public function openActivationModal(int $companyId): void
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

        $this->typeModalMode = 'activate';
        $this->typeModalCompanyId = $company->id;
        $this->selectedBusinessTypeId = $company->business_type_id;
        $this->showTypeModal = true;
    }

    public function openTypeEditModal(int $companyId): void
    {
        abort_unless(auth()->user()?->is_platform_admin, 403);

        $company = Company::findOrFail($companyId);

        $this->typeModalMode = 'edit';
        $this->typeModalCompanyId = $company->id;
        $this->selectedBusinessTypeId = $company->business_type_id;
        $this->showTypeModal = true;
    }

    public function confirmTypeModal(RealignSubscriptionPlanForBusinessType $realignSubscriptionPlan, ProvisionDefaultRestaurantRoles $provisionDefaultRestaurantRoles): void
    {
        abort_unless(auth()->user()?->is_platform_admin, 403);

        $this->validate([
            'selectedBusinessTypeId' => ['required', 'exists:business_types,id'],
        ], [], ['selectedBusinessTypeId' => 'tipo de negocio']);

        $company = Company::findOrFail($this->typeModalCompanyId);
        $businessType = BusinessType::findOrFail($this->selectedBusinessTypeId);

        if ($this->typeModalMode === 'activate') {
            $subscription = $company->subscriptions()
                ->whereNull('bundle_id')
                ->latest('id')
                ->first();

            if (! $subscription || $subscription->status !== 'pending') {
                $this->toast('Esta empresa no tiene una suscripcion pendiente.', 'warning');
                $this->closeTypeModal();
                return;
            }

            $company->update(['business_type_id' => $businessType->id]);

            try {
                $realignSubscriptionPlan->handle($company, $businessType);
            } catch (\InvalidArgumentException $exception) {
                $this->toast($exception->getMessage(), 'warning');
                $this->closeTypeModal();
                return;
            }

            $subscription->refresh()->update([
                'status'    => 'active',
                'starts_at' => now(),
            ]);

            if ($businessType->code === 'restaurant') {
                $provisionDefaultRestaurantRoles->handle($company);
            }

            $this->toast("Empresa \"{$company->display_name}\" activada correctamente.");
        } else {
            $company->update(['business_type_id' => $businessType->id]);

            try {
                $realignSubscriptionPlan->handle($company, $businessType);
            } catch (\InvalidArgumentException $exception) {
                $this->toast($exception->getMessage(), 'warning');
            }

            if ($businessType->code === 'restaurant') {
                $provisionDefaultRestaurantRoles->handle($company);
            }

            $this->toast("Tipo de negocio de \"{$company->display_name}\" actualizado.");
        }

        $this->closeTypeModal();
    }

    public function closeTypeModal(): void
    {
        $this->showTypeModal = false;
        $this->typeModalMode = 'activate';
        $this->typeModalCompanyId = null;
        $this->selectedBusinessTypeId = null;
        $this->resetErrorBag('selectedBusinessTypeId');
    }

    public function businessTypes(): Collection
    {
        return BusinessType::query()
            ->where('status', RecordStatus::Active->value)
            ->orderBy('id')
            ->get();
    }

    public function companies(): LengthAwarePaginator
    {
        return Company::query()
            ->with(['subscriptions' => fn ($q) => $q->whereNull('bundle_id')->latest('id')->limit(1), 'owner', 'businessType'])
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
            'companies'     => $this->companies(),
            'pendingCount'  => $this->pendingCount(),
            'businessTypes' => $this->businessTypes(),
        ])->layout('layouts.platform');
    }
}
