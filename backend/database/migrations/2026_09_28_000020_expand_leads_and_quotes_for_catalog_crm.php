<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('vertical')->nullable()->after('company_id');
            $table->foreignId('interested_service_id')->nullable()->after('priority_module')->constrained('services')->nullOnDelete();
            $table->foreignId('interested_product_id')->nullable()->after('interested_service_id')->constrained('products')->nullOnDelete();
            $table->text('notes')->nullable()->after('message');
        });

        Schema::table('quotes', function (Blueprint $table) {
            $table->foreignId('lead_id')->nullable()->after('client_id')->constrained('leads')->nullOnDelete();
            $table->string('vertical')->nullable()->after('company_id');
            $table->decimal('subtotal', 12, 2)->default(0)->after('notes');
            $table->decimal('tax_amount', 12, 2)->default(0)->after('subtotal');
            $table->decimal('discount_amount', 12, 2)->default(0)->after('tax_amount');
            $table->string('pdf_path')->nullable()->after('total');
            $table->timestamp('sent_at')->nullable()->after('pdf_path');
            $table->string('sent_to')->nullable()->after('sent_at');
        });

        Schema::table('quote_items', function (Blueprint $table) {
            $table->foreignId('service_id')->nullable()->after('product_id')->constrained('services')->nullOnDelete();
            $table->string('item_type')->default('product')->after('service_id');
            $table->decimal('subtotal', 12, 2)->default(0)->after('unit_price');
        });
    }

    public function down(): void
    {
        Schema::table('quote_items', function (Blueprint $table) {
            $table->dropForeign(['service_id']);
            $table->dropColumn(['service_id', 'item_type', 'subtotal']);
        });

        Schema::table('quotes', function (Blueprint $table) {
            $table->dropForeign(['lead_id']);
            $table->dropColumn(['lead_id', 'vertical', 'subtotal', 'tax_amount', 'discount_amount', 'pdf_path', 'sent_at', 'sent_to']);
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->dropForeign(['interested_service_id']);
            $table->dropForeign(['interested_product_id']);
            $table->dropColumn(['vertical', 'interested_service_id', 'interested_product_id', 'notes']);
        });
    }
};
