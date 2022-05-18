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

use JetBrains\PhpStorm\Pure;

/**
 * Flush Event
 *
 * @bundle Matchory\ResponseCache
 */
class Flush
{
    #[Pure]
    public function __construct(protected array|null $tags = null)
    {
    }

    #[Pure]
    public function getTags(): array|null
    {
        return $this->tags;
    }
}
