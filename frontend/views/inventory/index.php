<?php
$priorityColor = fn(string $p): string => match($p) { 'urgent' => 'danger', 'high' => 'warning', 'low' => 'secondary', default => 'primary' };
$statusColor   = fn(string $s): string => match($s) { 'approved' => 'success', 'rejected' => 'danger', 'submitted' => 'primary', 'cancelled' => 'secondary', default => 'light' };
$movementLabels = ['opening' => 'Opening', 'receipt' => 'Receipt', 'issue' => 'Issue', 'return' => 'Return', 'adjustment' => 'Adjustment', 'transfer' => 'Transfer', 'loss' => 'Loss', 'expiration' => 'Expiry'];
$supplierStatusColor = fn(string $s): string => match($s) { 'active' => 'success', 'on_hold' => 'warning', 'blacklisted' => 'danger', default => 'secondary' };
$number = static fn ($value): string => rtrim(rtrim(number_format((float)$value, 2, '.', ''), '0'), '.');
$lowCount = count(array_filter($items, static fn (array $item): bool => (float)$item['current_stock'] <= (float)$item['reorder_level']));
?>
<div class="page-head">
  <div>
    <h1 class="h4 mb-0">Inventory &amp; procurement</h1>
    <p class="text-muted small mb-0">Stock levels, movements, suppliers and purchase requests.</p>
  </div>
  <div class="text-end small text-muted">
    <div><i class="bi bi-boxes me-1"></i><?= count($items) ?> item<?= count($items) === 1 ? '' : 's' ?></div>
    <div class="<?= $lowCount ? 'text-warning' : '' ?>"><i class="bi bi-exclamation-triangle me-1"></i><?= $lowCount ?> at or below reorder level</div>
  </div>
</div>

<?php if ($canAdd): ?>
<div class="modal fade" id="itemModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="post" action="<?= url('inventory/items/store') ?>" data-ajax>
        <?= csrf_field() ?>
        <div class="modal-header">
          <h5 class="modal-title">Add stock item</h5>
          <button class="btn-close" type="button" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-2 mb-2">
            <div class="col-md-4">
              <label class="form-label">Item code</label>
              <input class="form-control" name="item_code" maxlength="40" placeholder="Auto if blank">
              <span class="invalid-feedback d-block" data-error-for="item_code"></span>
            </div>
            <div class="col-md-8">
              <label class="form-label">Item name <span class="text-danger">*</span></label>
              <input class="form-control" name="name" maxlength="160" required>
              <span class="invalid-feedback d-block" data-error-for="name"></span>
            </div>
          </div>
          <div class="row g-2 mb-2">
            <div class="col-md-4">
              <label class="form-label">Category</label>
              <select class="form-select" name="category_id">
                <option value="0">Uncategorised</option>
                <?php foreach ($categories as $category): ?>
                <option value="<?= (int)$category['id'] ?>"><?= e($category['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Unit</label>
              <input class="form-control" name="unit" maxlength="30" placeholder="piece / ream / litre">
            </div>
            <div class="col-md-4">
              <label class="form-label">Unit cost (UGX)</label>
              <input class="form-control" name="unit_cost" type="number" min="0" step="100" value="0">
            </div>
          </div>
          <div class="row g-2 mb-2">
            <div class="col-md-4">
              <label class="form-label">Opening stock</label>
              <input class="form-control" name="opening_stock" type="number" min="0" step="0.01" value="0">
            </div>
            <div class="col-md-4">
              <label class="form-label">Reorder level</label>
              <input class="form-control" name="reorder_level" type="number" min="0" step="0.01" value="0">
              <span class="invalid-feedback d-block" data-error-for="reorder_level"></span>
            </div>
            <div class="col-md-4">
              <label class="form-label">Description</label>
              <input class="form-control" name="description" maxlength="255">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-primary" type="submit">Create item</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<ul class="nav nav-pills app-pills mb-3" role="tablist">
  <li class="nav-item" role="presentation"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-stock" type="button" role="tab"><i class="bi bi-box-seam me-1"></i>Stock</button></li>
  <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-suppliers" type="button" role="tab"><i class="bi bi-truck me-1"></i>Suppliers <span class="badge text-bg-light ms-1"><?= count($suppliers) ?></span></button></li>
  <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-requests" type="button" role="tab"><i class="bi bi-file-earmark-text me-1"></i>Purchase requests <span class="badge text-bg-light ms-1"><?= count($requests) ?></span></button></li>
</ul>

<div class="tab-content">
  <div class="tab-pane fade show active" id="tab-stock" role="tabpanel">
    <div class="row g-3 mb-3">
    <?php if ($canAdd): ?>
      <div class="col-xl-4">
        <div class="card app-card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <span>Record stock movement</span>
            <button class="btn btn-sm btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#itemModal"><i class="bi bi-plus-lg"></i> Item</button>
          </div>
          <div class="card-body">
            <form method="post" action="<?= url('inventory/movements/store') ?>" data-ajax>
              <?= csrf_field() ?>
              <div class="mb-2">
                <label class="form-label">Item <span class="text-danger">*</span></label>
                <select class="form-select" name="item_id" required>
                  <option value="">-- Choose item --</option>
                  <?php foreach ($items as $item): ?>
                  <option value="<?= (int)$item['id'] ?>"><?= e($item['item_code'] . ' — ' . $item['name']) ?> (<?= $number($item['current_stock']) ?> <?= e($item['unit']) ?>)</option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="row g-2 mb-2">
                <div class="col-6">
                  <label class="form-label">Movement</label>
                  <select class="form-select" name="movement_type" id="movement-type">
                    <?php foreach ($movementLabels as $value => $label): ?>
                    <option value="<?= e($value) ?>"><?= e($label) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-6">
                  <label class="form-label">Quantity</label>
                  <input class="form-control" type="number" step="0.01" name="quantity" required>
                  <div class="form-text" id="quantity-help">Positive quantity. Issues and losses reduce stock.</div>
                </div>
              </div>
              <div class="row g-2 mb-2">
                <div class="col-6">
                  <label class="form-label">Unit cost</label>
                  <input class="form-control" type="number" min="0" step="100" name="unit_cost" placeholder="Optional">
                </div>
                <div class="col-6">
                  <label class="form-label">Date</label>
                  <input class="form-control" type="date" name="movement_date" value="<?= e(date('Y-m-d')) ?>">
                </div>
              </div>
              <div class="mb-2">
                <label class="form-label">Issued to</label>
                <input class="form-control" name="issued_to" maxlength="140" placeholder="Person or department">
              </div>
              <div class="row g-2 mb-2">
                <div class="col-5">
                  <label class="form-label">Location</label>
                  <input class="form-control" name="stock_location" maxlength="80" placeholder="Store / room">
                </div>
                <div class="col-7">
                  <label class="form-label">Reason / reference</label>
                  <input class="form-control" name="reason" maxlength="255" placeholder="Delivery note, issue slip…">
                </div>
              </div>
              <button class="btn btn-primary" type="submit">Post movement</button>
            </form>
          </div>
        </div>
      </div>
    <?php endif; ?>

      <div class="<?= $canAdd ? 'col-xl-8' : 'col-12' ?>">
        <div class="card app-card">
          <div class="card-header">Stock items <span class="badge text-bg-light"><?= count($items) ?></span></div>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" data-table>
              <thead><tr><th>Code</th><th>Name</th><th>Category</th><th>In stock</th><th>Reorder</th><th>Status</th></tr></thead>
              <tbody>
                <?php if (!$items): ?><tr><td colspan="6" class="text-center text-muted py-4">No items registered yet. Use “Item” to add one.</td></tr><?php endif; ?>
                <?php foreach ($items as $item): ?>
                <?php $low = (float)$item['current_stock'] <= (float)$item['reorder_level']; ?>
                <tr class="<?= $low ? 'table-warning' : '' ?>">
                  <td><?= e($item['item_code']) ?></td>
                  <td><strong><?= e($item['name']) ?></strong><?= $low ? ' <span class="badge text-bg-warning ms-1">Low</span>' : '' ?></td>
                  <td class="small text-muted"><?= e($item['category_name'] ?? '—') ?></td>
                  <td><strong><?= $number($item['current_stock']) ?></strong> <span class="text-muted small"><?= e($item['unit']) ?></span></td>
                  <td><?= $number($item['reorder_level']) ?></td>
                  <td><span class="badge text-bg-<?= $item['status'] === 'active' ? 'success' : 'secondary' ?>"><?= e($item['status']) ?></span></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div class="card app-card">
      <div class="card-header">Recent stock movements <span class="badge text-bg-light"><?= count($movements) ?></span></div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" data-table data-order-column="1" data-order-dir="desc">
          <thead><tr><th>Item</th><th>Date</th><th>Movement</th><th>Quantity</th><th>Balance after</th><th>Issued to</th><th>Reason</th></tr></thead>
          <tbody>
            <?php if (!$movements): ?><tr><td colspan="7" class="text-center text-muted py-4">No stock movements recorded yet.</td></tr><?php endif; ?>
            <?php foreach ($movements as $movement): ?>
            <tr>
              <td><strong><?= e($movement['item_name']) ?></strong><div class="small text-muted"><?= e($movement['item_code']) ?></div></td>
              <td class="small"><?= e(date('d M Y', strtotime($movement['movement_date']))) ?></td>
              <td><span class="badge text-bg-<?= in_array($movement['movement_type'], ['receipt', 'opening', 'return'], true) ? 'success' : ($movement['movement_type'] === 'adjustment' ? 'info' : 'warning') ?>"><?= e($movementLabels[$movement['movement_type']] ?? $movement['movement_type']) ?></span></td>
              <td><?= $number($movement['quantity']) ?> <span class="text-muted small"><?= e($movement['unit']) ?></span></td>
              <td><?= $number($movement['quantity_after']) ?></td>
              <td class="small"><?= e($movement['issued_to'] ?? '—') ?></td>
              <td class="small text-muted"><?= e($movement['reason'] ?? '—') ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="tab-pane fade" id="tab-suppliers" role="tabpanel">
    <div class="row g-3">
    <?php if ($canAdd): ?>
      <div class="col-xl-4">
        <div class="card app-card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <span><?= $editSupplier ? 'Edit supplier' : 'Add supplier' ?></span>
            <?php if ($editSupplier): ?><a class="btn btn-sm btn-outline-secondary" href="<?= url('inventory') ?>">New</a><?php endif; ?>
          </div>
          <div class="card-body">
            <form method="post" action="<?= url('inventory/suppliers/save') ?>" data-ajax>
              <?= csrf_field() ?>
              <?php if ($editSupplier): ?><input type="hidden" name="id" value="<?= (int)$editSupplier['id'] ?>"><?php endif; ?>
              <div class="mb-2">
                <label class="form-label">Supplier name <span class="text-danger">*</span></label>
                <input class="form-control" name="name" maxlength="160" required value="<?= e($editSupplier['name'] ?? '') ?>">
              </div>
              <div class="row g-2 mb-2">
                <div class="col-6">
                  <label class="form-label">Contact person</label>
                  <input class="form-control" name="contact_person" maxlength="140" value="<?= e($editSupplier['contact_person'] ?? '') ?>">
                </div>
                <div class="col-6">
                  <label class="form-label">Phone</label>
                  <input class="form-control" name="phone" maxlength="40" value="<?= e($editSupplier['phone'] ?? '') ?>">
                </div>
              </div>
              <div class="row g-2 mb-2">
                <div class="col-6">
                  <label class="form-label">Alternate phone</label>
                  <input class="form-control" name="alternate_phone" maxlength="40" value="<?= e($editSupplier['alternate_phone'] ?? '') ?>">
                </div>
                <div class="col-6">
                  <label class="form-label">Email</label>
                  <input class="form-control" type="email" name="email" maxlength="120" value="<?= e($editSupplier['email'] ?? '') ?>">
                </div>
              </div>
              <div class="mb-2">
                <label class="form-label">Address</label>
                <input class="form-control" name="address" maxlength="255" value="<?= e($editSupplier['address'] ?? '') ?>">
              </div>
              <div class="row g-2 mb-2">
                <div class="col-6">
                  <label class="form-label">TIN</label>
                  <input class="form-control" name="tin" maxlength="40" value="<?= e($editSupplier['tin'] ?? '') ?>">
                </div>
                <div class="col-6">
                  <label class="form-label">Payment terms</label>
                  <input class="form-control" name="payment_terms" maxlength="120" placeholder="e.g. 30 days" value="<?= e($editSupplier['payment_terms'] ?? '') ?>">
                </div>
              </div>
              <div class="mb-2">
                <label class="form-label">Items supplied</label>
                <input class="form-control" name="items_supplied" maxlength="255" value="<?= e($editSupplier['items_supplied'] ?? '') ?>">
              </div>
              <div class="mb-2">
                <label class="form-label">Status</label>
                <select class="form-select" name="status">
                  <?php foreach (['active' => 'Active', 'on_hold' => 'On hold', 'blacklisted' => 'Blacklisted', 'former' => 'Former'] as $value => $label): ?>
                  <option value="<?= e($value) ?>" <?= ($editSupplier['status'] ?? 'active') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="mb-3">
                <label class="form-label">Notes</label>
                <textarea class="form-control" name="notes" rows="2" maxlength="255"><?= e($editSupplier['notes'] ?? '') ?></textarea>
              </div>
              <button class="btn btn-primary" type="submit"><?= $editSupplier ? 'Save changes' : 'Add supplier' ?></button>
            </form>
          </div>
        </div>
      </div>
    <?php endif; ?>

      <div class="<?= $canAdd ? 'col-xl-8' : 'col-12' ?>">
        <div class="card app-card">
          <div class="card-header">Suppliers <span class="badge text-bg-light"><?= count($suppliers) ?></span></div>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" data-table>
              <thead><tr><th>Supplier</th><th>Contact</th><th>Items supplied</th><th>Orders</th><th>Status</th><?php if ($canAdd || $canEdit): ?><th></th><?php endif; ?></tr></thead>
              <tbody>
                <?php if (!$suppliers): ?><tr><td colspan="<?= ($canAdd || $canEdit) ? 6 : 5 ?>" class="text-center text-muted py-4">No suppliers registered yet.</td></tr><?php endif; ?>
                <?php foreach ($suppliers as $supplier): ?>
                <tr>
                  <td><strong><?= e($supplier['name']) ?></strong><div class="small text-muted"><?= e($supplier['email'] ?? ($supplier['address'] ?? '')) ?></div></td>
                  <td class="small"><?= e($supplier['contact_person'] ?? '—') ?><div class="text-muted"><?= e($supplier['phone'] ?? '') ?></div></td>
                  <td class="small"><?= e($supplier['items_supplied'] ?? '—') ?></td>
                  <td><?= (int)$supplier['total_orders'] ?></td>
                  <td>
                    <?php if ($canEdit): ?>
                    <form method="post" action="<?= url('inventory/suppliers/status') ?>" data-ajax>
                      <?= csrf_field() ?>
                      <input type="hidden" name="id" value="<?= (int)$supplier['id'] ?>">
                      <select class="form-select form-select-sm" name="status" onchange="this.form.requestSubmit()" style="width:auto">
                        <?php foreach (['active' => 'Active', 'on_hold' => 'On hold', 'blacklisted' => 'Blacklisted', 'former' => 'Former'] as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= $supplier['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </form>
                    <?php else: ?>
                    <span class="badge text-bg-<?= $supplierStatusColor($supplier['status']) ?>"><?= e(str_replace('_', ' ', $supplier['status'])) ?></span>
                    <?php endif; ?>
                  </td>
                  <?php if ($canAdd || $canEdit): ?>
                  <td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="<?= url('inventory', ['edit_supplier' => (int)$supplier['id']]) ?>#tab-suppliers"><i class="bi bi-pencil"></i></a></td>
                  <?php endif; ?>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="tab-pane fade" id="tab-requests" role="tabpanel">
    <div class="row g-3">
    <?php if ($canAdd): ?>
      <div class="col-xl-4">
        <div class="card app-card">
          <div class="card-header">New purchase request</div>
          <div class="card-body">
            <form method="post" action="<?= url('inventory/requests/create') ?>" data-ajax>
              <?= csrf_field() ?>
              <div class="form-section"><i class="bi bi-file-earmark-text"></i> Request details</div>
              <div class="mb-2">
                <label class="form-label">Justification <span class="text-danger">*</span></label>
                <textarea class="form-control" name="justification" rows="3" required maxlength="1000"></textarea>
                <span class="invalid-feedback d-block" data-error-for="justification"></span>
              </div>
              <div class="form-section"><i class="bi bi-calendar-range"></i> Timing &amp; cost</div>
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
  </div>
</div>

<?php if ($editSupplier): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var tab = document.querySelector('[data-bs-target="#tab-suppliers"]');
  if (tab && window.bootstrap) { bootstrap.Tab.getOrCreateInstance(tab).show(); }
});
</script>
<?php endif; ?>
<?php if ($canAdd): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var type = document.getElementById('movement-type');
  var help = document.getElementById('quantity-help');
  if (!type || !help) { return; }
  function updateHelp() {
    if (type.value === 'adjustment') {
      help.textContent = 'Signed quantity: use -5 to reduce stock, 5 to add stock.';
    } else if (['issue', 'loss', 'expiration', 'transfer'].includes(type.value)) {
      help.textContent = 'Positive quantity. This movement reduces stock.';
    } else {
      help.textContent = 'Positive quantity. This movement adds stock.';
    }
  }
  type.addEventListener('change', updateHelp);
  updateHelp();
});
</script>
<?php endif; ?>
