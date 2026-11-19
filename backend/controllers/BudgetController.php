<?php
declare(strict_types=1);

final class BudgetController extends Controller
{
    public function index(): void
    {
        if (!$this->allowed('finance', 'view')) {
            return;
        }
        $schoolId = school_id();
        $db = Database::conn();

        $stmt = $db->prepare(
            'SELECT b.*, d.name AS department_name
               FROM budgets b
               LEFT JOIN departments d ON d.id = b.department_id AND d.school_id = b.school_id
              WHERE b.school_id = ?
              ORDER BY b.created_at DESC'
        );
        $stmt->execute([$schoolId]);
        $budgets = $stmt->fetchAll();

        $stmt = $db->prepare('SELECT id, name FROM departments WHERE school_id = ? AND is_active = 1 ORDER BY name');
        $stmt->execute([$schoolId]);
        $departments = $stmt->fetchAll();

        $stmt = $db->prepare('SELECT id, name FROM academic_years WHERE school_id = ? ORDER BY start_date DESC');
        $stmt->execute([$schoolId]);
        $years = $stmt->fetchAll();

        $stmt = $db->prepare('SELECT t.id, t.name FROM terms t JOIN academic_years y ON y.id = t.academic_year_id WHERE y.school_id = ? ORDER BY t.term_number');
        $stmt->execute([$schoolId]);
        $terms = $stmt->fetchAll();

        $this->render('finance/budgets', [
            'pageTitle' => 'Budgets',
            'activeNav' => 'finance',
            'budgets' => $budgets,
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
            'name' => trim((string)($_POST['name'] ?? '')),
            'budget_type' => (string)($_POST['budget_type'] ?? 'termly'),
            'academic_year_id' => (int)($_POST['academic_year_id'] ?? 0),
            'term_id' => (int)($_POST['term_id'] ?? 0) ?: null,
            'department_id' => (int)($_POST['department_id'] ?? 0) ?: null,
            'total_amount' => (float)($_POST['total_amount'] ?? 0),
            'status' => (string)($_POST['status'] ?? 'draft'),
            'notes' => trim((string)($_POST['notes'] ?? '')) ?: null,
        ];

        if ($data['name'] === '' || $data['academic_year_id'] <= 0) {
            $this->respond(false, 'Name and academic year are required.', 'finance/budgets');
        }

        if ($id > 0) {
            Database::conn()->prepare(
                'UPDATE budgets SET name = ?, budget_type = ?, academic_year_id = ?, term_id = ?, department_id = ?, total_amount = ?, status = ?, notes = ?
                 WHERE id = ? AND school_id = ?',
                [...array_values($data), $id, $schoolId]
            );
            audit('finance', 'update', 'budgets', $id, 'Updated budget ' . $data['name']);
            $this->respond(true, 'Budget updated.', 'finance/budgets');
        } else {
            Database::conn()->prepare(
                'INSERT INTO budgets (school_id, name, budget_type, academic_year_id, term_id, department_id, total_amount, status, notes, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$schoolId, $data['name'], $data['budget_type'], $data['academic_year_id'], $data['term_id'], $data['department_id'], $data['total_amount'], $data['status'], $data['notes'], (int)Auth::user()['id']]
            );
            audit('finance', 'create', 'budgets', (int)Database::conn()->lastInsertId(), 'Created budget ' . $data['name']);
            $this->respond(true, 'Budget created.', 'finance/budgets');
        }
    }

    public function delete(): void
    {
        if (!$this->allowed('finance', 'delete')) {
            return;
        }
        $id = (int)($_POST['id'] ?? 0);
        $schoolId = school_id();
        Database::conn()->prepare('DELETE FROM budgets WHERE id = ? AND school_id = ?')->execute([$id, $schoolId]);
        audit('finance', 'delete', 'budgets', $id, 'Deleted budget');
        $this->respond(true, 'Budget deleted.', 'finance/budgets');
    }

    public function lines(): void
    {
        if (!$this->allowed('finance', 'view')) {
            return;
        }
        $budgetId = (int)($_GET['budget_id'] ?? 0);
        $lines = Database::query(
            'SELECT bl.*, a.account_code, a.account_name, a.account_type
               FROM budget_lines bl
               JOIN gl_accounts a ON a.id = bl.account_id AND a.school_id = ?
              WHERE bl.budget_id = ?
              ORDER BY a.account_code',
            [school_id(), $budgetId]
        )->fetchAll();
        echo json_encode($lines);
        exit;
    }

    public function saveLine(): void
    {
        if (!$this->allowed('finance', 'add')) {
            return;
        }
        $budgetId = (int)($_POST['budget_id'] ?? 0);
        $accountId = (int)($_POST['account_id'] ?? 0);
        $planned = (float)($_POST['planned_amount'] ?? 0);
        $notes = trim((string)($_POST['notes'] ?? '')) ?: null;

        if ($budgetId <= 0 || $accountId <= 0) {
            $this->respond(false, 'Budget and account are required.', 'finance/budgets');
        }

        Database::conn()->prepare(
            'INSERT INTO budget_lines (budget_id, account_id, planned_amount, actual_amount, variance, notes)
             VALUES (?, ?, ?, 0, ?, ?)
             ON DUPLICATE KEY UPDATE planned_amount = VALUES(planned_amount), notes = VALUES(notes), variance = VALUES(planned_amount)',
            [$budgetId, $accountId, $planned, $planned, $notes]
        );

        audit('finance', 'update', 'budget_lines', $budgetId, 'Updated budget line for account ' . $accountId);
        $this->respond(true, 'Budget line saved.', 'finance/budgets');
    }

    public function deleteLine(): void
    {
        if (!$this->allowed('finance', 'delete')) {
            return;
        }
        $id = (int)($_POST['id'] ?? 0);
        Database::conn()->prepare('DELETE FROM budget_lines WHERE id = ?')->execute([$id]);
        $this->respond(true, 'Budget line deleted.', 'finance/budgets');
    }
}
