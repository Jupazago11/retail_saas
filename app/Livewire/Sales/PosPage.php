<?php

namespace App\Livewire\Sales;

use App\Actions\Sales\CreateFrozenSale;
use App\Actions\Sales\CreatePosSale;
use App\Actions\Sales\ModifySale;
use App\Actions\Sales\UpdateDraftPosSale;
use App\Enums\CashSessionStatus;
use App\Enums\FrozenSaleStatus;
use App\Enums\RecordStatus;
use App\Enums\SaleStatus;
use App\Livewire\Concerns\InteractsWithToast;
use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\Company;
use App\Models\Customer;
use App\Models\FrozenSale;
use App\Models\Person;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\Warehouse;
use App\Services\Plans\CompanyPlanResolver;
use App\Services\Settings\CompanySettings;
use App\Services\Sales\SalePreviewService;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Component;

class PosPage extends Component
{
    use InteractsWithToast;

    public ?int $branchId = null;
    public ?int $warehouseId = null;
    public ?int $cashRegisterId = null;
    public ?int $customerId = null;
    public ?int $quickProductId = null;
    public string $productLookup = '';
    public string $auditReference = '';
    public string $loyaltyPointsToRedeem = '';
    public string $saleType = 'pos';
    public string $saleStatus = SaleStatus::Confirmed->value;
    public string $soldAt = '';
    public string $notes = '';
    public array $items = [];
    public ?int $cashSessionId = null;
    public array $payments = [];
    public int $nextLineKey = 0;
    public ?int $lastCreatedSaleId = null;
    public ?int $editingSaleId = null;
    public ?int $modifyingSaleId = null;
    public ?int $resumingFrozenSaleId = null;
    public bool $showFrozenModal = false;
    public array $frozenSalesForModal = [];
    public bool $showPaymentModal = false;
    public string $paymentCustomerDocument = '';
    public ?int $resolvedCustomerId = null;
    public ?string $resolvedCustomerName = null;
    protected ?array $previewCache = null;

    public function mount(): void
    {
        $this->ensurePermission('sales.create');
        $this->resetSaleForm();

        $editingSaleId = request()->integer('edit');

        if ($editingSaleId > 0) {
            $this->loadDraftSaleForEditing($editingSaleId);
        }

        $modifyingSaleId = request()->integer('modify');

        if ($modifyingSaleId > 0) {
            $this->loadSaleForModification($modifyingSaleId);
        }
    }

    public function updatedBranchId($value): void
    {
        $value = $value ? (int) $value : null;

        $warehouseIds = $this->warehouses()
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $cashRegisterIds = $this->cashRegisters()
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $cashSessionIds = $this->openCashSessions()
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($value === null || ! in_array((int) $this->warehouseId, $warehouseIds, true)) {
            $this->warehouseId = $warehouseIds[0] ?? null;
        }

        if ($value === null || ! in_array((int) $this->cashRegisterId, $cashRegisterIds, true)) {
            $this->cashRegisterId = $cashRegisterIds[0] ?? null;
        }

        if ($value === null || ! in_array((int) $this->cashSessionId, $cashSessionIds, true)) {
            $this->cashSessionId = $cashSessionIds[0] ?? null;
        }
    }

    public function updatedCashRegisterId($value): void
    {
        $value = $value ? (int) $value : null;
        $cashSessionIds = $this->openCashSessions()
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($value === null || ! in_array((int) $this->cashSessionId, $cashSessionIds, true)) {
            $this->cashSessionId = $cashSessionIds[0] ?? null;
        }
    }

    public function updatedCustomerId($value): void
    {
        if (! $this->canRedeemLoyalty()) {
            $this->loyaltyPointsToRedeem = '';
        }
    }

    public function updatedSaleStatus($value): void
    {
        if ((string) $value !== SaleStatus::Confirmed->value) {
            $this->loyaltyPointsToRedeem = '';
        }
    }

    public function selectPaymentCustomer(int $customerId): void
    {
        $customer = $this->customers()->firstWhere('id', $customerId);
        if (! $customer) {
            return;
        }
        $this->resolvedCustomerId = $customer->id;
        $this->resolvedCustomerName = $customer->person?->full_name ?? 'Cliente #'.$customer->id;
        $this->paymentCustomerDocument = '';
    }

    public function clearPaymentCustomer(): void
    {
        $this->resolvedCustomerId = null;
        $this->resolvedCustomerName = null;
        $this->paymentCustomerDocument = '';
    }

    public function setNewPaymentCustomerDoc(string $doc): void
    {
        $doc = trim($doc);
        if ($doc === '') {
            return;
        }
        $this->paymentCustomerDocument = $doc;
        $this->resolvedCustomerId = null;
        $this->resolvedCustomerName = null;
    }

    public function updatedItems($value, string $key): void
    {
        $segments = explode('.', $key);

        if (count($segments) < 2) {
            return;
        }

        $index = (int) $segments[0];
        $field = $segments[1];

        if (! isset($this->items[$index])) {
            return;
        }

        if ($field === 'product_id') {
            $product = $this->products()->firstWhere('id', (int) $value);

            $this->items[$index]['product_presentation_id'] = '';
            $this->items[$index]['product_variant_id'] = '';
            $this->items[$index]['price_tier'] = $this->defaultPriceTierForProduct($product);
            $this->items[$index]['unit_price'] = $product
                ? $this->resolvePriceForTier($product, null, $this->items[$index]['price_tier'])
                : '0';
        }

        if ($field === 'product_presentation_id') {
            $product = $this->products()->firstWhere('id', (int) ($this->items[$index]['product_id'] ?? 0));
            $presentation = $product?->presentations?->firstWhere('id', (int) $value);
            $tier = $this->items[$index]['price_tier'] ?? $this->defaultPriceTierForProduct($product);

            $this->items[$index]['price_tier'] = $tier;
            $this->items[$index]['unit_price'] = $product
                ? $this->resolvePriceForTier($product, $presentation, $tier)
                : '0';
        }
    }

    public function addItemLine(): void
    {
        $this->items[] = $this->newItemLine();
    }

    public function addQuickProductLine(): void
    {
        if (! $this->quickProductId) {
            return;
        }

        $product = $this->products()->firstWhere('id', (int) $this->quickProductId);

        if (! $product) {
            return;
        }

        $existingIndex = collect($this->items)->search(function (array $item) use ($product) {
            return (int) ($item['product_id'] ?? 0) === $product->id
                && blank($item['product_presentation_id'] ?? null)
                && blank($item['product_variant_id'] ?? null);
        });

        if ($existingIndex !== false) {
            $current = (string) ($this->items[$existingIndex]['quantity'] ?? '1');
            if (! preg_match('/^-?\d+(?:\.\d+)?$/', $current)) {
                $current = '1';
            }
            $newQuantity = rtrim(rtrim(bcadd($current, '1', 6), '0'), '.');
            $this->items[$existingIndex]['quantity'] = $newQuantity;
            $this->quickProductId = null;
            $this->productLookup = '';

            $this->toast("{$product->name}: cantidad actualizada a {$newQuantity}.", 'success');

            return;
        }

        $line = $this->newItemLine();
        $line['product_id'] = (string) $product->id;
        $line['price_tier'] = $this->defaultPriceTierForProduct($product);
        $line['unit_price'] = $this->resolvePriceForTier($product, null, $line['price_tier']);

        $emptyLineIndex = collect($this->items)
            ->search(fn (array $item) => blank($item['product_id'] ?? null));

        if ($emptyLineIndex !== false) {
            $line['_key'] = $this->items[$emptyLineIndex]['_key'];
            $this->items[$emptyLineIndex] = $line;
        } else {
            $this->items[] = $line;
        }

        $this->quickProductId = null;
        $this->productLookup = '';
    }

    public function submitProductLookup(?string $lookup = null): void
    {
        if ($lookup !== null) {
            $this->productLookup = trim($lookup);
        }

        $product = $this->lookupResolvedProduct();

        if (! $product) {
            return;
        }

        $this->addProductById($product->id);
    }

    public function freezeCurrentSale(CreateFrozenSale $createFrozenSale): void
    {
        if (! $this->canFreezeCurrentSale()) {
            return;
        }

        $items = $this->nonEmptyItems();

        if ($items === []) {
            $this->toast('Debes agregar al menos un producto antes de congelar la venta.', 'error');

            return;
        }

        try {
            $createPosSale = $createFrozenSale->handle($this->currentCompany(), [
                'branch_id' => $this->branchId,
                'warehouse_id' => $this->warehouseId,
                'cash_register_id' => $this->cashRegisterId,
                'customer_id' => $this->customerId,
                'created_by' => auth()->id(),
                'label' => 'Congelada '.$this->auditReference,
                'sale_type' => 'pos',
                'notes' => $this->blankToNull($this->notes),
                'items' => $items,
            ]);
        } catch (InvalidArgumentException $exception) {
            $this->toast($exception->getMessage(), 'error');

            return;
        }

        // Si habia una congelada retomada, queda reemplazada por la nueva
        $previousFrozenSaleId = $this->resumingFrozenSaleId;
        $this->resetSaleForm();

        if ($previousFrozenSaleId) {
            FrozenSale::find($previousFrozenSaleId)?->update(['status' => FrozenSaleStatus::Cancelled->value]);
        }

        $this->toast('Venta congelada correctamente: '.$createPosSale->label);
    }

    public function openPaymentModal(): void
    {
        $this->ensurePermission('sales.create');

        $itemCount = count($this->nonEmptyItems());
        $this->dispatch('pos-debug', stage: 'openPaymentModal.called', details: [
            'itemCount' => $itemCount,
            'customerId' => $this->customerId,
        ]);

        if ($itemCount === 0) {
            $this->toast('Agrega al menos un producto antes de cobrar.', 'error');
            $this->dispatch('pos-debug', stage: 'openPaymentModal.blocked', details: ['reason' => 'no items']);

            return;
        }

        $this->paymentCustomerDocument = '';
        $this->resolvedCustomerId = null;
        $this->resolvedCustomerName = null;
        $this->showPaymentModal = true;

        $this->dispatch('pos-debug', stage: 'openPaymentModal.done', details: [
            'showPaymentModal' => $this->showPaymentModal,
        ]);
    }

    public function closePaymentModal(): void
    {
        $this->showPaymentModal = false;
        $this->dispatch('pos-debug', stage: 'closePaymentModal', details: []);
    }

    public function canViewFrozenSales(): bool
    {
        return app(CompanyPlanResolver::class)->hasFeature($this->currentCompany(), 'pos.frozen_sales')
            && (auth()->user()?->hasCurrentCompanyPermission('sales.freeze') ?? false);
    }

    public function openFrozenSalesModal(): void
    {
        if (! $this->canViewFrozenSales()) {
            return;
        }

        $this->frozenSalesForModal = FrozenSale::query()
            ->where('company_id', $this->currentCompany()->id)
            ->where('status', FrozenSaleStatus::Open->value)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->when($this->resumingFrozenSaleId, fn ($q) => $q->where('id', '!=', $this->resumingFrozenSaleId))
            ->with('creator')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (FrozenSale $fs) => [
                'id' => $fs->id,
                'label' => $fs->label,
                'created_at' => $fs->created_at?->format('d/m/Y H:i') ?? '—',
                'creator_name' => $fs->creator?->name ?? '—',
                'items_count' => count($fs->payload_snapshot['items'] ?? []),
            ])
            ->all();

        $this->showFrozenModal = true;
    }

    public function closeFrozenSalesModal(): void
    {
        $this->showFrozenModal = false;
        $this->frozenSalesForModal = [];
    }

    public function resumeFrozenSale(int $frozenSaleId, CreateFrozenSale $createFrozenSale): void
    {
        $this->ensurePermission('sales.create');

        $company = $this->currentCompany();

        $frozenSale = FrozenSale::query()
            ->where('company_id', $company->id)
            ->where('status', FrozenSaleStatus::Open->value)
            ->find($frozenSaleId);

        if (! $frozenSale) {
            $this->toast('La venta congelada no esta disponible.', 'warning');
            $this->closeFrozenSalesModal();

            return;
        }

        if ($frozenSale->expires_at !== null && $frozenSale->expires_at->isPast()) {
            $this->toast('La venta congelada ya expiro.', 'warning');
            $this->closeFrozenSalesModal();

            return;
        }

        $currentItems = $this->nonEmptyItems();
        $previousFrozenSaleId = $this->resumingFrozenSaleId;

        if ($currentItems !== [] && $this->canFreezeCurrentSale()) {
            try {
                $auto = $createFrozenSale->handle($company, [
                    'branch_id' => $this->branchId,
                    'warehouse_id' => $this->warehouseId,
                    'cash_register_id' => $this->cashRegisterId,
                    'customer_id' => $this->customerId,
                    'created_by' => auth()->id(),
                    'label' => 'Auto-congelada ' . $this->auditReference,
                    'sale_type' => 'pos',
                    'notes' => $this->blankToNull($this->notes),
                    'items' => $currentItems,
                ]);
                // La congelada anterior queda reemplazada por esta nueva
                if ($previousFrozenSaleId) {
                    FrozenSale::find($previousFrozenSaleId)?->update(['status' => FrozenSaleStatus::Cancelled->value]);
                }
                $this->toast('Venta actual congelada: ' . $auto->label);
            } catch (InvalidArgumentException $exception) {
                // No se pudo auto-congelar, continuamos de todas formas
            }
        }

        $payload = $frozenSale->payload_snapshot;
        $label = $frozenSale->label;

        // NO se cancela aqui: la venta congelada permanece Open.
        // Se cancela/convierte solo cuando el usuario confirme la venta o la vuelva a congelar.

        $this->resetSaleForm();  // limpia resumingFrozenSaleId

        $this->resumingFrozenSaleId = $frozenSale->id;
        $this->branchId = $frozenSale->branch_id;
        $this->warehouseId = $frozenSale->warehouse_id;
        $this->cashRegisterId = $frozenSale->cash_register_id;
        $this->customerId = $frozenSale->customer_id;
        $this->notes = (string) ($payload['notes'] ?? '');
        $this->items = collect($payload['items'] ?? [])
            ->map(fn (array $item) => array_merge($this->newItemLine(), [
                'product_id' => (string) ($item['product_id'] ?? ''),
                'product_presentation_id' => isset($item['product_presentation_id']) && $item['product_presentation_id'] !== null
                    ? (string) $item['product_presentation_id'] : '',
                'product_variant_id' => isset($item['product_variant_id']) && $item['product_variant_id'] !== null
                    ? (string) $item['product_variant_id'] : '',
                'quantity' => rtrim(rtrim((string) ($item['quantity'] ?? '1'), '0'), '.'),
                'unit_price' => (string) ($item['unit_price'] ?? '0.00'),
                'discount_amount' => (string) ($item['discount_amount'] ?? '0.00'),
                'tax_rate' => (string) ($item['tax_rate'] ?? '0'),
                'price_tier' => $this->detectPriceTierFromSnapshot($item),
            ]))
            ->values()
            ->all();

        if ($this->items === []) {
            $this->items = [$this->newItemLine()];
        }

        $this->closeFrozenSalesModal();
        $this->toast('Retomando: ' . $label);
    }

    public function switchPriceTier(int $lineKey, string $tier): void
    {
        if (! in_array($tier, ['price_1', 'price_2', 'price_3'], true)) {
            return;
        }

        foreach ($this->items as $index => $item) {
            if ((int) ($item['_key'] ?? 0) !== $lineKey) {
                continue;
            }

            $product = $this->products()->firstWhere('id', (int) ($item['product_id'] ?? 0));

            if (! $product) {
                return;
            }

            $presentation = $product->presentations?->firstWhere('id', (int) ($item['product_presentation_id'] ?? 0));
            $availableTiers = $this->priceTierOptions($product, $presentation);

            if (! array_key_exists($tier, $availableTiers) || count($availableTiers) === 1) {
                return;
            }

            $this->items[$index]['price_tier'] = $tier;
            $this->items[$index]['unit_price'] = $this->resolvePriceForTier($product, $presentation, $tier);

            return;
        }
    }

    public function cyclePriceTier(int $lineKey): void
    {
        foreach ($this->items as $index => $item) {
            if ((int) ($item['_key'] ?? 0) !== $lineKey) {
                continue;
            }

            $product = $this->products()->firstWhere('id', (int) ($item['product_id'] ?? 0));

            if (! $product) {
                return;
            }

            $presentation = $product->presentations?->firstWhere('id', (int) ($item['product_presentation_id'] ?? 0));
            $availableTiers = array_keys($this->priceTierOptions($product, $presentation));

            if (count($availableTiers) <= 1) {
                return;
            }

            $currentTier = $item['price_tier'] ?? $availableTiers[0];
            $currentIndex = array_search($currentTier, $availableTiers, true);
            $nextIndex = $currentIndex === false ? 0 : (($currentIndex + 1) % count($availableTiers));
            $nextTier = $availableTiers[$nextIndex];

            $this->items[$index]['price_tier'] = $nextTier;
            $this->items[$index]['unit_price'] = $this->resolvePriceForTier($product, $presentation, $nextTier);

            return;
        }
    }

    public function addProductById(int $productId): void
    {
        logger()->debug('[pos] addProductById recibido', [
            'product_id' => $productId,
            'company_id' => $this->currentCompany()->id,
            'user_id' => auth()->id(),
        ]);
        $this->dispatch('pos-debug', stage: 'server.add-product.received', details: [
            'productId' => $productId,
        ]);

        $this->quickProductId = $productId;
        $this->addQuickProductLine();

        $selectedProductId = collect($this->items)
            ->pluck('product_id')
            ->map(fn ($id) => (int) $id)
            ->first(fn (int $id) => $id === $productId);

        logger()->debug('[pos] addProductById completado', [
            'product_id' => $productId,
            'selected' => $selectedProductId !== null,
            'items_count' => count($this->nonEmptyItems()),
        ]);
        $this->dispatch('pos-debug', stage: 'server.add-product.completed', details: [
            'productId' => $productId,
            'selected' => $selectedProductId !== null,
            'itemsCount' => count($this->nonEmptyItems()),
        ]);
        $this->dispatch('pos-product-selected', productId: $productId);
    }

    public function increaseQuantity(int $lineKey): void
    {
        $this->adjustQuantity($lineKey, '1');
    }

    public function decreaseQuantity(int $lineKey): void
    {
        $this->adjustQuantity($lineKey, '-1');
    }

    public function addPaymentLine(): void
    {
        $this->payments[] = $this->newPaymentLine();
    }

    public function removeItemLine(int $lineKey): void
    {
        $this->items = collect($this->items)
            ->reject(fn (array $item) => (int) ($item['_key'] ?? -1) === $lineKey)
            ->values()
            ->all();

        if ($this->items === []) {
            $this->items[] = $this->newItemLine();
        }
    }

    public function removePaymentLine(int $lineKey): void
    {
        $this->payments = collect($this->payments)
            ->reject(fn (array $payment) => (int) ($payment['_key'] ?? -1) === $lineKey)
            ->values()
            ->all();

        if ($this->payments === []) {
            $this->payments[] = $this->newPaymentLine();
        }
    }

    public function saveSale(): void
    {
        $this->ensurePermission('sales.create');

        $this->dispatch('pos-debug', stage: 'saveSale.called', details: [
            'paymentCustomerDocument' => $this->paymentCustomerDocument,
            'customerId' => $this->customerId,
            'payments' => $this->payments,
            'itemCount' => count($this->nonEmptyItems()),
        ]);

        if (! $this->canCreateSales()) {
            $this->toast($this->salesRequirementsMessage(), 'error');

            return;
        }

        $company = $this->currentCompany();

        // Resolver o crear cliente desde el documento ingresado en el modal
        $doc = trim($this->paymentCustomerDocument);
        if ($doc !== '') {
            try {
                $this->customerId = $this->resolvedCustomerId
                    ?? $this->resolveOrCreateCustomer($doc, $company);
            } catch (\Throwable $e) {
                $this->toast('No se pudo registrar el cliente: '.$e->getMessage(), 'error');

                return;
            }
        }

        // Validacion de limite de credito
        $creditTotal = collect($this->payments)
            ->filter(fn ($p) => ($p['payment_method_code'] ?? '') === 'credit')
            ->reduce(fn ($carry, $p) => bcadd($carry, is_numeric($p['amount'] ?? '') ? (string) $p['amount'] : '0', 2), '0.00');

        if (bccomp($creditTotal, '0', 2) > 0) {
            $creditCid = $this->customerId ?? $this->resolvedCustomerId;
            if (! $creditCid) {
                $this->toast('Para pagar con crédito debes seleccionar un cliente.', 'error');
                return;
            }
            $creditCustomer = Customer::with('creditAccount')->find($creditCid);
            if (! $creditCustomer?->credit_enabled) {
                $this->toast('Este cliente no tiene crédito habilitado.', 'error');
                return;
            }
            if (! $creditCustomer->creditAccount) {
                $this->toast('Este cliente no tiene cuenta de crédito configurada.', 'error');
                return;
            }
            if (bccomp($creditTotal, (string) $creditCustomer->creditAccount->available_credit, 2) > 0) {
                $avail = number_format((float) $creditCustomer->creditAccount->available_credit, 0, '.', '.');
                $this->toast("Crédito insuficiente. Disponible: {$avail}.", 'error');
                return;
            }
        }

        $createPosSale = app(CreatePosSale::class);

        try {
            $validated = $this->validate([
            'branchId' => [
                'required',
                Rule::exists('branches', 'id')->where(fn ($query) => $query->where('company_id', $company->id)->whereNull('deleted_at')),
            ],
            'warehouseId' => [
                'required',
                Rule::exists('warehouses', 'id')->where(fn ($query) => $query
                    ->where('company_id', $company->id)
                    ->where('branch_id', $this->branchId)
                    ->whereNull('deleted_at')),
            ],
            'cashRegisterId' => [
                'nullable',
                Rule::exists('cash_registers', 'id')->where(fn ($query) => $query
                    ->where('company_id', $company->id)
                    ->where('branch_id', $this->branchId)
                    ->whereNull('deleted_at')),
            ],
            'customerId' => [
                'nullable',
                Rule::exists('customers', 'id')->where(fn ($query) => $query->where('company_id', $company->id)),
            ],
            'loyaltyPointsToRedeem' => ['nullable', 'numeric', 'gte:0'],
            'saleStatus' => ['required', Rule::in([
                SaleStatus::Draft->value,
                SaleStatus::Confirmed->value,
            ])],
            'soldAt' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'cashSessionId' => [
                'nullable',
                Rule::exists('cash_sessions', 'id')->where(fn ($query) => $query
                    ->where('company_id', $company->id)
                    ->where('branch_id', $this->branchId)
                    ->where('status', CashSessionStatus::Open->value)
                    ->when($this->cashRegisterId, fn ($cashSessionQuery) => $cashSessionQuery
                        ->where('cash_register_id', $this->cashRegisterId))),
            ],
            'payments' => [Rule::requiredIf($this->requiresImmediatePayments()), 'array'],
            'payments.*.payment_method_code' => [
                Rule::requiredIf($this->requiresImmediatePayments()),
                'nullable',
                'string',
                'max:50',
            ],
            'payments.*.amount' => ['nullable', 'numeric', 'gt:0'],
            'payments.*.reference' => ['nullable', 'string', 'max:120'],
            'items.*.product_id' => [
                'required',
                Rule::exists('products', 'id')->where(fn ($query) => $query->where('company_id', $company->id)->whereNull('deleted_at')),
            ],
            'items.*.product_presentation_id' => [
                'nullable',
                Rule::exists('product_presentations', 'id')->where(fn ($query) => $query->where('company_id', $company->id)->whereNull('deleted_at')),
            ],
            'items.*.product_variant_id' => [
                'nullable',
                Rule::exists('product_variants', 'id')->where(fn ($query) => $query->where('company_id', $company->id)),
            ],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'min:0'],
        ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->toast(collect($e->errors())->flatten()->first() ?? 'Revisa los datos de la venta antes de confirmar.', 'error');

            return;
        }

        $payload = [
            'branch_id' => (int) $validated['branchId'],
            'warehouse_id' => (int) $validated['warehouseId'],
            'cash_register_id' => $validated['cashRegisterId'] ? (int) $validated['cashRegisterId'] : null,
            'customer_id' => $validated['customerId'] ? (int) $validated['customerId'] : null,
            'loyalty_points_to_redeem' => $this->canRedeemLoyalty()
                ? $this->blankToNull($validated['loyaltyPointsToRedeem'] ?? null)
                : null,
            'sale_type' => 'pos',
            'status' => $validated['saleStatus'],
            'sold_at' => $this->normalizeDateTime($validated['soldAt'] ?? null),
            'notes' => $this->blankToNull($validated['notes']),
            'user_id' => auth()->id(),
            'items' => collect($validated['items'])
                ->map(fn (array $item) => [
                    'product_id' => (int) $item['product_id'],
                    'product_presentation_id' => $item['product_presentation_id'] ? (int) $item['product_presentation_id'] : null,
                    'product_variant_id' => $item['product_variant_id'] ? (int) $item['product_variant_id'] : null,
                    'quantity' => (string) $item['quantity'],
                    'unit_price' => (string) $item['unit_price'],
                    'discount_amount' => $item['discount_amount'] !== null && $item['discount_amount'] !== '' ? (string) $item['discount_amount'] : '0',
                    'tax_rate' => $item['tax_rate'] !== null && $item['tax_rate'] !== '' ? (string) $item['tax_rate'] : '0',
                ])
                ->all(),
        ];

        try {
            $paymentPayload = [
                'require_immediate_payment' => $this->requiresImmediatePayments(),
                'cash_session_id' => $validated['cashSessionId'] ? (int) $validated['cashSessionId'] : null,
                'received_by' => auth()->id(),
                'payments' => collect($validated['payments'] ?? [])
                    ->map(fn (array $payment) => [
                        'payment_method_code' => trim((string) $payment['payment_method_code']),
                        'amount' => $this->blankToNull($payment['amount'] ?? null),
                        'reference' => $this->blankToNull($payment['reference'] ?? null),
                    ])
                    ->all(),
            ];

            if ($this->editingSaleId) {
                $draftSale = $this->salesQuery()->with(['items', 'payments', 'creditMovements', 'customer.loyaltyAccount'])->findOrFail($this->editingSaleId);
                $updateDraftPosSale = app(UpdateDraftPosSale::class);
                $sale = $updateDraftPosSale->handle($company, $draftSale, $payload, $paymentPayload);
            } elseif ($this->modifyingSaleId) {
                $originalSale = $this->salesQuery()->findOrFail($this->modifyingSaleId);
                $sale = app(ModifySale::class)->handle($company, $originalSale, $payload, $paymentPayload);
            } else {
                $sale = $createPosSale->handle($company, $payload, $paymentPayload);
            }
        } catch (InvalidArgumentException $exception) {
            $this->toast($exception->getMessage(), 'error');

            return;
        }

        $this->lastCreatedSaleId = $sale->id;
        $wasEditing = $this->editingSaleId !== null;
        $wasModifying = $this->modifyingSaleId !== null;
        $previousFrozenSaleId = $this->resumingFrozenSaleId;
        $this->resetSaleForm();
        $this->lastCreatedSaleId = $sale->id;

        if ($previousFrozenSaleId) {
            FrozenSale::find($previousFrozenSaleId)?->update([
                'status' => FrozenSaleStatus::Converted->value,
                'converted_sale_id' => $sale->id,
            ]);
        }

        if ($wasModifying) {
            $this->toast('Venta modificada correctamente: '.$sale->document_number.'. La venta original quedo anulada.');

            return;
        }

        $this->toast(
            $sale->status === SaleStatus::Confirmed->value
                ? ($wasEditing
                    ? 'Borrador confirmado correctamente: '.$sale->document_number.'.'
                    : 'Venta confirmada correctamente: '.$sale->document_number.'.')
                : ($wasEditing ? 'Borrador actualizado correctamente.' : 'Venta en borrador guardada correctamente.')
        );
    }

    public function loadDraftSaleForEditing(int $saleId): void
    {
        $this->ensurePermission('sales.create');

        $sale = $this->salesQuery()
            ->with(['items'])
            ->findOrFail($saleId);

        if ($sale->status !== SaleStatus::Draft->value || $sale->posted_to_inventory_at !== null) {
            $this->toast('Solo se pueden editar ventas en borrador.', 'warning');

            return;
        }

        $this->editingSaleId = $sale->id;
        $this->branchId = $sale->branch_id;
        $this->warehouseId = $sale->warehouse_id;
        $this->cashRegisterId = $sale->cash_register_id;
        $this->customerId = $sale->customer_id;
        $this->auditReference = $sale->document_number ?: ('BORRADOR '.$sale->id);
        $this->loyaltyPointsToRedeem = '';
        $this->saleType = 'pos';
        $this->saleStatus = $sale->status;
        $this->soldAt = $sale->sold_at?->format('Y-m-d\TH:i') ?? '';
        $this->notes = $sale->notes ?? '';
        $this->items = $this->mapSaleItemsForCart($sale->items);
        $this->cashSessionId = $this->openCashSessions()->first()?->id;
        $this->payments = [$this->newPaymentLine()];
        $this->resetValidation();
    }

    public function loadSaleForModification(int $saleId): void
    {
        $this->ensurePermission('sales.create');

        $sale = $this->salesQuery()
            ->with(['items'])
            ->findOrFail($saleId);

        if ($sale->status !== SaleStatus::Confirmed->value || $sale->sale_type !== 'pos') {
            $this->toast('Solo se pueden modificar ventas POS confirmadas.', 'warning');

            return;
        }

        $this->modifyingSaleId = $sale->id;
        $this->branchId = $sale->branch_id;
        $this->warehouseId = $sale->warehouse_id;
        $this->cashRegisterId = $sale->cash_register_id;
        $this->customerId = $sale->customer_id;
        $this->auditReference = 'MODIFICA '.($sale->document_number ?: $sale->id);
        $this->loyaltyPointsToRedeem = '';
        $this->saleType = 'pos';
        $this->saleStatus = SaleStatus::Confirmed->value;
        $this->soldAt = now()->format('Y-m-d\TH:i');
        $this->notes = $sale->notes ?? '';
        $this->items = $this->mapSaleItemsForCart($sale->items);
        $this->cashSessionId = $this->openCashSessions()->first()?->id;
        $this->payments = [$this->newPaymentLine()];
        $this->resetValidation();
    }

    protected function mapSaleItemsForCart($items): array
    {
        return $items
            ->map(fn ($item) => [
                '_key' => ++$this->nextLineKey,
                'product_id' => (string) $item->product_id,
                'product_presentation_id' => $item->product_presentation_id ? (string) $item->product_presentation_id : '',
                'product_variant_id' => $item->product_variant_id ? (string) $item->product_variant_id : '',
                'quantity' => rtrim(rtrim((string) $item->quantity, '0'), '.'),
                'unit_price' => (string) $item->unit_price,
                'discount_amount' => (string) $item->discount_amount,
                'tax_rate' => (string) $item->tax_rate,
                'price_tier' => $this->detectPriceTierForLoadedItem($item),
            ])
            ->values()
            ->all();
    }

    public function branches(): Collection
    {
        return Branch::query()
            ->where('company_id', $this->currentCompany()->id)
            ->where('status', RecordStatus::Active->value)
            ->whereNull('deleted_at')
            ->orderByDesc('is_primary')
            ->orderBy('name')
            ->get();
    }

    public function warehouses(): Collection
    {
        if (! $this->branchId) {
            return new Collection();
        }

        return Warehouse::query()
            ->where('company_id', $this->currentCompany()->id)
            ->where('branch_id', $this->branchId)
            ->where('status', RecordStatus::Active->value)
            ->whereNull('deleted_at')
            ->orderByDesc('is_primary')
            ->orderBy('name')
            ->get();
    }

    public function cashRegisters(): Collection
    {
        if (! $this->branchId) {
            return new Collection();
        }

        return CashRegister::query()
            ->where('company_id', $this->currentCompany()->id)
            ->where('branch_id', $this->branchId)
            ->where('status', RecordStatus::Active->value)
            ->whereNull('deleted_at')
            ->orderByDesc('is_primary')
            ->orderBy('name')
            ->get();
    }

    public function openCashSessions(): Collection
    {
        if (! $this->branchId) {
            return new Collection();
        }

        return CashSession::query()
            ->where('company_id', $this->currentCompany()->id)
            ->where('branch_id', $this->branchId)
            ->where('status', CashSessionStatus::Open->value)
            ->when($this->cashRegisterId, fn ($query) => $query->where('cash_register_id', $this->cashRegisterId))
            ->with(['cashRegister', 'opener'])
            ->orderByDesc('opened_at')
            ->get();
    }

    public function customers(): Collection
    {
        return Customer::query()
            ->where('company_id', $this->currentCompany()->id)
            ->where('status', RecordStatus::Active->value)
            ->with(['person', 'creditAccount', 'loyaltyAccount'])
            ->orderByDesc('id')
            ->get();
    }

    public function products(): Collection
    {
        return Product::query()
            ->where('company_id', $this->currentCompany()->id)
            ->where('status', RecordStatus::Active->value)
            ->whereNull('deleted_at')
            ->with([
                'presentations' => fn ($query) => $query
                    ->where('status', RecordStatus::Active->value)
                    ->whereNull('deleted_at')
                    ->orderBy('name'),
                'variants' => fn ($query) => $query
                    ->where('status', RecordStatus::Active->value)
                    ->with(['attributeValues.attribute'])
                    ->orderBy('id'),
            ])
            ->orderBy('name')
            ->get();
    }

    public function filteredProducts(): Collection
    {
        $products = $this->products();
        $term = mb_strtolower(trim($this->productLookup));

        if ($term === '') {
            return new Collection();
        }

        return $products
            ->filter(function (Product $product) use ($term) {
                $haystack = mb_strtolower(implode(' ', array_filter([
                    $product->name,
                    $product->sku,
                    $product->barcode,
                ])));

                return str_contains($haystack, $term);
            })
            ->sortBy(function (Product $product) use ($term) {
                $name = mb_strtolower((string) $product->name);
                $sku = mb_strtolower((string) $product->sku);
                $barcode = mb_strtolower((string) $product->barcode);

                return match (true) {
                    $barcode === $term => 0,
                    $sku === $term => 1,
                    $name === $term => 2,
                    str_starts_with($barcode, $term) => 3,
                    str_starts_with($sku, $term) => 4,
                    str_starts_with($name, $term) => 5,
                    default => 6,
                };
            })
            ->take(8)
            ->values();
    }

    public function latestSale(): ?Sale
    {
        if (! $this->lastCreatedSaleId) {
            return null;
        }

        return Sale::query()
            ->where('company_id', $this->currentCompany()->id)
            ->with(['customer.person', 'payments'])
            ->find($this->lastCreatedSaleId);
    }

    public function canFreezeCurrentSale(): bool
    {
        // Modificando una venta ya confirmada no hay nada que congelar: o se
        // confirma el cambio o se cancela y la venta original queda como
        // estaba. Congelar dejaria un carrito huerfano sin relacion con la
        // venta que se estaba modificando.
        return $this->modifyingSaleId === null
            && app(CompanyPlanResolver::class)->hasFeature($this->currentCompany(), 'pos.frozen_sales')
            && (auth()->user()?->hasCurrentCompanyPermission('sales.freeze') ?? false)
            && $this->canCreateSales();
    }

    public function canCreateSales(): bool
    {
        return $this->branches()->isNotEmpty()
            && $this->warehouses()->isNotEmpty()
            && $this->products()->isNotEmpty();
    }

    public function salesRequirementsMessage(): ?string
    {
        $missing = [];

        if ($this->branches()->isEmpty()) {
            $missing[] = 'una sucursal activa';
        }

        if ($this->warehouses()->isEmpty()) {
            $missing[] = 'una bodega activa';
        }

        if ($this->products()->isEmpty()) {
            $missing[] = 'un producto activo';
        }

        if ($missing === []) {
            return null;
        }

        return 'Debes tener al menos '.implode(', ', $missing).' para usar el POS.';
    }

    public function requiresImmediatePayments(): bool
    {
        return $this->saleStatus === SaleStatus::Confirmed->value;
    }

    public function paymentMethodOptions(): array
    {
        return [
            'cash'     => 'Efectivo',
            'card'     => 'Tarjeta',
            'transfer' => 'Transferencia',
            'credit'   => 'Credito',
        ];
    }

    public function selectedCustomer(): ?Customer
    {
        if (! $this->customerId) {
            return null;
        }

        return $this->customers()->firstWhere('id', $this->customerId);
    }

    public function canRedeemLoyalty(): bool
    {
        $customer = $this->selectedCustomer();

        return $this->saleStatus === SaleStatus::Confirmed->value
            && app(CompanyPlanResolver::class)->hasFeature($this->currentCompany(), 'loyalty.enabled')
            && (bool) app(CompanySettings::class)->get($this->currentCompany(), 'loyalty', 'loyalty_enabled')
            && $customer !== null
            && $customer->loyalty_enabled
            && $customer->loyaltyAccount !== null;
    }

    public function loyaltyRuleType(): string
    {
        return (string) app(CompanySettings::class)->get($this->currentCompany(), 'loyalty', 'points_rule_type');
    }

    public function loyaltyRate(): string
    {
        return (string) app(CompanySettings::class)->get($this->currentCompany(), 'loyalty', 'points_rate');
    }

    public function loyaltyCashEquivalentPreview(): string
    {
        if (! $this->canRedeemLoyalty()) {
            return '0.00';
        }

        $points = $this->blankToNull($this->loyaltyPointsToRedeem);

        if ($points === null || ! is_numeric($points)) {
            return '0.00';
        }

        return match ($this->loyaltyRuleType()) {
            'per_currency' => bcdiv(bcadd($points, '0', 4), $this->loyaltyRate(), 2),
            default => '0.00',
        };
    }

    public function previewData(): array
    {
        return $this->previewCache ??= app(SalePreviewService::class)->preview($this->currentCompany(), [
            'customer_id' => $this->customerId,
            'sold_at' => $this->normalizeDateTime($this->soldAt),
            'loyalty_points_to_redeem' => $this->blankToNull($this->loyaltyPointsToRedeem),
            'items' => $this->items,
        ]);
    }

    public function enteredPaymentsTotal(): string
    {
        return collect($this->payments)
            ->reduce(function (string $carry, array $payment) {
                $amount = $this->blankToNull($payment['amount'] ?? null);

                if ($amount === null || ! preg_match('/^-?\d+(?:\.\d+)?$/', $amount)) {
                    return $carry;
                }

                return bcadd($carry, bcadd($amount, '0', 2), 2);
            }, '0.00');
    }

    public function remainingToCollect(): string
    {
        $previewTotal = (string) data_get($this->previewData(), 'totals.grand_total', '0.00');

        return bcsub($previewTotal, $this->enteredPaymentsTotal(), 2);
    }

    public function creditDueDatePreview(): ?string
    {
        return now()
            ->addDays((int) app(CompanySettings::class)->get($this->currentCompany(), 'credit', 'default_term_days'))
            ->format('Y-m-d');
    }

    public function itemUnitsCount(): string
    {
        return collect($this->nonEmptyItems())
            ->reduce(function (string $carry, array $item) {
                $quantity = (string) ($item['quantity'] ?? '0');

                if (! preg_match('/^-?\d+(?:\.\d+)?$/', $quantity)) {
                    return $carry;
                }

                return bcadd($carry, $quantity, 2);
            }, '0.00');
    }

    public function lastItemTotal(): string
    {
        $lastItem = collect($this->nonEmptyItems())->last();

        if (! is_array($lastItem)) {
            return '0.00';
        }

        return bcmul(
            (string) ($lastItem['quantity'] ?? '0'),
            (string) ($lastItem['unit_price'] ?? '0'),
            2
        );
    }

    public function activePriceLabel(): string
    {
        $firstItem = collect($this->nonEmptyItems())->first();

        if (! is_array($firstItem)) {
            return 'V1';
        }

        return strtoupper(str_replace('price_', 'VENTA', (string) ($firstItem['price_tier'] ?? 'price_1')));
    }

    public function cashierName(): string
    {
        return mb_strtoupper((string) (auth()->user()?->name ?? 'CAJERO'));
    }

    public function terminalLabel(): string
    {
        $branchCode = $this->branches()->firstWhere('id', $this->branchId)?->code ?? 'SUC';
        $registerCode = $this->cashRegisters()->firstWhere('id', $this->cashRegisterId)?->code ?? 'POS';

        return mb_strtoupper($branchCode.'-'.$registerCode);
    }

    public function workDateLabel(): string
    {
        return now()->locale('es')->translatedFormat('d M Y H:i');
    }

    public function variantSummary(?ProductVariant $variant): string
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

    public function render(): View
    {
        return view('livewire.sales.pos-page', [
            'branches' => $this->branches(),
            'warehouses' => $this->warehouses(),
            'cashRegisters' => $this->cashRegisters(),
            'openCashSessions' => $this->openCashSessions(),
            'customers' => $this->customers(),
            'products' => $this->products(),
            'canCreateSales' => $this->canCreateSales(),
            'salesRequirementsMessage' => $this->salesRequirementsMessage(),
            'latestSale' => $this->latestSale(),
            'preview' => $this->previewData(),
        ])->layout('layouts.app', [
            'header' => view('components.page-title', [
                'title' => 'POS',
                'description' => 'Arma la venta inicial desde backoffice, confirma el documento y abre su ticket sin esperar todavia la UI final de caja.',
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

    protected function resetSaleForm(): void
    {
        $this->editingSaleId = null;
        $this->modifyingSaleId = null;
        $this->resumingFrozenSaleId = null;
        $this->showPaymentModal = false;
        $this->paymentCustomerDocument = '';
        $this->resolvedCustomerId = null;
        $this->resolvedCustomerName = null;
        $branches = $this->branches();

        $this->branchId = $branches->first()?->id;
        $this->warehouseId = $this->warehouses()->first()?->id;
        $this->cashRegisterId = $this->cashRegisters()->first()?->id;
        $this->customerId = null;
        $this->auditReference = $this->generateAuditReference();
        $this->loyaltyPointsToRedeem = '';
        $this->saleType = 'pos';
        $this->saleStatus = SaleStatus::Confirmed->value;
        $this->soldAt = now()->format('Y-m-d\TH:i');
        $this->notes = '';
        $this->items = [$this->newItemLine()];
        $this->cashSessionId = $this->openCashSessions()->first()?->id;
        $this->payments = [$this->newPaymentLine()];
        $this->resetValidation();
    }

    protected function salesQuery()
    {
        return Sale::query()
            ->where('company_id', $this->currentCompany()->id);
    }

    protected function newItemLine(): array
    {
        return [
            '_key' => ++$this->nextLineKey,
            'product_id' => '',
            'product_presentation_id' => '',
            'product_variant_id' => '',
            'quantity' => '1',
            'unit_price' => '0.00',
            'discount_amount' => '0.00',
            'tax_rate' => '0',
            'price_tier' => 'price_1',
        ];
    }

    protected function newPaymentLine(): array
    {
        return [
            '_key' => ++$this->nextLineKey,
            'payment_method_code' => 'cash',
            'amount' => '',
            'reference' => '',
        ];
    }

    public function priceTierOptions(?Product $product, $presentation = null): array
    {
        if (! $product) {
            return [];
        }

        $source = $presentation ?: $product;
        $options = [];

        foreach (['price_1', 'price_2', 'price_3'] as $tier) {
            $value = data_get($source, $tier);

            if ($value === null || $value === '') {
                continue;
            }

            $options[$tier] = [
                'label' => strtoupper(str_replace('price_', 'V', $tier)),
                'value' => $value,
            ];
        }

        if ($options === [] && $presentation) {
            return $this->priceTierOptions($product, null);
        }

        return $options;
    }

    public function productDisplayName(?Product $product): string
    {
        return $product?->name ?? 'Producto';
    }

    public function linePriceTierOptions(array $item): array
    {
        $product = $this->products()->firstWhere('id', (int) ($item['product_id'] ?? 0));

        if (! $product) {
            return [];
        }

        $presentation = $product->presentations?->firstWhere('id', (int) ($item['product_presentation_id'] ?? 0));

        return $this->priceTierOptions($product, $presentation);
    }

    protected function defaultPriceTierForProduct(?Product $product, $presentation = null): string
    {
        $options = $this->priceTierOptions($product, $presentation);

        return array_key_first($options) ?? 'price_1';
    }

    protected function resolvePriceForTier(?Product $product, $presentation, string $tier): string
    {
        if (! $product) {
            return '0';
        }

        $source = $presentation ?: $product;
        $value = data_get($source, $tier);

        if (($value === null || $value === '') && $presentation) {
            $value = data_get($product, $tier);
        }

        if ($value === null || $value === '') {
            $fallbackTier = $this->defaultPriceTierForProduct($product, $presentation);
            $value = data_get($presentation ?: $product, $fallbackTier) ?? data_get($product, $fallbackTier) ?? 0;
        }

        return (string) $value;
    }

    protected function detectPriceTierFromSnapshot(array $item): string
    {
        $product = $this->products()->firstWhere('id', (int) ($item['product_id'] ?? 0));

        if (! $product) {
            return 'price_1';
        }

        $presentation = $product->presentations?->firstWhere('id', (int) ($item['product_presentation_id'] ?? 0));
        $options = $this->priceTierOptions($product, $presentation);
        $unitPrice = (string) ($item['unit_price'] ?? '0');

        foreach (array_keys($options) as $tier) {
            if ((string) data_get($options[$tier], 'value') === $unitPrice) {
                return $tier;
            }
        }

        return array_key_first($options) ?? 'price_1';
    }

    protected function detectPriceTierForLoadedItem($item): string
    {
        $product = $this->products()->firstWhere('id', (int) $item->product_id);

        if (! $product) {
            return 'price_1';
        }

        $presentation = $product->presentations?->firstWhere('id', (int) ($item->product_presentation_id ?? 0));
        $options = $this->priceTierOptions($product, $presentation);

        foreach (array_keys($options) as $tier) {
            if ((string) data_get($options[$tier], 'value') === (string) $item->unit_price) {
                return $tier;
            }
        }

        return array_key_first($options) ?? 'price_1';
    }

    protected function nonEmptyItems(): array
    {
        return collect($this->items)
            ->filter(fn (array $item) => ! blank($item['product_id'] ?? null))
            ->values()
            ->all();
    }

    protected function generateAuditReference(): string
    {
        return now()->format('ymd His');
    }

    protected function lookupResolvedProduct(): ?Product
    {
        $term = mb_strtolower(trim($this->productLookup));

        if ($term === '') {
            return null;
        }

        $products = $this->products();

        return $products->first(function (Product $product) use ($term) {
            return in_array($term, [
                mb_strtolower((string) $product->barcode),
                mb_strtolower((string) $product->sku),
                mb_strtolower((string) $product->name),
            ], true);
        }) ?? $this->filteredProducts()->first();
    }

    protected function adjustQuantity(int $lineKey, string $delta): void
    {
        foreach ($this->items as $index => $item) {
            if ((int) ($item['_key'] ?? 0) !== $lineKey) {
                continue;
            }

            $current = (string) ($item['quantity'] ?? '0');

            if (! preg_match('/^-?\d+(?:\.\d+)?$/', $current)) {
                $current = '0';
            }

            $next = bcadd($current, $delta, 6);

            if (bccomp($next, '0.000001', 6) === -1) {
                $next = '1';
            }

            $this->items[$index]['quantity'] = rtrim(rtrim($next, '0'), '.');

            return;
        }
    }

    protected function blankToNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    protected function normalizeDateTime(mixed $value): ?string
    {
        $value = $this->blankToNull($value);

        if ($value === null) {
            return null;
        }

        return Carbon::parse($value)->format('Y-m-d H:i:s');
    }

    protected function resolveOrCreateCustomer(string $document, Company $company): int
    {
        $document = trim($document);
        $looksLikeDocument = (bool) preg_match('/^\d+$/', $document);

        $person = $looksLikeDocument
            ? Person::where('document_number', $document)->first()
            : Person::whereNull('document_number')->where('first_name', $document)->first();

        if (! $person) {
            $person = Person::create([
                'document_type'   => 'CC',
                'document_number' => $looksLikeDocument ? $document : null,
                'first_name'      => $document,
                'last_name'       => '',
            ]);
        }

        $customer = Customer::firstOrCreate(
            ['company_id' => $company->id, 'person_id' => $person->id],
            ['status' => 'active', 'credit_enabled' => false, 'loyalty_enabled' => false]
        );

        return $customer->id;
    }
}
