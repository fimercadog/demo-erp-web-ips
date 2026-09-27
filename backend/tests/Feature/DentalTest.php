<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DentalTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private User $user;
    private Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->company = Company::first();
        $this->user = User::where('email', 'admin@novaips.test')->first();
        Sanctum::actingAs($this->user, ['*']);

        $this->patient = Patient::where('company_id', $this->company->id)->first()
            ?? Patient::create([
                'company_id' => $this->company->id,
                'name' => 'Paciente Odontológico Test',
                'first_name' => 'Paciente',
                'last_name' => 'Test',
                'document_type' => 'CC',
                'document_number' => '123456789',
                'status' => 'active',
            ]);
    }

    public function test_can_fetch_and_initialize_odontogram(): void
    {
        $response = $this->getJson("/api/odontograms/patient/{$this->patient->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.patient_id', $this->patient->id);
    }

    public function test_can_save_tooth_condition_on_odontogram(): void
    {
        $odontogramResponse = $this->getJson("/api/odontograms/patient/{$this->patient->id}");
        $odontogramId = $odontogramResponse->json('data.id');

        $toothResponse = $this->postJson("/api/odontograms/{$odontogramId}/tooth", [
            'tooth_number' => '16',
            'surface' => 'O',
            'condition' => 'caries',
            'status' => 'required',
            'notes' => 'Caries Grado II en cara oclusal',
        ]);

        $toothResponse->assertStatus(200)
            ->assertJsonPath('data.teeth.0.tooth_number', '16')
            ->assertJsonPath('data.teeth.0.condition', 'caries');
    }

    public function test_can_create_treatment_plan_and_convert_to_invoice(): void
    {
        $planResponse = $this->postJson('/api/treatment-plans', [
            'patient_id' => $this->patient->id,
            'title' => 'Plan de Restauración Dental',
            'discount_amount' => 20000,
            'status' => 'draft',
            'items' => [
                [
                    'tooth_number' => '16',
                    'surface' => 'O',
                    'procedure_name' => 'Resina Estética Fotocurable',
                    'cost' => 180000,
                ],
                [
                    'tooth_number' => '36',
                    'surface' => 'ALL',
                    'procedure_name' => 'Endodoncia Unirradicular',
                    'cost' => 450000,
                ],
            ],
        ]);

        $planResponse->assertStatus(201)
            ->assertJsonPath('data.total_amount', '630000.00')
            ->assertJsonPath('data.final_amount', '610000.00');

        $planId = $planResponse->json('data.id');

        $invoiceResponse = $this->postJson("/api/treatment-plans/{$planId}/convert-to-invoice");

        $invoiceResponse->assertStatus(200)
            ->assertJsonPath('data.total', '610000.00');
    }

    public function test_can_record_dental_evolution(): void
    {
        $response = $this->postJson('/api/dental-evolutions', [
            'patient_id' => $this->patient->id,
            'tooth_number' => '16',
            'procedure_done' => 'Limpieza y obturación con resina nanohíbrida.',
            'observations' => 'Sin complicaciones.',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.tooth_number', '16');
    }

    public function test_can_create_dental_consent(): void
    {
        $response = $this->postJson('/api/dental-consents', [
            'patient_id' => $this->patient->id,
            'procedure_type' => 'endodontics',
            'title' => 'Consentimiento Informado Endodoncia',
            'content' => 'Se explicó el procedimiento y riesgos al paciente.',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.procedure_type', 'endodontics');
    }
}
