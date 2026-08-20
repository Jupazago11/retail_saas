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

        $hasModule = fn (string $code): bool => (bool) ($modules[$code] ?? false);

        // Entire groups gated by module
        $groupModules = [
            'inventory'          => 'inventory',
            'credit'             => 'credit',
            'loyalty'            => 'loyalty',
            'electronic_billing' => 'electronic_billing',
        ];

        // Personalizacion de marca (logo, color) por empresa: ocultada a
        // proposito por ahora, todavia no se va a habilitar.
        // Grupo "cash" completo: se administra desde el modal de Reglas
        // dentro del propio modulo Caja. Grupo "pos" y grupo "printing"
        // completos: se administran desde el modal de Reglas dentro del
        // modulo Ventas. Duplicarlos en esta pagina general solo la haria
        // mas grande y menos intuitiva.
        $hiddenKeys = [
            'general.logo_path',
            'general.logo_print_path',
            'general.primary_color',
            'cash.module_enabled',
            'cash.opening_required',
            'cash.default_opening_amount',
            'inventory.setup_wizard_answered',
            'pos.allow_alternative_prices',
            'pos.allow_manual_discounts',
            'pos.allow_promotion_stacking',
            'pos.allow_negative_stock',
            'pos.require_customer_for_credit_sale',
            'printing.ticket_format',
            'printing.show_logo',
        ];

        return collect(CompanySettingCatalog::definitions())
            ->filter(function (array $definition) use ($hasModule, $groupModules, $hiddenKeys) {
                $group = $definition['group'];
                $key   = $definition['key'];

                if (in_array("{$group}.{$key}", $hiddenKeys, true)) {
                    return false;
                }

                if (isset($groupModules[$group])) {
                    return $hasModule($groupModules[$group]);
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
            'allow_alternative_prices' => 'Permitir precios alternos',
            'allow_manual_discounts' => 'Permitir descuentos manuales',
            'allow_promotion_stacking' => 'Permitir apilar promociones',
            'allow_negative_stock' => 'Permitir inventario negativo',
            'require_customer_for_credit_sale' => 'Exigir cliente para venta a credito',
            'inventory_enabled' => 'Inventario activo',
            'minimum_stock_alerts_enabled' => 'Alertas de stock minimo',
            'default_cost_method' => 'Metodo de costo',
            'module_enabled' => 'Esta empresa usa caja registradora',
            'opening_required' => 'Exigir apertura de caja',
            'default_opening_amount' => 'Monto por defecto de apertura',
            'ticket_format' => 'Formato de ticket',
            'show_logo' => 'Mostrar logo',
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
                'description' => 'Administra parametros operativos por empresa para credito, fidelizacion, facturacion electronica e inventario.',
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
            // y los decimales llegan con 4 ceros de precision de almacenamiento
            // (ej "120000.0000"): se recortan los ceros sobrantes para mostrar
            // un numero limpio en el input.
            $settings[$group] = array_map(function ($value, string $key) use ($group) {
                $def = \App\Support\Settings\CompanySettingCatalog::definition($group, $key);

                if ($def && $def['type'] === 'boolean') {
                    return (bool) $value;
                }

                if ($def && $def['type'] === 'decimal' && $value !== null) {
                    $stringValue = (string) $value;

                    if (str_contains($stringValue, '.')) {
                        $stringValue = rtrim(rtrim($stringValue, '0'), '.');
                    }

                    return $stringValue === '' ? '0' : $stringValue;
                }

                return $value;
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
