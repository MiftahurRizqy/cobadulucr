<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Activity extends Model
{
    use Auditable;

    public const TYPES = [
        'intro_contact' => 'Intro Chat, Call, Email',
        'call' => 'Telepon', 'whatsapp' => 'WhatsApp', 'email' => 'Email', 'meeting' => 'Meeting',
        'visit' => 'Visit', 'sample_sent' => 'Kirim Sampel', 'quotation_sent' => 'Quotation',
        'negotiation' => 'Negosiasi', 'follow_up' => 'Follow-up', 'complaint' => 'Keluhan',
        'order' => 'Order', 'collection' => 'Collection', 'payment' => 'Pembayaran', 'internal_discussion' => 'Diskusi Internal',
        'note' => 'Catatan', 'stage_changed' => 'Perubahan Tahap', 'file_uploaded' => 'Upload File',
        'approval_special_price' => 'Harga Khusus',
        'approval_discount' => 'Diskon',
        'approval_credit_limit' => 'Batas Kredit',
        'approval_payment_term' => 'Tempo Pembayaran',
        'approval_complaint_settlement' => 'Penyelesaian Keluhan',
        'approval_return' => 'Return',
        'approval_free_goods' => 'Barang Gratis',
        'approval_marketing_support' => 'Dukungan Marketing',
        'approval_budget' => 'Budget',
        'approval_custom_project' => 'Custom Project',
    ];

    public const ACTIVITY_TYPES = [
        'intro_contact', 'visit', 'quotation_sent', 'meeting', 'sample_sent', 'order', 'collection',
    ];

    public const DECISION_TYPES = [
        'approval_special_price', 'approval_credit_limit', 'approval_payment_term',
        'approval_return',
        'approval_marketing_support', 'approval_budget', 'approval_custom_project',
    ];

    public const APPROVAL_FIELDS = [
        'approval_special_price' => [
            'reason' => ['label' => 'Alasan pengajuan', 'type' => 'textarea'],
        ],
        'approval_credit_limit' => [
            'po_number' => ['label' => 'Nomor PO', 'type' => 'text'],
            'new_order_value' => ['label' => 'Nilai pesanan baru', 'type' => 'currency'],
            'current_limit' => ['label' => 'Batas kredit saat ini', 'type' => 'currency'],
            'requested_limit' => ['label' => 'Batas kredit baru yang diajukan', 'type' => 'currency'],
            'outstanding_receivables' => ['label' => 'Piutang berjalan', 'type' => 'currency'],
            'remaining_limit' => ['label' => 'Sisa limit aktual', 'type' => 'computed_currency'],
            'over_limit' => ['label' => 'Total melebihi limit', 'type' => 'computed_currency'],
            'planned_payment_date' => ['label' => 'Tanggal rencana pembayaran', 'type' => 'date'],
            'planned_payment_amount' => ['label' => 'Nominal rencana pembayaran', 'type' => 'currency'],
            'reason' => ['label' => 'Alasan pengajuan', 'type' => 'textarea'],
        ],
        'approval_payment_term' => [
            'transaction_value' => ['label' => 'Nilai pesanan', 'type' => 'currency'],
            'current_days' => ['label' => 'Tempo saat ini (hari)', 'type' => 'number'],
            'requested_days' => ['label' => 'Tempo yang diajukan (hari)', 'type' => 'number'],
            'additional_days' => ['label' => 'Tambahan tempo', 'type' => 'computed_number'],
            'reason' => ['label' => 'Alasan pengajuan', 'type' => 'textarea'],
        ],
        'approval_return' => [
            'order_number' => ['label' => 'Nomor order / invoice', 'type' => 'text'],
            'product_name' => ['label' => 'Nama produk', 'type' => 'text'],
            'quantity' => ['label' => 'Jumlah', 'type' => 'number'],
            'unit' => ['label' => 'Satuan', 'type' => 'unit'],
            'condition' => ['label' => 'Kondisi barang', 'type' => 'text'],
            'reason' => ['label' => 'Alasan return', 'type' => 'textarea'],
        ],
        'approval_marketing_support' => [
            'support_type' => ['label' => 'Bentuk dukungan', 'type' => 'text'],
            'budget_amount' => ['label' => 'Perkiraan biaya', 'type' => 'currency'],
            'period' => ['label' => 'Periode pelaksanaan', 'type' => 'text'],
            'objective' => ['label' => 'Tujuan kegiatan', 'type' => 'textarea'],
        ],
        'approval_budget' => [
            'need_name' => ['label' => 'Nama kebutuhan', 'type' => 'text'],
            'budget_amount' => ['label' => 'Jumlah anggaran', 'type' => 'currency'],
            'needed_at' => ['label' => 'Tanggal dibutuhkan', 'type' => 'date'],
            'reason' => ['label' => 'Alasan kebutuhan', 'type' => 'textarea'],
        ],
        'approval_custom_project' => [
            'project_name' => ['label' => 'Nama proyek', 'type' => 'text'],
            'estimated_value' => ['label' => 'Perkiraan nilai proyek', 'type' => 'currency'],
            'target_date' => ['label' => 'Target selesai', 'type' => 'date'],
            'customer_need' => ['label' => 'Kebutuhan customer', 'type' => 'textarea'],
        ],
    ];

    protected $fillable = ['customer_id', 'lead_id', 'opportunity_id', 'user_id', 'type', 'summary', 'detail', 'result', 'next_action', 'occurred_at', 'next_follow_up_at', 'participants', 'follow_up_completed_at', 'follow_up_completed_by', 'follow_up_completion_activity_id'];
    protected function casts(): array { return ['occurred_at' => 'datetime', 'next_follow_up_at' => 'datetime', 'follow_up_completed_at' => 'datetime', 'participants' => 'array']; }
    public function customer() { return $this->belongsTo(Customer::class); }
    public function lead() { return $this->belongsTo(Lead::class); }
    public function opportunity() { return $this->belongsTo(Opportunity::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function followUpCompletedBy() { return $this->belongsTo(User::class, 'follow_up_completed_by'); }
    public function followUpCompletionActivity() { return $this->belongsTo(self::class, 'follow_up_completion_activity_id'); }
    public function attachments() { return $this->morphMany(Attachment::class, 'attachable'); }
    public function comments() { return $this->morphMany(Comment::class, 'commentable')->oldest(); }
    public function approvalDetail() { return $this->hasOne(ActivityApprovalDetail::class); }

    /** Nama data CRM yang menjadi konteks aktivitas, baik Customer maupun Lead. */
    public function getSubjectNameAttribute(): string
    {
        return $this->customer?->company_name
            ?? $this->lead?->company_name
            ?? 'Data tidak tersedia';
    }

    public function getSubjectTypeAttribute(): string
    {
        return $this->lead_id ? 'Lead' : 'Customer';
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isMasterAdmin()) return $query;

        if ($user->authority_level === 'staff' && $user->isSales()) {
            return $query->where(fn ($q) => $q
                ->where('user_id', $user->id)
                ->orWhereHas('lead', fn ($leadQuery) => $leadQuery->visibleTo($user))
                ->orWhereJsonContains('participants', $user->id));
        }

        return $query->where(fn ($q) => $q
            ->whereHas('customer', fn ($customerQuery) => $customerQuery->visibleTo($user))
            ->orWhereHas('lead', fn ($leadQuery) => $leadQuery->visibleTo($user))
            ->orWhereJsonContains('participants', $user->id));
    }
}
