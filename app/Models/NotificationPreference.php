<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    protected $fillable = [
        'user_id',
        'announcements',
        'billing_updates',
        'consumption_alerts',
        'leak_alerts',
        'service_request_updates',
        'quiet_hours_start',
        'quiet_hours_end',
    ];

    protected $casts = [
        'announcements' => 'boolean',
        'billing_updates' => 'boolean',
        'consumption_alerts' => 'boolean',
        'leak_alerts' => 'boolean',
        'service_request_updates' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
