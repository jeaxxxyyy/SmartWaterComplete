<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

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
        'delivery_status',
        'delivery_error',
        'user_deleted_at',
        'sent_at',
        'scheduled_at',
        'archived_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_read' => 'boolean',
        'scheduled_at' => 'datetime',
        'archived_at' => 'datetime',
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

    public function scopeDelivered(Builder $query): Builder
    {
        return $query
            ->where('delivery_status', 'delivered')
            ->where(function (Builder $builder): void {
                $builder->whereNull('scheduled_at')->orWhere('scheduled_at', '<=', now());
            });
    }
}
