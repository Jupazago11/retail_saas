<?php

namespace App\Livewire\Promotions;

use App\Actions\Promotions\CreatePromotion;
use App\Actions\Promotions\UpdatePromotion;
use App\Enums\PromotionDiscountType;
use App\Enums\PromotionStatus;
use App\Enums\PromotionTargetType;
use App\Enums\PromotionType;
use App\Enums\RecordStatus;
use App\Livewire\Concerns\InteractsWithToast;
use App\Models\Category;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Promotion;
use App\Models\PromotionComboItem;
use App\Models\PromotionTarget;
use App\Services\Audit\AuditLogger;
use App\Services\Plans\CompanyPlanResolver;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Component;

class PromotionsPage extends Component
{
    use InteractsWithToast;

    protected ?Collection $promotionsCache = null;
    protected ?Collection $productsCache = null;
    protected ?Collection $categoriesCache = null;
    protected ?Collection $variantsCache = null;

    public string $search = '';
    public string $statusFilter = '';
    public string $typeFilter = '';
    public string $effectiveStateFilter = '';
    public ?int $editingPromotionId = null;

    public string $name = '';
    public string $code = '';
    public string $status = 'active';
    public string $promotionType = 'product_discount';
    public string $discountType = 'percentage';
    public string $discountValue = '';
    public string $priority = '100';
    public string $startsAt = '';
    public string $endsAt = '';
    public array $targets = [];
    public array $comboItems = [];
    public int $nextLineKey = 0;

    public function mount(): void
    {
        abort_unless(
            app(CompanyPlanResolver::class)->hasModule($this->currentCompany(), 'promotions'),
            403,
            'El plan actual no tiene habilitado el modulo de promociones.'
        );

        $this->ensurePermission('promotions.manage');
        $this->resetPromotionForm();
    }

    public function updatedPromotionType($value): void
    {
        $value = (string) $value;

        if ($value === PromotionType::ComboPrice->value) {
            $this->discountType = PromotionDiscountType::FixedPrice->value;
            $this->targets = [];

            if (count($this->comboItems) < 2) {
                $this->comboItems = [
                    $this->newComboItemLine(),
                    $this->newComboItemLine(),
                ];
            }

            return;
        }

        if ($this->discountType === PromotionDiscountType::FixedPrice->value) {
            $this->discountType = PromotionDiscountType::Percentage->value;
        }

        $this->comboItems = [];

        if ($this->targets === []) {
            $this->targets = [$this->newTargetLine()];
        }
    }

    public function updatedTargets($value, string $key): void
    {
        $segments = explode('.', $key);

        if (count($segments) < 2) {
            return;
        }

        $index = (int) $segments[0];
        $field = $segments[1];

        if ($field === 'target_type' && isset($this->targets[$index])) {
            $this->targets[$index]['target_id'] = '';
        }
    }

    public function updatedComboItems($value, string $key): void
    {
        $segments = explode('.', $key);

        if (count($segments) < 2) {
            return;
        }

        $index = (int) $segments[0];
        $field = $segments[1];

        if ($field === 'product_id' && isset($this->comboItems[$index])) {
            $this->comboItems[$index]['product_variant_id'] = '';
        }
    }

    public function addTargetLine(): void
    {
        $this->targets[] = $this->newTargetLine();
    }

    public function removeTargetLine(int $lineKey): void
    {
        $this->targets = collect($this->targets)
            ->reject(fn (array $target) => (int) ($target['_key'] ?? -1) === $lineKey)
            ->values()
            ->all();

        if ($this->promotionType === PromotionType::ProductDiscount->value && $this->targets === []) {
            $this->targets = [$this->newTargetLine()];
        }
    }

    public function addComboItemLine(): void
    {
        $this->comboItems[] = $this->newComboItemLine();
    }

    public function removeComboItemLine(int $lineKey): void
    {
        $this->comboItems = collect($this->comboItems)
            ->reject(fn (array $item) => (int) ($item['_key'] ?? -1) === $lineKey)
            ->values()
            ->all();

        if ($this->promotionType === PromotionType::ComboPrice->value && count($this->comboItems) < 2) {
            while (count($this->comboItems) < 2) {
                $this->comboItems[] = $this->newComboItemLine();
            }
        }
    }

    public function resetPromotionForm(): void
    {
        $this->editingPromotionId = null;
        $this->name = '';
        $this->code = '';
        $this->status = PromotionStatus::Active->value;
        $this->promotionType = PromotionType::ProductDiscount->value;
        $this->discountType = PromotionDiscountType::Percentage->value;
        $this->discountValue = '';
        $this->priority = '100';
        $this->startsAt = '';
        $this->endsAt = '';
        $this->targets = [$this->newTargetLine()];
        $this->comboItems = [];
        $this->resetValidation();
    }

    public function startEditingPromotion(int $promotionId): void
    {
        $promotion = $this->promotions()->firstWhere('id', $promotionId)
            ?? $this->currentPromotionQuery()->findOrFail($promotionId);

        $this->editingPromotionId = $promotion->id;
        $this->name = $promotion->name;
        $this->code = (string) ($promotion->code ?? '');
        $this->status = $promotion->status;
        $this->promotionType = $promotion->promotion_type;
        $this->discountType = $promotion->discount_type;
        $this->discountValue = (string) $promotion->discount_value;
        $this->priority = (string) $promotion->priority;
        $this->startsAt = optional($promotion->starts_at)->format('Y-m-d\TH:i') ?? '';
        $this->endsAt = optional($promotion->ends_at)->format('Y-m-d\TH:i') ?? '';
        $this->targets = $promotion->targets
            ->map(fn (PromotionTarget $target) => [
                '_key' => ++$this->nextLineKey,
                'target_type' => $target->target_type,
                'target_id' => (string) $target->target_id,
                'min_quantity' => (string) $target->min_quantity,
            ])
            ->values()
            ->all();
        $this->comboItems = $promotion->comboItems
            ->map(fn (PromotionComboItem $item) => [
                '_key' => ++$this->nextLineKey,
                'product_id' => (string) $item->product_id,
                'product_variant_id' => $item->product_variant_id ? (string) $item->product_variant_id : '',
                'required_quantity' => (string) $item->required_quantity,
            ])
            ->values()
            ->all();

        if ($this->promotionType === PromotionType::ProductDiscount->value && $this->targets === []) {
            $this->targets = [$this->newTargetLine()];
        }

        if ($this->promotionType === PromotionType::ComboPrice->value && count($this->comboItems) < 2) {
            while (count($this->comboItems) < 2) {
                $this->comboItems[] = $this->newComboItemLine();
            }
        }

        $this->resetValidation();
    }

    public function archivePromotion(int $promotionId): void
    {
        $this->ensurePermission('promotions.manage');

        $promotion = $this->currentPromotionQuery()->findOrFail($promotionId);
        $company = $this->currentCompany();

        if ($promotion->status === PromotionStatus::Archived->value) {
            $this->toast('La promocion ya estaba archivada.', 'info');

            return;
        }

        DB::transaction(function () use ($company, $promotion) {
            $before = $promotion->fresh();
            $promotion->update([
                'status' => PromotionStatus::Archived->value,
            ]);

            app(AuditLogger::class)->logUpdated(
                $company,
                'promotion.archived',
                $before,
                $promotion->fresh()
            );
        });

        if ($this->editingPromotionId === $promotionId) {
            $this->resetPromotionForm();
        }

        $this->toast('Promocion archivada correctamente.');
    }

    public function duplicatePromotion(int $promotionId, CreatePromotion $createPromotion): void
    {
        $this->ensurePermission('promotions.manage');

        $promotion = $this->currentPromotionQuery()->findOrFail($promotionId);

        $createPromotion->handle($this->currentCompany(), [
            'name' => $promotion->name.' copia',
            'code' => $promotion->code ? $promotion->code.'-COPY' : null,
            'status' => PromotionStatus::Inactive->value,
            'promotion_type' => $promotion->promotion_type,
            'discount_type' => $promotion->discount_type,
            'discount_value' => (string) $promotion->discount_value,
            'priority' => $promotion->priority,
            'starts_at' => $promotion->starts_at,
            'ends_at' => $promotion->ends_at,
            'targets' => $promotion->targets
                ->map(fn (PromotionTarget $target) => [
                    'target_type' => $target->target_type,
                    'target_id' => (int) $target->target_id,
                    'min_quantity' => (string) $target->min_quantity,
                ])
                ->all(),
            'combo_items' => $promotion->comboItems
                ->map(fn (PromotionComboItem $item) => [
                    'product_id' => (int) $item->product_id,
                    'product_variant_id' => $item->product_variant_id ? (int) $item->product_variant_id : null,
                    'required_quantity' => (string) $item->required_quantity,
                ])
                ->all(),
        ]);

        $this->toast('Promocion duplicada correctamente.');
    }

    public function savePromotion(CreatePromotion $createPromotion, UpdatePromotion $updatePromotion): void
    {
        $this->ensurePermission('promotions.manage');

        $company = $this->currentCompany();
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['nullable', 'string', 'max:60'],
            'status' => ['required', Rule::in($this->promotionStatuses())],
            'promotionType' => ['required', Rule::in($this->promotionTypes())],
            'discountType' => ['required', Rule::in($this->allowedDiscountTypes())],
            'discountValue' => ['required', 'numeric', 'gt:0'],
            'priority' => ['required', 'integer', 'min:0'],
            'startsAt' => ['nullable', 'date'],
            'endsAt' => ['nullable', 'date', 'after_or_equal:startsAt'],
            'targets' => [Rule::requiredIf($this->promotionType === PromotionType::ProductDiscount->value), 'array'],
            'targets.*.target_type' => ['required_if:promotionType,'.PromotionType::ProductDiscount->value, Rule::in($this->promotionTargetTypes())],
            'targets.*.target_id' => ['required_if:promotionType,'.PromotionType::ProductDiscount->value, 'integer'],
            'targets.*.min_quantity' => ['required_if:promotionType,'.PromotionType::ProductDiscount->value, 'numeric', 'gt:0'],
            'comboItems' => [Rule::requiredIf($this->promotionType === PromotionType::ComboPrice->value), 'array'],
            'comboItems.*.product_id' => [
                'required_if:promotionType,'.PromotionType::ComboPrice->value,
                Rule::exists('products', 'id')->where(fn ($query) => $query->where('company_id', $company->id)->whereNull('deleted_at')),
            ],
            'comboItems.*.product_variant_id' => [
                'nullable',
                Rule::exists('product_variants', 'id')->where(fn ($query) => $query->where('company_id', $company->id)),
            ],
            'comboItems.*.required_quantity' => ['required_if:promotionType,'.PromotionType::ComboPrice->value, 'numeric', 'gt:0'],
        ]);

        if ($this->promotionType === PromotionType::ProductDiscount->value && count($validated['targets'] ?? []) < 1) {
            $this->addError('targets', 'La promocion por producto requiere al menos un objetivo.');

            return;
        }

        if ($this->promotionType === PromotionType::ComboPrice->value && count($validated['comboItems'] ?? []) < 2) {
            $this->addError('comboItems', 'El combo requiere al menos dos componentes.');

            return;
        }

        if (! $this->validateTargetSelections($validated['targets'] ?? []) || ! $this->validateComboSelections($validated['comboItems'] ?? [])) {
            return;
        }

        $isEditing = $this->editingPromotionId !== null;

        try {
            $payload = [
                'name' => trim($validated['name']),
                'code' => $this->blankToNull($validated['code']),
                'status' => $validated['status'],
                'promotion_type' => $validated['promotionType'],
                'discount_type' => $validated['discountType'],
                'discount_value' => (string) $validated['discountValue'],
                'priority' => (int) $validated['priority'],
                'starts_at' => $this->blankToNull($validated['startsAt']),
                'ends_at' => $this->blankToNull($validated['endsAt']),
                'targets' => collect($validated['targets'] ?? [])
                    ->map(fn (array $target) => [
                        'target_type' => $target['target_type'],
                        'target_id' => (int) $target['target_id'],
                        'min_quantity' => (string) $target['min_quantity'],
                    ])
                    ->all(),
                'combo_items' => collect($validated['comboItems'] ?? [])
                    ->map(fn (array $item) => [
                        'product_id' => (int) $item['product_id'],
                        'product_variant_id' => $item['product_variant_id'] !== '' && $item['product_variant_id'] !== null ? (int) $item['product_variant_id'] : null,
                        'required_quantity' => (string) $item['required_quantity'],
                    ])
                    ->all(),
            ];

            if ($this->editingPromotionId) {
                $updatePromotion->handle(
                    $company,
                    $this->currentPromotionQuery()->findOrFail($this->editingPromotionId),
                    $payload
                );
            } else {
                $createPromotion->handle($company, $payload);
            }
        } catch (InvalidArgumentException $exception) {
            $this->addError('name', $exception->getMessage());

            return;
        }

        $this->resetPromotionForm();
        $this->toast(
            $isEditing
                ? 'Promocion actualizada correctamente.'
                : 'Promocion guardada correctamente.'
        );
    }

    public function promotions(): Collection
    {
        $promotions = $this->promotionsCache ??= Promotion::query()
            ->where('company_id', $this->currentCompany()->id)
            ->with([
                'targets',
                'comboItems.product',
                'comboItems.variant.product',
            ])
            ->when($this->search !== '', function (Builder $query) {
                $search = '%'.trim($this->search).'%';

                $query->where(function (Builder $nested) use ($search) {
                    $nested
                        ->whereLike('name', $search)
                        ->orWhereLike('code', $search);
                });
            })
            ->when($this->statusFilter !== '', fn (Builder $query) => $query->where('status', $this->statusFilter))
            ->when($this->typeFilter !== '', fn (Builder $query) => $query->where('promotion_type', $this->typeFilter))
            ->orderByRaw("case when status = 'active' then 0 when status = 'inactive' then 1 else 2 end")
            ->orderBy('priority')
            ->orderByDesc('id')
            ->get();

        if ($this->effectiveStateFilter === '') {
            return $promotions;
        }

        return $promotions
            ->filter(fn (Promotion $promotion) => $this->effectiveState($promotion) === $this->effectiveStateFilter)
            ->values();
    }

    public function summaryCards(): array
    {
        $promotions = $this->promotions();

        return [
            'total' => $promotions->count(),
            'active' => $promotions->where('status', PromotionStatus::Active->value)->count(),
            'running' => $promotions->filter(fn (Promotion $promotion) => $this->effectiveState($promotion) === 'running')->count(),
            'upcoming' => $promotions->filter(fn (Promotion $promotion) => $this->effectiveState($promotion) === 'upcoming')->count(),
            'product_discounts' => $promotions->where('promotion_type', PromotionType::ProductDiscount->value)->count(),
            'combos' => $promotions->where('promotion_type', PromotionType::ComboPrice->value)->count(),
        ];
    }

    public function products(): Collection
    {
        return $this->productsCache ??= Product::query()
            ->where('company_id', $this->currentCompany()->id)
            ->where('status', RecordStatus::Active->value)
            ->whereNull('deleted_at')
            ->with(['category', 'variants'])
            ->orderBy('name')
            ->get();
    }

    public function categories(): Collection
    {
        return $this->categoriesCache ??= Category::query()
            ->where('company_id', $this->currentCompany()->id)
            ->where('status', RecordStatus::Active->value)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get();
    }

    public function variants(): Collection
    {
        return $this->variantsCache ??= ProductVariant::query()
            ->where('company_id', $this->currentCompany()->id)
            ->where('status', RecordStatus::Active->value)
            ->with('product')
            ->orderBy('sku')
            ->get();
    }

    public function targetOptionsForType(string $targetType): array
    {
        return match ($targetType) {
            PromotionTargetType::Product->value => $this->products()
                ->map(fn (Product $product) => [
                    'id' => $product->id,
                    'label' => $product->name,
                ])
                ->all(),
            PromotionTargetType::Category->value => $this->categories()
                ->map(fn (Category $category) => [
                    'id' => $category->id,
                    'label' => $category->name,
                ])
                ->all(),
            PromotionTargetType::Variant->value => $this->variants()
                ->map(fn (ProductVariant $variant) => [
                    'id' => $variant->id,
                    'label' => trim(($variant->product?->name ?? 'Producto').' / '.($variant->sku ?: 'Variante '.$variant->id)),
                ])
                ->all(),
            default => [],
        };
    }

    public function statusLabel(string $status): string
    {
        return match ($status) {
            PromotionStatus::Active->value => 'Activa',
            PromotionStatus::Inactive->value => 'Inactiva',
            PromotionStatus::Archived->value => 'Archivada',
            default => $status,
        };
    }

    public function promotionTypeLabel(string $promotionType): string
    {
        return match ($promotionType) {
            PromotionType::ProductDiscount->value => 'Descuento por producto',
            PromotionType::ComboPrice->value => 'Combo a precio fijo',
            default => $promotionType,
        };
    }

    public function discountTypeLabel(string $discountType): string
    {
        return match ($discountType) {
            PromotionDiscountType::Percentage->value => 'Porcentaje',
            PromotionDiscountType::FixedAmount->value => 'Monto fijo',
            PromotionDiscountType::FixedPrice->value => 'Precio fijo',
            default => $discountType,
        };
    }

    public function targetTypeLabel(string $targetType): string
    {
        return match ($targetType) {
            PromotionTargetType::Product->value => 'Producto',
            PromotionTargetType::Category->value => 'Categoria',
            PromotionTargetType::Variant->value => 'Variante',
            default => $targetType,
        };
    }

    public function promotionTargetLabel(PromotionTarget $target): string
    {
        $option = collect($this->targetOptionsForType($target->target_type))
            ->firstWhere('id', $target->target_id);

        return $option['label'] ?? 'Objetivo '.$target->target_id;
    }

    public function comboItemLabel(PromotionComboItem $item): string
    {
        $productName = $item->product?->name ?? 'Producto '.$item->product_id;

        if (! $item->variant) {
            return $productName;
        }

        return $productName.' / '.($item->variant->sku ?: 'Variante '.$item->variant->id);
    }

    public function effectiveState(Promotion $promotion): string
    {
        if ($promotion->status === PromotionStatus::Archived->value) {
            return 'archived';
        }

        if ($promotion->status === PromotionStatus::Inactive->value) {
            return 'inactive';
        }

        $now = now();

        if ($promotion->starts_at && $promotion->starts_at->isFuture()) {
            return 'upcoming';
        }

        if ($promotion->ends_at && $promotion->ends_at->isPast()) {
            return 'expired';
        }

        return 'running';
    }

    public function effectiveStateLabel(Promotion $promotion): string
    {
        return match ($this->effectiveState($promotion)) {
            'running' => 'Vigente',
            'upcoming' => 'Programada',
            'expired' => 'Vencida',
            'inactive' => 'Inactiva',
            'archived' => 'Archivada',
            default => 'Sin estado',
        };
    }

    public function render(): View
    {
        return view('livewire.promotions.promotions-page', [
            'promotions' => $this->promotions(),
            'statusCards' => $this->summaryCards(),
        ])->layout('layouts.app', [
            'header' => view('components.page-title', [
                'title' => 'Promociones',
                'description' => 'Administra descuentos por producto y combos a precio fijo con alcance por producto, categoria o variante.',
            ]),
        ]);
    }

    protected function currentCompany(): Company
    {
        return app(CurrentCompany::class)->company()
            ?? abort(404, 'No hay una empresa activa seleccionada.');
    }

    protected function currentPromotionQuery()
    {
        return Promotion::query()
            ->where('company_id', $this->currentCompany()->id)
            ->with(['targets', 'comboItems']);
    }

    protected function ensurePermission(string $permissionCode): void
    {
        abort_unless(
            auth()->user()?->hasCurrentCompanyPermission($permissionCode),
            403,
            'No tienes permiso para acceder a este modulo.'
        );
    }

    protected function newTargetLine(): array
    {
        return [
            '_key' => ++$this->nextLineKey,
            'target_type' => PromotionTargetType::Product->value,
            'target_id' => '',
            'min_quantity' => '1',
        ];
    }

    protected function newComboItemLine(): array
    {
        return [
            '_key' => ++$this->nextLineKey,
            'product_id' => '',
            'product_variant_id' => '',
            'required_quantity' => '1',
        ];
    }

    protected function validateTargetSelections(array $targets): bool
    {
        if ($this->promotionType !== PromotionType::ProductDiscount->value) {
            return true;
        }

        $valid = true;
        $productIds = $this->products()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $categoryIds = $this->categories()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $variantIds = $this->variants()->pluck('id')->map(fn ($id) => (int) $id)->all();

        foreach ($targets as $index => $target) {
            $allowedIds = match ($target['target_type']) {
                PromotionTargetType::Product->value => $productIds,
                PromotionTargetType::Category->value => $categoryIds,
                PromotionTargetType::Variant->value => $variantIds,
                default => [],
            };

            if (! in_array((int) $target['target_id'], $allowedIds, true)) {
                $this->addError('targets.'.$index.'.target_id', 'El objetivo seleccionado no pertenece a la empresa activa.');
                $valid = false;
            }
        }

        return $valid;
    }

    protected function validateComboSelections(array $comboItems): bool
    {
        if ($this->promotionType !== PromotionType::ComboPrice->value) {
            return true;
        }

        $valid = true;
        $variants = $this->variants()->keyBy('id');

        foreach ($comboItems as $index => $comboItem) {
            if (! empty($comboItem['product_variant_id'])) {
                $variant = $variants->get((int) $comboItem['product_variant_id']);

                if (! $variant || (int) $variant->product_id !== (int) $comboItem['product_id']) {
                    $this->addError('comboItems.'.$index.'.product_variant_id', 'La variante seleccionada no corresponde al producto elegido.');
                    $valid = false;
                }
            }
        }

        return $valid;
    }

    protected function promotionStatuses(): array
    {
        return array_map(fn (PromotionStatus $status) => $status->value, PromotionStatus::cases());
    }

    protected function promotionTypes(): array
    {
        return array_map(fn (PromotionType $type) => $type->value, PromotionType::cases());
    }

    protected function promotionTargetTypes(): array
    {
        return array_map(fn (PromotionTargetType $type) => $type->value, PromotionTargetType::cases());
    }

    protected function allowedDiscountTypes(): array
    {
        if ($this->promotionType === PromotionType::ComboPrice->value) {
            return [PromotionDiscountType::FixedPrice->value];
        }

        return [
            PromotionDiscountType::Percentage->value,
            PromotionDiscountType::FixedAmount->value,
        ];
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
