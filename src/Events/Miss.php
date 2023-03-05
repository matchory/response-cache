<?php

/**
 * This file is part of response-cache, a Matchory library.
 *
 * @author Moritz Friedrich <moritz@matchory.com>
 */

declare(strict_types=1);

namespace Matchory\ResponseCache\Events;

use Illuminate\Http\Request;
use JetBrains\PhpStorm\Deprecated;

/**
 * Miss Event
 *
 * @bundle Matchory\ResponseCache
 */
readonly class Miss
{
    public function __construct(public Request $request)
    {
    }

    /**
     * @return Request
     * @deprecated Use the request property directly
     */
    #[Deprecated(
        reason: "Use the request property directly",
        replacement: "%class%->request"
    )]
    public function getRequest(): Request
    {
        return $this->request;
    }
}
