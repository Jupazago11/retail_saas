<?php

namespace App\Livewire\Platform;

use App\Livewire\Concerns\InteractsWithToast;
use App\Models\Coupon;
use App\Models\Plan;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Component;

class PlatformCouponsPage extends Component
{
    use InteractsWithToast;

    public bool    $showModal      = false;
    public ?int    $editingId      = null;
    public string  $code           = '';
    public string  $name           = '';
    public string  $discountType   = 'percentage';
    public string  $discountValue  = '';
    public string  $startsAt       = '';
    public string  $expiresAt      = '';
    public string  $totalUsesLimit = '';
    public string  $perCompanyLimit = '';
    public string  $status         = 'active';
    public array   $selectedPlanIds = [];

    public function mount(): void
    {
        abort_unless(auth()->user()?->is_platform_admin, 403);
    }

    public function openCreate(): void
    {
        $this->reset(['editingId','code','name','discountType','discountValue','startsAt','expiresAt','totalUsesLimit','perCompanyLimit','selectedPlanIds']);
        $this->discountType = 'percentage';
        $this->status       = 'active';
        $this->showModal    = true;
    }

    public function startEdit(int $id): void
    {
        $coupon = Coupon::with('plans')->findOrFail($id);
        $this->editingId       = $coupon->id;
        $this->code            = $coupon->code;
        $this->name            = $coupon->name;
        $this->discountType    = $coupon->discount_type;
        $this->discountValue   = (string) $coupon->discount_value;
        $this->startsAt        = $coupon->starts_at?->format('Y-m-d') ?? '';
        $this->expiresAt       = $coupon->expires_at?->format('Y-m-d') ?? '';
        $this->totalUsesLimit  = $coupon->total_uses_limit ? (string) $coupon->total_uses_limit : '';
        $this->perCompanyLimit = $coupon->per_company_limit ? (string) $coupon->per_company_limit : '';
        $this->status          = $coupon->status;
        $this->selectedPlanIds = $coupon->plans->pluck('id')->map(fn ($i) => (string) $i)->all();
        $this->showModal       = true;
    }

    public function save(): void
    {
        abort_unless(auth()->user()?->is_platform_admin, 403);

        $uniqueCode = $this->editingId
            ? Rule::unique('coupons', 'code')->ignore($this->editingId)
            : Rule::unique('coupons', 'code');

        $validated = $this->validate([
            'code'            => ['required', 'string', 'max:80', $uniqueCode],
            'name'            => ['required', 'string', 'max:255'],
            'discountType'    => ['required', Rule::in(['percentage', 'fixed_amount'])],
            'discountValue'   => ['required', 'numeric', 'gt:0'],
            'startsAt'        => ['nullable', 'date'],
            'expiresAt'       => ['nullable', 'date'],
            'totalUsesLimit'  => ['nullable', 'integer', 'min:1'],
            'perCompanyLimit' => ['nullable', 'integer', 'min:1'],
            'status'          => ['required', Rule::in(['active', 'inactive'])],
            'selectedPlanIds' => ['array'],
            'selectedPlanIds.*' => ['integer', 'exists:plans,id'],
        ]);

        $payload = [
            'code'              => strtoupper($validated['code']),
            'name'              => $validated['name'],
            'discount_type'     => $validated['discountType'],
            'discount_value'    => $validated['discountValue'],
            'starts_at'         => $this->startsAt ?: null,
            'expires_at'        => $this->expiresAt ?: null,
            'total_uses_limit'  => $this->totalUsesLimit ?: null,
            'per_company_limit' => $this->perCompanyLimit ?: null,
            'status'            => $validated['status'],
        ];

        if ($this->editingId) {
            $coupon = Coupon::findOrFail($this->editingId);
            $coupon->update($payload);
        } else {
            $coupon = Coupon::create($payload);
        }

        $coupon->plans()->sync($validated['selectedPlanIds'] ?? []);

        $this->showModal = false;
        $this->toast($this->editingId ? 'Cupón actualizado.' : 'Cupón creado.');
    }

    public function toggleStatus(int $id): void
    {
        abort_unless(auth()->user()?->is_platform_admin, 403);
        $coupon = Coupon::findOrFail($id);
        $coupon->update(['status' => $coupon->status === 'active' ? 'inactive' : 'active']);
        $this->toast('Estado del cupón actualizado.');
    }

    public function closeModal(): void
    {
        $this->showModal = false;
    }

    public function plans(): Collection
    {
        return Plan::where('status', 'active')->orderBy('id')->get();
    }

    public function render(): View
    {
        $coupons = Coupon::with(['plans', 'redemptions'])->latest('id')->get();

        return view('livewire.platform.platform-coupons-page', [
            'coupons' => $coupons,
            'plans'   => $this->plans(),
        ])->layout('layouts.platform');
    }
}
