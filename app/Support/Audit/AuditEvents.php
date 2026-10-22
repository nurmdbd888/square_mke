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
        'document.posted', 'document.posted_edited', 'document.voided',
        'stock_note.posted', 'stock_note.posted_edited', 'stock_note.voided',
        'claim.status_changed', 'scheme_line.posted',
        'stock_check.confirmed',
        'payment.posted', 'payment.posted_edited', 'payment.voided', 'payment.cleared',
        'collection.confirmed',
        'daily_closing.created',
        'month_lock.locked', 'month_lock.unlocked',
    ];

    public const TYPES = [
        'user', 'role', 'setting',
        'product', 'product_alias', 'supplier', 'sales_route', 'sales_rep', 'vehicle',
        'price_list', 'price_list_item', 'retailer', 'opening_balance', 'custom_field',
        'document', 'document_line', 'stock_ledger_entry', 'product_batch',
        'stock_note', 'stock_note_line', 'claim', 'scheme_line', 'stock_comparison', 'stock_check',
        'payment', 'payment_allocation', 'daily_closing', 'expense_category',
        'month_lock', 'import_mapping_profile', 'master_import_batch', 'master_import_row',
    ];

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
