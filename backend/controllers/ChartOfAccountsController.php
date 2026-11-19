<?php
declare(strict_types=1);

/**
 * Chart of accounts management and account lookup.
 *
 * Backend controller: receives routed requests, enforces permissions and
 * renders a view or returns JSON through the base Controller helpers.
 */
final class ChartOfAccountsController extends Controller
{
    public function index(): void
    {
        if (!$this->allowed('finance', 'view')) {
            return;
        }
        $schoolId = school_id();
        $db = Database::conn();
        $stmt = $db->prepare(
            'SELECT a.*, p.account_name AS parent_name
               FROM gl_accounts a
               LEFT JOIN gl_accounts p ON p.id = a.parent_id AND p.school_id = a.school_id
              WHERE a.school_id = ?
              ORDER BY a.account_code'
        );
        $stmt->execute([$schoolId]);
        $accounts = $stmt->fetchAll();

        $this->render('finance/chart_of_accounts', [
            'pageTitle' => 'Chart of Accounts',
            'activeNav' => 'finance',
            'accounts' => $accounts,
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
        $data = [
            'account_code' => trim((string)($_POST['account_code'] ?? '')),
            'account_name' => trim((string)($_POST['account_name'] ?? '')),
            'account_type' => (string)($_POST['account_type'] ?? 'expense'),
            'parent_id' => (int)($_POST['parent_id'] ?? 0) ?: null,
            'description' => trim((string)($_POST['description'] ?? '')) ?: null,
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];

        if ($data['account_code'] === '' || $data['account_name'] === '') {
            $this->respond(false, 'Account code and name are required.', 'finance/chart-of-accounts');
        }

        $id = (int)($_POST['id'] ?? 0);

        // Prevent duplicate account codes
        $stmt = Database::conn()->prepare(
            'SELECT id FROM gl_accounts WHERE school_id = ? AND account_code = ? AND id <> ?'
        );
        $stmt->execute([$schoolId, $data['account_code'], $id]);
        $existing = $stmt->fetch();
        if ($existing) {
            $this->respond(false, 'Account code "' . e($data['account_code']) . '" already exists.', 'finance/chart-of-accounts');
        }

        $db = Database::conn();
        $db->beginTransaction();
        try {
            if ($id > 0) {
                $db->prepare(
                    'UPDATE gl_accounts SET account_code = ?, account_name = ?, account_type = ?, parent_id = ?, description = ?, is_active = ?
                     WHERE id = ? AND school_id = ?'
                )->execute([$data['account_code'], $data['account_name'], $data['account_type'], $data['parent_id'], $data['description'], $data['is_active'], $id, $schoolId]);
                audit('finance', 'update', 'gl_accounts', $id, 'Updated account ' . $data['account_code']);
                $db->commit();
                $this->respond(true, 'Account updated.', 'finance/chart-of-accounts');
            } else {
                $db->prepare(
                    'INSERT INTO gl_accounts (school_id, account_code, account_name, account_type, parent_id, description, is_active)
                     VALUES (?, ?, ?, ?, ?, ?, ?)'
                )->execute([$schoolId, $data['account_code'], $data['account_name'], $data['account_type'], $data['parent_id'], $data['description'], $data['is_active']]);
                $newId = (int)$db->lastInsertId();
                audit('finance', 'create', 'gl_accounts', $newId, 'Created account ' . $data['account_code']);
                $db->commit();
                $this->respond(true, 'Account created.', 'finance/chart-of-accounts');
            }
        } catch (Throwable $error) {
            $db->rollBack();
            $this->respond(false, 'Could not save account: ' . $error->getMessage(), 'finance/chart-of-accounts');
        }
    }

    public function delete(): void
    {
        if (!$this->allowed('finance', 'delete')) {
            return;
        }
        $id = (int)($_POST['id'] ?? 0);
        $schoolId = school_id();
        $db = Database::conn();
        $stmt = $db->prepare('SELECT COUNT(*) AS cnt FROM gl_accounts WHERE parent_id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        $used = (int)($row['cnt'] ?? 0);
        if ($used > 0) {
            $this->respond(false, 'Cannot delete: account has child accounts.', 'finance/chart-of-accounts');
        }
        Database::conn()->prepare('DELETE FROM gl_accounts WHERE id = ? AND school_id = ?')->execute([$id, $schoolId]);
        audit('finance', 'delete', 'gl_accounts', $id, 'Deleted chart of accounts entry');
        $this->respond(true, 'Account deleted.', 'finance/chart-of-accounts');
    }

    public function list(): void
    {
        if (!$this->allowed('finance', 'view')) {
            return;
        }
        $schoolId = school_id();
        $db = Database::conn();
        $type = (string)($_GET['type'] ?? '');
        $sql = 'SELECT id, account_code, account_name, account_type FROM gl_accounts WHERE school_id = ? AND is_active = 1';
        $params = [$schoolId];
        if ($type !== '') {
            $sql .= ' AND account_type = ?';
            $params[] = $type;
        }
        $sql .= ' ORDER BY account_code';
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        echo json_encode($stmt->fetchAll());
        exit;
    }
}
