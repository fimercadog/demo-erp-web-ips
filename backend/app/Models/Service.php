<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Service extends Model
{
    protected $fillable = [
        'company_id',
        'category_id',
        'vertical',
        'name',
        'description',
        'type',
        'estimated_duration_minutes',
        'price',
        'compare_price',
        'status',
        'is_public',
        'display_order',
    ];

    // `image_url` fijado por el servidor en ServiceController::image() tras subida de archivo
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'compare_price' => 'decimal:2',
            'estimated_duration_minutes' => 'integer',
            'is_public' => 'boolean',
            'display_order' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
