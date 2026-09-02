<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    use Auditable;
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

    public static function json(string $key, array $default = []): array
    {
        $value = static::query()->where('key', $key)->whereNull('role_id')->value('value');
        $decoded = is_string($value) ? json_decode($value, true) : null;

        return is_array($decoded) ? $decoded : $default;
    }

    public static function setJson(string $key, array $value): void
    {
        static::query()->updateOrCreate(
            ['key' => $key, 'role_id' => null],
            ['value' => json_encode($value, JSON_UNESCAPED_UNICODE)]
        );
    }

    public static function removeRoleOverrides(int $roleId): void
    {
        static::query()->where('role_id', $roleId)->delete();
    }
}
