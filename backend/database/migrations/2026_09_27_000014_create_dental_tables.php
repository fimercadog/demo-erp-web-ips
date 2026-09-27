<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('odontograms', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dentist_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type')->default('adult'); // adult, pediatric, mixed
            $table->string('status')->default('active'); // active, archived
            $table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['company_id', 'patient_id']);
        });

        Schema::create('odontogram_teeth', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('odontogram_id')->constrained()->cascadeOnDelete();
            $table->string('tooth_number', 10); // FDI notation: 11-48, 51-85
            $table->string('surface', 20)->default('ALL'); // O, M, D, V, L, ALL, APEX
            $table->string('condition', 50); // caries, restoration, crown, endo, missing, implant, prosthesis, sealant
            $table->string('status', 30)->default('required'); // existing, required, performed
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'odontogram_id', 'tooth_number']);
        });

        Schema::create('treatment_plans', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dentist_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('final_amount', 12, 2)->default(0);
            $table->string('status', 30)->default('draft'); // draft, approved, in_progress, completed, cancelled
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['company_id', 'patient_id']);
        });

        Schema::create('treatment_plan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('treatment_plan_id')->constrained()->cascadeOnDelete();
            $table->string('tooth_number', 10)->nullable();
            $table->string('surface', 20)->nullable();
            $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->string('procedure_name');
            $table->decimal('cost', 12, 2)->default(0);
            $table->string('status', 30)->default('pending'); // pending, in_progress, completed
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'treatment_plan_id']);
        });

        Schema::create('dental_evolutions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->foreignId('dentist_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('treatment_plan_id')->nullable()->constrained('treatment_plans')->nullOnDelete();
            $table->string('tooth_number', 10)->nullable();
            $table->text('procedure_done');
            $table->text('observations')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'patient_id']);
        });

        Schema::create('dental_consents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dentist_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('procedure_type', 50); // surgery, orthodontics, endodontics, implantology, general
            $table->string('title');
            $table->text('content');
            $table->timestamp('signed_at')->nullable();
            $table->string('signature_path', 500)->nullable();
            $table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['company_id', 'patient_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dental_consents');
        Schema::dropIfExists('dental_evolutions');
        Schema::dropIfExists('treatment_plan_items');
        Schema::dropIfExists('treatment_plans');
        Schema::dropIfExists('odontogram_teeth');
        Schema::dropIfExists('odontograms');
    }
};
