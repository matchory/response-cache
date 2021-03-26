<?php

/**
 * This file is part of response-cache, a Matchory application.
 *
 * @copyright 2020–2021 Matchory GmbH · All rights reserved
 * @author    Moritz Friedrich <moritz@matchory.com>
 */

declare(strict_types=1);

namespace Matchory\ResponseCache\Events;

use Illuminate\Http\Request;
use JetBrains\PhpStorm\Pure;

class Miss
{
    #[Pure]
    public function __construct(
        protected Request $request
    ) {
    }

    #[Pure]
    public function getRequest(): Request
    {
        return $this->request;
    }
}
