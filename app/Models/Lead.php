<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    use Auditable;

    public const STATUSES = [
        'leads_adds' => 'Leads Adds',
        'cold_lead' => 'Leads Cold',
        'warm_lead' => 'Leads Warm',
        'leads_hold' => 'Leads On Hold',
        'leads_risky' => 'Leads Risky',
        'converted' => 'Menjadi customer',
    ];

    public const EDITABLE_STATUSES = [
        'leads_adds' => 'Leads Adds',
        'cold_lead' => 'Leads Cold',
        'warm_lead' => 'Leads Warm',
        'leads_hold' => 'Leads On Hold',
        'leads_risky' => 'Leads Risky',
    ];

    protected $fillable = [
        'lead_id', 'company_name', 'brand_name', 'contact_name', 'phone', 'whatsapp', 'email',
        'city', 'province', 'address', 'area_id', 'business_unit_id', 'owner_id', 'source',
        'business_type', 'product_interest', 'product_interests', 'estimated_need', 'estimated_need_unit', 'notes', 'status',
        'next_follow_up_at', 'last_activity_at', 'created_by', 'status_before_conversion',
    ];

    protected function casts(): array { return ['product_interests' => 'array', 'next_follow_up_at' => 'datetime', 'last_activity_at' => 'datetime']; }
    protected static function booted(): void { static::creating(fn (Lead $lead) => $lead->lead_id ??= 'LEAD-'.now()->format('ym').'-'.str_pad((string) (self::max('id') + 1), 5, '0', STR_PAD_LEFT)); }
    public function owner() { return $this->belongsTo(User::class, 'owner_id'); }
    public function collaborators() { return $this->belongsToMany(User::class)->withTimestamps(); }
    public function area() { return $this->belongsTo(Area::class); }
    public function businessUnit() { return $this->belongsTo(BusinessUnit::class); }
    public function convertedCustomer() { return $this->hasOne(Customer::class, 'converted_from_lead_id'); }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? str((string) $this->status)->replace('_', ' ')->title()->toString();
    }

    public function reportStatusLabel(): string
    {
        $status = $this->status === 'converted' ? $this->status_before_conversion : $this->status;
        return self::EDITABLE_STATUSES[$status] ?? 'Tidak tercatat';
    }

    public function scopeWithReportStatus(Builder $query, string $status): Builder
    {
        return $query->where(fn (Builder $q) => $q->where('leads.status', $status)
            ->orWhere(fn (Builder $q) => $q->where('leads.status', 'converted')->where('leads.status_before_conversion', $status)));
    }

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
        if ($user->isMasterAdmin()) return $query;
        if ($user->hasRole('csa')) return $query;
        if ($user->authority_level === 'manager') return $query->whereIn('leads.business_unit_id', $user->businessUnits()->pluck('business_units.id'));
        if ($user->authority_level === 'supervisor') return $query->whereHas('owner', fn ($q) => $q->where('manager_id', $user->id));
        return $query->where(fn ($query) => $query
            ->where('leads.owner_id', $user->id)
            ->orWhereHas('collaborators', fn ($collaborators) => $collaborators->whereKey($user->id)));
    }
}
