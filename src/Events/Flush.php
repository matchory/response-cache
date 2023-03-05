<?php

/**
 * This file is part of response-cache, a Matchory library.
 *
 * Unauthorized copying of this file, via any medium, is strictly prohibited.
 * Its contents are strictly confidential and proprietary.
 *
 * @author Moritz Friedrich <moritz@matchory.com>
 */

declare(strict_types=1);

namespace Matchory\ResponseCache\Events;

use JetBrains\PhpStorm\Deprecated;

/**
 * Flush Event
 *
 * @bundle Matchory\ResponseCache
 */
readonly class Flush
{
    public function __construct(public array|null $tags = null)
    {
    }

    /**
     * @return array|null
     * @deprecated Use the tags property directly
     */
    #[Deprecated(
        reason: "Use the tags property directly",
        replacement: "%class%->tags"
    )]
    public function getTags(): array|null
    {
        return $this->tags;
    }
}
