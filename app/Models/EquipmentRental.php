<?php

namespace App\Models;

use App\Enums\EquipmentRentalStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class EquipmentRental extends Model
{
    protected $fillable = [
        'company_id',
        'equipment_type',
        'company_sequence',
        'status',
        'replaces_rental_id',
        'unit_cost',
        'monthly_price',
        'requested_at',
        'started_at',
        'pending_return_at',
        'returned_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => EquipmentRentalStatus::class,
            'unit_cost' => 'decimal:2',
            'monthly_price' => 'decimal:2',
            'requested_at' => 'datetime',
            'started_at' => 'datetime',
            'pending_return_at' => 'datetime',
            'returned_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function replaces(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaces_rental_id');
    }

    public function replacedBy(): HasOne
    {
        return $this->hasOne(self::class, 'replaces_rental_id');
    }

    public function monthsElapsed(): int
    {
        if (! $this->started_at) {
            return 0;
        }

        return (int) $this->started_at->diffInMonths(now());
    }

    public function amountRecovered(): float
    {
        return min((float) $this->monthly_price * $this->monthsElapsed(), (float) $this->unit_cost);
    }

    public function isRecovered(): bool
    {
        return $this->started_at !== null && $this->amountRecovered() >= (float) $this->unit_cost;
    }
}
