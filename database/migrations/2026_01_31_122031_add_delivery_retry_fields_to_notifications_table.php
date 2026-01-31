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
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropColumn(['error_type', 'error_code', 'last_retry_at', 'next_retry_at']);
        });
    }
};
