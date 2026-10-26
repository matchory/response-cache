<?php

/** @noinspection PhpMissingFieldTypeInspection */

/**
 * This file is part of response-cache, a Matchory library.
 *
 * @author Moritz Friedrich <moritz@matchory.com>
 */

declare(strict_types=1);

namespace Matchory\ResponseCache\Commands;

use Illuminate\Console\Command;
use Matchory\ResponseCache\ResponseCache;
use Random\RandomException;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * FlushCacheCommand
 *
 * @bundle Matchory\ResponseCache
 */
#[AsCommand(
    name: 'response-cache:clear',
    description: 'Clears the response cache',
)]
class ClearCacheCommand extends Command
{
    protected $signature = 'response-cache:clear {tags?* : Tags to flush}';

    protected $aliases = ['response-cache:flush'];

    /**
     * @throws RandomException
     * @throws RuntimeException
     */
    public function handle(ResponseCache $responseCache): int
    {
        $tags = $this->argument('tags');

        $responseCache->flush($tags === [] ? null : $tags);

        $this->components->info('Response cache flushed successfully.');

        return self::SUCCESS;
    }
}
