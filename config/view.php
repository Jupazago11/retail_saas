<?php

return [

    /*
    |--------------------------------------------------------------------------
    | View Storage Paths
    |--------------------------------------------------------------------------
    |
    | Most templating systems load templates from disk. Here you may specify
    | an array of paths that should be checked for your views. Of course
    | the usual Laravel view path has already been registered for you.
    |
    */

    'paths' => [
        resource_path('views'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Compiled View Path
    |--------------------------------------------------------------------------
    |
    | This option determines where all the compiled Blade templates will be
    | stored for your application. Typically, this is within the storage
    | directory. However, as usual, you are free to change this value.
    |
    | Sin realpath(): en un build efimero (Railway/Railpack) esa carpeta
    | puede no estar todavia legible/creada en el momento exacto en que
    | corre "php artisan config:cache" — realpath() sobre una ruta que no
    | resuelve devuelve false SILENCIOSAMENTE, y el compilador de Blade
    | truena con "Please provide a valid cache path". storage_path()
    | siempre devuelve un string valido sin importar si la carpeta ya
    | existe, evitando ese crash de raiz. Ver docs/requisitos-railway.md.
    |
    */

    'compiled' => env(
        'VIEW_COMPILED_PATH',
        storage_path('framework/views')
    ),

];
