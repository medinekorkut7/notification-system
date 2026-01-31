<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('notification_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('idempotency_key')->nullable()->unique();
            $table->string('correlation_id')->nullable()->index();
            $table->string('status')->default('pending')->index();
            $table->unsignedInteger('total_count')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_batches');
    }
};
