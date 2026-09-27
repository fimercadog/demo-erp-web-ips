<?php

namespace App\Http\Controllers\Api;

use App\Models\DentalConsent;
use App\Services\AuditService;
use App\Services\DentalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DentalConsentController extends BaseCrudController
{
    protected string $model = DentalConsent::class;

    protected array $with = ['patient', 'dentist'];

    protected array $searchable = ['title', 'procedure_type'];

    protected array $filterable = ['patient_id' => 'patient_id', 'dentist_id' => 'dentist_id', 'procedure_type' => 'procedure_type'];

    public function __construct(protected DentalService $dentalService)
    {
    }

    public function store(Request $request, AuditService $audit): JsonResponse
    {
        $companyId = $this->companyId($request);
        $validated = $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'dentist_id' => 'nullable|exists:users,id',
            'procedure_type' => 'required|string|max:50',
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'signature' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $consent = $this->dentalService->createConsent($companyId, $validated);
        $audit->record('created', $consent, $request);

        return response()->json([
            'data' => $consent->load($this->with),
            'message' => 'Consentimiento informado registrado exitosamente.',
        ], Response::HTTP_CREATED);
    }
}
