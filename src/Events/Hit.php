<?php

/**
 * This file is part of response-cache, a Matchory library.
 *
 * @author Moritz Friedrich <moritz@matchory.com>
 */

declare(strict_types=1);

namespace Matchory\ResponseCache\Events;

use Illuminate\Http\Request;
use JetBrains\PhpStorm\Pure;

/**
 * Hit Event
 *
 * @bundle Matchory\ResponseCache
 */
class Hit
{
    #[Pure]
    public function __construct(protected Request $request)
    {
    }

    #[Pure]
    public function getRequest(): Request
    {
        return $this->request;
    }
}
