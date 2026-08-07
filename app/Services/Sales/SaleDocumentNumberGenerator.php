<?php

namespace App\Services\Sales;

use App\Models\Company;
use App\Models\Sale;
use App\Services\Settings\CompanySettings;

class SaleDocumentNumberGenerator
{
    public const PAD_LENGTH = 6;

    public function __construct(
        protected CompanySettings $companySettings,
    ) {
    }

    public function nextForCompany(Company $company): array
    {
        $prefix = $this->prefix($company);
        $configuredStartingSequence = $this->startingSequence($company);
        $lastSequence = (int) Sale::query()
            ->where('company_id', $company->id)
            ->whereNotNull('document_sequence')
            ->orderByDesc('document_sequence')
            ->lockForUpdate()
            ->value('document_sequence');
        $baseSequence = max($lastSequence, $configuredStartingSequence - 1);
        $nextSequence = $baseSequence + 1;

        return [
            'document_sequence' => $nextSequence,
            'document_number' => $this->format($prefix, $nextSequence),
        ];
    }

    public function format(string $prefix, int $sequence): string
    {
        return $prefix . str_pad((string) $sequence, self::PAD_LENGTH, '0', STR_PAD_LEFT);
    }

    protected function prefix(Company $company): string
    {
        $prefix = trim((string) $this->companySettings->get($company, 'pos', 'sale_document_prefix'));

        return $prefix !== '' ? $prefix : 'VTA-';
    }

    protected function startingSequence(Company $company): int
    {
        return max(1, (int) $this->companySettings->get($company, 'pos', 'sale_document_starting_sequence'));
    }
}
