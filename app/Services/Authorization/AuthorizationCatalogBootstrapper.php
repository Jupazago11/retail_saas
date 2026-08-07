<?php

namespace App\Services\Authorization;

use App\Enums\RecordStatus;
use App\Models\Permission;
use App\Models\RoleTemplate;
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

            $permissionIds = Permission::query()
                ->pluck('id', 'code')
                ->all();

            RoleTemplate::query()->upsert(
                collect(PermissionCatalog::roleTemplates())
                    ->map(fn (array $template) => [
                        'code' => $template['code'],
                        'name' => $template['name'],
                        'scope' => $template['scope'],
                        'status' => RecordStatus::Active->value,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])
                    ->all(),
                ['code'],
                ['name', 'scope', 'status', 'updated_at']
            );

            $templateIds = RoleTemplate::query()
                ->pluck('id', 'code')
                ->all();

            $pairs = collect(PermissionCatalog::roleTemplates())
                ->flatMap(function (array $template) use ($templateIds, $permissionIds, $now) {
                    $templateId = $templateIds[$template['code']] ?? null;

                    if (! $templateId) {
                        return [];
                    }

                    return collect($template['permissions'])
                        ->filter(fn (string $code) => isset($permissionIds[$code]))
                        ->map(fn (string $code) => [
                            'role_template_id' => $templateId,
                            'permission_id' => $permissionIds[$code],
                            'created_at' => $now,
                            'updated_at' => $now,
                        ])
                        ->all();
                })
                ->values()
                ->all();

            if ($pairs !== []) {
                DB::table('role_template_permissions')->upsert(
                    $pairs,
                    ['role_template_id', 'permission_id'],
                    ['updated_at']
                );
            }

            $ownerTemplateId = $templateIds['owner'] ?? null;

            if ($ownerTemplateId) {
                DB::table('company_user')
                    ->where('company_role', 'owner')
                    ->whereNull('role_template_id')
                    ->update([
                        'role_template_id' => $ownerTemplateId,
                        'updated_at' => $now,
                    ]);
            }
        });
    }
}
