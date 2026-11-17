<div class="page-head">
  <div><h1 class="h4 mb-0">Gate &amp; visitors</h1><p class="text-muted small mb-0">Log visitor arrivals and departures at the school gate.</p></div>
</div>

<div class="row g-3">
<?php if ($canAdd): ?>
  <div class="col-xl-4">
    <div class="card app-card">
      <div class="card-header">Check in visitor</div>
      <div class="card-body">
        <form method="post" action="<?= url('gate/checkin') ?>" data-ajax>
          <?= csrf_field() ?>
          <div class="mb-2">
            <label class="form-label">Full name <span class="text-danger">*</span></label>
            <input class="form-control" name="full_name" maxlength="160" required placeholder="Visitor's full name">
            <span class="invalid-feedback d-block" data-error-for="full_name"></span>
          </div>
          <div class="row g-2 mb-2">
            <div class="col">
              <label class="form-label">Phone</label>
              <input class="form-control" name="phone" maxlength="40" placeholder="+256…">
            </div>
            <div class="col">
              <label class="form-label">Organisation</label>
              <input class="form-control" name="organisation" maxlength="160">
            </div>
          </div>
          <div class="mb-2">
            <label class="form-label">Person visiting <span class="text-danger">*</span></label>
            <input class="form-control" name="host_name" maxlength="160" required placeholder="Name of staff / student">
            <span class="invalid-feedback d-block" data-error-for="host_name"></span>
          </div>
          <div class="mb-2">
            <label class="form-label">Purpose of visit <span class="text-danger">*</span></label>
            <input class="form-control" name="purpose" maxlength="255" required>
            <span class="invalid-feedback d-block" data-error-for="purpose"></span>
          </div>
          <div class="row g-2 mb-3">
            <div class="col">
              <label class="form-label">Vehicle plate</label>
              <input class="form-control" name="vehicle_plate" maxlength="20">
            </div>
            <div class="col">
              <label class="form-label">Badge no.</label>
              <input class="form-control" name="badge_number" maxlength="20">
            </div>
          </div>
          <button class="btn btn-primary" type="submit">Check in</button>
        </form>
      </div>
    </div>
  </div>
<?php endif; ?>

  <div class="<?= $canAdd ? 'col-xl-8' : 'col-12' ?>">
    <div class="card app-card">
      <div class="card-header">Visitor log <span class="badge text-bg-light"><?= count($visitors) ?></span></div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" data-table>
          <thead><tr><th>Visitor</th><th>Purpose</th><th>Visiting</th><th>Time in</th><th>Time out</th><th>Status</th><?php if ($canEdit): ?><th>Pass</th><?php endif; ?></tr></thead>
          <tbody>
            <?php if (!$visitors): ?><tr><td colspan="<?= $canEdit ? 8 : 7 ?>" class="text-center text-muted py-4">No visitor records yet.</td></tr><?php endif; ?>
            <?php foreach ($visitors as $v): ?>
            <tr>
              <td>
                <strong><?= e($v['full_name']) ?></strong>
                <?php if ($v['organisation']): ?><div class="small text-muted"><?= e($v['organisation']) ?></div><?php endif; ?>
              </td>
              <td><?= e($v['purpose']) ?></td>
              <td><?= e($v['host_name'] ?? '—') ?></td>
              <td class="small"><?= $v['time_in'] ? e(date('d M H:i', strtotime($v['time_in']))) : '—' ?></td>
              <td class="small"><?= $v['time_out'] ? e(date('d M H:i', strtotime($v['time_out']))) : '<span class="text-success fw-semibold">Still inside</span>' ?></td>
              <td><span class="badge text-bg-<?= $v['status'] === 'inside' ? 'success' : 'secondary' ?>"><?= e($v['status']) ?></span></td>
              <?php if ($canEdit): ?>
              <td>
                <?php if ($v['status'] === 'inside'): ?>
                <form method="post" action="<?= url('gate/checkout') ?>" data-ajax>
                  <?= csrf_field() ?>
                  <input type="hidden" name="id" value="<?= (int)$v['id'] ?>">
                  <button class="btn btn-sm btn-outline-secondary">Check out</button>
                </form>
                <?php endif; ?>
                <a class="btn btn-sm btn-outline-secondary" href="<?= url('gate/pass', ['visitor_id' => (int)$v['id']]) ?>" target="_blank"><i class="bi bi-file-earmark-text"></i> Pass</a>
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
