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
        $this->render('inventory/index', [
            'pageTitle'  => 'Inventory & procurement',
            'activeNav'  => 'inventory',
            'items'      => InventoryModule::items($schoolId),
            'requests'   => InventoryModule::requests($schoolId),
            'canAdd'     => Auth::can('inventory', 'add'),
            'canEdit'    => Auth::can('inventory', 'edit'),
            'canApprove' => Auth::can('inventory', 'approve'),
        ]);
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
