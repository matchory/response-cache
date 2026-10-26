<?php

/**
 * This file is part of response-cache, a Matchory library.
 *
 * @author Moritz Friedrich <moritz@matchory.com>
 */

declare(strict_types=1);

namespace Matchory\ResponseCache;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Events\Dispatcher;
use Matchory\ResponseCache\Events\Flush;
use Psr\SimpleCache\InvalidArgumentException;
use Random\RandomException;
use RuntimeException;

use function array_map;
use function array_unique;
use function array_values;
use function bin2hex;
use function random_bytes;
use function sha1;

/**
 * Cache Repository
 *
 * Responsible for cache persistence. Every tag has a version, a random token
 * stored under its own key; flushing a tag writes a new token, which makes all
 * entries stamped with the previous one invalid.
 *
 * @bundle Matchory\ResponseCache
 */
readonly class Repository
{
    protected const VERSION_KEY_PREFIX = 'response-cache:tag:';

    /**
     * Version key of the internal tag every entry carries. No sha1 output can
     * equal it, so it never collides with a user tag.
     */
    protected const GLOBAL_VERSION_KEY = self::VERSION_KEY_PREFIX . '*';

    public function __construct(
        protected CacheRepository $store,
        protected Dispatcher $eventDispatcher,
        protected int $tagTtl,
    ) {}

    /**
     * Captures the current versions of the given tags and the global tag,
     * creating those that don't exist yet.
     *
     * @param  string[]  $tags
     * @return Snapshot|null Snapshot, or `null` if a version could not be
     *                       established.
     *
     * @throws InvalidArgumentException
     * @throws RandomException
     */
    public function snapshot(array $tags): ?Snapshot
    {
        return $this->establish($this->store->many($this->versionKeys($tags)));
    }

    /**
     * Flushes the response cache by writing new versions for the given tags.
     * Passing `null` flushes all entries, an empty array flushes nothing.
     *
     * @param  string[]|null  $tags
     *
     * @throws RandomException
     * @throws RuntimeException If the store fails to write a version
     */
    public function flush(?array $tags = null): void
    {
        if ($tags === []) {
            return;
        }

        $keys = $tags === null
            ? [self::GLOBAL_VERSION_KEY]
            : array_map($this->versionKey(...), $tags);

        foreach ($keys as $key) {
            if (! $this->store->put($key, $this->newToken(), $this->tagTtl)) {
                throw new RuntimeException(
                    "Failed to write the response cache tag version '{$key}'",
                );
            }
        }

        $this->eventDispatcher->dispatch(new Flush($tags));
    }

    /**
     * Completes a set of versions read from the store: missing versions are
     * created and read again, so whichever token is stored wins. Existing
     * versions are never overwritten here.
     *
     * @param  array<string, string|null>  $versions  Version key → token
     * @return Snapshot|null Snapshot, or `null` if a version is still missing.
     *
     * @throws InvalidArgumentException
     * @throws RandomException
     */
    protected function establish(array $versions): ?Snapshot
    {
        $missing = [];

        foreach ($versions as $key => $version) {
            if ($version === null) {
                $missing[] = $key;
                $this->store->add($key, $this->newToken(), $this->tagTtl);
            }
        }

        if ($missing !== []) {
            foreach ($this->store->many($missing) as $key => $version) {
                if ($version === null) {
                    return null;
                }

                $versions[$key] = $version;
            }
        }

        return new Snapshot($versions);
    }

    /**
     * Retrieves the version keys for a set of tags, including the global tag.
     *
     * @param  string[]  $tags
     * @return string[]
     */
    protected function versionKeys(array $tags): array
    {
        $keys = array_map($this->versionKey(...), $tags);
        $keys[] = self::GLOBAL_VERSION_KEY;

        return array_values(array_unique($keys));
    }

    /**
     * Retrieves the version key of a tag. Hashing keeps keys of any tag within
     * the key length limits of all stores.
     */
    protected function versionKey(string $tag): string
    {
        return self::VERSION_KEY_PREFIX . sha1($tag);
    }

    /**
     * Generates a new version token.
     *
     * @throws RandomException
     */
    protected function newToken(): string
    {
        return bin2hex(random_bytes(16));
    }
}
