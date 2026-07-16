<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    protected $fillable = [
        'user_id',
        'water_reading_id',
        'type',
        'batch_id',
        'title',
        'message',
        'metadata',
        'is_read',
        'user_deleted_at',
        'sent_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_read' => 'boolean',
        'user_deleted_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function waterReading(): BelongsTo
    {
        return $this->belongsTo(WaterReading::class);
    }
}
