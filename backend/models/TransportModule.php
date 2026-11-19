<?php
declare(strict_types=1);

/**
 * Routes, stops, student transport allocation and transport charges.
 *
 * Backend model: thin static data-access layer over the shared PDO connection.
 */
final class TransportModule extends Model
{
    public static function routes(int $schoolId): array
    {
        return static::query(
            'SELECT id,route_code,route_name,distance_km,fare_per_term,status FROM routes WHERE school_id=? ORDER BY route_name LIMIT 300',
            [$schoolId]
        );
    }

    public static function createRoute(array $data): int
    {
        static::db()->prepare(
            'INSERT INTO routes (school_id,route_code,route_name,description,distance_km,fare_per_term,status)
             VALUES (?,?,?,?,?,?,?)'
        )->execute([$data['school_id'],$data['code'],$data['name'],$data['description'],$data['distance'],$data['fare'],'active']);
        return (int)static::db()->lastInsertId();
    }

    public static function changeStatus(int $id, int $schoolId, string $status): bool
    {
        return static::execute('UPDATE routes SET status=? WHERE id=? AND school_id=?',[$status,$id,$schoolId]) > 0;
    }

    public static function years(int $schoolId): array
    {
        return static::query(
            'SELECT id,name,is_active,start_date,end_date FROM academic_years WHERE school_id=? ORDER BY is_active DESC,start_date DESC LIMIT 30',
            [$schoolId]
        );
    }

    public static function terms(int $schoolId, int $yearId): array
    {
        return static::query(
            'SELECT t.id,t.name,t.term_number,t.is_active,t.start_date,t.end_date
               FROM terms t
               JOIN academic_years y ON y.id=t.academic_year_id
              WHERE y.school_id=? AND y.id=?
              ORDER BY t.term_number LIMIT 10',
            [$schoolId,$yearId]
        );
    }

    public static function classes(int $schoolId): array
    {
        return static::query(
            'SELECT id,name,level FROM classes WHERE school_id=? AND is_active=1 ORDER BY order_index,name LIMIT 200',
            [$schoolId]
        );
    }

    public static function students(int $schoolId): array
    {
        return static::query(
            "SELECT s.id,s.admission_number,s.first_name,s.last_name,s.current_class_id,c.name AS class_name
               FROM students s
               LEFT JOIN classes c ON c.id=s.current_class_id
              WHERE s.school_id=? AND s.status='active'
              ORDER BY s.last_name,s.first_name LIMIT 2000",
            [$schoolId]
        );
    }

    /**
     * Every stop grouped by route, used to fill the stop picker.
     */
    public static function stops(int $schoolId): array
    {
        return static::query(
            'SELECT rs.id,rs.route_id,rs.stop_name,rs.pick_up_time,rs.drop_off_time,rs.sequence_order
               FROM route_stops rs
               JOIN routes r ON r.id=rs.route_id
              WHERE r.school_id=? AND rs.is_active=1
              ORDER BY rs.route_id,rs.sequence_order,rs.stop_name LIMIT 2000',
            [$schoolId]
        );
    }

    /**
     * Allocations for one academic year, optionally narrowed to one term.
     */
    public static function allocations(int $schoolId, int $yearId, int $termId = 0): array
    {
        $sql = 'SELECT sra.id,sra.student_id,sra.route_id,sra.stop_id,sra.term_id,sra.pickup_point,
                       sra.status,sra.allocated_at,sra.notes,
                       s.admission_number,s.first_name,s.last_name,
                       c.name AS class_name,
                       r.route_code,r.route_name,
                       rs.stop_name,rs.pick_up_time,rs.drop_off_time,
                       t.name AS term_name
                  FROM student_route_allocations sra
                  JOIN students s ON s.id=sra.student_id AND s.school_id=?
                  JOIN routes r ON r.id=sra.route_id AND r.school_id=?
                  LEFT JOIN classes c ON c.id=s.current_class_id
                  LEFT JOIN route_stops rs ON rs.id=sra.stop_id
                  LEFT JOIN terms t ON t.id=sra.term_id
                 WHERE sra.academic_year_id=?';
        $params = [$schoolId,$schoolId,$yearId];
        if ($termId > 0) {
            $sql .= ' AND sra.term_id=?';
            $params[] = $termId;
        }
        $sql .= ' ORDER BY r.route_name,s.last_name,s.first_name LIMIT 2000';
        return static::query($sql,$params);
    }

    /**
     * Assign a student to a route and stop. A student keeps one active route
     * per year and term, so older active routes are retired first.
     */
    public static function saveAllocation(array $data): int
    {
        $db = static::db();
        $db->beginTransaction();
        try {
            $db->prepare(
                "UPDATE student_route_allocations
                    SET status='inactive'
                  WHERE student_id=? AND academic_year_id=? AND term_id <=> ?
                    AND status='active' AND route_id<>?"
            )->execute([$data['student_id'],$data['academic_year_id'],$data['term_id'],$data['route_id']]);

            $existing = $db->prepare(
                'SELECT id FROM student_route_allocations
                  WHERE student_id=? AND route_id=? AND academic_year_id=? AND term_id <=> ?'
            );
            $existing->execute([$data['student_id'],$data['route_id'],$data['academic_year_id'],$data['term_id']]);
            $row = $existing->fetch();

            if ($row) {
                $db->prepare(
                    "UPDATE student_route_allocations
                        SET stop_id=?,pickup_point=?,status='active',allocated_by=?,allocated_at=NOW(),notes=?
                      WHERE id=?"
                )->execute([$data['stop_id'],$data['pickup_point'],$data['allocated_by'],$data['notes'],(int)$row['id']]);
                $id = (int)$row['id'];
            } else {
                $db->prepare(
                    "INSERT INTO student_route_allocations
                        (student_id,route_id,stop_id,academic_year_id,term_id,pickup_point,status,allocated_by,notes)
                     VALUES (?,?,?,?,?,?,'active',?,?)"
                )->execute([
                    $data['student_id'],$data['route_id'],$data['stop_id'],$data['academic_year_id'],
                    $data['term_id'],$data['pickup_point'],$data['allocated_by'],$data['notes'],
                ]);
                $id = (int)$db->lastInsertId();
            }
            $db->commit();
            return $id;
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }
    }

    public static function setAllocationStatus(int $id, int $schoolId, string $status): bool
    {
        return static::execute(
            'UPDATE student_route_allocations sra
               JOIN routes r ON r.id=sra.route_id
                SET sra.status=?
              WHERE sra.id=? AND r.school_id=?',
            [$status,$id,$schoolId]
        ) > 0;
    }

    public static function charges(int $schoolId, int $yearId, int $termId = 0): array
    {
        $sql = 'SELECT tc.*,r.route_code,r.route_name,c.name AS class_name,t.name AS term_name
                  FROM transport_charges tc
                  JOIN routes r ON r.id=tc.route_id
                  LEFT JOIN classes c ON c.id=tc.class_id
                  LEFT JOIN terms t ON t.id=tc.term_id
                 WHERE tc.school_id=? AND tc.academic_year_id=?';
        $params = [$schoolId,$yearId];
        if ($termId > 0) {
            $sql .= ' AND (tc.term_id=? OR tc.term_id IS NULL)';
            $params[] = $termId;
        }
        $sql .= ' ORDER BY r.route_name,tc.term_id DESC,tc.id DESC LIMIT 1000';
        return static::query($sql,$params);
    }

    /**
     * Save a route charge. The unique key treats NULLs as distinct, so an
     * explicit lookup keeps one generic and one class-specific row per term.
     */
    public static function saveCharge(array $data): int
    {
        $existing = static::one(
            'SELECT id FROM transport_charges
              WHERE school_id=? AND route_id=? AND class_id <=> ? AND academic_year_id=? AND term_id <=> ?
              LIMIT 1',
            [$data['school_id'],$data['route_id'],$data['class_id'],$data['academic_year_id'],$data['term_id']]
        );
        if ($existing) {
            static::execute(
                'UPDATE transport_charges SET charge_basis=?,amount=?,account_id=?,is_active=? WHERE id=?',
                [$data['charge_basis'],$data['amount'],$data['account_id'],$data['is_active'],(int)$existing['id']]
            );
            return (int)$existing['id'];
        }
        static::db()->prepare(
            'INSERT INTO transport_charges
                (school_id,route_id,class_id,academic_year_id,term_id,charge_basis,amount,account_id,is_active)
             VALUES (?,?,?,?,?,?,?,?,?)'
        )->execute([
            $data['school_id'],$data['route_id'],$data['class_id'],$data['academic_year_id'],
            $data['term_id'],$data['charge_basis'],$data['amount'],$data['account_id'],$data['is_active'],
        ]);
        return (int)static::db()->lastInsertId();
    }

    /**
     * Raise one transport invoice per allocated student for a term. A student
     * who already has a transport line for the same route is skipped.
     */
    public static function billTerm(int $schoolId, int $yearId, int $termId, string $issueDate, ?string $dueDate, int $actorId): array
    {
        $db = static::db();
        $db->beginTransaction();
        try {
            $allocations = $db->prepare(
                "SELECT sra.student_id,sra.route_id,r.route_name,r.fare_per_term,s.current_class_id
                   FROM student_route_allocations sra
                   JOIN routes r ON r.id=sra.route_id AND r.school_id=?
                   JOIN students s ON s.id=sra.student_id AND s.school_id=?
                  WHERE sra.academic_year_id=? AND sra.term_id=? AND sra.status='active'
                  ORDER BY sra.student_id"
            );
            $allocations->execute([$schoolId,$schoolId,$yearId,$termId]);
            $rows = $allocations->fetchAll();

            $chargeLookup = $db->prepare(
                'SELECT amount FROM transport_charges
                  WHERE school_id=? AND route_id=? AND academic_year_id=?
                    AND (term_id=? OR term_id IS NULL)
                    AND (class_id=? OR class_id IS NULL)
                    AND is_active=1
                  ORDER BY (class_id IS NOT NULL) DESC,(term_id IS NOT NULL) DESC,id DESC
                  LIMIT 1'
            );
            $already = $db->prepare(
                "SELECT COUNT(*) FROM fee_invoice_items fi
                   JOIN fee_invoices i ON i.id=fi.invoice_id
                  WHERE i.school_id=? AND i.student_id=? AND i.academic_year_id=? AND i.term_id=?
                    AND fi.source='transport' AND fi.item_name=?"
            );
            $insertInvoice = $db->prepare(
                "INSERT INTO fee_invoices
                    (invoice_number,school_id,student_id,academic_year_id,term_id,issue_date,due_date,
                     subtotal,total_amount,balance,currency,status,notes,generated_by)
                 VALUES (?,?,?,?,?,?,?,?,?,?,'UGX','unpaid',?,?)"
            );
            $insertItem = $db->prepare(
                "INSERT INTO fee_invoice_items (invoice_id,item_name,amount,net_amount,source,sort_order)
                 VALUES (?,?,?,?,'transport',90)"
            );

            $created = 0;
            $skipped = 0;
            $total = 0.0;
            foreach ($rows as $row) {
                $chargeLookup->execute([
                    $schoolId,(int)$row['route_id'],$yearId,$termId,
                    $row['current_class_id'] !== null ? (int)$row['current_class_id'] : 0,
                ]);
                $amount = $chargeLookup->fetchColumn();
                if ($amount === false || $amount === null) {
                    $amount = (float)($row['fare_per_term'] ?? 0);
                } else {
                    $amount = (float)$amount;
                }
                $amount = round((float)$amount, 2);
                if ($amount <= 0) {
                    $skipped++;
                    continue;
                }

                $itemName = 'Transport - ' . $row['route_name'];
                $already->execute([$schoolId,(int)$row['student_id'],$yearId,$termId,$itemName]);
                if ((int)$already->fetchColumn() > 0) {
                    $skipped++;
                    continue;
                }

                $invoiceNumber = 'TRN-' . date('Ymd-His') . '-' . strtoupper(bin2hex(random_bytes(3)));
                $insertInvoice->execute([
                    $invoiceNumber,$schoolId,(int)$row['student_id'],$yearId,$termId,$issueDate,$dueDate,
                    $amount,$amount,$amount,'Transport for ' . $row['route_name'],$actorId,
                ]);
                $invoiceId = (int)$db->lastInsertId();
                $insertItem->execute([$invoiceId,$itemName,$amount,$amount]);
                $created++;
                $total += $amount;
            }

            audit('transport', 'bill_term', 'fee_invoices', null,
                'Raised ' . $created . ' transport invoice(s) for term ' . $termId,
                null, ['created'=>$created,'skipped'=>$skipped,'total'=>round($total,2)]);

            $db->commit();
            return ['created'=>$created,'skipped'=>$skipped,'total'=>round($total,2)];
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }
    }
}
