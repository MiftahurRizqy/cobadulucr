<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantUserAccess extends Model
{
    protected $connection = 'central';

    protected $fillable = ['central_user_id', 'tenant_id', 'tenant_user_id'];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
