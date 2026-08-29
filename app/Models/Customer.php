<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use Auditable;

    protected $fillable = [
        'customer_id', 'converted_from_lead_id', 'became_customer_at', 'company_name', 'brand_name', 'legal_name',
        'npwp', 'address', 'shipping_address', 'billing_address', 'phone', 'email', 'city',
        'area_id', 'business_unit_id', 'department_id', 'sales_owner_id', 'supervisor_id',
        'manager_id', 'business_type', 'product_interest', 'product_interests', 'estimated_need', 'estimated_need_unit', 'status', 'credit_limit',
        'payment_term_days', 'estimated_monthly_purchase',
        'tags', 'last_order_at', 'last_activity_at', 'next_follow_up_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array', 'became_customer_at' => 'datetime', 'last_order_at' => 'datetime', 'last_activity_at' => 'datetime',
            'next_follow_up_at' => 'datetime', 'credit_limit' => 'decimal:2',
            'estimated_monthly_purchase' => 'decimal:2',
            'estimated_need' => 'integer', 'product_interests' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Customer $customer) {
            $customer->customer_id ??= 'CUST-'.now()->format('ym').'-'.str_pad((string) (self::max('id') + 1), 5, '0', STR_PAD_LEFT);
            $customer->became_customer_at ??= now();
        });
    }

    public function salesOwner() { return $this->belongsTo(User::class, 'sales_owner_id'); }
    public function sourceLead() { return $this->belongsTo(Lead::class, 'converted_from_lead_id'); }
    public function supervisor() { return $this->belongsTo(User::class, 'supervisor_id'); }
    public function manager() { return $this->belongsTo(User::class, 'manager_id'); }
    public function businessUnit() { return $this->belongsTo(BusinessUnit::class); }
    public function area() { return $this->belongsTo(Area::class); }
    public function contacts() { return $this->hasMany(Contact::class); }
    public function assignedUsers() { return $this->belongsToMany(User::class)->withPivot('responsibility')->withTimestamps(); }
    public function opportunities() { return $this->hasMany(Opportunity::class); }
    public function opportunityItems() { return $this->hasManyThrough(OpportunityItem::class, Opportunity::class); }
    public function activities() { return $this->hasMany(Activity::class)->latest('occurred_at'); }
    public function rooms() { return $this->hasMany(CustomerRoom::class); }
    public function tasks() { return $this->hasMany(Task::class); }
    public function approvals() { return $this->hasMany(Approval::class); }
    public function attachments() { return $this->morphMany(Attachment::class, 'attachable'); }

    public function interestItems(): array
    {
        return $this->product_interests ?: ($this->product_interest ? [[
            'product_name' => $this->product_interest,
            'estimated_need' => $this->estimated_need,
            'estimated_need_unit' => $this->estimated_need_unit ?: 'pcs',
        ]] : []);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isMasterAdmin()) {
            return $query;
        }

        if ($user->hasRole('csa')) {
            return $query;
        }

        if ($user->authority_level === 'manager') {
            return $query->whereIn('business_unit_id', $user->businessUnits()->pluck('business_units.id'));
        }

        if ($user->authority_level === 'supervisor') {
            $visibleUserIds = $user->subordinates()->pluck('id')->push($user->id)->unique();
            return $query->where(fn ($q) => $q->whereIn('sales_owner_id', $visibleUserIds)->orWhere('supervisor_id', $user->id));
        }

        if ($user->user_type === 'frontliner') {
            return $query->where(fn ($q) => $q->where('sales_owner_id', $user->id)->orWhereHas('assignedUsers', fn ($q) => $q->whereKey($user->id)));
        }

        return $query->where(fn ($q) => $q
            ->whereHas('rooms.members', fn ($q) => $q->where('user_id', $user->id))
            ->orWhereHas('tasks.assignees', fn ($q) => $q->where('user_id', $user->id))
            ->orWhereHas('approvals', fn ($q) => $q->where('current_approver_id', $user->id)));
    }

    protected function initials(): Attribute
    {
        return Attribute::get(fn () => collect(preg_split('/\s+/', $this->company_name))->take(2)->map(fn ($word) => mb_substr($word, 0, 1))->join(''));
    }
}
