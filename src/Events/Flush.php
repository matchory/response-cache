<?php

/**
 * This file is part of response-cache, a Matchory application.
 *
 * Unauthorized copying of this file, via any medium, is strictly prohibited.
 * Its contents are strictly confidential and proprietary.
 *
 * @copyright 2020–2021 Matchory GmbH · All rights reserved
 * @author    Moritz Friedrich <moritz@matchory.com>
 */

declare(strict_types=1);

namespace Matchory\ResponseCache\Events;

use JetBrains\PhpStorm\Pure;

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
