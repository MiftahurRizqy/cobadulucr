<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class KpiTemplate extends Model
{
    use Auditable;

    protected $fillable = [
        'role_slug', 'sales_target', 'noo_target', 'custom_noo_target', 'large_account_target',
        'drink_volume_target', 'food_volume_target', 'metric_targets', 'updated_by',
    ];

    protected function casts(): array
    {
        return ['sales_target' => 'decimal:2', 'metric_targets' => 'array'];
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function targetValues(): array
    {
        return $this->only([
            'sales_target', 'noo_target', 'custom_noo_target', 'large_account_target',
            'drink_volume_target', 'food_volume_target',
        ]);
    }
}
