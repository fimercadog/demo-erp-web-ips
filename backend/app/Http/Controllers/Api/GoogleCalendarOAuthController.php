<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesCompany;
use App\Http\Controllers\Controller;
use App\Models\GoogleCalendarIntegration;
use App\Services\GoogleCalendarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GoogleCalendarOAuthController extends Controller
{
    use ResolvesCompany;

    public function __construct(private readonly GoogleCalendarService $googleCalendarService) {}

    /**
     * Devuelve la URL de autorización de Google OAuth2 para la empresa y usuario actual.
     */
    public function redirect(Request $request): JsonResponse
    {
        $companyId = $this->companyId($request);
        $userId = $request->user()?->id;

        $authUrl = $this->googleCalendarService->getAuthUrl($companyId, $userId);

        return response()->json([
            'url' => $authUrl,
        ]);
    }

    /**
     * Procesa el callback de OAuth2 enviado desde Google.
     * CERO tokens en logs ni en la respuesta JSON pública.
     */
    public function callback(Request $request): JsonResponse
    {
        $request->validate([
            'code' => ['required', 'string'],
            'state' => ['required', 'string'],
        ]);

        $stateData = json_decode(base64_decode($request->string('state')), true);
        $companyId = $stateData['company_id'] ?? $this->companyId($request);
        $userId = $stateData['user_id'] ?? $request->user()?->id;

        try {
            $integration = $this->googleCalendarService->handleCallback(
                $request->string('code'),
                (int) $companyId,
                $userId ? (int) $userId : null
            );

            return response()->json([
                'message' => 'Integración con Google Calendar configurada exitosamente.',
                'integration' => [
                    'id' => $integration->id,
                    'company_id' => $integration->company_id,
                    'google_email' => $integration->google_email,
                    'google_calendar_id' => $integration->google_calendar_id,
                    'sync_enabled' => $integration->sync_enabled,
                    'sync_direction' => $integration->sync_direction,
                    'last_synced_at' => $integration->last_synced_at?->toIso8601String(),
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Error al vincular la cuenta de Google Calendar: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Estado actual de la integración con Google Calendar.
     */
    public function status(Request $request): JsonResponse
    {
        $companyId = $this->companyId($request);
        $userId = $request->user()?->id;

        $integration = GoogleCalendarIntegration::where('company_id', $companyId)
            ->when($userId, fn ($q) => $q->where('user_id', $userId)->orWhereNull('user_id'))
            ->first();

        if (! $integration) {
            return response()->json([
                'connected' => false,
                'integration' => null,
            ]);
        }

        return response()->json([
            'connected' => (bool) $integration->sync_enabled,
            'integration' => [
                'id' => $integration->id,
                'company_id' => $integration->company_id,
                'user_id' => $integration->user_id,
                'google_email' => $integration->google_email,
                'google_calendar_id' => $integration->google_calendar_id,
                'sync_enabled' => $integration->sync_enabled,
                'sync_direction' => $integration->sync_direction,
                'last_synced_at' => $integration->last_synced_at?->toIso8601String(),
                'sync_error' => $integration->sync_error,
            ],
        ]);
    }

    /**
     * Desconectar integración con Google Calendar.
     */
    public function disconnect(Request $request): JsonResponse
    {
        $companyId = $this->companyId($request);
        $userId = $request->user()?->id;

        $integration = GoogleCalendarIntegration::where('company_id', $companyId)
            ->when($userId, fn ($q) => $q->where('user_id', $userId)->orWhereNull('user_id'))
            ->first();

        if ($integration) {
            $integration->delete();
        }

        return response()->json([
            'message' => 'Integración con Google Calendar desvinculada.',
        ]);
    }
}
