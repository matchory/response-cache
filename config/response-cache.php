<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Response Cache Safety Switch
    |--------------------------------------------------------------------------
    |
    | Using this setting, you can globally enable or disable the response cache
    | for all requests. If you set it to false, nothing will be cached.
    |
    */
    'enabled' => env('RESPONSE_CACHE_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Time to Live
    |--------------------------------------------------------------------------
    |
    | This setting specifies the amount of time responses will be cached before
    | being marked as stale. The value must be specified as seconds.
    |
    */
    'ttl' => env('RESPONSE_CACHE_TTL', 60 * 60 * 24),

    /*
    |--------------------------------------------------------------------------
    | Cache Store
    |--------------------------------------------------------------------------
    |
    | Here you may pass any cache store defined in your config/cache.php file.
    | Please note that not all stores support tagging: For better control over
    | and increased performance of your cache, we strongly recommend using a
    | store with tag support, like Redis or APC.
    |
    */
    'store' => env('RESPONSE_CACHE_STORE', 'file'),

    /*
    |--------------------------------------------------------------------------
    | Default Tags
    |--------------------------------------------------------------------------
    |
    | If your store supports tags, all responses cached will also be tagged
    | with these tags by default. Additional tags may be used depending on the
    | middleware and strategy.
    |
    */
    'tags' => env('RESPONSE_CACHE_TAGS', []),

    /*
    |--------------------------------------------------------------------------
    | Server Timing Header
    |--------------------------------------------------------------------------
    |
    | If the server timing option is enabled, a Server-Timing header containing
    | the initial caching time will be added to all cached responses. This
    | makes debugging easier.
    | Leaving it enabled in production probably has no negative consequences
    | aside from a small performance penalty due to the bigger response size.
    |
    */
    'server_timing' => env('RESPONSE_CACHE_SERVER_TIMING', false),
];
