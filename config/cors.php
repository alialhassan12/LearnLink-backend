<?php

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => env('ALLOWED_ORIGINS') ? explode(',', env('ALLOWED_ORIGINS')) : ['http://localhost:5173', 'https://learnlink-lb.vercel.app'],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];