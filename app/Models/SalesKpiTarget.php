<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesKpiTarget extends Model
{
    protected $fillable = ['user_id', 'period_start', 'period_end', 'sales_target', 'noo_target', 'custom_noo_target', 'drink_volume_target', 'food_volume_target', 'evaluation_notes', 'updated_by'];

    protected function casts(): array
    {
        return ['period_start' => 'date', 'period_end' => 'date', 'sales_target' => 'decimal:2'];
    }

    public function user() { return $this->belongsTo(User::class); }
    public function updater() { return $this->belongsTo(User::class, 'updated_by'); }
}
