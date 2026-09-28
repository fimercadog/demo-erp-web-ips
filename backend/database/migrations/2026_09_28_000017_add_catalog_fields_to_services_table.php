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
        Schema::table('services', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('company_id')->constrained('categories')->nullOnDelete();
            $table->string('vertical')->nullable()->after('category_id')->index(); // ips | estetica | veterinaria | odontologia | sueroterapia
            $table->string('image_url', 500)->nullable()->after('description');
            $table->decimal('compare_price', 12, 2)->nullable()->after('price');
            $table->boolean('is_public')->default(true)->index()->after('status');
            $table->integer('display_order')->default(0)->after('is_public');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropColumn([
                'category_id',
                'vertical',
                'image_url',
                'compare_price',
                'is_public',
                'display_order',
            ]);
        });
    }
};
