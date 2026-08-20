<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Las "plantillas" de rol (role_templates) se eliminan: cada empresa
     * arma sus propios roles personalizados (company_roles) desde cero, sin
     * catalogo global compartido entre empresas. Este backfill preserva los
     * permisos de cualquier usuario que ya estuviera vinculado via
     * plantilla, creando un rol personalizado equivalente en su empresa
     * antes de que la siguiente migracion borre la columna y las tablas.
     *
     * Los propietarios (company_role = 'owner') se saltan a proposito: su
     * acceso total esta hardcodeado en CurrentCompanyPermissionResolver por
     * la columna company_role, no depende de ninguna plantilla ni rol.
     */
    public function up(): void
    {
        $assignments = DB::table('company_user')
            ->whereNotNull('role_template_id')
            ->where('company_role', '!=', 'owner')
            ->get(['company_id', 'user_id', 'role_template_id']);

        if ($assignments->isEmpty()) {
            return;
        }

        $templates = DB::table('role_templates')->get()->keyBy('id');
        $now = now();

        // (company_id, role_template_id) -> company_role_id ya creado, para
        // no duplicar el rol si varios usuarios de la misma empresa usaban
        // la misma plantilla.
        $createdRoles = [];

        foreach ($assignments as $assignment) {
            $template = $templates->get($assignment->role_template_id);

            if (! $template) {
                continue;
            }

            $key = $assignment->company_id.':'.$assignment->role_template_id;

            if (! isset($createdRoles[$key])) {
                $existing = DB::table('company_roles')
                    ->where('company_id', $assignment->company_id)
                    ->where('code', $template->code)
                    ->first();

                if ($existing) {
                    $companyRoleId = $existing->id;
                } else {
                    $companyRoleId = DB::table('company_roles')->insertGetId([
                        'company_id' => $assignment->company_id,
                        'code' => $template->code,
                        'display_name' => $template->name,
                        'status' => $template->status,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                $permissionIds = DB::table('role_template_permissions')
                    ->where('role_template_id', $assignment->role_template_id)
                    ->pluck('permission_id');

                foreach ($permissionIds as $permissionId) {
                    DB::table('company_role_permissions')->insertOrIgnore([
                        'company_role_id' => $companyRoleId,
                        'permission_id' => $permissionId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                $createdRoles[$key] = $companyRoleId;
            }

            DB::table('company_user')
                ->where('company_id', $assignment->company_id)
                ->where('user_id', $assignment->user_id)
                ->update([
                    'company_role_id' => $createdRoles[$key],
                    'company_role' => 'custom',
                    'updated_at' => $now,
                ]);
        }
    }

    public function down(): void
    {
        // Irreversible a proposito: no hay forma de distinguir con certeza
        // los company_roles creados solo por este backfill de los que un
        // usuario haya creado o editado a mano despues.
    }
};
