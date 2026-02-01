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
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('batch_id')->nullable()->index();
            $table->string('channel')->index();
            $table->string('priority')->default('normal')->index();
            $table->string('recipient')->index();
            $table->text('content');
            $table->string('status')->default('pending')->index();
            $table->string('idempotency_key')->nullable()->unique();
            $table->string('correlation_id')->nullable()->index();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedTinyInteger('max_attempts')->default(5);
            $table->timestamp('scheduled_at')->nullable()->index();
            $table->timestamp('processing_started_at')->nullable();
            $table->timestamp('sent_at')->nullable()->index();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('provider_message_id')->nullable()->index();
            $table->json('provider_response')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->index(['channel', 'status', 'created_at'], 'notifications_channel_status_created_at_idx');
            $table->index(['priority', 'status', 'created_at'], 'notifications_priority_status_created_at_idx');
            $table->index(['status', 'processing_started_at'], 'notifications_status_processing_started_idx');
            $table->index(['status', 'scheduled_at'], 'notifications_status_scheduled_at_idx');
            $table->index(['status', 'created_at'], 'notifications_status_created_at_idx');
            $table->index(['batch_id', 'created_at'], 'notifications_batch_created_at_idx');
            $table->index(['created_at'], 'notifications_created_at_idx');
            $table->foreign('batch_id')->references('id')->on('notification_batches')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
