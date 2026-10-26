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

    private CacheRepositoryImplementation $store;

    private Dispatcher $events;

    /**
     * @var Flush[]
     */
    private array $flushes = [];

    protected function setUp(): void
    {
        $this->store = new CacheRepositoryImplementation(new ArrayStore());
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

        foreach (array_keys($this->store->getStore()->all()) as $stored) {
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
