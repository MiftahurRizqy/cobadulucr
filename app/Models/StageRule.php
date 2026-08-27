<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StageRule extends Model
{
    protected $fillable = ['pipeline_stage_id', 'rule_type', 'field_key', 'label', 'configuration', 'is_mandatory'];

    protected function casts(): array
    {
        return ['configuration' => 'array', 'is_mandatory' => 'boolean'];
    }

    public function stage()
    {
        return $this->belongsTo(PipelineStage::class, 'pipeline_stage_id');
    }
}
