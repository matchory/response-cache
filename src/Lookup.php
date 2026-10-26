<?php

/**
 * This file is part of response-cache, a Matchory library.
 *
 * @author Moritz Friedrich <moritz@matchory.com>
 */

declare(strict_types=1);

namespace Matchory\ResponseCache;

use Symfony\Component\HttpFoundation\Response;

/**
 * Lookup
 *
 * The result of looking up a request in the cache: the response on a hit, or
 * the snapshot to stamp the new entry with on a miss. The snapshot is `null` if
 * the tag versions could not be established, in which case nothing is stored.
 *
 * @bundle Matchory\ResponseCache
 */
final readonly class Lookup
{
    public function __construct(
        public ?Response $response,
        public ?Snapshot $snapshot,
    ) {}
}
