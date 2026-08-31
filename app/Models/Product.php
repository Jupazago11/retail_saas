<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'category_id',
        'brand_id',
        'supplier_id',
        'base_unit_id',
        'tax_id',
        'tax_rate',
        'name',
        'sku',
        'barcode',
        'description',
        'cost',
        'price_1',
        'price_2',
        'price_3',
        'flexible_price',
        'margin_1',
        'margin_2',
        'margin_3',
        'tracks_inventory',
        'minimum_stock',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'cost' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'price_1' => 'decimal:2',
            'price_2' => 'decimal:2',
            'price_3' => 'decimal:2',
            'margin_1' => 'decimal:2',
            'margin_2' => 'decimal:2',
            'margin_3' => 'decimal:2',
            'minimum_stock' => 'decimal:3',
            'tracks_inventory' => 'boolean',
            'flexible_price' => 'boolean',
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

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'base_unit_id');
    }

    public function presentations(): HasMany
    {
        return $this->hasMany(ProductPresentation::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function purchaseItems(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function inventoryAdjustmentItems(): HasMany
    {
        return $this->hasMany(InventoryAdjustmentItem::class);
    }

    public function inventoryTransferItems(): HasMany
    {
        return $this->hasMany(InventoryTransferItem::class);
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function inventoryBalances(): HasMany
    {
        return $this->hasMany(InventoryBalance::class);
    }
}
