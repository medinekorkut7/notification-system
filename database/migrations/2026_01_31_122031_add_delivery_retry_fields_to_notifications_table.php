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
        Schema::table('notifications', function (Blueprint $table) {
            $table->string('error_type')->nullable()->after('last_error');
            $table->string('error_code')->nullable()->after('error_type');
            $table->timestamp('last_retry_at')->nullable()->after('error_code');
            $table->timestamp('next_retry_at')->nullable()->after('last_retry_at');
            $table->index(['status', 'next_retry_at'], 'notifications_status_next_retry_at_idx');
            $table->index(['status', 'error_type'], 'notifications_status_error_type_idx');
            $table->index(['status', 'error_code'], 'notifications_status_error_code_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('notifications_status_error_code_idx');
            $table->dropIndex('notifications_status_error_type_idx');
            $table->dropIndex('notifications_status_next_retry_at_idx');
            $table->dropColumn(['error_type', 'error_code', 'last_retry_at', 'next_retry_at']);
        });
    }
};
