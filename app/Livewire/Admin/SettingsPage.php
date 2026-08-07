<?php

namespace App\Livewire\Admin;

use App\Actions\Settings\UpdateCompanySettings;
use App\Models\Company;
use App\Services\Plans\CompanyPlanResolver;
use App\Services\Settings\CompanySettings;
use App\Services\Tenancy\CurrentCompany;
use App\Support\Settings\CompanySettingCatalog;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Component;

class SettingsPage extends Component
{
    public array $settings = [];

    public function mount(): void
    {
        $this->ensurePermission('settings.manage');
        $this->loadSettings();
    }

    public function saveSettings(UpdateCompanySettings $updateCompanySettings): void
    {
        $this->ensurePermission('settings.manage');

        try {
            $updateCompanySettings->handle(
                $this->currentCompany(),
                $this->settings,
                auth()->user()
            );
        } catch (InvalidArgumentException $exception) {
            $this->addError('settings', $exception->getMessage());

            return;
        }

        $this->loadSettings();
        $this->dispatch('toast', message: 'Configuracion guardada correctamente.', type: 'success');
    }

    public function groups(): array
    {
        $snapshot = app(CompanyPlanResolver::class)->snapshot($this->currentCompany());
        $modules  = $snapshot['modules'];
        $features = $snapshot['features'];

        $hasModule  = fn (string $code): bool => (bool) ($modules[$code] ?? false);
        $hasFeature = fn (string $code): bool => (bool) ($features[$code] ?? false);

        // Entire groups gated by module
        $groupModules = [
            'inventory'          => 'inventory',
            'credit'             => 'credit',
            'loyalty'            => 'loyalty',
            'electronic_billing' => 'electronic_billing',
        ];

        // Individual POS settings gated by feature / module
        $posKeyGates = [
            'frozen_sales_enabled'             => fn () => $hasFeature('pos.frozen_sales'),
            'frozen_sales_expiration_minutes'  => fn () => $hasFeature('pos.frozen_sales'),
            'allow_alternative_prices'         => fn () => $hasFeature('products.multiple_prices'),
            'allow_manual_discounts'           => fn () => $hasFeature('pos.manual_discounts'),
            'allow_promotion_stacking'         => fn () => $hasModule('promotions'),
            'allow_negative_stock'             => fn () => $hasModule('inventory'),
            'require_customer_for_credit_sale' => fn () => $hasModule('credit'),
        ];

        return collect(CompanySettingCatalog::definitions())
            ->filter(function (array $definition) use ($hasModule, $groupModules, $posKeyGates) {
                $group = $definition['group'];
                $key   = $definition['key'];

                if (isset($groupModules[$group])) {
                    return $hasModule($groupModules[$group]);
                }

                if ($group === 'pos' && isset($posKeyGates[$key])) {
                    return ($posKeyGates[$key])();
                }

                return true;
            })
            ->groupBy('group')
            ->map(fn ($definitions) => $definitions->values()->all())
            ->all();
    }

    public function fieldLabel(string $key): string
    {
        return match ($key) {
            'legal_name' => 'Razon social',
            'display_name' => 'Nombre comercial',
            'tax_id' => 'NIT / identificacion fiscal',
            'phone' => 'Telefono',
            'address' => 'Direccion',
            'logo_path' => 'Ruta de logo',
            'primary_color' => 'Color principal',
            'frozen_sales_enabled' => 'Permitir ventas congeladas',
            'frozen_sales_expiration_minutes' => 'Minutos para expirar ventas congeladas',
            'allow_alternative_prices' => 'Permitir precios alternos',
            'allow_manual_discounts' => 'Permitir descuentos manuales',
            'allow_promotion_stacking' => 'Permitir apilar promociones',
            'allow_negative_stock' => 'Permitir inventario negativo',
            'requires_open_cash_session' => 'Requerir caja abierta para vender',
            'require_customer_for_credit_sale' => 'Exigir cliente para venta a credito',
            'sale_document_prefix' => 'Prefijo documental interno',
            'sale_document_starting_sequence' => 'Consecutivo inicial interno',
            'inventory_enabled' => 'Inventario activo',
            'minimum_stock_alerts_enabled' => 'Alertas de stock minimo',
            'default_cost_method' => 'Metodo de costo',
            'opening_required' => 'Exigir apertura de caja',
            'default_opening_amount' => 'Monto por defecto de apertura',
            'allow_close_with_difference' => 'Permitir cierre con diferencia',
            'ticket_format' => 'Formato de ticket',
            'show_logo' => 'Mostrar logo',
            'show_saas_branding' => 'Mostrar marca del SaaS',
            'credit_enabled' => 'Credito habilitado',
            'default_term_days' => 'Plazo por defecto en dias',
            'block_new_credit_if_overdue' => 'Bloquear nuevo credito si hay mora',
            'loyalty_enabled' => 'Fidelizacion habilitada',
            'points_rule_type' => 'Regla de puntos',
            'points_rate' => 'Tasa de puntos',
            'points_expiration_days' => 'Dias para expirar puntos',
            'enabled' => 'Facturacion electronica habilitada',
            'provider' => 'Proveedor',
            'environment' => 'Ambiente',
            'resolution_number' => 'Resolucion',
            'prefix' => 'Prefijo',
            'technical_key' => 'Clave tecnica',
            'software_id' => 'Software ID',
            'software_pin' => 'Software PIN',
            'sequence_current' => 'Consecutivo actual',
            'sequence_max' => 'Consecutivo maximo',
            default => str_replace('_', ' ', $key),
        };
    }

    public function groupLabel(string $group): string
    {
        return match ($group) {
            'general' => 'General',
            'pos' => 'POS',
            'inventory' => 'Inventario',
            'cash' => 'Caja',
            'printing' => 'Impresion',
            'credit' => 'Credito',
            'loyalty' => 'Fidelizacion',
            'electronic_billing' => 'Facturacion electronica',
            default => ucfirst($group),
        };
    }

    public function selectOptions(string $group, string $key): array
    {
        return match ("{$group}.{$key}") {
            'inventory.default_cost_method' => [
                'weighted_average' => 'Promedio ponderado',
            ],
            'printing.ticket_format' => [
                'thermal_58mm' => 'Termica 58mm',
                'thermal_80mm' => 'Termica 80mm',
                'letter_a4' => 'Carta / A4',
            ],
            'loyalty.points_rule_type' => [
                'per_currency' => 'Por moneda',
            ],
            'electronic_billing.provider' => [
                'factus' => 'Factus',
                'facturia' => 'Facturia',
            ],
            'electronic_billing.environment' => [
                'sandbox' => 'Sandbox',
                'production' => 'Produccion',
            ],
            default => [],
        };
    }

    public function render(): View
    {
        return view('livewire.admin.settings-page', [
            'groups' => $this->groups(),
        ])->layout('layouts.app', [
            'header' => view('components.page-title', [
                'title' => 'Configuracion',
                'description' => 'Administra parametros operativos por empresa para POS, caja, credito, impresion e inventario.',
            ]),
        ]);
    }

    protected function loadSettings(): void
    {
        $companySettings = app(CompanySettings::class);
        $company         = $this->currentCompany();
        $settings        = [];

        foreach ($this->groups() as $group => $definitions) {
            $raw = $companySettings->group($company, $group);

            // Ensure booleans are strictly bool (MySQL TINYINT can return int 0/1)
            $settings[$group] = array_map(function ($value, string $key) use ($group) {
                $def = \App\Support\Settings\CompanySettingCatalog::definition($group, $key);
                return ($def && $def['type'] === 'boolean') ? (bool) $value : $value;
            }, $raw, array_keys($raw));

            // Re-key after array_map (it strips string keys)
            $settings[$group] = array_combine(array_keys($raw), $settings[$group]);
        }

        $settings['general']['legal_name']   = $company->legal_name;
        $settings['general']['display_name']  = $company->display_name;
        $settings['general']['tax_id']        = $company->tax_id;

        $this->settings = $settings;
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
}
