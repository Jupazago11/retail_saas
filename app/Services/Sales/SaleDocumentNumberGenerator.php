<?php

namespace App\Services\Sales;

use App\Models\Company;
use App\Models\Sale;

class SaleDocumentNumberGenerator
{
    public const PAD_LENGTH = 6;

    public function nextForCompany(Company $company): array
    {
        $lastSequence = (int) Sale::query()
            ->where('company_id', $company->id)
            ->whereNotNull('document_sequence')
            ->orderByDesc('document_sequence')
            ->lockForUpdate()
            ->value('document_sequence');
        $nextSequence = $lastSequence + 1;

        return [
            'document_sequence' => $nextSequence,
            // Solo numeros, sin prefijo de letras (se probo un prefijo
            // auto-derivado del nombre de la empresa y se descarto: ademas
            // de que no aportaba nada que $company_id ya no resuelva, un
            // separador como "-" es justo lo que un lector laser puede
            // mandar mal segun el layout de teclado del equipo (ver el caso
            // real "BAS-000007" leido como "BAS'000007"). Sin letras ni
            // separador, un scanner no tiene nada que traducir mal.
            'document_number' => $this->format('', $nextSequence),
        ];
    }

    // $prefix se mantiene en la firma (aunque ya no se use en
    // nextForCompany) porque una migracion historica
    // (2026_06_18_101500_add_internal_document_fields_to_sales_table) llama
    // a este metodo con 2 argumentos — cambiar la firma rompe ese replay.
    public function format(string $prefix, int $sequence): string
    {
        return $prefix . str_pad((string) $sequence, self::PAD_LENGTH, '0', STR_PAD_LEFT);
    }
}
