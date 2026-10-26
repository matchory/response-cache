<?php

/**
 * This file is part of response-cache, a Matchory library.
 *
 * @author Moritz Friedrich <moritz@matchory.com>
 */

declare(strict_types=1);

namespace Matchory\ResponseCache;

/**
 * Snapshot
 *
 * The tag versions captured before a response is built. An entry stamped with
 * them is only valid as long as none of its tags has been flushed since.
 *
 * @bundle Matchory\ResponseCache
 */
final readonly class Snapshot
{
    /**
     * @param  array<string, string>  $versions  Version key → token
     */
    public function __construct(public array $versions) {}
}
