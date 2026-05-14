<?php

use Illuminate\Queue\Events\JobProcessed;
use Storvia\Vantage\Listeners\RecordJobSuccess;
use Storvia\Vantage\Models\VantageJob;

it('stores released jobs as released in the database', function () {
    VantageJob::query()->delete();

    $releasedJob = new class
    {
        public function getQueue()
        {
            return 'default';
        }

        public function attempts()
        {
            return 1;
        }

        public function uuid()
        {
            return 'released-uuid';
        }

        public function resolveName()
        {
            return 'App\\Jobs\\RateLimitedJob';
        }

        public function isReleased()
        {
            return true;
        }

        public function isDeletedOrReleased()
        {
            return true;
        }
    };

    $event = new JobProcessed('database', $releasedJob);
    (new RecordJobSuccess)->handle($event);

    $row = VantageJob::where('uuid', 'released-uuid')->first();

    expect($row)->not->toBeNull()
        ->and($row->status)->toBe('released')
        ->and($row->job_class)->toBe('App\\Jobs\\RateLimitedJob')
        ->and($row->finished_at)->not->toBeNull();
});

it('still counts normal processed jobs', function () {
    VantageJob::query()->delete();

    $record = VantageJob::create([
        'uuid' => 'normal-uuid',
        'job_class' => 'App\\Jobs\\NormalJob',
        'status' => 'processing',
        'attempt' => 1,
        'started_at' => now()->subSecond(),
    ]);

    $normalJob = new class
    {
        public function getQueue()
        {
            return 'default';
        }

        public function attempts()
        {
            return 1;
        }

        public function uuid()
        {
            return 'normal-uuid';
        }

        public function resolveName()
        {
            return 'App\\Jobs\\NormalJob';
        }

        public function isReleased()
        {
            return false;
        }

        public function isDeletedOrReleased()
        {
            return false;
        }
    };

    $event = new JobProcessed('database', $normalJob);
    (new RecordJobSuccess)->handle($event);

    $updated = VantageJob::find($record->id);

    expect($updated->status)->toBe('processed')
        ->and($updated->finished_at)->not()->toBeNull();
});
