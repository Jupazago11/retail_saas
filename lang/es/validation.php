<?php

return [
    'accepted' => 'Debes aceptar :attribute.',
    'array' => 'El campo :attribute debe ser una lista.',
    'confirmed' => 'La confirmacion de :attribute no coincide.',
    'email' => 'El campo :attribute debe ser un correo electronico valido.',
    'image' => 'El archivo de :attribute debe ser una imagen (jpg, png, etc).',
    'max' => [
        'string' => 'El campo :attribute no debe superar :max caracteres.',
        'file' => 'El archivo de :attribute no debe pesar mas de :max kilobytes.',
    ],
    'min' => [
        'string' => 'El campo :attribute debe tener al menos :min caracteres.',
        'array' => 'Debes elegir al menos :min elemento(s) para :attribute.',
    ],
    'required' => 'El campo :attribute es obligatorio.',
    'string' => 'El campo :attribute debe ser una cadena de texto.',
    'unique' => 'El valor de :attribute ya esta en uso.',

    'attributes' => [
        'email' => 'correo electronico',
        'name' => 'nombre completo',
        'password' => 'contrasena',
        'password_confirmation' => 'confirmacion de contrasena',
        'token' => 'token',
        'username' => 'usuario',
        'form.username' => 'usuario',
        'form.password' => 'contrasena',
        'newAttachments' => 'las fotos',
        'newAttachments.*' => 'la foto',
    ],
];
