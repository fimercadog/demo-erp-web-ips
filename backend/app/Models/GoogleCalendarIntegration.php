<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class GoogleCalendarIntegration extends Model
{
    use HasFactory;

    protected $table = 'google_calendar_integrations';

    protected $fillable = [
        'company_id',
        'branch_id',
        'user_id',
        'google_email',
        'google_calendar_id',
        'access_token',
        'refresh_token',
        'expires_at',
        'sync_enabled',
        'sync_direction',
        'channel_id',
        'resource_id',
        'last_synced_at',
        'sync_error',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'last_synced_at' => 'datetime',
        'sync_enabled' => 'boolean',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    /**
     * Mutator for access_token: Always encrypt before storing.
     */
    public function setAccessTokenAttribute($value): void
    {
        $this->attributes['access_token'] = $value ? Crypt::encryptString($value) : null;
    }

    /**
     * Accessor for access_token: Always decrypt upon reading.
     */
    public function getAccessTokenAttribute($value): ?string
    {
        if (!$value) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Mutator for refresh_token: Always encrypt before storing.
     */
    public function setRefreshTokenAttribute($value): void
    {
        $this->attributes['refresh_token'] = $value ? Crypt::encryptString($value) : null;
    }

    /**
     * Accessor for refresh_token: Always decrypt upon reading.
     */
    public function getRefreshTokenAttribute($value): ?string
    {
        if (!$value) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function isExpired(): bool
    {
        if (!$this->expires_at) {
            return false;
        }

        return $this->expires_at->isPast();
    }
}
