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
    | Tags work on every store. null uses your default cache store.
    |
    */
    'store' => env('RESPONSE_CACHE_STORE'),

    /*
    |--------------------------------------------------------------------------
    | Tag Version Time to Live
    |--------------------------------------------------------------------------
    |
    | Every tag has a version, which is replaced when the tag is flushed. This
    | setting specifies how long versions are kept, in seconds. Once a version
    | expires, all responses carrying that tag are treated as stale, so cached
    | responses never outlive it.
    |
    */
    'tag_ttl' => env('RESPONSE_CACHE_TAG_TTL', 60 * 60 * 24 * 30),

    /*
    |--------------------------------------------------------------------------
    | Default Tags
    |--------------------------------------------------------------------------
    |
    | All responses cached will also be tagged with these tags by default, so
    | flushing one of them clears every response. Additional tags may be used
    | depending on the middleware and strategy.
    |
    */
    'tags' => ($tags = env('RESPONSE_CACHE_TAGS'))
        ? explode(',', (string) $tags)
        : [],

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

    /*
     |--------------------------------------------------------------------------
     | Enable Status Header
     |--------------------------------------------------------------------------
     |
     | If the status header option is enabled, a Response-Cache-Status header
     | containing the cache status (`miss` or `hit`) will be added to all cached
     | responses. This makes debugging easier.
     */
    'cache_status_enabled' => env('RESPONSE_CACHE_STATUS_ENABLED', true),
];
