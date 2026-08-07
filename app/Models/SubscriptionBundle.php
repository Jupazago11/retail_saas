<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionBundle extends Model
{
    protected $fillable = [
        'owner_user_id',
        'name',
        'status',
        'max_companies',
        'discount_type',
        'discount_value',
    ];

    protected function casts(): array
    {
        return [
            'max_companies' => 'integer',
            'discount_value' => 'decimal:2',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'bundle_id');
    }

    public function companies(): HasMany
    {
        return $this->hasMany(SubscriptionBundleCompany::class, 'bundle_id');
    }
}
