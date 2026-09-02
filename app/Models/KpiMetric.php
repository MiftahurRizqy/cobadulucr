<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class KpiMetric extends Model
{
    use Auditable;

    public const SOURCES = [
        'new_customer' => 'Customer baru',
        'won_opportunity' => 'Opportunity Closed Won',
        'won_revenue' => 'Nilai Closed Won',
        'won_quantity' => 'Kuantitas produk Closed Won',
        'large_account' => 'Customer dengan Closed Won minimum',
    ];

    protected $fillable = ['name', 'source', 'filters', 'unit', 'threshold', 'is_active', 'counts_in_achievement', 'sort_order', 'legacy_key'];
    protected function casts(): array { return ['filters'=>'array','threshold'=>'integer','is_active'=>'boolean','counts_in_achievement'=>'boolean']; }
}
