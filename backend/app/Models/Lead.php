<?php

namespace App\Models;

use Database\Factories\LeadFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lead extends Model
{
    /** @use HasFactory<LeadFactory> */
    use HasFactory;

    protected $fillable = [
        'company_id', 'vertical', 'name', 'company_name', 'email', 'phone', 'employee_count',
        'priority_module', 'interested_service_id', 'interested_product_id', 'message', 'notes',
        'source', 'status', 'ip_address',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function interestedService(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'interested_service_id');
    }

    public function interestedProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'interested_product_id');
    }

    public function quotes(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Quote::class);
    }
}
