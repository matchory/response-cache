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
use Symfony\Component\HttpFoundation\Response;

use function array_map;
use function array_unique;
use function array_values;
use function bin2hex;
use function count;
use function is_array;
use function iterator_to_array;
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
    protected const ENTRY_KEY_PREFIX = 'response-cache:entry:';

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
     * Looks up a cached response. It is only returned if every tag version it
     * was stamped with is still current; otherwise, the lookup carries the
     * snapshot to stamp a new entry with.
     *
     * @param  string[]  $tags
     *
     * @throws InvalidArgumentException
     * @throws RandomException
     */
    public function lookup(string $key, array $tags): Lookup
    {
        $entryKey = $this->entryKey($key);
        $versions = $this->many([
            $entryKey,
            ...$this->versionKeys($tags),
        ]);
        $entry = $versions[$entryKey];
        unset($versions[$entryKey]);

        if ($this->isValid($entry, $versions)) {
            return new Lookup($entry['response'], null);
        }

        return new Lookup(null, $this->establish($versions));
    }

    /**
     * Puts a response into the cache, stamped with the versions of a snapshot.
     *
     * @param  int|null  $ttl  Time-to-live in seconds, or `null` for no expiry
     */
    public function put(
        string $key,
        Response $response,
        Snapshot $snapshot,
        ?int $ttl,
    ): void {
        $this->store->put($this->entryKey($key), [
            'versions' => $snapshot->versions,
            'response' => $response,
        ], $ttl);
    }

    /**
     * Deletes a cached response.
     */
    public function delete(string $key): void
    {
        $this->store->forget($this->entryKey($key));
    }

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
        return $this->establish($this->many($this->versionKeys($tags)));
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
            foreach ($this->many($missing) as $key => $version) {
                if ($version === null) {
                    return null;
                }

                $versions[$key] = $version;
            }
        }

        return new Snapshot($versions);
    }

    /**
     * Checks whether a stored entry is valid: it must have been stamped with
     * exactly the current tags, and all of their versions must be unchanged.
     *
     * @param  array<string, string|null>  $versions  Current versions
     */
    protected function isValid(mixed $entry, array $versions): bool
    {
        if (
            ! is_array($entry)
            || ! is_array($entry['versions'] ?? null)
            || ! ($entry['response'] ?? null) instanceof Response
            || count($entry['versions']) !== count($versions)
        ) {
            return false;
        }

        foreach ($versions as $key => $version) {
            if ($version === null || ($entry['versions'][$key] ?? null) !== $version) {
                return false;
            }
        }

        return true;
    }

    /**
     * Reads multiple keys in one call. Missing keys yield `null`.
     *
     * @param  string[]  $keys
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException
     */
    protected function many(array $keys): array
    {
        return iterator_to_array($this->store->getMultiple($keys));
    }

    /**
     * Retrieves the cache key of an entry.
     */
    protected function entryKey(string $key): string
    {
        return self::ENTRY_KEY_PREFIX . $key;
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
