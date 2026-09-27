<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class DentalConsent extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'company_id',
        'patient_id',
        'dentist_id',
        'procedure_type',
        'title',
        'content',
        'signed_at',
        'signature_path',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'signed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (DentalConsent $consent) {
            if (empty($consent->uuid)) {
                $consent->uuid = (string) Str::uuid();
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function dentist(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dentist_id');
    }
}
