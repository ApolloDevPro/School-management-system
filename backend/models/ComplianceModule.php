<?php
declare(strict_types=1);

final class ComplianceModule extends Model
{
    // ------------------------------------------------------------------ EMIS

    public static function emisReturns(int $schoolId): array
    {
        return static::query(
            'SELECT r.id, r.return_type, r.period_label, r.reference_number,
                    r.total_learners, r.total_teachers, r.status, r.prepared_at,
                    r.submitted_at, y.name AS year_name
               FROM emis_returns r
               JOIN academic_years y ON y.id = r.academic_year_id
              WHERE r.school_id = ?
              ORDER BY r.created_at DESC
              LIMIT 200',
            [$schoolId]
        );
    }

    public static function emisReturn(int $id, int $schoolId): ?array
    {
        return static::one(
            'SELECT r.id, r.return_type, r.period_label, r.reference_number,
                    r.total_learners, r.total_teachers, r.total_support_staff,
                    r.status, r.prepared_at, r.submitted_at,
                    y.name AS year_name
               FROM emis_returns r
               JOIN academic_years y ON y.id = r.academic_year_id
              WHERE r.id = ? AND r.school_id = ?',
            [$id, $schoolId]
        );
    }

    public static function createEmisReturn(array $data): int
    {
        static::db()->prepare(
            'INSERT INTO emis_returns
               (school_id, academic_year_id, term_id, return_type, period_label,
                total_learners, total_teachers, total_support_staff, status, prepared_by, prepared_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,NOW())'
        )->execute([
            $data['school_id'], $data['year_id'], $data['term_id'] ?: null,
            $data['return_type'], $data['period_label'],
            $data['learners'], $data['teachers'], $data['support'],
            'draft', $data['user_id'],
        ]);
        return (int)static::db()->lastInsertId();
    }

    public static function submitEmisReturn(int $id, int $schoolId, int $userId): bool
    {
        return static::execute(
            "UPDATE emis_returns SET status='submitted', submitted_by=?, submitted_at=NOW()
              WHERE id=? AND school_id=? AND status='validated'",
            [$userId, $id, $schoolId]
        ) > 0;
    }

    public static function validateEmisReturn(int $id, int $schoolId, int $userId): bool
    {
        return static::execute(
            "UPDATE emis_returns SET status='validated', validated_by=?, validated_at=NOW()
              WHERE id=? AND school_id=? AND status='draft'",
            [$userId, $id, $schoolId]
        ) > 0;
    }

    // --------------------------------------------------------------- Grants

    public static function grants(int $schoolId): array
    {
        return static::query(
            'SELECT g.id, g.grant_type, g.amount_expected, g.amount_received,
                    g.beneficiary_count, g.per_learner_rate, g.received_date,
                    g.reference_number, g.status, g.allocation_status,
                    y.name AS year_name, t.name AS term_name
               FROM capitation_grants g
               JOIN academic_years y ON y.id = g.academic_year_id
               LEFT JOIN terms t ON t.id = g.term_id
              WHERE g.school_id = ?
              ORDER BY g.created_at DESC
              LIMIT 200',
            [$schoolId]
        );
    }

    public static function createGrant(array $data): int
    {
        static::db()->prepare(
            'INSERT INTO capitation_grants
               (school_id, grant_type, academic_year_id, term_id,
                amount_expected, beneficiary_count, per_learner_rate,
                reference_number, status)
             VALUES (?,?,?,?,?,?,?,?,?)'
        )->execute([
            $data['school_id'], $data['type'], $data['year_id'],
            $data['term_id'] ?: null, $data['amount_expected'],
            $data['beneficiary_count'] ?: null,
            $data['per_learner_rate'] ?: null,
            $data['reference'] ?: null,
            'expected',
        ]);
        return (int)static::db()->lastInsertId();
    }

    public static function recordGrantReceipt(int $id, int $schoolId, float $amount, string $date, int $userId): bool
    {
        return static::execute(
            "UPDATE capitation_grants
                SET amount_received=?, received_date=?, received_by=?,
                    status=IF(amount_received>=amount_expected,'received','partially_received')
              WHERE id=? AND school_id=?",
            [$amount, $date, $userId, $id, $schoolId]
        ) > 0;
    }

    // ----------------------------------------------------------- Shared lookups

    public static function years(int $schoolId): array
    {
        return static::query(
            'SELECT id, name, is_active FROM academic_years WHERE school_id=? ORDER BY is_active DESC, start_date DESC',
            [$schoolId]
        );
    }

    public static function terms(int $schoolId): array
    {
        return static::query(
            'SELECT t.id, t.name, t.academic_year_id FROM terms t
               JOIN academic_years y ON y.id=t.academic_year_id
              WHERE y.school_id=? ORDER BY y.is_active DESC, t.term_number ASC',
            [$schoolId]
        );
    }
}
