<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OdontogramTooth extends Model
{
    protected $fillable = [
        'company_id',
        'odontogram_id',
        'tooth_number',
        'surface',
        'condition',
        'status',
        'notes',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function odontogram(): BelongsTo
    {
        return $this->belongsTo(Odontogram::class);
    }
}
