<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Purchase extends Model
{
    protected $fillable = [
        'company_id',
        'branch_id',
        'warehouse_id',
        'supplier_id',
        'supplier_name',
        'invoice_number',
        'purchase_type',
        'status',
        'notes',
        'purchased_at',
        'due_at',
        'posted_to_inventory_at',
        'returned_from_inventory_at',
        'amount_paid',
        'balance_due',
        'paid_at',
        'subtotal',
        'tax_total',
        'total',
    ];

    protected function casts(): array
    {
        return [
            'purchased_at' => 'datetime',
            'due_at' => 'datetime',
            'posted_to_inventory_at' => 'datetime',
            'returned_from_inventory_at' => 'datetime',
            'paid_at' => 'datetime',
            'amount_paid' => 'decimal:2',
            'balance_due' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'total' => 'decimal:2',
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

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function payableMovements(): HasMany
    {
        return $this->hasMany(PayableMovement::class);
    }
}
