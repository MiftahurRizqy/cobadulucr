<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class Opportunity extends Model
{
    use Auditable;

    public const QUANTITY_UNITS = [
        'pcs' => 'Pcs',
        'pack' => 'Pack',
        'roll' => 'Roll',
        'ctn' => 'Ctn',
        'set' => 'Set',
        'kg' => 'Kg',
        'bal' => 'Bal',
    ];

    protected $fillable = [
        'opportunity_id', 'customer_id', 'lead_id', 'pipeline_id', 'pipeline_stage_id', 'owner_id',
        'participants', 'product_id', 'title', 'product_name', 'estimated_quantity', 'quantity_unit',
        'estimated_value', 'probability', 'target_price', 'offered_price', 'current_supplier',
        'competitor', 'expected_close_date', 'next_action', 'next_follow_up_at', 'lead_source',
        'priority', 'status', 'hold_reason', 'lost_reason', 'lost_reason_detail',
        'stage_entered_at', 'last_activity_at',
    ];

    protected function casts(): array
    {
        return [
            'expected_close_date' => 'date', 'next_follow_up_at' => 'datetime',
            'stage_entered_at' => 'datetime', 'last_activity_at' => 'datetime',
            'participants' => 'array',
            'estimated_quantity' => 'integer',
            'estimated_value' => 'decimal:2', 'target_price' => 'decimal:2', 'offered_price' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Opportunity $opportunity) {
            $opportunity->opportunity_id ??= 'OPP-'.now()->format('ym').'-'.str_pad((string) (self::max('id') + 1), 5, '0', STR_PAD_LEFT);
            $opportunity->stage_entered_at ??= now();
            $opportunity->quantity_unit ??= $opportunity->product_id
                ? (Product::whereKey($opportunity->product_id)->value('unit') ?: 'pcs')
                : 'pcs';
        });
    }

    public function customer() { return $this->belongsTo(Customer::class); }
    public function pipeline() { return $this->belongsTo(Pipeline::class); }
    public function stage() { return $this->belongsTo(PipelineStage::class, 'pipeline_stage_id'); }
    public function owner() { return $this->belongsTo(User::class, 'owner_id'); }
    public function product() { return $this->belongsTo(Product::class); }
    public function items() { return $this->hasMany(OpportunityItem::class); }
    public function activities() { return $this->hasMany(Activity::class); }
    public function tasks() { return $this->hasMany(Task::class); }
    public function approvals() { return $this->hasMany(Approval::class); }
    public function stageHistories() { return $this->hasMany(OpportunityStageHistory::class); }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isMasterAdmin()) return $query;
        if (in_array($user->authority_level, ['manager', 'supervisor'], true)) {
            return $query->where(fn ($query) => $query
                ->whereHas('customer', fn ($q) => $q->visibleTo($user))
                ->orWhereJsonContains('participants', $user->id));
        }
        if ($user->user_type === 'frontliner') {
            return $query->where(fn ($query) => $query
                ->where('owner_id', $user->id)
                ->orWhereJsonContains('participants', $user->id));
        }
        return $query->where(fn ($query) => $query
            ->whereHas('customer', fn ($q) => $q->visibleTo($user))
            ->orWhereJsonContains('participants', $user->id));
    }

    protected function weightedValue(): Attribute
    {
        return Attribute::get(fn () => (float) $this->estimated_value * ((int) $this->probability / 100));
    }

    protected function daysInStage(): Attribute
    {
        return Attribute::get(fn () => (int) ($this->stage_entered_at?->diffInDays(now()) ?? 0));
    }
}
