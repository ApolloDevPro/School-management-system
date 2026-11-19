<?php
declare(strict_types=1);

/**
 * Expense recording and deletion.
 *
 * Backend controller: receives routed requests, enforces permissions and
 * renders a view or returns JSON through the base Controller helpers.
 */
final class ExpenseController extends Controller
{
    public function index(): void
    {
        if (!$this->allowed('finance', 'view')) {
            return;
        }
        $schoolId = school_id();
        $db = Database::conn();

        $stmt = $db->prepare(
            'SELECT e.*, d.name AS department_name, a.account_name
               FROM expenses e
               LEFT JOIN departments d ON d.id = e.department_id AND d.school_id = e.school_id
               LEFT JOIN gl_accounts a ON a.id = e.account_id AND a.school_id = e.school_id
              WHERE e.school_id = ?
              ORDER BY e.expense_date DESC, e.id DESC'
        );
        $stmt->execute([$schoolId]);
        $expenses = $stmt->fetchAll();

        $stmt = $db->prepare('SELECT id, account_code, account_name, account_type FROM gl_accounts WHERE school_id = ? AND is_active = 1 AND account_type = \'expense\' ORDER BY account_code');
        $stmt->execute([$schoolId]);
        $accounts = $stmt->fetchAll();

        $stmt = $db->prepare('SELECT id, name FROM departments WHERE school_id = ? AND is_active = 1 ORDER BY name');
        $stmt->execute([$schoolId]);
        $departments = $stmt->fetchAll();

        $stmt = $db->prepare('SELECT id, name FROM academic_years WHERE school_id = ? ORDER BY start_date DESC');
        $stmt->execute([$schoolId]);
        $years = $stmt->fetchAll();

        $stmt = $db->prepare('SELECT t.id, t.name FROM terms t JOIN academic_years y ON y.id = t.academic_year_id WHERE y.school_id = ? ORDER BY t.term_number');
        $stmt->execute([$schoolId]);
        $terms = $stmt->fetchAll();

        $this->render('finance/expenses', [
            'pageTitle' => 'Expenses',
            'activeNav' => 'finance',
            'expenses' => $expenses,
            'accounts' => $accounts,
            'departments' => $departments,
            'years' => $years,
            'terms' => $terms,
            'canAdd' => Auth::can('finance', 'add'),
            'canEdit' => Auth::can('finance', 'edit'),
            'canDelete' => Auth::can('finance', 'delete'),
        ]);
    }

    public function save(): void
    {
        if (!$this->allowed('finance', 'add')) {
            return;
        }
        $schoolId = school_id();
        $id = (int)($_POST['id'] ?? 0);
        $data = [
            'voucher_number' => trim((string)($_POST['voucher_number'] ?? '')) ?: 'EXP-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(2))),
            'account_id' => (int)($_POST['account_id'] ?? 0),
            'department_id' => (int)($_POST['department_id'] ?? 0) ?: null,
            'academic_year_id' => (int)($_POST['academic_year_id'] ?? 0) ?: null,
            'term_id' => (int)($_POST['term_id'] ?? 0) ?: null,
            'amount' => (float)($_POST['amount'] ?? 0),
            'expense_date' => (string)($_POST['expense_date'] ?? date('Y-m-d')),
            'payee' => trim((string)($_POST['payee'] ?? '')),
            'description' => trim((string)($_POST['description'] ?? '')),
            'category' => (string)($_POST['category'] ?? 'other'),
            'payment_method' => (string)($_POST['payment_method'] ?? 'cash'),
            'approval_status' => (string)($_POST['approval_status'] ?? 'pending'),
        ];

        if ($data['account_id'] <= 0 || $data['payee'] === '' || $data['description'] === '') {
            $this->respond(false, 'Account, payee and description are required.', 'finance/expenses');
        }

        if ($id > 0) {
            Database::conn()->prepare(
                'UPDATE expenses SET voucher_number = ?, account_id = ?, department_id = ?, academic_year_id = ?, term_id = ?, amount = ?, expense_date = ?, payee = ?, description = ?, category = ?, payment_method = ?, approval_status = ?
                 WHERE id = ? AND school_id = ?',
                [...array_values($data), $id, $schoolId]
            );
            audit('finance', 'update', 'expenses', $id, 'Updated expense ' . $data['voucher_number']);
            $this->respond(true, 'Expense updated.', 'finance/expenses');
        } else {
            Database::conn()->prepare(
                'INSERT INTO expenses (school_id, voucher_number, account_id, department_id, academic_year_id, term_id, amount, expense_date, payee, description, category, payment_method, approval_status, requested_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$schoolId, $data['voucher_number'], $data['account_id'], $data['department_id'], $data['academic_year_id'], $data['term_id'], $data['amount'], $data['expense_date'], $data['payee'], $data['description'], $data['category'], $data['payment_method'], $data['approval_status'], (int)Auth::user()['id']]
            );
            audit('finance', 'create', 'expenses', (int)Database::conn()->lastInsertId(), 'Recorded expense ' . $data['voucher_number']);
            $this->respond(true, 'Expense recorded.', 'finance/expenses');
        }
    }

    public function delete(): void
    {
        if (!$this->allowed('finance', 'delete')) {
            return;
        }
        $id = (int)($_POST['id'] ?? 0);
        $schoolId = school_id();
        Database::conn()->prepare('DELETE FROM expenses WHERE id = ? AND school_id = ?')->execute([$id, $schoolId]);
        audit('finance', 'delete', 'expenses', $id, 'Deleted expense');
        $this->respond(true, 'Expense deleted.', 'finance/expenses');
    }
}
