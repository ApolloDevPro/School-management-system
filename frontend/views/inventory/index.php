<?php
$priorityColor = fn(string $p): string => match($p) { 'urgent' => 'danger', 'high' => 'warning', 'low' => 'secondary', default => 'primary' };
$statusColor   = fn(string $s): string => match($s) { 'approved' => 'success', 'rejected' => 'danger', 'submitted' => 'primary', 'cancelled' => 'secondary', default => 'light' };
?>
<div class="page-head">
  <div><h1 class="h4 mb-0">Inventory &amp; procurement</h1><p class="text-muted small mb-0">Track stock levels and manage purchase requests.</p></div>
</div>

<div class="row g-3 mb-3">
  <div class="col-12">
    <div class="card app-card">
      <div class="card-header">Stock items <span class="badge text-bg-light"><?= count($items) ?></span></div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" data-table>
          <thead><tr><th>Code</th><th>Name</th><th>Unit</th><th>In stock</th><th>Reorder level</th><th>Status</th></tr></thead>
          <tbody>
            <?php if (!$items): ?><tr><td colspan="6" class="text-center text-muted py-4">No items registered yet.</td></tr><?php endif; ?>
            <?php foreach ($items as $item): ?>
            <?php $low = (int)$item['current_stock'] <= (int)$item['reorder_level']; ?>
            <tr class="<?= $low ? 'table-warning' : '' ?>">
              <td><?= e($item['item_code']) ?></td>
              <td><strong><?= e($item['name']) ?></strong><?= $low ? ' <span class="badge text-bg-warning ms-1">Low</span>' : '' ?></td>
              <td><?= e($item['unit']) ?></td>
              <td><?= (int)$item['current_stock'] ?></td>
              <td><?= (int)$item['reorder_level'] ?></td>
              <td><span class="badge text-bg-<?= $item['status'] === 'active' ? 'success' : 'secondary' ?>"><?= e($item['status']) ?></span></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<div class="row g-3">
<?php if ($canAdd): ?>
  <div class="col-xl-4">
    <div class="card app-card">
      <div class="card-header">New purchase request</div>
      <div class="card-body">
        <form method="post" action="<?= url('inventory/requests/create') ?>" data-ajax>
          <?= csrf_field() ?>
          <div class="mb-2">
            <label class="form-label">Justification <span class="text-danger">*</span></label>
            <textarea class="form-control" name="justification" rows="3" required maxlength="1000"></textarea>
            <span class="invalid-feedback d-block" data-error-for="justification"></span>
          </div>
          <div class="row g-2 mb-2">
            <div class="col">
              <label class="form-label">Needed by</label>
              <input class="form-control" name="needed_by" type="date">
              <span class="invalid-feedback d-block" data-error-for="needed_by"></span>
            </div>
            <div class="col">
              <label class="form-label">Priority</label>
              <select class="form-select" name="priority">
                <option value="low">Low</option>
                <option value="normal" selected>Normal</option>
                <option value="high">High</option>
                <option value="urgent">Urgent</option>
              </select>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Estimated total (UGX)</label>
            <input class="form-control" name="estimated_total" type="number" min="0" step="1000" value="0">
          </div>
          <button class="btn btn-primary" type="submit">Save as draft</button>
        </form>
      </div>
    </div>
  </div>
<?php endif; ?>

  <div class="<?= $canAdd ? 'col-xl-8' : 'col-12' ?>">
    <div class="card app-card">
      <div class="card-header">Purchase requests <span class="badge text-bg-light"><?= count($requests) ?></span></div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" data-table>
          <thead><tr><th>Request #</th><th>Date</th><th>Needed by</th><th>Priority</th><th>Amount</th><th>Status</th><?php if ($canEdit || $canApprove): ?><th></th><?php endif; ?></tr></thead>
          <tbody>
            <?php if (!$requests): ?><tr><td colspan="7" class="text-center text-muted py-4">No purchase requests yet.</td></tr><?php endif; ?>
            <?php foreach ($requests as $req): ?>
            <tr>
              <td><strong><?= e($req['request_number']) ?></strong><div class="small text-muted text-truncate" style="max-width:160px"><?= e($req['justification']) ?></div></td>
              <td class="small"><?= e(date('d M Y', strtotime($req['request_date']))) ?></td>
              <td class="small"><?= $req['needed_by'] ? e(date('d M Y', strtotime($req['needed_by']))) : '—' ?></td>
              <td><span class="badge text-bg-<?= $priorityColor($req['priority']) ?>"><?= e($req['priority']) ?></span></td>
              <td><?= $req['estimated_total'] > 0 ? number_format((float)$req['estimated_total']) : '—' ?></td>
              <td><span class="badge text-bg-<?= $statusColor($req['status']) ?>"><?= e($req['status']) ?></span></td>
              <?php if ($canEdit || $canApprove): ?>
              <td class="text-nowrap">
                <?php if ($req['status'] === 'draft' && $canEdit): ?>
                  <form method="post" action="<?= url('inventory/requests/approve') ?>" data-ajax class="d-inline">
                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$req['id'] ?>"><input type="hidden" name="action" value="submit">
                    <button class="btn btn-sm btn-primary">Submit</button>
                  </form>
                  <form method="post" action="<?= url('inventory/requests/approve') ?>" data-ajax class="d-inline">
                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$req['id'] ?>"><input type="hidden" name="action" value="cancel">
                    <button class="btn btn-sm btn-outline-secondary">Cancel</button>
                  </form>
                <?php elseif ($req['status'] === 'submitted' && $canApprove): ?>
                  <form method="post" action="<?= url('inventory/requests/approve') ?>" data-ajax class="d-inline">
                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$req['id'] ?>"><input type="hidden" name="action" value="approve">
                    <button class="btn btn-sm btn-success">Approve</button>
                  </form>
                  <form method="post" action="<?= url('inventory/requests/approve') ?>" data-ajax class="d-inline">
                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$req['id'] ?>"><input type="hidden" name="action" value="reject">
                    <button class="btn btn-sm btn-outline-danger">Reject</button>
                  </form>
                <?php else: ?>
                  <span class="text-muted small">—</span>
                <?php endif; ?>
              </td>
              <?php endif; ?>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
