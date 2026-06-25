<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Billing extends Model
{
    protected $fillable = [
        'user_id',
        'water_rate',
        'subsidy_deduction',
        'threshold_liters',
        'leak_flow_rate_threshold',
        'quiet_hours_start',
        'quiet_hours_end',
        'is_active',
    ];

    protected $casts = [
        'water_rate' => 'decimal:2',
        'subsidy_deduction' => 'decimal:2',
        'threshold_liters' => 'decimal:2',
        'leak_flow_rate_threshold' => 'decimal:2',
        'quiet_hours_start' => 'integer',
        'quiet_hours_end' => 'integer',
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
