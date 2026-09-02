<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class AuditLog extends Model
{
    protected $fillable = ['user_id', 'action', 'module', 'auditable_type', 'auditable_id', 'old_values', 'new_values', 'reason', 'ip_address', 'user_agent'];

    protected function casts(): array
    {
        return ['old_values' => 'array', 'new_values' => 'array'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function auditable()
    {
        return $this->morphTo();
    }

    public static function record(
        string $action,
        string $module,
        ?Model $auditable = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $reason = null,
        ?int $userId = null,
    ): ?self {
        if (! Schema::hasTable('audit_logs')) {
            return null;
        }
        $actorId = $userId ?? auth()->id();
        if ($actorId && (! Schema::hasTable('users') || ! User::query()->whereKey($actorId)->exists())) {
            $actorId = null;
        }

        return static::create([
            'user_id' => $actorId,
            'action' => $action,
            'module' => $module,
            'auditable_type' => $auditable ? get_class($auditable) : null,
            'auditable_id' => $auditable?->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'reason' => $reason,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }

    public static function recordRelation(Model $model, string $relation, iterable $oldIds, iterable $newIds): ?self
    {
        $normalize = fn (iterable $ids) => collect($ids)
            ->map(fn ($id) => (int) $id)->filter()->unique()->sort()->values()->all();
        $old = $normalize($oldIds);
        $new = $normalize($newIds);

        if ($old === $new) {
            return null;
        }

        return static::record(
            'relations_updated',
            $model->getTable(),
            $model,
            [$relation => $old],
            [$relation => $new],
        );
    }
}
