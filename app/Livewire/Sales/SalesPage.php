<?php

namespace App\Livewire\Sales;

use App\Actions\Sales\CancelSale;
use App\Actions\Sales\ReturnSale;
use App\Actions\Settings\UpdateCompanyLogo;
use App\Actions\Settings\UpdateCompanySettings;
use App\Livewire\Concerns\HasResponsivePageSize;
use App\Livewire\Concerns\InteractsWithToast;
use App\Models\Company;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Services\Plans\CompanyPlanResolver;
use App\Services\Settings\CompanySettings;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class SalesPage extends Component
{
    use HasResponsivePageSize, InteractsWithToast, WithFileUploads, WithPagination;

    public int $perPage = 10;

    public string $search = '';
    public string $statusFilter = '';
    public string $saleTypeFilter = '';
    public ?int $returningSaleId = null;
    public array $returnItems = [];
    public string $returnReason = '';
    public ?int $cancellingSaleId = null;
    public string $cancellationReason = '';

    public bool $showRulesModal = false;
    public bool $ruleAllowAlternativePrices = false;
    public bool $ruleAllowManualDiscounts = false;
    public bool $ruleAllowPromotionStacking = false;
    public bool $ruleAllowNegativeStock = false;
    public bool $ruleRequireCustomerForCreditSale = true;
    public bool $ruleShowLogo = true;

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $newLogo = null;

    public function mount(): void
    {
        $this->ensurePermission('sales.view');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function setStatusFilter(string $status): void
    {
        if ($status !== '' && ! in_array($status, ['draft', 'confirmed', 'partially_returned', 'returned', 'cancelled'], true)) {
            return;
        }

        $this->statusFilter = $status;
        $this->resetPage();
    }

    public function setSaleTypeFilter(string $type): void
    {
        if ($type !== '' && ! in_array($type, ['pos', 'credit'], true)) {
            return;
        }

        $this->saleTypeFilter = $type;
        $this->resetPage();
    }

    // Disparado desde PosPage (componente hermano dentro del mismo
    // SalesWorkspacePage) al guardar cualquier venta. Sin esto la tabla
    // quedaba con datos viejos hasta que el usuario recargaba la pagina a
    // mano: Ventas y POS son instancias Livewire separadas que no se
    // refrescan entre si solo por compartir la misma pagina.
    #[On('sale-saved')]
    public function refreshSalesList(): void
    {
        $this->resetPage();
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

        $this->ruleAllowAlternativePrices = (bool) $companySettings->get($company, 'pos', 'allow_alternative_prices');
        $this->ruleAllowManualDiscounts = (bool) $companySettings->get($company, 'pos', 'allow_manual_discounts');
        $this->ruleAllowPromotionStacking = (bool) $companySettings->get($company, 'pos', 'allow_promotion_stacking');
        $this->ruleAllowNegativeStock = (bool) $companySettings->get($company, 'pos', 'allow_negative_stock');
        $this->ruleRequireCustomerForCreditSale = (bool) $companySettings->get($company, 'pos', 'require_customer_for_credit_sale');

        // Sin logo cargado no hay nada que mostrar: forzamos el check apagado
        // aunque la empresa haya tenido "show_logo" en true de antes (p. ej.
        // si quito el logo en otra sesion sin desmarcar esto primero).
        $hasLogo = app(UpdateCompanyLogo::class)->currentUrl($company) !== null;
        $this->ruleShowLogo = $hasLogo && (bool) $companySettings->get($company, 'printing', 'show_logo');

        $this->resetErrorBag();
        $this->showRulesModal = true;
    }

    public function closeRulesModal(): void
    {
        $this->showRulesModal = false;
        $this->resetErrorBag();
    }

    public function saveRules(UpdateCompanySettings $updateCompanySettings, UpdateCompanyLogo $updateCompanyLogo): void
    {
        $this->ensurePermission('settings.manage');

        // El cliente no siempre nota que "Guardar logo" es un boton aparte
        // del formulario: si dejo un archivo elegido sin subir, el
        // "Guardar" grande tambien lo sube antes de guardar el resto.
        if ($this->newLogo) {
            $this->validate([
                'newLogo' => ['image', 'mimes:jpg,jpeg,png,gif,webp,bmp', 'max:4096'],
            ], [], ['newLogo' => 'logo']);

            try {
                $updateCompanyLogo->handle($this->currentCompany(), $this->newLogo);
                $this->newLogo = null;
            } catch (InvalidArgumentException $exception) {
                $this->addError('newLogo', $exception->getMessage());

                return;
            }
        }

        try {
            $updateCompanySettings->handle($this->currentCompany(), [
                'pos' => [
                    'allow_alternative_prices' => $this->ruleAllowAlternativePrices,
                    'allow_manual_discounts' => $this->ruleAllowManualDiscounts,
                    'allow_promotion_stacking' => $this->ruleAllowPromotionStacking,
                    'allow_negative_stock' => $this->ruleAllowNegativeStock,
                    'require_customer_for_credit_sale' => $this->ruleRequireCustomerForCreditSale,
                ],
                'printing' => [
                    'show_logo' => $this->ruleShowLogo,
                ],
            ], auth()->user());
        } catch (InvalidArgumentException $exception) {
            $this->toast($exception->getMessage(), 'error');

            return;
        }

        $this->showRulesModal = false;
        $this->toast('Reglas de ventas actualizadas correctamente.');
    }

    public function uploadLogo(UpdateCompanyLogo $action): void
    {
        $this->ensurePermission('settings.manage');

        $this->validate([
            'newLogo' => ['required', 'image', 'mimes:jpg,jpeg,png,gif,webp,bmp', 'max:4096'],
        ], [], ['newLogo' => 'logo']);

        try {
            $action->handle($this->currentCompany(), $this->newLogo);
        } catch (InvalidArgumentException $exception) {
            $this->addError('newLogo', $exception->getMessage());

            return;
        }

        $this->newLogo = null;
        $this->toast('Logo actualizado correctamente.');
    }

    public function removeLogo(UpdateCompanyLogo $action): void
    {
        $this->ensurePermission('settings.manage');

        $action->remove($this->currentCompany());

        // Sin logo, "mostrar logo en el ticket" deja de tener sentido.
        $this->ruleShowLogo = false;

        $this->toast('Logo eliminado.', 'info');
    }

    public function isLogoStorageConfigured(): bool
    {
        return app(UpdateCompanyLogo::class)->isStorageConfigured();
    }

    public function ruleFeatureGates(): array
    {
        $company = $this->currentCompany();
        $resolver = app(CompanyPlanResolver::class);

        return [
            'alternative_prices' => $resolver->hasFeature($company, 'products.multiple_prices'),
            'manual_discounts' => $resolver->hasFeature($company, 'pos.manual_discounts'),
            'promotion_stacking' => $resolver->hasModule($company, 'promotions'),
            'negative_stock' => $resolver->hasModule($company, 'inventory'),
            'credit_sale' => $resolver->hasModule($company, 'credit'),
        ];
    }

    public function sales(): LengthAwarePaginator
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

                // Un lector laser manda el guion de "BAS-000007" segun el
                // layout de teclado activo en el equipo, y en varios
                // layouts en espanol ese scancode termina siendo un
                // apostrofe ("BAS'000007") en vez de un guion — no es algo
                // que se pueda arreglar desde la app (el navegador ya
                // recibe el caracter que el SO tradujo). Lo que si se puede
                // hacer es comparar tambien ignorando todo lo que no sea
                // letra o numero, para que "BAS-000007" y "BAS'000007"
                // (o "BAS 000007", "BAS_000007", etc.) matcheen igual.
                $normalizedSearch = preg_replace('/[^A-Za-z0-9]/', '', $this->search);

                $query->where(function (Builder $nested) use ($search, $normalizedSearch) {
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

                    // regexp_replace es de Postgres (dev/prod); los tests
                    // corren en SQLite, que no lo tiene — sin este guard la
                    // suite se rompe aunque en produccion nunca pasa por sqlite.
                    if ($normalizedSearch !== '' && $nested->getConnection()->getDriverName() === 'pgsql') {
                        $nested->orWhereRaw(
                            "regexp_replace(document_number, '[^A-Za-z0-9]', '', 'g') ILIKE ?",
                            ['%' . $normalizedSearch . '%']
                        );
                    }
                });
            })
            ->orderByDesc('sold_at')
            ->orderByDesc('id')
            ->paginate($this->perPage);
    }

    public function render(): View
    {
        return view('livewire.sales.sales-page', [
            'sales' => $this->sales(),
            'ruleFeatureGates' => $this->canManageRules() ? $this->ruleFeatureGates() : [],
            'currentLogoUrl' => $this->canManageRules() ? app(UpdateCompanyLogo::class)->currentUrl($this->currentCompany()) : null,
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
