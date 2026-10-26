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
use Illuminate\Support\Collection;
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
        $transaction = $this->openTransactions()->last();

        if ($transaction === null) {
            $callback();

            return;
        }

        $this->defer($transaction, $callback);
    }

    /**
     * Defers a callback until a transaction ends, then until the transactions
     * still open at that point have ended, and so on.
     */
    protected function defer(
        DatabaseTransactionRecord $transaction,
        callable $callback,
    ): void {
        $ended = function () use ($callback): void {
            $next = $this->openTransactions()->last();

            if ($next !== null) {
                $this->defer($next, $callback);

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

    /**
     * Retrieves the transactions that are still open. Laravel lowers a
     * connection's transaction level before it runs commit or rollback
     * callbacks, or when a commit fails, but may still list the transaction
     * as pending then. Transactions opened by testing traits are excluded.
     *
     * @return Collection<int, DatabaseTransactionRecord>
     */
    protected function openTransactions(): Collection
    {
        // Resolved on every call, never once, for Octane
        if (! $this->container->bound('db.transactions')) {
            return new Collection();
        }

        $database = $this->container->make('db');

        return $this->container
            ->make('db.transactions')
            ->callbackApplicableTransactions()
            ->filter(fn(DatabaseTransactionRecord $transaction) => $database
                ->connection($transaction->connection)
                ->transactionLevel() >= $transaction->level)
            ->values();
    }
}
