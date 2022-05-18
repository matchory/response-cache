<?php
/** @noinspection PhpMissingFieldTypeInspection */

/**
 * This file is part of response-cache, a Matchory application.
 *
 * @copyright 2020–2021 Matchory GmbH · All rights reserved
 * @author    Moritz Friedrich <moritz@matchory.com>
 */

declare(strict_types=1);

namespace Matchory\ResponseCache\Commands;

use BadMethodCallException;
use Illuminate\Console\Command;
use Matchory\ResponseCache\Repository;

/**
 * FlushCacheCommand
 *
 * @bundle Matchory\ResponseCache
 */
class FlushCacheCommand extends Command
{
    protected $description = 'Flushes the response cache';

    protected $signature = 'response-cache:flush {tags?* : Tags to flush}';

    /**
     * @param Repository $repository
     *
     * @throws BadMethodCallException
     */
    public function handle(Repository $repository): void
    {
        $repository->flush($this->argument('tags'));
    }
}
