<?php

/**
 * This file is part of response-cache, a Matchory library.
 *
 * @author Moritz Friedrich <moritz@matchory.com>
 */

declare(strict_types=1);

namespace Matchory\ResponseCache\Support;

use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\DatabaseTransactionRecord;
use Illuminate\Database\DatabaseTransactionsManager;
use Throwable;

/**
 * After Transactions
 *
 * Runs a callback once all open database transactions, on any connection, have
 * ended. It runs after a rollback, too: the data the callback is about may have
 * been written outside the transaction, or on another connection, so skipping
 * it could never be safe.
 * If no transaction is open, the callback runs immediately and its exceptions
 * propagate. A deferred callback runs while Laravel finishes the transaction,
 * so its exceptions are reported instead of interrupting the commit.
 *
 * @bundle Matchory\ResponseCache
 */
readonly class AfterTransactions
{
    public function __construct(protected Container $container) {}

    public function __invoke(callable $callback): void
    {
        // The manager is resolved on every call, never once, for Octane
        $manager = $this->container->bound('db.transactions')
            ? $this->container->make('db.transactions')
            : null;

        $transaction = $manager?->getPendingTransactions()->last();

        if ($transaction === null) {
            $callback();

            return;
        }

        $this->defer($manager, $transaction, $callback);
    }

    /**
     * Defers a callback until a transaction ends, then until the transactions
     * still open at that point have ended, and so on.
     */
    protected function defer(
        DatabaseTransactionsManager $manager,
        DatabaseTransactionRecord $transaction,
        callable $callback,
    ): void {
        $ended = function () use ($manager, $transaction, $callback): void {
            // When a transaction is rolled back entirely, its callbacks run
            // before it is removed from the pending transactions
            $next = $manager->getPendingTransactions()
                ->reject(fn(DatabaseTransactionRecord $pending) => $pending->connection === $transaction->connection
                    && $pending->level >= $transaction->level)
                ->last();

            if ($next !== null) {
                $this->defer($manager, $next, $callback);

                return;
            }

            try {
                $callback();
            } catch (Throwable $exception) {
                $this->container->make(ExceptionHandler::class)->report($exception);
            }
        };

        $transaction->addCallback($ended);
        $transaction->addCallbackForRollback($ended);
    }
}
