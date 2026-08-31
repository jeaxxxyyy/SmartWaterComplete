<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingPayment extends Model
{
    protected $fillable = [
        'billing_statement_id',
        'recorded_by',
        'receipt_number',
        'amount',
        'payment_method',
        'reference_number',
        'notes',
        'received_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'received_at' => 'datetime',
    ];

    public function statement(): BelongsTo
    {
        return $this->belongsTo(BillingStatement::class, 'billing_statement_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
