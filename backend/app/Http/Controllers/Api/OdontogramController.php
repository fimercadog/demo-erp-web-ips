<?php

namespace App\Http\Controllers\Api;

use App\Models\Odontogram;
use App\Services\AuditService;
use App\Services\DentalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OdontogramController extends BaseCrudController
{
    protected string $model = Odontogram::class;

    protected array $with = ['patient', 'dentist', 'teeth'];

    protected array $filterable = ['patient_id' => 'patient_id', 'dentist_id' => 'dentist_id', 'type' => 'type', 'status' => 'status'];

    public function __construct(protected DentalService $dentalService)
    {
    }

    public function showByPatient(Request $request, int $patientId): JsonResponse
    {
        $companyId = $this->companyId($request);
        $type = $request->input('type', 'adult');
        $dentistId = $request->input('dentist_id');

        $odontogram = $this->dentalService->getOrInitializeOdontogram($companyId, $patientId, $dentistId ? (int) $dentistId : null, $type);

        return response()->json([
            'data' => $odontogram,
        ]);
    }

    public function saveTooth(Request $request, int $id, AuditService $audit): JsonResponse
    {
        $companyId = $this->companyId($request);
        $validated = $request->validate([
            'tooth_number' => 'required|string|max:10',
            'surface' => 'nullable|string|max:20',
            'condition' => 'required|string|max:50',
            'status' => 'nullable|string|max:30',
            'notes' => 'nullable|string',
        ]);

        $tooth = $this->dentalService->saveToothCondition($companyId, $id, $validated);
        $odontogram = Odontogram::with(['patient', 'dentist', 'teeth'])->where('company_id', $companyId)->findOrFail($id);

        $audit->record('updated', $odontogram, $request, []);

        return response()->json([
            'data' => $odontogram,
            'message' => 'Pieza dental actualizada correctamente.',
        ]);
    }
}
