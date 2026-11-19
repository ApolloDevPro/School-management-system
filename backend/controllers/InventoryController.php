<?php
declare(strict_types=1);

/**
 * Stock requisitions and approvals.
 *
 * Backend controller: receives routed requests, enforces permissions and
 * renders a view or returns JSON through the base Controller helpers.
 */
final class InventoryController extends Controller
{
    public function index(): void
    {
        $schoolId = school_id();
        $suppliers = InventoryModule::suppliers($schoolId);
        $editSupplier = null;
        $editSupplierId = (int)($_GET['edit_supplier'] ?? 0);
        if ($editSupplierId > 0) {
            foreach ($suppliers as $supplier) {
                if ((int)$supplier['id'] === $editSupplierId) {
                    $editSupplier = $supplier;
                    break;
                }
            }
        }
        $this->render('inventory/index', [
            'pageTitle'  => 'Inventory & procurement',
            'activeNav'  => 'inventory',
            'items'      => InventoryModule::items($schoolId),
            'categories' => InventoryModule::categories($schoolId),
            'suppliers'  => $suppliers,
            'movements'  => InventoryModule::movements($schoolId),
            'requests'   => InventoryModule::requests($schoolId),
            'editSupplier' => $editSupplier,
            'canAdd'     => Auth::can('inventory', 'add'),
            'canEdit'    => Auth::can('inventory', 'edit'),
            'canApprove' => Auth::can('inventory', 'approve'),
        ]);
    }

    public function storeItem(): void
    {
        $schoolId = school_id();
        $name = trim((string)($_POST['name'] ?? ''));
        $code = strtoupper(trim((string)($_POST['item_code'] ?? '')));
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $unit = trim((string)($_POST['unit'] ?? 'piece'));
        $description = trim((string)($_POST['description'] ?? ''));
        $reorderRaw = trim((string)($_POST['reorder_level'] ?? '0'));
        $stockRaw = trim((string)($_POST['opening_stock'] ?? '0'));
        $costRaw = trim((string)($_POST['unit_cost'] ?? '0'));

        $errors = [];
        if ($name === '' || mb_strlen($name) > 160) { $errors['name'] = 'Enter an item name (max 160 chars).'; }
        if ($code !== '' && (mb_strlen($code) > 40 || !preg_match('/^[A-Z0-9._-]+$/', $code))) {
            $errors['item_code'] = 'Use letters, numbers, dots, dashes or underscores (max 40).';
        }
        if (mb_strlen($unit) === 0 || mb_strlen($unit) > 30) { $unit = 'piece'; }
        if (mb_strlen($description) > 255) { $errors['description'] = 'Description is too long.'; }
        if (!preg_match('/^\d{1,9}(?:\.\d{1,2})?$/', $reorderRaw)
            || !preg_match('/^\d{1,9}(?:\.\d{1,2})?$/', $stockRaw)
            || !preg_match('/^\d{1,10}(?:\.\d{1,2})?$/', $costRaw)) {
            $errors['reorder_level'] = 'Enter valid stock and cost amounts.';
        }
        if ($categoryId > 0) {
            $category = Database::query('SELECT id FROM item_categories WHERE id=? AND school_id=?', [$categoryId, $schoolId])->fetch();
            if (!$category) { $errors['category_id'] = 'Choose a category from this school.'; }
        }
        if (!$code) {
            $code = 'ITM-' . strtoupper(bin2hex(random_bytes(3)));
        }
        if (InventoryModule::itemCodeExists($schoolId, $code)) {
            $errors['item_code'] = 'That item code is already in use.';
        }
        if ($errors) {
            $this->respond(false, reset($errors), 'inventory', [], $errors);
        }

        $id = InventoryModule::createItem([
            'school_id' => $schoolId,
            'item_code' => $code,
            'name' => $name,
            'category_id' => $categoryId > 0 ? $categoryId : null,
            'unit' => $unit,
            'description' => $description !== '' ? $description : null,
            'reorder_level' => round((float)$reorderRaw, 2),
            'opening_stock' => round((float)$stockRaw, 2),
            'unit_cost' => round((float)$costRaw, 2),
            'created_by' => (int)(Auth::user()['id'] ?? 0),
        ]);
        audit('inventory', 'item_created', 'items', $id, 'Added stock item ' . $name);
        $this->respond(true, 'Stock item "' . $name . '" created.', 'inventory');
    }

    public function storeMovement(): void
    {
        $schoolId = school_id();
        $itemId = (int)($_POST['item_id'] ?? 0);
        $type = (string)($_POST['movement_type'] ?? 'receipt');
        $quantityRaw = trim((string)($_POST['quantity'] ?? ''));
        $costRaw = trim((string)($_POST['unit_cost'] ?? ''));
        $date = trim((string)($_POST['movement_date'] ?? date('Y-m-d')));
        $issuedTo = trim((string)($_POST['issued_to'] ?? ''));
        $location = trim((string)($_POST['stock_location'] ?? ''));
        $reason = trim((string)($_POST['reason'] ?? ''));

        $allowedTypes = ['opening', 'receipt', 'issue', 'return', 'adjustment', 'transfer', 'loss', 'expiration'];
        if (!in_array($type, $allowedTypes, true)) {
            $this->respond(false, 'Choose a valid movement type.', 'inventory');
        }
        $pattern = $type === 'adjustment' ? '/^-?\d{1,9}(?:\.\d{1,2})?$/' : '/^\d{1,9}(?:\.\d{1,2})?$/';
        if (!preg_match($pattern, $quantityRaw) || (float)$quantityRaw === 0.0) {
            $this->respond(false, 'Enter a valid quantity. Adjustments may be negative.', 'inventory');
        }
        if ($costRaw !== '' && !preg_match('/^\d{1,10}(?:\.\d{1,2})?$/', $costRaw)) {
            $this->respond(false, 'Enter a valid unit cost.', 'inventory');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !strtotime($date)) {
            $this->respond(false, 'Enter a valid movement date.', 'inventory');
        }
        if (mb_strlen($issuedTo) > 140 || mb_strlen($location) > 80 || mb_strlen($reason) > 255) {
            $this->respond(false, 'Issued-to, location or reason text is too long.', 'inventory');
        }
        $item = Database::query('SELECT id FROM items WHERE id=? AND school_id=?', [$itemId, $schoolId])->fetch();
        if (!$item) {
            $this->respond(false, 'Choose a stock item from this school.', 'inventory');
        }

        try {
            $result = InventoryModule::recordMovement($schoolId, [
                'item_id' => $itemId,
                'movement_type' => $type,
                'quantity' => (float)$quantityRaw,
                'unit_cost' => $costRaw !== '' ? (float)$costRaw : null,
                'movement_date' => $date,
                'issued_to' => $issuedTo !== '' ? $issuedTo : null,
                'stock_location' => $location !== '' ? $location : null,
                'reason' => $reason !== '' ? $reason : null,
            ], (int)(Auth::user()['id'] ?? 0));
        } catch (DomainException $error) {
            $this->respond(false, $error->getMessage(), 'inventory');
            return;
        }

        $this->respond(
            true,
            ucfirst($type) . ' recorded for ' . $result['item_name'] . '. New balance: '
                . rtrim(rtrim(number_format($result['quantity_after'], 2, '.', ''), '0'), '.') . ' ' . $result['unit'] . '.',
            'inventory'
        );
    }

    public function saveSupplier(): void
    {
        $schoolId = school_id();
        $id = (int)($_POST['id'] ?? 0);
        $name = trim((string)($_POST['name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $status = (string)($_POST['status'] ?? 'active');
        $fields = [
            'contact_person' => 140,
            'phone' => 40,
            'alternate_phone' => 40,
            'address' => 255,
            'tin' => 40,
            'items_supplied' => 255,
            'payment_terms' => 120,
            'notes' => 255,
        ];
        $values = [];
        foreach ($fields as $field => $max) {
            $values[$field] = trim((string)($_POST[$field] ?? ''));
            if (mb_strlen($values[$field]) > $max) {
                $this->respond(false, 'The ' . str_replace('_', ' ', $field) . ' value is too long.', 'inventory');
            }
            if ($values[$field] === '') {
                $values[$field] = null;
            }
        }
        if ($name === '' || mb_strlen($name) > 160) {
            $this->respond(false, 'Enter a supplier name (max 160 characters).', 'inventory');
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->respond(false, 'Enter a valid supplier email address.', 'inventory');
        }
        if (!in_array($status, ['active', 'on_hold', 'blacklisted', 'former'], true)) {
            $status = 'active';
        }

        try {
            $savedId = InventoryModule::saveSupplier(array_merge($values, [
                'id' => $id,
                'school_id' => $schoolId,
                'name' => $name,
                'email' => $email !== '' ? $email : null,
                'status' => $status,
            ]));
        } catch (DomainException $error) {
            $this->respond(false, $error->getMessage(), 'inventory');
            return;
        }
        audit('inventory', $id > 0 ? 'supplier_updated' : 'supplier_created', 'suppliers', $savedId, 'Saved supplier ' . $name);
        $this->respond(true, 'Supplier saved.', 'inventory');
    }

    public function toggleSupplier(): void
    {
        $status = (string)($_POST['status'] ?? '');
        if (!in_array($status, ['active', 'on_hold', 'blacklisted', 'former'], true)) {
            $this->respond(false, 'Invalid supplier status.', 'inventory');
        }
        $id = (int)($_POST['id'] ?? 0);
        $ok = InventoryModule::setSupplierStatus($id, school_id(), $status);
        $this->respond($ok, $ok ? 'Supplier status updated.' : 'Could not update supplier.', 'inventory');
    }

    public function createRequest(): void
    {
        $schoolId  = school_id();
        $userId    = (int)Auth::user()['id'];
        $neededBy  = trim((string)($_POST['needed_by'] ?? ''));
        $priority  = (string)($_POST['priority'] ?? 'normal');
        $just      = trim((string)($_POST['justification'] ?? ''));
        $amount    = (float)($_POST['estimated_total'] ?? 0);

        $errors = [];
        if (!in_array($priority, ['low', 'normal', 'high', 'urgent'], true)) { $priority = 'normal'; }
        if ($just === '')                              { $errors['justification'] = 'Provide a justification for this request.'; }
        if ($neededBy !== '' && !strtotime($neededBy)) { $errors['needed_by']    = 'Enter a valid date.'; }
        if ($errors) {
            $this->respond(false, 'Please correct the highlighted fields.', 'inventory', [], $errors);
        }

        $number = 'PR-' . strtoupper(bin2hex(random_bytes(3)));
        $id = InventoryModule::createRequest([
            'number'        => $number,
            'school_id'     => $schoolId,
            'user_id'       => $userId,
            'needed_by'     => $neededBy !== '' ? $neededBy : null,
            'priority'      => $priority,
            'justification' => $just,
            'amount'        => $amount > 0 ? $amount : 0,
        ]);

        audit('inventory', 'request_created', 'purchase_requests', $id, 'Purchase request ' . $number . ' created');
        $this->respond(true, 'Purchase request ' . $number . ' saved as draft.', 'inventory');
    }

    public function approveRequest(): void
    {
        $id       = (int)($_POST['id'] ?? 0);
        $action   = (string)($_POST['action'] ?? '');
        $userId   = (int)Auth::user()['id'];
        $schoolId = school_id();

        $transitions = [
            'submit'  => ['draft',     'submitted'],
            'approve' => ['submitted', 'approved'],
            'reject'  => ['submitted', 'rejected'],
            'cancel'  => ['draft',     'cancelled'],
        ];
        if (!isset($transitions[$action])) {
            $this->respond(false, 'Invalid action.', 'inventory');
        }
        [$from, $to] = $transitions[$action];

        if (in_array($action, ['approve', 'reject'], true) && !Auth::can('inventory', 'approve')) {
            $this->respond(false, 'You do not have permission to approve purchase requests.', 'inventory');
        }
        if (in_array($action, ['submit', 'cancel'], true)
            && !InventoryModule::ownsDraft($id, $schoolId, $userId)
            && !Auth::can('inventory', 'approve')) {
            $this->respond(false, 'You can only manage your own draft requests.', 'inventory');
        }

        $ok = InventoryModule::transition($id, $schoolId, $from, $to, $action === 'approve' ? $userId : null);
        $this->respond($ok, $ok ? 'Request ' . $to . '.' : 'Could not update request (wrong current status?).', 'inventory');
    }
}
