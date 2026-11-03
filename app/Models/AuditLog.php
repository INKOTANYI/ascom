<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Who changed what and when (FR-29, BR-15). Rows are only ever added, never edited. */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'action', 'table_name', 'record_id', 'old_values', 'new_values', 'ip_address'];

    protected $casts = ['old_values' => 'array', 'new_values' => 'array', 'created_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function record(string $action, Model|string $subject, ?array $old = null, ?array $new = null, ?int $recordId = null): self
    {
        return static::query()->create([
            'user_id' => auth()->id(),
            'action' => $action,
            'table_name' => is_string($subject) ? $subject : $subject->getTable(),
            'record_id' => $recordId ?? (is_string($subject) ? null : $subject->getKey()),
            'old_values' => $old ?: null,
            'new_values' => $new ?: null,
            'ip_address' => app()->runningInConsole() ? null : request()->ip(),
        ]);
    }
}
