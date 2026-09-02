<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Pipeline extends Model
{
    use Auditable;
    protected $fillable = ['name', 'slug', 'description', 'business_unit_id', 'business_type', 'counts_as_custom_noo', 'uses_pipeline_for_custom_progress', 'is_active', 'created_by'];
    protected function casts(): array { return ['counts_as_custom_noo' => 'boolean', 'uses_pipeline_for_custom_progress' => 'boolean', 'is_active' => 'boolean']; }
    public function stages() { return $this->hasMany(PipelineStage::class)->orderBy('position'); }
    public function opportunities() { return $this->hasMany(Opportunity::class); }
    public function businessUnit() { return $this->belongsTo(BusinessUnit::class); }
}
