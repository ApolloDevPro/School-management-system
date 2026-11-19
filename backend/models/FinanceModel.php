<?php
declare(strict_types=1);

final class FinanceModel extends Model
{
    private const BULK_INVOICE_LIMIT = 1000;

    public static function termsForSchool(int $schoolId): array
    {
        return static::query(
            'SELECT t.id, t.name, t.academic_year_id
               FROM terms t
               JOIN academic_years y ON y.id = t.academic_year_id
              WHERE y.school_id = ?
              ORDER BY y.start_date DESC, t.id DESC',
            [$schoolId]
        );
    }

    public static function invoices(int $schoolId): array
    {
        return static::query(
            'SELECT i.*, s.admission_number, s.first_name, s.last_name, y.name AS year_name, t.name AS term_name
               FROM fee_invoices i
               JOIN students s ON s.id = i.student_id
               JOIN academic_years y ON y.id = i.academic_year_id
               LEFT JOIN terms t ON t.id = i.term_id
              WHERE i.school_id = ?
              ORDER BY i.id DESC LIMIT 100',
            [$schoolId]
        );
    }

    public static function payments(int $schoolId): array
    {
        return static::query(
            'SELECT p.*, s.admission_number, s.first_name, s.last_name,
                    i.invoice_number, r.receipt_number
               FROM payments p
               JOIN students s ON s.id = p.student_id
               LEFT JOIN fee_invoices i ON i.id = p.invoice_id
               LEFT JOIN receipts r ON r.payment_id = p.id AND r.is_void = 0
              WHERE p.school_id = ?
              ORDER BY p.id DESC LIMIT 100',
            [$schoolId]
        );
    }

    public static function paymentMethods(int $schoolId): array
    {
        return static::query(
            'SELECT id, name, channel FROM payment_methods WHERE school_id = ? AND is_active = 1 ORDER BY sort_order, name',
            [$schoolId]
        );
    }

    public static function gateways(int $schoolId): array
    {
        return static::query(
            'SELECT id, provider_code, provider_name, provider_type, currency, environment, is_active, notes
               FROM gateway_configs WHERE school_id = ? ORDER BY provider_name',
            [$schoolId]
        );
    }

    public static function gatewayTransactions(int $schoolId): array
    {
        return static::query(
            'SELECT x.*, g.provider_name, s.admission_number, s.first_name, s.last_name, i.invoice_number
               FROM gateway_transactions x
               JOIN gateway_configs g ON g.id = x.gateway_config_id
               LEFT JOIN students s ON s.id = x.student_id
               LEFT JOIN fee_invoices i ON i.id = x.invoice_id
              WHERE x.school_id = ?
              ORDER BY x.id DESC LIMIT 100',
            [$schoolId]
        );
    }

    public static function grants(int $schoolId): array
    {
        return static::query(
            'SELECT g.*, y.name AS year_name, t.name AS term_name,
                    COALESCE(x.spent_total, 0) AS expenditure_total
               FROM capitation_grants g
               JOIN academic_years y ON y.id = g.academic_year_id
               LEFT JOIN terms t ON t.id = g.term_id
               LEFT JOIN (SELECT grant_id, SUM(amount) AS spent_total FROM grant_expenditures GROUP BY grant_id) x
                 ON x.grant_id = g.id
              WHERE g.school_id = ?
              ORDER BY g.id DESC LIMIT 100',
            [$schoolId]
        );
    }

    public static function grantExpenditures(int $schoolId): array
    {
        return static::query(
            'SELECT x.*, g.grant_type, g.academic_year_id, y.name AS year_name
               FROM grant_expenditures x
               JOIN capitation_grants g ON g.id = x.grant_id
               JOIN academic_years y ON y.id = g.academic_year_id
              WHERE g.school_id = ?
              ORDER BY x.id DESC LIMIT 100',
            [$schoolId]
        );
    }

    public static function emisReturns(int $schoolId): array
    {
        return static::query(
            'SELECT r.*, y.name AS year_name, t.name AS term_name
               FROM emis_returns r
               JOIN academic_years y ON y.id = r.academic_year_id
               LEFT JOIN terms t ON t.id = r.term_id
              WHERE r.school_id = ?
              ORDER BY r.id DESC LIMIT 100',
            [$schoolId]
        );
    }

    public static function saveInvoice(int $schoolId, array $data, ?array $actor): int
    {
        return static::transaction(function (PDO $db) use ($schoolId, $data, $actor): int {
            $statement = $db->prepare('SELECT id FROM students WHERE id = ? AND school_id = ? AND status = ? FOR UPDATE');
            $statement->execute([$data['student_id'], $schoolId, 'active']);
            if (!$statement->fetch()) {
                throw new DomainException('Choose an active student from this school.');
            }
            $statement = $db->prepare('SELECT id FROM academic_years WHERE id = ? AND school_id = ?');
            $statement->execute([$data['academic_year_id'], $schoolId]);
            if (!$statement->fetch()) {
                throw new DomainException('Choose a valid academic year.');
            }
            if ($data['term_id'] !== null) {
                $statement = $db->prepare(
                    'SELECT t.id FROM terms t JOIN academic_years y ON y.id = t.academic_year_id
                      WHERE t.id = ? AND y.school_id = ? AND t.academic_year_id = ?'
                );
                $statement->execute([$data['term_id'], $schoolId, $data['academic_year_id']]);
                if (!$statement->fetch()) {
                    throw new DomainException('Choose a term in the selected academic year.');
                }
            }

            $invoiceNumber = 'INV-' . date('Ymd-His') . '-' . strtoupper(bin2hex(random_bytes(3)));
            $db->prepare(
                "INSERT INTO fee_invoices
                    (invoice_number, school_id, student_id, academic_year_id, term_id, issue_date, due_date,
                     subtotal, total_amount, balance, currency, status, notes, generated_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'UGX', 'unpaid', ?, ?)"
            )->execute([
                $invoiceNumber, $schoolId, $data['student_id'], $data['academic_year_id'], $data['term_id'],
                $data['issue_date'], $data['due_date'], $data['amount'], $data['amount'], $data['amount'],
                $data['notes'], $actor['id'] ?? null,
            ]);
            $id = (int)$db->lastInsertId();
            $db->prepare(
                "INSERT INTO fee_invoice_items (invoice_id, item_name, amount, net_amount, source, sort_order)
                 VALUES (?, ?, ?, ?, 'manual', 1)"
            )->execute([$id, $data['item_name'], $data['amount'], $data['amount']]);
            audit('finance', 'create', 'fee_invoices', $id, 'Created invoice ' . $invoiceNumber, null, [
                'amount' => $data['amount'], 'student_id' => $data['student_id'],
            ]);
            return $id;
        });
    }

    public static function recordPayment(int $schoolId, array $data, ?array $actor): int
    {
        return static::transaction(function (PDO $db) use ($schoolId, $data, $actor): int {
            $statement = $db->prepare(
                'SELECT i.id, i.student_id, i.balance FROM fee_invoices i
                  WHERE i.id = ? AND i.school_id = ? AND i.status IN (?, ?) FOR UPDATE'
            );
            $statement->execute([$data['invoice_id'], $schoolId, 'unpaid', 'part_paid']);
            $invoice = $statement->fetch();
            if (!$invoice) {
                throw new DomainException('Choose an unpaid invoice belonging to this school.');
            }
            $statement = $db->prepare(
                "SELECT COALESCE(SUM(amount_local), 0) FROM payments
                  WHERE invoice_id = ? AND school_id = ? AND status = 'pending'"
            );
            $statement->execute([$data['invoice_id'], $schoolId]);
            $available = (float)$invoice['balance'] - (float)$statement->fetchColumn();
            if ($data['amount'] > $available) {
                throw new DomainException('The payment exceeds the invoice balance available after pending payments.');
            }
            if ($data['payment_method_id'] !== null) {
                $statement = $db->prepare('SELECT channel FROM payment_methods WHERE id = ? AND school_id = ? AND is_active = 1');
                $statement->execute([$data['payment_method_id'], $schoolId]);
                $method = $statement->fetch();
                if (!$method) {
                    throw new DomainException('Choose an active payment method for this school.');
                }
                $data['channel'] = $method['channel'];
            }

            $reference = 'PAY-' . date('Ymd-His') . '-' . strtoupper(bin2hex(random_bytes(3)));
            $db->prepare(
                'INSERT INTO payments
                    (payment_reference, school_id, student_id, invoice_id, payment_method_id, channel, amount,
                     currency, exchange_rate, amount_local, payment_date, external_reference, payer_name,
                     payer_phone, received_by, status, notes)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $reference, $schoolId, $invoice['student_id'], $data['invoice_id'], $data['payment_method_id'],
                $data['channel'], $data['amount'], 'UGX', $data['amount'], $data['payment_date'],
                $data['external_reference'], $data['payer_name'], $data['payer_phone'], $actor['id'] ?? null,
                'pending', $data['notes'],
            ]);
            $id = (int)$db->lastInsertId();
            audit('finance', 'create', 'payments', $id, 'Recorded payment ' . $reference . ' awaiting verification', null, [
                'amount' => $data['amount'], 'invoice_id' => $data['invoice_id'],
            ]);
            return $id;
        });
    }

    public static function verifyPayment(int $schoolId, int $paymentId, string $result, string $comments, ?array $actor): void
    {
        static::transaction(function (PDO $db) use ($schoolId, $paymentId, $result, $comments, $actor): void {
            $statement = $db->prepare('SELECT * FROM payments WHERE id = ? AND school_id = ? FOR UPDATE');
            $statement->execute([$paymentId, $schoolId]);
            $payment = $statement->fetch();
            if (!$payment || $payment['status'] !== 'pending') {
                throw new DomainException('Only a pending payment from this school can be verified.');
            }
            if ($result === 'confirmed') {
                if ($payment['invoice_id'] !== null) {
                    $statement = $db->prepare('SELECT * FROM fee_invoices WHERE id = ? AND school_id = ? FOR UPDATE');
                    $statement->execute([$payment['invoice_id'], $schoolId]);
                    $invoice = $statement->fetch();
                    if (!$invoice || (float)$payment['amount_local'] > (float)$invoice['balance']) {
                        throw new DomainException('The invoice balance no longer covers this payment.');
                    }
                    $db->prepare(
                        "UPDATE fee_invoices
                            SET paid_amount = paid_amount + ?,
                                balance = GREATEST(total_amount - (paid_amount + ?), 0),
                                status = CASE WHEN total_amount <= paid_amount + ? THEN 'paid' ELSE 'part_paid' END
                          WHERE id = ? AND school_id = ?"
                    )->execute([$payment['amount_local'], $payment['amount_local'], $payment['amount_local'], $invoice['id'], $schoolId]);
                }
                $db->prepare(
                    "UPDATE payments SET status = 'verified', verified_by = ?, verified_at = NOW(), posted_at = NOW()
                      WHERE id = ? AND school_id = ?"
                )->execute([$actor['id'] ?? null, $paymentId, $schoolId]);
                $receiptNumber = 'RCT-' . date('Ymd-His') . '-' . strtoupper(bin2hex(random_bytes(3)));
                $db->prepare(
                    "INSERT INTO receipts (receipt_number, school_id, payment_id, student_id, amount, method_label,
                                           narrative, issued_by, delivery_status)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'printed')"
                )->execute([
                    $receiptNumber, $schoolId, $paymentId, $payment['student_id'], $payment['amount_local'],
                    $payment['channel'], $comments !== '' ? $comments : 'Verified fee payment', $actor['id'] ?? null,
                ]);
            } else {
                $db->prepare(
                    "UPDATE payments SET status = 'rejected', verified_by = ?, verified_at = NOW(), notes = ?
                      WHERE id = ? AND school_id = ?"
                )->execute([$actor['id'] ?? null, $comments ?: $payment['notes'], $paymentId, $schoolId]);
            }
            $db->prepare(
                'INSERT INTO payment_verifications (payment_id, verified_by, result, comments) VALUES (?, ?, ?, ?)'
            )->execute([$paymentId, $actor['id'] ?? 0, $result, $comments ?: null]);
            audit('finance', 'verify', 'payments', $paymentId, 'Payment ' . $result . ($result === 'confirmed' ? '; receipt issued' : ''), [
                'status' => 'pending',
            ], ['status' => $result === 'confirmed' ? 'verified' : 'rejected']);
        });
    }

    public static function saveGateway(int $schoolId, array $data): int
    {
        return static::transaction(function (PDO $db) use ($schoolId, $data): int {
            $db->prepare(
                'INSERT INTO gateway_configs
                    (school_id, provider_code, provider_name, provider_type, currency, environment, is_active, notes)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE provider_name = VALUES(provider_name), provider_type = VALUES(provider_type),
                    currency = VALUES(currency), environment = VALUES(environment), is_active = VALUES(is_active), notes = VALUES(notes)'
            )->execute([
                $schoolId, $data['provider_code'], $data['provider_name'], $data['provider_type'],
                $data['currency'], $data['environment'], $data['is_active'], $data['notes'],
            ]);
            $id = (int)$db->lastInsertId();
            if ($id === 0) {
                $statement = $db->prepare('SELECT id FROM gateway_configs WHERE school_id = ? AND provider_code = ?');
                $statement->execute([$schoolId, $data['provider_code']]);
                $id = (int)$statement->fetchColumn();
            }
            audit('online_payments', 'configure', 'gateway_configs', $id, 'Saved sandbox gateway configuration ' . $data['provider_code']);
            return $id;
        });
    }

    public static function setGatewayActive(int $schoolId, int $gatewayId, bool $active): void
    {
        $changed = static::execute('UPDATE gateway_configs SET is_active = ? WHERE id = ? AND school_id = ?', [
            $active ? 1 : 0, $gatewayId, $schoolId,
        ]);
        if (!$changed) {
            throw new DomainException('Gateway configuration was not found for this school.');
        }
        audit('online_payments', 'update', 'gateway_configs', $gatewayId, $active ? 'Activated gateway config' : 'Deactivated gateway config');
    }

    public static function logGatewayTransaction(int $schoolId, array $data): int
    {
        return static::transaction(function (PDO $db) use ($schoolId, $data): int {
            $statement = $db->prepare('SELECT id FROM gateway_configs WHERE id = ? AND school_id = ? AND is_active = 1');
            $statement->execute([$data['gateway_config_id'], $schoolId]);
            if (!$statement->fetch()) {
                throw new DomainException('Choose an active gateway configuration for this school.');
            }
            if ($data['invoice_id'] !== null) {
                $statement = $db->prepare('SELECT student_id, balance FROM fee_invoices WHERE id = ? AND school_id = ?');
                $statement->execute([$data['invoice_id'], $schoolId]);
                $invoice = $statement->fetch();
                if (!$invoice) {
                    throw new DomainException('Choose an invoice from this school.');
                }
                if ($data['student_id'] !== null && (int)$data['student_id'] !== (int)$invoice['student_id']) {
                    throw new DomainException('The selected student does not match the invoice.');
                }
                $data['student_id'] = (int)$invoice['student_id'];
            } elseif ($data['student_id'] !== null) {
                $statement = $db->prepare('SELECT id FROM students WHERE id = ? AND school_id = ?');
                $statement->execute([$data['student_id'], $schoolId]);
                if (!$statement->fetch()) {
                    throw new DomainException('Choose a student from this school.');
                }
            }
            $db->prepare(
                'INSERT INTO gateway_transactions
                    (school_id, gateway_config_id, provider_txn_id, invoice_id, student_id, amount, currency,
                     payer_msisdn, payer_name, purpose, status, failure_reason, payload_request, payload_response)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $schoolId, $data['gateway_config_id'], $data['provider_txn_id'], $data['invoice_id'],
                $data['student_id'], $data['amount'], $data['currency'], $data['payer_msisdn'], $data['payer_name'],
                'fees', $data['status'], $data['failure_reason'],
                json_encode(['mode' => 'manual_sandbox_log'], JSON_THROW_ON_ERROR),
                json_encode(['network_call' => false], JSON_THROW_ON_ERROR),
            ]);
            $id = (int)$db->lastInsertId();
            audit('online_payments', 'log', 'gateway_transactions', $id, 'Logged ' . $data['status'] . ' sandbox gateway transaction');
            return $id;
        });
    }

    public static function updateGatewayTransaction(int $schoolId, int $transactionId, string $status, string $note, ?array $actor): void
    {
        static::transaction(function (PDO $db) use ($schoolId, $transactionId, $status, $note, $actor): void {
            $statement = $db->prepare('SELECT status FROM gateway_transactions WHERE id = ? AND school_id = ? FOR UPDATE');
            $statement->execute([$transactionId, $schoolId]);
            $oldStatus = $statement->fetchColumn();
            if ($oldStatus === false) {
                throw new DomainException('Gateway transaction not found for this school.');
            }
            $db->prepare(
                'UPDATE gateway_transactions SET status = ?, resolution_note = ?, resolved_by = ?, resolved_at = NOW()
                  WHERE id = ? AND school_id = ?'
            )->execute([$status, $note ?: null, $actor['id'] ?? null, $transactionId, $schoolId]);
            audit('online_payments', 'monitor', 'gateway_transactions', $transactionId, 'Updated sandbox transaction monitoring status', [
                'status' => $oldStatus,
            ], ['status' => $status, 'note' => $note]);
        });
    }

    public static function saveGrant(int $schoolId, array $data, ?array $actor): int
    {
        return static::transaction(function (PDO $db) use ($schoolId, $data, $actor): int {
            $statement = $db->prepare('SELECT id FROM academic_years WHERE id = ? AND school_id = ?');
            $statement->execute([$data['academic_year_id'], $schoolId]);
            if (!$statement->fetch()) {
                throw new DomainException('Choose an academic year from this school.');
            }
            if ($data['term_id'] !== null) {
                $statement = $db->prepare(
                    'SELECT t.id FROM terms t JOIN academic_years y ON y.id = t.academic_year_id
                      WHERE t.id = ? AND y.school_id = ? AND t.academic_year_id = ?'
                );
                $statement->execute([$data['term_id'], $schoolId, $data['academic_year_id']]);
                if (!$statement->fetch()) {
                    throw new DomainException('Choose a term in the selected academic year.');
                }
            }
            $status = $data['amount_received'] >= $data['amount_expected'] && $data['amount_received'] > 0
                ? 'received'
                : ($data['amount_received'] > 0 ? 'partially_received' : 'expected');
            $db->prepare(
                'INSERT INTO capitation_grants
                    (school_id, grant_type, academic_year_id, term_id, amount_expected, amount_received,
                     beneficiary_count, per_learner_rate, received_date, reference_number, received_by, status, notes)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $schoolId, $data['grant_type'], $data['academic_year_id'], $data['term_id'],
                $data['amount_expected'], $data['amount_received'], $data['beneficiary_count'],
                $data['per_learner_rate'], $data['received_date'], $data['reference_number'],
                $actor['id'] ?? null, $status, $data['notes'],
            ]);
            $id = (int)$db->lastInsertId();
            audit('compliance', 'create', 'capitation_grants', $id, 'Recorded ' . $data['grant_type'] . ' capitation grant', null, [
                'expected' => $data['amount_expected'], 'received' => $data['amount_received'],
            ]);
            return $id;
        });
    }

    public static function addGrantExpenditure(int $schoolId, array $data, ?array $actor): int
    {
        return static::transaction(function (PDO $db) use ($schoolId, $data, $actor): int {
            $statement = $db->prepare('SELECT * FROM capitation_grants WHERE id = ? AND school_id = ? FOR UPDATE');
            $statement->execute([$data['grant_id'], $schoolId]);
            $grant = $statement->fetch();
            if (!$grant) {
                throw new DomainException('Grant not found for this school.');
            }
            if ($data['amount'] > (float)$grant['amount_received'] - (float)$grant['amount_spent']) {
                throw new DomainException('Expenditure exceeds the grant funds received and not yet spent.');
            }
            $db->prepare(
                'INSERT INTO grant_expenditures
                    (grant_id, category, description, amount, spent_date, beneficiary_summary, approved_by, notes)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $data['grant_id'], $data['category'], $data['description'], $data['amount'],
                $data['spent_date'], $data['beneficiary_summary'], $actor['id'] ?? null, $data['notes'],
            ]);
            $id = (int)$db->lastInsertId();
            $spent = (float)$grant['amount_spent'] + $data['amount'];
            $allocated = max((float)$grant['amount_allocated'], $spent);
            $allocationStatus = $allocated >= (float)$grant['amount_received'] ? 'fully_allocated' :
                ($allocated > 0 ? 'partially_allocated' : 'unallocated');
            $db->prepare(
                'UPDATE capitation_grants SET amount_spent = ?, amount_allocated = ?, allocation_status = ?
                  WHERE id = ? AND school_id = ?'
            )->execute([$spent, $allocated, $allocationStatus, $data['grant_id'], $schoolId]);
            audit('compliance', 'create', 'grant_expenditures', $id, 'Recorded capitation grant expenditure', null, [
                'grant_id' => $data['grant_id'], 'amount' => $data['amount'], 'category' => $data['category'],
            ]);
            return $id;
        });
    }

    public static function saveEmisReturn(int $schoolId, array $data, ?array $actor): int
    {
        return static::transaction(function (PDO $db) use ($schoolId, $data, $actor): int {
            $statement = $db->prepare('SELECT id FROM academic_years WHERE id = ? AND school_id = ?');
            $statement->execute([$data['academic_year_id'], $schoolId]);
            if (!$statement->fetch()) {
                throw new DomainException('Choose an academic year from this school.');
            }
            if ($data['term_id'] !== null) {
                $statement = $db->prepare(
                    'SELECT t.id FROM terms t JOIN academic_years y ON y.id = t.academic_year_id
                      WHERE t.id = ? AND y.school_id = ? AND t.academic_year_id = ?'
                );
                $statement->execute([$data['term_id'], $schoolId, $data['academic_year_id']]);
                if (!$statement->fetch()) {
                    throw new DomainException('Choose a term in the selected academic year.');
                }
            }
            $dataSnapshot = json_encode([
                'total_learners' => $data['total_learners'],
                'total_teachers' => $data['total_teachers'],
                'total_support_staff' => $data['total_support_staff'],
            ], JSON_THROW_ON_ERROR);
            $db->prepare(
                "INSERT INTO emis_returns
                    (school_id, academic_year_id, term_id, return_type, period_label, data_snapshot,
                     total_learners, total_teachers, total_support_staff, status, prepared_by, prepared_at, remarks)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', ?, NOW(), ?)"
            )->execute([
                $schoolId, $data['academic_year_id'], $data['term_id'], $data['return_type'], $data['period_label'],
                $dataSnapshot, $data['total_learners'], $data['total_teachers'], $data['total_support_staff'],
                $actor['id'] ?? null, $data['remarks'],
            ]);
            $id = (int)$db->lastInsertId();
            audit('compliance', 'create', 'emis_returns', $id, 'Prepared government return ' . $data['period_label']);
            return $id;
        });
    }

    public static function updateEmisStatus(int $schoolId, int $returnId, string $status, string $remarks, ?array $actor): void
    {
        static::transaction(function (PDO $db) use ($schoolId, $returnId, $status, $remarks, $actor): void {
            $statement = $db->prepare('SELECT status FROM emis_returns WHERE id = ? AND school_id = ? FOR UPDATE');
            $statement->execute([$returnId, $schoolId]);
            $oldStatus = $statement->fetchColumn();
            if ($oldStatus === false) {
                throw new DomainException('Government return not found for this school.');
            }
            $transitions = [
                'draft' => ['validated'],
                'validated' => ['submitted'],
                'submitted' => ['accepted', 'rejected', 'returned_for_correction'],
                'returned_for_correction' => ['validated'],
                'rejected' => ['draft'],
                'accepted' => [],
            ];
            if (!in_array($status, $transitions[$oldStatus] ?? [], true)) {
                throw new DomainException('That government-return status transition is not allowed.');
            }
            $actorColumn = $status === 'validated' ? 'validated_by' : ($status === 'submitted' ? 'submitted_by' : null);
            $timestampColumn = $status === 'validated' ? 'validated_at' : ($status === 'submitted' ? 'submitted_at' : null);
            $sql = 'UPDATE emis_returns SET status = ?, remarks = ?';
            $params = [$status, $remarks ?: null];
            if ($actorColumn !== null) {
                $sql .= ', ' . $actorColumn . ' = ?, ' . $timestampColumn . ' = NOW()';
                $params[] = $actor['id'] ?? null;
            }
            $sql .= ' WHERE id = ? AND school_id = ?';
            $params[] = $returnId;
            $params[] = $schoolId;
            $db->prepare($sql)->execute($params);
            audit('compliance', 'update', 'emis_returns', $returnId, 'Updated government return from ' . $oldStatus . ' to ' . $status);
        });
    }

    public static function classes(int $schoolId): array
    {
        return static::query(
            'SELECT id, name, level FROM classes WHERE school_id = ? AND is_active = 1 ORDER BY order_index, name',
            [$schoolId]
        );
    }

    public static function structures(int $schoolId): array
    {
        return static::query(
            'SELECT fs.*, y.name AS year_name, t.name AS term_name, c.name AS class_name,
                    (SELECT COUNT(*) FROM fee_structure_items i WHERE i.fee_structure_id = fs.id) AS item_count
               FROM fee_structures fs
               JOIN academic_years y ON y.id = fs.academic_year_id
               LEFT JOIN terms t ON t.id = fs.term_id
               LEFT JOIN classes c ON c.id = fs.class_id
              WHERE fs.school_id = ?
              ORDER BY fs.id DESC LIMIT 100',
            [$schoolId]
        );
    }

    public static function structureItems(int $schoolId): array
    {
        return static::query(
            'SELECT i.*, fs.name AS structure_name, fs.status AS structure_status
               FROM fee_structure_items i
               JOIN fee_structures fs ON fs.id = i.fee_structure_id
              WHERE fs.school_id = ?
              ORDER BY fs.id DESC, i.sort_order, i.id LIMIT 300',
            [$schoolId]
        );
    }

    public static function saveStructure(int $schoolId, array $data, ?array $actor): int
    {
        return static::transaction(function (PDO $db) use ($schoolId, $data, $actor): int {
            $statement = $db->prepare('SELECT id FROM academic_years WHERE id = ? AND school_id = ?');
            $statement->execute([$data['academic_year_id'], $schoolId]);
            if (!$statement->fetch()) {
                throw new DomainException('Choose an academic year from this school.');
            }
            if ($data['term_id'] !== null) {
                $statement = $db->prepare(
                    'SELECT t.id FROM terms t JOIN academic_years y ON y.id = t.academic_year_id
                      WHERE t.id = ? AND y.school_id = ? AND t.academic_year_id = ?'
                );
                $statement->execute([$data['term_id'], $schoolId, $data['academic_year_id']]);
                if (!$statement->fetch()) {
                    throw new DomainException('Choose a term in the selected academic year.');
                }
            }
            if ($data['class_id'] !== null) {
                $statement = $db->prepare('SELECT level FROM classes WHERE id = ? AND school_id = ?');
                $statement->execute([$data['class_id'], $schoolId]);
                $class = $statement->fetch();
                if (!$class) {
                    throw new DomainException('Choose a class from this school.');
                }
                if ($data['class_level'] !== 'all' && $class['level'] !== $data['class_level']) {
                    throw new DomainException('The selected class does not match the chosen class level.');
                }
            }
            $db->prepare(
                'INSERT INTO fee_structures
                    (school_id, name, academic_year_id, term_id, class_level, class_id, student_category,
                     total_amount, effective_from, status, description, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?)'
            )->execute([
                $schoolId, $data['name'], $data['academic_year_id'], $data['term_id'], $data['class_level'],
                $data['class_id'], $data['student_category'], $data['effective_from'], $data['status'],
                $data['description'], $actor['id'] ?? null,
            ]);
            $id = (int)$db->lastInsertId();
            audit('finance', 'create', 'fee_structures', $id, 'Created fee structure ' . $data['name'], null, [
                'academic_year_id' => $data['academic_year_id'], 'status' => $data['status'],
            ]);
            return $id;
        });
    }

    public static function addStructureItem(int $schoolId, array $data): int
    {
        return static::transaction(function (PDO $db) use ($schoolId, $data): int {
            $statement = $db->prepare('SELECT status FROM fee_structures WHERE id = ? AND school_id = ? FOR UPDATE');
            $statement->execute([$data['fee_structure_id'], $schoolId]);
            $status = $statement->fetchColumn();
            if ($status === false) {
                throw new DomainException('Fee structure not found for this school.');
            }
            if ($status === 'archived') {
                throw new DomainException('Items cannot be added to an archived fee structure.');
            }
            $db->prepare(
                'INSERT INTO fee_structure_items
                    (fee_structure_id, item_name, item_code, amount, is_mandatory, applies_to, sort_order)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $data['fee_structure_id'], $data['item_name'], $data['item_code'], $data['amount'],
                $data['is_mandatory'], $data['applies_to'], $data['sort_order'],
            ]);
            $id = (int)$db->lastInsertId();
            $db->prepare(
                'UPDATE fee_structures fs
                    SET total_amount = (SELECT COALESCE(SUM(amount), 0)
                                          FROM fee_structure_items WHERE fee_structure_id = fs.id)
                  WHERE fs.id = ? AND fs.school_id = ?'
            )->execute([$data['fee_structure_id'], $schoolId]);
            audit('finance', 'create', 'fee_structure_items', $id, 'Added fee item ' . $data['item_name'], null, [
                'fee_structure_id' => $data['fee_structure_id'], 'amount' => $data['amount'],
            ]);
            return $id;
        });
    }

    public static function setStructureStatus(int $schoolId, int $structureId, string $status, ?array $actor): void
    {
        static::transaction(function (PDO $db) use ($schoolId, $structureId, $status, $actor): void {
            $statement = $db->prepare('SELECT status, name FROM fee_structures WHERE id = ? AND school_id = ? FOR UPDATE');
            $statement->execute([$structureId, $schoolId]);
            $structure = $statement->fetch();
            if (!$structure) {
                throw new DomainException('Fee structure not found for this school.');
            }
            if ($status === 'active' && $structure['status'] !== 'active') {
                $statement = $db->prepare('SELECT COUNT(*) FROM fee_structure_items WHERE fee_structure_id = ?');
                $statement->execute([$structureId]);
                if ((int)$statement->fetchColumn() < 1) {
                    throw new DomainException('Add at least one fee item before activating this structure.');
                }
            }
            $db->prepare(
                'UPDATE fee_structures SET status = ?, updated_at = NOW() WHERE id = ? AND school_id = ?'
            )->execute([$status, $structureId, $schoolId]);
            audit('finance', 'update', 'fee_structures', $structureId,
                'Changed fee structure ' . $structure['name'] . ' status from ' . $structure['status'] . ' to ' . $status,
                ['status' => $structure['status']], ['status' => $status]);
        });
    }

    public static function bulkInvoice(int $schoolId, array $data, ?array $actor): array
    {
        return static::transaction(function (PDO $db) use ($schoolId, $data, $actor): array {
            $statement = $db->prepare(
                'SELECT fs.*, y.start_date AS year_start_date
                   FROM fee_structures fs
                   JOIN academic_years y ON y.id = fs.academic_year_id
                  WHERE fs.id = ? AND fs.school_id = ? FOR UPDATE'
            );
            $statement->execute([$data['fee_structure_id'], $schoolId]);
            $structure = $statement->fetch();
            if (!$structure) {
                throw new DomainException('Fee structure not found for this school.');
            }
            if ($structure['status'] !== 'active') {
                throw new DomainException('Only an active fee structure can be used for bulk invoicing.');
            }
            if ($structure['effective_from'] !== null && $data['issue_date'] < $structure['effective_from']) {
                throw new DomainException(
                    'The issue date cannot be before the structure effective date of ' . $structure['effective_from'] . '.'
                );
            }

            $statement = $db->prepare(
                'SELECT item_name, amount, applies_to, sort_order FROM fee_structure_items
                  WHERE fee_structure_id = ? ORDER BY sort_order, id'
            );
            $statement->execute([$structure['id']]);
            $items = $statement->fetchAll();
            if (!$items) {
                throw new DomainException('Add at least one fee item before generating bulk invoices.');
            }

            $classIds = [];
            if ($data['class_id'] !== null) {
                $statement = $db->prepare('SELECT id, level FROM classes WHERE id = ? AND school_id = ? AND is_active = 1');
                $statement->execute([$data['class_id'], $schoolId]);
                $class = $statement->fetch();
                if (!$class) {
                    throw new DomainException('Choose an active class from this school.');
                }
                if ($structure['class_id'] !== null && (int)$structure['class_id'] !== $data['class_id']) {
                    throw new DomainException('The chosen class does not match the class scope of the fee structure.');
                }
                if ($structure['class_level'] !== 'all' && $class['level'] !== $structure['class_level']) {
                    throw new DomainException('The chosen class level does not match the fee structure.');
                }
                $classIds = [$data['class_id']];
            } elseif ($structure['class_id'] !== null) {
                $classIds = [(int)$structure['class_id']];
            } elseif ($structure['class_level'] !== 'all') {
                $statement = $db->prepare('SELECT id FROM classes WHERE school_id = ? AND level = ? AND is_active = 1');
                $statement->execute([$schoolId, $structure['class_level']]);
                $classIds = array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
                if (!$classIds) {
                    throw new DomainException('No active classes match the fee structure level in this school.');
                }
            }

            $sql = "SELECT s.id, s.student_type FROM students s WHERE s.school_id = ? AND s.status = 'active'";
            $params = [$schoolId];
            if ($classIds) {
                $sql .= ' AND s.current_class_id IN (' . implode(',', array_fill(0, count($classIds), '?')) . ')';
                $params = array_merge($params, $classIds);
            }
            switch ($structure['student_category']) {
                case 'day':
                case 'boarding':
                    $sql .= ' AND s.student_type = ?';
                    $params[] = $structure['student_category'];
                    break;
                case 'new':
                    $sql .= ' AND ((s.admission_date IS NOT NULL AND s.admission_date >= ?)
                                 OR (s.admission_date IS NULL AND COALESCE(s.academic_year_id, 0) = ?))';
                    $params[] = $structure['year_start_date'];
                    $params[] = (int)$structure['academic_year_id'];
                    break;
                case 'continuing':
                    $sql .= ' AND ((s.admission_date IS NOT NULL AND s.admission_date < ?)
                                 OR (s.admission_date IS NULL AND COALESCE(s.academic_year_id, 0) <> ?))';
                    $params[] = $structure['year_start_date'];
                    $params[] = (int)$structure['academic_year_id'];
                    break;
            }
            $sql .= ' ORDER BY s.id LIMIT ' . (self::BULK_INVOICE_LIMIT + 1);
            $statement = $db->prepare($sql);
            $statement->execute($params);
            $students = $statement->fetchAll();

            $truncated = count($students) > self::BULK_INVOICE_LIMIT;
            if ($truncated) {
                $students = array_slice($students, 0, self::BULK_INVOICE_LIMIT);
            }

            $statement = $db->prepare('SELECT student_id FROM fee_invoices WHERE fee_structure_id = ?');
            $statement->execute([$structure['id']]);
            $alreadyInvoiced = array_flip(array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN)));

            $insertInvoice = $db->prepare(
                "INSERT INTO fee_invoices
                    (invoice_number, school_id, student_id, academic_year_id, term_id, fee_structure_id,
                     issue_date, due_date, subtotal, total_amount, balance, currency, status, notes, generated_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'UGX', 'unpaid', ?, ?)"
            );
            $insertItem = $db->prepare(
                "INSERT INTO fee_invoice_items
                    (invoice_id, item_name, amount, net_amount, source, sort_order)
                 VALUES (?, ?, ?, ?, 'structure', ?)"
            );

            $created = 0;
            $skipped = 0;
            foreach ($students as $student) {
                if (isset($alreadyInvoiced[(int)$student['id']])) {
                    $skipped++;
                    continue;
                }
                $applicable = array_values(array_filter(
                    $items,
                    static fn (array $item): bool => $item['applies_to'] === 'all'
                        || $item['applies_to'] === $student['student_type']
                ));
                if (!$applicable) {
                    $skipped++;
                    continue;
                }
                $subtotal = 0.0;
                foreach ($applicable as $item) {
                    $subtotal += (float)$item['amount'];
                }
                $subtotal = round($subtotal, 2);
                $invoiceNumber = 'INV-' . date('Ymd-His') . '-' . strtoupper(bin2hex(random_bytes(4)));
                $insertInvoice->execute([
                    $invoiceNumber, $schoolId, $student['id'], $structure['academic_year_id'], $structure['term_id'],
                    $structure['id'], $data['issue_date'], $data['due_date'], $subtotal, $subtotal, $subtotal,
                    $data['notes'], $actor['id'] ?? null,
                ]);
                $invoiceId = (int)$db->lastInsertId();
                foreach ($applicable as $item) {
                    $insertItem->execute([$invoiceId, $item['item_name'], $item['amount'], $item['amount'], $item['sort_order']]);
                }
                $created++;
            }

            audit('finance', 'create', 'fee_invoices', null,
                'Bulk generated ' . $created . ' invoice(s) from fee structure ' . $structure['name'],
                null, ['fee_structure_id' => (int)$structure['id'], 'created' => $created, 'skipped' => $skipped]);

            return ['created' => $created, 'skipped' => $skipped, 'truncated' => $truncated];
        });
    }

    public static function balances(int $schoolId, ?int $yearId, bool $includeAll): array
    {
        $sql = 'SELECT s.id, s.admission_number, s.first_name, s.last_name, c.name AS class_name,
                       COUNT(i.id) AS invoice_count,
                       COALESCE(SUM(i.total_amount), 0) AS total_invoiced,
                       COALESCE(SUM(i.paid_amount), 0) AS total_paid,
                       COALESCE(SUM(i.balance), 0) AS balance,
                       MIN(i.due_date) AS earliest_due_date,
                       MAX(CASE WHEN i.balance > 0 AND i.due_date IS NOT NULL AND i.due_date < CURDATE()
                                THEN DATEDIFF(CURDATE(), i.due_date) END) AS days_overdue
                  FROM students s
                  LEFT JOIN fee_invoices i
                    ON i.student_id = s.id AND i.school_id = ? AND i.status IN (?, ?, ?)';
        $params = [$schoolId, 'unpaid', 'part_paid', 'paid'];
        if ($yearId !== null) {
            $sql .= ' AND i.academic_year_id = ?';
            $params[] = $yearId;
        }
        $sql .= " LEFT JOIN classes c ON c.id = s.current_class_id
                  WHERE s.school_id = ? AND s.status = 'active'
                  GROUP BY s.id, s.admission_number, s.first_name, s.last_name, c.name";
        $params[] = $schoolId;
        if (!$includeAll) {
            $sql .= ' HAVING balance > 0';
        }
        $sql .= ' ORDER BY balance DESC, total_invoiced DESC, s.last_name, s.first_name LIMIT 200';
        return static::query($sql, $params);
    }

    public static function clearances(int $schoolId): array
    {
        return static::query(
            'SELECT cl.*, s.admission_number, s.first_name, s.last_name, c.name AS class_name,
                    y.name AS year_name, t.name AS term_name,
                    cb.full_name AS checked_by_name, ab.full_name AS approved_by_name
               FROM clearances cl
               JOIN students s ON s.id = cl.student_id
               JOIN academic_years y ON y.id = cl.academic_year_id
               LEFT JOIN terms t ON t.id = cl.term_id
               LEFT JOIN classes c ON c.id = s.current_class_id
               LEFT JOIN users cb ON cb.id = cl.checked_by
               LEFT JOIN users ab ON ab.id = cl.approved_by
              WHERE s.school_id = ?
              ORDER BY cl.updated_at DESC, cl.id DESC LIMIT 200',
            [$schoolId]
        );
    }

    public static function saveClearance(int $schoolId, array $data, ?array $actor): int
    {
        return static::transaction(function (PDO $db) use ($schoolId, $data, $actor): int {
            $statement = $db->prepare(
                "SELECT id FROM students WHERE id = ? AND school_id = ? AND status = 'active' FOR UPDATE"
            );
            $statement->execute([$data['student_id'], $schoolId]);
            if (!$statement->fetch()) {
                throw new DomainException('Choose an active student from this school.');
            }
            $statement = $db->prepare('SELECT id FROM academic_years WHERE id = ? AND school_id = ?');
            $statement->execute([$data['academic_year_id'], $schoolId]);
            if (!$statement->fetch()) {
                throw new DomainException('Choose an academic year from this school.');
            }
            if ($data['term_id'] !== null) {
                $statement = $db->prepare(
                    'SELECT t.id FROM terms t JOIN academic_years y ON y.id = t.academic_year_id
                      WHERE t.id = ? AND y.school_id = ? AND t.academic_year_id = ?'
                );
                $statement->execute([$data['term_id'], $schoolId, $data['academic_year_id']]);
                if (!$statement->fetch()) {
                    throw new DomainException('Choose a term in the selected academic year.');
                }
            }

            $balance = self::outstandingBalance(
                $db, $schoolId, (int)$data['student_id'], (int)$data['academic_year_id'], $data['term_id']
            );
            $approved = in_array($data['status'], ['cleared', 'exception_granted'], true);
            $userId = $actor['id'] ?? null;

            $statement = $db->prepare(
                'SELECT id FROM clearances
                  WHERE student_id = ? AND academic_year_id = ? AND clearance_type = ? AND term_id <=> ? FOR UPDATE'
            );
            $statement->execute([$data['student_id'], $data['academic_year_id'], $data['clearance_type'], $data['term_id']]);
            $existing = $statement->fetchColumn();

            if ($existing) {
                $id = (int)$existing;
                $db->prepare(
                    'UPDATE clearances
                        SET status = ?, outstanding_balance = ?, reason = ?, policy_note = ?, exception_reason = ?,
                            checked_by = ?, checked_at = NOW(),
                            approved_by = ?, approved_at = ?
                      WHERE id = ?'
                )->execute([
                    $data['status'], $balance, $data['reason'], $data['policy_note'], $data['exception_reason'],
                    $userId, $approved ? $userId : null, $approved ? date('Y-m-d H:i:s') : null, $id,
                ]);
                $action = 'update';
            } else {
                $db->prepare(
                    'INSERT INTO clearances
                        (student_id, academic_year_id, term_id, clearance_type, status, outstanding_balance,
                         reason, policy_note, checked_by, checked_at, approved_by, approved_at, exception_reason)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?)'
                )->execute([
                    $data['student_id'], $data['academic_year_id'], $data['term_id'], $data['clearance_type'],
                    $data['status'], $balance, $data['reason'], $data['policy_note'], $userId,
                    $approved ? $userId : null, $approved ? date('Y-m-d H:i:s') : null, $data['exception_reason'],
                ]);
                $id = (int)$db->lastInsertId();
                $action = 'create';
            }

            audit('finance', $action, 'clearances', $id,
                'Saved ' . $data['clearance_type'] . ' clearance as ' . $data['status'], null, [
                    'student_id' => (int)$data['student_id'], 'status' => $data['status'],
                    'outstanding_balance' => $balance,
                ]);
            return $id;
        });
    }

    public static function approveClearance(
        int $schoolId, int $clearanceId, string $status, string $exceptionReason, ?array $actor
    ): void {
        static::transaction(function (PDO $db) use ($schoolId, $clearanceId, $status, $exceptionReason, $actor): void {
            $statement = $db->prepare(
                'SELECT cl.* FROM clearances cl
                   JOIN students s ON s.id = cl.student_id
                  WHERE cl.id = ? AND s.school_id = ? FOR UPDATE'
            );
            $statement->execute([$clearanceId, $schoolId]);
            $clearance = $statement->fetch();
            if (!$clearance) {
                throw new DomainException('Clearance record not found for this school.');
            }
            if (!in_array($clearance['status'], ['pending', 'blocked'], true)) {
                throw new DomainException('Only a pending or blocked clearance can be decided.');
            }
            $balance = self::outstandingBalance(
                $db, $schoolId, (int)$clearance['student_id'], (int)$clearance['academic_year_id'],
                $clearance['term_id'] !== null ? (int)$clearance['term_id'] : null
            );
            $userId = $actor['id'] ?? null;
            $db->prepare(
                'UPDATE clearances
                    SET status = ?, outstanding_balance = ?, approved_by = ?, approved_at = NOW(),
                        exception_reason = ?, checked_by = COALESCE(checked_by, ?), checked_at = COALESCE(checked_at, NOW())
                  WHERE id = ?'
            )->execute([
                $status, $balance, $userId, $exceptionReason !== '' ? $exceptionReason : $clearance['exception_reason'],
                $userId, $clearanceId,
            ]);
            audit('finance', 'approve', 'clearances', $clearanceId,
                'Clearance decision: ' . $clearance['status'] . ' to ' . $status, ['status' => $clearance['status']], [
                    'status' => $status, 'outstanding_balance' => $balance, 'exception_reason' => $exceptionReason ?: null,
                ]);
        });
    }

    public static function reminders(int $schoolId): array
    {
        return static::query(
            'SELECT r.*, s.admission_number, s.first_name, s.last_name,
                    i.invoice_number, y.name AS year_name, t.name AS term_name,
                    su.full_name AS sent_by_name,
                    COALESCE(NULLIF(g.full_name, ""), CONCAT(g.first_name, " ", g.last_name)) AS guardian_name
               FROM fee_reminders r
               JOIN students s ON s.id = r.student_id
               JOIN academic_years y ON y.id = r.academic_year_id
               LEFT JOIN terms t ON t.id = r.term_id
               LEFT JOIN fee_invoices i ON i.id = r.invoice_id
               LEFT JOIN guardians g ON g.id = r.guardian_id
               LEFT JOIN users su ON su.id = r.sent_by
              WHERE s.school_id = ?
              ORDER BY r.id DESC LIMIT 100',
            [$schoolId]
        );
    }

    public static function sendReminder(int $schoolId, array $data, ?array $actor): int
    {
        if (!in_array($data['channel'], ['sms', 'email', 'portal', 'print'], true)) {
            throw new DomainException('Choose a valid reminder channel.');
        }
        $delivery = ['channel' => (string)$data['channel'], 'phone' => '', 'email' => '', 'message' => ''];
        $id = static::transaction(function (PDO $db) use ($schoolId, $data, $actor, &$delivery): int {
            $statement = $db->prepare(
                "SELECT id, admission_number, first_name, last_name
                   FROM students WHERE id = ? AND school_id = ? AND status = 'active'"
            );
            $statement->execute([$data['student_id'], $schoolId]);
            $student = $statement->fetch();
            if (!$student) {
                throw new DomainException('Choose an active student from this school.');
            }
            $statement = $db->prepare('SELECT id FROM academic_years WHERE id = ? AND school_id = ?');
            $statement->execute([$data['academic_year_id'], $schoolId]);
            if (!$statement->fetch()) {
                throw new DomainException('Choose an academic year from this school.');
            }
            if ($data['term_id'] !== null) {
                $statement = $db->prepare(
                    'SELECT t.id FROM terms t JOIN academic_years y ON y.id = t.academic_year_id
                      WHERE t.id = ? AND y.school_id = ? AND t.academic_year_id = ?'
                );
                $statement->execute([$data['term_id'], $schoolId, $data['academic_year_id']]);
                if (!$statement->fetch()) {
                    throw new DomainException('Choose a term in the selected academic year.');
                }
            }

            $balance = 0.0;
            $dueDate = null;
            $invoiceId = null;
            $reference = null;
            if ($data['invoice_id'] !== null) {
                $statement = $db->prepare(
                    'SELECT id, student_id, balance, due_date, invoice_number, academic_year_id, term_id
                       FROM fee_invoices
                      WHERE id = ? AND school_id = ? AND status IN (?, ?) FOR UPDATE'
                );
                $statement->execute([$data['invoice_id'], $schoolId, 'unpaid', 'part_paid']);
                $invoice = $statement->fetch();
                if (!$invoice || (int)$invoice['student_id'] !== (int)$student['id']) {
                    throw new DomainException('Choose an unpaid invoice that belongs to this student.');
                }
                $balance = (float)$invoice['balance'];
                $dueDate = $invoice['due_date'];
                $invoiceId = (int)$invoice['id'];
                $reference = $invoice['invoice_number'];
            } else {
                $sql = 'SELECT COALESCE(SUM(balance), 0) AS total_balance, MIN(due_date) AS earliest_due
                          FROM fee_invoices
                         WHERE student_id = ? AND school_id = ? AND academic_year_id = ?
                           AND status IN (?, ?)';
                $params = [$student['id'], $schoolId, $data['academic_year_id'], 'unpaid', 'part_paid'];
                if ($data['term_id'] !== null) {
                    $sql .= ' AND term_id = ?';
                    $params[] = $data['term_id'];
                }
                $statement = $db->prepare($sql);
                $statement->execute($params);
                $totals = $statement->fetch();
                $balance = (float)($totals['total_balance'] ?? 0);
                $dueDate = $totals['earliest_due'] ?? null;
                $statement = $db->prepare(
                    'SELECT invoice_number FROM fee_invoices
                      WHERE student_id = ? AND school_id = ? AND academic_year_id = ? AND status IN (?, ?)
                      ORDER BY (due_date IS NULL), due_date, id LIMIT 1'
                );
                $statement->execute([$student['id'], $schoolId, $data['academic_year_id'], 'unpaid', 'part_paid']);
                $reference = $statement->fetchColumn() ?: null;
            }
            if ($balance <= 0) {
                throw new DomainException('This student has no outstanding fee balance for the selected period.');
            }

            $statement = $db->prepare(
                'SELECT g.id, g.phone, g.email,
                        COALESCE(NULLIF(g.full_name, ""), CONCAT(g.first_name, " ", g.last_name)) AS guardian_name
                   FROM student_guardians sg
                   JOIN guardians g ON g.id = sg.guardian_id
                  WHERE sg.student_id = ?
                  ORDER BY sg.is_fee_payer DESC, sg.is_primary DESC, g.id LIMIT 1'
            );
            $statement->execute([$student['id']]);
            $guardian = $statement->fetch();

            $message = $data['message'];
            if ($message === null || $message === '') {
                $message = self::reminderTemplate($db, $schoolId, $data['channel'], [
                    '{guardian_name}'     => $guardian ? $guardian['guardian_name'] : 'Parent/Guardian',
                    '{student_name}'      => $student['first_name'] . ' ' . $student['last_name'],
                    '{admission_number}'  => $student['admission_number'],
                    '{balance}'           => number_format($balance, 0, '.', ','),
                    '{due_date}'          => $dueDate ?: 'the due date',
                    '{payment_reference}' => $reference ?: 'the school office',
                    '{school_name}'       => school_row()['name'] ?? APP_NAME,
                ]);
            }
            $message = mb_substr($message, 0, 500);
            $delivery = [
                'channel' => (string)$data['channel'],
                'phone'   => (string)($guardian['phone'] ?? ''),
                'email'   => (string)($guardian['email'] ?? ''),
                'message' => $message,
            ];

            $db->prepare(
                "INSERT INTO fee_reminders
                    (student_id, guardian_id, invoice_id, academic_year_id, term_id, channel,
                     balance_at_time, message, sent_by, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'scheduled')"
            )->execute([
                $student['id'], $guardian ? (int)$guardian['id'] : null, $invoiceId,
                $data['academic_year_id'], $data['term_id'], $data['channel'],
                round($balance, 2), $message, $actor['id'] ?? null,
            ]);
            $id = (int)$db->lastInsertId();
            audit('finance', 'create', 'fee_reminders', $id,
                'Recorded a ' . $data['channel'] . ' fee reminder for ' . $student['admission_number'], null, [
                    'balance_at_time' => round($balance, 2), 'channel' => $data['channel'],
                ]);
            return $id;
        });

        if ($delivery['channel'] === 'sms' || $delivery['channel'] === 'email') {
            $destination = trim($delivery[$delivery['channel'] === 'sms' ? 'phone' : 'email']);
            $ok = false;
            $error = '';

            if ($destination === '') {
                $error = 'No ' . $delivery['channel'] . ' address on file for the guardian.';
            } elseif ($delivery['channel'] === 'sms') {
                $result = SmsService::send($destination, $delivery['message']);
                $ok = (bool)($result['ok'] ?? false);
                $error = $ok ? '' : (string)($result['error'] ?? 'SMS gateway rejected the message.');
            } else {
                $ok = MailService::send(
                    $destination,
                    'Fee balance reminder',
                    '<p>' . nl2br(e($delivery['message'])) . '</p>'
                );
                $error = $ok ? '' : 'Email delivery failed.';
            }

            Database::conn()->prepare(
                "UPDATE fee_reminders SET status = ?, sent_at = IF(? = 'sent', NOW(), NULL), error_note = ? WHERE id = ?"
            )->execute([
                $ok ? 'sent' : 'failed',
                $ok ? 'sent' : 'failed',
                $ok ? null : mb_substr($error, 0, 255),
                $id,
            ]);

            if (!$ok) {
                throw new DomainException('Reminder saved but delivery failed: ' . $error);
            }
        } else {
            Database::conn()->prepare(
                "UPDATE fee_reminders SET status = 'sent', sent_at = NOW() WHERE id = ?"
            )->execute([$id]);
        }

        return $id;
    }

    public static function receipts(int $schoolId): array
    {
        return static::query(
            'SELECT r.*, s.admission_number, s.first_name, s.last_name,
                    p.payment_reference, p.payment_date, p.channel, p.external_reference,
                    ru.full_name AS issued_by_name
               FROM receipts r
               JOIN students s ON s.id = r.student_id
               LEFT JOIN payments p ON p.id = r.payment_id
               LEFT JOIN users ru ON ru.id = r.issued_by
              WHERE r.school_id = ?
              ORDER BY r.id DESC LIMIT 100',
            [$schoolId]
        );
    }

    private static function outstandingBalance(
        PDO $db, int $schoolId, int $studentId, int $yearId, ?int $termId
    ): float {
        $sql = 'SELECT COALESCE(SUM(balance), 0) FROM fee_invoices
                 WHERE student_id = ? AND school_id = ? AND academic_year_id = ?
                   AND status IN (?, ?)';
        $params = [$studentId, $schoolId, $yearId, 'unpaid', 'part_paid'];
        if ($termId !== null) {
            $sql .= ' AND term_id = ?';
            $params[] = $termId;
        }
        $statement = $db->prepare($sql);
        $statement->execute($params);
        return round((float)$statement->fetchColumn(), 2);
    }

    private static function reminderTemplate(PDO $db, int $schoolId, string $channel, array $replacements): string
    {
        $templateChannel = in_array($channel, ['sms', 'email', 'portal'], true) ? $channel : 'sms';
        $statement = $db->prepare(
            'SELECT body FROM notification_templates
              WHERE school_id = ? AND template_code = ? AND channel = ? AND is_active = 1
              ORDER BY id LIMIT 1'
        );
        $statement->execute([$schoolId, 'fee_reminder', $templateChannel]);
        $body = $statement->fetchColumn();
        if (!$body) {
            $statement = $db->prepare(
                'SELECT body FROM notification_templates
                  WHERE school_id = ? AND template_code = ? AND is_active = 1 ORDER BY id LIMIT 1'
            );
            $statement->execute([$schoolId, 'fee_reminder']);
            $body = $statement->fetchColumn();
        }
        if (!$body) {
            $body = 'Dear {guardian_name}, {student_name} ({admission_number}) has an outstanding fee balance '
                . 'of UGX {balance}, due on {due_date}. Please pay at the school office or by mobile money '
                . 'quoting {payment_reference}. - {school_name}';
        }
        return strtr((string)$body, $replacements);
    }

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
}
