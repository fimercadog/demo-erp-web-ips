<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\GoogleCalendarIntegration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GoogleCalendarService
{
    protected string $clientId;
    protected string $clientSecret;
    protected string $redirectUri;

    public function __construct()
    {
        $this->clientId = (string) config('services.google.client_id', env('GOOGLE_CLIENT_ID', ''));
        $this->clientSecret = (string) config('services.google.client_secret', env('GOOGLE_CLIENT_SECRET', ''));
        $this->redirectUri = (string) config('services.google.redirect_uri', env('GOOGLE_REDIRECT_URI', ''));
    }

    /**
     * Generate OAuth Authorization URL for Google Calendar integration.
     */
    public function getAuthUrl(int $companyId, ?int $userId = null): string
    {
        $state = json_encode([
            'company_id' => $companyId,
            'user_id' => $userId,
            'nonce' => Str::random(16),
        ]);

        $params = [
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/calendar https://www.googleapis.com/auth/userinfo.email',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => base64_encode($state),
        ];

        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    }

    /**
     * Process OAuth callback and save/update GoogleCalendarIntegration.
     * ZERO tokens are logged to preserve security.
     */
    public function handleCallback(string $code, int $companyId, ?int $userId = null): GoogleCalendarIntegration
    {
        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri' => $this->redirectUri,
            'grant_type' => 'authorization_code',
            'code' => $code,
        ]);

        if (! $response->successful()) {
            Log::error('Google Calendar OAuth error', ['company_id' => $companyId, 'status' => $response->status()]);
            throw new \RuntimeException('Failed to exchange Google OAuth code: ' . $response->body());
        }

        $data = $response->json();
        $accessToken = $data['access_token'] ?? null;
        $refreshToken = $data['refresh_token'] ?? null;
        $expiresIn = $data['expires_in'] ?? 3600;

        if (! $accessToken) {
            throw new \RuntimeException('Google OAuth response did not include access token.');
        }

        // Fetch user email from Google UserInfo endpoint
        $userResponse = Http::withToken($accessToken)->get('https://www.googleapis.com/oauth2/v2/userinfo');
        $googleEmail = $userResponse->successful() ? ($userResponse->json()['email'] ?? 'google-user@fidelos.com') : 'google-user@fidelos.com';

        $integration = GoogleCalendarIntegration::updateOrCreate(
            [
                'company_id' => $companyId,
                'user_id' => $userId,
            ],
            [
                'google_email' => $googleEmail,
                'google_calendar_id' => 'primary',
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'expires_at' => now()->addSeconds($expiresIn - 60),
                'sync_enabled' => true,
                'sync_direction' => 'two_way',
                'last_synced_at' => now(),
                'sync_error' => null,
            ]
        );

        return $integration;
    }

    /**
     * Refresh access token if expired.
     */
    public function refreshAccessToken(GoogleCalendarIntegration $integration): bool
    {
        if (! $integration->refresh_token) {
            $integration->update([
                'sync_enabled' => false,
                'sync_error' => 'Missing refresh token. Re-authentication required.',
            ]);
            return false;
        }

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $integration->refresh_token,
            'grant_type' => 'refresh_token',
        ]);

        if (! $response->successful()) {
            $integration->update([
                'sync_enabled' => false,
                'sync_error' => 'Failed to refresh Google OAuth token.',
            ]);
            return false;
        }

        $data = $response->json();
        $newAccessToken = $data['access_token'] ?? null;
        $expiresIn = $data['expires_in'] ?? 3600;

        if ($newAccessToken) {
            $integration->update([
                'access_token' => $newAccessToken,
                'expires_at' => now()->addSeconds($expiresIn - 60),
                'sync_error' => null,
            ]);
            return true;
        }

        return false;
    }

    /**
     * Ensure valid token for API call.
     */
    protected function getValidToken(GoogleCalendarIntegration $integration): ?string
    {
        if ($integration->isExpired()) {
            $refreshed = $this->refreshAccessToken($integration);
            if (! $refreshed) {
                return null;
            }
            $integration->refresh();
        }

        return $integration->access_token;
    }

    /**
     * Query FreeBusy API for busy intervals in Google Calendar.
     * Returns array of ['start' => Carbon, 'end' => Carbon].
     */
    public function getFreeBusySlots(GoogleCalendarIntegration $integration, string $startDate, string $endDate): array
    {
        if (! $integration->sync_enabled) {
            return [];
        }

        $token = $this->getValidToken($integration);
        if (! $token) {
            return [];
        }

        $calendarId = $integration->google_calendar_id ?: 'primary';
        $timeMin = \Carbon\Carbon::parse($startDate)->startOfDay()->toIso8601String();
        $timeMax = \Carbon\Carbon::parse($endDate)->endOfDay()->toIso8601String();

        $response = Http::withToken($token)->post('https://www.googleapis.com/calendar/v3/freeBusy', [
            'timeMin' => $timeMin,
            'timeMax' => $timeMax,
            'items' => [
                ['id' => $calendarId],
            ],
        ]);

        if (! $response->successful()) {
            Log::warning('Failed to fetch Google Calendar FreeBusy', [
                'integration_id' => $integration->id,
                'company_id' => $integration->company_id,
            ]);
            return [];
        }

        $data = $response->json();
        $busyList = $data['calendars'][$calendarId]['busy'] ?? [];

        $slots = [];
        foreach ($busyList as $busy) {
            if (isset($busy['start'], $busy['end'])) {
                $slots[] = [
                    'start' => \Carbon\Carbon::parse($busy['start']),
                    'end' => \Carbon\Carbon::parse($busy['end']),
                ];
            }
        }

        return $slots;
    }

    /**
     * Sync FidelOS Appointment to Google Calendar (Insert or Update).
     */
    public function syncAppointmentToGoogle(Appointment $appointment): bool
    {
        $appointment->loadMissing(['practitioner', 'service', 'patient', 'client', 'company']);

        // Find applicable GoogleCalendarIntegration (practitioner specific or company default)
        $integration = GoogleCalendarIntegration::where('company_id', $appointment->company_id)
            ->where(function ($query) use ($appointment) {
                if ($appointment->practitioner_id) {
                    $query->where('user_id', $appointment->practitioner_id)
                        ->orWhereNull('user_id');
                } else {
                    $query->whereNull('user_id');
                }
            })
            ->where('sync_enabled', true)
            ->first();

        if (! $integration) {
            $appointment->update([
                'sync_status' => 'disabled',
                'sync_error' => 'No active Google Calendar integration found for company or practitioner.',
            ]);
            return false;
        }

        $token = $this->getValidToken($integration);
        if (! $token) {
            $appointment->update([
                'sync_status' => 'error',
                'sync_error' => 'Failed to obtain valid access token.',
            ]);
            return false;
        }

        $calendarId = $integration->google_calendar_id ?: 'primary';
        $summary = ($appointment->service?->name ?? 'Cita FidelOS') . ' - FidelOS ERP';
        
        $patientName = $appointment->patient?->full_name 
            ?? $appointment->client?->name 
            ?? 'Cliente Directo';
            
        $description = sprintf(
            "Cita FidelOS\nPaciente/Cliente: %s\nServicio: %s\nEstado: %s\nRazon: %s",
            $patientName,
            $appointment->service?->name ?? 'General',
            strtoupper($appointment->status),
            $appointment->reason ?? 'Sin motivo registrado'
        );

        if ($appointment->is_domiciliary) {
            $description .= sprintf(
                "\n\n--- DETALLES DOMICILIARIOS ---\nDirección: %s\nBarrio/Ciudad: %s, %s\nReferencia: %s",
                $appointment->address ?? 'N/A',
                $appointment->neighborhood ?? 'N/A',
                $appointment->city ?? 'N/A',
                $appointment->address_reference ?? 'N/A'
            );
        }

        $location = $appointment->is_domiciliary 
            ? ($appointment->address . ', ' . $appointment->city) 
            : ($appointment->branch?->address ?? $appointment->company?->name);

        $eventData = [
            'summary' => $summary,
            'description' => $description,
            'location' => $location,
            'start' => [
                'dateTime' => \Carbon\Carbon::parse($appointment->starts_at)->toIso8601String(),
            ],
            'end' => [
                'dateTime' => \Carbon\Carbon::parse($appointment->ends_at)->toIso8601String(),
            ],
            'reminders' => [
                'useDefault' => true,
            ],
        ];

        if ($appointment->google_event_id) {
            // Update existing event
            $url = "https://www.googleapis.com/calendar/v3/calendars/{$calendarId}/events/{$appointment->google_event_id}";
            $response = Http::withToken($token)->patch($url, $eventData);
        } else {
            // Create new event
            $url = "https://www.googleapis.com/calendar/v3/calendars/{$calendarId}/events";
            $response = Http::withToken($token)->post($url, $eventData);
        }

        if ($response->successful()) {
            $eventId = $response->json()['id'] ?? $appointment->google_event_id;
            $appointment->update([
                'google_calendar_id' => $calendarId,
                'google_event_id' => $eventId,
                'sync_status' => 'synced',
                'last_synced_at' => now(),
                'sync_error' => null,
            ]);
            $integration->update(['last_synced_at' => now()]);
            return true;
        }

        $errorBody = $response->body();
        Log::error('Google Calendar event sync failed', [
            'appointment_id' => $appointment->id,
            'status' => $response->status(),
        ]);

        $appointment->update([
            'sync_status' => 'error',
            'sync_error' => 'API Error (' . $response->status() . '): ' . substr($errorBody, 0, 250),
        ]);

        return false;
    }

    /**
     * Delete Google Calendar Event upon appointment cancellation or deletion.
     */
    public function deleteGoogleEvent(Appointment $appointment): bool
    {
        if (! $appointment->google_event_id) {
            return true;
        }

        $integration = GoogleCalendarIntegration::where('company_id', $appointment->company_id)
            ->where('sync_enabled', true)
            ->first();

        if (! $integration) {
            return false;
        }

        $token = $this->getValidToken($integration);
        if (! $token) {
            return false;
        }

        $calendarId = $appointment->google_calendar_id ?: ($integration->google_calendar_id ?: 'primary');
        $url = "https://www.googleapis.com/calendar/v3/calendars/{$calendarId}/events/{$appointment->google_event_id}";

        $response = Http::withToken($token)->delete($url);

        if ($response->successful() || $response->status() === 404) {
            $appointment->update([
                'google_event_id' => null,
                'sync_status' => 'synced',
                'last_synced_at' => now(),
            ]);
            return true;
        }

        return false;
    }

    /**
     * Idempotent Webhook Handler for Google Push Notifications.
     */
    public function handleWebhookRequest(\Illuminate\Http\Request $request): bool
    {
        $channelId = $request->header('X-Goog-Channel-ID');
        $resourceId = $request->header('X-Goog-Resource-ID');
        $resourceState = strtolower((string) $request->header('X-Goog-Resource-State'));

        if (! $channelId) {
            return false;
        }

        // Find integration matching channel_id
        $integration = GoogleCalendarIntegration::where('channel_id', $channelId)
            ->orWhere('resource_id', $resourceId)
            ->first();

        if (! $integration) {
            // Idempotent return: Channel not found or expired webhook
            return true;
        }

        if (in_array($resourceState, ['sync', 'exists'], true)) {
            // Real event change or sync ping in Google Calendar -> update last_synced_at
            $integration->update(['last_synced_at' => now()]);
            return true;
        }

        return true;
    }
}
