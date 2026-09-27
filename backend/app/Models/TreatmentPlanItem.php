<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TreatmentPlanItem extends Model
{
    protected $fillable = [
        'company_id',
        'treatment_plan_id',
        'tooth_number',
        'surface',
        'service_id',
        'procedure_name',
        'cost',
        'status',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'cost' => 'decimal:2',
            'completed_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function treatmentPlan(): BelongsTo
    {
        return $this->belongsTo(TreatmentPlan::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
