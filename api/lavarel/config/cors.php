<?php

return [

    'paths' => ['api/*'], // habilitar CORS para /api/*
    'allowed_methods' => ['*'],
    'allowed_origins' => ['*'], // en producción restringe a tu dominio frontend
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,

];