<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsumptionGoal extends Model
{
    protected $fillable = ['user_id', 'month', 'target_liters'];

    protected $casts = [
        'month' => 'date',
        'target_liters' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
