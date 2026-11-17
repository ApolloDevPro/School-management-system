<?php
declare(strict_types=1);

final class Promotion extends Model
{
    protected static string $table = 'promotion_batches';

    public static function batchesForSchool(int $schoolId): array
    {
        return static::query(
            'SELECT b.*, y.name AS year_name,
                    (SELECT COUNT(*) FROM promotion_records r WHERE r.batch_id = b.id) AS record_count
               FROM promotion_batches b
               JOIN academic_years y ON y.id = b.academic_year_id
              WHERE b.school_id = ?
              ORDER BY b.id DESC LIMIT 100',
            [$schoolId]
        );
    }

    public static function recordsOf(int $batchId): array
    {
        return static::query(
            'SELECT r.*, s.first_name, s.last_name, s.admission_number,
                    fc.name AS from_class_name, tc.name AS to_class_name
               FROM promotion_records r
               JOIN students s ON s.id = r.student_id
               JOIN classes fc ON fc.id = r.from_class_id
               LEFT JOIN classes tc ON tc.id = r.to_class_id
              WHERE r.batch_id = ?
              ORDER BY fc.order_index, fc.name, s.last_name, s.first_name',
            [$batchId]
        );
    }
}
