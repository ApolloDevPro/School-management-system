<?php
declare(strict_types=1);

final class AuditLog extends Model
{
    protected static string $table = 'audit_logs';

    public static function recent(int $limit = 8): array
    {
        return static::query(
            'SELECT a.created_at, a.user_name, a.module, a.action, a.summary
               FROM audit_logs a
              ORDER BY a.id DESC
              LIMIT ' . max(1, min(50, $limit))
        );
    }

    public static function record(array $data): void
    {
        static::insert($data);
    }
}
