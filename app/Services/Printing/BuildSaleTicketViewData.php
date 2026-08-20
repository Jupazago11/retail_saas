<?php

namespace App\Services\Printing;

use App\Actions\Settings\UpdateCompanyLogo;
use App\Models\Company;
use App\Models\Sale;
use App\Services\Settings\CompanySettings;
use Picqer\Barcode\BarcodeGeneratorSVG;

class BuildSaleTicketViewData
{
    public function __construct(
        protected CompanySettings $companySettings,
        protected UpdateCompanyLogo $updateCompanyLogo,
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

        $ticketFormat = (string) $this->companySettings->get($company, 'printing', 'ticket_format');

        // El papel termico (58mm/80mm) no tiene ancho para nombres largos sin
        // romper la tabla de items; en letter_a4 sobra espacio de sobra.
        $shouldAbbreviate = in_array($ticketFormat, ['thermal_58mm', 'thermal_80mm'], true);

        // Un nombre corto que ya entra completo en una linea no necesita
        // recorte. El objetivo es que la mayor cantidad de nombres quede en
        // una sola linea, asi que el limite se mide en "ancho estimado"
        // (ver estimatedTextWidth) y no en cantidad de caracteres: contar
        // caracteres fallaba porque "Arroz Premium 1Kg" (17) no entraba en
        // una linea pero "Gaseosa Cola 1.5L" (tambien 17) si — la diferencia
        // es que "Premium" tiene letras anchas (m, r) que "Cola" no tiene.
        $inlineWidthLimit = match ($ticketFormat) {
            'thermal_58mm' => 6.0,
            'thermal_80mm' => 9.0,
            default => null,
        };

        return [
            'sale' => $sale,
            'company' => $company,
            'ticketFormat' => $ticketFormat,
            'barcodeSvg' => $this->buildBarcodeSvg($sale->document_number, $ticketFormat),
            'showLogo' => (bool) $this->companySettings->get($company, 'printing', 'show_logo'),
            // El ticket usa la version blanco/negro pre-procesada (no el
            // logo a color que ve la empresa en Reglas): asi no depende de
            // que la impresora termica sepa dithear grises/colores bien.
            'logoPath' => $this->updateCompanyLogo->currentPrintUrl($company),
            'companyPhone' => $this->companySettings->get($company, 'general', 'phone'),
            'companyAddress' => $this->companySettings->get($company, 'general', 'address'),
            'items' => $sale->items->map(function ($item) use ($shouldAbbreviate, $inlineWidthLimit) {
                $description = $item->description_snapshot
                    ?: $item->product?->name
                    ?: 'Item';

                if ($shouldAbbreviate && $this->estimatedTextWidth($description) > $inlineWidthLimit) {
                    $description = $this->abbreviateWords($description);
                }

                if ($item->variant?->sku) {
                    $description .= ' / ' . $item->variant->sku;
                }

                if ($item->presentation?->name) {
                    $presentationName = $item->presentation->name;

                    if ($shouldAbbreviate && $this->estimatedTextWidth($presentationName) > $inlineWidthLimit) {
                        $presentationName = $this->abbreviateWords($presentationName);
                    }

                    $description .= ' (' . $presentationName . ')';
                }

                return [
                    'description' => $description,
                    'quantity' => $this->formatQuantity((float) $item->quantity),
                    'unit_price' => \App\Support\Money::format((float) $item->unit_price),
                    'discount_amount' => \App\Support\Money::format((float) $item->discount_amount),
                    'line_total' => \App\Support\Money::format((float) $item->line_total),
                ];
            })->all(),
            // IVA que ya viene incluido en el total (no se suma aparte): se
            // desglosa solo para informar al cliente cuanto de lo pagado es
            // impuesto. Distinto de $sale->tax_total (impuesto aditivo, hoy
            // sin uso real en ningun flujo de venta).
            'taxIncludedTotal' => \App\Support\Money::format((float) $this->includedTaxTotal($sale)),
        ];
    }

    protected function includedTaxTotal(Sale $sale): string
    {
        return $sale->items->reduce(
            fn (string $carry, $item) => bcadd($carry, $this->includedTaxAmount(
                (string) $item->line_total,
                (string) ($item->product_tax_rate ?? '0'),
            ), 2),
            '0.00',
        );
    }

    protected function includedTaxAmount(string $amount, string $ratePercent): string
    {
        if (bccomp($ratePercent, '0', 2) <= 0) {
            return '0.00';
        }

        $divisor = bcadd('1', bcdiv($ratePercent, '100', 6), 6);
        $net = bcdiv($amount, $divisor, 6);

        return number_format((float) bcsub($amount, $net, 6), 2, '.', '');
    }

    // "1.00" -> "1", "0.750" -> "0.75": sin ceros de relleno, salvo que el
    // producto se venda por peso/fraccion, donde si importan los decimales.
    protected function formatQuantity(float $quantity): string
    {
        $formatted = rtrim(rtrim(number_format($quantity, 3, '.', ''), '0'), '.');

        return $formatted === '' ? '0' : $formatted;
    }

    // Estima el ancho visual de un texto en "unidades de caracter promedio"
    // (no en cantidad de caracteres) porque el nombre del producto se
    // imprime en negrita y con una fuente proporcional: una "m" pesa mucho
    // mas que una "i" o un espacio. Sin esto, dos nombres de igual longitud
    // ("Arroz Premium 1Kg" y "Gaseosa Cola 1.5L", ambos 17 caracteres)
    // terminan con resultados distintos en papel — uno entra en una linea
    // y el otro no — y no hay forma de distinguirlos solo contando letras.
    protected function estimatedTextWidth(string $text): float
    {
        $narrow = ['i', 'j', 'l', 'I', 't', 'f', 'r', '.', ',', ':', ';', "'", '|', '!'];
        $wide = ['m', 'M', 'W', 'w', 'G', 'O', 'Q', '@'];

        $width = 0.0;

        foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) as $char) {
            $width += match (true) {
                $char === ' ' => 0.3,
                in_array($char, $narrow, true) => 0.34,
                in_array($char, $wide, true) => 0.95,
                default => 0.58,
            };
        }

        return $width;
    }

    // Trunca cada palabra a $maxLength sin marcar el corte (ej.
    // "Semidescremada" -> "Semid") para ganar el caracter extra que el
    // punto costaba y ayudar a que el nombre entre en una sola linea; si
    // aun asi es muy largo, el word-break del CSS lo baja a una segunda
    // linea como maximo.
    protected function abbreviateWords(string $text, int $maxLength = 5): string
    {
        return collect(preg_split('/\s+/', trim($text)))
            ->map(fn (string $word) => mb_substr($word, 0, $maxLength))
            ->implode(' ');
    }

    protected function buildBarcodeSvg(?string $documentNumber, string $ticketFormat): ?string
    {
        if (! $documentNumber) {
            return null;
        }

        // El ancho "natural" del SVG (antes de cualquier escalado por CSS)
        // ya superaba los 48mm imprimibles del papel de 58mm — si el motor
        // de impresion no reescala el SVG igual que la pantalla (paso mas
        // por CSS que por el %), se imprime cortado. widthFactor mas chico
        // en 58mm genera un SVG angosto de entrada, sin depender de que el
        // escalado por CSS se respete al imprimir.
        $widthFactor = $ticketFormat === 'thermal_58mm' ? 1.0 : 1.5;

        return (new BarcodeGeneratorSVG())->getBarcode(
            $documentNumber,
            BarcodeGeneratorSVG::TYPE_CODE_128,
            widthFactor: $widthFactor,
            height: 36,
        );
    }
}
