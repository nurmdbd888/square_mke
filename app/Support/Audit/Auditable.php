<?php

namespace App\Support\Audit;

use Illuminate\Database\Eloquent\Model;

/**
 * Records created / updated / deleted / restored with old and new values.
 *
 * A model can list $auditHidden (value replaced by "[hidden]") and
 * $auditIgnore (changes to these alone do not create a row).
 *
 * @mixin Model
 */
trait Auditable
{
    public const HIDDEN_VALUE = '[hidden]';

    public static function bootAuditable(): void
    {
        static::created(function (Model $model) {
            AuditLogger::log('created', $model, null, $model->auditValues($model->getAttributes()));
        });

        static::updated(function (Model $model) {
            $changes = array_diff_key($model->getChanges(), array_flip($model->auditIgnoredAttributes()));

            if ($changes === []) {
                return;
            }

            $old = array_intersect_key($model->getRawOriginal(), $changes);

            AuditLogger::log('updated', $model, $model->auditValues($old), $model->auditValues($changes));
        });

        static::deleted(function (Model $model) {
            AuditLogger::log('deleted', $model, $model->auditValues($model->getAttributes()), null);
        });

        if (method_exists(static::class, 'restored')) {
            static::restored(function (Model $model) {
                AuditLogger::log('restored', $model, null, ['deleted_at' => null]);
            });
        }
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function auditValues(array $values): array
    {
        $hidden = property_exists($this, 'auditHidden') ? $this->auditHidden : [];
        $values = array_diff_key($values, array_flip(['created_at', 'updated_at']));

        foreach ($values as $key => $value) {
            if (in_array($key, $hidden, true)) {
                $values[$key] = $value === null ? null : self::HIDDEN_VALUE;
            }
        }

        ksort($values);

        return $values;
    }

    /**
     * @return list<string>
     */
    public function auditIgnoredAttributes(): array
    {
        // deleted_at changes are reported as "deleted" / "restored" instead.
        return array_merge(['updated_at', 'deleted_at'], property_exists($this, 'auditIgnore') ? $this->auditIgnore : []);
    }
}
