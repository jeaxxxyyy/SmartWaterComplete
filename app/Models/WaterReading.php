<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WaterReading extends Model
{
    protected $fillable = [
        'user_id',
        'device_name',
        'device_payload',
        'leak_detection_status',
        'flow_rate',
        'liters_used',
        'cubic_meter',
        'estimated_bill',
        'rate_per_cubic_meter',
        'subsidy_deduction',
        'threshold_liters',
        'water_limit_liters',
        'monitoring_status',
        'wifi_signal_strength',
        'device_connection_status',
        'server_connection_status',
        'is_threshold_exceeded',
        'is_possible_leak',
        'recorded_at',
    ];

    protected $casts = [
        'device_payload' => 'array',
        'flow_rate' => 'decimal:2',
        'liters_used' => 'decimal:2',
        'cubic_meter' => 'decimal:4',
        'estimated_bill' => 'decimal:2',
        'rate_per_cubic_meter' => 'decimal:2',
        'subsidy_deduction' => 'decimal:2',
        'threshold_liters' => 'decimal:2',
        'water_limit_liters' => 'decimal:2',
        'wifi_signal_strength' => 'integer',
        'is_threshold_exceeded' => 'boolean',
        'is_possible_leak' => 'boolean',
        'recorded_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }
}
