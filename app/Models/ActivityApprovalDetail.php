<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class ActivityApprovalDetail extends Model
{
    use Auditable;
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'normal_price' => 'decimal:2',
            'requested_price' => 'decimal:2',
            'quantity' => 'decimal:2',
            'current_limit' => 'decimal:2',
            'requested_limit' => 'decimal:2',
            'new_order_value' => 'decimal:2',
            'outstanding_receivables' => 'decimal:2',
            'remaining_limit' => 'decimal:2',
            'over_limit' => 'decimal:2',
            'planned_payment_amount' => 'decimal:2',
            'planned_payment_date' => 'date',
            'transaction_value' => 'decimal:2',
            'additional_days' => 'integer',
            'budget_amount' => 'decimal:2',
            'estimated_value' => 'decimal:2',
            'needed_at' => 'date',
            'target_date' => 'date',
            'decided_at' => 'datetime',
            'special_price_items' => 'array',
        ];
    }

    public function activity()
    {
        return $this->belongsTo(Activity::class);
    }

    public function decidedBy()
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
