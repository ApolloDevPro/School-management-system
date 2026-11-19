<?php
declare(strict_types=1);

/**
 * Sponsors, bursaries, discounts and fee waivers.
 *
 * Each adjustment either stays as a record (class-wide policy) or is applied
 * to one student invoice, where the invoice totals and balance are updated.
 */
final class FinanceSupportModule extends Model
{
    /**
     * Run a callback inside one transaction and roll back on any error.
     */
    private static function transaction(callable $callback): mixed
    {
        $db = static::db();
        $db->beginTransaction();
        try {
            $result = $callback($db);
            $db->commit();
            return $result;
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }
    }

    /**
     * Insert into one of the two registry tables this module owns.
     */
    private static function insertRow(string $table, array $data): int
    {
        if (!in_array($table, ['sponsors', 'bursaries'], true)) {
            throw new InvalidArgumentException('Unsupported finance support table.');
        }
        $columns = array_keys($data);
        static::db()->prepare(
            'INSERT INTO ' . $table . ' (' . implode(', ', $columns) . ') VALUES ('
            . implode(', ', array_fill(0, count($columns), '?')) . ')'
        )->execute(array_values($data));
        return (int)static::db()->lastInsertId();
    }

    public static function years(int $schoolId): array
    {
        return static::query(
            'SELECT id, name, start_date, end_date, is_current
               FROM academic_years WHERE school_id = ? ORDER BY start_date DESC',
            [$schoolId]
        );
    }

    public static function terms(int $schoolId): array
    {
        return static::query(
            'SELECT t.id, t.name, t.term_number, t.is_active, y.id AS year_id, y.name AS year_name
               FROM terms t JOIN academic_years y ON y.id = t.academic_year_id
              WHERE y.school_id = ? ORDER BY y.start_date DESC, t.term_number',
            [$schoolId]
        );
    }

    public static function classes(int $schoolId): array
    {
        return static::query(
            'SELECT id, name, level FROM classes WHERE school_id = ? AND is_active = 1 ORDER BY order_index, name',
            [$schoolId]
        );
    }

    public static function students(int $schoolId): array
    {
        return static::query(
            'SELECT id, admission_number, first_name, last_name
               FROM students WHERE school_id = ? AND status = ? ORDER BY last_name, first_name',
            [$schoolId, 'active']
        );
    }

    public static function sponsors(int $schoolId): array
    {
        return static::query(
            'SELECT sp.*,
                    (SELECT COUNT(*) FROM bursaries b WHERE b.sponsor_id = sp.id) AS bursary_count
               FROM sponsors sp
              WHERE sp.school_id = ?
              ORDER BY sp.name',
            [$schoolId]
        );
    }

    public static function sponsor(int $id, int $schoolId): ?array
    {
        return static::one('SELECT * FROM sponsors WHERE id = ? AND school_id = ?', [$id, $schoolId]);
    }

    public static function saveSponsor(int $schoolId, array $data): int
    {
        if (!empty($data['id'])) {
            $id = (int)$data['id'];
            $exists = static::sponsor($id, $schoolId);
            if (!$exists) {
                throw new DomainException('Sponsor not found.');
            }
            unset($data['id']);
            $assignments = implode(', ', array_map(static fn (string $c): string => $c . ' = ?', array_keys($data)));
            static::execute('UPDATE sponsors SET ' . $assignments . ' WHERE id = ? AND school_id = ?', [...array_values($data), $id, $schoolId]);
            return $id;
        }
        $data['school_id'] = $schoolId;
        $data['balance'] = 0;
        return static::insertRow('sponsors', $data);
    }

    public static function bursaries(int $schoolId): array
    {
        return static::query(
            'SELECT b.*, sp.name AS sponsor_name,
                    (SELECT COUNT(*) FROM bursary_awards a WHERE a.bursary_id = b.id) AS award_count,
                    (SELECT COALESCE(SUM(a.award_amount), 0) FROM bursary_awards a
                      WHERE a.bursary_id = b.id AND a.status = ?) AS awarded_total
               FROM bursaries b
               LEFT JOIN sponsors sp ON sp.id = b.sponsor_id
              WHERE b.school_id = ?
              ORDER BY b.status, b.name',
            ['active', $schoolId]
        );
    }

    public static function bursary(int $id, int $schoolId): ?array
    {
        return static::one('SELECT * FROM bursaries WHERE id = ? AND school_id = ?', [$id, $schoolId]);
    }

    public static function saveBursary(int $schoolId, array $data): int
    {
        if (!empty($data['id'])) {
            $id = (int)$data['id'];
            if (!static::bursary($id, $schoolId)) {
                throw new DomainException('Bursary not found.');
            }
            unset($data['id']);
            $assignments = implode(', ', array_map(static fn (string $c): string => $c . ' = ?', array_keys($data)));
            static::execute('UPDATE bursaries SET ' . $assignments . ' WHERE id = ? AND school_id = ?', [...array_values($data), $id, $schoolId]);
            return $id;
        }
        $data['school_id'] = $schoolId;
        return static::insertRow('bursaries', $data);
    }

    public static function awards(int $schoolId): array
    {
        return static::query(
            'SELECT a.*, b.name AS bursary_name, b.coverage_type,
                    s.admission_number, s.first_name, s.last_name,
                    y.name AS year_name, t.name AS term_name
               FROM bursary_awards a
               JOIN bursaries b ON b.id = a.bursary_id
               JOIN students s ON s.id = a.student_id
               JOIN academic_years y ON y.id = a.academic_year_id
               LEFT JOIN terms t ON t.id = a.term_id
              WHERE b.school_id = ?
              ORDER BY a.created_at DESC, a.id DESC',
            [$schoolId]
        );
    }

    /**
     * Record one award. A percentage is valued against the student's invoices
     * for the year and term, so the award amount shown is an estimate until it
     * is applied to a specific invoice.
     */
    public static function saveAward(int $schoolId, array $data, ?int $actorId): int
    {
        $bursary = static::bursary((int)$data['bursary_id'], $schoolId);
        if (!$bursary || $bursary['status'] !== 'open') {
            throw new DomainException('Choose an open bursary for this school.');
        }
        $student = Student::findFull((int)$data['student_id']);
        if (!$student || (int)$student['school_id'] !== $schoolId) {
            throw new DomainException('Choose an active student for this school.');
        }
        $year = static::one('SELECT id FROM academic_years WHERE id = ? AND school_id = ?', [(int)$data['academic_year_id'], $schoolId]);
        if (!$year) {
            throw new DomainException('Choose an academic year for this school.');
        }
        if (!empty($data['term_id'])) {
            $term = static::one(
                'SELECT t.id FROM terms t JOIN academic_years y ON y.id = t.academic_year_id
                  WHERE t.id = ? AND y.school_id = ? AND y.id = ?',
                [(int)$data['term_id'], $schoolId, (int)$data['academic_year_id']]
            );
            if (!$term) {
                throw new DomainException('Choose a term that belongs to the academic year.');
            }
        }

        $value = (float)$data['award_value'];
        $amount = (float)$data['award_amount'];
        if ($data['award_type'] === 'percent') {
            $sql = 'SELECT COALESCE(SUM(total_amount), 0) FROM fee_invoices
                     WHERE school_id = ? AND student_id = ? AND academic_year_id = ? AND status <> ?';
            $params = [$schoolId, (int)$data['student_id'], (int)$data['academic_year_id'], 'void'];
            if (!empty($data['term_id'])) {
                $sql .= ' AND term_id = ?';
                $params[] = (int)$data['term_id'];
            }
            $amount = round((float)static::scalar($sql, $params) * $value / 100, 2);
        }

        $statement = static::db()->prepare(
            'INSERT INTO bursary_awards
                (bursary_id, student_id, academic_year_id, term_id, award_amount, award_type,
                 award_value, start_date, end_date, status, approved_by, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $statement->execute([
            (int)$data['bursary_id'], (int)$data['student_id'], (int)$data['academic_year_id'],
            $data['term_id'] ?: null, $amount, $data['award_type'], $value,
            $data['start_date'] ?: null, $data['end_date'] ?: null, 'active', $actorId, $data['notes'] ?: null,
        ]);
        return (int)static::db()->lastInsertId();
    }

    public static function discounts(int $schoolId): array
    {
        return static::query(
            'SELECT d.*, s.admission_number, s.first_name, s.last_name,
                    c.name AS class_name, y.name AS year_name, t.name AS term_name,
                    i.invoice_number
               FROM discounts_waivers d
               LEFT JOIN students s ON s.id = d.student_id
               LEFT JOIN classes c ON c.id = d.class_id
               JOIN academic_years y ON y.id = d.academic_year_id
               LEFT JOIN terms t ON t.id = d.term_id
               LEFT JOIN fee_invoices i ON i.id = d.invoice_id
              WHERE d.school_id = ?
              ORDER BY d.created_at DESC, d.id DESC',
            [$schoolId]
        );
    }

    public static function saveDiscount(int $schoolId, array $data, ?int $actorId): int
    {
        $year = static::one('SELECT id FROM academic_years WHERE id = ? AND school_id = ?', [(int)$data['academic_year_id'], $schoolId]);
        if (!$year) {
            throw new DomainException('Choose an academic year for this school.');
        }
        if (!empty($data['student_id'])) {
            $student = Student::findFull((int)$data['student_id']);
            if (!$student || (int)$student['school_id'] !== $schoolId) {
                throw new DomainException('Choose a student from this school.');
            }
        }
        if (!empty($data['class_id'])) {
            $class = static::one('SELECT id FROM classes WHERE id = ? AND school_id = ?', [(int)$data['class_id'], $schoolId]);
            if (!$class) {
                throw new DomainException('Choose a class from this school.');
            }
        }
        if (empty($data['student_id']) && empty($data['class_id'])) {
            throw new DomainException('Choose a student or a class for this adjustment.');
        }
        if (!empty($data['term_id'])) {
            $term = static::one(
                'SELECT t.id FROM terms t JOIN academic_years y ON y.id = t.academic_year_id
                  WHERE t.id = ? AND y.school_id = ? AND y.id = ?',
                [(int)$data['term_id'], $schoolId, (int)$data['academic_year_id']]
            );
            if (!$term) {
                throw new DomainException('Choose a term that belongs to the academic year.');
            }
        }

        $statement = static::db()->prepare(
            'INSERT INTO discounts_waivers
                (school_id, student_id, class_id, academic_year_id, term_id, adjustment_type, reason,
                 description, value_type, value, max_amount, expires_on, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $statement->execute([
            $schoolId, $data['student_id'] ?: null, $data['class_id'] ?: null,
            (int)$data['academic_year_id'], $data['term_id'] ?: null,
            $data['adjustment_type'], $data['reason'], $data['description'] ?: null,
            $data['value_type'], (float)$data['value'],
            $data['max_amount'] !== null ? (float)$data['max_amount'] : null,
            $data['expires_on'] ?: null, 'pending',
        ]);
        return (int)static::db()->lastInsertId();
    }

    public static function approveDiscount(int $id, int $schoolId, int $actorId): void
    {
        $row = static::one('SELECT * FROM discounts_waivers WHERE id = ? AND school_id = ?', [$id, $schoolId]);
        if (!$row || $row['status'] !== 'pending') {
            throw new DomainException('Only a pending adjustment can be approved.');
        }
        static::execute(
            'UPDATE discounts_waivers SET status = ?, approved_by = ?, approved_at = ? WHERE id = ? AND school_id = ?',
            ['approved', $actorId, date('Y-m-d H:i:s'), $id, $schoolId]
        );
    }

    /**
     * Apply an approved award to the best open invoice for that student.
     */
    public static function applyAward(int $awardId, int $schoolId, int $actorId): array
    {
        $award = static::one(
            'SELECT a.*, b.name AS bursary_name, b.coverage_type
               FROM bursary_awards a JOIN bursaries b ON b.id = a.bursary_id
              WHERE a.id = ? AND b.school_id = ?',
            [$awardId, $schoolId]
        );
        if (!$award) {
            throw new DomainException('Bursary award not found.');
        }
        if ($award['status'] !== 'active') {
            throw new DomainException('Only an active award can be applied.');
        }
        return static::transaction(function (PDO $db) use ($award, $schoolId, $actorId): array {
            $invoice = static::invoiceFor($db, $schoolId, (int)$award['student_id'], (int)$award['academic_year_id'], $award['term_id'] ? (int)$award['term_id'] : null, null);
            if (!$invoice) {
                throw new DomainException('No open invoice was found for this student, year and term.');
            }
            $amount = (float)$award['award_amount'];
            if ($award['award_type'] === 'percent') {
                $amount = round((float)$invoice['subtotal'] * (float)$award['award_value'] / 100, 2);
            }
            $applied = static::applyToInvoice($db, $invoice, $amount, 'waived', $schoolId, $actorId, [
                'adjustment_type' => 'bursary_offset',
                'reason' => 'scholarship',
                'description' => $award['bursary_name'],
            ]);
            $db->prepare('UPDATE bursary_awards SET award_amount = ?, status = ? WHERE id = ?')
                ->execute([$applied['amount'], 'completed', (int)$award['id']]);
            return $applied + ['invoice_number' => $invoice['invoice_number']];
        });
    }

    /**
     * Apply a discount or waiver to one invoice and update invoice totals.
     */
    public static function applyDiscount(int $id, int $schoolId, int $actorId): array
    {
        $row = static::one('SELECT * FROM discounts_waivers WHERE id = ? AND school_id = ?', [$id, $schoolId]);
        if (!$row) {
            throw new DomainException('Adjustment not found.');
        }
        if (!in_array($row['status'], ['pending', 'approved'], true)) {
            throw new DomainException('Only a pending or approved adjustment can be applied.');
        }
        if (empty($row['student_id'])) {
            throw new DomainException('Class-wide adjustments need a student before they can be applied to an invoice.');
        }
        return static::transaction(function (PDO $db) use ($row, $schoolId, $actorId): array {
            $invoice = static::invoiceFor(
                $db, $schoolId, (int)$row['student_id'], (int)$row['academic_year_id'],
                $row['term_id'] ? (int)$row['term_id'] : null, $row['invoice_id'] ? (int)$row['invoice_id'] : null
            );
            if (!$invoice) {
                throw new DomainException('No open invoice was found for this adjustment.');
            }
            $amount = (float)$row['value'];
            if ($row['value_type'] === 'percent') {
                $amount = round((float)$invoice['subtotal'] * $amount / 100, 2);
            }
            if ($row['max_amount'] !== null) {
                $amount = min($amount, (float)$row['max_amount']);
            }
            $bucket = $row['adjustment_type'] === 'discount' ? 'discount' : 'waived';
            $applied = static::applyToInvoice($db, $invoice, $amount, $bucket, $schoolId, $actorId, [
                'adjustment_type' => $row['adjustment_type'],
                'reason' => $row['reason'],
                'description' => $row['description'],
            ]);
            $db->prepare(
                'UPDATE discounts_waivers SET status = ?, invoice_id = ?, approved_by = COALESCE(approved_by, ?),
                        approved_at = COALESCE(approved_at, ?) WHERE id = ?'
            )->execute(['applied', (int)$invoice['id'], $actorId, date('Y-m-d H:i:s'), (int)$row['id']]);
            return $applied + ['invoice_number' => $invoice['invoice_number']];
        });
    }

    /**
     * Lock the invoice, reduce its balance by the adjustment and add a record
     * to the adjustment table so the change stays visible on the invoice.
     */
    private static function applyToInvoice(
        PDO $db,
        array $invoice,
        float $amount,
        string $bucket,
        int $schoolId,
        int $actorId,
        array $meta
    ): array {
        $balance = (float)$invoice['balance'];
        if ($balance <= 0.004) {
            throw new DomainException('Invoice ' . $invoice['invoice_number'] . ' is already settled.');
        }
        $amount = round(min(max(0.0, $amount), $balance), 2);
        if ($amount <= 0) {
            throw new DomainException('The adjustment has no value to apply.');
        }
        $discountTotal = (float)$invoice['discount_total'] + ($bucket === 'discount' ? $amount : 0);
        $waivedTotal = (float)$invoice['waived_total'] + ($bucket === 'waived' ? $amount : 0);
        $paid = (float)$invoice['paid_amount'];
        $total = max($paid, round((float)$invoice['subtotal'] - $discountTotal - $waivedTotal, 2));
        $newBalance = round($total - $paid, 2);
        $status = $newBalance <= 0.004 ? 'paid' : ($paid > 0 ? 'part_paid' : 'unpaid');

        $db->prepare(
            'UPDATE fee_invoices SET discount_total = ?, waived_total = ?, total_amount = ?, balance = ?, status = ?
              WHERE id = ?'
        )->execute([$discountTotal, $waivedTotal, $total, $newBalance, $status, (int)$invoice['id']]);

        $db->prepare(
            'INSERT INTO discounts_waivers
                (school_id, student_id, academic_year_id, term_id, adjustment_type, reason, description,
                 value_type, value, max_amount, invoice_id, approved_by, approved_at, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $schoolId, (int)$invoice['student_id'], (int)$invoice['academic_year_id'], $invoice['term_id'],
            $meta['adjustment_type'], $meta['reason'], $meta['description'] ?: 'Applied automatically',
            'amount', $amount, null, (int)$invoice['id'], $actorId, date('Y-m-d H:i:s'), 'applied',
        ]);

        return [
            'amount' => $amount,
            'balance' => $newBalance,
            'status' => $status,
            'invoice_id' => (int)$invoice['id'],
        ];
    }

    private static function invoiceFor(
        PDO $db,
        int $schoolId,
        int $studentId,
        int $yearId,
        ?int $termId,
        ?int $invoiceId
    ): ?array {
        $sql = 'SELECT * FROM fee_invoices
                 WHERE school_id = ? AND student_id = ? AND academic_year_id = ? AND status <> ?
                   AND balance > 0';
        $params = [$schoolId, $studentId, $yearId, 'void'];
        if ($termId !== null) {
            $sql .= ' AND (term_id = ? OR term_id IS NULL)';
            $params[] = $termId;
        }
        if ($invoiceId !== null) {
            $sql .= ' AND id = ?';
            $params[] = $invoiceId;
        }
        $sql .= ' ORDER BY id DESC LIMIT 1 FOR UPDATE';
        $statement = $db->prepare($sql);
        $statement->execute($params);
        return $statement->fetch() ?: null;
    }
}
