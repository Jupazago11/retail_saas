<?php

namespace App\Livewire\Reports;

use App\Models\Branch;
use App\Models\Company;
use App\Services\Plans\CompanyPlanResolver;
use App\Services\Reports\OperationalReportService;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Component;

class OperationalReportsPage extends Component
{
    public string $dateFrom = '';
    public string $dateTo = '';
    public ?int $branchId = null;

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

    public function canViewCosts(): bool
    {
        return auth()->user()?->hasCurrentCompanyPermission('reports.view_costs') ?? false;
    }

    public function creditEnabled(): bool
    {
        return app(CompanyPlanResolver::class)->hasModule($this->currentCompany(), 'credit');
    }

    public function loyaltyEnabled(): bool
    {
        return app(CompanyPlanResolver::class)->hasModule($this->currentCompany(), 'loyalty');
    }

    public function promotionsEnabled(): bool
    {
        return app(CompanyPlanResolver::class)->hasModule($this->currentCompany(), 'promotions');
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

    public function creditAging(): \Illuminate\Support\Collection
    {
        if (! $this->creditEnabled()) {
            return collect();
        }

        return app(OperationalReportService::class)->creditAging($this->currentCompany(), $this->filters());
    }

    public function exportUrl(string $dataset): string
    {
        return route('reports.export', array_filter([
            'dataset' => $dataset,
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'branch_id' => $this->branchId,
        ], fn ($value) => $value !== null && $value !== ''));
    }

    public function render(): View
    {
        return view('livewire.reports.operational-reports-page', [
            'branches' => $this->branches(),
            'summaryCards' => $this->summaryCards(),
            'branchBreakdown' => $this->branchBreakdown(),
            'topProducts' => $this->topProducts(),
            'paymentMethodBreakdown' => $this->paymentMethodBreakdown(),
            'creditAging' => $this->creditAging(),
            'recentPromotions' => $this->recentPromotions(),
            'salesTrend' => $this->salesTrend(),
            'creditEnabled' => $this->creditEnabled(),
            'loyaltyEnabled' => $this->loyaltyEnabled(),
            'promotionsEnabled' => $this->promotionsEnabled(),
            'profitabilityEnabled' => $this->profitabilityEnabled(),
        ])->layout('layouts.app', [
            'header' => view('components.page-title', [
                'title' => 'Reportes',
                'description' => 'Consulta ventas, recaudo, cartera, fidelizacion y actividad promocional con filtros operativos y exportacion CSV.',
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
        ];
    }
}
