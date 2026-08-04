<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeakAlert extends Model
{
    public const STATUS_DETECTED = 'detected';

    public const STATUS_ACKNOWLEDGED = 'acknowledged';

    public const STATUS_INVESTIGATING = 'investigating';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_FALSE_ALARM = 'false_alarm';

    public const ACTIVE_STATUSES = [
        self::STATUS_DETECTED,
        self::STATUS_ACKNOWLEDGED,
        self::STATUS_INVESTIGATING,
    ];

    public const STATUSES = [
        self::STATUS_DETECTED,
        self::STATUS_ACKNOWLEDGED,
        self::STATUS_INVESTIGATING,
        self::STATUS_RESOLVED,
        self::STATUS_FALSE_ALARM,
    ];

    protected $fillable = [
        'user_id',
        'first_water_reading_id',
        'latest_water_reading_id',
        'notification_id',
        'status',
        'notes',
        'detected_at',
        'last_detected_at',
        'notified_at',
        'acknowledged_at',
        'resolved_at',
    ];

    protected $casts = [
        'detected_at' => 'datetime',
        'last_detected_at' => 'datetime',
        'notified_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function firstReading(): BelongsTo
    {
        return $this->belongsTo(WaterReading::class, 'first_water_reading_id');
    }

    public function latestReading(): BelongsTo
    {
        return $this->belongsTo(WaterReading::class, 'latest_water_reading_id');
    }

    public function notification(): BelongsTo
    {
        return $this->belongsTo(Notification::class);
    }
}
