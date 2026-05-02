<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

#[Fillable([
    'name',
    'email',
    'password',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements AuditableContract
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasApiTokens, HasFactory, HasUlids, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    public function preferences(): HasOne
    {
        return $this->hasOne(UserPreference::class);
    }

    public function roleRecord(): HasOne
    {
        return $this->hasOne(UserRole::class);
    }

    public function verification(): HasOne
    {
        return $this->hasOne(UserVerification::class);
    }

    public function accessLogs(): HasMany
    {
        return $this->hasMany(UserAccessLog::class, 'target_user_id');
    }

    public function mfaMethods(): HasMany
    {
        return $this->hasMany(UserMfa::class);
    }

    public function actedAccessLogs(): HasMany
    {
        return $this->hasMany(UserAccessLog::class, 'actor_user_id');
    }

    public function latestAccessLog(): HasOne
    {
        return $this->hasOne(UserAccessLog::class, 'target_user_id')->latestOfMany('occurred_at');
    }

    public function latestLoginLog(): HasOne
    {
        return $this->hasOne(UserAccessLog::class, 'target_user_id')
            ->where('event', 'auth.login')
            ->latestOfMany('occurred_at');
    }

    protected function role(): Attribute
    {
        return Attribute::get(fn (): string => $this->roleRecord?->role ?? 'membro');
    }
}
