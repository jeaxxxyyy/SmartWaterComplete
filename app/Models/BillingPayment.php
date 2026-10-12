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
        'status',
        'reference_number',
        'proof_path',
        'notes',
        'status_note',
        'received_at',
        'verified_by',
        'verified_at',
        'voided_by',
        'voided_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'received_at' => 'datetime',
        'verified_at' => 'datetime',
        'voided_at' => 'datetime',
    ];

    public function statement(): BelongsTo
    {
        return $this->belongsTo(BillingStatement::class, 'billing_statement_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }
}
