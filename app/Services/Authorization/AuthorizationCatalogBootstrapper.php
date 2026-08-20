<?php

namespace App\Services\Authorization;

use App\Enums\RecordStatus;
use App\Models\Permission;
use App\Support\Authorization\PermissionCatalog;
use Illuminate\Support\Facades\DB;

class AuthorizationCatalogBootstrapper
{
    public function ensureDefaults(): void
    {
        DB::transaction(function () {
            $now = now();

            Permission::query()->upsert(
                collect(PermissionCatalog::permissions())
                    ->map(fn (array $permission) => [
                        ...$permission,
                        'status' => RecordStatus::Active->value,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])
                    ->all(),
                ['code'],
                ['name', 'module_code', 'status', 'updated_at']
            );
        });
    }
}
