<?php

namespace App\Livewire\Sales;

use App\Actions\Sales\CancelSale;
use App\Actions\Sales\ReturnSale;
use App\Actions\Settings\UpdateCompanySettings;
use App\Livewire\Concerns\InteractsWithToast;
use App\Models\Company;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Services\Plans\CompanyPlanResolver;
use App\Services\Settings\CompanySettings;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;
use Livewire\Component;

class SalesPage extends Component
{
    use InteractsWithToast;

    public string $search = '';
    public string $statusFilter = '';
    public string $saleTypeFilter = '';
    public ?int $returningSaleId = null;
    public array $returnItems = [];
    public string $returnReason = '';
    public ?int $cancellingSaleId = null;
    public string $cancellationReason = '';

    public bool $showRulesModal = false;
    public bool $ruleFrozenSalesEnabled = true;
    public bool $ruleAllowAlternativePrices = false;
    public bool $ruleAllowManualDiscounts = false;
    public bool $ruleAllowPromotionStacking = false;
    public bool $ruleAllowNegativeStock = false;
    public bool $ruleRequireCustomerForCreditSale = true;
    public string $ruleSaleDocumentPrefix = 'VTA-';
    public string $ruleSaleDocumentStartingSequence = '1';
    public string $ruleTicketFormat = 'thermal_80mm';
    public bool $ruleShowLogo = true;
    public bool $ruleShowSaasBranding = false;

    public function mount(): void
    {
        $this->ensurePermission('sales.view');
    }

    public function canManageRules(): bool
    {
        return auth()->user()?->hasCurrentCompanyPermission('settings.manage') ?? false;
    }

    public function openRulesModal(): void
    {
        $this->ensurePermission('settings.manage');

        $companySettings = app(CompanySettings::class);
        $company = $this->currentCompany();

        $this->ruleFrozenSalesEnabled = (bool) $companySettings->get($company, 'pos', 'frozen_sales_enabled');
        $this->ruleAllowAlternativePrices = (bool) $companySettings->get($company, 'pos', 'allow_alternative_prices');
        $this->ruleAllowManualDiscounts = (bool) $companySettings->get($company, 'pos', 'allow_manual_discounts');
        $this->ruleAllowPromotionStacking = (bool) $companySettings->get($company, 'pos', 'allow_promotion_stacking');
        $this->ruleAllowNegativeStock = (bool) $companySettings->get($company, 'pos', 'allow_negative_stock');
        $this->ruleRequireCustomerForCreditSale = (bool) $companySettings->get($company, 'pos', 'require_customer_for_credit_sale');
        $this->ruleSaleDocumentPrefix = (string) $companySettings->get($company, 'pos', 'sale_document_prefix');
        $this->ruleSaleDocumentStartingSequence = (string) $companySettings->get($company, 'pos', 'sale_document_starting_sequence');
        $this->ruleTicketFormat = (string) $companySettings->get($company, 'printing', 'ticket_format');
        $this->ruleShowLogo = (bool) $companySettings->get($company, 'printing', 'show_logo');
        $this->ruleShowSaasBranding = (bool) $companySettings->get($company, 'printing', 'show_saas_branding');

        $this->resetErrorBag();
        $this->showRulesModal = true;
    }

    public function closeRulesModal(): void
    {
        $this->showRulesModal = false;
        $this->resetErrorBag();
    }

    public function saveRules(UpdateCompanySettings $updateCompanySettings): void
    {
        $this->ensurePermission('settings.manage');

        $validated = $this->validate([
            'ruleSaleDocumentPrefix' => ['required', 'string', 'max:20'],
            'ruleSaleDocumentStartingSequence' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $updateCompanySettings->handle($this->currentCompany(), [
                'pos' => [
                    'frozen_sales_enabled' => $this->ruleFrozenSalesEnabled,
                    'allow_alternative_prices' => $this->ruleAllowAlternativePrices,
                    'allow_manual_discounts' => $this->ruleAllowManualDiscounts,
                    'allow_promotion_stacking' => $this->ruleAllowPromotionStacking,
                    'allow_negative_stock' => $this->ruleAllowNegativeStock,
                    'require_customer_for_credit_sale' => $this->ruleRequireCustomerForCreditSale,
                    'sale_document_prefix' => $validated['ruleSaleDocumentPrefix'],
                    'sale_document_starting_sequence' => $validated['ruleSaleDocumentStartingSequence'],
                ],
                'printing' => [
                    'ticket_format' => $this->ruleTicketFormat,
                    'show_logo' => $this->ruleShowLogo,
                    'show_saas_branding' => $this->ruleShowSaasBranding,
                ],
            ], auth()->user());
        } catch (InvalidArgumentException $exception) {
            $this->addError('ruleSaleDocumentPrefix', $exception->getMessage());

            return;
        }

        $this->showRulesModal = false;
        $this->toast('Reglas de ventas actualizadas correctamente.');
    }

    public function ruleFeatureGates(): array
    {
        $company = $this->currentCompany();
        $resolver = app(CompanyPlanResolver::class);

        return [
            'frozen_sales' => $resolver->hasFeature($company, 'pos.frozen_sales'),
            'alternative_prices' => $resolver->hasFeature($company, 'products.multiple_prices'),
            'manual_discounts' => $resolver->hasFeature($company, 'pos.manual_discounts'),
            'promotion_stacking' => $resolver->hasModule($company, 'promotions'),
            'negative_stock' => $resolver->hasModule($company, 'inventory'),
            'credit_sale' => $resolver->hasModule($company, 'credit'),
        ];
    }

    public function sales(): Collection
    {
        return $this->salesQuery()
            ->with([
                'branch',
                'cashRegister',
                'customer.person',
                'user',
                'payments',
                'items.product',
                'items.presentation',
                'items.variant.attributeValues.attribute',
                'replacesSale',
                'replacedBySale',
            ])
            ->when($this->statusFilter !== '', fn (Builder $query) => $query->where('status', $this->statusFilter))
            ->when($this->saleTypeFilter !== '', fn (Builder $query) => $query->where('sale_type', $this->saleTypeFilter))
            ->when($this->search !== '', function (Builder $query) {
                $search = '%' . trim($this->search) . '%';

                $query->where(function (Builder $nested) use ($search) {
                    $nested
                        ->whereLike('id', $search)
                        ->orWhereLike('document_number', $search)
                        ->orWhereLike('sale_type', $search)
                        ->orWhereLike('status', $search)
                        ->orWhereHas('customer.person', function (Builder $personQuery) use ($search) {
                            $personQuery
                                ->whereLike('first_name', $search)
                                ->orWhereLike('last_name', $search)
                                ->orWhereLike('document_number', $search);
                        })
                        ->orWhereHas('user', fn (Builder $userQuery) => $userQuery->whereLike('name', $search));
                });
            })
            ->orderByDesc('sold_at')
            ->orderByDesc('id')
            ->get();
    }

    public function statusCards(): array
    {
        $sales = $this->sales();

        return [
            'total_count' => $sales->count(),
            'confirmed_count' => $sales->where('status', 'confirmed')->count(),
            'credit_count' => $sales->where('sale_type', 'credit')->count(),
            'grand_total' => \App\Support\Money::format((float) $sales->sum(fn (Sale $sale) => (float) $sale->grand_total)),
        ];
    }

    public function render(): View
    {
        return view('livewire.sales.sales-page', [
            'sales' => $this->sales(),
            'statusCards' => $this->statusCards(),
            'ruleFeatureGates' => $this->canManageRules() ? $this->ruleFeatureGates() : [],
        ])->layout('layouts.app', [
            'header' => view('components.page-title', [
                'title' => 'Ventas',
                'description' => 'Consulta ventas registradas, revisa totales operativos y abre el ticket imprimible por documento.',
            ]),
        ]);
    }

    public function canCreateSales(): bool
    {
        return auth()->user()?->hasCurrentCompanyPermission('sales.create') ?? false;
    }

    public function canReturnSales(): bool
    {
        return auth()->user()?->hasCurrentCompanyPermission('sales.return') ?? false;
    }

    public function canCancelSales(): bool
    {
        return auth()->user()?->hasCurrentCompanyPermission('sales.cancel') ?? false;
    }

    public function startReturningSale(int $saleId): void
    {
        $this->ensurePermission('sales.return');

        $sale = $this->salesQuery()
            ->with(['items'])
            ->findOrFail($saleId);

        if (! in_array($sale->status, ['confirmed', 'partially_returned'], true)) {
            return;
        }

        $this->returningSaleId = $sale->id;
        $this->returnItems = $sale->items
            ->map(function (SaleItem $item) {
                $pendingQuantity = $this->pendingReturnQuantity($item);

                return [
                    'sale_item_id' => $item->id,
                    'quantity' => '',
                    'pending_quantity' => $pendingQuantity,
                ];
            })
            ->filter(fn (array $item) => bccomp($item['pending_quantity'], '0', 6) === 1)
            ->values()
            ->all();

        if ($this->returnItems === []) {
            $this->returningSaleId = null;
            $this->toast('La venta ya no tiene cantidades pendientes por devolver.', 'warning');
        }

        $this->resetValidation();
    }

    public function cancelReturningSale(): void
    {
        $this->returningSaleId = null;
        $this->returnItems = [];
        $this->returnReason = '';
        $this->resetValidation();
    }

    public function registerReturn(ReturnSale $returnSale): void
    {
        $this->ensurePermission('sales.return');

        $validated = $this->validate([
            'returningSaleId' => [
                'required',
                \Illuminate\Validation\Rule::exists('sales', 'id')->where(fn ($query) => $query->where('company_id', $this->currentCompany()->id)),
            ],
            'returnItems' => ['required', 'array', 'min:1'],
            'returnItems.*.sale_item_id' => ['required', 'integer'],
            'returnItems.*.quantity' => ['nullable', 'numeric', 'gt:0'],
            'returnReason' => ['required', 'string', 'max:500'],
        ]);

        $items = collect($validated['returnItems'])
            ->filter(fn (array $item) => isset($item['quantity']) && trim((string) $item['quantity']) !== '')
            ->map(fn (array $item) => [
                'sale_item_id' => (int) $item['sale_item_id'],
                'quantity' => (string) $item['quantity'],
            ])
            ->values()
            ->all();

        if ($items === []) {
            $this->addError('returnItems', 'Debes indicar al menos una cantidad a devolver.');

            return;
        }

        $sale = $this->salesQuery()->findOrFail((int) $validated['returningSaleId']);

        try {
            $returnSale->handle($this->currentCompany(), $sale, $items, $validated['returnReason']);
        } catch (InvalidArgumentException $exception) {
            $this->addError('returnItems', $exception->getMessage());

            return;
        }

        $this->cancelReturningSale();
        $this->toast('Devolucion registrada correctamente.');
    }

    public function startCancellingSale(int $saleId): void
    {
        $this->ensurePermission('sales.cancel');

        $sale = $this->salesQuery()->findOrFail($saleId);

        if (! in_array($sale->status, ['draft', 'confirmed'], true)) {
            return;
        }

        $this->cancellingSaleId = $sale->id;
        $this->cancellationReason = '';
        $this->resetValidation();
    }

    public function cancelCancellingSale(): void
    {
        $this->cancellingSaleId = null;
        $this->cancellationReason = '';
        $this->resetValidation();
    }

    public function cancelSaleDocument(int $saleId, CancelSale $cancelSale): void
    {
        $this->ensurePermission('sales.cancel');

        $validated = $this->validate([
            'cancellationReason' => ['required', 'string', 'max:500'],
        ]);

        $sale = $this->salesQuery()->findOrFail($saleId);

        try {
            $cancelSale->handle($this->currentCompany(), $sale, $validated['cancellationReason']);
        } catch (InvalidArgumentException $exception) {
            $this->addError('cancellationReason', $exception->getMessage());

            return;
        }

        if ($this->returningSaleId === $saleId) {
            $this->cancelReturningSale();
        }

        if ($this->cancellingSaleId === $saleId) {
            $this->cancelCancellingSale();
        }

        $this->toast('Venta anulada correctamente.', 'info');
    }

    public function draftEditUrl(int $saleId): string
    {
        return route('sales.pos', ['edit' => $saleId]);
    }

    public function modifySaleUrl(int $saleId): string
    {
        return route('sales.pos', ['modify' => $saleId]);
    }

    public function variantSummary($variant): string
    {
        if (! $variant) {
            return 'Sin variante';
        }

        $parts = $variant->attributeValues
            ->sortBy(fn ($value) => ($value->attribute->name ?? '') . '-' . $value->value)
            ->map(fn ($value) => ($value->attribute->name ?? 'Atributo') . ': ' . $value->value)
            ->values()
            ->all();

        if ($parts === []) {
            return $variant->sku ?: 'Variante #' . $variant->id;
        }

        return implode(' / ', $parts);
    }

    protected function currentCompany(): Company
    {
        return app(CurrentCompany::class)->company()
            ?? abort(404, 'No hay una empresa activa seleccionada.');
    }

    protected function pendingReturnQuantity(SaleItem $item): string
    {
        return bcsub((string) $item->quantity, (string) $item->returned_quantity, 6);
    }

    protected function salesQuery()
    {
        return Sale::query()
            ->where('company_id', $this->currentCompany()->id);
    }

    protected function ensurePermission(string $permissionCode): void
    {
        abort_unless(
            auth()->user()?->hasCurrentCompanyPermission($permissionCode),
            403,
            'No tienes permiso para acceder a este modulo.'
        );
    }
}
