<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->index(['status', 'scheduled_at'], 'notifications_status_scheduled_at_idx');
            $table->index(['status', 'created_at'], 'notifications_status_created_at_idx');
            $table->index(['batch_id', 'created_at'], 'notifications_batch_created_at_idx');
            $table->index(['status', 'error_type'], 'notifications_status_error_type_idx');
            $table->index(['status', 'error_code'], 'notifications_status_error_code_idx');
            $table->index(['created_at'], 'notifications_created_at_idx');
        });

        Schema::table('dead_letter_notifications', function (Blueprint $table) {
            $table->index(['created_at'], 'dead_letter_created_at_idx');
            $table->index(['channel', 'created_at'], 'dead_letter_channel_created_at_idx');
            $table->index(['error_code'], 'dead_letter_error_code_idx');
        });

        Schema::table('notification_templates', function (Blueprint $table) {
            $table->index(['created_at'], 'notification_templates_created_at_idx');
        });

        Schema::table('admin_users', function (Blueprint $table) {
            $table->index(['created_at'], 'admin_users_created_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('notifications_status_scheduled_at_idx');
            $table->dropIndex('notifications_status_created_at_idx');
            $table->dropIndex('notifications_batch_created_at_idx');
            $table->dropIndex('notifications_status_error_type_idx');
            $table->dropIndex('notifications_status_error_code_idx');
            $table->dropIndex('notifications_created_at_idx');
        });

        Schema::table('dead_letter_notifications', function (Blueprint $table) {
            $table->dropIndex('dead_letter_created_at_idx');
            $table->dropIndex('dead_letter_channel_created_at_idx');
            $table->dropIndex('dead_letter_error_code_idx');
        });

        Schema::table('notification_templates', function (Blueprint $table) {
            $table->dropIndex('notification_templates_created_at_idx');
        });

        Schema::table('admin_users', function (Blueprint $table) {
            $table->dropIndex('admin_users_created_at_idx');
        });
    }
};
