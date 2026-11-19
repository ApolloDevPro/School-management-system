<?php
$stopsByRoute = [];
foreach ($stops as $stop) {
    $stopsByRoute[(int)$stop['route_id']][] = $stop;
}
$activeAllocations = count(array_filter($allocations, static fn (array $row): bool => $row['status'] === 'active'));
$money = static fn ($value): string => number_format((float)$value, 0);
$filterParams = ['year_id' => $yearId, 'term_id' => $termId];
?>
<div class="page-head">
  <div>
    <h1 class="h4 mb-0">Transport</h1>
    <p class="text-muted small mb-0">Routes, student allocation, route sheets and term billing.</p>
  </div>
  <div class="text-end small text-muted">
    <div><i class="bi bi-people me-1"></i><?= $activeAllocations ?> active allocation<?= $activeAllocations === 1 ? '' : 's' ?></div>
    <div><i class="bi bi-cash-coin me-1"></i><?= count($charges) ?> charge rule<?= count($charges) === 1 ? '' : 's' ?></div>
  </div>
</div>

<?php if (!$yearId || !$termId): ?>
<div class="alert alert-warning d-flex gap-2 align-items-start">
  <i class="bi bi-exclamation-triangle mt-1"></i>
  <div><strong>Set up the academic calendar first.</strong> Transport allocation and billing need an academic year and term. Open <a href="<?= url('academics') ?>">Curriculum &amp; subjects</a> to add them.</div>
</div>
<?php else: ?>
<div class="card app-card mb-3">
  <div class="card-body py-3">
    <form method="get" action="<?= url('transport') ?>" class="row g-2 align-items-end">
      <div class="col-sm-4 col-lg-3">
        <label class="form-label small text-muted mb-1">Academic year</label>
        <select class="form-select" name="year_id" data-submit-on-change>
          <?php foreach ($years as $year): ?>
          <option value="<?= (int)$year['id'] ?>" <?= (int)$year['id'] === $yearId ? 'selected' : '' ?>><?= e($year['name']) ?><?= (int)$year['is_active'] === 1 ? ' (active)' : '' ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-sm-4 col-lg-3">
        <label class="form-label small text-muted mb-1">Term</label>
        <select class="form-select" name="term_id" data-submit-on-change>
          <?php foreach ($terms as $term): ?>
          <option value="<?= (int)$term['id'] ?>" <?= (int)$term['id'] === $termId ? 'selected' : '' ?>><?= e($term['name']) ?><?= (int)$term['is_active'] === 1 ? ' (active)' : '' ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-sm-4 col-lg-2">
        <button class="btn btn-outline-secondary w-100" type="submit"><i class="bi bi-arrow-repeat me-1"></i>Apply</button>
      </div>
    </form>
  </div>
</div>

<ul class="nav nav-pills app-pills mb-3" role="tablist">
  <li class="nav-item" role="presentation"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-routes" type="button" role="tab"><i class="bi bi-signpost-split me-1"></i>Routes</button></li>
  <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-students" type="button" role="tab"><i class="bi bi-people me-1"></i>Students <span class="badge text-bg-light ms-1"><?= count($allocations) ?></span></button></li>
  <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-charges" type="button" role="tab"><i class="bi bi-receipt me-1"></i>Charges &amp; billing</button></li>
</ul>

<div class="tab-content">
  <div class="tab-pane fade show active" id="tab-routes" role="tabpanel">
    <div class="row g-3">
    <?php if ($canAdd): ?>
      <div class="col-xl-4">
        <div class="card app-card">
          <div class="card-header">Add route</div>
          <div class="card-body">
            <form method="post" action="<?= url('transport/routes/store') ?>" data-ajax>
              <?= csrf_field() ?>
              <div class="form-section"><i class="bi bi-truck"></i> Route details</div>
              <div class="row g-2 mb-2">
                <div class="col-5">
                  <label class="form-label">Route code</label>
                  <input class="form-control" name="route_code" maxlength="20" placeholder="e.g. RT-01">
                </div>
                <div class="col-7">
                  <label class="form-label">Route name <span class="text-danger">*</span></label>
                  <input class="form-control" name="route_name" maxlength="160" required placeholder="e.g. Town via Kampala Rd">
                  <span class="invalid-feedback d-block" data-error-for="route_name"></span>
                </div>
              </div>
              <div class="mb-2">
                <label class="form-label">Description</label>
                <textarea class="form-control" name="description" rows="2" maxlength="500"></textarea>
              </div>
              <div class="form-section"><i class="bi bi-cash-stack"></i> Distance &amp; fare</div>
              <div class="row g-2 mb-3">
                <div class="col">
                  <label class="form-label">Distance (km)</label>
                  <input class="form-control" name="distance_km" type="number" step="0.1" min="0" value="0">
                  <span class="invalid-feedback d-block" data-error-for="distance_km"></span>
                </div>
                <div class="col">
                  <label class="form-label">Default fare / term (UGX)</label>
                  <input class="form-control" name="fare_per_term" type="number" step="1000" min="0" value="0">
                  <span class="invalid-feedback d-block" data-error-for="fare_per_term"></span>
                </div>
              </div>
              <button class="btn btn-primary" type="submit">Add route</button>
            </form>
          </div>
        </div>
      </div>
    <?php endif; ?>

      <div class="<?= $canAdd ? 'col-xl-8' : 'col-12' ?>">
        <div class="card app-card">
          <div class="card-header">Routes <span class="badge text-bg-light"><?= count($routes) ?></span></div>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" data-table>
              <thead><tr><th>Code</th><th>Name</th><th>Distance</th><th>Default fare</th><th>Status</th><?php if ($canEdit): ?><th>Route sheet</th><?php endif; ?></tr></thead>
              <tbody>
                <?php if (!$routes): ?><tr><td colspan="<?= $canEdit ? 6 : 5 ?>" class="text-center text-muted py-4">No routes configured yet.</td></tr><?php endif; ?>
                <?php foreach ($routes as $route): ?>
                <tr>
                  <td><?= e($route['route_code'] ?? '—') ?></td>
                  <td><strong><?= e($route['route_name']) ?></strong></td>
                  <td><?= $route['distance_km'] > 0 ? number_format((float)$route['distance_km'], 1) . ' km' : '—' ?></td>
                  <td><?= $route['fare_per_term'] > 0 ? number_format((float)$route['fare_per_term']) : '—' ?></td>
                  <td>
                    <?php if ($canEdit): ?>
                    <form method="post" action="<?= url('transport/routes/toggle') ?>" data-ajax>
                      <?= csrf_field() ?>
                      <input type="hidden" name="id" value="<?= (int)$route['id'] ?>">
                      <select class="form-select form-select-sm" name="status" onchange="this.form.requestSubmit()" style="width:auto">
                        <option value="active"    <?= $route['status'] === 'active'    ? 'selected' : '' ?>>Active</option>
                        <option value="inactive"  <?= $route['status'] === 'inactive'  ? 'selected' : '' ?>>Inactive</option>
                        <option value="suspended" <?= $route['status'] === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                      </select>
                    </form>
                    <?php else: ?>
                    <span class="badge text-bg-<?= $route['status'] === 'active' ? 'success' : ($route['status'] === 'suspended' ? 'warning' : 'secondary') ?>"><?= e($route['status']) ?></span>
                    <?php endif; ?>
                  </td>
                  <?php if ($canEdit): ?>
                  <td class="text-end text-nowrap">
                    <a class="btn btn-sm btn-outline-secondary" href="<?= url('transport/route-sheet', ['route_id' => (int)$route['id']]) ?>" target="_blank"><i class="bi bi-file-earmark-text"></i> Sheet</a>
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

  <div class="tab-pane fade" id="tab-students" role="tabpanel">
    <div class="row g-3">
    <?php if ($canAdd): ?>
      <div class="col-xl-4">
        <div class="card app-card">
          <div class="card-header">Allocate a student</div>
          <div class="card-body">
            <form method="post" action="<?= url('transport/allocations/save') ?>" data-ajax>
              <?= csrf_field() ?>
              <input type="hidden" name="year_id" value="<?= $yearId ?>">
              <input type="hidden" name="term_id" value="<?= $termId ?>">
              <div class="mb-2">
                <label class="form-label">Student <span class="text-danger">*</span></label>
                <select class="form-select" name="student_id" required>
                  <option value="">-- Choose student --</option>
                  <?php foreach ($students as $student): ?>
                  <option value="<?= (int)$student['id'] ?>"><?= e($student['admission_number'] . ' — ' . $student['first_name'] . ' ' . $student['last_name']) ?><?= $student['class_name'] ? ' (' . e($student['class_name']) . ')' : '' ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="mb-2">
                <label class="form-label">Route <span class="text-danger">*</span></label>
                <select class="form-select" name="route_id" id="allocation-route" required>
                  <option value="">-- Choose route --</option>
                  <?php foreach ($routes as $route): ?>
                  <?php if ($route['status'] === 'active'): ?>
                  <option value="<?= (int)$route['id'] ?>"><?= e(($route['route_code'] ? $route['route_code'] . ' — ' : '') . $route['route_name']) ?></option>
                  <?php endif; ?>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="mb-2">
                <label class="form-label">Stop</label>
                <select class="form-select" name="stop_id" id="allocation-stop">
                  <option value="">No fixed stop</option>
                </select>
                <div class="form-text">Stops appear after you choose a route. Add a pickup point below when the route has no stop list.</div>
              </div>
              <div class="mb-2">
                <label class="form-label">Pickup point</label>
                <input class="form-control" name="pickup_point" maxlength="160" placeholder="e.g. Kireka trading centre">
              </div>
              <div class="mb-3">
                <label class="form-label">Notes</label>
                <input class="form-control" name="notes" maxlength="255" placeholder="Optional note for the driver">
              </div>
              <button class="btn btn-primary" type="submit">Save allocation</button>
            </form>
          </div>
        </div>
      </div>
    <?php endif; ?>

      <div class="<?= $canAdd ? 'col-xl-8' : 'col-12' ?>">
        <div class="card app-card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <span>Allocated students <span class="badge text-bg-light"><?= count($allocations) ?></span></span>
            <span class="small text-muted"><?= e(($terms[0]['name'] ?? 'Term') . ' · ' . ($years[0]['name'] ?? 'Year')) ?></span>
          </div>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" data-table>
              <thead><tr><th>Student</th><th>Class</th><th>Route</th><th>Stop / pickup</th><th>Status</th><th>Allocated</th><?php if ($canEdit): ?><th></th><?php endif; ?></tr></thead>
              <tbody>
                <?php if (!$allocations): ?><tr><td colspan="<?= $canEdit ? 7 : 6 ?>" class="text-center text-muted py-4">No students allocated for this term yet.</td></tr><?php endif; ?>
                <?php foreach ($allocations as $row): ?>
                <tr>
                  <td><strong><?= e($row['first_name'] . ' ' . $row['last_name']) ?></strong><div class="small text-muted"><?= e($row['admission_number']) ?></div></td>
                  <td><?= e($row['class_name'] ?? '—') ?></td>
                  <td><?= e(($row['route_code'] ? $row['route_code'] . ' — ' : '') . $row['route_name']) ?></td>
                  <td>
                    <?php if ($row['stop_name']): ?>
                      <?= e($row['stop_name']) ?>
                      <div class="small text-muted"><?= e(substr((string)$row['pick_up_time'], 0, 5)) ?> pick-up</div>
                    <?php else: ?>
                      <?= e($row['pickup_point'] ?? '—') ?>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if ($canEdit): ?>
                    <form method="post" action="<?= url('transport/allocations/status') ?>" data-ajax>
                      <?= csrf_field() ?>
                      <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                      <select class="form-select form-select-sm" name="status" onchange="this.form.requestSubmit()" style="width:auto">
                        <option value="active"    <?= $row['status'] === 'active'    ? 'selected' : '' ?>>Active</option>
                        <option value="inactive"  <?= $row['status'] === 'inactive'  ? 'selected' : '' ?>>Inactive</option>
                        <option value="suspended" <?= $row['status'] === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                      </select>
                    </form>
                    <?php else: ?>
                    <span class="badge text-bg-<?= $row['status'] === 'active' ? 'success' : ($row['status'] === 'suspended' ? 'warning' : 'secondary') ?>"><?= e($row['status']) ?></span>
                    <?php endif; ?>
                  </td>
                  <td class="small"><?= e(date('d M Y', strtotime($row['allocated_at']))) ?></td>
                  <?php if ($canEdit): ?>
                  <td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="<?= url('transport/route-sheet', ['route_id' => (int)$row['route_id']]) ?>" target="_blank"><i class="bi bi-printer"></i></a></td>
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

  <div class="tab-pane fade" id="tab-charges" role="tabpanel">
    <div class="row g-3">
    <?php if ($canEdit): ?>
      <div class="col-xl-4">
        <div class="card app-card mb-3">
          <div class="card-header">Charge rule</div>
          <div class="card-body">
            <form method="post" action="<?= url('transport/charges/save') ?>" data-ajax>
              <?= csrf_field() ?>
              <input type="hidden" name="year_id" value="<?= $yearId ?>">
              <div class="mb-2">
                <label class="form-label">Route <span class="text-danger">*</span></label>
                <select class="form-select" name="route_id" required>
                  <option value="">-- Choose route --</option>
                  <?php foreach ($routes as $route): ?>
                  <option value="<?= (int)$route['id'] ?>"><?= e(($route['route_code'] ? $route['route_code'] . ' — ' : '') . $route['route_name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="row g-2 mb-2">
                <div class="col">
                  <label class="form-label">Class</label>
                  <select class="form-select" name="class_id">
                    <option value="0">All classes</option>
                    <?php foreach ($classes as $class): ?>
                    <option value="<?= (int)$class['id'] ?>"><?= e($class['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col">
                  <label class="form-label">Term</label>
                  <select class="form-select" name="term_id">
                    <?php foreach ($terms as $term): ?>
                    <option value="<?= (int)$term['id'] ?>" <?= (int)$term['id'] === $termId ? 'selected' : '' ?>><?= e($term['name']) ?></option>
                    <?php endforeach; ?>
                    <option value="0">Whole year</option>
                  </select>
                </div>
              </div>
              <div class="row g-2 mb-2">
                <div class="col-7">
                  <label class="form-label">Amount (UGX) <span class="text-danger">*</span></label>
                  <input class="form-control" name="amount" type="number" min="0" step="1000" required value="0">
                </div>
                <div class="col-5">
                  <label class="form-label">Basis</label>
                  <select class="form-select" name="charge_basis">
                    <option value="per_term">Per term</option>
                    <option value="per_trip">Per trip</option>
                    <option value="per_distance">Per distance</option>
                    <option value="annual">Annual</option>
                  </select>
                </div>
              </div>
              <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="is_active" id="charge-active" checked>
                <label class="form-check-label" for="charge-active">Use this rule when billing</label>
              </div>
              <button class="btn btn-primary" type="submit">Save charge</button>
            </form>
          </div>
        </div>

        <?php if ($canApprove): ?>
        <div class="card app-card">
          <div class="card-header">Raise term invoices</div>
          <div class="card-body">
            <p class="small text-muted">Every active allocation gets one transport invoice for the selected term. Students already billed for the same route are skipped.</p>
            <form method="post" action="<?= url('transport/charges/bill') ?>" data-ajax onsubmit="return confirm('Raise transport invoices for all active allocations this term?');">
              <?= csrf_field() ?>
              <input type="hidden" name="year_id" value="<?= $yearId ?>">
              <input type="hidden" name="term_id" value="<?= $termId ?>">
              <div class="mb-3">
                <label class="form-label">Due date</label>
                <input class="form-control" type="date" name="due_date" value="<?= e(date('Y-m-d', strtotime('+14 days'))) ?>">
              </div>
              <button class="btn btn-success w-100" type="submit"><i class="bi bi-receipt-cutoff me-1"></i>Bill selected term</button>
            </form>
          </div>
        </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

      <div class="<?= $canEdit ? 'col-xl-8' : 'col-12' ?>">
        <div class="card app-card">
          <div class="card-header">Charge rules <span class="badge text-bg-light"><?= count($charges) ?></span></div>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" data-table>
              <thead><tr><th>Route</th><th>Class</th><th>Term</th><th>Basis</th><th>Amount</th><th>Active</th></tr></thead>
              <tbody>
                <?php if (!$charges): ?><tr><td colspan="6" class="text-center text-muted py-4">No charge rules yet. The route default fare is used when no rule matches.</td></tr><?php endif; ?>
                <?php foreach ($charges as $charge): ?>
                <tr>
                  <td><strong><?= e($charge['route_name']) ?></strong></td>
                  <td><?= e($charge['class_name'] ?? 'All classes') ?></td>
                  <td><?= e($charge['term_name'] ?? 'Whole year') ?></td>
                  <td><?= e(str_replace('_', ' ', ucfirst($charge['charge_basis']))) ?></td>
                  <td><?= $money($charge['amount']) ?></td>
                  <td><span class="badge text-bg-<?= (int)$charge['is_active'] === 1 ? 'success' : 'secondary' ?>"><?= (int)$charge['is_active'] === 1 ? 'Yes' : 'No' ?></span></td>
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

<script>
window.APP.transportStops = <?= json_encode($stopsByRoute, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
document.addEventListener('DOMContentLoaded', function () {
  var routeSelect = document.getElementById('allocation-route');
  var stopSelect = document.getElementById('allocation-stop');
  if (!routeSelect || !stopSelect) { return; }
  function fillStops() {
    var stops = (window.APP.transportStops || {})[routeSelect.value] || [];
    stopSelect.innerHTML = '<option value="">No fixed stop</option>';
    stops.forEach(function (stop) {
      var option = document.createElement('option');
      option.value = stop.id;
      var time = stop.pick_up_time ? ' · ' + String(stop.pick_up_time).slice(0, 5) : '';
      option.textContent = stop.stop_name + time;
      stopSelect.appendChild(option);
    });
  }
  routeSelect.addEventListener('change', fillStops);
  fillStops();
});
</script>
<?php endif; ?>
