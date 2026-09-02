<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use Auditable;

    protected $fillable = ['business_unit_id', 'code', 'name', 'is_frontliner', 'activity_evidence_required', 'is_active'];

    protected function casts(): array
    {
        return ['is_frontliner' => 'boolean', 'activity_evidence_required' => 'boolean', 'is_active' => 'boolean'];
    }

    public function businessUnit()
    {
        return $this->belongsTo(BusinessUnit::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class);
    }

    public function teams()
    {
        return $this->hasMany(Team::class);
    }
}
