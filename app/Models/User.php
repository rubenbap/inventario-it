<?php

namespace App\Models;

use App\Notifications\ResetPassword;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable, SoftDeletes;

    public const ROLE_ADMIN = 'admin';
    public const ROLE_LEITURA = 'leitura';
    public const ROLES = [self::ROLE_ADMIN, self::ROLE_LEITURA];
    public const ROLE_LABELS = [self::ROLE_ADMIN => 'Pode mexer', self::ROLE_LEITURA => 'Só consulta'];

    protected $fillable = [
        'first_name', 'last_name', 'email', 'username', 'password', 'employee_num', 'jobtitle', 'phone',
        'company_id', 'department_id', 'location_id', 'manager_id', 'can_login', 'role', 'activated', 'notes',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'password' => 'hashed',
        'can_login' => 'boolean',
        'activated' => 'boolean',
    ];

    private ?array $cachedCompanyIds = null;

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPassword($token));
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(self::class, 'manager_id');
    }

    public function assets(): MorphMany
    {
        return $this->morphMany(Asset::class, 'assigned');
    }

    public function accessoryCheckouts(): HasMany
    {
        return $this->hasMany(AccessoryCheckout::class, 'assigned_to');
    }

    public function isAdmin(): bool
    {
        return $this->can_login && $this->role === self::ROLE_ADMIN;
    }

    public function canWrite(): bool
    {
        return $this->isAdmin() && $this->tokenAllowsWrite();
    }

    public function tokenAllowsWrite(): bool
    {
        $token = $this->currentAccessToken();

        return ! $token || $token->can('write');
    }

    public function canViewEncrypted(): bool
    {
        return $this->canWrite();
    }

    public function companyIds(): array
    {
        if ($this->cachedCompanyIds === null) {
            $this->cachedCompanyIds = $this->can_login ? Company::query()->pluck('id')->map(fn ($id) => (int) $id)->all() : [];
        }

        return $this->cachedCompanyIds;
    }

    public function forgetCompanyIds(): void
    {
        $this->cachedCompanyIds = null;
    }

    public function hasCompany(?int $companyId): bool
    {
        return $companyId !== null && in_array((int) $companyId, $this->companyIds(), true);
    }

    public function getNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }
}
