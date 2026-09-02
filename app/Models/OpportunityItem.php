<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class OpportunityItem extends Model
{
    use Auditable;

    public const PRODUCT_TYPES = [
        'regular' => 'Reguler',
        'custom' => 'Custom',
    ];

    protected $fillable = [
        'product_id', 'product_name', 'market_segment', 'product_type', 'custom_stage', 'photo_path', 'quantity', 'quantity_unit',
        'target_price', 'unit_price', 'subtotal', 'deal_status',
        'deal_status_updated_by', 'deal_status_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'target_price' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'deal_status_updated_at' => 'datetime',
        ];
    }

    public function opportunity() { return $this->belongsTo(Opportunity::class); }
    public function product() { return $this->belongsTo(Product::class); }
    public function dealStatusUpdatedBy() { return $this->belongsTo(User::class, 'deal_status_updated_by'); }
}
