<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Odontogram extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'company_id',
        'patient_id',
        'dentist_id',
        'type',
        'status',
        'notes',
    ];

    protected static function booted(): void
    {
        static::creating(function (Odontogram $odontogram) {
            if (empty($odontogram->uuid)) {
                $odontogram->uuid = (string) Str::uuid();
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

    public function teeth(): HasMany
    {
        return $this->hasMany(OdontogramTooth::class);
    }
}
