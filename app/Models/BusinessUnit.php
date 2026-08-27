<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class BusinessUnit extends Model
{
    use Auditable;
    protected $fillable = ['code', 'name', 'is_active'];

    public function users()
    {
        return $this->belongsToMany(User::class);
    }

    public function departments()
    {
        return $this->hasMany(Department::class);
    }
}
