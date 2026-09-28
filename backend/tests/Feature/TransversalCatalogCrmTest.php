<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Lead;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TransversalCatalogCrmTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Mail::fake();
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'deals.manage']);
        $this->company = Company::factory()->create(['name' => 'FidelOS Core Test IPS']);
    }

    public function test_visitor_submits_quote_request_for_all_verticals_and_generates_lead_quote_pdf(): void
    {
        $product = Product::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Sérum Facial Antioxidante',
            'unit_price' => 120000,
            'is_public' => true,
            'status' => 'active',
            'vertical' => 'estetica',
        ]);

        $service = Service::create([
            'company_id' => $this->company->id,
            'name' => 'Limpieza Facial Médica',
            'price' => 150000,
            'is_public' => true,
            'status' => 'active',
            'vertical' => 'estetica',
        ]);

        $payload = [
            'vertical' => 'estetica',
            'name' => 'Carolina Mendoza',
            'email' => 'carolina@estetica.example',
            'phone' => '+57 300 111 2233',
            'company_name' => 'Spa BioEstética',
            'message' => 'Deseo cotizar paquete facial estético.',
            'consent' => true,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
                ['service_id' => $service->id, 'quantity' => 1],
            ],
        ];

        $res = $this->postJson('/api/public/catalog/quote-requests', $payload)
            ->assertStatus(201)
            ->assertJsonStructure(['message', 'quote_id', 'lead_id']);

        $quoteId = $res->json('quote_id');
        $leadId = $res->json('lead_id');

        // 1. Verificar Lead en BD
        $this->assertDatabaseHas('leads', [
            'id' => $leadId,
            'company_id' => $this->company->id,
            'vertical' => 'estetica',
            'email' => 'carolina@estetica.example',
            'status' => 'new',
        ]);

        // 2. Verificar Cliente en BD
        $this->assertDatabaseHas('clients', [
            'company_id' => $this->company->id,
            'email' => 'carolina@estetica.example',
        ]);

        // 3. Verificar Cotización e Ítems con Precios Históricos
        $quote = Quote::find($quoteId);
        $this->assertNotNull($quote);
        $this->assertEquals('estetica', $quote->vertical);
        $this->assertEquals(390000, (float) $quote->total); // (2 * 120.000) + (1 * 150.000) = 390.000
        $this->assertCount(2, $quote->items);

        // 4. Verificar PDF Generado en Storage
        $this->assertNotNull($quote->pdf_path);
        Storage::disk('public')->assertExists($quote->pdf_path);
    }

    public function test_historical_pricing_preservation_when_catalog_prices_change(): void
    {
        $product = Product::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Vacuna Polivalente',
            'unit_price' => 50000,
            'is_public' => true,
            'status' => 'active',
            'vertical' => 'veterinaria',
        ]);

        // Solicitar cotización Q1 con el precio original de $50,000
        $res1 = $this->postJson('/api/public/catalog/quote-requests', [
            'vertical' => 'veterinaria',
            'name' => 'Dr. Fernando',
            'email' => 'fernando@vet.example',
            'consent' => true,
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ])->assertStatus(201);

        $q1 = Quote::find($res1->json('quote_id'));
        $this->assertEquals(100000, (float) $q1->total);

        // Administrador cambia el precio del catálogo a $80,000
        $product->update(['unit_price' => 80000]);

        // Solicitar nueva cotización Q2
        $res2 = $this->postJson('/api/public/catalog/quote-requests', [
            'vertical' => 'veterinaria',
            'name' => 'Dra. Patricia',
            'email' => 'patricia@vet.example',
            'consent' => true,
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ])->assertStatus(201);

        $q2 = Quote::find($res2->json('quote_id'));

        // ASERSION CRÍTICA DE PRECIO HISTÓRICO: Q1 mantiene $50,000 unitario ($100,000 total), Q2 usa $80,000 ($160,000 total)
        $this->assertEquals(100000, (float) $q1->fresh()->total);
        $this->assertEquals(50000, (float) $q1->items->first()->unit_price);

        $this->assertEquals(160000, (float) $q2->total);
        $this->assertEquals(80000, (float) $q2->items->first()->unit_price);
    }

    public function test_admin_can_download_pdf_and_send_email_quote(): void
    {
        $user = User::factory()->create(['company_id' => $this->company->id]);
        $user->givePermissionTo('deals.manage');

        $client = Client::factory()->create(['company_id' => $this->company->id, 'email' => 'cliente@odontologia.example']);
        $quote = Quote::create([
            'company_id' => $this->company->id,
            'client_id' => $client->id,
            'vertical' => 'odontologia',
            'title' => 'Cotización Implante Dental',
            'status' => 'draft',
            'total' => 450000,
        ]);
        $quote->items()->create([
            'product_name' => 'Implante Titanio',
            'quantity' => 1,
            'unit_price' => 450000,
        ]);

        // Descarga de PDF por endpoint de API
        $this->actingAs($user, 'sanctum')
            ->getJson("/api/quotes/{$quote->id}/pdf")
            ->assertOk();

        // Envío de Cotización por Correo
        $this->actingAs($user, 'sanctum')
            ->postJson("/api/quotes/{$quote->id}/send-email")
            ->assertOk()
            ->assertJsonPath('data.status', 'sent');

        $this->assertDatabaseHas('quotes', [
            'id' => $quote->id,
            'status' => 'sent',
            'sent_to' => 'cliente@odontologia.example',
        ]);
    }

    public function test_multi_tenant_isolation_prevents_cross_company_quote_access(): void
    {
        $companyA = Company::factory()->create(['name' => 'Empresa A']);
        $companyB = Company::factory()->create(['name' => 'Empresa B']);

        $userA = User::factory()->create(['company_id' => $companyA->id]);
        $userA->givePermissionTo('deals.manage');

        $clientB = Client::factory()->create(['company_id' => $companyB->id]);
        $quoteB = Quote::create([
            'company_id' => $companyB->id,
            'client_id' => $clientB->id,
            'title' => 'Cotización Secreta B',
            'status' => 'draft',
            'total' => 1000000,
        ]);

        $this->actingAs($userA, 'sanctum')
            ->getJson("/api/quotes/{$quoteB->id}")
            ->assertStatus(404);
    }

    public function test_dental_booking_creates_core_lead_and_appointment(): void
    {
        $service = Service::create([
            'company_id' => $this->company->id,
            'name' => 'Profilaxis Dental Ultrasónica',
            'price' => 120000,
            'estimated_duration_minutes' => 45,
            'status' => 'active',
            'vertical' => 'odontologia',
        ]);

        $payload = [
            'service_id' => $service->id,
            'starts_at' => now()->addDays(2)->setTime(10, 0)->format('Y-m-d H:i:s'),
            'client_name' => 'Lina María',
            'client_email' => 'lina@dental.example',
            'client_phone' => '+57 301 999 8877',
            'patient_name' => 'Lina María',
            'notes' => 'Valoración e higiene inicial',
        ];

        $this->postJson('/api/public/dental/book', $payload)->assertStatus(201);

        $this->assertDatabaseHas('leads', [
            'company_id' => $this->company->id,
            'vertical' => 'odontologia',
            'email' => 'lina@dental.example',
            'source' => 'dental_booking',
        ]);
    }

    public function test_sueroterapia_domiciliary_booking_creates_core_lead_and_appointment(): void
    {
        $service = Service::create([
            'company_id' => $this->company->id,
            'name' => 'Suero Multivitamínico e Inmunológico',
            'price' => 180000,
            'estimated_duration_minutes' => 60,
            'status' => 'active',
            'vertical' => 'sueroterapia',
        ]);

        $payload = [
            'service_id' => $service->id,
            'client_name' => 'Roberto Gómez',
            'email' => 'roberto@suero.example',
            'phone' => '+57 310 222 3344',
            'address' => 'Calle 100 #15-30',
            'neighborhood' => 'Chicó',
            'starts_at' => now()->addDays(1)->setTime(14, 0)->toIso8601String(),
            'consent' => true,
        ];

        $this->postJson('/api/public/domiciliary/book', $payload)->assertStatus(201);

        $this->assertDatabaseHas('leads', [
            'company_id' => $this->company->id,
            'vertical' => 'sueroterapia',
            'email' => 'roberto@suero.example',
            'source' => 'domiciliary_booking',
        ]);
    }
}
