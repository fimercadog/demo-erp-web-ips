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
        Schema::table('appointments', function (Blueprint $table) {
            $table->string('google_calendar_id')->nullable()->after('dispatch_status');
            $table->string('google_event_id')->nullable()->after('google_calendar_id');
            $table->string('sync_status')->default('pending')->after('google_event_id'); // synced | pending | error | disabled
            $table->timestamp('last_synced_at')->nullable()->after('sync_status');
            $table->text('sync_error')->nullable()->after('last_synced_at');

            $table->index(['company_id', 'google_event_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'google_event_id']);
            $table->dropColumn([
                'google_calendar_id',
                'google_event_id',
                'sync_status',
                'last_synced_at',
                'sync_error',
            ]);
        });
    }
};
