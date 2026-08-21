<?php

namespace App\Services\Products;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenFoodFactsService
{
    private const BASE_URL = 'https://world.openfoodfacts.org/api/v3/product';
    // El formulario bloquea Codigo de barras/Nombre mientras esta consulta
    // esta en vuelo (ver products-page.blade.php, wire:loading.attr) — este
    // timeout es el limite real de cuanto puede durar ese bloqueo.
    private const TIMEOUT = 2;

    public function findName(string $barcode): ?string
    {
        $barcode = trim($barcode);

        if ($barcode === '') {
            return null;
        }

        try {
            $response = Http::timeout(self::TIMEOUT)
                ->withUserAgent('RetailSaaS/1.0 (jupazago11@gmail.com)')
                ->get(self::BASE_URL.'/'.$barcode, [
                    'fields' => 'product_name,product_name_es,quantity',
                ]);

            if (! $response->successful()) {
                return null;
            }

            $data = $response->json();

            if (($data['status'] ?? '') !== 'success') {
                return null;
            }

            return $this->extractName($data['product'] ?? []);
        } catch (\Throwable $e) {
            Log::warning('OpenFoodFacts lookup failed', [
                'barcode' => $barcode,
                'error'   => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function extractName(array $product): ?string
    {
        $name = $product['product_name_es'] ?? null;

        if (! $this->isUsable($name)) {
            $name = $product['product_name'] ?? null;
        }

        if (! $this->isUsable($name)) {
            return null;
        }

        $name     = trim($name);
        $quantity = $product['quantity'] ?? null;

        if ($this->isUsable($quantity)) {
            $name .= ' – '.trim($quantity);
        }

        return $name;
    }

    private function isUsable(?string $value): bool
    {
        return $value !== null && trim($value) !== '';
    }
}
