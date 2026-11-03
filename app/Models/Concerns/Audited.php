<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;

/** Writes every create, update and delete of the model to the audit log (BR-15). */
trait Audited
{
    protected static function bootAudited(): void
    {
        static::created(fn ($model) => AuditLog::record('create', $model, null, $model->auditable($model->getAttributes())));

        static::updated(function ($model) {
            $changed = $model->auditable($model->getChanges());
            if ($changed) {
                AuditLog::record('update', $model, array_intersect_key($model->auditable($model->getOriginal()), $changed), $changed);
            }
        });

        static::deleted(fn ($model) => AuditLog::record('delete', $model, $model->auditable($model->getOriginal())));
    }

    /** Timestamps and file paths add noise to the history. */
    protected function auditable(array $values): array
    {
        return collect($values)->except(['created_at', 'updated_at', 'photo_path', 'password', 'remember_token'])
            ->map(fn ($v) => $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : $v)
            ->all();
    }
}
