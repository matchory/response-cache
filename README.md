Response Cache [![Latest Stable Version](https://poser.pugx.org/matchory/response-cache/v)](https://packagist.org/packages/matchory/response-cache) [![Total Downloads](https://poser.pugx.org/matchory/response-cache/downloads)](https://packagist.org/packages/matchory/response-cache) [![Latest Unstable Version](https://poser.pugx.org/matchory/response-cache/v/unstable)](https://packagist.org/packages/matchory/response-cache) [![License](https://poser.pugx.org/matchory/response-cache/license)](https://packagist.org/packages/matchory/response-cache) [![Laravel Octane Compatible](https://img.shields.io/badge/Laravel%20Octane-Compatible-success?style=flat&logo=laravel)](https://github.com/laravel/octane)
==============
> A Laravel package that adds a smart cache for full responses

Using this package, you can cache full response instances returned from your controllers, simply by adding a middleware.  
This can speed up your application immensely!

Features
--------

- **Easy to use**: Just [add the `CacheResponse`](#using-the-responsecache-middleware-manually) to your route!
- **Fully customizable**: The cache probably works out of the box, but you can control every aspect of caching if necessary.
- **Support for authenticated requests**: By default, cache entries will be unique to every authenticated user.
- **Resolves route bindings in tags**: Cache tags may use any value in the route or request data dynamically.

```php
Route::get('/example')->middleware([
    'response_cache:3600,examples'
])
```

Alternatives
------------
Coincidentally, I just learned [spatie](https://spatie.be/) have built almost exactly the same library as we did:  
[spatie/laravel-responsecache](https://github.com/spatie/laravel-responsecache).

### Why not use Varnish?
In contrast to external caches, having the response cache in your app gives you the power to evaluate route bindings, authentication state, and easy programmatic cache flushing.  
This also makes it possible to place the cache check anywhere in your middleware stack!

Requirements
------------

- **PHP 8.2 or later**
- Laravel 12.x or 13.x

Installation
------------
Install using composer:

```bash
composer require matchory/response-cache
```

Unless you have disabled package discovery, everything should be set up already. Otherwise, add the service provider and the facade to your configuration.

Optionally, you can publish the configuration file:

```bash
php artisan vendor:publish --provider "Matchory\ResponseCache\ResponseCacheProvider"
```

Check out the [configuration section](#configuration) to learn about all available options.

Register the middleware aliases in your `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->alias([
        'response_cache' => \Matchory\ResponseCache\Http\Middleware\CacheResponse::class,
        'bypass_cache' => \Matchory\ResponseCache\Http\Middleware\BypassCache::class,
    ]);
})
```

Then add it to your routes, after authentication:

```php
Route::middleware(['auth:sanctum', 'response_cache'])->group(function () {
    // ...
});
```

> **Important:** The cache must run _after_ authentication. Cached responses are returned before any middleware that follows
> `response_cache` runs, and cache entries are only separated per user if the user is already authenticated when the cache is
> checked. Placing it before your authentication or authorization middleware can serve one user's responses to another.

Configuration
-------------
The config file contains the following settings:

### `enabled` (Environment variable: `RESPONSE_CACHE_ENABLED`)
Using this setting, you can globally enable or disable the response cache for all requests. If you set it to `false`, nothing will be cached.

Defaults to `true`.

### `ttl` (Environment variable: `RESPONSE_CACHE_TTL`)
This setting specifies the amount of time responses will be cached before being marked as stale. The value must be specified in seconds.

Defaults to `60 * 60 * 24`, or exactly 24 hours.

### `store` (Environment variable: `RESPONSE_CACHE_STORE`)
Here you may pass any cache store defined in your `config/cache.php` file. Tags work on every store, including `file`, `database` and `dynamodb`; the store
does not need to support Laravel's cache tags. The `failover` store is not supported, because it can lose flushes during an outage (see
[Limitations](#limitations)).

Defaults to `null`, which uses your application's default cache store.

### `tag_ttl` (Environment variable: `RESPONSE_CACHE_TAG_TTL`)
Every tag has a version, which is replaced when the tag is flushed (see [Cache Tags](#cache-tags)). This setting specifies how long versions are kept, in
seconds. Versions are never refreshed: once one expires, all responses carrying that tag become misses, and the next request starts a new version. Cached
responses therefore never outlive `tag_ttl`, even if their own TTL is longer.

Defaults to `60 * 60 * 24 * 30`, or 30 days.

### `tags` (Environment variable: `RESPONSE_CACHE_TAGS`)
Tags added to every cached response. Additional tags may be used depending on the middleware and strategy. Flushing one of the default tags invalidates every
cached response.

Defaults to `[]`.

### `server_timing` (Environment variable: `RESPONSE_CACHE_SERVER_TIMING`)
If the server timing option is enabled, a Server-Timing header containing the initial caching time will be added to all cached responses. This makes debugging
easier. Leaving it enabled in production probably has no negative consequences aside from a small performance penalty due to the bigger response size.

Defaults to `false`.

### `cache_status_enabled` (Environment variable: `RESPONSE_CACHE_STATUS_ENABLED`)
If the cache status option is enabled, a `Response-Cache-Status` header containing the cache status (`hit` or `miss`) will be added to all responses. This makes debugging easier.

Defaults to `true`.

Usage
-----
After following the installation instructions above, you should be ready to go: Responses with a status code in the `200` and `300` range will be cached and
returned on subsequent visits. To bypass the cache for specific routes, you can simply add the `bypass_cache` middleware:

```php
Route::get('/kitten')->middleware('bypass_cache');
```

### Flushing the cache manually
To clear the cache manually, you can use the `response-cache:clear` artisan command (or its alias, `response-cache:flush`):

```bash
php artisan response-cache:clear
```

This will flush the entire response cache, and nothing else in the cache store. To flush a given set of cache tags only, add them to the command:

```bash
php artisan response-cache:clear tag-1 tag-2 ... tag-n
```

### Flushing the cache programmatically
In production use, it is often helpful to flush the cache automatically if something happens. For example, you can flush the cache whenever a model event is
fired by setting up an observer:

```php
use Matchory\ResponseCache\Facades\ResponseCache;

class ExampleObserver {
    public function saved(): void {
        ResponseCache::flush();
    }

    public function deleted(): void {
        // By passing one or more tags, only those items tagged appropriately
        // will be cleared
        ResponseCache::flush(['example']);
    }
}
```

Calling `flush()` without arguments (or with `null`) invalidates every cached response. Passing tags invalidates every response carrying at least one of
them, regardless of its other tags, the user it was cached for, or its query string. Passing an empty array flushes nothing, so a computed tag list that
happens to be empty can never clear the whole cache.

Flushing is cheap: it writes one small cache key per tag, so it's safe to call many times from observers. Invalidated responses are not deleted; they stay in
the store until their TTL expires. If you need the space back immediately, run `php artisan cache:clear` on that store.

**Flushes run after the database transaction commits.** If you flush inside a transaction, the flush is deferred until it commits, so other requests can't
cache the old data in the meantime. If the transaction rolls back, the flush never happens, since the data didn't change. Outside a transaction, the flush
runs immediately. If the cache store fails to write a flush, `flush()` throws a `RuntimeException`.

To be more selective, use the `delete` method:

```php
use Matchory\ResponseCache\Facades\ResponseCache;

ResponseCache::delete('/example/url');
ResponseCache::delete(['/example/1', '/example/2']);
```

> **Note:** This only deletes the response cached for the current context, such as the authenticated user, because cache keys include it. To clear a URL
> for everyone, work with tags instead.

#### Cache Tags
Cache tags are a simple, yet very powerful mechanism for efficient cache usage. By applying tags to a set of cached items, you can purge all of them, without
ever knowing the individual cache keys of these items. Say, there are lots of cached data points all related to "flights": plane schedules, arrival dates,
passenger records, etc. All is fine and well, until a flight is cancelled! Now, we will need to purge the cache for anything that includes the flight in
question. This would be a logical nightmare, hadn't we set up cache tags: Instead of deleting lots of items manually, we flush the tag with the flight
number, and we're good to go!

Tags work on every cache store; they don't use Laravel's cache tags. Instead, every tag has a version: a random token stored in the cache. Each cached response
records the versions of its tags when it is built, and it is only served while all of them are still current. Flushing a tag writes a new version, which
makes every response carrying it a miss. The versions are read before your controller runs, so a flush that happens while a response is being built
invalidates that response, too.

This library is built heavily around the concept of cache tags: By tagging properly, you can make granular cache flushing a breeze. Tags can be added to routes
using several approaches:

- By passing them to the middleware:
  ```php
  Route::get('/foo')->middleware(['response_cache:tag-1,tag-2']);
  ```
- By adding them to the `config/response-cache.php` config file:
  ```php
  'tags' => env('RESPONSE_CACHE_TAGS', [ 'tag-1', 'tag-2' ]),
  ```
- By returning them from [your strategy](#customizing-tagging).

#### Using bindings in cache tags
This library supports adding dynamic tags that are based on route bindings. This works pretty much the same as with ordinary route bindings, but with the added
benefit of also having access to all request values, not just those mentioned in the route itself.  
To add a binding to your cache tag, simply include the name of a parameter in curly braces: `flights.{flight}`. As soon as something is to be fetched from or
put into the cache, this binding will be resolved using the current request instance. If the parameter `flight` was an instance of an Eloquent model, it would
be replaced with its route key name, so probably the flight ID!

Take a look at the following example:

```php
Route::get('/api/v{version}/flights/{flight}', function(\App\Flight $flight) {
    // ...
})->middleware('response_cache:api.v{version},flights,flights.v{version},flights.{flight},flights.v{version}.{flight}');
```

Imagine we perform the following request: `/api/v3/flights/505`  
Now, the response generated by this route would be tagged with the following tags:

- `api.v3`
- `flights`
- `flights.v3`
- `flights.505`
- `flights.v3.505`

Depending on your requirements, you can simply flush the cache for any of these and rest assured the response will be invalidated!

### Caching Strategies
A _strategy_ is what the response cache uses to determine just how a response should be cached. It generates cache keys, determines cache tags and controls
cache bypassing. The default strategy should fit for most use cases, but if it doesn't, we got you covered, too!

To use a custom strategy, start by either extending [the default implementation](./src/Support/BaseStrategy.php) (recommended) or
implementing [the `CacheStrategy` interface](./src/Contracts/CacheStrategy.php).

#### Customize cache keys
To customize the way cache keys are generated, you have several options, as the default implementation splits this process in multiple methods:

```php
use Illuminate\Http\Request;use Matchory\ResponseCache\Support\BaseStrategy;

class MyStrategy extends BaseStrategy
{
    public function key(Request $request): string
    {
        $identifier = $this->extractRequestIdentifier($request);
        $suffix = $this->buildSuffix($request);

        return $this->hash($identifier . $suffix);
    }
}
```

- The `extractRequestIdentifier` method extracts the full request URL and method as the base of the cache key. This should be enough in most applications.
- The `buildSuffix` method checks for the current authentication status and appends the ID of the authenticated user. You may wish to modify this to use a
  customer or application identifier, for example.
- The `hash` method builds a hash of the given cache key (by default it uses `md5`), so the key length stays consistent.

#### Customizing cache bypass
Strategies decide in two steps whether to cache. Before the response exists, `isCacheable` decides whether a request may be served from or stored in the
cache at all. Requests it rejects skip the cache entirely, without reading anything. By default, it accepts `GET` and `HEAD` requests:

```php
use Illuminate\Http\Request;
use Matchory\ResponseCache\Support\BaseStrategy;

class MyStrategy extends BaseStrategy
{
    public function isCacheable(Request $request): bool
    {
        return $this->isMethodCachable($request);
    }
}
```

Once the response exists, `shouldCache` decides whether to store it:

```php
use Illuminate\Http\Request;
use Matchory\ResponseCache\Support\BaseStrategy;
use Symfony\Component\HttpFoundation\Response;

class MyStrategy extends BaseStrategy
{
    public function shouldCache(Request $request, Response $response): bool
    {
        if (! $this->isCacheable($request)) {
            return false;
        }

        return $this->isSuccessful($response);
    }
}
```

By default, this will cache any request with the `GET` or `HEAD` methods, and responses with a success or redirection status.

#### Customizing tagging
Tagging cached responses is an immensely powerful feature that allows you to flush a collection of cache entries if something happens. So if a single member of
a collection is deleted, for example, you can remove all cached instances of the same collection, without having to know the exact cache keys used to retrieve
them!  
To make this as easy as possible, strategies provide a method to pull tags from a request. Tags must be derivable from the request alone, because they
are needed to look up a cached response before any response exists:

```php
use Illuminate\Http\Request;
use Matchory\ResponseCache\Support\BaseStrategy;

class MyStrategy extends BaseStrategy
{
    public function tags(Request $request): array
    {
        return [
            $request->attributes->get('customer.id')
        ];
    }
}
```

### Using the `ResponseCache` middleware manually
When adding the middleware to routes, you also have the possibility to configure the time to leave and add a set of tags per route:

```php
// Default settings as set in the config file
Route::get('/foo')->middleware('response_cache')

// TTL of 60 seconds
Route::get('/bar')->middleware('response_cache:60');

// Tags "foo", "bar" and "baz" are added
Route::get('/baz')->middleware('response_cache:foo,bar,baz'); 

// TTL of 60 seconds and tags "foo", "bar" and "baz" are added
Route::get('/quz')->middleware('response_cache:60,foo,bar,baz');
```

If the first parameter to the middleware is a number, it will be interpreted as a TTL value, everything that follows as tags. The TTL will override the
[configuration value](#ttl-environment-variable-response_cache_ttl), tags will be merged.

### Reacting to cache events
This library exposes some events you can listen for and act accordingly. This is probably most helpful during [debugging](#debugging).

#### `Hit`
Emitted if a response was found in the cache. Includes the request instance.

#### `Miss`
Emitted if a response could not be found in the cache and thus had to be generated by the application. Includes the request instance. Requests the
[caching strategy](#caching-strategies) rejects in `isCacheable`, such as `POST` requests, bypass the cache without emitting any event.

#### `Flush`
Emitted if the cache was flushed. Includes the tags that were flushed, or `null` if all tags were flushed.

### Debugging
Cache issues can be a little annoying to debug. This library has several facilities built in to make the process as simple as possible:

1. **Enable the `Server-Timing` header**: By switching this feature on, all responses will include a
   [`Server-Timing` header](https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Server-Timing) that includes the time your response was cached. This
   information will automatically show up in your browser's developer tools!
2. **Listen to cache events**: By listening to [the cache events](#reacting-to-cache-events), you can make sure everything is working as intended.
3. **Temporarily or conditionally disable caching**: By changing the `response-cache.enabled` setting, you can quickly determine whether caching is the culprit.

Limitations
-----------
Tag versioning never serves a response after a flush that it knows about, but some situations are outside its control:

- **Data read before the versions.** The cache middleware runs after authentication and route binding, so a bound model or the authenticated user is loaded
  before the tag versions are read. A write that commits and flushes in those few milliseconds produces a response built from the old data but stamped with
  the new version. It is served until the next flush of one of its tags, or until its TTL expires.
- **Read replicas.** A request that reads from a lagging replica after a flush can cache old data under the new version, just like above.
- **Store replication and failover.** If the cache store loses a write, for example during a Redis failover with asynchronous replication, or when
  Memcached remaps a dead server, an old version can come back and make stale responses valid again. This is inherent to those stores. For the same reason,
  Laravel's `failover` cache store is refused.
- **DynamoDB** reads are eventually consistent, which leaves a short window after a flush. The version of the internal global tag is read on every request,
  which makes it a hot partition.
- **Local stores.** The `file` and `apc` stores are local to a host, and the `array` store to a process. A flush only reaches the host or worker that runs
  it, so use them on a single host only.
- **Hit rate.** Flushing a broad tag like `users` on every write keeps all responses carrying it cold on write-heavy tables, and flushing everything after a
  deployment causes a burst of misses. There is no lock against many requests rebuilding the same response at once.

Upgrading to v2
---------------
Version 2 replaces Laravel's cache tags with tag versioning. Existing cache entries are not read anymore, which just causes misses after the upgrade.

- **Strategies:** `CacheStrategy::tags()` takes the request only: `tags(Request $request): array`. Tags derived from the response are no longer supported.
- **Strategies:** `CacheStrategy` has a new method, `isCacheable(Request $request): bool`. Custom strategies extending `BaseStrategy` inherit it.
- **Events:** requests rejected by `isCacheable` (by default, anything but `GET` and `HEAD`) no longer emit a `Miss` event.
- **Flushing:** `ResponseCache::flush($tags)` no longer adds the default tags, so it flushes exactly the given tags. `flush([])` now does nothing; use
  `flush()` to flush everything.
- **Flushing:** flushes inside a database transaction run after it commits, and are dropped if it rolls back.
- **Flushing:** `flush()` throws a `RuntimeException` if the cache store fails to write.
- **Flushing:** invalidated responses are no longer deleted from the store; they expire with their TTL.
- **`ResponseCache` methods:** `has()` was removed; use `get()` or `lookup()`. `put()` requires a `Snapshot` of the tag versions, taken before the response
  is built, in place of the tags: `put($request, $response, $snapshot, $ttl)`. Take one with `snapshot($request, $tags)`, or use the one returned by
  `lookup($request, $tags)` on a miss. `get()` and `lookup()` take a tags array (`[]` by default) instead of `null`. `delete()` no longer takes tags.
- **`Repository`:** the methods `get()`, `has()`, `getStore()`, `supportsTags()`, `serialize()` and `hydrate()` were removed, along with custom
  serialization through a `Repository` subclass. Its constructor takes the tag TTL as a third argument.
- **Configuration:** `store` defaults to `null` (your default cache store) instead of `file`. The new `tag_ttl` option sets how long tag versions are kept
  (30 days by default) and must be at least one second. The `failover` cache store is refused with an `InvalidArgumentException`.
- **Stores:** tags work on every cache store; a store with tag support is no longer needed.
