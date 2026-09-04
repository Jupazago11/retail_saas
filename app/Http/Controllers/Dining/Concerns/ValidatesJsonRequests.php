<?php

namespace App\Http\Controllers\Dining\Concerns;

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * $request->validate() normalmente responde 422 en JSON cuando el request
 * la pide (Accept: application/json) — pero bootstrap/app.php restringe
 * shouldRenderJsonWhen() a rutas 'api/*', y estos controladores (endpoints
 * JSON llamados por fetch() desde el editor del plano, ver
 * DiningFloorPlanTablesController) viven fuera de ese prefijo. Sin este
 * helper, un request invalido termina en un redirect 302 a la pagina
 * anterior (el comportamiento web normal de ValidationException) en vez de
 * un 422 limpio — csrfFetch() en resources/js/app.js no sabe interpretar
 * eso. HttpResponseException es un caso especial que Laravel siempre
 * devuelve tal cual, sin pasar por shouldRenderJsonWhen.
 */
trait ValidatesJsonRequests
{
    protected function validateJson(Request $request, array $rules): array
    {
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            throw new HttpResponseException(response()->json([
                'message' => 'Los datos enviados no son validos.',
                'errors' => $validator->errors(),
            ], 422));
        }

        return $validator->validated();
    }
}
