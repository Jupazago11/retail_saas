<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromotionComboItem extends Model
{
    protected $fillable = [
        'promotion_id',
        'product_id',
        'product_variant_id',
        'required_quantity',
    ];

    protected function casts(): array
    {
        return [
            'required_quantity' => 'decimal:6',
        ];
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
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
