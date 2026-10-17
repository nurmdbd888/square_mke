<?php

namespace App\Support\Audit;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Writes rows to audit_logs. Never throws: a failing audit write is logged to
 * the file log and must not break the action being audited (for example the
 * Maintenance page before the audit table exists).
 */
class AuditLogger
{
    public const SOURCE_WEB = 'web';

    public const SOURCE_MAINTENANCE = 'maintenance';

    public const SOURCE_CONSOLE = 'console';

    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    public static function log(
        string $event,
        ?Model $subject = null,
        ?array $old = null,
        ?array $new = null,
        ?User $actor = null,
        ?string $source = null,
        ?string $subjectType = null,
    ): ?AuditLog {
        try {
            $request = request();
            $inHttp = $request->route() !== null;
            $actor ??= self::currentUser();

            return AuditLog::query()->create([
                'user_id' => $actor?->getKey(),
                'user_name' => $actor?->username,
                'source' => $source ?? ($inHttp ? ($request->is('maintenance', 'maintenance/*') ? self::SOURCE_MAINTENANCE : self::SOURCE_WEB) : self::SOURCE_CONSOLE),
                'event' => $event,
                'auditable_type' => $subject?->getMorphClass() ?? $subjectType,
                'auditable_id' => $subject?->getKey(),
                'old_values' => $old === [] ? null : $old,
                'new_values' => $new === [] ? null : $new,
                'ip_address' => $inHttp ? $request->ip() : null,
                'user_agent' => $inHttp ? mb_substr((string) $request->userAgent(), 0, 255) : null,
                'url' => $inHttp ? mb_substr($request->method().' '.$request->path(), 0, 255) : null,
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::warning('Audit log write failed: '.$e->getMessage(), ['event' => $event]);

            return null;
        }
    }

    private static function currentUser(): ?User
    {
        try {
            $user = Auth::user();

            return $user instanceof User ? $user : null;
        } catch (Throwable) {
            return null;
        }
    }
}
