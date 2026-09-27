<?php

namespace App\Services;

use App\Models\DentalConsent;
use App\Models\DentalEvolution;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Odontogram;
use App\Models\OdontogramTooth;
use App\Models\Patient;
use App\Models\TreatmentPlan;
use App\Models\TreatmentPlanItem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DentalService
{
    /** FDI adult tooth numbers (11-18, 21-28, 31-38, 41-48) */
    public const ADULT_TEETH = [
        '18','17','16','15','14','13','12','11',
        '21','22','23','24','25','26','27','28',
        '48','47','46','45','44','43','42','41',
        '31','32','33','34','35','36','37','38'
    ];

    /** FDI pediatric tooth numbers (51-55, 61-65, 71-75, 81-85) */
    public const PEDIATRIC_TEETH = [
        '55','54','53','52','51',
        '61','62','63','64','65',
        '85','84','83','82','81',
        '71','72','73','74','75'
    ];

    public function getOrInitializeOdontogram(int $companyId, int $patientId, ?int $dentistId = null, string $type = 'adult'): Odontogram
    {
        $odontogram = Odontogram::with('teeth')
            ->where('company_id', $companyId)
            ->where('patient_id', $patientId)
            ->where('status', 'active')
            ->first();

        if (! $odontogram) {
            $odontogram = Odontogram::create([
                'company_id' => $companyId,
                'patient_id' => $patientId,
                'dentist_id' => $dentistId,
                'type' => $type,
                'status' => 'active',
                'notes' => 'Odontograma inicial generado automáticamente.',
            ]);
        }

        return $odontogram->load('teeth');
    }

    public function saveToothCondition(int $companyId, int $odontogramId, array $data): OdontogramTooth
    {
        /** @var Odontogram $odontogram */
        $odontogram = Odontogram::where('company_id', $companyId)->findOrFail($odontogramId);

        return OdontogramTooth::updateOrCreate(
            [
                'company_id' => $companyId,
                'odontogram_id' => $odontogram->id,
                'tooth_number' => $data['tooth_number'],
                'surface' => $data['surface'] ?? 'ALL',
            ],
            [
                'condition' => $data['condition'],
                'status' => $data['status'] ?? 'required',
                'notes' => $data['notes'] ?? null,
            ]
        );
    }

    public function createTreatmentPlan(int $companyId, array $data): TreatmentPlan
    {
        return DB::transaction(function () use ($companyId, $data) {
            $items = $data['items'] ?? [];
            $totalAmount = 0;

            foreach ($items as $item) {
                $totalAmount += floatval($item['cost'] ?? 0);
            }

            $discountAmount = floatval($data['discount_amount'] ?? 0);
            $finalAmount = max(0, $totalAmount - $discountAmount);

            /** @var TreatmentPlan $plan */
            $plan = TreatmentPlan::create([
                'company_id' => $companyId,
                'patient_id' => $data['patient_id'],
                'dentist_id' => $data['dentist_id'] ?? null,
                'title' => $data['title'],
                'total_amount' => $totalAmount,
                'discount_amount' => $discountAmount,
                'final_amount' => $finalAmount,
                'status' => $data['status'] ?? 'draft',
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($items as $item) {
                TreatmentPlanItem::create([
                    'company_id' => $companyId,
                    'treatment_plan_id' => $plan->id,
                    'tooth_number' => $item['tooth_number'] ?? null,
                    'surface' => $item['surface'] ?? null,
                    'service_id' => $item['service_id'] ?? null,
                    'procedure_name' => $item['procedure_name'],
                    'cost' => $item['cost'],
                    'status' => $item['status'] ?? 'pending',
                ]);
            }

            return $plan->load('items');
        });
    }

    public function convertTreatmentPlanToInvoice(int $companyId, int $planId): Invoice
    {
        return DB::transaction(function () use ($companyId, $planId) {
            /** @var TreatmentPlan $plan */
            $plan = TreatmentPlan::with(['patient', 'items'])->where('company_id', $companyId)->findOrFail($planId);

            abort_if($plan->invoice_id, 422, 'Este plan de tratamiento ya fue convertido a factura ERP.');

            /** @var Patient $patient */
            $patient = $plan->patient;

            $invoice = Invoice::create([
                'company_id' => $companyId,
                'client_id' => $patient->client_id ?? null,
                'number' => 'FAC-DENT-' . strtoupper(Str::random(6)),
                'status' => 'draft',
                'issue_date' => now(),
                'due_date' => now()->addDays(30),
                'subtotal' => $plan->total_amount,
                'discount' => $plan->discount_amount,
                'tax' => 0,
                'total' => $plan->final_amount,
                'notes' => "Factura generada desde Plan de Tratamiento Odontológico: {$plan->title}",
            ]);

            foreach ($plan->items as $item) {
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'product_name' => "Proc. Dental {$item->tooth_number}: {$item->procedure_name}",
                    'quantity' => 1,
                    'unit_price' => $item->cost,
                    'discount' => 0,
                    'tax' => 0,
                    'line_total' => $item->cost,
                ]);
            }

            $plan->update([
                'status' => 'approved',
                'invoice_id' => $invoice->id,
            ]);

            return $invoice->load('items');
        });
    }

    public function recordEvolution(int $companyId, array $data): DentalEvolution
    {
        return DentalEvolution::create([
            'company_id' => $companyId,
            'patient_id' => $data['patient_id'],
            'appointment_id' => $data['appointment_id'] ?? null,
            'dentist_id' => $data['dentist_id'] ?? null,
            'treatment_plan_id' => $data['treatment_plan_id'] ?? null,
            'tooth_number' => $data['tooth_number'] ?? null,
            'procedure_done' => $data['procedure_done'],
            'observations' => $data['observations'] ?? null,
        ]);
    }

    public function createConsent(int $companyId, array $data): DentalConsent
    {
        return DentalConsent::create([
            'company_id' => $companyId,
            'patient_id' => $data['patient_id'],
            'dentist_id' => $data['dentist_id'] ?? null,
            'procedure_type' => $data['procedure_type'],
            'title' => $data['title'],
            'content' => $data['content'],
            'signed_at' => isset($data['signature']) ? now() : null,
            'signature_path' => $data['signature'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);
    }
}
