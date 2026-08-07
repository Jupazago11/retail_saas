<?php

namespace App\Actions\Loyalty;

use App\Models\Company;
use App\Models\LoyaltyAccount;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Loyalty\LoyaltyLedger;
use App\Services\Settings\CompanySettings;
use InvalidArgumentException;

class AdjustLoyaltyPoints
{
    public function __construct(
        protected LoyaltyLedger $loyaltyLedger,
        protected CompanySettings $companySettings,
        protected AuditLogger $auditLogger,
    ) {
    }

    public function handle(
        Company $company,
        LoyaltyAccount $account,
        array $attributes,
        ?User $actor = null,
    ) {
        if ((int) $account->company_id !== (int) $company->id) {
            throw new InvalidArgumentException('La cuenta de fidelizacion no pertenece a la empresa activa.');
        }

        if (! $this->companySettings->get($company, 'loyalty', 'loyalty_enabled')) {
            throw new InvalidArgumentException('La empresa no tiene habilitado el modulo de fidelizacion.');
        }

        $type = trim((string) ($attributes['type'] ?? ''));
        $points = trim((string) ($attributes['points'] ?? '0'));
        $reasonCode = trim((string) ($attributes['reason_code'] ?? ''));
        $notes = $this->blankToNull($attributes['notes'] ?? null);

        if (! in_array($reasonCode, ['service_recovery', 'promotion_compensation', 'migration_adjustment', 'admin_correction', 'fraud_correction'], true)) {
            throw new InvalidArgumentException('Debes seleccionar un motivo valido para el ajuste manual.');
        }

        if ($type === 'debit' && ($notes === null || mb_strlen($notes) < 8)) {
            throw new InvalidArgumentException('Los descuentos manuales de puntos requieren una nota descriptiva de al menos 8 caracteres.');
        }

        $formattedNotes = '['.$reasonCode.']'.($notes ? ' '.$notes : '');
        $before = $account->fresh();

        $movement = match ($type) {
            'credit' => $this->loyaltyLedger->creditManually($company, $account, $points, $formattedNotes),
            'debit' => $this->loyaltyLedger->debitManually($company, $account, $points, $formattedNotes),
            default => throw new InvalidArgumentException('El tipo de ajuste de puntos no es valido.'),
        };

        $after = $account->fresh();
        $this->auditLogger->logUpdated($company, 'loyalty.manual_adjustment', $before, $after, $actor);

        return $movement;
    }

    protected function blankToNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
