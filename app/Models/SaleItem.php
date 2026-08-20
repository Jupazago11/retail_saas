<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleItem extends Model
{
    protected $fillable = [
        'sale_id',
        'product_id',
        'product_presentation_id',
        'product_variant_id',
        'description_snapshot',
        'promotion_snapshot',
        'quantity',
        'base_quantity',
        'returned_quantity',
        'returned_base_quantity',
        'unit_price',
        'discount_amount',
        'tax_rate',
        'tax_amount',
        'line_subtotal',
        'line_total',
        'cost_snapshot',
        'product_tax_rate',
    ];

    protected function casts(): array
    {
        return [
            'promotion_snapshot' => 'array',
            'quantity' => 'decimal:6',
            'base_quantity' => 'decimal:6',
            'returned_quantity' => 'decimal:6',
            'returned_base_quantity' => 'decimal:6',
            'unit_price' => 'decimal:4',
            'discount_amount' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'line_subtotal' => 'decimal:2',
            'line_total' => 'decimal:2',
            'cost_snapshot' => 'decimal:4',
            'product_tax_rate' => 'decimal:2',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function presentation(): BelongsTo
    {
        return $this->belongsTo(ProductPresentation::class, 'product_presentation_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
