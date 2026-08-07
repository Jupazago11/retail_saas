<?php

namespace App\Livewire\Loyalty;

use App\Actions\Loyalty\ExpireLoyaltyPoints;
use App\Actions\Loyalty\AdjustLoyaltyPoints;
use App\Enums\LoyaltyAccountStatus;
use App\Enums\LoyaltyMovementType;
use App\Livewire\Concerns\InteractsWithToast;
use App\Models\Company;
use App\Models\LoyaltyAccount;
use App\Models\LoyaltyMovement;
use App\Services\Plans\CompanyPlanResolver;
use App\Services\Settings\CompanySettings;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Component;

class LoyaltyAccountsPage extends Component
{
    use InteractsWithToast;

    protected ?Collection $accountsCache = null;
    protected ?Collection $recentMovementsCache = null;

    public string $search = '';
    public string $statusFilter = '';
    public string $movementTypeFilter = '';
    public string $expirationAsOf = '';
    public ?int $adjustmentAccountId = null;
    public string $adjustmentType = 'credit';
    public string $adjustmentReasonCode = 'admin_correction';
    public string $adjustmentPoints = '';
    public string $adjustmentNotes = '';

    public function mount(): void
    {
        abort_unless(
            app(CompanyPlanResolver::class)->hasFeature($this->currentCompany(), 'loyalty.enabled'),
            403,
            'El plan actual no tiene habilitado el modulo de fidelizacion.'
        );

        $this->ensurePermission('loyalty.manage');
        $this->expirationAsOf = now()->format('Y-m-d');
    }

    public function accounts(): Collection
    {
        return $this->accountsCache ??= LoyaltyAccount::query()
            ->where('company_id', $this->currentCompany()->id)
            ->with([
                'customer.person',
                'movements' => fn ($query) => $query
                    ->with('sale')
                    ->orderByDesc('occurred_at')
                    ->orderByDesc('id'),
            ])
            ->when($this->search !== '', function (Builder $query) {
                $search = '%'.trim($this->search).'%';

                $query->whereHas('customer.person', function (Builder $personQuery) use ($search) {
                    $personQuery
                        ->whereLike('first_name', $search)
                        ->orWhereLike('last_name', $search)
                        ->orWhereLike('document_number', $search);
                });
            })
            ->when($this->statusFilter !== '', fn (Builder $query) => $query->where('status', $this->statusFilter))
            ->orderByDesc('points_balance')
            ->orderByDesc('id')
            ->get();
    }

    public function recentMovements(): Collection
    {
        return $this->recentMovementsCache ??= LoyaltyMovement::query()
            ->where('company_id', $this->currentCompany()->id)
            ->with([
                'loyaltyAccount.customer.person',
                'sale',
            ])
            ->when($this->search !== '', function (Builder $query) {
                $search = '%'.trim($this->search).'%';

                $query->where(function (Builder $nested) use ($search) {
                    $nested
                        ->whereHas('loyaltyAccount.customer.person', function (Builder $personQuery) use ($search) {
                            $personQuery
                                ->whereLike('first_name', $search)
                                ->orWhereLike('last_name', $search)
                                ->orWhereLike('document_number', $search);
                        })
                        ->orWhereLike('sale_id', $search);
                });
            })
            ->when($this->movementTypeFilter !== '', fn (Builder $query) => $query->where('movement_type', $this->movementTypeFilter))
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(20)
            ->get();
    }

    public function summaryCards(): array
    {
        $accounts = $this->accounts();
        $movements = $this->recentMovements();

        return [
            'accounts_count' => $accounts->count(),
            'active_count' => $accounts->where('status', LoyaltyAccountStatus::Active->value)->count(),
            'points_balance_total' => number_format((float) $accounts->sum(fn (LoyaltyAccount $account) => (float) $account->points_balance), 4, '.', ','),
            'movements_count' => $movements->count(),
        ];
    }

    public function settingsSnapshot(): array
    {
        $company = $this->currentCompany();
        $settings = app(CompanySettings::class);

        return [
            'enabled' => (bool) $settings->get($company, 'loyalty', 'loyalty_enabled'),
            'rule_type' => (string) $settings->get($company, 'loyalty', 'points_rule_type'),
            'points_rate' => (string) $settings->get($company, 'loyalty', 'points_rate'),
            'expiration_days' => (int) $settings->get($company, 'loyalty', 'points_expiration_days'),
        ];
    }

    public function expirePoints(ExpireLoyaltyPoints $expireLoyaltyPoints): void
    {
        $this->ensurePermission('loyalty.manage');

        $validated = $this->validate([
            'expirationAsOf' => ['required', 'date'],
        ]);
        $settings = $this->settingsSnapshot();

        if (! $settings['enabled']) {
            $this->toast('La fidelizacion esta deshabilitada para la empresa activa.', 'warning');

            return;
        }

        if ($settings['expiration_days'] <= 0) {
            $this->toast('La expiracion automatica esta deshabilitada en configuracion.', 'warning');

            return;
        }

        $movements = $expireLoyaltyPoints->handle($this->currentCompany(), $validated['expirationAsOf']);

        if ($movements->isEmpty()) {
            $this->toast('No hubo puntos por expirar para la fecha indicada.', 'info');

            return;
        }

        $this->toast('Expiracion ejecutada correctamente sobre '.$movements->count().' cuenta(s).');
    }

    public function startAdjustingAccount(int $accountId): void
    {
        $this->ensurePermission('loyalty.manage');
        $this->accountQuery()->findOrFail($accountId);

        $this->adjustmentAccountId = $accountId;
        $this->adjustmentType = 'credit';
        $this->adjustmentReasonCode = 'admin_correction';
        $this->adjustmentPoints = '';
        $this->adjustmentNotes = '';
        $this->resetValidation();
    }

    public function cancelAdjustment(): void
    {
        $this->adjustmentAccountId = null;
        $this->adjustmentType = 'credit';
        $this->adjustmentReasonCode = 'admin_correction';
        $this->adjustmentPoints = '';
        $this->adjustmentNotes = '';
        $this->resetValidation();
    }

    public function applyAdjustment(AdjustLoyaltyPoints $adjustLoyaltyPoints): void
    {
        $this->ensurePermission('loyalty.manage');

        $validated = $this->validate([
            'adjustmentAccountId' => ['required', 'integer'],
            'adjustmentType' => ['required', 'in:credit,debit'],
            'adjustmentReasonCode' => ['required', 'in:service_recovery,promotion_compensation,migration_adjustment,admin_correction,fraud_correction'],
            'adjustmentPoints' => ['required', 'numeric', 'gt:0'],
            'adjustmentNotes' => ['nullable', 'string', 'max:500'],
        ]);

        $account = $this->accountQuery()->findOrFail((int) $validated['adjustmentAccountId']);

        try {
            $movement = $adjustLoyaltyPoints->handle(
                $this->currentCompany(),
                $account,
                [
                    'type' => $validated['adjustmentType'],
                    'reason_code' => $validated['adjustmentReasonCode'],
                    'points' => $validated['adjustmentPoints'],
                    'notes' => $validated['adjustmentNotes'],
                ],
                auth()->user(),
            );
        } catch (\InvalidArgumentException $exception) {
            $this->addError('adjustmentPoints', $exception->getMessage());

            return;
        }

        if (! $movement) {
            $this->addError('adjustmentPoints', 'El ajuste debe ser mayor a cero.');

            return;
        }

        $this->cancelAdjustment();
        $this->toast('Ajuste manual aplicado correctamente.');
    }

    public function statusLabel(string $status): string
    {
        return match ($status) {
            LoyaltyAccountStatus::Active->value => 'Activa',
            LoyaltyAccountStatus::Blocked->value => 'Bloqueada',
            LoyaltyAccountStatus::Closed->value => 'Cerrada',
            default => $status,
        };
    }

    public function movementLabel(string $movementType): string
    {
        return match ($movementType) {
            LoyaltyMovementType::Earn->value => 'Acumulacion',
            LoyaltyMovementType::Redeem->value => 'Redencion',
            LoyaltyMovementType::ManualCredit->value => 'Ajuste manual a favor',
            LoyaltyMovementType::ManualDebit->value => 'Ajuste manual en contra',
            LoyaltyMovementType::Expiration->value => 'Expiracion',
            LoyaltyMovementType::SaleReturnReversal->value => 'Reverso por devolucion',
            LoyaltyMovementType::SaleCancellationReversal->value => 'Reverso por anulacion',
            LoyaltyMovementType::SaleReturnRedemptionRestore->value => 'Restitucion por devolucion',
            LoyaltyMovementType::SaleCancellationRedemptionRestore->value => 'Restitucion por anulacion',
            default => $movementType,
        };
    }

    public function movementDirection(string $movementType): int
    {
        return match ($movementType) {
            LoyaltyMovementType::Earn->value,
            LoyaltyMovementType::ManualCredit->value,
            LoyaltyMovementType::SaleReturnRedemptionRestore->value,
            LoyaltyMovementType::SaleCancellationRedemptionRestore->value => 1,
            LoyaltyMovementType::Redeem->value,
            LoyaltyMovementType::ManualDebit->value,
            LoyaltyMovementType::Expiration->value,
            LoyaltyMovementType::SaleReturnReversal->value,
            LoyaltyMovementType::SaleCancellationReversal->value => -1,
            default => 0,
        };
    }

    public function ruleTypeLabel(string $ruleType): string
    {
        return match ($ruleType) {
            'per_currency' => 'Puntos por moneda',
            default => $ruleType,
        };
    }

    public function adjustmentReasonLabel(string $reasonCode): string
    {
        return match ($reasonCode) {
            'service_recovery' => 'Recuperacion de servicio',
            'promotion_compensation' => 'Compensacion promocional',
            'migration_adjustment' => 'Ajuste por migracion',
            'admin_correction' => 'Correccion administrativa',
            'fraud_correction' => 'Correccion por fraude',
            default => $reasonCode,
        };
    }

    public function render(): View
    {
        return view('livewire.loyalty.loyalty-accounts-page', [
            'accounts' => $this->accounts(),
            'recentMovements' => $this->recentMovements(),
            'statusCards' => $this->summaryCards(),
            'settingsSnapshot' => $this->settingsSnapshot(),
        ])->layout('layouts.app', [
            'header' => view('components.page-title', [
                'title' => 'Fidelizacion',
                'description' => 'Consulta saldos de puntos, revisa el ledger por cliente y ejecuta expiraciones manuales sobre la configuracion vigente.',
            ]),
        ]);
    }

    protected function currentCompany(): Company
    {
        return app(CurrentCompany::class)->company()
            ?? abort(404, 'No hay una empresa activa seleccionada.');
    }

    protected function accountQuery()
    {
        return LoyaltyAccount::query()
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
