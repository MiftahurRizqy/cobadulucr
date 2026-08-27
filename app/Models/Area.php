<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Area extends Model
{
    use Auditable;
    protected $fillable = ['code', 'name', 'branch', 'is_active'];
    protected function casts(): array { return ['is_active' => 'boolean']; }
    public function users() { return $this->belongsToMany(User::class); }
    public function customers() { return $this->hasMany(Customer::class); }
    public function leads() { return $this->hasMany(Lead::class); }
}
