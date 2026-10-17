<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only: the app never updates or deletes audit rows.
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id', 'user_name', 'source', 'event', 'auditable_type', 'auditable_id',
        'old_values', 'new_values', 'ip_address', 'user_agent', 'url', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * @param  Builder<AuditLog>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<AuditLog>
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['user'] ?? null, fn (Builder $q, $id) => $q->where('user_id', $id))
            ->when($filters['event'] ?? null, fn (Builder $q, $event) => str_ends_with($event, '.*')
                ? $q->where('event', 'like', substr($event, 0, -1).'%')
                : $q->where('event', $event))
            ->when($filters['type'] ?? null, fn (Builder $q, $type) => $q->where('auditable_type', $type))
            ->when($filters['id'] ?? null, fn (Builder $q, $id) => $q->where('auditable_id', $id))
            ->when($filters['source'] ?? null, fn (Builder $q, $source) => $q->where('source', $source))
            ->when($filters['from'] ?? null, fn (Builder $q, $from) => $q->where('created_at', '>=', $from.' 00:00:00'))
            ->when($filters['to'] ?? null, fn (Builder $q, $to) => $q->where('created_at', '<=', $to.' 23:59:59'));
    }

    /**
     * Keys present in either side, so the viewer can show a before/after table.
     *
     * @return list<string>
     */
    public function changedKeys(): array
    {
        $keys = array_unique(array_merge(array_keys($this->old_values ?? []), array_keys($this->new_values ?? [])));
        sort($keys);

        return $keys;
    }
}
