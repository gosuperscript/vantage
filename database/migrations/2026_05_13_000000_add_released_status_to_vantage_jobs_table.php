<?php

use Illuminate\Database\Connection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
        Schema::table('vantage_jobs', function (Blueprint $table) {
            $table->enum('status', ['processing', 'processed', 'failed', 'released'])->default('processing')->change();
        });
    }

    public function down(): void
    {
        Schema::table('vantage_jobs', function (Blueprint $table) {
            $table->enum('status', ['processing', 'processed', 'failed'])->default('processing')->change();
        });
    }
};
