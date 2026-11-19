<?php /* Parent/student portal: own profile (or children), fee, attendance and result snapshots, notices and consent settings. */
$userType = $profile['user_type'] ?? 'staff';
$typeBadge = match($userType) { 'student' => 'primary', 'parent' => 'success', default => 'secondary' };
?>
<?php
$headTitle = 'My portal';
$headIcon = 'bi-person-workspace';
$headSubtitle = 'Self-service access to your records, notices and consent settings.'
    . ' <span class="badge text-bg-' . $typeBadge . ' ms-1">' . e(ucfirst($userType)) . '</span>';
require __DIR__ . '/../dashboard/_head.php';
?>

<?php if (!$profile): ?>
<!-- No portal profile – staff user viewing the portal -->
<div class="alert alert-info">
  <strong>Staff account.</strong> This portal is intended for parent and student accounts.
  Your administrative access is managed through the main system menus.
</div>
<?php endif; ?>

<div class="row g-3">

  <!-- ------------------------------------------------ Profile / children card -->
  <div class="col-lg-4">
    <?php if ($userType === 'student' && $studentData): ?>
    <div class="card app-card mb-3">
      <div class="card-header">My profile</div>
      <div class="card-body text-center">
        <?php if ($studentData['photo_path']): ?>
        <img src="<?= e($studentData['photo_path']) ?>" class="rounded-circle mb-2" width="80" height="80" alt="Photo" style="object-fit:cover">
        <?php else: ?>
        <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center mx-auto mb-2" style="width:80px;height:80px;font-size:2rem">
          <?= strtoupper(mb_substr($studentData['first_name'] ?? '?', 0, 1)) ?>
        </div>
        <?php endif; ?>
        <h6 class="mb-0"><?= e($studentData['first_name'] . ' ' . $studentData['last_name']) ?></h6>
        <div class="small text-muted"><?= e($studentData['admission_number']) ?></div>
      </div>
      <ul class="list-group list-group-flush">
        <li class="list-group-item d-flex justify-content-between"><span class="text-muted">Class</span><strong><?= e($studentData['class_name'] ?? '—') ?></strong></li>
        <li class="list-group-item d-flex justify-content-between"><span class="text-muted">Stream</span><strong><?= e($studentData['stream_name'] ?? '—') ?></strong></li>
        <li class="list-group-item d-flex justify-content-between"><span class="text-muted">House</span><strong><?= e($studentData['house_name'] ?? '—') ?></strong></li>
        <li class="list-group-item d-flex justify-content-between"><span class="text-muted">Status</span><span class="badge text-bg-<?= $studentData['status'] === 'active' ? 'success' : 'secondary' ?>"><?= e($studentData['status']) ?></span></li>
      </ul>
    </div>

    <?php elseif ($userType === 'parent' && $guardianData): ?>
    <div class="card app-card mb-3">
      <div class="card-header">My children</div>
      <ul class="list-group list-group-flush">
        <?php if (!$guardianData): ?>
        <li class="list-group-item text-muted text-center py-3">No linked students found.</li>
        <?php endif; ?>
        <?php foreach ($guardianData as $child): ?>
        <li class="list-group-item">
          <div class="fw-semibold"><?= e($child['first_name'] . ' ' . $child['last_name']) ?></div>
          <div class="small text-muted"><?= e($child['admission_number']) ?> &bull; <?= e($child['class_name'] ?? '—') ?></div>
          <span class="badge text-bg-<?= $child['status'] === 'active' ? 'success' : 'secondary' ?> mt-1"><?= e($child['status']) ?></span>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>

    <?php else: ?>
    <div class="card app-card mb-3">
      <div class="card-header">Profile</div>
      <div class="card-body text-muted text-center py-4">
        Portal access is for parent and student accounts.<br>
        Contact the administrator to link your account.
      </div>
    </div>
    <?php endif; ?>

    <!-- Consent management -->
    <?php if ($consents): ?>
    <div class="card app-card">
      <div class="card-header">Data consent</div>
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead><tr><th>Consent type</th><th>Status</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($consents as $c): ?>
            <tr>
              <td class="small"><?= e(str_replace('_', ' ', $c['consent_type'])) ?></td>
              <td><span class="badge text-bg-<?= $c['granted'] ? 'success' : 'secondary' ?>"><?= $c['granted'] ? 'Granted' : 'Withdrawn' ?></span></td>
              <td>
                <form method="post" action="<?= url('portal/consent/update') ?>" data-ajax class="d-inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                  <input type="hidden" name="granted" value="<?= $c['granted'] ? '0' : '1' ?>">
                  <button class="btn btn-sm btn-outline-<?= $c['granted'] ? 'danger' : 'success' ?>">
                    <?= $c['granted'] ? 'Withdraw' : 'Grant' ?>
                  </button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- ------------------------------------------------ Notices -->
  <div class="col-lg-8">
    <?php if ($children): $currency = school_row()['currency'] ?? 'UGX'; ?>
    <div class="card app-card mb-3">
      <div class="card-header"><?= $userType === 'parent' ? 'My children at a glance' : 'My progress' ?></div>
      <div class="list-group list-group-flush">
        <?php foreach ($children as $child): $fees = $child['fees']; $att = $child['attendance']; $res = $child['result']; ?>
        <div class="list-group-item">
          <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
            <div>
              <div class="fw-semibold"><?= e($child['first_name'] . ' ' . $child['last_name']) ?></div>
              <div class="small text-muted">
                <?= e($child['admission_number']) ?>
                <?php if (!empty($child['class_name'])): ?> &bull; <?= e($child['class_name']) ?><?php endif; ?>
              </div>
            </div>
            <span class="badge text-bg-<?= $child['status'] === 'active' ? 'success' : 'secondary' ?>"><?= e($child['status']) ?></span>
          </div>

          <div class="row g-2 mt-2 text-center">
            <div class="col-md-4">
              <div class="border rounded p-2 h-100">
                <div class="small text-muted">Fee balance</div>
                <div class="fw-semibold"><?= e($currency) ?> <?= number_format((float)$fees['balance'], 0) ?></div>
                <div class="small text-muted">Paid <?= number_format((float)$fees['paid'], 0) ?> of <?= number_format((float)$fees['charged'], 0) ?></div>
                <?php if ((float)$fees['overdue'] > 0): ?>
                <div class="small text-danger">Overdue <?= number_format((float)$fees['overdue'], 0) ?></div>
                <?php endif; ?>
              </div>
            </div>
            <div class="col-md-4">
              <div class="border rounded p-2 h-100">
                <div class="small text-muted">Attendance</div>
                <?php if ((int)$att['days_marked'] > 0): ?>
                <div class="fw-semibold"><?= e((string)$att['percent']) ?>%</div>
                <div class="small text-muted"><?= (int)$att['days_present'] ?> present of <?= (int)$att['days_marked'] ?> days</div>
                <?php else: ?>
                <div class="fw-semibold text-muted">Not recorded</div>
                <div class="small text-muted">No daily attendance yet</div>
                <?php endif; ?>
              </div>
            </div>
            <div class="col-md-4">
              <div class="border rounded p-2 h-100">
                <div class="small text-muted">Last exam</div>
                <?php if ($res): ?>
                <div class="fw-semibold"><?= e(number_format((float)$res['average_percent'], 1)) ?>%</div>
                <div class="small text-muted">
                  <?= e(mb_strimwidth((string)$res['exam_name'], 0, 24, '…')) ?>
                  &bull; #<?= (int)$res['class_rank'] ?> of <?= (int)$res['class_size'] ?>
                </div>
                <?php else: ?>
                <div class="fw-semibold text-muted">No results</div>
                <div class="small text-muted">No published exam yet</div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <div class="card app-card">
      <div class="card-header">Recent notices <span class="badge text-bg-light"><?= count($notices) ?></span></div>
      <?php if (!$notices): ?>
      <div class="card-body text-muted text-center py-4">No published notices at the moment.</div>
      <?php else: ?>
      <div class="list-group list-group-flush">
        <?php foreach ($notices as $n): ?>
        <div class="list-group-item">
          <div class="d-flex justify-content-between align-items-start">
            <strong><?= e($n['title']) ?></strong>
            <small class="text-muted ms-2 text-nowrap"><?= e(date('d M Y', strtotime($n['published_at']))) ?></small>
          </div>
          <div class="text-muted small mt-1"><?= nl2br(e(mb_substr($n['body'] ?? '', 0, 300))) ?><?= mb_strlen($n['body'] ?? '') > 300 ? '&hellip;' : '' ?></div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <?php if (!in_array($userType, ['parent', 'student'], true)): ?>
    <!-- Quick links -->
    <div class="card app-card mt-3 dashboard-actions">
      <div class="card-header">Quick access</div>
      <div class="card-body">
        <div class="row g-2">
          <?php $links = [
            ['Finance', 'finance', 'bi-cash-stack', 'View fees and payments'],
            ['Academics', 'academics', 'bi-book', 'Timetables and curriculum'],
            ['Examinations', 'examinations', 'bi-clipboard-check', 'Results and report cards'],
            ['Attendance', 'attendance', 'bi-calendar-check', 'Attendance records'],
          ]; ?>
          <?php foreach ($links as [$label, $route, $icon, $desc]): ?>
          <div class="col-sm-6">
            <a href="<?= url($route) ?>" class="text-decoration-none">
              <div class="border rounded p-3 d-flex align-items-center gap-3 h-100">
                <i class="bi <?= $icon ?> fs-3 text-primary"></i>
                <div>
                  <div class="fw-semibold"><?= $label ?></div>
                  <div class="small text-muted"><?= $desc ?></div>
                </div>
              </div>
            </a>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </div>

</div>
