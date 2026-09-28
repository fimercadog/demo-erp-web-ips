<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesCompany;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Patient;
use App\Models\Service;
use App\Services\AuditService;
use App\Services\SchedulingService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class PublicDentalSchedulingController extends Controller
{
    use ResolvesCompany;

    public function __construct(private readonly SchedulingService $scheduling)
    {
    }

    public function services(Request $request): JsonResponse
    {
        $companyId = $this->companyId($request);
        $services = Service::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'price', 'type', 'estimated_duration_minutes']);

        return response()->json([
            'data' => $services,
        ]);
    }

    public function book(Request $request, AuditService $audit): JsonResponse
    {
        $companyId = $this->companyId($request);

        $validated = $request->validate([
            'service_id' => 'required|exists:services,id',
            'starts_at' => 'required|date_format:Y-m-d H:i:s',
            'client_name' => 'required|string|max:255',
            'client_email' => 'required|email|max:255',
            'client_phone' => 'required|string|max:50',
            'patient_name' => 'required|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $startsAt = Carbon::parse($validated['starts_at']);

        return DB::transaction(function () use ($companyId, $validated, $startsAt, $request, $audit) {
            /** @var Service $service */
            $service = Service::query()->where('company_id', $companyId)->findOrFail($validated['service_id']);
            $duration = $service->estimated_duration_minutes ?: 30;
            $endsAt = $startsAt->copy()->addMinutes($duration);

            $client = Client::firstOrCreate(
                [
                    'company_id' => $companyId,
                    'email' => strtolower($validated['client_email']),
                ],
                [
                    'name' => $validated['client_name'],
                    'phone' => $validated['client_phone'],
                    'status' => 'active',
                ]
            );

            $patient = Patient::firstOrCreate(
                [
                    'company_id' => $companyId,
                    'client_id' => $client->id,
                    'first_name' => $validated['patient_name'],
                ],
                [
                    'name' => $validated['patient_name'],
                    'status' => 'active',
                ]
            );

            \App\Models\Lead::firstOrCreate(
                ['company_id' => $companyId, 'email' => strtolower($validated['client_email'])],
                [
                    'vertical' => 'odontologia',
                    'name' => $validated['client_name'],
                    'phone' => $validated['client_phone'],
                    'interested_service_id' => $service->id,
                    'source' => 'dental_booking',
                    'status' => 'new',
                ]
            );

            $appointment = Appointment::create([
                'company_id' => $companyId,
                'patient_id' => $patient->id,
                'service_id' => $service->id,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'status' => 'scheduled',
                'reason' => "Cita Odontológica: {$service->name}",
                'notes' => $validated['notes'] ?? null,
            ]);

            $audit->record('created', $appointment, $request);

            return response()->json([
                'data' => $appointment->load(['patient', 'service']),
                'message' => 'Cita odontológica agendada exitosamente.',
            ], Response::HTTP_CREATED);
        });
    }
}
