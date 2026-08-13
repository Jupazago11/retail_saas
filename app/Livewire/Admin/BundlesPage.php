<?php

namespace App\Livewire\Admin;

use App\Actions\Subscriptions\CreateSubscriptionBundle;
use App\Actions\Subscriptions\UpdateSubscriptionBundle;
use App\Enums\RecordStatus;
use App\Livewire\Concerns\InteractsWithToast;
use App\Models\Company;
use App\Models\Plan;
use App\Models\SubscriptionBundleCompany;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Component;

class BundlesPage extends Component
{
    use InteractsWithToast;

    public ?int $editingMembershipId = null;
    public string $name = '';
    public string $status = 'active';
    public string $maxCompanies = '';
    public string $discountType = '';
    public string $discountValue = '';
    public string $planId = '';

    public function mount(): void
    {
        $this->ensurePermission('settings.manage');
        $this->resetBundleForm();
    }

    public function resetBundleForm(): void
    {
        $this->editingMembershipId = null;
        $this->name = '';
        $this->status = RecordStatus::Active->value;
        $this->maxCompanies = '';
        $this->discountType = '';
        $this->discountValue = '';
        $this->planId = '';
        $this->resetValidation();
    }

    public function startEditingMembership(int $membershipId): void
    {
        $membership = $this->memberships()->firstWhere('id', $membershipId)
            ?? SubscriptionBundleCompany::query()
                ->with(['plan', 'bundle.subscriptions' => fn ($query) => $query->latest('starts_at')->latest('id')])
                ->findOrFail($membershipId);

        $bundle = $membership->bundle;
        $bundleSubscription = $bundle?->subscriptions->first();

        $this->editingMembershipId = $membership->id;
        $this->name = (string) ($bundle?->name ?? '');
        $this->status = (string) ($bundle?->status ?? RecordStatus::Active->value);
        $this->maxCompanies = $bundle?->max_companies ? (string) $bundle->max_companies : '';
        $this->discountType = (string) ($bundle?->discount_type ?? '');
        $this->discountValue = $bundle ? (string) $bundle->discount_value : '';
        $this->planId = (string) ($membership->plan_id ?: $bundleSubscription?->plan_id ?: '');
        $this->resetValidation();
    }

    public function saveBundle(CreateSubscriptionBundle $createSubscriptionBundle, UpdateSubscriptionBundle $updateSubscriptionBundle): void
    {
        $this->ensurePermission('settings.manage');

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::in([RecordStatus::Active->value, RecordStatus::Inactive->value])],
            'maxCompanies' => ['nullable', 'integer', 'min:1'],
            'discountType' => ['nullable', Rule::in(['percentage', 'fixed_amount'])],
            'discountValue' => ['nullable', 'numeric', 'min:0'],
            'planId' => ['required', 'integer', Rule::exists('plans', 'id')->where(fn ($query) => $query->where('status', RecordStatus::Active->value))],
        ]);

        $payload = [
            'name' => $validated['name'],
            'status' => $validated['status'],
            'max_companies' => $this->blankToNull($validated['maxCompanies'] ?? null),
            'discount_type' => $this->blankToNull($validated['discountType'] ?? null),
            'discount_value' => $this->blankToNull($validated['discountValue'] ?? null),
            'plan_id' => (int) $validated['planId'],
        ];

        try {
            if ($this->editingMembershipId) {
                $updateSubscriptionBundle->handle(
                    $this->currentCompany(),
                    SubscriptionBundleCompany::query()->findOrFail($this->editingMembershipId),
                    $payload,
                    auth()->user(),
                );
            } else {
                $createSubscriptionBundle->handle(
                    $this->currentCompany(),
                    $payload,
                    auth()->user(),
                );
            }
        } catch (InvalidArgumentException $exception) {
            $this->addError('name', $exception->getMessage());

            return;
        }

        $wasEditing = $this->editingMembershipId !== null;
        $this->resetBundleForm();
        $this->toast($wasEditing ? 'Bundle actualizado correctamente.' : 'Bundle creado correctamente.');
    }

    public function memberships(): Collection
    {
        return SubscriptionBundleCompany::query()
            ->where('company_id', $this->currentCompany()->id)
            ->with([
                'plan',
                'bundle.owner',
                'bundle.companies.company',
                'bundle.subscriptions' => fn ($query) => $query->with('plan')->latest('starts_at')->latest('id'),
            ])
            ->latest('id')
            ->get();
    }

    public function availablePlans(): Collection
    {
        return Plan::query()
            ->where('status', RecordStatus::Active->value)
            ->orderBy('id')
            ->get();
    }

    public function assignedPlanLabel(SubscriptionBundleCompany $membership): string
    {
        return $membership->plan?->name
            ?? $membership->bundle?->subscriptions->first()?->plan?->name
            ?? 'Sin plan';
    }

    public function formatMoney(mixed $value): string
    {
        return \App\Support\Money::format((float) $value);
    }

    public function render(): View
    {
        return view('livewire.admin.bundles-page', [
            'memberships' => $this->memberships(),
            'availablePlans' => $this->availablePlans(),
        ])->layout('layouts.app', [
            'header' => view('components.page-title', [
                'title' => 'Paquetes',
                'description' => 'Administra los paquetes asociados a la empresa activa y su contexto comercial actual.',
            ]),
        ]);
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
