<?php

namespace App\Services\Products;

use App\Models\ProductPresentation;
use InvalidArgumentException;

class ProductPresentationConverter
{
    public function toBaseQuantity(string $presentationQuantity, string $conversionFactor, int $scale = 6): string
    {
        $this->ensurePositiveFactor($conversionFactor);

        return bcmul($presentationQuantity, $conversionFactor, $scale);
    }

    public function fromBaseQuantity(string $baseQuantity, string $conversionFactor, int $scale = 6): string
    {
        $this->ensurePositiveFactor($conversionFactor);

        return bcdiv($baseQuantity, $conversionFactor, $scale);
    }

    public function presentationToBase(ProductPresentation $presentation, string $presentationQuantity, int $scale = 6): string
    {
        return $this->toBaseQuantity($presentationQuantity, (string) $presentation->conversion_factor, $scale);
    }

    public function baseToPresentation(ProductPresentation $presentation, string $baseQuantity, int $scale = 6): string
    {
        return $this->fromBaseQuantity($baseQuantity, (string) $presentation->conversion_factor, $scale);
    }

    protected function ensurePositiveFactor(string $conversionFactor): void
    {
        if (bccomp($conversionFactor, '0', 6) !== 1) {
            throw new InvalidArgumentException('El factor de conversion debe ser mayor que cero.');
        }
    }
}
