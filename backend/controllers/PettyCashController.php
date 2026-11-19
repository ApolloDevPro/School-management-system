<?php
declare(strict_types=1);

/**
 * Petty cash funds and vouchers.
 *
 * Backend controller: receives routed requests, enforces permissions and
 * renders a view or returns JSON through the base Controller helpers.
 */
final class PettyCashController extends Controller
{
    public function index(): void
    {
        if (!$this->allowed('finance', 'view')) {
            return;
        }
        $schoolId = school_id();
        $db = Database::conn();

        $stmt = $db->prepare(
            "SELECT f.*, CONCAT_WS(' ', s.first_name, s.last_name) AS custodian_name
               FROM petty_cash_funds f
               LEFT JOIN staff s ON s.id = f.custodian_staff_id AND s.school_id = f.school_id
              WHERE f.school_id = ?
              ORDER BY f.fund_name"
        );
        $stmt->execute([$schoolId]);
        $funds = $stmt->fetchAll();

        $stmt = $db->prepare("SELECT id, staff_number, first_name, last_name FROM staff WHERE school_id = ? AND employment_status IN ('active','probation','contract') ORDER BY last_name, first_name");
        $stmt->execute([$schoolId]);
        $staff = $stmt->fetchAll();

        $stmt = $db->prepare('SELECT id, account_code, account_name FROM gl_accounts WHERE school_id = ? AND is_active = 1 ORDER BY account_code');
        $stmt->execute([$schoolId]);
        $accounts = $stmt->fetchAll();

        $this->render('finance/petty_cash', [
            'pageTitle' => 'Petty Cash',
            'activeNav' => 'finance',
            'funds' => $funds,
            'staff' => $staff,
            'accounts' => $accounts,
            'canAdd' => Auth::can('finance', 'add'),
            'canEdit' => Auth::can('finance', 'edit'),
            'canDelete' => Auth::can('finance', 'delete'),
        ]);
    }

    public function saveFund(): void
    {
        if (!$this->allowed('finance', 'add')) {
            return;
        }
        $schoolId = school_id();
        $id = (int)($_POST['id'] ?? 0);
        $data = [
            'fund_name' => trim((string)($_POST['fund_name'] ?? '')),
            'opening_balance' => (float)($_POST['opening_balance'] ?? 0),
            'custodian_staff_id' => (int)($_POST['custodian_staff_id'] ?? 0) ?: null,
            'account_id' => (int)($_POST['account_id'] ?? 0) ?: null,
            'opened_on' => (string)($_POST['opened_on'] ?? date('Y-m-d')),
            'status' => (string)($_POST['status'] ?? 'open'),
        ];

        if ($data['fund_name'] === '') {
            $this->respond(false, 'Fund name is required.', 'finance/petty-cash');
        }

        if ($id > 0) {
            Database::conn()->prepare(
                'UPDATE petty_cash_funds SET fund_name = ?, opening_balance = ?, current_balance = opening_balance, custodian_staff_id = ?, account_id = ?, opened_on = ?, status = ?
                 WHERE id = ? AND school_id = ?',
                [...array_values($data), $id, $schoolId]
            );
            audit('finance', 'update', 'petty_cash_funds', $id, 'Updated petty cash fund ' . $data['fund_name']);
            $this->respond(true, 'Fund updated.', 'finance/petty-cash');
        } else {
            Database::conn()->prepare(
                'INSERT INTO petty_cash_funds (school_id, fund_name, opening_balance, current_balance, custodian_staff_id, account_id, opened_on, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$schoolId, $data['fund_name'], $data['opening_balance'], $data['opening_balance'], $data['custodian_staff_id'], $data['account_id'], $data['opened_on'], $data['status']]
            );
            audit('finance', 'create', 'petty_cash_funds', (int)Database::conn()->lastInsertId(), 'Created petty cash fund ' . $data['fund_name']);
            $this->respond(true, 'Fund created.', 'finance/petty-cash');
        }
    }

    public function saveVoucher(): void
    {
        if (!$this->allowed('finance', 'add')) {
            return;
        }
        $schoolId = school_id();
        $fundId = (int)($_POST['fund_id'] ?? 0);
        $amount = (float)($_POST['amount'] ?? 0);
        $spentOn = trim((string)($_POST['spent_on'] ?? ''));
        $accountId = (int)($_POST['account_id'] ?? 0) ?: null;

        if ($fundId <= 0 || $amount <= 0 || $spentOn === '') {
            $this->respond(false, 'Fund, amount and spent-on are required.', 'finance/petty-cash');
        }

        $stmt = $db->prepare('SELECT current_balance FROM petty_cash_funds WHERE id = ? AND school_id = ?');
        $stmt->execute([$fundId, $schoolId]);
        $fund = $stmt->fetch();
        if (!$fund) {
            $this->respond(false, 'Petty cash fund not found.', 'finance/petty-cash');
        }

        $balanceAfter = (float)$fund['current_balance'] - $amount;
        $voucherNumber = 'PCV-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));

        Database::conn()->prepare(
            'INSERT INTO petty_cash_vouchers (fund_id, voucher_number, amount, spent_on, spent_by, expense_date, account_id, status, balance_after)
             VALUES (?, ?, ?, ?, ?, ?, ?, \'requested\', ?)',
            [$fundId, $voucherNumber, $amount, $spentOn, (int)Auth::user()['id'], (string)($_POST['expense_date'] ?? date('Y-m-d')), $accountId, $balanceAfter]
        );

        audit('finance', 'create', 'petty_cash_vouchers', null, 'Created petty cash voucher ' . $voucherNumber . ' for fund ' . $fundId);
        $this->respond(true, 'Voucher created.', 'finance/petty-cash');
    }

    public function deleteFund(): void
    {
        if (!$this->allowed('finance', 'delete')) {
            return;
        }
        $id = (int)($_POST['id'] ?? 0);
        $schoolId = school_id();
        Database::conn()->prepare('DELETE FROM petty_cash_funds WHERE id = ? AND school_id = ?')->execute([$id, $schoolId]);
        audit('finance', 'delete', 'petty_cash_funds', $id, 'Deleted petty cash fund');
        $this->respond(true, 'Fund deleted.', 'finance/petty-cash');
    }
}
