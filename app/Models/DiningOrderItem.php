<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiningOrderItem extends Model
{
    protected $fillable = [
        'frozen_sale_id',
        'product_id',
        'product_variant_id',
        'quantity',
        'kitchen_status',
        'created_by',
        'modified_by',
        'is_modified',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:6',
            'is_modified' => 'boolean',
        ];
    }

    public function frozenSale(): BelongsTo
    {
        return $this->belongsTo(FrozenSale::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function modifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'modified_by');
    }
}
