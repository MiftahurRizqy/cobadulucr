<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class OpportunityStageHistory extends Model
{
    use Auditable;

    protected $fillable = ['opportunity_id', 'from_stage_id', 'to_stage_id', 'changed_by', 'reason', 'validation_snapshot'];

    protected function casts(): array
    {
        return ['validation_snapshot' => 'array'];
    }

    public function opportunity()
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function fromStage()
    {
        return $this->belongsTo(PipelineStage::class, 'from_stage_id');
    }

    public function toStage()
    {
        return $this->belongsTo(PipelineStage::class, 'to_stage_id');
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
