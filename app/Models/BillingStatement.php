<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillingStatement extends Model
{
    protected $fillable = [
        'user_id',
        'statement_number',
        'billing_month',
        'due_date',
        'opening_balance',
        'total_liters',
        'total_cubic_meter',
        'water_rate',
        'fixed_monthly_charge',
        'currency',
        'subsidy_deduction',
        'current_charge',
        'amount_paid',
        'remaining_balance',
        'status',
        'generated_at',
        'paid_at',
    ];

    protected $casts = [
        'billing_month' => 'date',
        'due_date' => 'date',
        'opening_balance' => 'decimal:2',
        'total_liters' => 'decimal:2',
        'total_cubic_meter' => 'decimal:4',
        'water_rate' => 'decimal:2',
        'fixed_monthly_charge' => 'decimal:2',
        'subsidy_deduction' => 'decimal:2',
        'current_charge' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'remaining_balance' => 'decimal:2',
        'generated_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(BillingPayment::class);
    }
}
