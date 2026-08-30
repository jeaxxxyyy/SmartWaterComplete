<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Billing extends Model
{
    protected $fillable = [
        'user_id',
        'water_rate',
        'fixed_monthly_charge',
        'subsidy_deduction',
        'currency',
        'effective_date',
        'threshold_liters',
        'consumption_alert_enabled',
        'consumption_change_threshold_percent',
        'consumption_alert_unit',
        'consumption_alert_behavior',
        'leak_flow_rate_threshold',
        'leak_alert_enabled',
        'leak_continuous_duration_minutes',
        'leak_alert_behavior',
        'quiet_hours_start',
        'quiet_hours_end',
        'is_active',
    ];

    protected $casts = [
        'water_rate' => 'decimal:2',
        'fixed_monthly_charge' => 'decimal:2',
        'subsidy_deduction' => 'decimal:2',
        'effective_date' => 'date',
        'threshold_liters' => 'decimal:2',
        'consumption_alert_enabled' => 'boolean',
        'consumption_change_threshold_percent' => 'decimal:2',
        'leak_flow_rate_threshold' => 'decimal:2',
        'leak_alert_enabled' => 'boolean',
        'leak_continuous_duration_minutes' => 'integer',
        'quiet_hours_start' => 'integer',
        'quiet_hours_end' => 'integer',
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
