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
        Schema::create('google_calendar_integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('google_email');
            $table->string('google_calendar_id')->default('primary');
            $table->text('access_token');
            $table->text('refresh_token')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('sync_enabled')->default(true);
            $table->string('sync_direction')->default('two_way'); // two_way | export_only | import_only
            $table->string('channel_id')->nullable();
            $table->string('resource_id')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->text('sync_error')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'user_id']);
            $table->index(['company_id', 'channel_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('google_calendar_integrations');
    }
};
