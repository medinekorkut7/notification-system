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
        Schema::table('notification_batches', function (Blueprint $table) {
            $table->string('trace_id')->nullable()->after('correlation_id');
            $table->string('span_id')->nullable()->after('trace_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notification_batches', function (Blueprint $table) {
            $table->dropColumn(['trace_id', 'span_id']);
        });
    }
};
