<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Subscription extends Model
{
    protected $fillable = [
        'company_id',
        'bundle_id',
        'plan_id',
        'status',
        'starts_at',
        'ends_at',
        'trial_ends_at',
        'paid_at',
        'payment_reference',
        'billing_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'paid_at' => 'datetime',
            'billing_snapshot' => 'array',
        ];
    }

    public function isPaid(): bool
    {
        return $this->paid_at !== null;
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function bundle(): BelongsTo
    {
        return $this->belongsTo(SubscriptionBundle::class, 'bundle_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function couponRedemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }

    public function paymentAttachments(): HasMany
    {
        return $this->hasMany(PaymentAttachment::class);
    }

    public function isPastDue(): bool
    {
        return in_array($this->status, ['active', 'trialing'], true)
            && $this->ends_at !== null
            && $this->ends_at->isPast();
    }

    public function scopeDueForExpiration(Builder $query, Carbon $moment): void
    {
        $query
            ->whereNull('bundle_id')
            ->whereIn('status', ['active', 'trialing'])
            ->whereNotNull('ends_at')
            ->where('ends_at', '<', $moment);
    }

    public function scopeActiveAt(Builder $query, Carbon $moment): void
    {
        $query
            ->where(function (Builder $statusQuery) use ($moment) {
                $statusQuery
                    ->where('status', 'active')
                    ->orWhere(function (Builder $trialQuery) use ($moment) {
                        $trialQuery
                            ->where('status', 'trialing')
                            ->where(function (Builder $validTrialQuery) use ($moment) {
                                $validTrialQuery
                                    ->whereNull('trial_ends_at')
                                    ->orWhere('trial_ends_at', '>=', $moment);
                            });
                    });
            })
            ->where(function (Builder $startsQuery) use ($moment) {
                $startsQuery
                    ->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', $moment);
            })
            ->where(function (Builder $endsQuery) use ($moment) {
                $endsQuery
                    ->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', $moment);
            });
    }
}
