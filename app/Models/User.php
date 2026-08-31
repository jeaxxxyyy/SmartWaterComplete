<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'name',
    'email',
    'password',
    'role',
    'assigned_user_id',
    'account_number',
    'address',
    'phone',
    'preferred_locale',
    'low_bandwidth_mode',
    'is_active',
    'created_by_admin',
    'created_by',
    'approved_at',
    'account_status',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    public const ADMIN_EMAIL = 'admin@gmail.com';

    public const ADMIN_PASSWORD = 'admin123';

    public const ACCOUNT_STATUS_ACTIVE = 'active';

    public const ACCOUNT_STATUS_SUSPENDED = 'suspended';

    public const ACCOUNT_STATUS_ARCHIVED = 'archived';

    public static function generateAccountNumber(): string
    {
        $highestNumber = self::query()
            ->where('role', 'user')
            ->where('account_number', 'like', 'ACC-USER-%')
            ->pluck('account_number')
            ->map(function (?string $accountNumber): int {
                preg_match('/^ACC-USER-(\d+)$/', $accountNumber ?? '', $matches);

                return isset($matches[1]) ? (int) $matches[1] : 0;
            })
            ->max() ?? 0;

        $nextNumber = $highestNumber + 1;

        do {
            $candidate = sprintf('ACC-USER-%04d', $nextNumber);
            $nextNumber++;
        } while (self::query()->where('account_number', $candidate)->exists());

        return $candidate;
    }

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
            'is_active' => 'boolean',
            'created_by_admin' => 'boolean',
            'approved_at' => 'datetime',
            'low_bandwidth_mode' => 'boolean',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isDevice(): bool
    {
        return $this->role === 'device';
    }

    public function isHousehold(): bool
    {
        return $this->role === 'user';
    }

    public function isAccountActive(): bool
    {
        return $this->is_active === true
            && ($this->account_status ?? self::ACCOUNT_STATUS_ACTIVE) === self::ACCOUNT_STATUS_ACTIVE;
    }

    public function canHouseholdLogin(): bool
    {
        return $this->isHousehold()
            && $this->isAccountActive()
            && $this->created_by_admin === true
            && $this->created_by !== null
            && $this->approved_at !== null;
    }

    public function assignedHousehold(): BelongsTo
    {
        return $this->belongsTo(self::class, 'assigned_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(self::class, 'created_by');
    }

    public function assignedDevices(): HasMany
    {
        return $this->hasMany(self::class, 'assigned_user_id');
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class, 'household_id');
    }

    public function waterReadings(): HasMany
    {
        return $this->hasMany(WaterReading::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function leakAlerts(): HasMany
    {
        return $this->hasMany(LeakAlert::class);
    }

    public function billings(): HasMany
    {
        return $this->hasMany(Billing::class);
    }

    public function billingStatements(): HasMany
    {
        return $this->hasMany(BillingStatement::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function billingPayments(): HasMany
    {
        return $this->hasMany(BillingPayment::class, 'recorded_by');
    }

    public function serviceRequests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class);
    }

    public function notificationPreference(): HasOne
    {
        return $this->hasOne(NotificationPreference::class);
    }

    public function consumptionGoals(): HasMany
    {
        return $this->hasMany(ConsumptionGoal::class);
    }
}
