<?php

namespace App\Services\Credit;

use App\Models\Company;
use App\Models\CreditAccount;
use App\Models\Customer;
use App\Services\Plans\CompanyPlanResolver;
use App\Services\Settings\CompanySettings;
use InvalidArgumentException;

class CreditAccountResolver
{
    public function __construct(
        protected CompanySettings $companySettings,
        protected CompanyPlanResolver $companyPlanResolver,
        protected CreditLedger $creditLedger,
    ) {}

    /**
     * Resuelve la cuenta de credito que debe recibir un cargo (venta 100% a
     * credito o porcion a credito de un pago mixto). Siempre lanza si algo
     * en la cadena de validacion no permite el cargo — nunca hay un cargo
     * "silencioso" sin cuenta valida detras.
     */
    public function resolveActiveAccount(Company $company, ?Customer $customer): CreditAccount
    {
        if (
            ! $this->companyPlanResolver->hasModule($company, 'credit')
            || ! $this->companyPlanResolver->hasFeature($company, 'credit.enabled')
        ) {
            throw new InvalidArgumentException('El plan actual no tiene habilitado el modulo de credito.');
        }

        if (! $this->companySettings->get($company, 'credit', 'credit_enabled')) {
            throw new InvalidArgumentException('La empresa no tiene habilitado el modulo de credito.');
        }

        if ($this->companySettings->get($company, 'pos', 'require_customer_for_credit_sale') && ! $customer) {
            throw new InvalidArgumentException('La venta a credito requiere un cliente.');
        }

        if (! $customer) {
            throw new InvalidArgumentException('La venta a credito requiere un cliente.');
        }

        if (! $customer->credit_enabled) {
            throw new InvalidArgumentException('El cliente no tiene credito habilitado.');
        }

        $creditAccount = $customer->creditAccount()->first();

        if (! $creditAccount) {
            throw new InvalidArgumentException('El cliente no tiene una cuenta de credito creada.');
        }

        if ($creditAccount->status !== 'active') {
            throw new InvalidArgumentException('La cuenta de credito del cliente no esta activa.');
        }

        if (
            $this->companySettings->get($company, 'credit', 'block_new_credit_if_overdue')
            && $this->creditLedger->hasOverdueBalance($creditAccount)
        ) {
            throw new InvalidArgumentException('El cliente tiene cartera vencida y la empresa bloquea nuevos creditos.');
        }

        return $creditAccount;
    }

    // `payment_term_days` en la cuenta permite que un cliente puntual tenga
    // 15 dias y otro 45, en vez de forzar el mismo plazo para toda la
    // cartera via credit.default_term_days.
    public function resolveTermDays(Company $company, CreditAccount $account): int
    {
        return $account->payment_term_days
            ?? (int) $this->companySettings->get($company, 'credit', 'default_term_days');
    }
}
