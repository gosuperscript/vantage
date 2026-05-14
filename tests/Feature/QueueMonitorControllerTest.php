<?php

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Str;
use Storvia\Vantage\Models\VantageJob;
use Storvia\Vantage\Support\TagAggregator;

beforeEach(function () {
    VantageJob::query()->delete();
});

it('counts processed and released jobs separately on the dashboard', function () {
    VantageJob::create([
        'uuid' => Str::uuid(),
        'job_class' => 'App\\Jobs\\ProcessedJob',
        'status' => 'processed',
        'created_at' => now()->subDay(),
    ]);

    VantageJob::create([
        'uuid' => Str::uuid(),
        'job_class' => 'App\\Jobs\\ReleasedJob',
        'status' => 'released',
        'created_at' => now()->subDay(),
    ]);

    $this->get('/vantage')
        ->assertOk()
        ->assertViewHas('stats', fn (array $stats) => $stats['processed'] === 1 && $stats['released'] === 1);
});

it('shows retry indicators on the dashboard recent jobs table', function () {
    $failed = VantageJob::create([
        'uuid' => Str::uuid(),
        'job_class' => 'App\\Jobs\\FailedThenRetried',
        'status' => 'failed',
        'created_at' => now(),
    ]);

    VantageJob::create([
        'uuid' => Str::uuid(),
        'job_class' => 'App\\Jobs\\FailedThenRetried',
        'status' => 'processed',
        'retried_from_id' => $failed->id,
        'created_at' => now(),
    ]);

    $this->get('/vantage')
        ->assertOk()
        ->assertSee('Retried', false)
        ->assertSee('Retry of #'.$failed->id, false)
        ->assertSee(route('vantage.jobs.show', $failed->id), false);
});

it('displays dashboard with job statistics', function () {
    // Create test jobs
    VantageJob::create([
        'uuid' => Str::uuid(),
        'job_class' => 'App\\Jobs\\TestJob',
        'status' => 'processed',
        'created_at' => now()->subDays(1),
    ]);

    VantageJob::create([
        'uuid' => Str::uuid(),
        'job_class' => 'App\\Jobs\\TestJob',
        'status' => 'failed',
        'created_at' => now()->subDays(2),
    ]);

    $response = $this->get('/vantage');

    $response->assertStatus(200)
        ->assertSee('Dashboard')
        ->assertSee('Released', false)
        ->assertSee('TestJob', false);
});

it('displays jobs list with filtering', function () {
    VantageJob::create([
        'uuid' => Str::uuid(),
        'job_class' => 'App\\Jobs\\TestJob',
        'status' => 'processed',
        'queue' => 'default',
    ]);

    VantageJob::create([
        'uuid' => Str::uuid(),
        'job_class' => 'App\\Jobs\\FailedJob',
        'status' => 'failed',
        'queue' => 'default',
    ]);

    $response = $this->get('/vantage/jobs');

    $response->assertStatus(200)
        ->assertSee('TestJob', false)
        ->assertSee('FailedJob', false);

    // Filter by status
    $response = $this->get('/vantage/jobs?status=failed');

    $response->assertStatus(200)
        ->assertSee('FailedJob', false)
        ->assertDontSee('TestJob', false);
});

it('displays individual job details', function () {
    $job = VantageJob::create([
        'uuid' => Str::uuid(),
        'job_class' => 'App\\Jobs\\TestJob',
        'status' => 'processed',
        'queue' => 'default',
        'connection' => 'database',
        'duration_ms' => 1500,
        'job_tags' => ['important', 'email'],
    ]);

    $response = $this->get("/vantage/jobs/{$job->id}");

    $response->assertStatus(200)
        ->assertSee('TestJob', false)
        ->assertSee('important', false)
        ->assertSee('email', false)
        ->assertSee('1.5s', false);
});

it('shows disabled prev and next attempt links when there is only one run for the uuid', function () {
    $job = VantageJob::create([
        'uuid' => 'solo-uuid',
        'job_class' => 'App\\Jobs\\TestJob',
        'status' => 'processed',
        'attempt' => 1,
    ]);

    $response = $this->get("/vantage/jobs/{$job->id}");

    $response->assertStatus(200);
    expect(substr_count($response->getContent(), 'aria-disabled="true"'))->toBe(2);
});

it('links prev and next attempt for the same job uuid', function () {
    $uuid = 'shared-uuid-attempts';

    $first = VantageJob::create([
        'uuid' => $uuid,
        'job_class' => 'App\\Jobs\\TestJob',
        'status' => 'failed',
        'attempt' => 1,
    ]);

    $second = VantageJob::create([
        'uuid' => $uuid,
        'job_class' => 'App\\Jobs\\TestJob',
        'status' => 'processed',
        'attempt' => 2,
    ]);

    $third = VantageJob::create([
        'uuid' => $uuid,
        'job_class' => 'App\\Jobs\\TestJob',
        'status' => 'processed',
        'attempt' => 3,
    ]);

    $this->get("/vantage/jobs/{$second->id}")
        ->assertStatus(200)
        ->assertSee(route('vantage.jobs.show', $first->id), false)
        ->assertSee(route('vantage.jobs.show', $third->id), false)
        ->assertDontSee('aria-disabled="true"', false);

    $this->get("/vantage/jobs/{$first->id}")
        ->assertStatus(200)
        ->assertSee(route('vantage.jobs.show', $second->id), false)
        ->assertSee('aria-disabled="true"', false);

    $this->get("/vantage/jobs/{$third->id}")
        ->assertStatus(200)
        ->assertSee(route('vantage.jobs.show', $second->id), false)
        ->assertSee('aria-disabled="true"', false);
});

it('shows first and last attempt badges for rows that share a queue job uuid', function () {
    $uuid = 'shared-uuid-badges';

    VantageJob::create([
        'uuid' => $uuid,
        'job_class' => 'App\\Jobs\\BadgeAttemptJob',
        'status' => 'failed',
        'attempt' => 1,
        'queue' => 'default',
    ]);

    VantageJob::create([
        'uuid' => $uuid,
        'job_class' => 'App\\Jobs\\BadgeAttemptJob',
        'status' => 'processing',
        'attempt' => 2,
        'queue' => 'default',
    ]);

    VantageJob::create([
        'uuid' => $uuid,
        'job_class' => 'App\\Jobs\\BadgeAttemptJob',
        'status' => 'processed',
        'attempt' => 3,
        'queue' => 'default',
    ]);

    $this->get('/vantage/jobs')
        ->assertOk()
        ->assertSee('First', false)
        ->assertSee('Last', false);

    $this->get('/vantage')
        ->assertOk()
        ->assertSee('First', false)
        ->assertSee('Last', false);
});

it('rejects web retry when the failed run is not the last worker attempt for its uuid', function () {
    $this->withoutMiddleware(VerifyCsrfToken::class);

    $uuid = 'block-retry-shared-uuid';

    $olderFailed = VantageJob::create([
        'uuid' => $uuid,
        'job_class' => 'App\\Jobs\\StaleFail',
        'status' => 'failed',
        'attempt' => 1,
        'queue' => 'default',
    ]);

    VantageJob::create([
        'uuid' => $uuid,
        'job_class' => 'App\\Jobs\\StaleFail',
        'status' => 'processed',
        'attempt' => 2,
        'queue' => 'default',
    ]);

    $this->post(route('vantage.jobs.retry', $olderFailed->id))
        ->assertSessionHas('error', VantageJob::retryOnlyLastAttemptMessage());

    $this->get("/vantage/jobs/{$olderFailed->id}")
        ->assertOk()
        ->assertSee(VantageJob::retryOnlyLastAttemptMessage(), false);
});

it('displays retry chain in job details', function () {
    $original = VantageJob::create([
        'uuid' => Str::uuid(),
        'job_class' => 'App\\Jobs\\TestJob',
        'status' => 'failed',
    ]);

    $retry = VantageJob::create([
        'uuid' => Str::uuid(),
        'job_class' => 'App\\Jobs\\TestJob',
        'status' => 'processed',
        'retried_from_id' => $original->id,
    ]);

    $response = $this->get("/vantage/jobs/{$retry->id}");

    $response->assertStatus(200)
        ->assertSee('Retry Chain', false);
});

it('filters jobs by tags', function () {
    VantageJob::create([
        'uuid' => Str::uuid(),
        'job_class' => 'App\\Jobs\\TestJob',
        'status' => 'processed',
        'job_tags' => ['email', 'important'],
    ]);

    VantageJob::create([
        'uuid' => Str::uuid(),
        'job_class' => 'App\\Jobs\\OtherJob',
        'status' => 'processed',
        'job_tags' => ['report'],
    ]);

    $response = $this->get('/vantage/jobs?tags=email');

    $response->assertStatus(200)
        ->assertSee('TestJob', false)
        ->assertDontSee('OtherJob', false);
});

it('shows released counts on the tags page', function () {
    $job = VantageJob::create([
        'uuid' => Str::uuid(),
        'job_class' => 'App\\Jobs\\TaggedReleased',
        'status' => 'released',
        'job_tags' => ['billing'],
        'created_at' => now()->subDay(),
    ]);

    (new TagAggregator)->insertJobTags($job->id, ['billing'], $job->created_at);

    $this->get('/vantage/tags')
        ->assertOk()
        ->assertSee('Released', false)
        ->assertSee('billing', false)
        ->assertViewHas('tagStats', fn (array $tagStats) => isset($tagStats['billing'])
            && ($tagStats['billing']['released'] ?? 0) === 1
            && ($tagStats['billing']['processed'] ?? 0) === 0);
});

it('shows retry indicators on the all jobs list', function () {
    $failed = VantageJob::create([
        'uuid' => Str::uuid(),
        'job_class' => 'App\\Jobs\\ListRetryJob',
        'status' => 'failed',
        'queue' => 'default',
    ]);

    VantageJob::create([
        'uuid' => Str::uuid(),
        'job_class' => 'App\\Jobs\\ListRetryJob',
        'status' => 'processed',
        'queue' => 'default',
        'retried_from_id' => $failed->id,
    ]);

    $this->get('/vantage/jobs')
        ->assertOk()
        ->assertSee('Retried', false)
        ->assertSee('Retry of #'.$failed->id, false);
});

it('shows retry indicators on the failed jobs list', function () {
    $originalFailed = VantageJob::create([
        'uuid' => Str::uuid(),
        'job_class' => 'App\\Jobs\\FailedListRetry',
        'status' => 'failed',
        'exception_class' => 'Exception',
        'exception_message' => 'First failure',
    ]);

    VantageJob::create([
        'uuid' => Str::uuid(),
        'job_class' => 'App\\Jobs\\FailedListRetry',
        'status' => 'processed',
        'retried_from_id' => $originalFailed->id,
    ]);

    $retryFailed = VantageJob::create([
        'uuid' => Str::uuid(),
        'job_class' => 'App\\Jobs\\FailedListRetry',
        'status' => 'failed',
        'retried_from_id' => $originalFailed->id,
        'exception_class' => 'Exception',
        'exception_message' => 'Retry also failed',
    ]);

    $this->get('/vantage/failed')
        ->assertOk()
        ->assertSee('Retried', false)
        ->assertSee('Retry of #'.$originalFailed->id, false)
        ->assertSee('First failure', false)
        ->assertSee('Retry also failed', false);
});

it('displays failed jobs page', function () {
    VantageJob::create([
        'uuid' => Str::uuid(),
        'job_class' => 'App\\Jobs\\TestJob',
        'status' => 'failed',
        'exception_class' => 'Exception',
        'exception_message' => 'Test error',
    ]);

    $response = $this->get('/vantage/failed');

    $response->assertStatus(200)
        ->assertSee('Failed', false)
        ->assertSee('Test error', false);
});
