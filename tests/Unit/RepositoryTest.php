<?php

/**
 * This file is part of response-cache, a Matchory application.
 *
 * @author    Moritz Friedrich <moritz@matchory.com>
 */

declare(strict_types=1);

namespace Matchory\ResponseCache\Tests\Unit;

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepositoryImplementation;
use Illuminate\Events\Dispatcher;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Matchory\ResponseCache\Events\Flush;
use Matchory\ResponseCache\Repository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function array_keys;
use function str_repeat;

#[CoversClass(Repository::class)]
class RepositoryTest extends TestCase
{
    private const TAG_TTL = 3600;

    private ArrayStore $arrayStore;

    private CacheRepositoryImplementation $store;

    private Dispatcher $events;

    /**
     * @var Flush[]
     */
    private array $flushes = [];

    protected function setUp(): void
    {
        $this->arrayStore = new ArrayStore();
        $this->store = new CacheRepositoryImplementation($this->arrayStore);
        $this->events = new Dispatcher();
        $this->events->listen(Flush::class, function (Flush $event) {
            $this->flushes[] = $event;
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
    }

    #[Test]
    public function snapshotCreatesMissingVersions(): void
    {
        $repository = $this->createRepository();
        $first = $repository->snapshot(['users']);
        $second = $repository->snapshot(['users']);

        self::assertNotNull($first);
        self::assertCount(2, $first->versions);
        self::assertEquals($first, $second);
    }

    #[Test]
    public function flushChangesOnlyTheGivenTag(): void
    {
        $repository = $this->createRepository();
        $before = $repository->snapshot(['users', 'posts'])->versions;
        $repository->flush(['users']);
        $after = $repository->snapshot(['users', 'posts'])->versions;

        $users = $repository->versionKeyFor('users');
        $posts = $repository->versionKeyFor('posts');
        $global = $repository->versionKeyFor(null);

        self::assertNotSame($before[$users], $after[$users]);
        self::assertSame($before[$posts], $after[$posts]);
        self::assertSame($before[$global], $after[$global]);
    }

    #[Test]
    public function flushWithoutTagsChangesOnlyGlobalVersion(): void
    {
        $repository = $this->createRepository();
        $before = $repository->snapshot(['users'])->versions;
        $repository->flush();
        $after = $repository->snapshot(['users'])->versions;

        $users = $repository->versionKeyFor('users');
        $global = $repository->versionKeyFor(null);

        self::assertSame($before[$users], $after[$users]);
        self::assertNotSame($before[$global], $after[$global]);
    }

    #[Test]
    public function flushWithEmptyArrayIsNoop(): void
    {
        $repository = $this->createRepository();
        $before = $repository->snapshot(['users']);
        $repository->flush([]);

        self::assertEquals($before, $repository->snapshot(['users']));
        self::assertSame([], $this->flushes);
    }

    #[Test]
    public function flushDispatchesEvent(): void
    {
        $repository = $this->createRepository();
        $repository->flush(['users']);
        $repository->flush();

        self::assertCount(2, $this->flushes);
        self::assertSame(['users'], $this->flushes[0]->tags);
        self::assertNull($this->flushes[1]->tags);
    }

    #[Test]
    public function flushThrowsWhenStoreRejectsWrite(): void
    {
        $this->store = new CacheRepositoryImplementation(new class extends ArrayStore {
            public function put($key, $value, $seconds)
            {
                return false;
            }
        });
        $repository = $this->createRepository();

        $this->expectException(RuntimeException::class);

        $repository->flush(['users']);
    }

    #[Test]
    public function versionKeysAreHashed(): void
    {
        $repository = $this->createRepository();
        $tag = str_repeat('a tag with spaces ', 20);
        $before = $repository->snapshot([$tag])->versions;
        $repository->flush([$tag]);
        $after = $repository->snapshot([$tag])->versions;
        $key = $repository->versionKeyFor($tag);

        self::assertNotSame($before[$key], $after[$key]);

        foreach (array_keys($this->arrayStore->all()) as $stored) {
            self::assertMatchesRegularExpression(
                '/^response-cache:tag:([0-9a-f]{40}|\*)$/',
                $stored,
            );
        }
    }

    #[Test]
    public function versionsAreWrittenWithTagTtl(): void
    {
        $repository = $this->createRepository();
        $before = $repository->snapshot(['users']);

        Carbon::setTestNow(Carbon::now()->addSeconds(self::TAG_TTL + 1));

        self::assertNull($this->store->get($repository->versionKeyFor('users')));
        self::assertNotEquals($before, $repository->snapshot(['users']));
    }

    #[Test]
    public function lookupMissesThenHits(): void
    {
        $repository = $this->createRepository();
        $response = new Response('foo');
        $lookup = $repository->lookup('key', ['users']);

        self::assertNull($lookup->response);
        self::assertNotNull($lookup->snapshot);

        $repository->put('key', $response, $lookup->snapshot, 60);

        self::assertSame($response, $repository->lookup('key', ['users'])->response);
    }

    #[Test]
    public function flushInvalidatesEntry(): void
    {
        $repository = $this->createRepository();
        $this->putEntry($repository, ['users', 'user:42']);
        $repository->flush(['user:42']);

        self::assertNull($repository->lookup('key', ['users', 'user:42'])->response);
    }

    #[Test]
    public function flushAllInvalidatesEntryButNotForeignKeys(): void
    {
        $repository = $this->createRepository();
        $this->store->put('foreign', 'x', 60);
        $this->putEntry($repository, ['users']);
        $repository->flush();

        self::assertNull($repository->lookup('key', ['users'])->response);
        self::assertSame('x', $this->store->get('foreign'));
    }

    #[Test]
    public function staleSnapshotIsNeverValid(): void
    {
        $repository = $this->createRepository();
        $lookup = $repository->lookup('key', ['users']);
        $repository->flush(['users']);
        $repository->put('key', new Response('foo'), $lookup->snapshot, 60);

        self::assertNull($repository->lookup('key', ['users'])->response);
    }

    #[Test]
    public function differentTagSetMisses(): void
    {
        $repository = $this->createRepository();
        $this->putEntry($repository, ['a']);

        self::assertNull($repository->lookup('key', ['a', 'b'])->response);
    }

    #[Test]
    public function evictedVersionMisses(): void
    {
        $repository = $this->createRepository();
        $lookup = $repository->lookup('key', ['users']);
        $this->store->forget($repository->versionKeyFor('users'));
        $repository->put('key', new Response('foo'), $lookup->snapshot, 60);

        self::assertNull($repository->lookup('key', ['users'])->response);
    }

    #[Test]
    public function entryOutlivingTagTtlMisses(): void
    {
        $repository = $this->createRepository();
        $this->putEntry($repository, ['users'], self::TAG_TTL * 2);

        Carbon::setTestNow(Carbon::now()->addSeconds(self::TAG_TTL + 1));

        self::assertNull($repository->lookup('key', ['users'])->response);
    }

    #[Test]
    public function entryWithoutTtlExpiresWithTagTtl(): void
    {
        $repository = $this->createRepository();
        $snapshot = $repository->lookup('key', ['users'])->snapshot;
        $repository->put('key', new Response('foo'), $snapshot, null);

        Carbon::setTestNow(Carbon::now()->addSeconds(self::TAG_TTL + 1));

        self::assertNull($this->store->get('response-cache:entry:key'));
    }

    #[Test]
    public function deleteRemovesEntry(): void
    {
        $repository = $this->createRepository();
        $this->putEntry($repository, ['users']);
        $repository->delete('key');

        self::assertNull($repository->lookup('key', ['users'])->response);
    }

    /**
     * @param  string[]  $tags
     */
    private function putEntry(Repository $repository, array $tags, int $ttl = 60): void
    {
        $snapshot = $repository->lookup('key', $tags)->snapshot;
        $repository->put('key', new Response('foo'), $snapshot, $ttl);

        self::assertNotNull($repository->lookup('key', $tags)->response);
    }

    private function createRepository(): TestRepository
    {
        return new TestRepository($this->store, $this->events, self::TAG_TTL);
    }
}

/**
 * Exposes the version key derivation to the tests.
 */
readonly class TestRepository extends Repository
{
    public function versionKeyFor(?string $tag): string
    {
        return $tag === null
            ? self::GLOBAL_VERSION_KEY
            : $this->versionKey($tag);
    }
}
