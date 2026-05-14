<?php

namespace Storvia\Vantage\Support\Traits;

use Illuminate\Support\Str;
use Storvia\Vantage\Support\VantageLogger;

trait ExtractsRetryOf
{
    /**
     * Extract the retry_of ID from the job payload
     */
    protected function getRetryOf($event): ?int
    {
        $retryOf = null;

        try {
            $payload = $event->job->payload();

            // Injected by Queue::createPayloadUsing (survives SerializesModels __serialize)
            if (array_key_exists('vantage_retry_of', $payload)) {
                $marker = $payload['vantage_retry_of'];
                if (is_numeric($marker)) {
                    return (int) $marker;
                }
            }

            $cmd = $payload['data']['command'] ?? null;

            if (is_object($cmd) && property_exists($cmd, 'queueMonitorRetryOf')) {
                $retryOf = (int) $cmd->queueMonitorRetryOf;
            } elseif (is_string($cmd)) {
                $obj = @unserialize($cmd, ['allowed_classes' => true]);
                if (is_object($obj) && property_exists($obj, 'queueMonitorRetryOf')) {
                    $retryOf = (int) $obj->queueMonitorRetryOf;
                }
            }
        } catch (\Throwable $e) {
            // Log error if needed, but don't break the application
            VantageLogger::debug('Error extracting retryOf', ['error' => $e->getMessage()]);
        }

        VantageLogger::debug('QM retryOf check', ['retryOf' => $retryOf]);

        return $retryOf;
    }

    /**
     * Same as {@see getRetryOf} but only when the worker is on the first dequeue attempt.
     *
     * Laravel keeps the same queue payload (including vantage_retry_of / queueMonitorRetryOf)
     * for automatic retries of the same job; those should not get a second retried_from_id link.
     */
    protected function getRetryOfForFirstQueueAttempt($event): ?int
    {
        if (method_exists($event->job, 'attempts') && (int) $event->job->attempts() !== 1) {
            return null;
        }

        return $this->getRetryOf($event);
    }

    /**
     * Get the job class name
     */
    protected function getJobClass($event): string
    {
        return method_exists($event->job, 'resolveName')
            ? $event->job->resolveName()
            : get_class($event->job);
    }

    /**
     * Get the best available UUID for the job
     */
    protected function getBestUuid($event): string
    {
        // Prefer a stable id if available (Laravel versions differ here)
        if (method_exists($event->job, 'uuid') && $event->job->uuid()) {
            return (string) $event->job->uuid();
        }
        if (method_exists($event->job, 'getJobId') && $event->job->getJobId()) {
            return (string) $event->job->getJobId();
        }

        // Otherwise we'll generate a UUID
        return (string) Str::uuid();
    }
}
