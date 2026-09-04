<?php

namespace App\Actions\Companies;

use App\Enums\RecordStatus;
use App\Models\Company;
use App\Models\CompanyRole;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Crea 1 usuario interno por cada rol base del vertical restaurante
 * (Cajero, Mesero, Cocina) creado por ProvisionDefaultRestaurantRoles, para
 * que la empresa arranque con esas 3 cuentas ya utilizables el primer dia
 * en vez de que el dueño tenga que crearlas a mano una por una.
 *
 * Username = "{codigo_rol}.{company_id}" (ej. "cajero.42") — nunca el
 * codigo del rol solo, porque username es unico en toda la plataforma y
 * "cajero" a secas solo podria pertenecer a una empresa. Password inicial =
 * el mismo username (nada que generar ni mostrar en ningun lado); junto con
 * must_change_password=true (que ya pone CreateInternalUserForCompany), el
 * empleado real cambia esa clave el primer login via RequirePasswordChange
 * — el dueño solo necesita comunicarle el username.
 *
 * Idempotente por diseño: si el rol ya tiene algun usuario vinculado
 * (activo o inactivo, sin importar si lo creo esta accion o el dueño a
 * mano), no crea uno nuevo. Eso permite invocarla tanto al activar/cambiar
 * el tipo de negocio como, de forma preventiva, cada vez que se abre
 * Admin > Roles y Usuarios — asi las empresas restaurante que ya estaban
 * activas antes de que existiera esta accion tambien terminan con sus 3
 * cuentas base sin necesidad de un backfill aparte.
 */
class ProvisionDefaultRestaurantUsers
{
    public function __construct(
        protected CreateInternalUserForCompany $createInternalUserForCompany,
    ) {
    }

    public function handle(Company $company): void
    {
        $roles = CompanyRole::query()
            ->where('company_id', $company->id)
            ->where('status', RecordStatus::Active->value)
            ->whereIn('code', array_keys(ProvisionDefaultRestaurantRoles::ROLES))
            ->get();

        foreach ($roles as $role) {
            $hasUser = DB::table('company_user')
                ->where('company_role_id', $role->id)
                ->exists();

            if ($hasUser) {
                continue;
            }

            $username = strtolower($role->code).'.'.$company->id;

            try {
                $this->createInternalUserForCompany->handle($company, [
                    'name' => $role->display_name,
                    'username' => $username,
                    // Password inicial = username (ver docblock de la clase).
                    'password' => $username,
                    'company_role_id' => $role->id,
                ]);
            } catch (InvalidArgumentException) {
                // Cupo de usuarios del plan agotado: el rol se queda sin
                // cuenta por ahora, el dueño la crea a mano cuando libere
                // cupo (misma pantalla, mismo formulario de siempre).
                continue;
            }
        }
    }
}
