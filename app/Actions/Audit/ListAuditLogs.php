<?php

namespace App\Actions\Audit;

use App\Models\AuditLog;
use App\Models\Company;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class ListAuditLogs
{
    public function handle(Company $company, array $filters = []): Collection
    {
        return $this->buildQuery($company, $filters)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
    }

    public function paginate(Company $company, array $filters, int $perPage, int $page): LengthAwarePaginator
    {
        return $this->buildQuery($company, $filters)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    protected function buildQuery(Company $company, array $filters): Builder
    {
        $query = AuditLog::query()
            ->where('company_id', $company->id)
            ->with('actor');

        $action = $this->blankToNull($filters['action'] ?? null);

        if ($action !== null) {
            $query->where('action', $action);
        }

        $actorUserId = $filters['actor_user_id'] ?? null;

        if ($actorUserId !== null && $actorUserId !== '') {
            $query->where('actor_user_id', (int) $actorUserId);
        }

        $auditableType = $this->blankToNull($filters['auditable_type'] ?? null);

        if ($auditableType !== null) {
            $query->where('auditable_type', $auditableType);
        }

        $auditableId = $filters['auditable_id'] ?? null;

        if ($auditableId !== null && $auditableId !== '') {
            $query->where('auditable_id', (int) $auditableId);
        }

        $dateFrom = $this->blankToNull($filters['date_from'] ?? null);

        if ($dateFrom !== null) {
            $query->whereDate('created_at', '>=', $this->normalizeDate($dateFrom));
        }

        $dateTo = $this->blankToNull($filters['date_to'] ?? null);

        if ($dateTo !== null) {
            $query->whereDate('created_at', '<=', $this->normalizeDate($dateTo));
        }

        $ipAddress = $this->blankToNull($filters['ip_address'] ?? null);

        if ($ipAddress !== null) {
            $query->whereLike('ip_address', '%'.$ipAddress.'%');
        }

        $search = $this->blankToNull($filters['search'] ?? null);

        if ($search !== null) {
            $query->where(function ($nested) use ($search) {
                $nested
                    ->whereLike('action', '%'.$search.'%')
                    ->orWhereLike('auditable_type', '%'.$search.'%')
                    ->orWhereLike('ip_address', '%'.$search.'%')
                    ->orWhereHas('actor', function ($actorQuery) use ($search) {
                        $actorQuery
                            ->whereLike('name', '%'.$search.'%')
                            ->orWhereLike('username', '%'.$search.'%');
                    });
            });
        }

        return $query;
    }

    protected function blankToNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    protected function normalizeDate(string $value): string
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            throw new InvalidArgumentException('Fecha invalida.');
        }

        return $value;
    }
}
