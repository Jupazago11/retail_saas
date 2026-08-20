<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Services\Authorization\CurrentCompanyPermissionResolver;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'name',
    'username',
    'email',
    'password',
    'status',
    'is_platform_admin',
    'must_change_password',
    'last_login_at',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at'  => 'datetime',
            'last_login_at'      => 'datetime',
            'password'             => 'hashed',
            'is_platform_admin'    => 'boolean',
            'must_change_password' => 'boolean',
        ];
    }

    public function ownedCompanies(): HasMany
    {
        return $this->hasMany(Company::class, 'owner_user_id');
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class)
            ->withPivot(['company_role', 'company_role_id', 'status', 'joined_at'])
            ->withTimestamps();
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function frozenSales(): HasMany
    {
        return $this->hasMany(FrozenSale::class, 'created_by');
    }

    public function openedCashSessions(): HasMany
    {
        return $this->hasMany(CashSession::class, 'opened_by');
    }

    public function closedCashSessions(): HasMany
    {
        return $this->hasMany(CashSession::class, 'closed_by');
    }

    public function receivedPayments(): HasMany
    {
        return $this->hasMany(Payment::class, 'received_by');
    }

    public function subscriptionBundles(): HasMany
    {
        return $this->hasMany(SubscriptionBundle::class, 'owner_user_id');
    }

    public function hasCurrentCompanyPermission(string $permissionCode): bool
    {
        return app(CurrentCompanyPermissionResolver::class)->has($this, $permissionCode);
    }

    public function hasAnyCurrentCompanyPermission(array $permissionCodes): bool
    {
        return app(CurrentCompanyPermissionResolver::class)->hasAny($this, $permissionCodes);
    }
}
