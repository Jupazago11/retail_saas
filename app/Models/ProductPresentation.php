<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductPresentation extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'product_id',
        'unit_id',
        'name',
        'barcode',
        'conversion_factor',
        'price_1',
        'price_2',
        'price_3',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'conversion_factor' => 'decimal:6',
            'price_1' => 'decimal:2',
            'price_2' => 'decimal:2',
            'price_3' => 'decimal:2',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function purchaseItems(): HasMany
    {
        return $this->hasMany(PurchaseItem::class, 'product_presentation_id');
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class, 'product_presentation_id');
    }
}
