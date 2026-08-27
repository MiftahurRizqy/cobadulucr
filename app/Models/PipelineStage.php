<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class PipelineStage extends Model
{
    use Auditable;
    protected $fillable = ['pipeline_id', 'name', 'slug', 'position', 'color', 'probability', 'sla_days', 'is_won', 'is_lost', 'is_active'];

    protected function casts(): array
    {
        return ['is_won' => 'boolean', 'is_lost' => 'boolean', 'is_active' => 'boolean'];
    }

    public function pipeline()
    {
        return $this->belongsTo(Pipeline::class);
    }

    public function rules()
    {
        return $this->hasMany(StageRule::class);
    }

    public function opportunities()
    {
        return $this->hasMany(Opportunity::class);
    }
}
