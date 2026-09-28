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
        Schema::table('products', function (Blueprint $table) {
            $table->string('vertical')->nullable()->after('category_id')->index(); // ips | estetica | veterinaria | odontologia | sueroterapia
            $table->decimal('compare_price', 12, 2)->nullable()->after('unit_price');
            $table->integer('display_order')->default(0)->after('is_public');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'vertical',
                'compare_price',
                'display_order',
            ]);
        });
    }
};
