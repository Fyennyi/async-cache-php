<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Rate Limiter Type
    |--------------------------------------------------------------------------
    |
    | Supported: "auto", "symfony", "in_memory"
    |
    */
    'rate_limiter_type' => env('ASYNC_CACHE_RATE_LIMITER', 'auto'),

    /*
    |--------------------------------------------------------------------------
    | Default Cache Strategy
    |--------------------------------------------------------------------------
    |
    | Supported: "strict", "background", "force_refresh"
    |
    */
    'default_strategy' => 'strict',
];
