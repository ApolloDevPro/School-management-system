<?php
declare(strict_types=1);

/**
 * A-Level subject combinations and their subjects.
 *
 * Backend model: thin static data-access layer over the shared PDO connection.
 */
final class AlevelCombination extends Model
{
    protected static string $table = 'alevel_combinations';

    public static function forSchool(int $schoolId): array
    {
        return static::query(
            'SELECT c.*,
                    (SELECT COUNT(*) FROM combination_subjects cs WHERE cs.combination_id = c.id) AS subject_count,
                    (SELECT COUNT(*) FROM student_combinations sc
                      WHERE sc.combination_id = c.id AND sc.status = \'active\') AS student_count
               FROM alevel_combinations c
              WHERE c.school_id = ?
              ORDER BY c.code',
            [$schoolId]
        );
    }

    public static function activeForSchool(int $schoolId): array
    {
        return static::query(
            'SELECT * FROM alevel_combinations WHERE school_id = ? AND is_active = 1 ORDER BY code',
            [$schoolId]
        );
    }

    public static function findInSchool(int $id, int $schoolId): ?array
    {
        return static::one(
            'SELECT * FROM alevel_combinations WHERE id = ? AND school_id = ?',
            [$id, $schoolId]
        );
    }

    public static function codeTaken(int $schoolId, string $code, ?int $ignoreId = null): bool
    {
        $sql    = 'SELECT COUNT(*) FROM alevel_combinations WHERE school_id = ? AND code = ?';
        $params = [$schoolId, $code];
        if ($ignoreId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $ignoreId;
        }
        return (int)static::scalar($sql, $params) > 0;
    }

    public static function replaceSubjects(int $combinationId, array $rows): void
    {
        $db = static::db();
        $db->beginTransaction();
        try {
            $db->prepare('DELETE FROM combination_subjects WHERE combination_id = ?')->execute([$combinationId]);
            $statement = $db->prepare(
                'INSERT INTO combination_subjects (combination_id, subject_id, subject_role, is_compulsory, order_index)
                 VALUES (?, ?, ?, ?, ?)'
            );
            foreach ($rows as $index => $row) {
                $statement->execute([
                    $combinationId,
                    $row['subject_id'],
                    $row['subject_role'],
                    $row['is_compulsory'] ? 1 : 0,
                    $index,
                ]);
            }
            $db->commit();
        } catch (Throwable $error) {
            $db->rollBack();
            throw $error;
        }
    }
}
