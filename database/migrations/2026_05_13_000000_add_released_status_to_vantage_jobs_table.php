<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Get the database connection for the migration.
     */
    public function getConnection(): ?string
    {
        return config('vantage.database_connection');
    }

    public function up(): void
    {
        $connection = $this->getConnection();

        if (DB::connection($connection)->getDriverName() === 'pgsql') {
            $this->replacePostgresStatusCheck($connection, [
                'processing', 'processed', 'failed', 'released',
            ]);

            return;
        }

        Schema::connection($connection)->table('vantage_jobs', function (Blueprint $table) {
            $table->enum('status', ['processing', 'processed', 'failed', 'released'])->default('processing')->change();
        });
    }

    public function down(): void
    {
        $connection = $this->getConnection();

        if (DB::connection($connection)->getDriverName() === 'pgsql') {
            $this->replacePostgresStatusCheck($connection, [
                'processing', 'processed', 'failed',
            ]);

            return;
        }

        Schema::connection($connection)->table('vantage_jobs', function (Blueprint $table) {
            $table->enum('status', ['processing', 'processed', 'failed'])->default('processing')->change();
        });
    }

    /**
     * Laravel's enum()->change() emits invalid PostgreSQL (CHECK cannot follow TYPE in one ALTER).
     * Replace the status CHECK constraint explicitly instead.
     *
     * @param  array<int, string>  $allowed
     */
    private function replacePostgresStatusCheck(?string $connection, array $allowed): void
    {
        $db = DB::connection($connection);
        $prefix = $db->getTablePrefix();
        $table = $prefix.'vantage_jobs';
        $tableSql = '"'.str_replace('"', '""', $table).'"';

        foreach (['vantage_jobs_status_check', 'queue_job_runs_status_check'] as $name) {
            $db->statement('ALTER TABLE '.$tableSql.' DROP CONSTRAINT IF EXISTS "'.str_replace('"', '""', $name).'"');
        }

        $list = implode(', ', array_map(static fn (string $v): string => "'".str_replace("'", "''", $v)."'", $allowed));

        $db->statement(
            'ALTER TABLE '.$tableSql.' ADD CONSTRAINT vantage_jobs_status_check CHECK (status::text IN ('.$list.'))'
        );
    }
};
