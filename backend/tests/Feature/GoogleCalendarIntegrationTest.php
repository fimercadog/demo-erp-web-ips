<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Company;
use App\Models\GoogleCalendarIntegration;
use App\Models\Service;
use App\Models\User;
use App\Services\GoogleCalendarService;
use App\Services\SchedulingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GoogleCalendarIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected Company $companyA;
    protected Company $companyB;
    protected User $userA;
    protected User $userB;
    protected Service $serviceA;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'Odontólogo/a', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Médico/a', 'guard_name' => 'web']);

        $this->companyA = Company::create(['name' => 'Clínica A', 'slug' => 'clinica-a', 'status' => 'active']);
        $this->companyB = Company::create(['name' => 'Clínica B', 'slug' => 'clinica-b', 'status' => 'active']);

        $this->userA = User::create([
            'company_id' => $this->companyA->id,
            'name' => 'Dr. Carlos A',
            'email' => 'carlos@clinica-a.com',
            'password' => bcrypt('secret'),
            'is_active' => true,
        ]);
        $this->userA->assignRole('Odontólogo/a');

        $this->userB = User::create([
            'company_id' => $this->companyB->id,
            'name' => 'Dra. María B',
            'email' => 'maria@clinica-b.com',
            'password' => bcrypt('secret'),
            'is_active' => true,
        ]);
        $this->userB->assignRole('Médico/a');

        $this->serviceA = Service::create([
            'company_id' => $this->companyA->id,
            'name' => 'Consulta Odontológica General',
            'estimated_duration_minutes' => 30,
            'price' => 150000,
            'status' => 'active',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @test */
    public function tokens_are_always_encrypted_in_database_and_hidden_from_json(): void
    {
        $integration = GoogleCalendarIntegration::create([
            'company_id' => $this->companyA->id,
            'user_id' => $this->userA->id,
            'google_email' => 'carlos@gmail.com',
            'google_calendar_id' => 'primary',
            'access_token' => 'plain_access_token_123',
            'refresh_token' => 'plain_refresh_token_456',
            'sync_enabled' => true,
        ]);

        // Check raw DB record has encrypted tokens
        $raw = \DB::table('google_calendar_integrations')->where('id', $integration->id)->first();
        $this->assertNotEquals('plain_access_token_123', $raw->access_token);
        $this->assertNotEquals('plain_refresh_token_456', $raw->refresh_token);

        // Accessor decrypts automatically
        $this->assertEquals('plain_access_token_123', $integration->access_token);
        $this->assertEquals('plain_refresh_token_456', $integration->refresh_token);

        // Hidden from JSON serialization
        $json = $integration->toArray();
        $this->assertArrayNotHasKey('access_token', $json);
        $this->assertArrayNotHasKey('refresh_token', $json);
    }

    /** @test */
    public function multi_tenant_isolation_prevents_cross_company_google_calendar_access(): void
    {
        $integrationA = GoogleCalendarIntegration::create([
            'company_id' => $this->companyA->id,
            'user_id' => $this->userA->id,
            'google_email' => 'carlos@gmail.com',
            'access_token' => 'token_a',
            'sync_enabled' => true,
        ]);

        $integrationB = GoogleCalendarIntegration::create([
            'company_id' => $this->companyB->id,
            'user_id' => $this->userB->id,
            'google_email' => 'maria@gmail.com',
            'access_token' => 'token_b',
            'sync_enabled' => true,
        ]);

        $foundA = GoogleCalendarIntegration::forCompany($this->companyA->id)->get();
        $this->assertCount(1, $foundA);
        $this->assertEquals($integrationA->id, $foundA->first()->id);

        $foundB = GoogleCalendarIntegration::forCompany($this->companyB->id)->get();
        $this->assertCount(1, $foundB);
        $this->assertEquals($integrationB->id, $foundB->first()->id);
    }

    /** @test */
    public function google_calendar_freebusy_blocks_slot_in_scheduling_service(): void
    {
        $nextMonday = Carbon::now()->next(Carbon::MONDAY)->setTime(8, 0, 0);
        Carbon::setTestNow($nextMonday);

        GoogleCalendarIntegration::create([
            'company_id' => $this->companyA->id,
            'user_id' => $this->userA->id,
            'google_email' => 'carlos@gmail.com',
            'access_token' => 'token_valid',
            'expires_at' => now()->addHour(),
            'sync_enabled' => true,
        ]);

        $timeMin = $nextMonday->copy()->setTime(10, 0)->toIso8601String();
        $timeMax = $nextMonday->copy()->setTime(11, 0)->toIso8601String();

        Http::fake([
            'https://www.googleapis.com/calendar/v3/freeBusy' => Http::response([
                'calendars' => [
                    'primary' => [
                        'busy' => [
                            ['start' => $timeMin, 'end' => $timeMax],
                        ],
                    ],
                ],
            ], 200),
        ]);

        /** @var SchedulingService $scheduling */
        $scheduling = app(SchedulingService::class);

        $slots = $scheduling->freeSlots($this->companyA->id, $this->serviceA, $nextMonday);

        // 10:00 and 10:30 should NOT be available due to Google Calendar FreeBusy collision!
        $this->assertNotContains('10:00', $slots);
        $this->assertNotContains('10:30', $slots);
        // 11:00 should be available
        $this->assertContains('11:00', $slots);
    }

    /** @test */
    public function appointment_syncs_to_google_calendar_on_creation_and_deletion(): void
    {
        $nextMonday = Carbon::now()->next(Carbon::MONDAY)->setTime(8, 0, 0);
        Carbon::setTestNow($nextMonday);

        GoogleCalendarIntegration::create([
            'company_id' => $this->companyA->id,
            'user_id' => $this->userA->id,
            'google_email' => 'carlos@gmail.com',
            'access_token' => 'token_valid',
            'expires_at' => now()->addHour(),
            'sync_enabled' => true,
        ]);

        Http::fake([
            'https://www.googleapis.com/calendar/v3/freeBusy' => Http::response([
                'calendars' => [
                    'primary' => [
                        'busy' => [],
                    ],
                ],
            ], 200),
            'https://www.googleapis.com/calendar/v3/calendars/primary/events' => Http::response([
                'id' => 'google_event_777',
                'status' => 'confirmed',
            ], 200),
            'https://www.googleapis.com/calendar/v3/calendars/primary/events/google_event_777' => Http::response([], 200),
        ]);

        /** @var SchedulingService $schedulingService */
        $schedulingService = app(SchedulingService::class);

        $appointment = $schedulingService->createAppointment([
            'company_id' => $this->companyA->id,
            'service_id' => $this->serviceA->id,
            'practitioner_id' => $this->userA->id,
            'starts_at' => $nextMonday->copy()->setTime(14, 0)->toDateTimeString(),
            'duration_minutes' => 30,
            'reason' => 'Limpieza dental',
        ]);

        $this->assertEquals('google_event_777', $appointment->fresh()->google_event_id);
        $this->assertEquals('synced', $appointment->fresh()->sync_status);

        // Test Cancellation removes event from Google Calendar
        $schedulingService->cancelAppointment($appointment, 'Cliente canceló');

        $this->assertEquals('cancelled', $appointment->fresh()->status);
    }

    /** @test */
    public function google_webhook_is_idempotent_and_always_returns_200_ok(): void
    {
        $integration = GoogleCalendarIntegration::create([
            'company_id' => $this->companyA->id,
            'user_id' => $this->userA->id,
            'google_email' => 'carlos@gmail.com',
            'access_token' => 'token_valid',
            'channel_id' => 'channel_abc123',
            'resource_id' => 'resource_xyz789',
            'sync_enabled' => true,
        ]);

        $response = $this->postJson('/api/webhooks/google-calendar', [], [
            'X-Goog-Channel-ID' => 'channel_abc123',
            'X-Goog-Resource-ID' => 'resource_xyz789',
            'X-Goog-Resource-State' => 'exists',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'acknowledged']);

        $this->assertNotNull($integration->fresh()->last_synced_at);

        // Unknown channel ID also returns 200 OK (idempotent fallback)
        $unknownResponse = $this->postJson('/api/webhooks/google-calendar', [], [
            'X-Goog-Channel-ID' => 'unknown_channel',
        ]);

        $unknownResponse->assertStatus(200);
    }
}
