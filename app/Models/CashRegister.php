<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CashRegister extends Model
{
    use SoftDeletes;

    public const PRINTER_TYPES = [
        'thermal_58mm' => 'Termica 58mm',
        'thermal_80mm' => 'Termica 80mm',
        'letter_a4' => 'Carta / A4',
    ];

    protected $fillable = [
        'company_id',
        'branch_id',
        'name',
        'code',
        'status',
        'is_primary',
        'printer_type',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
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

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function frozenSales(): HasMany
    {
        return $this->hasMany(FrozenSale::class);
    }

    public function cashSessions(): HasMany
    {
        return $this->hasMany(CashSession::class);
    }
}
