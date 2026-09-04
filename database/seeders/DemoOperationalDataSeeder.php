<?php

namespace Database\Seeders;

use App\Actions\Cash\OpenCashSession;
use App\Actions\Cash\CloseCashSession;
use App\Actions\Credit\RegisterCreditPayment;
use App\Actions\Customers\CreateCustomer;
use App\Actions\Loyalty\AdjustLoyaltyPoints;
use App\Actions\Promotions\CreatePromotion;
use App\Actions\Purchases\CreatePurchase;
use App\Actions\Purchases\RegisterPurchasePayment;
use App\Actions\Sales\CancelSale;
use App\Actions\Sales\CreateSale;
use App\Actions\Sales\RegisterSalePayments;
use App\Actions\Sales\ReturnSale;
use App\Actions\Suppliers\CreateSupplier;
use App\Enums\PromotionDiscountType;
use App\Enums\PromotionStatus;
use App\Enums\PromotionTargetType;
use App\Enums\PromotionType;
use App\Enums\PurchaseStatus;
use App\Enums\RecordStatus;
use App\Enums\SaleStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Subscription;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\LoyaltyMovement;
use App\Services\Settings\CompanySettings;
use Illuminate\Database\Seeder;

class DemoOperationalDataSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->demoCompanies() as $profile) {
            $owner = User::query()->where('email', $profile['owner_email'])->first();
            $company = Company::query()->where('legal_name', $profile['legal_name'])->first();

            if (! $owner || ! $company) {
                continue;
            }

            $this->configureCompany($company, $profile['plan_code'], $profile['settings']);
            $catalog = $this->seedCatalog($company);
            $suppliers = $this->seedSuppliers($company);
            $customers = $this->seedCustomers($company, $profile['plan_code']);
            $cashSession = $this->ensureOpenCashSession($company, $owner, $profile['settings']['opening_amount']);

            $this->seedPurchases($company, $catalog['products'], $suppliers, $profile['purchase_seed']);
            $this->seedSales($company, $owner, $cashSession, $catalog['products'], $customers, $profile['plan_code']);
            $this->seedEnhancedCommercialScenarios($company, $owner, $cashSession, $catalog['products'], $customers, $profile['plan_code']);
        }
    }

    protected function configureCompany(Company $company, string $planCode, array $settings): void
    {
        $service = app(CompanySettings::class);

        $service->set($company, 'general', 'phone', $settings['phone']);
        $service->set($company, 'general', 'address', $settings['address']);
        $service->set($company, 'cash', 'default_opening_amount', $settings['opening_amount']);
        $service->set($company, 'pos', 'allow_manual_discounts', $planCode === 'premium');
        $service->set($company, 'credit', 'credit_enabled', in_array($planCode, ['pro', 'premium'], true));
        $service->set($company, 'credit', 'default_term_days', $settings['credit_term_days']);
        $service->set($company, 'loyalty', 'loyalty_enabled', in_array($planCode, ['pro', 'premium'], true));
        $service->set($company, 'loyalty', 'points_rate', $settings['points_rate']);
    }

    protected function seedCatalog(Company $company): array
    {
        $unit = Unit::query()->updateOrCreate(
            ['company_id' => $company->id, 'code' => 'UND'],
            ['name' => 'Unidad', 'precision_scale' => 0, 'status' => RecordStatus::Active->value]
        );

        $categories = collect([
            'ABR' => 'Abarrotes',
            'BEB' => 'Bebidas',
            'ASE' => 'Aseo',
            'SNK' => 'Snacks',
        ])->mapWithKeys(fn (string $name, string $code) => [
            $code => Category::query()->updateOrCreate(
                ['company_id' => $company->id, 'code' => $code],
                ['name' => $name, 'status' => RecordStatus::Active->value]
            ),
        ]);

        $brands = collect([
            'Gran Campo',
            'Lacteos del Valle',
            'Refrescos 360',
            'Hogar Plus',
            'Snacks Mix',
        ])->mapWithKeys(fn (string $name) => [
            $name => Brand::query()->updateOrCreate(
                ['company_id' => $company->id, 'name' => $name],
                ['status' => RecordStatus::Active->value]
            ),
        ]);

        $products = collect([
            [
                'sku' => 'ARR-001',
                'name' => 'Arroz Premium 1Kg',
                'category' => 'ABR',
                'brand' => 'Gran Campo',
                'cost' => '3800',
                'price_1' => '5200',
                'minimum_stock' => '8',
            ],
            [
                'sku' => 'LEC-001',
                'name' => 'Leche Entera 1L',
                'category' => 'ABR',
                'brand' => 'Lacteos del Valle',
                'cost' => '3200',
                'price_1' => '4300',
                'minimum_stock' => '10',
            ],
            [
                'sku' => 'ACE-001',
                'name' => 'Aceite Vegetal 900ml',
                'category' => 'ABR',
                'brand' => 'Gran Campo',
                'cost' => '6800',
                'price_1' => '8700',
                'minimum_stock' => '6',
            ],
            [
                'sku' => 'PAN-001',
                'name' => 'Panela Pulverizada 500g',
                'category' => 'ABR',
                'brand' => 'Gran Campo',
                'cost' => '2600',
                'price_1' => '3600',
                'minimum_stock' => '6',
            ],
            [
                'sku' => 'GAS-001',
                'name' => 'Gaseosa Cola 1.5L',
                'category' => 'BEB',
                'brand' => 'Refrescos 360',
                'cost' => '4200',
                'price_1' => '5600',
                'minimum_stock' => '12',
            ],
            [
                'sku' => 'DET-001',
                'name' => 'Detergente en Polvo 1Kg',
                'category' => 'ASE',
                'brand' => 'Hogar Plus',
                'cost' => '7900',
                'price_1' => '10900',
                'minimum_stock' => '5',
            ],
            [
                'sku' => 'PAP-001',
                'name' => 'Papel Higienico x4',
                'category' => 'ASE',
                'brand' => 'Hogar Plus',
                'cost' => '6200',
                'price_1' => '8500',
                'minimum_stock' => '5',
            ],
            [
                'sku' => 'GAL-001',
                'name' => 'Galletas Choco Pack',
                'category' => 'SNK',
                'brand' => 'Snacks Mix',
                'cost' => '1800',
                'price_1' => '2600',
                'minimum_stock' => '15',
            ],
            // Productos adicionales para tener catalogo con volumen realista
            // y poder probar la paginacion de la lista de productos.
            [
                'sku' => 'ARR-002',
                'name' => 'Arroz Ordinario 1Kg',
                'category' => 'ABR',
                'brand' => 'Gran Campo',
                'cost' => '3200',
                'price_1' => '4500',
                'minimum_stock' => '8',
            ],
            [
                'sku' => 'AZU-001',
                'name' => 'Azucar Blanca 1Kg',
                'category' => 'ABR',
                'brand' => 'Gran Campo',
                'cost' => '2400',
                'price_1' => '3400',
                'minimum_stock' => '8',
            ],
            [
                'sku' => 'SAL-001',
                'name' => 'Sal Refinada 500g',
                'category' => 'ABR',
                'brand' => 'Gran Campo',
                'cost' => '900',
                'price_1' => '1500',
                'minimum_stock' => '10',
            ],
            [
                'sku' => 'HAR-001',
                'name' => 'Harina de Trigo 1Kg',
                'category' => 'ABR',
                'brand' => 'Gran Campo',
                'cost' => '2600',
                'price_1' => '3800',
                'minimum_stock' => '6',
            ],
            [
                'sku' => 'PAS-001',
                'name' => 'Pasta Espagueti 500g',
                'category' => 'ABR',
                'brand' => 'Gran Campo',
                'cost' => '2100',
                'price_1' => '3200',
                'minimum_stock' => '10',
            ],
            [
                'sku' => 'CAF-001',
                'name' => 'Cafe Molido 500g',
                'category' => 'ABR',
                'brand' => 'Gran Campo',
                'cost' => '7200',
                'price_1' => '9800',
                'minimum_stock' => '5',
            ],
            [
                'sku' => 'CHO-001',
                'name' => 'Chocolate de Mesa 500g',
                'category' => 'ABR',
                'brand' => 'Gran Campo',
                'cost' => '5400',
                'price_1' => '7600',
                'minimum_stock' => '5',
            ],
            [
                'sku' => 'LEC-002',
                'name' => 'Leche Deslactosada 1L',
                'category' => 'ABR',
                'brand' => 'Lacteos del Valle',
                'cost' => '3400',
                'price_1' => '4600',
                'minimum_stock' => '8',
            ],
            [
                'sku' => 'YOG-001',
                'name' => 'Yogurt Natural 1L',
                'category' => 'ABR',
                'brand' => 'Lacteos del Valle',
                'cost' => '4200',
                'price_1' => '5900',
                'minimum_stock' => '6',
            ],
            [
                'sku' => 'QUE-001',
                'name' => 'Queso Campesino 500g',
                'category' => 'ABR',
                'brand' => 'Lacteos del Valle',
                'cost' => '6800',
                'price_1' => '9200',
                'minimum_stock' => '4',
            ],
            [
                'sku' => 'MAN-001',
                'name' => 'Mantequilla 250g',
                'category' => 'ABR',
                'brand' => 'Lacteos del Valle',
                'cost' => '3600',
                'price_1' => '4900',
                'minimum_stock' => '6',
            ],
            [
                'sku' => 'GAS-002',
                'name' => 'Gaseosa Naranja 1.5L',
                'category' => 'BEB',
                'brand' => 'Refrescos 360',
                'cost' => '4200',
                'price_1' => '5600',
                'minimum_stock' => '12',
            ],
            [
                'sku' => 'GAS-003',
                'name' => 'Gaseosa Manzana 1.5L',
                'category' => 'BEB',
                'brand' => 'Refrescos 360',
                'cost' => '4200',
                'price_1' => '5600',
                'minimum_stock' => '12',
            ],
            [
                'sku' => 'AGU-001',
                'name' => 'Agua Sin Gas 600ml',
                'category' => 'BEB',
                'brand' => 'Refrescos 360',
                'cost' => '1200',
                'price_1' => '2000',
                'minimum_stock' => '15',
            ],
            [
                'sku' => 'JUG-001',
                'name' => 'Jugo de Naranja 1L',
                'category' => 'BEB',
                'brand' => 'Refrescos 360',
                'cost' => '3800',
                'price_1' => '5200',
                'minimum_stock' => '8',
            ],
            [
                'sku' => 'TEE-001',
                'name' => 'Te Frio 500ml',
                'category' => 'BEB',
                'brand' => 'Refrescos 360',
                'cost' => '2200',
                'price_1' => '3400',
                'minimum_stock' => '10',
            ],
            [
                'sku' => 'JAB-001',
                'name' => 'Jabon de Tocador x3',
                'category' => 'ASE',
                'brand' => 'Hogar Plus',
                'cost' => '4800',
                'price_1' => '6900',
                'minimum_stock' => '6',
            ],
            [
                'sku' => 'LIM-001',
                'name' => 'Limpiador Multiusos 1L',
                'category' => 'ASE',
                'brand' => 'Hogar Plus',
                'cost' => '5200',
                'price_1' => '7400',
                'minimum_stock' => '6',
            ],
            [
                'sku' => 'CLO-001',
                'name' => 'Cloro 1L',
                'category' => 'ASE',
                'brand' => 'Hogar Plus',
                'cost' => '2800',
                'price_1' => '4100',
                'minimum_stock' => '8',
            ],
            [
                'sku' => 'SUA-001',
                'name' => 'Suavizante de Telas 1L',
                'category' => 'ASE',
                'brand' => 'Hogar Plus',
                'cost' => '5600',
                'price_1' => '7800',
                'minimum_stock' => '6',
            ],
            [
                'sku' => 'ESP-001',
                'name' => 'Esponjilla x3',
                'category' => 'ASE',
                'brand' => 'Hogar Plus',
                'cost' => '1800',
                'price_1' => '2900',
                'minimum_stock' => '10',
            ],
            [
                'sku' => 'PAP-002',
                'name' => 'Servilletas x100',
                'category' => 'ASE',
                'brand' => 'Hogar Plus',
                'cost' => '3200',
                'price_1' => '4600',
                'minimum_stock' => '8',
            ],
        ])->mapWithKeys(function (array $definition) use ($company, $unit, $categories, $brands) {
            $product = Product::query()->updateOrCreate(
                ['company_id' => $company->id, 'sku' => $definition['sku']],
                [
                    'category_id' => $categories[$definition['category']]->id,
                    'brand_id' => $brands[$definition['brand']]->id,
                    'base_unit_id' => $unit->id,
                    'name' => $definition['name'],
                    'cost' => $definition['cost'],
                    'price_1' => $definition['price_1'],
                    'tracks_inventory' => true,
                    'minimum_stock' => $definition['minimum_stock'],
                    'status' => RecordStatus::Active->value,
                ]
            );

            return [$definition['sku'] => $product];
        });

        return [
            'unit' => $unit,
            'categories' => $categories,
            'brands' => $brands,
            'products' => $products,
        ];
    }

    protected function seedSuppliers(Company $company): array
    {
        $definitions = [
            [
                'document_number' => '9007001001',
                'first_name' => 'Distribuidora',
                'last_name' => 'Andina',
                'payment_term_days' => 15,
                'email' => 'compras.andina@demo.test',
                'phone' => '3004001001',
            ],
            [
                'document_number' => '9007001002',
                'first_name' => 'Bebidas',
                'last_name' => 'Capital',
                'payment_term_days' => 8,
                'email' => 'pedidos.bebidas@demo.test',
                'phone' => '3004001002',
            ],
            [
                'document_number' => '9007001003',
                'first_name' => 'Aseo',
                'last_name' => 'Integral',
                'payment_term_days' => 30,
                'email' => 'ordenes.aseo@demo.test',
                'phone' => '3004001003',
            ],
        ];

        $suppliers = [];

        foreach ($definitions as $definition) {
            $supplier = Supplier::query()
                ->where('company_id', $company->id)
                ->whereHas('person', fn ($query) => $query->where('document_number', $definition['document_number']))
                ->with('person')
                ->first();

            if (! $supplier) {
                $supplier = app(CreateSupplier::class)->handle($company, [
                    'document_type' => 'NIT',
                    'document_number' => $definition['document_number'],
                    'first_name' => $definition['first_name'],
                    'last_name' => $definition['last_name'],
                    'payment_term_days' => $definition['payment_term_days'],
                    'email' => $definition['email'],
                    'phone' => $definition['phone'],
                    'notes' => 'Proveedor demo sembrado automaticamente.',
                ]);
            }

            $suppliers[$definition['document_number']] = $supplier;
        }

        return $suppliers;
    }

    protected function seedCustomers(Company $company, string $planCode): array
    {
        $shared = [
            [
                'document_type' => 'CC',
                'document_number' => '1001001001',
                'first_name' => 'Ana',
                'last_name' => 'Torres',
                'phone' => '3105001001',
                'email' => 'ana.torres@demo.test',
                'credit_enabled' => false,
                'credit_limit' => '0',
                'loyalty_enabled' => in_array($planCode, ['pro', 'premium'], true),
            ],
            [
                'document_type' => 'CC',
                'document_number' => '1001001002',
                'first_name' => 'Carlos',
                'last_name' => 'Ruiz',
                'phone' => '3105001002',
                'email' => 'carlos.ruiz@demo.test',
                'credit_enabled' => in_array($planCode, ['pro', 'premium'], true),
                'credit_limit' => in_array($planCode, ['pro', 'premium'], true) ? '40000' : '0',
                'loyalty_enabled' => in_array($planCode, ['pro', 'premium'], true),
            ],
            [
                'document_type' => 'CC',
                'document_number' => '1001001003',
                'first_name' => 'Laura',
                'last_name' => 'Becerra',
                'phone' => '3105001003',
                'email' => 'laura.becerra@demo.test',
                'credit_enabled' => false,
                'credit_limit' => '0',
                'loyalty_enabled' => in_array($planCode, ['pro', 'premium'], true),
            ],
            [
                'document_type' => 'NIT',
                'document_number' => '9010001111',
                'first_name' => 'Minimercado',
                'last_name' => 'San Jose',
                'phone' => '3205001111',
                'email' => 'compras@sanjose.demo.test',
                'credit_enabled' => in_array($planCode, ['pro', 'premium'], true),
                'credit_limit' => in_array($planCode, ['pro', 'premium'], true) ? '80000' : '0',
                'loyalty_enabled' => false,
            ],
        ];

        $specific = match ($planCode) {
            'basic' => [
                [
                    'document_type' => 'CC',
                    'document_number' => '1001001099',
                    'first_name' => 'Patricia',
                    'last_name' => 'Gomez',
                    'phone' => '3105001099',
                    'email' => 'patricia.gomez@demo.test',
                    'credit_enabled' => false,
                    'credit_limit' => '0',
                    'loyalty_enabled' => false,
                ],
            ],
            'pro' => [
                [
                    'document_type' => 'CC',
                    'document_number' => '1001002001',
                    'first_name' => 'Jorge',
                    'last_name' => 'Mora',
                    'phone' => '3105002001',
                    'email' => 'jorge.mora@demo.test',
                    'credit_enabled' => true,
                    'credit_limit' => '60000',
                    'loyalty_enabled' => false,
                ],
                [
                    'document_type' => 'CC',
                    'document_number' => '1001002002',
                    'first_name' => 'Sandra',
                    'last_name' => 'Rios',
                    'phone' => '3105002002',
                    'email' => 'sandra.rios@demo.test',
                    'credit_enabled' => true,
                    'credit_limit' => '60000',
                    'loyalty_enabled' => true,
                ],
            ],
            default => [
                [
                    'document_type' => 'CC',
                    'document_number' => '1001002001',
                    'first_name' => 'Jorge',
                    'last_name' => 'Mora',
                    'phone' => '3105002001',
                    'email' => 'jorge.mora@demo.test',
                    'credit_enabled' => true,
                    'credit_limit' => '85000',
                    'loyalty_enabled' => false,
                ],
                [
                    'document_type' => 'NIT',
                    'document_number' => '9010002222',
                    'first_name' => 'Distribuciones',
                    'last_name' => 'Fenix',
                    'phone' => '3205002222',
                    'email' => 'cartera@fenix.demo.test',
                    'credit_enabled' => true,
                    'credit_limit' => '120000',
                    'loyalty_enabled' => false,
                ],
                [
                    'document_type' => 'CC',
                    'document_number' => '1001002003',
                    'first_name' => 'Cafe',
                    'last_name' => 'Del Parque',
                    'phone' => '3105002003',
                    'email' => 'cafe.parque@demo.test',
                    'credit_enabled' => false,
                    'credit_limit' => '0',
                    'loyalty_enabled' => true,
                ],
            ],
        };

        $customers = [];

        foreach (array_merge($shared, $specific) as $definition) {
            $customer = Customer::query()
                ->where('company_id', $company->id)
                ->whereHas('person', fn ($query) => $query->where('document_number', $definition['document_number']))
                ->with(['person', 'creditAccount', 'loyaltyAccount'])
                ->first();

            if (! $customer) {
                $customer = app(CreateCustomer::class)->handle($company, $definition);
            }

            $customers[$definition['document_number']] = $customer;
        }

        return $customers;
    }

    protected function ensureOpenCashSession(Company $company, User $owner, string $openingAmount): \App\Models\CashSession
    {
        $branch = $company->branches()->firstOrFail();
        $cashRegister = $company->cashRegisters()->where('branch_id', $branch->id)->firstOrFail();

        $existing = $company->cashSessions()
            ->where('cash_register_id', $cashRegister->id)
            ->where('status', 'open')
            ->first();

        if ($existing) {
            return $existing;
        }

        return app(OpenCashSession::class)->handle($company, [
            'branch_id' => $branch->id,
            'cash_register_id' => $cashRegister->id,
            'opened_by' => $owner->id,
            'opening_amount' => $openingAmount,
        ]);
    }

    protected function seedPurchases(Company $company, $products, array $suppliers, string $seedCode): void
    {
        $branch = $company->branches()->firstOrFail();
        $warehouse = $company->warehouses()->firstOrFail();

        $purchases = [
            [
                'invoice_number' => "SEED-{$seedCode}-PUR-001",
                'supplier' => '9007001001',
                'status' => PurchaseStatus::Paid->value,
                'paid_amount' => '194800',
                'purchased_at' => now()->subDays(55)->format('Y-m-d H:i:s'),
                'due_at' => now()->subDays(40)->format('Y-m-d H:i:s'),
                'items' => [
                    ['sku' => 'ARR-001', 'quantity' => '20', 'unit_cost' => '3800'],
                    ['sku' => 'LEC-001', 'quantity' => '18', 'unit_cost' => '3200'],
                    ['sku' => 'ACE-001', 'quantity' => '9', 'unit_cost' => '6800'],
                ],
            ],
            [
                'invoice_number' => "SEED-{$seedCode}-PUR-002",
                'supplier' => '9007001002',
                'status' => PurchaseStatus::PartiallyPaid->value,
                'paid_amount' => '42000',
                'purchased_at' => now()->subDays(18)->format('Y-m-d H:i:s'),
                'due_at' => now()->subDays(3)->format('Y-m-d H:i:s'),
                'items' => [
                    ['sku' => 'GAS-001', 'quantity' => '22', 'unit_cost' => '4200'],
                    ['sku' => 'GAL-001', 'quantity' => '30', 'unit_cost' => '1800'],
                ],
            ],
            [
                'invoice_number' => "SEED-{$seedCode}-PUR-003",
                'supplier' => '9007001003',
                'status' => PurchaseStatus::Confirmed->value,
                'purchased_at' => now()->subDays(7)->format('Y-m-d H:i:s'),
                'due_at' => now()->addDays(12)->format('Y-m-d H:i:s'),
                'items' => [
                    ['sku' => 'DET-001', 'quantity' => '10', 'unit_cost' => '7900'],
                    ['sku' => 'PAP-001', 'quantity' => '12', 'unit_cost' => '6200'],
                    ['sku' => 'PAN-001', 'quantity' => '15', 'unit_cost' => '2600'],
                ],
            ],
        ];

        foreach ($purchases as $definition) {
            $existing = Purchase::query()
                ->where('company_id', $company->id)
                ->where('invoice_number', $definition['invoice_number'])
                ->first();

            if ($existing) {
                continue;
            }

            app(CreatePurchase::class)->handle($company, [
                'branch_id' => $branch->id,
                'warehouse_id' => $warehouse->id,
                'supplier_id' => $suppliers[$definition['supplier']]->id,
                'invoice_number' => $definition['invoice_number'],
                'status' => $definition['status'],
                'paid_amount' => $definition['paid_amount'] ?? null,
                'purchased_at' => $definition['purchased_at'],
                'due_at' => $definition['due_at'],
                'notes' => 'Compra demo sembrada automaticamente.',
                'items' => collect($definition['items'])->map(fn (array $item) => [
                    'product_id' => $products[$item['sku']]->id,
                    'quantity' => $item['quantity'],
                    'unit_cost' => $item['unit_cost'],
                ])->all(),
            ]);
        }
    }

    protected function seedSales(
        Company $company,
        User $owner,
        \App\Models\CashSession $cashSession,
        $products,
        array $customers,
        string $planCode,
    ): void {
        $branch = $company->branches()->firstOrFail();
        $warehouse = $company->warehouses()->firstOrFail();
        $cashRegister = $company->cashRegisters()->firstOrFail();

        $cashSales = [
            [
                'marker' => "seed:{$company->slug}:cash:ana:001",
                'customer' => '1001001001',
                'sold_at' => now()->subDays(70),
                'payments' => [['payment_method_code' => 'cash']],
                'items' => [
                    ['sku' => 'ARR-001', 'quantity' => '2', 'unit_price' => '5200'],
                    ['sku' => 'LEC-001', 'quantity' => '2', 'unit_price' => '4300'],
                ],
            ],
            [
                'marker' => "seed:{$company->slug}:cash:carlos:001",
                'customer' => '1001001002',
                'sold_at' => now()->subDays(32),
                'payments' => [['payment_method_code' => 'card']],
                'items' => [
                    ['sku' => 'ACE-001', 'quantity' => '1', 'unit_price' => '8700'],
                    ['sku' => 'GAL-001', 'quantity' => '4', 'unit_price' => '2600'],
                ],
            ],
            [
                'marker' => "seed:{$company->slug}:cash:ana:002",
                'customer' => '1001001001',
                'sold_at' => now()->subDays(9),
                'payments' => [
                    ['payment_method_code' => 'cash', 'amount' => '8000'],
                    ['payment_method_code' => 'card'],
                ],
                'items' => [
                    ['sku' => 'DET-001', 'quantity' => '1', 'unit_price' => '10900'],
                    ['sku' => 'PAP-001', 'quantity' => '1', 'unit_price' => '8500'],
                ],
            ],
            [
                'marker' => "seed:{$company->slug}:cash:laura:001",
                'customer' => '1001001003',
                'sold_at' => now()->subDays(2),
                'payments' => [['payment_method_code' => 'transfer']],
                'items' => [
                    ['sku' => 'GAS-001', 'quantity' => '3', 'unit_price' => '5600'],
                    ['sku' => 'GAL-001', 'quantity' => '5', 'unit_price' => '2600'],
                ],
            ],
        ];

        foreach ($cashSales as $definition) {
            $this->ensureCashSale($company, $owner, $cashSession, $branch->id, $warehouse->id, $cashRegister->id, $products, $customers, $definition);
        }

        if (! in_array($planCode, ['pro', 'premium'], true)) {
            return;
        }

        $creditSales = [
            [
                'marker' => "seed:{$company->slug}:credit:carlos:paid",
                'customer' => '1001001002',
                'sold_at' => now()->subDays(50),
                'due_at' => now()->subDays(20),
                'payment_at' => now()->subDays(35),
                'payment_amount' => null,
                'items' => [
                    ['sku' => 'ARR-001', 'quantity' => '3', 'unit_price' => '5200'],
                    ['sku' => 'ACE-001', 'quantity' => '1', 'unit_price' => '8700'],
                ],
            ],
            [
                'marker' => "seed:{$company->slug}:credit:jorge:overdue",
                'customer' => '1001002001',
                'sold_at' => now()->subDays(36),
                'due_at' => now()->subDays(8),
                'payment_at' => now()->subDays(12),
                'payment_amount' => $planCode === 'pro' ? '12000' : null,
                'items' => [
                    ['sku' => 'DET-001', 'quantity' => '2', 'unit_price' => '10900'],
                    ['sku' => 'PAP-001', 'quantity' => '2', 'unit_price' => '8500'],
                ],
            ],
        ];

        if ($planCode === 'pro') {
            $creditSales[] = [
                'marker' => "seed:{$company->slug}:credit:sandra:current",
                'customer' => '1001002002',
                'sold_at' => now()->subDays(6),
                'due_at' => now()->addDays(18),
                'payment_at' => null,
                'payment_amount' => null,
                'items' => [
                    ['sku' => 'LEC-001', 'quantity' => '6', 'unit_price' => '4300'],
                    ['sku' => 'GAL-001', 'quantity' => '8', 'unit_price' => '2600'],
                ],
            ];
        }

        if ($planCode === 'premium') {
            $creditSales[] = [
                'marker' => "seed:{$company->slug}:credit:fenix:partial",
                'customer' => '9010002222',
                'sold_at' => now()->subDays(24),
                'due_at' => now()->subDays(3),
                'payment_at' => now()->subDays(7),
                'payment_amount' => '15000',
                'items' => [
                    ['sku' => 'GAS-001', 'quantity' => '10', 'unit_price' => '5600'],
                    ['sku' => 'ARR-001', 'quantity' => '6', 'unit_price' => '5200'],
                ],
            ];
            $creditSales[] = [
                'marker' => "seed:{$company->slug}:cash:cafe:001",
                'customer' => '1001002003',
                'sold_at' => now()->subDay(),
                'payments' => [['payment_method_code' => 'cash']],
                'items' => [
                    ['sku' => 'PAN-001', 'quantity' => '4', 'unit_price' => '3600'],
                    ['sku' => 'LEC-001', 'quantity' => '2', 'unit_price' => '4300'],
                ],
            ];
        }

        foreach ($creditSales as $definition) {
            if (isset($definition['payments'])) {
                $this->ensureCashSale($company, $owner, $cashSession, $branch->id, $warehouse->id, $cashRegister->id, $products, $customers, $definition);

                continue;
            }

            $this->ensureCreditSale($company, $owner, $cashSession, $branch->id, $warehouse->id, $cashRegister->id, $products, $customers, $definition);
        }
    }

    protected function seedEnhancedCommercialScenarios(
        Company $company,
        User $owner,
        \App\Models\CashSession $cashSession,
        $products,
        array $customers,
        string $planCode,
    ): void {
        if (in_array($planCode, ['pro', 'premium'], true)) {
            $this->seedPromotions($company, $products, $planCode);
            $this->seedLoyaltyBootstrap($company, $customers, $planCode);
            $this->seedRedeemedSale($company, $owner, $cashSession, $products, $customers, $planCode);
        }

        $this->seedReturnedSale($company, $owner, $cashSession, $products, $customers);
        $this->seedCancelledSale($company, $owner, $cashSession, $products, $customers);
        $this->seedHistoricalClosedSession($company, $owner, $cashSession, $products, $customers, $planCode);
    }

    protected function seedPromotions(Company $company, $products, string $planCode): void
    {
        $productPromotionCode = 'SEED-PROMO-'.$company->slug.'-ARR';

        if (! Promotion::query()->where('company_id', $company->id)->where('code', $productPromotionCode)->exists()) {
            app(CreatePromotion::class)->handle($company, [
                'name' => 'Promo Arroz Semanal',
                'code' => $productPromotionCode,
                'status' => PromotionStatus::Active->value,
                'promotion_type' => PromotionType::ProductDiscount->value,
                'discount_type' => PromotionDiscountType::Percentage->value,
                'discount_value' => '8',
                'priority' => 20,
                'starts_at' => now()->subDays(10)->format('Y-m-d H:i:s'),
                'ends_at' => now()->addDays(20)->format('Y-m-d H:i:s'),
                'targets' => [[
                    'target_type' => PromotionTargetType::Product->value,
                    'target_id' => $products['ARR-001']->id,
                    'min_quantity' => '2',
                ]],
            ]);
        }

        if ($planCode !== 'premium') {
            return;
        }

        $comboPromotionCode = 'SEED-COMBO-'.$company->slug.'-SNACK';

        if (! Promotion::query()->where('company_id', $company->id)->where('code', $comboPromotionCode)->exists()) {
            app(CreatePromotion::class)->handle($company, [
                'name' => 'Combo Merienda Demo',
                'code' => $comboPromotionCode,
                'status' => PromotionStatus::Active->value,
                'promotion_type' => PromotionType::ComboPrice->value,
                'discount_type' => PromotionDiscountType::FixedPrice->value,
                'discount_value' => '6900',
                'priority' => 30,
                'starts_at' => now()->subDays(5)->format('Y-m-d H:i:s'),
                'ends_at' => now()->addDays(25)->format('Y-m-d H:i:s'),
                'combo_items' => [
                    [
                        'product_id' => $products['GAS-001']->id,
                        'required_quantity' => '1',
                    ],
                    [
                        'product_id' => $products['GAL-001']->id,
                        'required_quantity' => '1',
                    ],
                ],
            ]);
        }
    }

    protected function seedLoyaltyBootstrap(Company $company, array $customers, string $planCode): void
    {
        $targets = ['1001001001'];

        if ($planCode === 'pro') {
            $targets[] = '1001002002';
        }

        if ($planCode === 'premium') {
            $targets[] = '1001002003';
        }

        foreach ($targets as $documentNumber) {
            $customer = $customers[$documentNumber] ?? null;

            if (! $customer?->loyaltyAccount) {
                continue;
            }

            $marker = 'seed-loyalty-'.$company->slug.'-'.$documentNumber;

            if (LoyaltyMovement::query()
                ->where('company_id', $company->id)
                ->where('loyalty_account_id', $customer->loyaltyAccount->id)
                ->where('notes', 'like', '%'.$marker.'%')
                ->exists()) {
                continue;
            }

            app(AdjustLoyaltyPoints::class)->handle($company, $customer->loyaltyAccount, [
                'type' => 'credit',
                'points' => $documentNumber === '1001001001' ? '180' : '120',
                'reason_code' => 'migration_adjustment',
                'notes' => $marker.' saldo inicial demo',
            ]);
        }
    }

    protected function seedRedeemedSale(
        Company $company,
        User $owner,
        \App\Models\CashSession $cashSession,
        $products,
        array $customers,
        string $planCode,
    ): void {
        $marker = "seed:{$company->slug}:loyalty:redeem";

        if (Sale::query()->where('company_id', $company->id)->where('notes', $marker)->exists()) {
            return;
        }

        $customerDocument = $planCode === 'premium' ? '1001002003' : '1001001001';
        $customer = $customers[$customerDocument] ?? null;

        if (! $customer?->loyaltyAccount) {
            return;
        }

        $branch = $company->branches()->firstOrFail();
        $warehouse = $company->warehouses()->firstOrFail();
        $cashRegister = $company->cashRegisters()->firstOrFail();

        $sale = app(CreateSale::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'cash_register_id' => $cashRegister->id,
            'customer_id' => $customer->id,
            'user_id' => $owner->id,
            'status' => SaleStatus::Confirmed->value,
            'sold_at' => now()->subDays(4)->format('Y-m-d H:i:s'),
            'notes' => $marker,
            'loyalty_points_to_redeem' => $planCode === 'premium' ? '60' : '40',
            'items' => [
                [
                    'product_id' => $products['ARR-001']->id,
                    'quantity' => '2',
                    'unit_price' => '5200',
                ],
                [
                    'product_id' => $products['GAL-001']->id,
                    'quantity' => '3',
                    'unit_price' => '2600',
                ],
            ],
        ]);

        $payments = app(RegisterSalePayments::class)->handle($company, $sale, [
            'cash_session_id' => $cashSession->id,
            'received_by' => $owner->id,
            'payments' => [[
                'payment_method_code' => 'cash',
                'amount' => (string) $sale->grand_total,
            ]],
        ]);

        Payment::query()
            ->whereIn('id', collect($payments)->pluck('id')->all())
            ->update(['paid_at' => now()->subDays(4)->format('Y-m-d H:i:s')]);
    }

    protected function seedReturnedSale(
        Company $company,
        User $owner,
        \App\Models\CashSession $cashSession,
        $products,
        array $customers,
    ): void {
        $marker = "seed:{$company->slug}:returned:partial";
        $sale = Sale::query()->where('company_id', $company->id)->where('notes', 'like', '%'.$marker.'%')->with('items')->first();

        if (! $sale) {
            $branch = $company->branches()->firstOrFail();
            $warehouse = $company->warehouses()->firstOrFail();
            $cashRegister = $company->cashRegisters()->firstOrFail();

            $sale = app(CreateSale::class)->handle($company, [
                'branch_id' => $branch->id,
                'warehouse_id' => $warehouse->id,
                'cash_register_id' => $cashRegister->id,
                'customer_id' => $customers['1001001001']->id,
                'user_id' => $owner->id,
                'status' => SaleStatus::Confirmed->value,
                'sold_at' => now()->subDays(12)->format('Y-m-d H:i:s'),
                'notes' => $marker,
                'items' => [
                    [
                        'product_id' => $products['GAS-001']->id,
                        'quantity' => '2',
                        'unit_price' => '5600',
                    ],
                    [
                        'product_id' => $products['GAL-001']->id,
                        'quantity' => '4',
                        'unit_price' => '2600',
                    ],
                ],
            ]);

            $payments = app(RegisterSalePayments::class)->handle($company, $sale, [
                'cash_session_id' => $cashSession->id,
                'received_by' => $owner->id,
                'payments' => [[
                    'payment_method_code' => 'cash',
                    'amount' => (string) $sale->grand_total,
                ]],
            ]);

            Payment::query()
                ->whereIn('id', collect($payments)->pluck('id')->all())
                ->update(['paid_at' => now()->subDays(12)->format('Y-m-d H:i:s')]);
        }

        if (in_array($sale->status, [SaleStatus::PartiallyReturned->value, SaleStatus::Returned->value], true)) {
            return;
        }

        $returned = app(ReturnSale::class)->handle($company, $sale, [[
            'sale_item_id' => $sale->items->first()->id,
            'quantity' => '1',
        ]], 'Cliente reporto golpe en el empaque');

        $returned->update([
            'returned_at' => now()->subDays(10)->format('Y-m-d H:i:s'),
        ]);
    }

    protected function seedCancelledSale(
        Company $company,
        User $owner,
        \App\Models\CashSession $cashSession,
        $products,
        array $customers,
    ): void {
        $marker = "seed:{$company->slug}:cancelled";
        $sale = Sale::query()->where('company_id', $company->id)->where('notes', 'like', '%'.$marker.'%')->first();

        if (! $sale) {
            $branch = $company->branches()->firstOrFail();
            $warehouse = $company->warehouses()->firstOrFail();
            $cashRegister = $company->cashRegisters()->firstOrFail();

            $sale = app(CreateSale::class)->handle($company, [
                'branch_id' => $branch->id,
                'warehouse_id' => $warehouse->id,
                'cash_register_id' => $cashRegister->id,
                'customer_id' => $customers['1001001003']->id,
                'user_id' => $owner->id,
                'status' => SaleStatus::Confirmed->value,
                'sold_at' => now()->subDays(8)->format('Y-m-d H:i:s'),
                'notes' => $marker,
                'items' => [
                    [
                        'product_id' => $products['DET-001']->id,
                        'quantity' => '1',
                        'unit_price' => '10900',
                    ],
                    [
                        'product_id' => $products['PAP-001']->id,
                        'quantity' => '1',
                        'unit_price' => '8500',
                    ],
                ],
            ]);

            $payments = app(RegisterSalePayments::class)->handle($company, $sale, [
                'cash_session_id' => $cashSession->id,
                'received_by' => $owner->id,
                'payments' => [[
                    'payment_method_code' => 'cash',
                    'amount' => (string) $sale->grand_total,
                ]],
            ]);

            Payment::query()
                ->whereIn('id', collect($payments)->pluck('id')->all())
                ->update(['paid_at' => now()->subDays(8)->format('Y-m-d H:i:s')]);
        }

        if ($sale->status === SaleStatus::Cancelled->value) {
            return;
        }

        $cancelled = app(CancelSale::class)->handle($company, $sale, 'Pedido duplicado generado en mostrador');

        $cancelled->update([
            'cancelled_at' => now()->subDays(7)->format('Y-m-d H:i:s'),
        ]);
    }

    protected function seedHistoricalClosedSession(
        Company $company,
        User $owner,
        \App\Models\CashSession $cashSession,
        $products,
        array $customers,
        string $planCode,
    ): void {
        $marker = "seed:{$company->slug}:historical-close";

        if (Sale::query()->where('company_id', $company->id)->where('notes', $marker)->exists()) {
            return;
        }

        $branch = $company->branches()->firstOrFail();
        $warehouse = $company->warehouses()->firstOrFail();
        $cashRegister = $company->cashRegisters()->firstOrFail();

        $sale = app(CreateSale::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'cash_register_id' => $cashRegister->id,
            'customer_id' => $customers['1001001001']->id,
            'user_id' => $owner->id,
            'status' => SaleStatus::Confirmed->value,
            'sold_at' => now()->subDays(1)->format('Y-m-d H:i:s'),
            'notes' => $marker,
            'items' => [
                [
                    'product_id' => $products['ARR-001']->id,
                    'quantity' => '1',
                    'unit_price' => '5200',
                ],
                [
                    'product_id' => $products['LEC-001']->id,
                    'quantity' => '2',
                    'unit_price' => '4300',
                ],
            ],
        ]);

        $payments = app(RegisterSalePayments::class)->handle($company, $sale, [
            'cash_session_id' => $cashSession->id,
            'received_by' => $owner->id,
            'payments' => [[
                'payment_method_code' => 'cash',
                'amount' => (string) $sale->grand_total,
            ]],
        ]);

        Payment::query()
            ->whereIn('id', collect($payments)->pluck('id')->all())
            ->update(['paid_at' => now()->subDay()->format('Y-m-d H:i:s')]);

        $cashSum = $cashSession->payments()
            ->where('status', 'confirmed')
            ->where('payment_method_code', 'cash')
            ->sum('amount');
        $expected = bcadd((string) $cashSession->opening_amount, number_format((float) $cashSum, 2, '.', ''), 2);
        $counted = $planCode === 'pro'
            ? bcsub($expected, '300.00', 2)
            : $expected;

        $closed = app(CloseCashSession::class)->handle($company, $cashSession, [
            'closed_by' => $owner->id,
            'closing_counted_amount' => $counted,
        ]);

        $closed->update([
            'opened_at' => now()->subDays(1)->startOfDay()->format('Y-m-d H:i:s'),
            'closed_at' => now()->subDay()->endOfDay()->format('Y-m-d H:i:s'),
        ]);

        app(OpenCashSession::class)->handle($company, [
            'branch_id' => $branch->id,
            'cash_register_id' => $cashRegister->id,
            'opened_by' => $owner->id,
            'opening_amount' => (string) app(CompanySettings::class)->get($company, 'cash', 'default_opening_amount'),
        ]);
    }

    protected function ensureCashSale(
        Company $company,
        User $owner,
        \App\Models\CashSession $cashSession,
        int $branchId,
        int $warehouseId,
        int $cashRegisterId,
        $products,
        array $customers,
        array $definition,
    ): void {
        $existing = Sale::query()
            ->where('company_id', $company->id)
            ->where('notes', $definition['marker'])
            ->first();

        if ($existing) {
            return;
        }

        $sale = app(CreateSale::class)->handle($company, [
            'branch_id' => $branchId,
            'warehouse_id' => $warehouseId,
            'cash_register_id' => $cashRegisterId,
            'customer_id' => $customers[$definition['customer']]->id,
            'user_id' => $owner->id,
            'status' => SaleStatus::Confirmed->value,
            'sold_at' => $definition['sold_at']->format('Y-m-d H:i:s'),
            'notes' => $definition['marker'],
            'items' => collect($definition['items'])->map(fn (array $item) => [
                'product_id' => $products[$item['sku']]->id,
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
            ])->all(),
        ]);

        $normalizedPayments = $this->normalizeSalePayments($sale, $definition['payments']);

        $payments = app(RegisterSalePayments::class)->handle($company, $sale, [
            'cash_session_id' => $cashSession->id,
            'received_by' => $owner->id,
            'payments' => $normalizedPayments,
        ]);

        Payment::query()
            ->whereIn('id', collect($payments)->pluck('id')->all())
            ->update(['paid_at' => $definition['sold_at']->format('Y-m-d H:i:s')]);
    }

    protected function ensureCreditSale(
        Company $company,
        User $owner,
        \App\Models\CashSession $cashSession,
        int $branchId,
        int $warehouseId,
        int $cashRegisterId,
        $products,
        array $customers,
        array $definition,
    ): void {
        $existing = Sale::query()
            ->where('company_id', $company->id)
            ->where('notes', $definition['marker'])
            ->with('payments')
            ->first();

        if (! $existing) {
            $existing = app(CreateSale::class)->handle($company, [
                'branch_id' => $branchId,
                'warehouse_id' => $warehouseId,
                'cash_register_id' => $cashRegisterId,
                'customer_id' => $customers[$definition['customer']]->id,
                'user_id' => $owner->id,
                'sale_type' => 'credit',
                'status' => SaleStatus::Confirmed->value,
                'sold_at' => $definition['sold_at']->format('Y-m-d H:i:s'),
                'notes' => $definition['marker'],
                'items' => collect($definition['items'])->map(fn (array $item) => [
                    'product_id' => $products[$item['sku']]->id,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                ])->all(),
            ]);

            $existing->update([
                'credit_due_at' => $definition['due_at']->format('Y-m-d H:i:s'),
            ]);
        }

        if ($definition['payment_amount'] === null || $existing->payments()->exists()) {
            return;
        }

        // Los abonos ya no se enlazan a una venta puntual: RegisterCreditPayment
        // recibe la cuenta de credito directamente, no la Sale.
        $account = $customers[$definition['customer']]->creditAccount()->firstOrFail();

        $payment = app(RegisterCreditPayment::class)->handle($company, $account, [
            'cash_session_id' => $cashSession->id,
            'received_by' => $owner->id,
            'payment_method_code' => 'cash',
            'amount' => $definition['payment_amount'],
            'reference' => strtoupper(str_replace(':', '-', $definition['marker'])).'-ABONO',
        ]);

        if ($definition['payment_at']) {
            $payment->update([
                'paid_at' => $definition['payment_at']->format('Y-m-d H:i:s'),
            ]);
        }
    }

    protected function normalizeSalePayments(Sale $sale, array $payments): array
    {
        if (count($payments) === 1 && ! isset($payments[0]['amount'])) {
            return [[
                'payment_method_code' => $payments[0]['payment_method_code'],
                'amount' => (string) $sale->grand_total,
            ]];
        }

        if (count($payments) === 2 && isset($payments[0]['amount']) && ! isset($payments[1]['amount'])) {
            $firstAmount = bcadd((string) $payments[0]['amount'], '0', 2);
            $secondAmount = bcsub((string) $sale->grand_total, $firstAmount, 2);

            return [
                [
                    'payment_method_code' => $payments[0]['payment_method_code'],
                    'amount' => $firstAmount,
                ],
                [
                    'payment_method_code' => $payments[1]['payment_method_code'],
                    'amount' => $secondAmount,
                ],
            ];
        }

        return collect($payments)->map(function (array $payment) {
            return [
                'payment_method_code' => $payment['payment_method_code'],
                'amount' => $payment['amount'],
            ];
        })->all();
    }

    protected function demoCompanies(): array
    {
        return [
            [
                'owner_email' => 'demo.basic@retailsaas.test',
                'legal_name' => 'Demo Basic Market SAS',
                'plan_code' => 'basic',
                'settings' => [
                    'sale_prefix' => 'BAS-',
                    'opening_amount' => '120000',
                    'credit_term_days' => 30,
                    'points_rate' => '1.0000',
                    'phone' => '6017001001',
                    'address' => 'Cra 10 # 20-15, Bogota',
                ],
                'purchase_seed' => 'BAS',
            ],
            [
                'owner_email' => 'demo.pro@retailsaas.test',
                'legal_name' => 'Demo Pro Retail SAS',
                'plan_code' => 'pro',
                'settings' => [
                    'sale_prefix' => 'PRO-',
                    'opening_amount' => '180000',
                    'credit_term_days' => 21,
                    'points_rate' => '1.2500',
                    'phone' => '6047002002',
                    'address' => 'Cl 45 # 18-90, Medellin',
                ],
                'purchase_seed' => 'PRO',
            ],
            [
                'owner_email' => 'demo.premium@retailsaas.test',
                'legal_name' => 'Demo Premium Commerce SAS',
                'plan_code' => 'premium',
                'settings' => [
                    'sale_prefix' => 'PRM-',
                    'opening_amount' => '260000',
                    'credit_term_days' => 25,
                    'points_rate' => '1.5000',
                    'phone' => '6057003003',
                    'address' => 'Av 3 # 12-40, Cali',
                ],
                'purchase_seed' => 'PRM',
            ],
        ];
    }
}
