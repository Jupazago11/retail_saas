<?php

namespace App\Http\Middleware;

use App\Support\Money;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NormalizeMoneyInput
{
    private const MONEY_KEYWORDS = [
        'amount',
        'price',
        'cost',
        'total',
        'subtotal',
        'discount',
        'balance',
        'credit',
        'cash',
        'opening',
        'paid',
        'change',
        'equivalent',
        'limit',
    ];

    private const EXCLUDED_KEYWORDS = [
        'quantity',
        'tax_rate',
        'points',
        'stock',
        'minimum',
        'base_quantity',
        'rate',
        'percentage',
        'cashregister',
        'cashsession',
        // Plan limits (max_users, max_products, max_cash_registers, ...) are
        // plain integer counts, not currency, but their keys collide with the
        // "limit"/"cash" money keywords above.
        'editlimits',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $request->merge($this->sanitizeArray($request->all()));

        return $next($request);
    }

    private function sanitizeArray(array $payload): array
    {
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = $this->sanitizeArray($value);

                continue;
            }

            if (is_string($value) && $this->isMoneyKey((string) $key)) {
                $payload[$key] = Money::normalizeInput($value);
            }
        }

        return $payload;
    }

    private function isMoneyKey(string $key): bool
    {
        $normalized = strtolower($key);

        foreach (self::EXCLUDED_KEYWORDS as $excludedKeyword) {
            if (str_contains($normalized, $excludedKeyword)) {
                return false;
            }
        }

        foreach (self::MONEY_KEYWORDS as $moneyKeyword) {
            if (str_contains($normalized, $moneyKeyword)) {
                return true;
            }
        }

        return false;
    }
}
