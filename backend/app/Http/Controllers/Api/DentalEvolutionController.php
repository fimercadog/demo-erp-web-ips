<?php

namespace App\Http\Controllers\Api;

use App\Models\DentalEvolution;
use App\Services\AuditService;
use App\Services\DentalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DentalEvolutionController extends BaseCrudController
{
    protected string $model = DentalEvolution::class;

    protected array $with = ['patient', 'appointment', 'dentist', 'treatmentPlan'];

    protected array $searchable = ['procedure_done', 'observations'];

    protected array $filterable = ['patient_id' => 'patient_id', 'dentist_id' => 'dentist_id', 'appointment_id' => 'appointment_id', 'treatment_plan_id' => 'treatment_plan_id'];

    public function __construct(protected DentalService $dentalService)
    {
    }

    public function store(Request $request, AuditService $audit): JsonResponse
    {
        $companyId = $this->companyId($request);
        $validated = $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'appointment_id' => 'nullable|exists:appointments,id',
            'dentist_id' => 'nullable|exists:users,id',
            'treatment_plan_id' => 'nullable|exists:treatment_plans,id',
            'tooth_number' => 'nullable|string|max:10',
            'procedure_done' => 'required|string',
            'observations' => 'nullable|string',
        ]);

        $evolution = $this->dentalService->recordEvolution($companyId, $validated);
        $audit->record('created', $evolution, $request);

        return response()->json([
            'data' => $evolution->load($this->with),
            'message' => 'Evolución clínica odontológica registrada exitosamente.',
        ], Response::HTTP_CREATED);
    }
}
