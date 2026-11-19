<?php
declare(strict_types=1);

/**
 * Staff records.
 *
 * Backend model: thin static data-access layer over the shared PDO connection.
 */
final class Staff extends Model
{
    protected static string $table = 'staff';

    public static function forSchool(int $schoolId, string $status = ''): array
    {
        $sql = 'SELECT s.*, CONCAT(s.first_name, \' \', s.last_name) AS full_name,
                       d.name AS department_name
                  FROM staff s
                  LEFT JOIN departments d ON d.id = s.department_id
                 WHERE s.school_id = ?';
        $params = [$schoolId];
        if ($status !== '') {
            $sql .= ' AND s.employment_status = ?';
            $params[] = $status;
        } else {
            $sql .= " AND s.employment_status IN ('active','probation','contract')";
        }
        $sql .= ' ORDER BY s.first_name, s.last_name';
        return static::query($sql, $params);
    }

    public static function activeForSchool(int $schoolId): array
    {
        return static::forSchool($schoolId);
    }

    public static function name(int $id): string
    {
        $row = static::one('SELECT CONCAT(first_name, \' \', last_name) AS n FROM staff WHERE id = ?', [$id]);
        return $row['n'] ?? '';
    }

    public static function displayName(int $id): string
    {
        return static::name($id);
    }

    public static function exists(int $id): bool
    {
        return static::one('SELECT id FROM staff WHERE id = ?', [$id]) !== null;
    }
}
