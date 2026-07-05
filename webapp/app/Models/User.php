<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Jetstream\HasTeams;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    public const ROLE_PLATFORM_ADMIN = 'platform_admin';
    public const ROLE_HQ_ADMIN = 'hq_admin';
    public const ROLE_BORDER_OFFICER = 'border_officer';
    public const ROLE_BORDER_SUPERVISOR = 'border_supervisor';
    public const ROLE_REGIONAL_SUPERVISOR = 'regional_supervisor';

    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasProfilePhoto;
    use HasTeams;
    use Notifiable;
    use TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
        'country_code',
        'border_post_id',
        'role',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'profile_photo_url',
    ];

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
            'is_admin' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function borderPost(): BelongsTo
    {
        return $this->belongsTo(BorderPost::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_code', 'code');
    }

    public function mobileDevices(): HasMany
    {
        return $this->hasMany(MobileDevice::class);
    }

    public function mobileSubmissions(): HasMany
    {
        return $this->hasMany(MobileSubmission::class);
    }

    public function dashboardViews(): HasMany
    {
        return $this->hasMany(DashboardView::class);
    }

    public function canUseMobileApp(): bool
    {
        return $this->is_active && $this->border_post_id !== null;
    }

    public function isPlatformAdmin(): bool
    {
        return (bool) config('borderreach.platform_mode')
            && $this->is_admin
            && ($this->role === self::ROLE_PLATFORM_ADMIN || $this->role === null || $this->role === '');
    }

    public function canManageAllTenants(): bool
    {
        return $this->isPlatformAdmin();
    }

    public function canManageDeploymentRequests(): bool
    {
        return $this->isPlatformAdmin();
    }

    public function tenantCountryCode(): ?string
    {
        $countryCode = $this->borderPost?->country_code ?: $this->country_code;

        return $countryCode ? strtoupper($countryCode) : null;
    }

    public function canAccessCountry(?string $countryCode): bool
    {
        if ($this->canManageAllTenants()) {
            return true;
        }

        $tenantCountryCode = $this->tenantCountryCode();

        return $tenantCountryCode !== null && strtoupper((string) $countryCode) === $tenantCountryCode;
    }

    public function operationalCountryCode(): string
    {
        return $this->tenantCountryCode() ?: (string) config('borderreach.tenant_country_code', 'SLE');
    }
}
