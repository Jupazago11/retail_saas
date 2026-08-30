<?php

namespace App\Actions\Cash;

use App\Actions\Cash\Concerns\RecomputesClosedSessionTotals;
use App\Actions\Cash\Concerns\ResolvesDenominationBreakdown;
use App\Enums\CashSessionStatus;
use App\Models\CashSession;
use App\Models\Company;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Corrige el efectivo contado ("Contado") de una sesion YA CERRADA — por
 * ejemplo, un error de digitacion al cerrar la caja. Antes de esto, Bases y
 * Pagos de caja se podian corregir despues de cerrar (ManageCashSessionFunds/
 * ManageCashSessionExpenses) pero el monto contado quedaba fijo para
 * siempre: la unica forma de cambiarlo era escribirlo al cerrar la sesion,
 * y una vez cerrada no habia ningun campo editable para el, dejando al
 * usuario sin forma de corregirlo salvo "reabrir y volver a cerrar" — algo
 * que este sistema nunca soporto (el cierre es una transicion de un solo
 * sentido). Acepta el mismo desglose por denominacion que CloseCashSession
 * (o un monto suelto si el usuario prefiere escribirlo directo), para que
 * corregir un cierre se sienta igual que cerrarlo la primera vez.
 */
class UpdateCashSessionCount
{
    use RecomputesClosedSessionTotals;
    use ResolvesDenominationBreakdown;

    public function __construct(
        protected AuditLogger $auditLogger,
    ) {
    }

    public function handle(Company $company, CashSession $cashSession, array $attributes, User $actor): CashSession
    {
        if ($cashSession->company_id !== $company->id) {
            throw new InvalidArgumentException('La sesion de caja no pertenece a la empresa indicada.');
        }

        $denominationBreakdown = $this->resolveDenominationBreakdown($attributes);
        $countedAmount = $denominationBreakdown !== null
            ? $this->amountFromDenominationBreakdown($denominationBreakdown)
            : $this->normalizeAmount($attributes['closing_counted_amount'] ?? null);

        return DB::transaction(function () use ($company, $cashSession, $countedAmount, $denominationBreakdown, $actor) {
            $cashSession = CashSession::query()->lockForUpdate()->findOrFail($cashSession->id);

            if ($cashSession->status === CashSessionStatus::Open->value) {
                throw new InvalidArgumentException('Esta sesion todavia esta abierta; ciérrala primero.');
            }

            if (! $actor->hasCurrentCompanyPermission('settings.manage')) {
                throw new InvalidArgumentException('Esta sesion ya esta cerrada. Solo un administrador de la empresa puede corregir el efectivo contado.');
            }

            $before = $cashSession->withoutRelations()->attributesToArray();

            $cashSession->update([
                'closing_counted_amount' => $countedAmount,
                'closing_denomination_breakdown' => $denominationBreakdown,
            ]);

            $this->recomputeClosedTotalsIfNeeded($cashSession);

            $this->auditLogger->logSnapshot(
                $company,
                'cash_session.counted_amount_corrected',
                CashSession::class,
                $cashSession->id,
                $before,
                $cashSession->fresh()->withoutRelations()->attributesToArray(),
                $actor,
            );

            return $cashSession->fresh();
        });
    }

    protected function normalizeAmount(mixed $value): string
    {
        $value = trim((string) $value);

        if (! preg_match('/^\d+(?:\.\d+)?$/', $value)) {
            throw new InvalidArgumentException('El monto contado es invalido.');
        }

        return bcadd($value, '0', 2);
    }
}
