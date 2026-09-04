<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EquipmentType extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'unit_cost',
        'monthly_price',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'unit_cost' => 'decimal:2',
            'monthly_price' => 'decimal:2',
        ];
    }
}
