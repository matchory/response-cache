<?php

/**
 * This file is part of response-cache, a Matchory application.
 *
 * @copyright 2020–2021 Matchory GmbH · All rights reserved
 * @author    Moritz Friedrich <moritz@matchory.com>
 */

declare(strict_types=1);

namespace Matchory\ResponseCache\Facades;

use Illuminate\Support\Facades\Facade;
use Matchory\ResponseCache\ResponseCache as Instance;

/**
 * Response Cache Facade
 *
 * @method static void clear(array|null $tags = null) Clears the cache
 * @method static void delete(array|string $uri, array|null $tags = null) Deletes one or more URIs
 * @method static bool enabled() Checks whether the cache is enabled
 *
 * @bundle Matchory\ResponseCache
 * @see    Instance
 */
class ResponseCache extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Instance::class;
    }
}
