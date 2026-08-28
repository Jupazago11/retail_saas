<?php

namespace App\Livewire\Reports;

use App\Enums\RecordStatus;
use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\Company;
use App\Services\Plans\CompanyPlanResolver;
use App\Services\Reports\OperationalReportService;
use App\Services\Reports\PurchaseReportService;
use App\Services\Settings\CompanySettings;
use App\Services\Tenancy\CurrentCompany;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Component;

class OperationalReportsPage extends Component
{
    public string $dateFrom = '';
    public string $dateTo = '';
    public ?int $branchId = null;
    public ?int $cashRegisterId = null;

    public function mount(): void
    {
        $this->ensurePermission('reports.view');
        $this->dateFrom = now()->startOfMonth()->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');
    }

    public function branches(): Collection
    {
        return Branch::query()
            ->where('company_id', $this->currentCompany()->id)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get();
    }

    // Filtro secundario dentro de la columna Ventas: solo tiene sentido
    // mostrarlo si la empresa realmente tiene mas de una caja habilitada
    // (el plan Basico, por ejemplo, solo permite 1 — ver max_cash_registers
    // en PlanCatalog). Ventas si tiene cash_register_id por venta; Compras
    // todavia no registra de que caja sale un pago a proveedores, asi que
    // este filtro por ahora es solo para Ventas.
    public function cashRegisters(): Collection
    {
        return CashRegister::query()
            ->where('company_id', $this->currentCompany()->id)
            ->where('status', RecordStatus::Active->value)
            ->whereNull('deleted_at')
            ->orderByDesc('is_primary')
            ->orderBy('name')
            ->get();
    }

    public function canViewCosts(): bool
    {
        return auth()->user()?->hasCurrentCompanyPermission('reports.view_costs') ?? false;
    }

    // No basta con que el plan incluya el modulo: la empresa tiene que
    // haberlo prendido operativamente en Configuracion (mismo criterio que
    // PosPage::creditPaymentMethodAvailable()). Si no, mostrar "Cartera" en
    // un reporte de una empresa que nunca usa credito solo confunde.
    public function creditEnabled(): bool
    {
        $company = $this->currentCompany();

        return app(CompanyPlanResolver::class)->hasModule($company, 'credit')
            && app(CompanyPlanResolver::class)->hasFeature($company, 'credit.enabled')
            && (bool) app(CompanySettings::class)->get($company, 'credit', 'credit_enabled');
    }

    public function loyaltyEnabled(): bool
    {
        $company = $this->currentCompany();

        return app(CompanyPlanResolver::class)->hasModule($company, 'loyalty')
            && app(CompanyPlanResolver::class)->hasFeature($company, 'loyalty.enabled')
            && (bool) app(CompanySettings::class)->get($company, 'loyalty', 'loyalty_enabled');
    }

    public function promotionsEnabled(): bool
    {
        return app(CompanyPlanResolver::class)->hasModule($this->currentCompany(), 'promotions');
    }

    // El plan Basico no incluye el modulo de compras (ver PlanCatalog) — sin
    // esto, una empresa en ese plan veia una columna "Compras" entera en
    // $0 y un "contraste" contra nada, en vez de simplemente no mostrarla.
    public function purchasesEnabled(): bool
    {
        return app(CompanyPlanResolver::class)->hasModule($this->currentCompany(), 'purchases');
    }

    public function profitabilityEnabled(): bool
    {
        return $this->canViewCosts()
            && app(CompanyPlanResolver::class)->hasFeature($this->currentCompany(), 'reports.profitability');
    }

    public function summaryCards(): array
    {
        return app(OperationalReportService::class)->summaryCards(
            $this->currentCompany(),
            $this->filters(),
            $this->profitabilityEnabled(),
            $this->creditEnabled(),
            $this->loyaltyEnabled(),
            $this->promotionsEnabled(),
        );
    }

    public function salesTrend(): \Illuminate\Support\Collection
    {
        return app(OperationalReportService::class)->salesTrend($this->currentCompany(), $this->filters());
    }

    public function branchBreakdown(): \Illuminate\Support\Collection
    {
        return app(OperationalReportService::class)->branchBreakdown($this->currentCompany(), $this->filters());
    }

    public function topProducts(): \Illuminate\Support\Collection
    {
        return app(OperationalReportService::class)->topProducts(
            $this->currentCompany(),
            $this->filters(),
            $this->canViewCosts()
        );
    }

    public function recentPromotions(): \Illuminate\Support\Collection
    {
        if (! $this->promotionsEnabled()) {
            return collect();
        }

        return app(OperationalReportService::class)->recentPromotions($this->currentCompany());
    }

    public function paymentMethodBreakdown(): \Illuminate\Support\Collection
    {
        return app(OperationalReportService::class)->paymentMethodBreakdown($this->currentCompany(), $this->filters());
    }

    public function purchaseSummaryCards(): array
    {
        if (! $this->purchasesEnabled()) {
            return [];
        }

        return app(PurchaseReportService::class)->summaryCards($this->currentCompany(), $this->filters());
    }

    public function purchasesTrend(): \Illuminate\Support\Collection
    {
        if (! $this->purchasesEnabled()) {
            return collect();
        }

        return app(PurchaseReportService::class)->purchasesTrend($this->currentCompany(), $this->filters());
    }

    public function purchasePaymentMethodBreakdown(): \Illuminate\Support\Collection
    {
        if (! $this->purchasesEnabled()) {
            return collect();
        }

        return app(PurchaseReportService::class)->paymentMethodBreakdown($this->currentCompany(), $this->filters());
    }

    public function topSuppliers(): \Illuminate\Support\Collection
    {
        if (! $this->purchasesEnabled()) {
            return collect();
        }

        return app(PurchaseReportService::class)->topSuppliers($this->currentCompany(), $this->filters());
    }

    /**
     * Contraste rapido "cuanto entro vs cuanto salio" en el rango filtrado,
     * para la franja que se muestra encima de las columnas Compras/Ventas.
     * Null cuando el plan no incluye compras: no hay nada contra que
     * contrastar.
     */
    public function periodBalance(): ?array
    {
        if (! $this->purchasesEnabled()) {
            return null;
        }

        $income = app(OperationalReportService::class)->salesTotalRaw($this->currentCompany(), $this->filters());
        $expense = app(PurchaseReportService::class)->purchasesTotalRaw($this->currentCompany(), $this->filters());

        return [
            'income' => Money::format($income),
            'expense' => Money::format($expense),
            'difference' => Money::format(abs($income - $expense)),
            'is_positive' => $income >= $expense,
        ];
    }

    public function creditAging(): \Illuminate\Support\Collection
    {
        if (! $this->creditEnabled()) {
            return collect();
        }

        return app(OperationalReportService::class)->creditAging($this->currentCompany(), $this->filters());
    }

    public function render(): View
    {
        return view('livewire.reports.operational-reports-page', [
            'branches' => $this->branches(),
            'cashRegisters' => $this->cashRegisters(),
            'summaryCards' => $this->summaryCards(),
            'branchBreakdown' => $this->branchBreakdown(),
            'topProducts' => $this->topProducts(),
            'paymentMethodBreakdown' => $this->paymentMethodBreakdown(),
            'creditAging' => $this->creditAging(),
            'recentPromotions' => $this->recentPromotions(),
            'salesTrend' => $this->salesTrend(),
            'purchaseSummaryCards' => $this->purchaseSummaryCards(),
            'purchasesTrend' => $this->purchasesTrend(),
            'purchasePaymentMethodBreakdown' => $this->purchasePaymentMethodBreakdown(),
            'topSuppliers' => $this->topSuppliers(),
            'periodBalance' => $this->periodBalance(),
            'purchasesEnabled' => $this->purchasesEnabled(),
            'creditEnabled' => $this->creditEnabled(),
            'loyaltyEnabled' => $this->loyaltyEnabled(),
            'promotionsEnabled' => $this->promotionsEnabled(),
            'profitabilityEnabled' => $this->profitabilityEnabled(),
        ])->layout('layouts.app', [
            'header' => view('components.page-title', [
                'title' => 'Reportes',
                'description' => 'Consulta ventas, recaudo, cartera, fidelizacion y actividad promocional con filtros operativos.',
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

    protected function filters(): array
    {
        return [
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'branch_id' => $this->branchId,
            'cash_register_id' => $this->cashRegisterId,
        ];
    }
}
