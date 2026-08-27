<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Schema;

trait Auditable
{
    private static array $auditHidden = [
        'password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes',
        'created_at', 'updated_at', 'deleted_at',
    ];

    protected static function bootAuditable(): void
    {
        foreach (['created', 'updated', 'deleted'] as $event) {
            static::$event(function ($model) use ($event) {
                if (! Schema::hasTable('audit_logs')) {
                    return;
                }

                $attributes = collect($model->getAttributes())->except(self::$auditHidden);
                $changes = collect($model->getChanges())->except(self::$auditHidden);
                $changedKeys = $changes->keys();

                if ($event === 'updated' && $changes->isEmpty()) {
                    return;
                }

                $oldValues = $event === 'updated'
                    ? collect($model->getRawOriginal())->only($changedKeys)->except(self::$auditHidden)->all()
                    : null;
                $newValues = match ($event) {
                    'created' => $attributes->all(),
                    'updated' => $changes->all(),
                    default => null,
                };

                AuditLog::create([
                    'user_id' => auth()->id(),
                    'action' => $event,
                    'module' => $model->getTable(),
                    'auditable_type' => $model::class,
                    'auditable_id' => $model->getKey(),
                    'old_values' => $oldValues,
                    'new_values' => $newValues,
                    'ip_address' => request()?->ip(),
                    'user_agent' => request()?->userAgent(),
                ]);
            });
        }
    }
}
