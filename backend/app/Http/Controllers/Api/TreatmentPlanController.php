<?php

namespace App\Http\Controllers\Api;

use App\Models\TreatmentPlan;
use App\Services\AuditService;
use App\Services\DentalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TreatmentPlanController extends BaseCrudController
{
    protected string $model = TreatmentPlan::class;

    protected array $with = ['patient', 'dentist', 'items', 'invoice'];

    protected array $searchable = ['title'];

    protected array $filterable = ['patient_id' => 'patient_id', 'dentist_id' => 'dentist_id', 'status' => 'status'];

    public function __construct(protected DentalService $dentalService)
    {
    }

    public function store(Request $request, AuditService $audit): JsonResponse
    {
        $companyId = $this->companyId($request);
        $validated = $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'dentist_id' => 'nullable|exists:users,id',
            'title' => 'required|string|max:255',
            'discount_amount' => 'nullable|numeric|min:0',
            'status' => 'nullable|string|in:draft,approved,in_progress,completed,cancelled',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.tooth_number' => 'nullable|string|max:10',
            'items.*.surface' => 'nullable|string|max:20',
            'items.*.service_id' => 'nullable|exists:services,id',
            'items.*.procedure_name' => 'required|string|max:255',
            'items.*.cost' => 'required|numeric|min:0',
            'items.*.status' => 'nullable|string|in:pending,in_progress,completed',
        ]);

        $plan = $this->dentalService->createTreatmentPlan($companyId, $validated);
        $audit->record('created', $plan, $request);

        return response()->json([
            'data' => $plan,
            'message' => 'Plan de tratamiento creado exitosamente.',
        ], Response::HTTP_CREATED);
    }

    public function convertToInvoice(Request $request, int $id, AuditService $audit): JsonResponse
    {
        $companyId = $this->companyId($request);
        $invoice = $this->dentalService->convertTreatmentPlanToInvoice($companyId, $id);
        $plan = TreatmentPlan::where('company_id', $companyId)->find($id);

        if ($plan) {
            $audit->record('updated', $plan, $request, ['status' => 'approved']);
        }

        return response()->json([
            'data' => $invoice,
            'message' => 'Plan de tratamiento convertido exitosamente a Factura ERP.',
        ]);
    }
}
