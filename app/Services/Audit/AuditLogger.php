<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AuditLogger
{
    public function logCreated(
        Company $company,
        string $action,
        Model $model,
        ?User $actor = null,
        ?string $ipAddress = null,
    ): AuditLog {
        return AuditLog::query()->create([
            'company_id' => $company->id,
            'actor_user_id' => $actor?->id,
            'action' => $action,
            'auditable_type' => $model::class,
            'auditable_id' => $model->getKey(),
            'before_snapshot' => null,
            'after_snapshot' => $this->snapshot($model),
            'ip_address' => $ipAddress,
        ]);
    }

    public function logUpdated(
        Company $company,
        string $action,
        Model $before,
        Model $after,
        ?User $actor = null,
        ?string $ipAddress = null,
    ): ?AuditLog {
        return $this->logSnapshot(
            $company,
            $action,
            $after::class,
            $after->getKey(),
            $this->snapshot($before),
            $this->snapshot($after),
            $actor,
            $ipAddress,
        );
    }

    public function logSnapshot(
        Company $company,
        string $action,
        string $auditableType,
        int|string|null $auditableId,
        ?array $beforeSnapshot,
        ?array $afterSnapshot,
        ?User $actor = null,
        ?string $ipAddress = null,
    ): ?AuditLog {
        if ($beforeSnapshot === $afterSnapshot) {
            return null;
        }

        return AuditLog::query()->create([
            'company_id' => $company->id,
            'actor_user_id' => $actor?->id,
            'action' => $action,
            'auditable_type' => $auditableType,
            'auditable_id' => $auditableId,
            'before_snapshot' => $beforeSnapshot,
            'after_snapshot' => $afterSnapshot,
            'ip_address' => $ipAddress,
        ]);
    }

    protected function snapshot(Model $model): array
    {
        return $model->withoutRelations()->attributesToArray();
    }
}
