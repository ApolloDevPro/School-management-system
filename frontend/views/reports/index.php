<?php
// ------------------------------------------------------------------ helpers
$school   = school_row() ?? [];
$currency = $school['currency_symbol'] ?? 'Ugx';

$tabUrl = function (string $t, array $extra = []) use ($yearId, $examId, $dateFrom, $dateTo, $date): string {
    $q = [];
    if ($yearId)   $q['year_id']   = $yearId;
    if ($examId)   $q['exam_id']   = $examId;
    if ($dateFrom) $q['date_from'] = $dateFrom;
    if ($dateTo)   $q['date_to']   = $dateTo;
    if ($date)     $q['date']      = $date;
    $q = array_merge($q, $extra);
    return url('reports') . '&tab=' . $t . ($q ? '&' . http_build_query($q, '', '&', PHP_QUERY_RFC3986) : '');
};

$exportUrl = function (string $r, array $extra = []) use ($yearId, $examId, $dateFrom, $dateTo): string {
    $q = [];
    if ($yearId)   $q['year_id']   = $yearId;
    if ($examId)   $q['exam_id']   = $examId;
    if ($dateFrom) $q['date_from'] = $dateFrom;
    if ($dateTo)   $q['date_to']   = $dateTo;
    $q = array_merge($q, $extra);
    return url('reports/export') . '&report=' . $r . ($q ? '&' . http_build_query($q, '', '&', PHP_QUERY_RFC3986) : '');
};

$pdfUrl = function (string $r, array $extra = []) use ($yearId, $examId, $dateFrom, $dateTo, $date): string {
    $q = [];
    if ($yearId)   $q['year_id']   = $yearId;
    if ($examId)   $q['exam_id']   = $examId;
    if ($dateFrom) $q['date_from'] = $dateFrom;
    if ($dateTo)   $q['date_to']   = $dateTo;
    if ($date)     $q['date']      = $date;
    $q = array_merge($q, $extra);
    return url('reports/pdf') . '&report=' . $r . ($q ? '&' . http_build_query($q, '', '&', PHP_QUERY_RFC3986) : '');
};

$money = function (float $v) use ($currency): string {
    return $currency . ' ' . number_format($v, 0);
};

$kpi        = $data['kpis'] ?? null;
$viewsMissing = $data['views_missing'] ?? false;
$contractExpiry = $data['contract_expiry'] ?? [];
$licenceAlerts  = $data['licence_alerts'] ?? [];
$charts       = $data['charts'] ?? [];

$tabs = [
    'overview'   => ['Overview', 'bi-speedometer2'],
    'students'   => ['Students', 'bi-people'],
    'finance'    => ['Finance', 'bi-cash-stack'],
    'attendance' => ['Attendance', 'bi-calendar-check'],
    'exams'      => ['Examinations', 'bi-clipboard-check'],
    'hr'         => ['HR & payroll', 'bi-person-badge'],
    'operations' => ['Operations', 'bi-building'],
    'audit'      => ['Audit log', 'bi-journal-text'],
];

// Chart config passed to JS
$chartConfig = [
    'fee_collection'     => $charts['fee_collection']     ?? ['labels' => [], 'datasets' => []],
    'students_by_class'  => $charts['students_by_class']  ?? ['labels' => [], 'datasets' => []],
    'students_by_status' => $charts['students_by_status'] ?? ['labels' => [], 'datasets' => []],
    'attendance'         => $charts['attendance']         ?? ['labels' => [], 'datasets' => []],
    'exam_performance'   => $charts['exam_performance']   ?? ['labels' => [], 'datasets' => []],
    'dorm_occupancy'     => $charts['dorm_occupancy']     ?? ['labels' => [], 'datasets' => []],
    'route_load'         => $charts['route_load']         ?? ['labels' => [], 'datasets' => []],
    'low_stock'          => $charts['low_stock']          ?? ['labels' => [], 'datasets' => []],
];

// Chart type map
$chartType = [
    'fee_collection'     => 'line',
    'students_by_class'  => 'doughnut',
    'students_by_status' => 'doughnut',
    'attendance'         => 'line',
    'exam_performance'   => 'bar',
    'dorm_occupancy'     => 'bar',
    'route_load'         => 'bar',
    'low_stock'          => 'bar',
];
?>
<div class="page-head d-flex justify-content-between align-items-start">
  <div>
    <h1 class="h4 mb-0">Reports &amp; analytics</h1>
    <p class="text-muted small mb-0">Live operational summaries across all school modules.</p>
  </div>
  <?php if ($viewsMissing): ?>
  <span class="badge text-bg-warning">DB views missing</span>
  <?php endif; ?>
</div>

<?php if ($viewsMissing): ?>
<div class="alert alert-warning border-0 bg-warning bg-opacity-10">
  <i class="bi bi-exclamation-triangle me-2"></i>
  <strong>Some database views are not installed.</strong> KPI dashboard and expiry alerts may not load.
  Apply <code>database/19_reports_views.sql</code> to enable full analytics.
</div>
<?php endif; ?>

<!-- ── Global filter bar ─────────────────────────────────────────── -->
<div class="filter-bar p-3 mb-4">
  <form class="row g-2 align-items-end" method="get">
    <input type="hidden" name="r" value="reports">
    <input type="hidden" name="tab" value="<?= e($tab) ?>">
    <div class="col-md-2">
      <label class="form-label">Academic year</label>
      <select class="form-select form-select-sm" name="year_id">
        <option value="">— all —</option>
        <?php foreach ($years as $y): ?>
        <option value="<?= (int)$y['id'] ?>" <?= (int)$y['id'] === $yearId ? 'selected' : '' ?>><?= e($y['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <label class="form-label">Examination</label>
      <select class="form-select form-select-sm" name="exam_id">
        <option value="">— all —</option>
        <?php foreach ($exams as $ex): ?>
        <option value="<?= (int)$ex['id'] ?>" <?= (int)$ex['id'] === $examId ? 'selected' : '' ?>><?= e($ex['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <label class="form-label">From</label>
      <input class="form-control form-control-sm" type="date" name="date_from" value="<?= e($dateFrom) ?>">
    </div>
    <div class="col-md-2">
      <label class="form-label">To</label>
      <input class="form-control form-control-sm" type="date" name="date_to" value="<?= e($dateTo) ?>">
    </div>
    <div class="col-md-2">
      <label class="form-label">Date</label>
      <input class="form-control form-control-sm" type="date" name="date" value="<?= e($date) ?>">
    </div>
    <div class="col-md-2 d-grid">
      <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-filter me-1"></i> Apply</button>
      <a href="<?= url('reports') ?>" class="btn btn-outline-secondary btn-sm mt-1"><i class="bi bi-arrow-clockwise me-1"></i> Reset</a>
    </div>
  </form>
</div>

<!-- ── Tab nav ───────────────────────────────────────────────────── -->
<ul class="nav nav-tabs mb-4 border-0">
  <?php foreach ($tabs as $key => [$label, $icon]): ?>
  <li class="nav-item">
    <a class="nav-link <?= $tab === $key ? 'active show bg-primary text-white' : '' ?>" href="<?= $tabUrl($key) ?>">
      <i class="bi <?= $icon ?> me-1"></i><?= $label ?>
    </a>
  </li>
  <?php endforeach; ?>
</ul>

<?php
// ═════════════════════════════════════════════════════════════════════════════════
// OVERVIEW
// ═════════════════════════════════════════════════════════════════════════════════
?>
<?php if ($tab === 'overview'): ?>

<!-- KPI cards -->
<div class="row g-3 mb-4">
  <?php
  $kpiCards = [
      ['Active students',    $kpi['active_students'] ?? 0,           'bi-people-fill',            'primary'],
      ['Boarders',           $kpi['boarders'] ?? 0,                  'bi-house-door-fill',        'info'],
      ['Active staff',       $kpi['active_staff'] ?? 0,              'bi-person-badge-fill',      'success'],
      ['Active classes',     $kpi['active_classes'] ?? 0,            'bi-grid-fill',              'secondary'],
      ['Outstanding fees',   'UGX ' . number_format((float)($kpi['outstanding_fees'] ?? 0)), 'bi-cash-stack', 'danger'],
      ['Collected (month)',  'UGX ' . number_format((float)($kpi['fees_collected_this_month'] ?? 0)), 'bi-arrow-down-circle-fill', 'success'],
      ['Absent today',       $kpi['absent_today'] ?? 0,              'bi-calendar-x-fill',        'warning'],
      ['Open tickets',       $kpi['open_tickets'] ?? 0,              'bi-ticket-perforated-fill', 'secondary'],
  ];
  $toneMap = ['primary' => '', 'info' => 'tone-info', 'success' => 'tone-success', 'secondary' => 'tone-secondary', 'danger' => 'tone-danger', 'warning' => 'tone-warning'];
  ?>
  <?php foreach ($kpiCards as [$label, $value, $icon, $color]): $tone = $toneMap[$color] ?? ''; ?>
  <div class="col-sm-6 col-lg-3">
    <div class="card stat-card stat-with-icon <?= $tone ?>">
      <div class="stat-icon"><i class="bi <?= $icon ?>"></i></div>
      <div class="stat-body">
        <div class="stat-label"><?= e($label) ?></div>
        <div class="stat-value <?= str_starts_with((string)$label, 'Outstanding') || str_starts_with((string)$label, 'Collected') ? 'stat-money' : '' ?>"><?= $value ?></div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Charts row -->
<div class="row g-3 mb-4">
  <div class="col-lg-6 col-xl-3">
    <div class="card app-card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span class="small text-muted">Fee collection trend</span>
        <a href="<?= $pdfUrl('collection', ['date_from' => $dateFrom, 'date_to' => $dateTo]) ?>" class="btn btn-xs btn-outline-primary"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
      </div>
      <div class="card-body p-3">
        <canvas id="chart-fee-collection" height="150"></canvas>
      </div>
    </div>
  </div>
  <div class="col-lg-6 col-xl-3">
    <div class="card app-card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span class="small text-muted">Students by class</span>
        <a href="<?= $pdfUrl('students') ?>" class="btn btn-xs btn-outline-primary"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
      </div>
      <div class="card-body p-3">
        <canvas id="chart-students-class" height="150"></canvas>
      </div>
    </div>
  </div>
  <div class="col-lg-6 col-xl-3">
    <div class="card app-card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span class="small text-muted">Students by status</span>
        <a href="<?= $exportUrl('students') ?>" class="btn btn-xs btn-outline-secondary"><i class="bi bi-download"></i> CSV</a>
      </div>
      <div class="card-body p-3">
        <canvas id="chart-students-status" height="150"></canvas>
      </div>
    </div>
  </div>
  <div class="col-lg-6 col-xl-3">
    <div class="card app-card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span class="small text-muted">Daily attendance %</span>
        <a href="<?= $pdfUrl('attendance', ['date_from' => $dateFrom, 'date_to' => $dateTo]) ?>" class="btn btn-xs btn-outline-primary"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
      </div>
      <div class="card-body p-3">
        <canvas id="chart-attendance" height="150"></canvas>
      </div>
    </div>
  </div>
</div>

<!-- Placement summary -->
<div class="row g-3 mb-4">
  <div class="col-lg-8">
    <div class="card app-card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span>Student placement <span class="badge text-bg-light"><?= count($data['placement'] ?? []) ?> total</span></span>
        <a href="<?= $exportUrl('students') ?>" class="btn btn-xs btn-outline-secondary"><i class="bi bi-download"></i> CSV</a>
      </div>
      <div class="table-responsive">
        <table class="table table-hover table-sm align-middle mb-0" data-table>
          <thead><tr><th>Adm. #</th><th>Name</th><th>Gender</th><th>Class</th><th>Stream</th><th>House</th><th>Type</th><th>Status</th></tr></thead>
          <tbody>
            <?php if (empty($data['placement'])): ?><tr><td colspan="8" class="text-center text-muted py-4">No active students found.</td></tr><?php endif; ?>
            <?php foreach ($data['placement'] ?? [] as $r): ?>
            <tr>
              <td class="small text-muted"><?= e($r['admission_number'] ?? '—') ?></td>
              <td><strong><?= e($r['full_name'] ?? '—') ?></strong></td>
              <td><?= e($r['gender'] ?? '—') ?></td>
              <td><?= e($r['class_name'] ?? '—') ?></td>
              <td><?= e($r['stream_name'] ?? '—') ?></td>
              <td><?= e($r['house_name'] ?? '—') ?></td>
              <td><span class="badge text-bg-<?= (($r['student_type'] ?? '') === 'boarding') ? 'info' : 'secondary' ?>"><?= e($r['student_type'] ?? '—') ?></span></td>
              <td><span class="badge text-bg-success"><?= e($r['status'] ?? '—') ?></span></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <!-- Contract expiry -->
    <?php if ($contractExpiry): ?>
    <div class="card app-card mb-3">
      <div class="card-header text-bg-warning d-flex justify-content-between align-items-center">
        <span>⚠ Staff contracts expiring within 90 days <span class="badge bg-dark"><?= count($contractExpiry) ?></span></span>
        <a href="<?= $exportUrl('contracts') ?>" class="btn btn-xs btn-outline-dark ms-2"><i class="bi bi-download"></i> CSV</a>
      </div>
      <div class="table-responsive">
        <table class="table table-hover table-sm align-middle mb-0">
          <thead><tr><th>Staff</th><th>Position</th><th>Expiry</th><th>Days left</th></tr></thead>
          <tbody>
            <?php foreach (array_slice($contractExpiry, 0, 8) as $r): ?>
            <tr class="<?= (int)($r['days_to_expiry'] ?? 0) <= 30 ? 'table-danger' : 'table-warning' ?>">
              <td class="small"><strong><?= e($r['staff_name'] ?? '—') ?></strong></td>
              <td class="small"><?= e($r['position'] ?? '—') ?></td>
              <td class="small"><?= e($r['end_date'] ?? '—') ?></td>
              <td><strong class="small"><?= (int)($r['days_to_expiry'] ?? 0) ?></strong></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <!-- Licence alerts -->
    <?php if ($licenceAlerts): ?>
    <div class="card app-card">
      <div class="card-header text-bg-danger d-flex justify-content-between align-items-center">
        <span>🔒 Licences expiring within 90 days <span class="badge bg-dark"><?= count($licenceAlerts) ?></span></span>
        <a href="<?= $exportUrl('licences') ?>" class="btn btn-xs btn-outline-dark ms-2"><i class="bi bi-download"></i> CSV</a>
      </div>
      <div class="table-responsive">
        <table class="table table-hover table-sm align-middle mb-0">
          <thead><tr><th>Type</th><th>Licence #</th><th>Expiry</th><th>Days left</th></tr></thead>
          <tbody>
            <?php foreach (array_slice($licenceAlerts, 0, 8) as $r): ?>
            <tr class="<?= (int)($r['days_to_expiry'] ?? 0) <= 14 ? 'table-danger' : 'table-warning' ?>">
              <td class="small"><strong><?= e($r['licence_type']) ?></strong></td>
              <td class="small"><?= e($r['licence_number'] ?? '—') ?></td>
              <td class="small"><?= e($r['expiry_date'] ?? '—') ?></td>
              <td><strong class="small"><?= (int)($r['days_to_expiry'] ?? 0) ?></strong></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <?php if (!$contractExpiry && !$licenceAlerts): ?>
    <div class="alert alert-success border-0 bg-success bg-opacity-10"><i class="bi bi-check-circle me-1"></i> No expiry alerts at the moment.</div>
    <?php endif; ?>
  </div>
</div>

<?php
// ═════════════════════════════════════════════════════════════════════════════════
// STUDENTS
// ═════════════════════════════════════════════════════════════════════════════════
?>
<?php elseif ($tab === 'students'): ?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <span class="text-muted small"><?= count($data['placement'] ?? []) ?> active students</span>
  <div class="d-flex gap-2">
    <a href="<?= $exportUrl('students') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-download me-1"></i> Export CSV</a>
    <a href="<?= $pdfUrl('students') ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-file-earmark-pdf me-1"></i> Export PDF</a>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-lg-6">
    <div class="card app-card h-100">
      <div class="card-header">Students by class</div>
      <div class="card-body p-3">
        <canvas id="chart-students-class-tab" height="160"></canvas>
      </div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card app-card h-100">
      <div class="card-header">Students by status</div>
      <div class="card-body p-3">
        <canvas id="chart-students-status-tab" height="160"></canvas>
      </div>
    </div>
  </div>
</div>

<div class="card app-card">
  <div class="table-responsive">
    <table class="table table-hover table-sm align-middle mb-0" data-table>
      <thead><tr><th>Adm. #</th><th>Name</th><th>Gender</th><th>Class</th><th>Stream</th><th>House</th><th>Type</th><th>Status</th></tr></thead>
      <tbody>
        <?php if (empty($data['placement'])): ?><tr><td colspan="8" class="text-center text-muted py-4">No active students found.</td></tr><?php endif; ?>
        <?php foreach ($data['placement'] ?? [] as $r): ?>
        <tr>
          <td class="small text-muted"><?= e($r['admission_number'] ?? '—') ?></td>
          <td><strong><?= e($r['full_name'] ?? '—') ?></strong></td>
          <td><?= e($r['gender'] ?? '—') ?></td>
          <td><?= e($r['class_name'] ?? '—') ?></td>
          <td><?= e($r['stream_name'] ?? '—') ?></td>
          <td><?= e($r['house_name'] ?? '—') ?></td>
          <td><span class="badge text-bg-<?= (($r['student_type'] ?? '') === 'boarding') ? 'info' : 'secondary' ?>"><?= e($r['student_type'] ?? '—') ?></span></td>
          <td><span class="badge text-bg-success"><?= e($r['status'] ?? '—') ?></span></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php
// ═════════════════════════════════════════════════════════════════════════════════
// FINANCE
// ═════════════════════════════════════════════════════════════════════════════════
?>
<?php elseif ($tab === 'finance'): ?>

<div class="card app-card mb-3">
  <div class="card-body py-2">
    <form class="row g-2 align-items-end" method="get">
      <input type="hidden" name="r" value="reports"><input type="hidden" name="tab" value="finance">
      <div class="col-auto">
        <label class="form-label small mb-1">Academic year</label>
        <select class="form-select form-select-sm" name="year_id" onchange="this.form.submit()">
          <option value="">— all —</option>
          <?php foreach ($years as $y): ?>
          <option value="<?= (int)$y['id'] ?>" <?= (int)$y['id'] === $yearId ? 'selected' : '' ?>><?= e($y['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-auto">
        <label class="form-label small mb-1">From</label>
        <input class="form-control form-control-sm" type="date" name="date_from" value="<?= e($dateFrom) ?>" onchange="this.form.submit()">
      </div>
      <div class="col-auto">
        <label class="form-label small mb-1">To</label>
        <input class="form-control form-control-sm" type="date" name="date_to" value="<?= e($dateTo) ?>" onchange="this.form.submit()">
      </div>
      <div class="col-auto d-grid gap-2">
        <a href="<?= $exportUrl('collection', ['date_from' => $dateFrom, 'date_to' => $dateTo]) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-download me-1"></i> CSV</a>
        <a href="<?= $pdfUrl('collection', ['date_from' => $dateFrom, 'date_to' => $dateTo]) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-file-earmark-pdf me-1"></i> PDF</a>
      </div>
    </form>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-lg-6">
    <div class="card app-card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span>Fee collection trend</span>
      </div>
      <div class="card-body p-3">
        <canvas id="chart-fee-collection-tab" height="160"></canvas>
      </div>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="card app-card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span>Fee debtors <span class="badge text-bg-light"><?= count($data['debtors'] ?? []) ?></span></span>
        <?php if ($yearId): ?>
        <a href="<?= $exportUrl('debtors', ['year_id' => $yearId]) ?>" class="btn btn-xs btn-outline-secondary"><i class="bi bi-download"></i> CSV</a>
        <?php endif; ?>
      </div>
      <div class="table-responsive">
        <table class="table table-hover table-sm align-middle mb-0" data-table>
          <thead><tr><th>Student</th><th>Class</th><th>Balance (UGX)</th><th>Overdue</th><th>Days</th></tr></thead>
          <tbody>
            <?php if (empty($data['debtors'])): ?><tr><td colspan="5" class="text-center text-muted py-3"><?= $yearId ? 'No debtors.' : 'Select a year to load debtors.' ?></td></tr><?php endif; ?>
            <?php foreach ($data['debtors'] ?? [] as $r): ?>
            <tr>
              <td>
                <strong><?= e($r['student_name'] ?? '—') ?></strong>
                <div class="small text-muted"><?= e($r['admission_number'] ?? '—') ?></div>
              </td>
              <td class="small"><?= e($r['class_name'] ?? '—') ?></td>
              <td class="<?= (float)($r['balance'] ?? 0) > 0 ? 'text-danger fw-bold' : '' ?>"><?= number_format((float)($r['balance'] ?? 0)) ?></td>
              <td><?= number_format((float)($r['overdue_amount'] ?? 0)) ?></td>
              <td><span class="badge text-bg-<?= (int)($r['days_overdue'] ?? 0) > 30 ? 'danger' : 'warning' ?>"><?= (int)($r['days_overdue'] ?? 0) ?>d</span></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card app-card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span>Fee collection (<?= e($dateFrom) ?> → <?= e($dateTo) ?>) <span class="badge text-bg-light"><?= count($data['collection'] ?? []) ?></span></span>
        <a href="<?= $exportUrl('collection', ['date_from' => $dateFrom, 'date_to' => $dateTo]) ?>" class="btn btn-xs btn-outline-secondary"><i class="bi bi-download"></i> CSV</a>
      </div>
      <div class="table-responsive">
        <table class="table table-hover table-sm align-middle mb-0" data-table>
          <thead><tr><th>Date</th><th>Channel</th><th>Method</th><th>Txns</th><th>Amount (UGX)</th></tr></thead>
          <tbody>
            <?php if (empty($data['collection'])): ?><tr><td colspan="5" class="text-center text-muted py-3">No payments in selected period.</td></tr><?php endif; ?>
            <?php foreach ($data['collection'] ?? [] as $r): ?>
            <tr>
              <td class="small"><?= e($r['payment_date'] ?? '—') ?></td>
              <td><?= e($r['channel'] ?? '—') ?></td>
              <td><?= e($r['method_name'] ?? '—') ?></td>
              <td><?= (int)($r['payment_count'] ?? 0) ?></td>
              <td class="fw-bold text-success"><?= number_format((float)($r['amount_collected'] ?? 0)) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php
// ═════════════════════════════════════════════════════════════════════════════════
// ATTENDANCE
// ═════════════════════════════════════════════════════════════════════════════════
?>
<?php elseif ($tab === 'attendance'): ?>

<div class="card app-card mb-3">
  <div class="card-body py-2">
    <form class="row g-2 align-items-end" method="get">
      <input type="hidden" name="r" value="reports"><input type="hidden" name="tab" value="attendance">
      <div class="col-auto">
        <label class="form-label small mb-1">Date</label>
        <input class="form-control form-control-sm" type="date" name="date" value="<?= e($date) ?>">
      </div>
      <div class="col-auto d-grid">
        <button class="btn btn-sm btn-primary" type="submit">Load</button>
      </div>
    </form>
  </div>
</div>

<div class="card app-card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span>Daily attendance — <?= e(date('d F Y', strtotime($date))) ?> <span class="badge text-bg-light"><?= count($data['daily'] ?? []) ?></span></span>
    <a href="<?= $exportUrl('attendance', ['date' => $date]) ?>" class="btn btn-xs btn-outline-secondary"><i class="bi bi-download"></i> CSV</a>
  </div>
  <div class="table-responsive">
    <table class="table table-hover table-sm align-middle mb-0" data-table>
      <thead><tr><th>Class</th><th>Stream</th><th>Marked</th><th>Present</th><th>Absent</th><th>Late</th><th>Excused</th><th>Attendance %</th></tr></thead>
      <tbody>
        <?php if (empty($data['daily'])): ?><tr><td colspan="8" class="text-center text-muted py-4">No attendance marked for this date.</td></tr><?php endif; ?>
        <?php foreach ($data['daily'] ?? [] as $r): ?>
        <?php $pct = (float)($r['attendance_percent'] ?? 0); ?>
        <tr>
          <td><strong><?= e($r['class_name'] ?? '—') ?></strong></td>
          <td><?= e($r['stream_name'] ?? '—') ?></td>
          <td><?= (int)($r['marked_count'] ?? 0) ?></td>
          <td class="text-success fw-bold"><?= (int)($r['present_count'] ?? 0) ?></td>
          <td class="text-danger"><?= (int)($r['absent_count'] ?? 0) ?></td>
          <td class="text-warning"><?= (int)($r['late_count'] ?? 0) ?></td>
          <td><?= (int)($r['excused_count'] ?? 0) ?></td>
          <td>
            <div class="d-flex align-items-center gap-2">
              <div class="progress flex-grow-1" style="height:6px">
                <div class="progress-bar bg-<?= $pct >= 90 ? 'success' : ($pct >= 75 ? 'warning' : 'danger') ?>" style="width:<?= min($pct, 100) ?>%"></div>
              </div>
              <span class="small fw-bold"><?= number_format($pct, 1) ?>%</span>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php
// ═════════════════════════════════════════════════════════════════════════════════
// EXAMS
// ═════════════════════════════════════════════════════════════════════════════════
?>
<?php elseif ($tab === 'exams'): ?>

<div class="card app-card mb-3">
  <div class="card-body py-2">
    <form class="row g-2 align-items-end" method="get">
      <input type="hidden" name="r" value="reports"><input type="hidden" name="tab" value="exams">
      <div class="col-auto">
        <label class="form-label small mb-1">Examination</label>
        <select class="form-select form-select-sm" name="exam_id" onchange="this.form.submit()">
          <option value="">— select exam —</option>
          <?php foreach ($exams as $ex): ?>
          <option value="<?= (int)$ex['id'] ?>" <?= (int)$ex['id'] === $examId ? 'selected' : '' ?>><?= e($ex['name']) ?> (<?= e($ex['exam_type'] ?? '') ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
    </form>
  </div>
</div>

<div class="card app-card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span>Class performance by subject <span class="badge text-bg-light"><?= count($data['performance'] ?? []) ?></span></span>
    <?php if ($examId): ?>
    <a href="<?= $exportUrl('exams', ['exam_id' => $examId]) ?>" class="btn btn-xs btn-outline-secondary"><i class="bi bi-download"></i> CSV</a>
    <a href="<?= $pdfUrl('exams', ['exam_id' => $examId]) ?>" class="btn btn-xs btn-outline-primary"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
    <?php endif; ?>
  </div>
  <div class="table-responsive">
    <table class="table table-hover table-sm align-middle mb-0" data-table>
      <thead><tr><th>Exam</th><th>Class</th><th>Subject</th><th>Candidates</th><th>Average %</th><th>Highest %</th><th>Lowest %</th><th>Passed</th><th>Pass rate %</th></tr></thead>
      <tbody>
        <?php if (empty($data['performance'])): ?><tr><td colspan="9" class="text-center text-muted py-4"><?= $examId ? 'No results for this exam.' : 'Select an exam to load performance data.' ?></td></tr><?php endif; ?>
        <?php foreach ($data['performance'] ?? [] as $r): ?>
        <?php $pass = (float)($r['pass_rate'] ?? 0); ?>
        <tr>
          <td class="small text-muted"><?= e($r['exam_name'] ?? '—') ?></td>
          <td><strong><?= e($r['class_name'] ?? '—') ?></strong></td>
          <td><?= e($r['subject_name'] ?? '—') ?></td>
          <td><?= (int)($r['candidates'] ?? 0) ?></td>
          <td><?= number_format((float)($r['average_percent'] ?? 0), 1) ?></td>
          <td class="text-success"><?= number_format((float)($r['highest_percent'] ?? 0), 1) ?></td>
          <td class="text-danger"><?= number_format((float)($r['lowest_percent'] ?? 0), 1) ?></td>
          <td><?= (int)($r['passed_count'] ?? 0) ?></td>
          <td>
            <div class="d-flex align-items-center gap-2">
              <div class="progress flex-grow-1" style="height:6px">
                <div class="progress-bar bg-<?= $pass >= 80 ? 'success' : ($pass >= 50 ? 'warning' : 'danger') ?>" style="width:<?= min($pass, 100) ?>%"></div>
              </div>
              <span class="small fw-bold"><?= number_format($pass, 1) ?>%</span>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php
// ═════════════════════════════════════════════════════════════════════════════════
// HR
// ═════════════════════════════════════════════════════════════════════════════════
?>
<?php elseif ($tab === 'hr'): ?>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="card app-card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span>Leave balances <span class="badge text-bg-light"><?= count($data['leave_balances'] ?? []) ?></span></span>
        <a href="<?= $exportUrl('leave') ?>" class="btn btn-xs btn-outline-secondary"><i class="bi bi-download"></i> CSV</a>
      </div>
      <div class="table-responsive">
        <table class="table table-hover table-sm align-middle mb-0" data-table>
          <thead><tr><th>Staff</th><th>Leave type</th><th>Entitlement</th><th>Taken</th><th>Remaining</th><th>Pending</th></tr></thead>
          <tbody>
            <?php if (empty($data['leave_balances'])): ?><tr><td colspan="6" class="text-center text-muted py-4">No leave data available.</td></tr><?php endif; ?>
            <?php foreach ($data['leave_balances'] ?? [] as $r): ?>
            <tr>
              <td><strong><?= e($r['staff_name'] ?? '—') ?></strong></td>
              <td class="small"><?= e($r['leave_type'] ?? '—') ?></td>
              <td><?= (int)($r['days_per_year'] ?? 0) ?>d</td>
              <td><?= (int)($r['days_taken'] ?? 0) ?>d</td>
              <td class="<?= (int)($r['days_remaining'] ?? 0) < 3 ? 'text-danger fw-bold' : 'text-success' ?>"><?= (int)($r['days_remaining'] ?? 0) ?>d</td>
              <td><?= (int)($r['days_pending'] ?? 0) ?>d</td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card app-card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span>Payroll summary <span class="badge text-bg-light"><?= count($data['payroll'] ?? []) ?></span></span>
        <a href="<?= $exportUrl('payroll') ?>" class="btn btn-xs btn-outline-secondary"><i class="bi bi-download"></i> CSV</a>
      </div>
      <div class="table-responsive">
        <table class="table table-hover table-sm align-middle mb-0" data-table>
          <thead><tr><th>Period</th><th>Employees</th><th>Gross (UGX)</th><th>Net (UGX)</th><th>Status</th></tr></thead>
          <tbody>
            <?php if (empty($data['payroll'])): ?><tr><td colspan="5" class="text-center text-muted py-4">No payroll data available.</td></tr><?php endif; ?>
            <?php foreach ($data['payroll'] ?? [] as $r): ?>
            <tr>
              <td class="small"><strong><?= e($r['period_label'] ?? '—') ?></strong></td>
              <td><?= (int)($r['employees_paid'] ?? 0) ?></td>
              <td><?= number_format((float)($r['total_gross'] ?? 0)) ?></td>
              <td class="fw-bold"><?= number_format((float)($r['total_net_pay'] ?? 0)) ?></td>
              <td><span class="badge text-bg-<?= (($r['status'] ?? '') === 'paid') ? 'success' : 'secondary' ?>"><?= e($r['status'] ?? '—') ?></span></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php
// ═════════════════════════════════════════════════════════════════════════════════
// OPERATIONS
// ═════════════════════════════════════════════════════════════════════════════════
?>
<?php elseif ($tab === 'operations'): ?>

<!-- Library stock -->
<div class="card app-card mb-3">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span>📚 Library stock <span class="badge text-bg-light"><?= count($data['library'] ?? []) ?></span></span>
    <a href="<?= $exportUrl('library') ?>" class="btn btn-xs btn-outline-secondary"><i class="bi bi-download"></i> CSV</a>
  </div>
  <div class="table-responsive">
    <table class="table table-hover table-sm align-middle mb-0" data-table>
      <thead><tr><th>Title</th><th>Author</th><th>Category</th><th>Total</th><th>Available</th><th>Out</th><th>Lost</th><th>Status</th></tr></thead>
      <tbody>
        <?php if (empty($data['library'])): ?><tr><td colspan="8" class="text-center text-muted py-3">No books found.</td></tr><?php endif; ?>
        <?php foreach ($data['library'] ?? [] as $r): ?>
        <tr>
          <td><strong><?= e($r['title'] ?? '—') ?></strong></td>
          <td class="small"><?= e($r['author'] ?? '—') ?></td>
          <td class="small"><?= e($r['category_name'] ?? '—') ?></td>
          <td><?= (int)($r['total_copies'] ?? 0) ?></td>
          <td class="text-success"><?= (int)($r['available_copies'] ?? 0) ?></td>
          <td><?= (int)($r['copies_out'] ?? 0) ?></td>
          <td class="text-danger"><?= (int)($r['lost_copies'] ?? 0) ?></td>
          <td><span class="badge text-bg-<?= (($r['status'] ?? '') === 'active') ? 'success' : 'secondary' ?>"><?= e($r['status'] ?? '—') ?></span></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="row g-3 mb-3">
  <!-- Overdue books -->
  <?php if ($data['overdue'] ?? false): ?>
  <div class="col-lg-6">
    <div class="card app-card">
      <div class="card-header text-bg-warning d-flex justify-content-between align-items-center">
        <span>📕 Overdue books <span class="badge bg-dark"><?= count($data['overdue']) ?></span></span>
        <a href="<?= $exportUrl('overdue') ?>" class="btn btn-xs btn-outline-dark"><i class="bi bi-download"></i> CSV</a>
      </div>
      <div class="table-responsive">
        <table class="table table-hover table-sm align-middle mb-0" data-table>
          <thead><tr><th>Title</th><th>Borrower</th><th>Due date</th><th>Days overdue</th></tr></thead>
          <tbody>
            <?php foreach ($data['overdue'] as $r): ?>
            <tr>
              <td><?= e($r['title'] ?? '—') ?></td>
              <td><strong><?= e($r['borrower_name'] ?? '—') ?></strong><div class="small text-muted"><?= e($r['admission_number'] ?? '') ?></div></td>
              <td class="small"><?= e($r['due_date'] ?? '—') ?></td>
              <td><span class="badge text-bg-danger"><?= (int)($r['days_overdue'] ?? 0) ?>d</span></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Boarding occupancy -->
  <div class="col-lg-6">
    <div class="card app-card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span>🏠 Dormitory occupancy <span class="badge text-bg-light"><?= count($data['boarding'] ?? []) ?></span></span>
      </div>
      <div class="table-responsive">
        <table class="table table-hover table-sm align-middle mb-0" data-table>
          <thead><tr><th>Dormitory</th><th>Type</th><th>Capacity</th><th>Occupied</th><th>Vacancies</th><th>Occupancy %</th></tr></thead>
          <tbody>
            <?php if (empty($data['boarding'])): ?><tr><td colspan="6" class="text-center text-muted py-3">No dormitory data.</td></tr><?php endif; ?>
            <?php foreach ($data['boarding'] ?? [] as $r): ?>
            <?php $occ = (float)($r['occupancy_percent'] ?? 0); ?>
            <tr>
              <td><strong><?= e($r['dormitory_name'] ?? '—') ?></strong></td>
              <td><?= e($r['dormitory_type'] ?? '—') ?></td>
              <td><?= (int)($r['capacity'] ?? 0) ?></td>
              <td><?= (int)($r['occupied_beds'] ?? 0) ?></td>
              <td class="<?= (int)($r['vacancies'] ?? 0) === 0 ? 'text-danger' : 'text-success' ?>"><?= (int)($r['vacancies'] ?? 0) ?></td>
              <td>
                <div class="d-flex align-items-center gap-2">
                  <div class="progress flex-grow-1" style="height:6px">
                    <div class="progress-bar bg-<?= $occ >= 95 ? 'danger' : ($occ >= 80 ? 'warning' : 'success') ?>" style="width:<?= min($occ, 100) ?>%"></div>
                  </div>
                  <span class="small"><?= number_format($occ, 1) ?>%</span>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Transport routes -->
  <div class="col-lg-6">
    <div class="card app-card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span>🚌 Transport route load <span class="badge text-bg-light"><?= count($data['transport'] ?? []) ?></span></span>
      </div>
      <div class="table-responsive">
        <table class="table table-hover table-sm align-middle mb-0" data-table>
          <thead><tr><th>Code</th><th>Route</th><th>Plate</th><th>Capacity</th><th>Allocated</th><th>Load %</th><th>Status</th></tr></thead>
          <tbody>
            <?php if (empty($data['transport'])): ?><tr><td colspan="7" class="text-center text-muted py-3">No route data.</td></tr><?php endif; ?>
            <?php foreach ($data['transport'] ?? [] as $r): ?>
            <?php $load = (float)($r['capacity_percent'] ?? 0); ?>
            <tr>
              <td class="small text-muted"><?= e($r['route_code'] ?? '—') ?></td>
              <td><strong><?= e($r['route_name'] ?? '—') ?></strong></td>
              <td class="small"><?= e($r['plate_number'] ?? '—') ?></td>
              <td><?= (int)($r['seating_capacity'] ?? 0) ?></td>
              <td><?= (int)($r['students_allocated'] ?? 0) ?></td>
              <td>
                <div class="d-flex align-items-center gap-2">
                  <div class="progress flex-grow-1" style="height:6px">
                    <div class="progress-bar bg-<?= $load >= 95 ? 'danger' : ($load >= 80 ? 'warning' : 'success') ?>" style="width:<?= min($load, 100) ?>%"></div>
                  </div>
                  <span class="small"><?= number_format($load, 1) ?>%</span>
                </div>
              </td>
              <td><span class="badge text-bg-<?= (($r['status'] ?? '') === 'active') ? 'success' : 'secondary' ?>"><?= e($r['status'] ?? '—') ?></span></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Low inventory -->
  <div class="col-lg-6">
    <div class="card app-card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span>Low stock items <span class="badge <?= !empty($data['inventory']) ? 'bg-dark' : 'text-bg-light' ?>"><?= count($data['inventory'] ?? []) ?></span></span>
        <?php if (!empty($data['inventory'])): ?>
        <a href="<?= $exportUrl('low_stock') ?>" class="btn btn-xs btn-outline-dark"><i class="bi bi-download"></i> CSV</a>
        <?php endif; ?>
      </div>
      <div class="table-responsive">
        <table class="table table-hover table-sm align-middle mb-0" data-table>
          <thead><tr><th>Code</th><th>Item</th><th>Unit</th><th>Stock</th><th>Reorder</th><th>Shortfall</th></tr></thead>
          <tbody>
            <?php if (empty($data['inventory'])): ?><tr><td colspan="6" class="text-center text-muted py-3">All items above reorder level.</td></tr><?php endif; ?>
            <?php foreach ($data['inventory'] ?? [] as $r): ?>
            <tr class="table-warning">
              <td class="small"><?= e($r['item_code'] ?? '—') ?></td>
              <td><strong><?= e($r['item_name'] ?? '—') ?></strong><div class="small text-muted"><?= e($r['category_name'] ?? '') ?></div></td>
              <td><?= e($r['unit'] ?? '—') ?></td>
              <td class="text-danger fw-bold"><?= (int)($r['current_stock'] ?? 0) ?></td>
              <td><?= (int)($r['reorder_level'] ?? 0) ?></td>
              <td><span class="badge text-bg-danger"><?= (int)($r['shortfall'] ?? 0) ?></span></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php
// ═════════════════════════════════════════════════════════════════════════════════
// AUDIT
// ═════════════════════════════════════════════════════════════════════════════════
?>
<?php elseif ($tab === 'audit'): ?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <span class="text-muted small"><?= count($data['audit'] ?? []) ?> log entries (most recent first)</span>
  <a href="<?= $exportUrl('audit') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-download me-1"></i> Export CSV</a>
</div>
<div class="card app-card">
  <div class="table-responsive">
    <table class="table table-hover table-sm align-middle mb-0" data-table>
      <thead><tr><th>User</th><th>Module</th><th>Action</th><th>Date</th><th>Count</th></tr></thead>
      <tbody>
        <?php if (empty($data['audit'])): ?><tr><td colspan="5" class="text-center text-muted py-4">No audit entries.</td></tr><?php endif; ?>
        <?php foreach ($data['audit'] ?? [] as $r): ?>
        <tr>
          <td><strong><?= e($r['user_name'] ?? '—') ?></strong></td>
          <td><span class="badge text-bg-light"><?= e($r['module'] ?? '—') ?></span></td>
          <td class="small"><?= e(str_replace('_', ' ', $r['action'] ?? '')) ?></td>
          <td class="small"><?= e($r['activity_date'] ?? '—') ?></td>
          <td><span class="badge text-bg-secondary"><?= (int)($r['action_count'] ?? 0) ?></span></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php endif; ?>

<!-- ════════════════════════════════════════════════════════════════════════════════
   Charts initialization
   ═════════════════════════════════════════════════════════════════════════════════ -->
<script>
window.APP = window.APP || {};
window.APP.reportsChartConfig = <?= json_encode($chartConfig, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
window.APP.reportsChartTypes = <?= json_encode($chartType, JSON_UNESCAPED_SLASHES) ?>;

document.addEventListener('DOMContentLoaded', function () {
  if (!window.Chart) return;

  var cfg = window.APP.reportsChartConfig || {};
  var types = window.APP.reportsChartTypes || {};

  function colorsFor(type, count) {
    var palettes = {
      line:   ['#2563eb', '#16a34a', '#0891b2', '#7c3aed', '#d97706'],
      doughnut: ['#2563eb', '#16a34a', '#d97706', '#dc2626', '#0891b2', '#7c3aed', '#be185d', '#b45309', '#0d9488', '#6366f1'],
      bar:    ['#2563eb', '#16a34a', '#0891b2', '#7c3aed', '#d97706', '#dc2626', '#be185d']
    };
    var base = palettes[type] || palettes.line;
    var out = [];
    for (var i = 0; i < count; i++) { out.push(base[i % base.length]); }
    return out;
  }

  function initChart(id) {
    var c = document.getElementById(id);
    if (!c) return;
    var key = c.dataset.chartKey;
    if (!cfg[key]) return;

    var data = cfg[key];
    var labels = data.labels || [];
    var datasets = data.datasets || [];
    var type = types[key] || 'line';

    var ds = datasets.map(function (d, i) {
      return Object.assign({}, d, {
        backgroundColor: d.backgroundColor || colorsFor(type, datasets.length)[i] || '#2563eb',
        borderColor: d.borderColor || colorsFor(type, datasets.length)[i] || '#2563eb',
        borderWidth: d.borderWidth !== undefined ? d.borderWidth : (type === 'doughnut' ? 2 : 1),
        tension: d.tension !== undefined ? d.tension : (type === 'line' ? 0.3 : 0),
        fill: d.fill !== undefined ? d.fill : (type === 'line' ? true : false)
      });
    });

    new window.Chart(c, {
      type: type,
      data: { labels: labels, datasets: ds },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: type !== 'doughnut', position: type === 'doughnut' ? 'bottom' : 'top' } },
        scales: type === 'bar' || type === 'line' ? { y: { beginAtZero: true } } : {}
      }
    });
  }

  // Initialize all report charts
  document.querySelectorAll('canvas[id^="chart-"]').forEach(function (c) {
    initChart(c.id);
  });
});
</script>
