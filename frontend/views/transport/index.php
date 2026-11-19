<div class="page-head">
  <div><h1 class="h4 mb-0">Transport</h1><p class="text-muted small mb-0">Manage routes, vehicles and student transport allocation.</p></div>
</div>

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
              <label class="form-label">Fare / term (UGX)</label>
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
          <thead><tr><th>Code</th><th>Name</th><th>Distance</th><th>Fare / term</th><th>Status</th><?php if ($canEdit): ?><th>Route sheet</th><?php endif; ?></tr></thead>
          <tbody>
            <?php if (!$routes): ?><tr><td colspan="<?= $canEdit ? 7 : 6 ?>" class="text-center text-muted py-4">No routes configured yet.</td></tr><?php endif; ?>
            <?php foreach ($routes as $route): ?>
            <tr>
              <td><?= e($route['route_code'] ?? '—') ?></td>
              <td><strong><?= e($route['route_name']) ?></strong></td>
              <td><?= $route['distance_km'] > 0 ? number_format((float)$route['distance_km'], 1) . ' km' : '—' ?></td>
              <td><?= $route['fare_per_term'] > 0 ? number_format((float)$route['fare_per_term']) : '—' ?></td>
              <td><span class="badge text-bg-<?= $route['status'] === 'active' ? 'success' : ($route['status'] === 'suspended' ? 'warning' : 'secondary') ?>"><?= e($route['status']) ?></span></td>
              <?php if ($canEdit): ?>
              <td>
                <form method="post" action="<?= url('transport/routes/toggle') ?>" data-ajax>
                  <?= csrf_field() ?>
                  <input type="hidden" name="id" value="<?= (int)$route['id'] ?>">
                  <select class="form-select form-select-sm" name="status" onchange="this.form.requestSubmit()" style="width:auto">
                    <option value="active"    <?= $route['status'] === 'active'    ? 'selected' : '' ?>>Active</option>
                    <option value="inactive"  <?= $route['status'] === 'inactive'  ? 'selected' : '' ?>>Inactive</option>
                    <option value="suspended" <?= $route['status'] === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                  </select>
                </form>
              </td>
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
