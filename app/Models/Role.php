<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use Auditable;
    protected $fillable = ['name', 'slug', 'description', 'is_system', 'parent_role_id'];

    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }

    public function users()
    {
        return $this->belongsToMany(User::class);
    }

    public function permissions()
    {
        return $this->belongsToMany(Permission::class);
    }

    public function deniedPermissions()
    {
        return $this->belongsToMany(Permission::class, 'permission_role_denials');
    }

    public function parentRole()
    {
        return $this->belongsTo(self::class, 'parent_role_id');
    }

    public function childRoles()
    {
        return $this->hasMany(self::class, 'parent_role_id');
    }

    public function effectivePermissions()
    {
        $permissions = $this->parentRole
            ? $this->parentRole->effectivePermissions()->merge($this->permissions)
            : $this->permissions;
        $deniedIds = $this->deniedPermissions->pluck('id');

        return $permissions->unique('id')->reject(fn (Permission $permission) => $deniedIds->contains($permission->id))->values();
    }

    public function grants(string $permission): bool
    {
        return $this->effectivePermissions()->contains('key', $permission);
    }
}
