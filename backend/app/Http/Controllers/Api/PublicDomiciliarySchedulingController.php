<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Services\DomiciliaryAppointmentService;
use App\Services\PublicTenantResolverService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicDomiciliarySchedulingController extends Controller
{
    public function __construct(
        private DomiciliaryAppointmentService $domiciliaryService,
        private PublicTenantResolverService $tenantResolver
    ) {
    }

    /**
     * Get active IV Sueroterapia services for public booking.
     */
    public function services(Request $request): JsonResponse
    {
        $company = $this->tenantResolver->resolveCompany($request);

        $services = Service::where('company_id', $company->id)
            ->where('status', 'active')
            ->get();

        return response()->json([
            'data' => $services,
        ]);
    }

    /**
     * Public booking for domiciliary IV therapy.
     */
    public function book(Request $request): JsonResponse
    {
        $company = $this->tenantResolver->resolveCompany($request);

        // Honeypot check
        if (! empty($request->input('website'))) {
            return response()->json(['message' => 'Solicitud procesada.'], 200);
        }

        $validated = $request->validate([
            'service_id' => 'required|exists:services,id',
            'client_name' => 'required|string|max:255',
            'email' => 'required|email',
            'phone' => 'required|string|max:50',
            'address' => 'required|string|max:255',
            'city' => 'nullable|string|max:100',
            'neighborhood' => 'required|string|max:100',
            'address_reference' => 'nullable|string|max:500',
            'starts_at' => 'required|date|after:now',
            'notes' => 'nullable|string|max:500',
            'consent' => 'required|accepted',
        ]);

        $validated['company_id'] = $company->id;
        $validated['city'] = $validated['city'] ?? $company->city;

        \App\Models\Lead::firstOrCreate(
            ['company_id' => $company->id, 'email' => strtolower($validated['email'])],
            [
                'vertical' => 'sueroterapia',
                'name' => $validated['client_name'],
                'phone' => $validated['phone'],
                'interested_service_id' => $validated['service_id'],
                'message' => 'Residencia: '.$validated['address'].' ('.$validated['neighborhood'].')',
                'source' => 'domiciliary_booking',
                'status' => 'new',
            ]
        );

        $appointment = $this->domiciliaryService->bookDomiciliary($validated);

        return response()->json([
            'message' => '¡Tu cita de sueroterapia a domicilio ha sido agendada con éxito! Un profesional confirmará tu atención.',
            'data' => [
                'appointment_id' => $appointment->id,
                'service' => $appointment->service?->name,
                'starts_at' => $appointment->starts_at->toIso8601String(),
                'address' => $appointment->address,
                'neighborhood' => $appointment->neighborhood,
                'dispatch_status' => $appointment->dispatch_status,
            ],
        ], 201);
    }
}
