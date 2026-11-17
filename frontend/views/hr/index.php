<div class="page-head">
  <div><h1 class="h4 mb-0">HR &amp; payroll</h1><p class="text-muted small mb-0">Staff records, leave administration and draft payroll</p></div>
</div>

<div class="row g-3">
<?php if ($canAdd): ?>
  <div class="col-xl-4">
    <div class="card app-card">
      <div class="card-header">Add staff record</div>
      <div class="card-body">
        <form method="post" action="<?= url('hr/staff/store') ?>">
          <?= csrf_field() ?>
          <div class="mb-2"><label class="form-label">Staff number</label><input class="form-control" name="staff_number" maxlength="40" required></div>
          <div class="row g-2">
            <div class="col"><label class="form-label">First name</label><input class="form-control" name="first_name" maxlength="60" required></div>
            <div class="col"><label class="form-label">Last name</label><input class="form-control" name="last_name" maxlength="60" required></div>
          </div>
          <div class="mb-2 mt-2"><label class="form-label">Middle name</label><input class="form-control" name="middle_name" maxlength="60"></div>
          <div class="row g-2">
            <div class="col"><label class="form-label">Position</label><input class="form-control" name="position" maxlength="120"></div>
            <div class="col"><label class="form-label">Phone</label><input class="form-control" name="phone" maxlength="40"></div>
          </div>
          <div class="mb-2 mt-2"><label class="form-label">Email</label><input class="form-control" name="email" type="email" maxlength="120"></div>
          <div class="row g-2">
            <div class="col"><label class="form-label">Employment type</label><select class="form-select" name="employment_type"><option value="teaching">Teaching</option><option value="non_teaching">Non-teaching</option></select></div>
            <div class="col"><label class="form-label">Status</label><select class="form-select" name="employment_status"><option value="active">Active</option><option value="probation">Probation</option><option value="contract">Contract</option></select></div>
          </div>
          <div class="row g-2 mt-1">
            <div class="col"><label class="form-label">Appointment date</label><input class="form-control" type="date" name="date_of_appointment"></div>
            <div class="col"><label class="form-label">Qualification</label><input class="form-control" name="highest_qualification" maxlength="140"></div>
          </div>
          <button class="btn btn-primary mt-3" type="submit">Create staff record</button>
        </form>
      </div>
    </div>
  </div>
<?php endif; ?>
  <div class="<?= $canAdd ? 'col-xl-8' : 'col-12' ?>">
    <div class="card app-card">
      <div class="card-header">Staff directory <span class="badge text-bg-light"><?= count($staff) ?></span></div>
      <div class="table-responsive"><table class="table table-hover align-middle mb-0" data-table>
        <thead><tr><th>Staff</th><th>Position / department</th><th>Contact</th><th>Employment</th><th class="text-end">Documents</th></tr></thead><tbody>
<?php if (!$staff): ?><tr><td colspan="5" class="text-center text-muted py-4">No staff records found.</td></tr><?php endif; ?>
<?php foreach ($staff as $member): ?>
        <tr><td><strong><?= e($member['last_name'] . ' ' . $member['first_name']) ?></strong><div class="small text-muted"><?= e($member['staff_number']) ?></div></td>
          <td><?= e($member['position'] ?? '—') ?><div class="small text-muted"><?= e($member['department_name'] ?? '') ?></div></td>
          <td><?= e($member['phone'] ?? '—') ?><div class="small text-muted"><?= e($member['email'] ?? '') ?></div></td>
          <td><?= e(str_replace('_', ' ', $member['employment_type'])) ?><div class="small text-muted"><?= e($member['employment_status']) ?></div></td>
          <td class="text-end text-nowrap">
            <a class="btn btn-sm btn-outline-success" href="<?= url('hr/appointment-letter', ['staff_id' => (int)$member['id']]) ?>" target="_blank"><i class="bi bi-file-earmark-text"></i> Letter</a>
            <a class="btn btn-sm btn-outline-secondary" href="<?= url('hr/id-card', ['staff_id' => (int)$member['id']]) ?>" target="_blank"><i class="bi bi-card-id"></i> ID</a>
          </td></tr>
<?php endforeach; ?>
        </tbody>
      </table></div>
    </div>
  </div>
</div>

<div class="row g-3 mt-1">
  <div class="col-xl-5">
    <div class="card app-card mb-3">
      <div class="card-header">Leave requests</div>
<?php if ($canAdd && $leaveTypes): ?>
      <div class="card-body border-bottom">
        <form method="post" action="<?= url('hr/leave/apply') ?>" class="row g-2">
          <?= csrf_field() ?>
          <div class="col-12"><label class="form-label">Staff member</label><select class="form-select" name="staff_id" required><option value="">Choose…</option>
<?php foreach ($staff as $member): ?><option value="<?= (int)$member['id'] ?>"><?= e($member['staff_number'] . ' — ' . $member['last_name'] . ' ' . $member['first_name']) ?></option><?php endforeach; ?>
          </select></div>
          <div class="col-12"><label class="form-label">Leave type</label><select class="form-select" name="leave_type_id" required><option value="">Choose…</option>
<?php foreach ($leaveTypes as $type): if ((int)$type['is_active']): ?><option value="<?= (int)$type['id'] ?>"><?= e($type['name']) ?></option><?php endif; endforeach; ?>
          </select></div>
          <div class="col-6"><label class="form-label">From</label><input class="form-control" type="date" name="start_date" required></div>
          <div class="col-6"><label class="form-label">To</label><input class="form-control" type="date" name="end_date" required></div>
          <div class="col-12"><label class="form-label">Reason</label><input class="form-control" name="reason" maxlength="255"></div>
          <div class="col-12"><button class="btn btn-primary btn-sm">Submit leave</button></div>
        </form>
      </div>
<?php elseif (!$leaveTypes): ?>
      <div class="card-body small text-muted">Create a leave type below before submitting requests.</div>
<?php endif; ?>
      <div class="table-responsive"><table class="table table-sm align-middle mb-0">
        <thead><tr><th>Staff / dates</th><th>Type</th><th>Status</th><?php if ($canApprove): ?><th></th><?php endif; ?></tr></thead><tbody>
<?php if (!$leaveRequests): ?><tr><td colspan="<?= $canApprove ? 4 : 3 ?>" class="text-center text-muted py-3">No leave requests.</td></tr><?php endif; ?>
<?php foreach ($leaveRequests as $request): ?>
        <tr><td><?= e($request['last_name'] . ' ' . $request['first_name']) ?><div class="small text-muted"><?= e($request['start_date']) ?> – <?= e($request['end_date']) ?> (<?= e($request['days_requested']) ?> days)</div></td>
          <td><?= e($request['leave_type']) ?></td><td><span class="badge text-bg-light"><?= e($request['status']) ?></span></td>
<?php if ($canApprove): ?><td class="text-nowrap">
<?php if ($request['status'] === 'pending'): foreach (['approved' => 'Approve', 'rejected' => 'Reject'] as $decision => $label): ?>
          <form method="post" action="<?= url('hr/leave/review') ?>" class="d-inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$request['id'] ?>"><input type="hidden" name="status" value="<?= e($decision) ?>"><button class="btn btn-sm btn-outline-<?= $decision === 'approved' ? 'success' : 'danger' ?>"><?= e($label) ?></button></form>
<?php endforeach; endif; ?>
        </td><?php endif; ?></tr>
<?php endforeach; ?>
        </tbody>
      </table></div>
    </div>
<?php if ($canEdit): ?>
    <div class="card app-card">
      <div class="card-header">Configure leave type</div><div class="card-body">
        <form method="post" action="<?= url('hr/leave-types/store') ?>" class="row g-2">
          <?= csrf_field() ?>
          <div class="col-6"><label class="form-label">Name</label><input class="form-control" name="name" maxlength="60" required></div>
          <div class="col-6"><label class="form-label">Code</label><input class="form-control" name="code" maxlength="20" required></div>
          <div class="col-6"><label class="form-label">Days per year</label><input class="form-control" type="number" min="0" max="366" name="days_per_year" value="0" required></div>
          <div class="col-6 d-flex align-items-end"><div><div class="form-check"><input class="form-check-input" type="checkbox" name="is_paid" checked><label class="form-check-label">Paid leave</label></div><div class="form-check"><input class="form-check-input" type="checkbox" name="requires_document"><label class="form-check-label">Requires document</label></div></div></div>
          <div class="col-12"><button class="btn btn-outline-primary btn-sm">Add leave type</button></div>
        </form>
      </div>
    </div>
<?php endif; ?>
  </div>

  <div class="col-xl-7">
    <div class="card app-card">
      <div class="card-header">Payroll periods and payroll items</div>
      <div class="card-body border-bottom">
        <div class="d-flex flex-wrap gap-3 justify-content-between align-items-end">
          <form method="post" action="<?= url('hr/payroll/periods/create') ?>" class="d-flex flex-wrap align-items-end gap-2">
            <?= csrf_field() ?>
            <div><label class="form-label">Year</label><input class="form-control" type="number" min="2000" max="2200" name="period_year" value="<?= date('Y') ?>" required></div>
            <div><label class="form-label">Month</label><select class="form-select" name="period_month"><?php for ($m = 1; $m <= 12; $m++): ?><option value="<?= $m ?>" <?= $m === (int)date('n') ? 'selected' : '' ?>><?= e(date('F', mktime(0, 0, 0, $m, 1))) ?></option><?php endfor; ?></select></div>
            <button class="btn btn-primary">Create draft period</button>
          </form>
          <form method="get" action="<?= url('hr') ?>" class="d-flex gap-2 align-items-end">
            <div><label class="form-label">Select period</label><select class="form-select" name="period_id"><option value="">Choose…</option>
<?php foreach ($periods as $period): ?><option value="<?= (int)$period['id'] ?>" <?= $selectedPeriod && (int)$selectedPeriod['id'] === (int)$period['id'] ? 'selected' : '' ?>><?= e($period['period_label'] . ' · ' . $period['status']) ?></option><?php endforeach; ?>
            </select></div><button class="btn btn-outline-secondary">View</button>
          </form>
        </div>
        <div class="small text-muted mt-2">Payroll entry records manually supplied base pay, allowances and other deductions. PAYE and NSSF are left at zero; no statutory calculation is performed.</div>
      </div>
<?php if ($selectedPeriod && $canEdit && in_array($selectedPeriod['status'], ['draft', 'processing'], true)): ?>
      <div class="card-body border-bottom">
        <form method="post" action="<?= url('hr/payroll/items/add') ?>" class="row g-2 align-items-end">
          <?= csrf_field() ?><input type="hidden" name="payroll_period_id" value="<?= (int)$selectedPeriod['id'] ?>">
          <div class="col-lg-4"><label class="form-label">Staff</label><select class="form-select" name="staff_id" required><option value="">Choose…</option>
<?php foreach ($staff as $member): ?><option value="<?= (int)$member['id'] ?>"><?= e($member['staff_number'] . ' — ' . $member['last_name'] . ' ' . $member['first_name']) ?></option><?php endforeach; ?>
          </select></div>
          <div class="col"><label class="form-label">Basic pay</label><input class="form-control" type="number" min="0" step="0.01" name="basic_pay" required></div>
          <div class="col"><label class="form-label">Allowances</label><input class="form-control" type="number" min="0" step="0.01" name="allowances_total" value="0"></div>
          <div class="col"><label class="form-label">Other deductions</label><input class="form-control" type="number" min="0" step="0.01" name="other_deductions" value="0"></div>
          <div class="col-auto"><button class="btn btn-primary">Add pay item</button></div>
        </form>
      </div>
<?php endif; ?>
      <div class="table-responsive"><table class="table table-sm align-middle mb-0">
        <thead><tr><th>Period</th><th>Status</th><th>Staff</th><th>Basic</th><th>Gross</th><th>Deductions</th><th>Net</th></tr></thead><tbody>
<?php if (!$periods): ?><tr><td colspan="7" class="text-center text-muted py-4">No payroll periods created.</td></tr><?php endif; ?>
<?php foreach ($periods as $period): ?>
        <tr><td><a href="<?= url('hr', ['period_id' => (int)$period['id']]) ?>"><?= e($period['period_label']) ?></a></td><td><?= e($period['status']) ?></td><td colspan="5"><?= (int)$period['item_count'] ?> pay item(s)</td></tr>
<?php endforeach; ?>
<?php foreach ($payrollItems as $item): ?>
        <tr><td><?= e($selectedPeriod['period_label']) ?></td><td>—</td><td><?= e($item['last_name'] . ' ' . $item['first_name']) ?></td><td><?= e(number_format((float)$item['basic_pay'], 2)) ?></td><td><?= e(number_format((float)$item['gross_pay'], 2)) ?></td><td><?= e(number_format((float)$item['total_deductions'], 2)) ?></td><td><?= e(number_format((float)$item['net_pay'], 2)) ?></td></tr>
<?php endforeach; ?>
        </tbody>
      </table></div>
    </div>
  </div>
</div>
