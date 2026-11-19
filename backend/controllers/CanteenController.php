<?php
declare(strict_types=1);

/**
 * Canteen products, student wallets and deposits.
 *
 * Backend controller: receives routed requests, enforces permissions and
 * renders a view or returns JSON through the base Controller helpers.
 */
final class CanteenController extends Controller
{
    public function index(): void
    {
        $schoolId = school_id();
        $this->render('canteen/index', [
            'pageTitle' => 'Canteen & shop',
            'activeNav' => 'canteen',
            'products'  => CanteenModule::products($schoolId),
            'students'  => CanteenModule::studentsWithWallet($schoolId),
            'sales'     => CanteenModule::sales($schoolId),
            'today'     => CanteenModule::todaySummary($schoolId),
            'sellers'   => CanteenModule::sellers($schoolId),
            'reconciliations' => CanteenModule::reconciliations($schoolId),
            'canAdd'    => Auth::can('canteen', 'add'),
            'canEdit'   => Auth::can('canteen', 'edit'),
            'canApprove'=> Auth::can('canteen', 'approve'),
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
        if ($cost < 0)   { $errors['cost_price']   = 'Cost must be 0 or more.'; }
        if ($stock < 0)  { $errors['current_stock'] = 'Stock must be 0 or more.'; }
        if (!in_array($category, CanteenModule::CATEGORIES, true)) { $category = 'snack'; }
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

        $student = Database::query('SELECT id, admission_number, first_name, last_name FROM students WHERE id = ? AND school_id = ?', [$studentId, $schoolId])->fetch();
        if (!$student) {
            $this->respond(false, 'Student not found.', 'canteen');
            return;
        }

        $wallet = Database::query('SELECT * FROM student_wallets WHERE student_id = ? AND status = \'active\'', [$studentId])->fetch();
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
            $student = Database::query('SELECT id, admission_number, first_name, last_name FROM students WHERE id = ? AND school_id = ?', [$studentId, $schoolId])->fetch();
            if (!$student) {
                $this->respond(false, 'Student not found.', 'canteen');
                return;
            }
            $wallet = Database::query('SELECT * FROM student_wallets WHERE student_id = ?', [$studentId])->fetch();
            if ($wallet) {
                $transactions = Database::query(
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

        $students = Database::query(
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

    public function recordSale(): void
    {
        $schoolId = school_id();
        $buyerType = (string)($_POST['buyer_type'] ?? 'student');
        $paymentMethod = (string)($_POST['payment_method'] ?? 'cash');
        $studentId = (int)($_POST['student_id'] ?? 0);
        $discountRaw = trim((string)($_POST['discount'] ?? '0'));
        $amountRaw = trim((string)($_POST['amount_paid'] ?? '0'));
        $notes = trim((string)($_POST['notes'] ?? ''));
        $productIds = $_POST['product_id'] ?? [];
        $quantities = $_POST['quantity'] ?? [];

        if (!in_array($buyerType, ['student', 'staff', 'visitor', 'other'], true)) {
            $this->respond(false, 'Choose a valid buyer type.', 'canteen');
        }
        if (!in_array($paymentMethod, ['cash', 'wallet'], true)) {
            $this->respond(false, 'Choose cash or wallet payment.', 'canteen');
        }
        if (!is_array($productIds) || !is_array($quantities) || count($productIds) !== count($quantities)) {
            $this->respond(false, 'Add at least one product line to the sale.', 'canteen');
        }
        if (!preg_match('/^\d{1,10}(?:\.\d{1,2})?$/', $discountRaw) || !preg_match('/^\d{1,10}(?:\.\d{1,2})?$/', $amountRaw)) {
            $this->respond(false, 'Enter valid money amounts.', 'canteen');
        }
        if (mb_strlen($notes) > 255) {
            $this->respond(false, 'Sale notes are too long.', 'canteen');
        }

        $lines = [];
        foreach ($productIds as $index => $productId) {
            $quantity = round((float)($quantities[$index] ?? 0), 2);
            if ($quantity <= 0) {
                continue;
            }
            $lines[] = ['product_id' => (int)$productId, 'quantity' => $quantity];
        }
        if (!$lines) {
            $this->respond(false, 'Add at least one product line with a quantity.', 'canteen');
        }
        if (count($lines) > 50) {
            $this->respond(false, 'A single sale cannot contain more than 50 lines.', 'canteen');
        }
        if ($buyerType === 'student' && $studentId < 1) {
            $this->respond(false, 'Choose the student buying from the canteen.', 'canteen');
        }

        try {
            $sale = CanteenModule::recordSale($schoolId, [
                'buyer_type' => $buyerType,
                'student_id' => $studentId,
                'payment_method' => $paymentMethod,
                'discount' => (float)$discountRaw,
                'amount_paid' => (float)$amountRaw,
                'notes' => $notes !== '' ? $notes : null,
                'lines' => $lines,
            ], (int)(Auth::user()['id'] ?? 0));
        } catch (DomainException $error) {
            $this->respond(false, $error->getMessage(), 'canteen');
            return;
        }

        $message = 'Sale ' . $sale['sale_number'] . ' recorded: ' . number_format($sale['total'], 2) . ' UGX.';
        if ($sale['change'] > 0) {
            $message .= ' Change: ' . number_format($sale['change'], 2) . '.';
        }
        if ($sale['wallet_balance'] !== null) {
            $message .= ' Wallet balance: ' . number_format($sale['wallet_balance'], 2) . '.';
        }
        $this->respond(true, $message, 'canteen');
    }

    public function voidSale(): void
    {
        if (!$this->allowed('canteen', 'approve')) {
            return;
        }
        $saleId = (int)($_POST['id'] ?? 0);
        $reason = trim((string)($_POST['void_reason'] ?? ''));
        if ($saleId < 1 || mb_strlen($reason) < 3 || mb_strlen($reason) > 255) {
            $this->respond(false, 'Choose a sale and give a reason of at least 3 characters.', 'canteen');
        }
        try {
            CanteenModule::voidSale($saleId, school_id(), $reason, (int)(Auth::user()['id'] ?? 0));
        } catch (DomainException $error) {
            $this->respond(false, $error->getMessage(), 'canteen');
            return;
        }
        $this->respond(true, 'Sale voided and stock restored.', 'canteen');
    }

    /**
     * Count the till for one day and seller and compare with cash sales.
     */
    public function saveTillCount(): void
    {
        $date = trim((string)($_POST['recon_date'] ?? ''));
        $sellerId = (int)($_POST['seller_staff_id'] ?? 0);
        $counted = $_POST['counted_cash'] ?? '';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !strtotime($date)
            || !is_numeric($counted) || (float)$counted < 0) {
            $this->respond(false, 'Enter a valid date and a cash count of zero or more.', 'canteen');
        }
        try {
            $result = CanteenModule::saveReconciliation(
                school_id(),
                $date,
                $sellerId > 0 ? $sellerId : null,
                round((float)$counted, 2),
                $this->optionalText($_POST['remarks'] ?? '', 255),
                (int)Auth::user()['id']
            );
        } catch (DomainException $error) {
            $this->respond(false, $error->getMessage(), 'canteen');
        }
        audit('canteen', 'reconcile', 'shop_till_reconciliations', $result['id'], 'Counted till for ' . $date);
        $this->respond(
            true,
            'Till counted. Expected ' . number_format($result['expected_cash'], 0)
                . ', variance ' . number_format($result['difference'], 0) . '.',
            'canteen'
        );
    }

    public function approveTillCount(): void
    {
        if (!Auth::can('canteen', 'approve')) {
            $this->respond(false, 'You are not allowed to approve till counts.', 'canteen');
        }
        $id = (int)($_POST['id'] ?? 0);
        try {
            CanteenModule::approveReconciliation($id, school_id(), (int)Auth::user()['id']);
        } catch (DomainException $error) {
            $this->respond(false, $error->getMessage(), 'canteen');
        }
        audit('canteen', 'approve', 'shop_till_reconciliations', $id, 'Approved till count #' . $id);
        $this->respond(true, 'Till count approved.', 'canteen');
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
