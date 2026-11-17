<?php
$user = Auth::user();
$can = function (string $module, string $action = 'view') use ($user): bool {
    return Auth::can($module, $action);
};
?>
<div class="page-head">
  <div>
    <h1 class="h4 mb-0">Inventory Dashboard</h1>
    <p class="text-muted small mb-0">Stock items, purchase requests and low-stock alerts.</p>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-6 col-xl-3">
    <div class="card stat-card tone-primary">
      <div class="stat-label">Items</div>
      <div class="stat-value"><?= number_format($stats['items']) ?></div>
      <div class="stat-note">Catalogued items</div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="card stat-card tone-primary">
      <div class="stat-label">Low stock</div>
      <div class="stat-value stat-<?= $stats['low_stock'] > 0 ? 'warning' : 'success' ?>"><?= number_format($stats['low_stock']) ?></div>
      <div class="stat-note">At or below reorder level</div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="card stat-card tone-primary">
      <div class="stat-label">Requests</div>
      <div class="stat-value"><?= number_format($stats['requests']) ?></div>
      <div class="stat-note">Purchase requests</div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="card stat-card tone-primary">
      <div class="stat-label">Draft requests</div>
      <div class="stat-value"><?= number_format($stats['pending']) ?></div>
      <div class="stat-note">Awaiting approval</div>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="card app-card h-100">
      <div class="card-header">Stock items</div>
      <div class="card-body p-0">
<?php if (!$items): ?>
        <div class="text-muted text-center p-3">No items in inventory.</div>
<?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead>
              <tr>
                <th>Item</th>
                <th style="width:14%">Code</th>
                <th style="width:14%">Unit</th>
                <th style="width:16%">In stock</th>
                <th style="width:18%">Reorder level</th>
                <th style="width:14%">Status</th>
              </tr>
            </thead>
            <tbody>
<?php foreach ($items as $it): ?>
              <tr>
                <td><?= e($it['name']) ?></td>
                <td><?= e($it['item_code'] ?? '—') ?></td>
                <td><?= e($it['unit'] ?? '—') ?></td>
                <td><?= number_format((int)$it['current_stock']) ?></td>
                <td><?= number_format((int)$it['reorder_level']) ?></td>
                <td><span class="badge text-bg-<?= ((int)$it['current_stock'] <= (int)$it['reorder_level']) ? 'warning' : 'secondary' ?>"><?= e($it['status']) ?></span></td>
              </tr>
<?php endforeach; ?>
            </tbody>
          </table>
        </div>
<?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card app-card h-100">
      <div class="card-header">Purchase requests</div>
      <div class="card-body p-0">
<?php if (!$requests): ?>
        <div class="text-muted text-center p-3">No purchase requests yet.</div>
<?php else: ?>
        <div class="list-group list-group-flush">
<?php foreach ($requests as $r): ?>
          <div class="list-group-item">
            <div class="d-flex justify-content-between align-items-start">
              <div>
                <div class="fw-semibold"><?= e($r['request_number']) ?></div>
                <div class="small text-muted"><?= e($r['justification']) ?></div>
              </div>
              <div class="text-end">
                <div class="fw-semibold"><?= number_format((float)($r['estimated_total'] ?? 0)) ?></div>
                <span class="badge text-bg-secondary"><?= e($r['status']) ?></span>
              </div>
            </div>
          </div>
<?php endforeach; ?>
        </div>
<?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-12">
    <div class="card app-card">
      <div class="card-header">Quick actions</div>
      <div class="card-body">
        <div class="row g-2">
<?php if ($can('inventory', 'add')): ?>
          <div class="col-sm-6">
            <a href="<?= url('inventory') ?>" class="btn btn-outline-primary w-100">Create request</a>
          </div>
<?php endif; ?>
<?php if ($can('inventory', 'edit')): ?>
          <div class="col-sm-6">
            <a href="<?= url('inventory') ?>" class="btn btn-outline-secondary w-100">Approve request</a>
          </div>
<?php endif; ?>


        </div>
      </div>
    </div>
  </div>
</div>
