<?php

namespace App\Services\Printing;

use App\Actions\Settings\UpdateCompanyLogo;
use App\Models\Company;
use App\Models\DiningTable;
use App\Services\Settings\CompanySettings;
use App\Support\Money;

/**
 * Arma los datos de la "cuenta" de una mesa TODAVIA sin cobrar: mismo
 * formato visual que el ticket de una venta real (ver
 * printing/partials/ticket-styles.blade.php), pero leyendo directo del
 * payload_snapshot de la FrozenSale abierta — no existe un Sale/document
 * number todavia, por eso no hay codigo de barras ni QR de recibo aca.
 */
class BuildDiningOrderPreviewTicketData
{
    public function __construct(
        protected CompanySettings $companySettings,
        protected UpdateCompanyLogo $updateCompanyLogo,
    ) {
    }

    public function handle(Company $company, DiningTable $table): array
    {
        $frozenSale = $table->openFrozenSale();
        $payload = $frozenSale?->payload_snapshot ?? ['items' => [], 'totals' => ['subtotal' => '0.00', 'discount_total' => '0.00', 'tax_total' => '0.00', 'grand_total' => '0.00']];

        $ticketFormat = (string) (
            $frozenSale?->cashRegister?->printer_type
            ?? $company->cashRegisters()->where('is_primary', true)->value('printer_type')
            ?? 'thermal_80mm'
        );

        return [
            'table' => $table,
            'company' => $company,
            'ticketFormat' => $ticketFormat,
            'showLogo' => (bool) $this->companySettings->get($company, 'printing', 'show_logo'),
            'logoPath' => $this->updateCompanyLogo->currentPrintUrl($company),
            'companyPhone' => $this->companySettings->get($company, 'general', 'phone'),
            'companyAddress' => $this->companySettings->get($company, 'general', 'address'),
            'branchName' => $frozenSale?->branch?->name,
            'servedBy' => $frozenSale?->creator?->name,
            'generatedAt' => now(),
            'items' => collect($payload['items'] ?? [])->map(function (array $line) {
                return [
                    'description' => $line['description_snapshot'] ?? 'Item',
                    'quantity' => $this->formatQuantity((float) ($line['quantity'] ?? 0)),
                    'unit_price' => Money::format((float) ($line['unit_price'] ?? 0)),
                    'line_total' => Money::format((float) ($line['line_total'] ?? 0)),
                ];
            })->all(),
            'subtotal' => Money::format((float) ($payload['totals']['subtotal'] ?? 0)),
            'taxTotal' => Money::format((float) ($payload['totals']['tax_total'] ?? 0)),
            'grandTotal' => Money::format((float) ($payload['totals']['grand_total'] ?? 0)),
        ];
    }

    protected function formatQuantity(float $quantity): string
    {
        $formatted = rtrim(rtrim(number_format($quantity, 3, '.', ''), '0'), '.');

        return $formatted === '' ? '0' : $formatted;
    }
}
