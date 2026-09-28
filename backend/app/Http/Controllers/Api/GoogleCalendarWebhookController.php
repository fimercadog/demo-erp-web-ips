<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GoogleCalendarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GoogleCalendarWebhookController extends Controller
{
    public function __construct(private readonly GoogleCalendarService $googleCalendarService) {}

    /**
     * Endpoint idempotente para push notifications de Google Calendar.
     * Retorna siempre 200 OK a Google. CERO tokens en logs.
     */
    public function handle(Request $request): JsonResponse
    {
        try {
            $this->googleCalendarService->handleWebhookRequest($request);
        } catch (\Throwable $e) {
            // Idempotent exception swallowing to prevent Google Webhook retries
        }

        return response()->json([
            'status' => 'acknowledged',
        ], 200);
    }
}
