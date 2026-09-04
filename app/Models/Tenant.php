<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    protected $fillable = ['name', 'slug', 'database_name', 'database_username', 'database_password', 'logo_path', 'primary_color', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'database_password' => 'encrypted',
        ];
    }

    public function getConnectionName()
    {
        return config('database.default') === 'sqlite' ? 'sqlite' : 'central';
    }
}
