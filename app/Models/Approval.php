<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Approval extends Model
{
    use Auditable;
    protected $fillable = ['approval_id', 'type', 'title', 'customer_id', 'opportunity_id', 'requester_id', 'current_approver_id', 'previous_value', 'requested_value', 'reason', 'status', 'decision_note', 'decided_at'];
    protected function casts(): array { return ['decided_at' => 'datetime', 'previous_value' => 'decimal:2', 'requested_value' => 'decimal:2']; }
    protected static function booted(): void { static::creating(fn (Approval $approval) => $approval->approval_id ??= 'APR-'.now()->format('ym').'-'.str_pad((string) (self::max('id') + 1), 5, '0', STR_PAD_LEFT)); }
    public function customer() { return $this->belongsTo(Customer::class); }
    public function opportunity() { return $this->belongsTo(Opportunity::class); }
    public function requester() { return $this->belongsTo(User::class, 'requester_id'); }
    public function currentApprover() { return $this->belongsTo(User::class, 'current_approver_id'); }
    public function steps() { return $this->hasMany(ApprovalStep::class); }
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isMasterAdmin()) return $query;
        return $query->where(fn ($q) => $q->where('requester_id', $user->id)->orWhere('current_approver_id', $user->id)->orWhereHas('customer', fn ($q) => $q->visibleTo($user)));
    }
}
