<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionBundleCompany extends Model
{
    protected $fillable = [
        'bundle_id',
        'company_id',
        'plan_id',
    ];

    public function bundle(): BelongsTo
    {
        return $this->belongsTo(SubscriptionBundle::class, 'bundle_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
