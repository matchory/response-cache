Response Cache [![Latest Stable Version](https://poser.pugx.org/matchory/response-cache/v)](https://packagist.org/packages/matchory/response-cache) [![Total Downloads](https://poser.pugx.org/matchory/response-cache/downloads)](https://packagist.org/packages/matchory/response-cache) [![Latest Unstable Version](https://poser.pugx.org/matchory/response-cache/v/unstable)](https://packagist.org/packages/matchory/response-cache) [![License](https://poser.pugx.org/matchory/response-cache/license)](https://packagist.org/packages/matchory/response-cache)
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
    'cacheResponse:3600,examples'
])
```

Alternatives
------------
Coincidentally, I just learned [spatie](https://spatie.be/) have built almost exactly the same library as we did:  
[spatie/laravel-responsecache](https://github.com/spatie/laravel-responsecache).


Requirements
------------
- **PHP 8.0 or later**
- Laravel 7.x or later

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

Add the middleware to your `app/Http/Kernel.php`:
```php
protected $middlewareGroups = [
   'web' => [
       // ...
       \Matchory\ResponseCache\Http\Middleware\CacheResponse::class,
       // ...
   ],
];

protected $routeMiddleware = [
    // ...
    'bypass_cache' => \Matchory\ResponseCache\Http\Middleware\BypassCache::class,
    // ...
];
```

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
Here you may pass any cache store defined in your `config/cache.php` file. Please note that not all stores support tagging: For better control over and
increased performance of your cache, we strongly recommend using a store with tag support, like Redis or APC.

Defaults to `file`.

### `tags` (Environment variable: `RESPONSE_CACHE_TAGS`)
If your store supports tags, all responses cached will also be tagged with these tags by default. Additional tags may be used depending on the middleware and
strategy.

Defaults to `[]`.

### `server_timing` (Environment variable: `RESPONSE_CACHE_SERVER_TIMING`)
If the server timing option is enabled, a Server-Timing header containing the initial caching time will be added to all cached responses. This makes debugging
easier. Leaving it enabled in production probably has no negative consequences aside from a small performance penalty due to the bigger response size.

Defaults to `false`.

Usage
-----
After following the installation instructions above, you should be ready to go: Responses with a status code in the `200` and `300` range will be cached and
returned on subsequent visits. To bypass the cache for specific routes, you can simply add the `bypass_cache` middleware:
```php
Route::get('/kitten')->middleware('bypass_cache');
```

### Flushing the cache manually
To clear the cache manually, you can use the `response-cache:flush` artisan command:
```bash
php artisan response-cache:flush
```
This will flush the entire response cache. To delete a given set of cache tags only, add them to the command:
```bash
php artisan response-cache:flush tag-1 tag-2 ... tag-n
```

### Flushing the cache programmatically
In production use, it is often helpful to flush the cache automatically if something happens. For example, you can flush the cache whenever a model event is
fired by setting up an observer:
```php
use Matchory\ResponseCache\Facades\ResponseCache;

class ExampleObserver {
    public function saved(): void {
        ResponseCache::clear();
    }

    public function deleted(): void {
        // By passing one or more tags, only those items tagged appropriately
        // will be cleared
        ResponseCache::clear(['example']);
    }
}
```

By invoking the `clear` method, the entire store (for the given tags) will be purged. To be more selective, use the `delete` method:
```php
use Matchory\ResponseCache\Facades\ResponseCache;

ResponseCache::delete('/example/url');
ResponseCache::delete(['/example/1', '/example/2']);
```

> **Note:** This will not work for cache items that require context information, such as user authentication. If you hit this problem, you'll probably want to
> work with tags instead.

#### Cache Tags
Cache tags are a simple, yet very powerful mechanism for efficient cache usage. By applying tags to a set of cached items, you can purge all of them, without
ever knowing the individual cache keys of these items. Say, there are lots of cached data points all related to "flights": plane schedules, arrival dates,
passenger records, etc. All is fine and well, until a flight is cancelled! Now, we will need to purge the cache for anything that includes the flight in
question. This would be a logical nightmare, hadn't we set up cache tags: Instead of deleting lots of items manually, we instruct Laravel to purge all items
tagged with the flight number, and we're good to go!

This library is built heavily around the concept of cache tags: By tagging properly, you can make granular cache flushing a breeze. Tags can be added to routes
using several approaches:
- By passing them to the middleware:
  ```php
  Route::get('/foo')->middleware(['cache:tag-1,tag-2']);
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
})->middleware('cache:api.v{version},flights,flights.v{version},flights.{flight},flights.v{version}.{flight}');
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
To customize whether a response is cached or not, you can implement one or more helpers:
```php
use Illuminate\Http\Request;
use Matchory\ResponseCache\Support\BaseStrategy;
use Symfony\Component\HttpFoundation\Response;

class MyStrategy extends BaseStrategy
{
    public function shouldCache(Request $request, Response $response): bool
    {
        if (! $this->isMethodCachable($request)) {
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
To make this as easy as possible, strategies provide a method to pull tags from a request and response:
```php
use Illuminate\Http\Request;
use Matchory\ResponseCache\Support\BaseStrategy;
use Symfony\Component\HttpFoundation\Response;

class MyStrategy extends BaseStrategy
{
    public function tags(Request $request, Response|null $response = null): array
    {
        return [
            $request->attributes->get('customer.id')
        ];
    }
}
```

### Using the `ResponseCache` middleware manually
Instead of simply adding the caching middleware to all web routes as shown above, you can of course also add it to selected routes manually. In this case, you
also have the possibility to configure the time to leave and add a set of tags:

```php
// TTL of 60 seconds
Route::get('/foo')->middleware('response_cache:60');

// Tags "foo", "bar" and "baz" are added
Route::get('/bar')->middleware('response_cache:foo,bar,baz'); 

// TTL of 60 seconds and tags "foo", "bar" and "baz" are added
Route::get('/bar')->middleware('response_cache:60,foo,bar,baz');
```

### Reacting to cache events
This library exposes some events you can listen for and act accordingly. This is probably most helpful during [debugging](#debugging).

#### `Hit`
Emitted if a response was found in the cache. Includes the request instance.

#### `Miss`
Emitted if a response could not be found in the cache or was [indicated as non-cachable by the caching strategy](#caching-strategies) and thus had to be
generated by the application. Includes the request instance.

#### `Flush`
Emitted if the cache was flushed. Includes the tags that were flushed, or `null` if all tags were flushed.

### Manual response serialization
By default, the response cache uses a [cache repository implementation](./src/Repository.php) with a very simple serialization method: _Doing nothing_. Any
serialization is deferred to Laravel's cache implementation. In rare cases, you may need to change this behavior and modify a response before serializing or
after hydrating it.  
To do so, start by extending the `Repository` class:
```php
use Matchory\ResponseCache\Repository;
use Symfony\Component\HttpFoundation\Response;

class CustomRepository extends Repository
{
    protected function serialize(Response $response) : mixed
    {
        return serialize($response); 
    }

    protected function hydrate(mixed $responseData): Response
    {
        return unserialize($responseData, [Response::class])
    }
}
```

Override the container binding in your `AppServiceProvider`, so your repository will be used instead of the default:
```php
use Matchory\ResponseCache\Repository;

protected function register():void
    {
        $this->app->bind(Repository::class, CustomRepository::class);
    }
```

### Debugging
Cache issues can be a little annoying to debug. This library has several facilities built in to make the process as simple as possible:

1. **Enable the `Server-Timing` header**: By switching this feature on, all responses will include a
   [`Server-Timing` header](https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Server-Timing) that includes the time your response was cached. This
   information will automatically show up in your browser's developer tools!
2. **Listen to cache events**: By listening to [the cache events](#reacting-to-cache-events), you can make sure everything is working as intended.
3. **Temporarily or conditionally disable caching**: By changing the `response-cache.enabled` setting, you can quickly determine whether caching is the culprit.
4. **Use a custom repository**: [Override the built-in serialization method](#manual-response-serialization) to take control of response serialization and
   hydration.
