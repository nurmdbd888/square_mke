<?php

namespace App\Support\Audit;

/**
 * Event names written to audit_logs. Labels live in lang/{en,bn}/audit.php.
 */
final class AuditEvents
{
    public const ALL = [
        'created', 'updated', 'deleted', 'restored',
        'auth.login', 'auth.logout', 'auth.failed', 'auth.lockout', 'auth.password_changed', 'auth.password_reset',
        'two_factor.enabled', 'two_factor.disabled', 'two_factor.failed', 'two_factor.recovery_used', 'two_factor.recovery_regenerated',
        'user.role_changed', 'user.password_reset_by_admin', 'user.two_factor_reset',
        'role.permissions_changed',
        'settings.updated', 'settings.deleted',
        'maintenance.generate_key', 'maintenance.migrate', 'maintenance.clear_caches', 'maintenance.owner_created', 'maintenance.owner_recovered',
    ];

    public const TYPES = ['user', 'role', 'setting'];

    public static function label(string $event): string
    {
        $key = 'audit.events.'.$event;
        $label = __($key);

        return $label === $key ? $event : $label;
    }

    public static function typeLabel(?string $type): string
    {
        if ($type === null) {
            return '';
        }

        $key = 'audit.types.'.$type;
        $label = __($key);

        return $label === $key ? $type : $label;
    }
}
