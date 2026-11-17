<?php
declare(strict_types=1);

final class AcademicYear extends Model
{
    protected static string $table = 'academic_years';

    public static function forSchool(int $schoolId): array
    {
        return static::query(
            'SELECT * FROM academic_years WHERE school_id = ? ORDER BY start_date DESC, id DESC',
            [$schoolId]
        );
    }

    public static function active(int $schoolId): ?array
    {
        return static::one(
            'SELECT * FROM academic_years WHERE school_id = ? AND is_active = 1 ORDER BY id LIMIT 1',
            [$schoolId]
        );
    }

    public static function activate(int $schoolId, int $id): void
    {
        $db = static::db();
        $db->beginTransaction();
        try {
            $db->prepare('UPDATE academic_years SET is_active = 0 WHERE school_id = ?')->execute([$schoolId]);
            $db->prepare(
                "UPDATE academic_years SET is_active = 1, status = 'active' WHERE id = ? AND school_id = ?"
            )->execute([$id, $schoolId]);
            $db->prepare(
                'UPDATE terms t JOIN academic_years y ON y.id = t.academic_year_id
                    SET t.is_active = 0
                  WHERE y.school_id = ? AND t.academic_year_id <> ?'
            )->execute([$schoolId, $id]);
            $db->commit();
        } catch (Throwable $error) {
            $db->rollBack();
            throw $error;
        }
    }

    public static function nameTaken(int $schoolId, string $name, ?int $ignoreId = null): bool
    {
        $sql    = 'SELECT COUNT(*) FROM academic_years WHERE school_id = ? AND name = ?';
        $params = [$schoolId, $name];
        if ($ignoreId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $ignoreId;
        }
        return (int)static::scalar($sql, $params) > 0;
    }
}
