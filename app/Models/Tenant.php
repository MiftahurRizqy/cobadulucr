<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    protected $fillable = ['name', 'slug', 'database_name', 'logo_path', 'primary_color', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function getConnectionName()
    {
        return config('database.default') === 'sqlite' ? 'sqlite' : 'central';
    }
}
