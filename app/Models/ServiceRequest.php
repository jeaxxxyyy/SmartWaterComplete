<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceRequest extends Model
{
    protected $fillable = [
        'reference_number',
        'user_id',
        'assigned_to',
        'billing_statement_id',
        'category',
        'subject',
        'description',
        'priority',
        'status',
        'resolved_at',
    ];

    protected $casts = ['resolved_at' => 'datetime'];

    public function household(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function billingStatement(): BelongsTo
    {
        return $this->belongsTo(BillingStatement::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ServiceRequestMessage::class);
    }
}
