<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Company;
use App\Models\DentalConsent;
use App\Models\DentalEvolution;
use App\Models\Odontogram;
use App\Models\OdontogramTooth;
use App\Models\Patient;
use App\Models\Service;
use App\Models\TreatmentPlan;
use App\Models\TreatmentPlanItem;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DentalSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->first();
        if (! $company) {
            return;
        }

        $companyId = $company->id;
        $dentist = User::query()->where('company_id', $companyId)->first();

        // 1. Servicios Odontológicos
        $services = [
            [
                'name' => 'Profilaxis y Detartraje Ultrasónico',
                'description' => 'Higiene oral profunda con remoción de placa calcificada y pulido dental.',
                'price' => 120000,
                'estimated_duration_minutes' => 45,
                'type' => 'procedure',
                'status' => 'active',
            ],
            [
                'name' => 'Resina Estética Fotocurable',
                'description' => 'Restauración estética directa con resina nanohíbrida por superficie.',
                'price' => 180000,
                'estimated_duration_minutes' => 45,
                'type' => 'procedure',
                'status' => 'active',
            ],
            [
                'name' => 'Tratamiento de Conducto (Endodoncia Unirradicular)',
                'description' => 'Tratamiento biomecánico del sistema de conductos radiculares.',
                'price' => 450000,
                'estimated_duration_minutes' => 60,
                'type' => 'procedure',
                'status' => 'active',
            ],
            [
                'name' => 'Blanqueamiento Dental LED',
                'description' => 'Aclaramiento dental con peróxido de carbamida activado por luz fría.',
                'price' => 350000,
                'estimated_duration_minutes' => 60,
                'type' => 'procedure',
                'status' => 'active',
            ],
            [
                'name' => 'Cirugía de Implante Dental de Titanio',
                'description' => 'Colocación osteointegrada de implante titanio grado médico.',
                'price' => 1800000,
                'estimated_duration_minutes' => 90,
                'type' => 'procedure',
                'status' => 'active',
            ],
        ];

        foreach ($services as $srvData) {
            Service::updateOrCreate(
                [
                    'company_id' => $companyId,
                    'name' => $srvData['name'],
                ],
                $srvData
            );
        }

        // 2. Cliente y Paciente Odontológico Demo
        $client = Client::firstOrCreate(
            [
                'company_id' => $companyId,
                'email' => 'camila.ramirez@example.com',
            ],
            [
                'name' => 'Camila Ramírez',
                'phone' => '+57 300 555 1234',
                'status' => 'active',
            ]
        );

        $patient = Patient::firstOrCreate(
            [
                'company_id' => $companyId,
                'document_number' => '1098765432',
            ],
            [
                'client_id' => $client->id,
                'name' => 'Camila Ramírez',
                'first_name' => 'Camila',
                'last_name' => 'Ramírez',
                'document_type' => 'CC',
                'sex' => 'F',
                'birth_date' => '1995-04-12',
                'phone' => '+57 300 555 1234',
                'email' => 'camila.ramirez@example.com',
                'status' => 'active',
            ]
        );

        // 3. Cita Odontológica Demo
        $profilaxisService = Service::where('company_id', $companyId)->where('name', 'like', '%Profilaxis%')->first();
        $appointment = Appointment::create([
            'company_id' => $companyId,
            'patient_id' => $patient->id,
            'service_id' => $profilaxisService?->id,
            'practitioner_id' => $dentist?->id,
            'starts_at' => now()->addDays(1)->setTime(10, 0),
            'ends_at' => now()->addDays(1)->setTime(10, 45),
            'status' => 'confirmed',
            'reason' => 'Valoración Odontológica Inicial y Limpieza',
            'notes' => 'Paciente solicita revisión de pieza 16 por molestia con frío.',
        ]);

        // 4. Odontograma Inicial con Hallazgos
        $odontogram = Odontogram::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $companyId,
            'patient_id' => $patient->id,
            'dentist_id' => $dentist?->id,
            'type' => 'adult',
            'status' => 'active',
            'notes' => 'Odontograma de ingreso. Caries oclusal en 16 y endodoncia requerida en 36.',
        ]);

        $findings = [
            ['tooth' => '16', 'surface' => 'O', 'condition' => 'caries', 'status' => 'required', 'notes' => 'Caries oclusal Grado II'],
            ['tooth' => '21', 'surface' => 'V', 'condition' => 'restoration', 'status' => 'existing', 'notes' => 'Resina vestibular previa en buen estado'],
            ['tooth' => '36', 'surface' => 'ALL', 'condition' => 'endo', 'status' => 'required', 'notes' => 'Necrosis pulpar, requiere endodoncia'],
            ['tooth' => '48', 'surface' => 'ALL', 'condition' => 'missing', 'status' => 'existing', 'notes' => 'Pieza extraída previamente'],
        ];

        foreach ($findings as $f) {
            OdontogramTooth::create([
                'company_id' => $companyId,
                'odontogram_id' => $odontogram->id,
                'tooth_number' => $f['tooth'],
                'surface' => $f['surface'],
                'condition' => $f['condition'],
                'status' => $f['status'],
                'notes' => $f['notes'],
            ]);
        }

        // 5. Plan de Tratamiento
        $treatmentPlan = TreatmentPlan::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $companyId,
            'patient_id' => $patient->id,
            'dentist_id' => $dentist?->id,
            'title' => 'Plan de Rehabilitación Oral e Higiene',
            'total_amount' => 750000,
            'discount_amount' => 50000,
            'final_amount' => 700000,
            'status' => 'approved',
            'notes' => 'Aprobado por el paciente en cuotas.',
        ]);

        TreatmentPlanItem::create([
            'company_id' => $companyId,
            'treatment_plan_id' => $treatmentPlan->id,
            'tooth_number' => '16',
            'surface' => 'O',
            'service_id' => Service::where('company_id', $companyId)->where('name', 'like', '%Resina%')->first()?->id,
            'procedure_name' => 'Resina Estética Fotocurable Oclusal',
            'cost' => 180000,
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        TreatmentPlanItem::create([
            'company_id' => $companyId,
            'treatment_plan_id' => $treatmentPlan->id,
            'tooth_number' => '36',
            'surface' => 'ALL',
            'service_id' => Service::where('company_id', $companyId)->where('name', 'like', '%Endodoncia%')->first()?->id,
            'procedure_name' => 'Endodoncia Unirradicular Pieza 36',
            'cost' => 450000,
            'status' => 'pending',
        ]);

        TreatmentPlanItem::create([
            'company_id' => $companyId,
            'treatment_plan_id' => $treatmentPlan->id,
            'tooth_number' => 'ALL',
            'surface' => 'ALL',
            'service_id' => $profilaxisService?->id,
            'procedure_name' => 'Profilaxis y Detartraje Ultrasónico',
            'cost' => 120000,
            'status' => 'pending',
        ]);

        // 6. Evolución Clínica
        DentalEvolution::create([
            'company_id' => $companyId,
            'patient_id' => $patient->id,
            'appointment_id' => $appointment->id,
            'dentist_id' => $dentist?->id,
            'treatment_plan_id' => $treatmentPlan->id,
            'tooth_number' => '16',
            'procedure_done' => 'Aislamiento absoluto, eliminación de tejido cariado y obturación con resina nanohíbrida A2 fotocurada.',
            'observations' => 'Paciente tolera procedimiento sin complicaciones. Oclusión ajustada.',
        ]);

        // 7. Consentimiento Informado
        DentalConsent::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $companyId,
            'patient_id' => $patient->id,
            'dentist_id' => $dentist?->id,
            'procedure_type' => 'endodontics',
            'title' => 'Consentimiento Informado para Tratamiento de Endodoncia',
            'content' => 'Por medio del presente documento declaro que se me ha explicado de manera clara y comprensible el procedimiento de tratamiento de conductos radiculares para la pieza dental 36, incluyendo sus riesgos, beneficios y alternativas.',
            'signed_at' => now(),
            'notes' => 'Firmado digitalmente durante la consulta inicial.',
        ]);
    }
}
