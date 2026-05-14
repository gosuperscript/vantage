<?php

namespace Storvia\Vantage\Support;

use Illuminate\Queue\SerializesModels;

/**
 * Carries the Vantage job run ID for a manual retry across queue payload creation.
 *
 * Dynamic properties such as {@see $queueMonitorRetryOf} are not included when Laravel
 * serializes jobs that use {@see SerializesModels}, so the retry link
 * is stored on the outer queue payload instead.
 */
final class PendingVantageRetry
{
    protected static ?int $retriedFromId = null;

    public static function set(int $vantageJobRunId): void
    {
        self::$retriedFromId = $vantageJobRunId;
    }

    public static function peek(): ?int
    {
        return self::$retriedFromId;
    }

    public static function forget(): void
    {
        self::$retriedFromId = null;
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function whileRetrying(int $vantageJobRunId, callable $callback): mixed
    {
        self::set($vantageJobRunId);

        try {
            return $callback();
        } finally {
            self::forget();
        }
    }
}
