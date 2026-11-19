<?php
declare(strict_types=1);

/**
 * Terms and the active term.
 *
 * Backend model: thin static data-access layer over the shared PDO connection.
 */
final class Term extends Model
{
    protected static string $table = 'terms';

    public static function forYear(int $yearId): array
    {
        return static::query(
            'SELECT * FROM terms WHERE academic_year_id = ? ORDER BY term_number',
            [$yearId]
        );
    }

    public static function active(): ?array
    {
        return static::one(
            'SELECT t.*, y.name AS year_name, y.school_id
               FROM terms t
               JOIN academic_years y ON y.id = t.academic_year_id
              WHERE t.is_active = 1
              ORDER BY t.id LIMIT 1'
        );
    }

    public static function withYear(): array
    {
        return static::query(
            'SELECT t.*, y.name AS year_name
               FROM terms t
               JOIN academic_years y ON y.id = t.academic_year_id
              ORDER BY y.start_date DESC, t.term_number'
        );
    }

    public static function activate(int $id): void
    {
        $db = static::db();
        $db->beginTransaction();
        try {
            $db->prepare('UPDATE terms SET is_active = 0')->execute();
            $db->prepare(
                "UPDATE terms SET is_active = 1, status = 'active' WHERE id = ?"
            )->execute([$id]);
            $db->commit();
        } catch (Throwable $error) {
            $db->rollBack();
            throw $error;
        }
    }

    public static function numberTaken(int $yearId, int $number, ?int $ignoreId = null): bool
    {
        $sql    = 'SELECT COUNT(*) FROM terms WHERE academic_year_id = ? AND term_number = ?';
        $params = [$yearId, $number];
        if ($ignoreId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $ignoreId;
        }
        return (int)static::scalar($sql, $params) > 0;
    }
}
