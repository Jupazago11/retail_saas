<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends Model
{
    protected $fillable = [
        'company_id',
        'warehouse_id',
        'product_id',
        'product_variant_id',
        'movement_type',
        'reference_type',
        'reference_id',
        'quantity_in',
        'quantity_out',
        'unit_cost',
        'balance_quantity',
        'balance_cost',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity_in' => 'decimal:6',
            'quantity_out' => 'decimal:6',
            'unit_cost' => 'decimal:4',
            'balance_quantity' => 'decimal:6',
            'balance_cost' => 'decimal:4',
            'occurred_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
