<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $fillable = ['key', 'role_id', 'value'];

    public static function bool(string $key, bool $default = true, ?User $user = null): bool
    {
        $value = static::query()->where('key', $key)->whereNull('role_id')->value('value');
        $resolved = $value === null ? $default : filter_var($value, FILTER_VALIDATE_BOOL);

        if (! $user) {
            return $resolved;
        }

        $roleIds = $user->roles()->orderBy('roles.id')->pluck('roles.id');
        if ($roleIds->isEmpty()) {
            return $resolved;
        }

        $override = static::query()
            ->where('key', $key)
            ->whereIn('role_id', $roleIds)
            ->orderBy('role_id')
            ->value('value');

        return $override === null ? $resolved : filter_var($override, FILTER_VALIDATE_BOOL);
    }

    public static function setBool(string $key, bool $value, ?int $roleId = null): void
    {
        static::query()->updateOrCreate(['key' => $key, 'role_id' => $roleId], ['value' => $value ? '1' : '0']);
    }

    public static function removeRoleOverrides(int $roleId): void
    {
        static::query()->where('role_id', $roleId)->delete();
    }
}
