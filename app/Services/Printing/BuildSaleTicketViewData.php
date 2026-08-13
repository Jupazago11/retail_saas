<?php

namespace App\Services\Printing;

use App\Models\Company;
use App\Models\Sale;
use App\Services\Settings\CompanySettings;
use Picqer\Barcode\BarcodeGeneratorSVG;

class BuildSaleTicketViewData
{
    public function __construct(
        protected CompanySettings $companySettings,
    ) {
    }

    public function handle(Company $company, Sale $sale): array
    {
        $sale->loadMissing([
            'items.product',
            'items.presentation',
            'items.variant',
            'branch',
            'cashRegister',
            'customer.person',
            'user',
        ]);

        return [
            'sale' => $sale,
            'company' => $company,
            'ticketFormat' => (string) $this->companySettings->get($company, 'printing', 'ticket_format'),
            'barcodeSvg' => $this->buildBarcodeSvg($sale->document_number),
            'showLogo' => (bool) $this->companySettings->get($company, 'printing', 'show_logo'),
            'showSaasBranding' => (bool) $this->companySettings->get($company, 'printing', 'show_saas_branding'),
            'logoPath' => $this->companySettings->get($company, 'general', 'logo_path'),
            'companyPhone' => $this->companySettings->get($company, 'general', 'phone'),
            'companyAddress' => $this->companySettings->get($company, 'general', 'address'),
            'items' => $sale->items->map(function ($item) {
                $description = $item->description_snapshot
                    ?: $item->product?->name
                    ?: 'Item';

                if ($item->variant?->sku) {
                    $description .= ' / ' . $item->variant->sku;
                }

                if ($item->presentation?->name) {
                    $description .= ' (' . $item->presentation->name . ')';
                }

                return [
                    'description' => $description,
                    'quantity' => number_format((float) $item->quantity, 2, '.', ''),
                    'unit_price' => \App\Support\Money::format((float) $item->unit_price),
                    'discount_amount' => \App\Support\Money::format((float) $item->discount_amount),
                    'line_total' => \App\Support\Money::format((float) $item->line_total),
                ];
            })->all(),
        ];
    }

    protected function buildBarcodeSvg(?string $documentNumber): ?string
    {
        if (! $documentNumber) {
            return null;
        }

        return (new BarcodeGeneratorSVG())->getBarcode(
            $documentNumber,
            BarcodeGeneratorSVG::TYPE_CODE_128,
            widthFactor: 1.5,
            height: 36,
        );
    }
}
