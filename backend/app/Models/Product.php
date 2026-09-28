<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    // `image_url` NO es asignable en masa: lo fija solo el servidor en
    // ProductController::image() tras subir el archivo por el endpoint de upload.
    // Asi un POST/PUT/PATCH no puede meter una URL externa arbitraria en el catalogo.
    protected $fillable = [
        'company_id', 'client_uuid', 'vertical', 'sku', 'name', 'description',
        'category_id', 'brand_id', 'unit_id',
        'unit_price', 'compare_price', 'cost_price', 'reorder_level', 'status', 'is_public', 'display_order',
        'requires_batch', 'requires_expiration',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'compare_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'is_public' => 'boolean',
        'display_order' => 'integer',
        'requires_batch' => 'boolean',
        'requires_expiration' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(ProductBatch::class);
    }

    /** Existencia actual = suma de movimientos, sin tabla de stock aparte. */
    public function stockOnHand(?int $warehouseId = null): int
    {
        return (int) $this->stockMovements()
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->sum('quantity');
    }
}
