<?php
/** @noinspection PhpMissingFieldTypeInspection */

/**
 * This file is part of response-cache, a Matchory library.
 *
 * @author Moritz Friedrich <moritz@matchory.com>
 */

declare(strict_types=1);

namespace Matchory\ResponseCache\Commands;

use BadMethodCallException;
use Illuminate\Console\Command;
use Matchory\ResponseCache\Repository;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * FlushCacheCommand
 *
 * @bundle Matchory\ResponseCache
 */
#[AsCommand(
    name: 'response-cache:clear',
    description: 'Clears the response cache',
    aliases: ['response-cache:flush']
)]
class ClearCacheCommand extends Command
{
    protected $signature = 'response-cache:clear {tags?* : Tags to flush}';

    /**
     * @param Repository $repository
     *
     * @throws BadMethodCallException
     */
    public function handle(Repository $repository): int
    {
        $repository->flush($this->argument('tags'));

        $this->components->info('Response cache flushed successfully.');

        return self::SUCCESS;
    }
}
