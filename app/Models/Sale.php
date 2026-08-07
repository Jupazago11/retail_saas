<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Sale extends Model
{
    protected $fillable = [
        'company_id',
        'branch_id',
        'warehouse_id',
        'document_sequence',
        'document_number',
        'cash_register_id',
        'customer_id',
        'credit_account_id',
        'user_id',
        'sale_type',
        'status',
        'pricing_snapshot',
        'notes',
        'sold_at',
        'credit_due_at',
        'posted_to_inventory_at',
        'cancelled_at',
        'returned_at',
        'replaces_sale_id',
        'subtotal',
        'discount_total',
        'tax_total',
        'grand_total',
    ];

    protected function casts(): array
    {
        return [
            'pricing_snapshot' => 'array',
            'sold_at' => 'datetime',
            'credit_due_at' => 'datetime',
            'posted_to_inventory_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'returned_at' => 'datetime',
            'document_sequence' => 'integer',
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'grand_total' => 'decimal:2',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function cashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function creditAccount(): BelongsTo
    {
        return $this->belongsTo(CreditAccount::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function convertedFrozenSales(): HasMany
    {
        return $this->hasMany(FrozenSale::class, 'converted_sale_id');
    }

    public function replacesSale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'replaces_sale_id');
    }

    public function replacedBySale(): HasOne
    {
        return $this->hasOne(Sale::class, 'replaces_sale_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function creditMovements(): HasMany
    {
        return $this->hasMany(CreditMovement::class);
    }

    public function loyaltyMovements(): HasMany
    {
        return $this->hasMany(LoyaltyMovement::class);
    }
}
