<?php
declare(strict_types=1);

final class CanteenController extends Controller
{
    public function index(): void
    {
        $schoolId = school_id();
        $this->render('canteen/index', [
            'pageTitle' => 'Canteen & shop',
            'activeNav' => 'canteen',
            'products'  => CanteenModule::products($schoolId),
            'canAdd'    => Auth::can('canteen', 'add'),
            'canEdit'   => Auth::can('canteen', 'edit'),
        ]);
    }

    public function storeProduct(): void
    {
        $name     = trim((string)($_POST['name'] ?? ''));
        $category = (string)($_POST['category'] ?? 'food');
        $unit     = trim((string)($_POST['unit'] ?? ''));
        $price    = (float)($_POST['selling_price'] ?? 0);
        $cost     = (float)($_POST['cost_price'] ?? 0);
        $stock    = (int)($_POST['current_stock'] ?? 0);
        $reorder  = (int)($_POST['reorder_level'] ?? 0);

        $errors = [];
        if ($name === '' || mb_strlen($name) > 160) { $errors['name']  = 'Enter a product name (max 160 chars).'; }
        if ($price < 0)  { $errors['selling_price'] = 'Price must be 0 or more.'; }
        if ($stock < 0)  { $errors['current_stock'] = 'Stock must be 0 or more.'; }
        if (!in_array($category, ['food', 'stationery', 'uniform', 'toiletries', 'other'], true)) { $category = 'food'; }
        if ($errors) {
            $this->respond(false, 'Please correct the highlighted fields.', 'canteen', [], $errors);
        }

        $code = 'PRD-' . strtoupper(bin2hex(random_bytes(2)));
        $id = CanteenModule::createProduct([
            'school_id' => school_id(),
            'code'      => $code,
            'name'      => $name,
            'category'  => $category,
            'unit'      => $unit ?: 'piece',
            'cost'      => $cost,
            'price'     => $price,
            'stock'     => $stock,
            'reorder'   => $reorder,
        ]);

        audit('canteen', 'product_created', 'products', $id, 'Added product: ' . $name);
        $this->respond(true, 'Product "' . $name . '" added.', 'canteen');
    }

    public function toggleProduct(): void
    {
        $id     = (int)($_POST['id'] ?? 0);
        $status = (string)($_POST['status'] ?? '');
        if (!in_array($status, ['active', 'inactive', 'out_of_stock'], true)) {
            $this->respond(false, 'Invalid status.', 'canteen');
        }
        $ok = CanteenModule::changeStatus($id, school_id(), $status);
        $this->respond($ok, $ok ? 'Product status updated.' : 'Could not update product.', 'canteen');
    }

    public function walletDeposit(): void
    {
        $schoolId = school_id();
        $db = Database::conn();

        $studentId = $this->positiveInt($_POST['student_id'] ?? null, 'Student');
        $amount = $this->amount($_POST['amount'] ?? null, 'Deposit amount');
        $reference = $this->optionalText($_POST['reference'] ?? null, 80);
        $notes = $this->optionalText($_POST['notes'] ?? null, 255);
        $guardianId = $this->optionalInt($_POST['guardian_id'] ?? null);
        $paymentId = $this->optionalInt($_POST['payment_id'] ?? null);

        $student = $db->query('SELECT id, admission_number, first_name, last_name FROM students WHERE id = ? AND school_id = ?', [$studentId, $schoolId])->fetch();
        if (!$student) {
            $this->respond(false, 'Student not found.', 'canteen');
            return;
        }

        $wallet = $db->query('SELECT * FROM student_wallets WHERE student_id = ? AND status = \'active\'', [$studentId])->fetch();
        if (!$wallet) {
            $walletNumber = 'WLT-' . strtoupper(bin2hex(random_bytes(4)));
            $db->prepare('INSERT INTO student_wallets (student_id, wallet_number, balance, status) VALUES (?, ?, 0, \'active\')')
                ->execute([$studentId, $walletNumber]);
            $walletId = (int)$db->lastInsertId();
        } else {
            $walletId = (int)$wallet['id'];
        }

        $db->beginTransaction();
        try {
            $balanceBefore = (float)($wallet['balance'] ?? 0);
            $balanceAfter = round($balanceBefore + $amount, 2);

            $db->prepare('UPDATE student_wallets SET balance = ? WHERE id = ?')->execute([$balanceAfter, $walletId]);

            $txnId = $db->prepare(
                'INSERT INTO wallet_transactions (wallet_id, transaction_type, amount, balance_before, balance_after, reference, payment_id, guardian_id, performed_by, notes)
                 VALUES (?, \'deposit\', ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $walletId, $amount, $balanceBefore, $balanceAfter, $reference, $paymentId, $guardianId,
                (int)(Auth::user()['id'] ?? 0), $notes
            ]);

            audit('canteen', 'wallet_deposit', 'student_wallets', $walletId, 'Deposited ' . number_format($amount, 2) . ' to wallet for ' . $student['admission_number']);
            $db->commit();
            $this->respond(true, 'Wallet deposit recorded. New balance: ' . number_format($balanceAfter, 2), 'canteen');
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $this->respond(false, $error->getMessage(), 'canteen');
        }
    }

    public function walletTransactions(): void
    {
        $schoolId = school_id();
        $db = Database::conn();

        $studentId = (int)($_GET['student_id'] ?? 0);
        $wallet = null;
        $transactions = [];

        if ($studentId > 0) {
            $student = $db->query('SELECT id, admission_number, first_name, last_name FROM students WHERE id = ? AND school_id = ?', [$studentId, $schoolId])->fetch();
            if (!$student) {
                $this->respond(false, 'Student not found.', 'canteen');
                return;
            }
            $wallet = $db->query('SELECT * FROM student_wallets WHERE student_id = ?', [$studentId])->fetch();
            if ($wallet) {
                $transactions = $db->query(
                    'SELECT wt.*, s.full_name AS performed_by_name
                       FROM wallet_transactions wt
                       LEFT JOIN staff s ON s.id = wt.performed_by
                      WHERE wt.wallet_id = ?
                      ORDER BY wt.created_at DESC, wt.id DESC
                      LIMIT 200',
                    [(int)$wallet['id']]
                )->fetchAll();
            }
        }

        $students = $db->query(
            'SELECT s.id, s.admission_number, s.first_name, s.last_name, w.balance
               FROM students s
               LEFT JOIN student_wallets w ON w.student_id = s.id
              WHERE s.school_id = ? AND s.status = \'active\'
              ORDER BY s.last_name, s.first_name
              LIMIT 500',
            [$schoolId]
        )->fetchAll();

        $this->render('canteen/wallet', [
            'pageTitle' => 'Student Wallets',
            'activeNav' => 'canteen',
            'students' => $students,
            'studentId' => $studentId,
            'student' => $student ?? null,
            'wallet' => $wallet,
            'transactions' => $transactions,
            'canAdd' => Auth::can('canteen', 'add'),
        ]);
    }

    private function positiveInt(mixed $value, string $label): int
    {
        $parsed = filter_var($value, FILTER_VALIDATE_INT);
        if ($parsed === false || $parsed < 1) {
            throw new DomainException($label . ' is required.');
        }
        return $parsed;
    }

    private function optionalInt(mixed $value): ?int
    {
        if ($value === null || trim((string)$value) === '' || (string)$value === '0') {
            return null;
        }
        $parsed = filter_var($value, FILTER_VALIDATE_INT);
        if ($parsed === false || $parsed < 1) {
            return null;
        }
        return $parsed;
    }

    private function optionalText(mixed $value, int $maxLength): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $text = trim($value);
        if (mb_strlen($text) > $maxLength) {
            throw new DomainException('Text value exceeds the allowed length.');
        }
        return $text === '' ? null : $text;
    }

    private function amount(mixed $value, string $label): float
    {
        if (!is_scalar($value) || !preg_match('/^\d{1,10}(?:\.\d{1,2})?$/', (string)$value)) {
            throw new DomainException($label . ' must be a valid amount.');
        }
        $amount = (float)$value;
        if ($amount <= 0 || $amount > 99999999999.99) {
            throw new DomainException($label . ' is outside the permitted range.');
        }
        return round($amount, 2);
    }
}
