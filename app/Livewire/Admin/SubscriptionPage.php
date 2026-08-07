<?php

namespace App\Livewire\Admin;

use App\Actions\Subscriptions\ChangeCompanySubscription;
use App\Actions\Subscriptions\EndCompanySubscription;
use App\Actions\Subscriptions\RenewCompanySubscription;
use App\Enums\RecordStatus;
use App\Livewire\Concerns\InteractsWithToast;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionBundleCompany;
use App\Services\Plans\CompanyPlanResolver;
use App\Services\Plans\PlanCatalogBootstrapper;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Component;

class SubscriptionPage extends Component
{
    use InteractsWithToast;

    public string $selectedPlanId = '';
    public string $subscriptionStatus = 'active';
    public string $trialDays = '14';
    public string $startsAt = '';

    public function mount(PlanCatalogBootstrapper $planCatalogBootstrapper): void
    {
        $this->ensurePermission('settings.manage');
        $planCatalogBootstrapper->ensureDefaults();
        $this->hydrateDefaults();
    }

    public function saveSubscription(ChangeCompanySubscription $changeCompanySubscription): void
    {
        $this->ensurePermission('settings.manage');

        $rules = [
            'selectedPlanId' => ['required', 'integer'],
            'subscriptionStatus' => ['required', 'in:active,trialing'],
            'startsAt' => ['nullable', 'date'],
        ];

        if ($this->subscriptionStatus === 'trialing') {
            $rules['trialDays'] = ['required', 'integer', 'min:1', 'max:365'];
        }

        $validated = $this->validate($rules);

        try {
            $changeCompanySubscription->handle($this->currentCompany(), [
                'plan_id' => (int) $validated['selectedPlanId'],
                'status' => $validated['subscriptionStatus'],
                'starts_at' => $this->blankToNull($validated['startsAt'] ?? null),
                'trial_days' => $validated['subscriptionStatus'] === 'trialing'
                    ? (int) ($validated['trialDays'] ?: 14)
                    : null,
            ], auth()->user());
        } catch (InvalidArgumentException $exception) {
            $this->addError('selectedPlanId', $exception->getMessage());

            return;
        }

        $this->hydrateDefaults();
        $this->toast('Suscripcion actualizada correctamente.');
    }

    public function endSubscription(int $subscriptionId, EndCompanySubscription $endCompanySubscription): void
    {
        $this->ensurePermission('settings.manage');

        try {
            $endCompanySubscription->handle(
                $this->currentCompany(),
                $this->directSubscriptions()->firstWhere('id', $subscriptionId)
                    ?? Subscription::query()->findOrFail($subscriptionId),
                now(),
                auth()->user(),
            );
        } catch (InvalidArgumentException $exception) {
            $this->addError('selectedPlanId', $exception->getMessage());

            return;
        }

        $this->hydrateDefaults();
        $this->toast('Suscripcion finalizada correctamente.');
    }

    public function renewSubscription(int $subscriptionId, RenewCompanySubscription $renewCompanySubscription): void
    {
        $this->ensurePermission('settings.manage');

        $subscription = $this->directSubscriptions()->firstWhere('id', $subscriptionId)
            ?? Subscription::query()->findOrFail($subscriptionId);

        try {
            $renewCompanySubscription->handle(
                $this->currentCompany(),
                $subscription,
                'active',
                now(),
                null,
                auth()->user(),
            );
        } catch (InvalidArgumentException $exception) {
            $this->addError('selectedPlanId', $exception->getMessage());

            return;
        }

        $this->hydrateDefaults();
        $this->toast('Suscripcion renovada correctamente.');
    }

    public function plans(): Collection
    {
        return Plan::query()
            ->where('status', RecordStatus::Active->value)
            ->with(['modules', 'features', 'limits'])
            ->orderBy('id')
            ->get();
    }

    public function directSubscriptions(): Collection
    {
        return Subscription::query()
            ->where('company_id', $this->currentCompany()->id)
            ->whereNull('bundle_id')
            ->with('plan')
            ->latest('starts_at')
            ->latest('id')
            ->get();
    }

    public function bundleMemberships(): Collection
    {
        return SubscriptionBundleCompany::query()
            ->where('company_id', $this->currentCompany()->id)
            ->with(['plan', 'bundle.owner', 'bundle.subscriptions.plan'])
            ->latest('id')
            ->get();
    }

    public function effectiveSnapshot(): array
    {
        return app(CompanyPlanResolver::class)->snapshot($this->currentCompany());
    }

    public function sourceLabel(string $source): string
    {
        return match ($source) {
            'direct' => 'Suscripcion directa',
            'bundle' => 'Bundle',
            default => 'Sin fuente',
        };
    }

    public function statusLabel(string $status): string
    {
        return match ($status) {
            'trialing' => 'Trial',
            'active' => 'Activa',
            'ended' => 'Finalizada',
            default => ucfirst($status),
        };
    }

    public function formatMoney(mixed $value): string
    {
        return \App\Support\Money::format((float) $value);
    }

    public function render(): View
    {
        $snapshot = $this->effectiveSnapshot();

        return view('livewire.admin.subscription-page', [
            'plans' => $this->plans(),
            'snapshot' => $snapshot,
            'directSubscriptions' => $this->directSubscriptions(),
            'bundleMemberships' => $this->bundleMemberships(),
        ])->layout('layouts.app', [
            'header' => view('components.page-title', [
                'title' => 'Suscripcion',
                'description' => 'Consulta el plan efectivo de la empresa y administra cambios manuales, cierres y renovaciones de suscripciones directas.',
            ]),
        ]);
    }

    protected function hydrateDefaults(): void
    {
        $currentDirectSubscription = Subscription::query()
            ->where('company_id', $this->currentCompany()->id)
            ->whereNull('bundle_id')
            ->activeAt(now())
            ->latest('starts_at')
            ->latest('id')
            ->first();

        if ($currentDirectSubscription) {
            $this->selectedPlanId = (string) $currentDirectSubscription->plan_id;
            $this->subscriptionStatus = $currentDirectSubscription->status === 'trialing' ? 'trialing' : 'active';
            $this->trialDays = $currentDirectSubscription->trial_ends_at
                ? (string) max(1, now()->diffInDays($currentDirectSubscription->trial_ends_at, false))
                : '14';
            $this->startsAt = optional($currentDirectSubscription->starts_at)->format('Y-m-d\TH:i') ?? '';
            $this->resetValidation();

            return;
        }

        $snapshot = $this->effectiveSnapshot();
        $this->selectedPlanId = $snapshot['plan']?->id ? (string) $snapshot['plan']->id : '';
        $this->subscriptionStatus = 'active';
        $this->trialDays = '14';
        $this->startsAt = '';
        $this->resetValidation();
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

    protected function blankToNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
