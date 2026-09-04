<?php

namespace App\Livewire\Admin;

use App\Actions\Coupons\CreateCoupon;
use App\Actions\Coupons\UpdateCoupon;
use App\Enums\RecordStatus;
use App\Livewire\Concerns\InteractsWithToast;
use App\Models\Company;
use App\Models\Coupon;
use App\Models\Plan;
use App\Models\SubscriptionBundle;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Component;

class CouponsPage extends Component
{
    use InteractsWithToast;

    public ?int $editingCouponId = null;
    public string $code = '';
    public string $name = '';
    public string $discountType = 'percentage';
    public string $discountValue = '';
    public string $startsAt = '';
    public string $expiresAt = '';
    public string $totalUsesLimit = '';
    public string $perUserLimit = '';
    public string $perCompanyLimit = '';
    public string $status = 'active';
    public array $selectedPlanIds = [];
    public array $selectedBundleIds = [];

    public function mount(): void
    {
        $this->ensurePermission('settings.manage');
        $this->resetCouponForm();
    }

    public function resetCouponForm(): void
    {
        $this->editingCouponId = null;
        $this->code = '';
        $this->name = '';
        $this->discountType = 'percentage';
        $this->discountValue = '';
        $this->startsAt = '';
        $this->expiresAt = '';
        $this->totalUsesLimit = '';
        $this->perUserLimit = '';
        $this->perCompanyLimit = '';
        $this->status = RecordStatus::Active->value;
        $this->selectedPlanIds = [];
        $this->selectedBundleIds = [];
        $this->resetValidation();
    }

    public function startEditingCoupon(int $couponId): void
    {
        $coupon = $this->coupons()->firstWhere('id', $couponId)
            ?? Coupon::query()->with(['plans', 'bundles'])->findOrFail($couponId);

        $this->editingCouponId = $coupon->id;
        $this->code = $coupon->code;
        $this->name = $coupon->name;
        $this->discountType = $coupon->discount_type;
        $this->discountValue = (string) $coupon->discount_value;
        $this->startsAt = optional($coupon->starts_at)->format('Y-m-d\TH:i') ?? '';
        $this->expiresAt = optional($coupon->expires_at)->format('Y-m-d\TH:i') ?? '';
        $this->totalUsesLimit = $coupon->total_uses_limit ? (string) $coupon->total_uses_limit : '';
        $this->perUserLimit = $coupon->per_user_limit ? (string) $coupon->per_user_limit : '';
        $this->perCompanyLimit = $coupon->per_company_limit ? (string) $coupon->per_company_limit : '';
        $this->status = $coupon->status;
        $this->selectedPlanIds = $coupon->plans->pluck('id')->map(fn ($id) => (string) $id)->values()->all();
        $this->selectedBundleIds = $coupon->bundles->pluck('id')->map(fn ($id) => (string) $id)->values()->all();
        $this->resetValidation();
    }

    public function saveCoupon(CreateCoupon $createCoupon, UpdateCoupon $updateCoupon): void
    {
        $this->ensurePermission('settings.manage');

        $validated = $this->validate([
            'code' => ['required', 'string', 'max:80'],
            'name' => ['required', 'string', 'max:255'],
            'discountType' => ['required', Rule::in(['percentage', 'fixed_amount'])],
            'discountValue' => ['required', 'numeric', 'gt:0'],
            'startsAt' => ['nullable', 'date'],
            'expiresAt' => ['nullable', 'date', 'after_or_equal:startsAt'],
            'totalUsesLimit' => ['nullable', 'integer', 'min:1'],
            'perUserLimit' => ['nullable', 'integer', 'min:1'],
            'perCompanyLimit' => ['nullable', 'integer', 'min:1'],
            'status' => ['required', Rule::in([RecordStatus::Active->value, RecordStatus::Inactive->value])],
            'selectedPlanIds' => ['array'],
            'selectedPlanIds.*' => ['integer', Rule::exists('plans', 'id')->where(fn ($query) => $query->where('status', RecordStatus::Active->value))],
            'selectedBundleIds' => ['array'],
            'selectedBundleIds.*' => ['integer', Rule::exists('subscription_bundles', 'id')->where(fn ($query) => $query->where('status', RecordStatus::Active->value))],
        ]);

        $payload = [
            'code' => $validated['code'],
            'name' => $validated['name'],
            'discount_type' => $validated['discountType'],
            'discount_value' => (string) $validated['discountValue'],
            'starts_at' => $this->blankToNull($validated['startsAt'] ?? null),
            'expires_at' => $this->blankToNull($validated['expiresAt'] ?? null),
            'total_uses_limit' => $this->blankToNull($validated['totalUsesLimit'] ?? null),
            'per_user_limit' => $this->blankToNull($validated['perUserLimit'] ?? null),
            'per_company_limit' => $this->blankToNull($validated['perCompanyLimit'] ?? null),
            'status' => $validated['status'],
            'plan_ids' => $validated['selectedPlanIds'] ?? [],
            'bundle_ids' => $validated['selectedBundleIds'] ?? [],
        ];

        try {
            if ($this->editingCouponId) {
                $updateCoupon->handle(
                    $this->currentCompany(),
                    Coupon::query()->findOrFail($this->editingCouponId),
                    $payload,
                    auth()->user(),
                );
            } else {
                $createCoupon->handle($this->currentCompany(), $payload, auth()->user());
            }
        } catch (InvalidArgumentException $exception) {
            $this->addError('code', $exception->getMessage());

            return;
        }

        $wasEditing = $this->editingCouponId !== null;
        $this->resetCouponForm();
        $this->toast($wasEditing ? 'Cupon actualizado correctamente.' : 'Cupon guardado correctamente.');
    }

    public function toggleStatus(int $couponId): void
    {
        $this->ensurePermission('settings.manage');

        $coupon = Coupon::query()->findOrFail($couponId);
        $coupon->update([
            'status' => $coupon->status === RecordStatus::Active->value
                ? RecordStatus::Inactive->value
                : RecordStatus::Active->value,
        ]);

        $this->toast('Estado del cupon actualizado.');
    }

    public function coupons(): Collection
    {
        return Coupon::query()
            ->with([
                'plans',
                'bundles',
                'redemptions' => fn ($query) => $query
                    ->where(function ($companyQuery) {
                        $companyQuery
                            ->whereNull('company_id')
                            ->orWhere('company_id', $this->currentCompany()->id);
                    })
                    ->with(['company', 'user', 'subscription.plan'])
                    ->latest('id')
                    ->limit(5),
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

    public function availableBundles(): Collection
    {
        return SubscriptionBundle::query()
            ->where('status', RecordStatus::Active->value)
            ->with('owner')
            ->orderBy('name')
            ->get();
    }

    public function formatMoney(mixed $value): string
    {
        return \App\Support\Money::format((float) $value);
    }

    public function discountTypeLabel(string $discountType): string
    {
        return match ($discountType) {
            'percentage' => 'Porcentaje',
            'fixed_amount' => 'Monto fijo',
            default => $discountType,
        };
    }

    public function render(): View
    {
        return view('livewire.admin.coupons-page', [
            'coupons' => $this->coupons(),
            'availablePlans' => $this->availablePlans(),
            'availableBundles' => $this->availableBundles(),
        ])->layout('layouts.app', [
            'header' => view('components.page-title', [
                'title' => 'Cupones',
                'description' => 'Administra cupones comerciales, su alcance por planes o paquetes y sus redenciones recientes.',
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
