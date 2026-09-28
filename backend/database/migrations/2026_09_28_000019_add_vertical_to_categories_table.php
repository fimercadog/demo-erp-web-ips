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
        Schema::table('categories', function (Blueprint $table) {
            $table->string('vertical')->nullable()->after('company_id')->index(); // ips | estetica | veterinaria | odontologia | sueroterapia
            $table->boolean('is_public')->default(true)->after('status');
            $table->integer('display_order')->default(0)->after('is_public');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn([
                'vertical',
                'is_public',
                'display_order',
            ]);
        });
    }
};
