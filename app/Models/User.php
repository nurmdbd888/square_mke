<?php

namespace App\Models;

use App\Support\Access\PermissionRegistry;
use App\Support\Audit\Auditable;
use Database\Factories\UserFactory;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use Auditable, HasFactory, HasRoles, Notifiable, SoftDeletes;

    protected $fillable = [
        'name', 'username', 'email', 'phone', 'password', 'locale', 'must_change_password',
    ];

    protected $hidden = [
        'password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes',
    ];

    /** @var list<string> */
    protected array $auditHidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];

    /** @var list<string> */
    protected array $auditIgnore = ['remember_token', 'last_login_at', 'last_login_ip', 'two_factor_last_step'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    public function setUsernameAttribute(string $value): void
    {
        $this->attributes['username'] = mb_strtolower(trim($value));
    }

    public function setEmailAttribute(?string $value): void
    {
        $value = $value === null ? '' : mb_strtolower(trim($value));
        $this->attributes['email'] = $value === '' ? null : $value;
    }

    public function isOwner(): bool
    {
        return $this->hasRole(PermissionRegistry::OWNER);
    }

    public function roleName(): ?string
    {
        return $this->getRoleNames()->first();
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }

    public function sendPasswordResetNotification($token): void
    {
        $notification = new ResetPassword($token);
        $notification->locale($this->locale ?? app()->getLocale());

        $this->notify($notification);
    }
}
