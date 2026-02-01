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
        Schema::create('dead_letter_notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('notification_id')->nullable()->index();
            $table->string('channel')->nullable()->index();
            $table->string('recipient')->nullable()->index();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('error_type')->nullable();
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->json('payload')->nullable();
            $table->json('last_response')->nullable();
            $table->timestamps();
            $table->index(['recipient', 'channel'], 'dead_letter_recipient_channel_idx');
            $table->index(['created_at'], 'dead_letter_created_at_idx');
            $table->index(['channel', 'created_at'], 'dead_letter_channel_created_at_idx');
            $table->index(['error_code'], 'dead_letter_error_code_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dead_letter_notifications');
    }
};
