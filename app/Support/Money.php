<?php

namespace App\Support;

class Money
{
    public static function format(mixed $value): string
    {
        $numeric = (float) ($value ?? 0);

        return number_format(round($numeric), 0, '', '.');
    }

    public static function normalizeInput(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $normalized = preg_replace('/[^0-9-]/', '', $value);

        if ($normalized === null || $normalized === '' || $normalized === '-') {
            return $value;
        }

        return $normalized;
    }
}
