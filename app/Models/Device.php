<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Device extends Model
{
    protected $fillable = [
        'uuid', 'asset_number', 'serial_number', 'name', 'household_id', 'auth_user_id',
        'hardware_id', 'firmware_version', 'status', 'maintenance_status', 'installation_address',
        'installed_at', 'last_seen_at', 'last_telemetry_at', 'calibrated_at', 'calibration_due_at',
        'token_rotated_at', 'revoked_at', 'activation_code_hash', 'activation_expires_at',
        'activated_at', 'notes',
    ];

    protected $hidden = ['activation_code_hash'];

    protected $casts = [
        'installed_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'last_telemetry_at' => 'datetime',
        'calibrated_at' => 'datetime',
        'calibration_due_at' => 'datetime',
        'token_rotated_at' => 'datetime',
        'revoked_at' => 'datetime',
        'activation_expires_at' => 'datetime',
        'activated_at' => 'datetime',
    ];

    protected $appends = ['is_online'];

    public function household(): BelongsTo
    {
        return $this->belongsTo(User::class, 'household_id');
    }

    public function authUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auth_user_id');
    }

    public function readings(): HasMany
    {
        return $this->hasMany(WaterReading::class);
    }

    public function getIsOnlineAttribute(): bool
    {
        return $this->status === 'active'
            && $this->last_seen_at?->gte(now()->subSeconds(config('devices.offline_seconds'))) === true;
    }
}
