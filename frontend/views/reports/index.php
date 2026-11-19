<?php
/**
 * Reports & analytics hub.
 *
 * One page, eight report tabs: overview, students, finance, attendance, exams,
 * HR, operations and the audit log. Every tab shares the filter bar and can
 * export its table as CSV or PDF.
 */
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

$exportUrl = function (string $r, array $extra = []) use ($yearId, $examId, $dateFrom, $dateTo, $date): string {
    $q = [];
    if ($yearId)   $q['year_id']   = $yearId;
    if ($examId)   $q['exam_id']   = $examId;
    if ($dateFrom) $q['date_from'] = $dateFrom;
    if ($dateTo)   $q['date_to']   = $dateTo;
    if ($date)     $q['date']      = $date;
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

$money = fn(float $v): string => $currency . ' ' . number_format($v, 0);
$num   = fn($v): string => number_format((int)$v);
$sum   = fn(array $rows, string $key): float => array_sum(array_map(fn(array $r): float => (float)($r[$key] ?? 0), $rows));

$kpi            = $reportData['kpis'] ?? null;
$viewsMissing   = $reportData['views_missing'] ?? false;
$placement      = $reportData['placement'] ?? [];
$contractExpiry = $reportData['contract_expiry'] ?? [];
$licenceAlerts  = $reportData['licence_alerts'] ?? [];
$collection     = $reportData['collection'] ?? [];
$debtors        = $reportData['debtors'] ?? [];
$daily          = $reportData['daily'] ?? [];
$performance    = $reportData['performance'] ?? [];
$leave          = $reportData['leave_balances'] ?? [];
$payroll        = $reportData['payroll'] ?? [];
$library        = $reportData['library'] ?? [];
$overdue        = $reportData['overdue'] ?? [];
$boarding       = $reportData['boarding'] ?? [];
$transport      = $reportData['transport'] ?? [];
$inventory      = $reportData['inventory'] ?? [];
$audit          = $reportData['audit'] ?? [];
$charts         = $reportData['charts'] ?? [];

$tabs = [
    'overview'   => ['Overview', 'bi-grid-1x2'],
    'students'   => ['Students', 'bi-people'],
    'finance'    => ['Finance', 'bi-cash-stack'],
    'attendance' => ['Attendance', 'bi-calendar-check'],
    'exams'      => ['Examinations', 'bi-clipboard-check'],
    'hr'         => ['HR & payroll', 'bi-person-badge'],
    'operations' => ['Operations', 'bi-building'],
    'audit'      => ['Audit log', 'bi-journal-text'],
];

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

$term = null;
try {
    $term = Term::activeForSchool(school_id());
} catch (Throwable) {
    $term = null;
}

$hasChart = fn(string $key): bool => !empty($chartConfig[$key]['labels'] ?? []);

/**
 * KPI card.
 */
$stat = function (string $label, string $value, string $icon, string $tone = 'primary', string $note = '', bool $money = false, string $col = 'col-6 col-xl-3'): void {
    ?>
    <div class="<?= e($col) ?>">
      <div class="card stat-card h-100 tone-<?= e($tone) ?>">
        <i class="stat-glyph bi <?= e($icon) ?>" aria-hidden="true"></i>
        <div class="stat-label"><?= e($label) ?></div>
        <div class="stat-value<?= $money ? ' stat-money' : '' ?>"><?= e($value) ?></div>
        <?php if ($note !== ''): ?><div class="stat-note"><?= e($note) ?></div><?php endif; ?>
      </div>
    </div>
    <?php
};

/**
 * Chart card with a friendly empty state when the period has no data.
 */
$chart = function (string $key, string $icon, string $title, string $subtitle = '', string $col = 'col-lg-6') use ($chartConfig, $hasChart): void {
    ?>
    <div class="<?= e($col) ?>">
      <div class="card app-card h-100">
        <div class="card-header justify-content-between">
          <span class="d-inline-flex align-items-center gap-2"><i class="bi <?= e($icon) ?> text-primary"></i><?= e($title) ?></span>
          <?php if ($subtitle !== ''): ?><span class="small text-muted fw-normal d-none d-sm-inline"><?= e($subtitle) ?></span><?php endif; ?>
        </div>
        <div class="card-body">
          <?php if (!$hasChart($key)): ?>
          <div class="report-empty">
            <i class="bi bi-graph-up"></i>
            <strong>No data for this period</strong>
            <span>Records will appear here once they are captured.</span>
          </div>
          <?php else: ?>
          <div class="report-chart"><canvas id="chart-<?= e($key) ?>" data-chart-key="<?= e($key) ?>" role="img" aria-label="<?= e($title) ?>"></canvas></div>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php
};

/**
 * CSV and PDF buttons for a report, used in section headers.
 */
$downloads = function (string $report, array $extra = []) use ($exportUrl, $pdfUrl): void {
    ?>
    <a class="btn btn-sm btn-outline-secondary" href="<?= e($exportUrl($report, $extra)) ?>" title="Download CSV"><i class="bi bi-filetype-csv"></i><span class="d-none d-sm-inline ms-1">CSV</span></a>
    <a class="btn btn-sm btn-outline-primary" href="<?= e($pdfUrl($report, $extra)) ?>" title="Download PDF"><i class="bi bi-file-earmark-pdf"></i><span class="d-none d-sm-inline ms-1">PDF</span></a>
    <?php
};

// -------------------------------------------------------------- overview data
$overviewKpi = [
    ['Active students',  $num($kpi['active_students'] ?? 0),                    'bi-people-fill',            'primary',   'Currently enrolled'],
    ['Boarders',         $num($kpi['boarders'] ?? 0),                           'bi-house-door-fill',        'info',      'In dormitories'],
    ['Active staff',     $num($kpi['active_staff'] ?? 0),                       'bi-person-badge-fill',      'success',   'Teaching & support'],
    ['Active classes',   $num($kpi['active_classes'] ?? 0),                     'bi-grid-fill',              'secondary', 'Class groups'],
    ['Outstanding fees', $money((float)($kpi['outstanding_fees'] ?? 0)),        'bi-cash-stack',             'danger',    'Unpaid balance'],
    ['Collected (month)', $money((float)($kpi['fees_collected_this_month'] ?? 0)), 'bi-arrow-down-circle-fill', 'success', 'Received this month'],
    ['Absent today',     $num($kpi['absent_today'] ?? 0),                       'bi-calendar-x-fill',        'warning',   'Marked absent'],
    ['Open tickets',     $num($kpi['open_tickets'] ?? 0),                       'bi-ticket-perforated-fill', 'secondary', 'Support queue'],
];

// -------------------------------------------------------------- student data
$totalStudents = count($placement);
$boarders      = count(array_filter($placement, fn(array $r): bool => ($r['student_type'] ?? '') === 'boarding'));
$dayScholars   = count(array_filter($placement, fn(array $r): bool => ($r['student_type'] ?? '') === 'day'));
$classCount    = count(array_unique(array_filter(array_map(fn(array $r): string => (string)($r['class_name'] ?? ''), $placement))));

// -------------------------------------------------------------- finance data
$totalCharged   = $sum($debtors, 'total_charged');
$totalPaid      = $sum($debtors, 'total_paid');
$totalBalance   = $sum($debtors, 'balance');
$overdueFees    = $sum($debtors, 'overdue_amount');
$collectedPeriod = $sum($collection, 'amount_collected');

$channelTotals = [];
foreach ($collection as $row) {
    $channel = trim((string)($row['channel'] ?? '')) ?: 'Other';
    $channelTotals[$channel]['amount'] = ($channelTotals[$channel]['amount'] ?? 0) + (float)($row['amount_collected'] ?? 0);
    $channelTotals[$channel]['count']  = ($channelTotals[$channel]['count'] ?? 0) + (int)($row['payment_count'] ?? 0);
}
arsort($channelTotals);
$channelPeak = $channelTotals ? max(array_column($channelTotals, 'amount')) : 0;

// ---------------------------------------------------------- attendance data
$markedTotal   = (int)$sum($daily, 'marked_count');
$presentTotal  = (int)$sum($daily, 'present_count');
$absentTotal   = (int)$sum($daily, 'absent_count');
$lateTotal     = (int)$sum($daily, 'late_count');
$excusedTotal  = (int)$sum($daily, 'excused_count');
$avgAttendance = $daily ? array_sum(array_column($daily, 'attendance_percent')) / count($daily) : 0.0;

// --------------------------------------------------------------- exam data
$subjectRows    = count($performance);
$candidateEntries = (int)$sum($performance, 'candidates');
$avgExamScore   = $subjectRows ? array_sum(array_column($performance, 'average_percent')) / $subjectRows : 0.0;
$avgPassRate    = $subjectRows ? array_sum(array_column($performance, 'pass_rate')) / $subjectRows : 0.0;
$bestClassName  = '—';
$bestClassScore = -1.0;
foreach ($performance as $row) {
    if ((float)($row['average_percent'] ?? 0) > $bestClassScore) {
        $bestClassScore = (float)($row['average_percent'] ?? 0);
        $bestClassName  = (string)($row['class_name'] ?? '—');
    }
}

// ----------------------------------------------------------------- HR data
$staffWithLeave = count(array_unique(array_filter(array_column($leave, 'staff_name'))));
$netPayTotal    = $sum($payroll, 'total_net_pay');

// --------------------------------------------------------- operations data
$bookTitles     = count($library);
$availableCopies = (int)$sum($library, 'available_copies');
$overdueCount   = count($overdue);
$lowStockCount  = count($inventory);
$occupiedBeds   = (int)$sum($boarding, 'occupied_beds');
$capacityBeds   = (int)$sum($boarding, 'capacity');

// -------------------------------------------------------------- audit data
$auditActions = (int)$sum($audit, 'action_count');
$auditUsers   = count(array_unique(array_filter(array_column($audit, 'user_name'))));
$auditModules = count(array_unique(array_filter(array_column($audit, 'module'))));
?>
<div class="report-page">

  <!-- ── Header ─────────────────────────────────────────────────── -->
  <header class="dashboard-head">
    <div class="dashboard-head-main">
      <div class="dashboard-head-icon" aria-hidden="true"><i class="bi bi-bar-chart-line"></i></div>
      <div class="dashboard-head-text">
        <h1 class="dashboard-title">Reports &amp; analytics</h1>
        <p class="dashboard-subtitle mb-0">Live operational summaries, with CSV and PDF exports for every module.</p>
      </div>
    </div>
    <div class="dashboard-head-side">
      <div class="dashboard-status">
        <span class="badge text-bg-<?= $activeYear ? 'success' : 'warning' ?>"><?= e($activeYear['name'] ?? 'No active year') ?></span>
        <span class="badge text-bg-<?= $term ? 'info' : 'warning' ?>"><?= e($term['name'] ?? 'No active term') ?></span>
      </div>
      <div class="d-flex gap-2">
        <a class="btn btn-sm btn-outline-secondary" href="<?= e($tabUrl($tab)) ?>"><i class="bi bi-arrow-clockwise me-1"></i> Refresh</a>
        <a class="btn btn-sm btn-primary" href="<?= e($tab === 'overview' ? '#export-centre' : url('reports') . '&tab=overview#export-centre') ?>"><i class="bi bi-download me-1"></i> Export centre</a>
      </div>
    </div>
  </header>

  <?php if ($viewsMissing): ?>
  <div class="alert alert-warning d-flex align-items-start gap-2">
    <i class="bi bi-exclamation-triangle-fill mt-1"></i>
    <div>
      <strong>Some database views are missing.</strong>
      KPI values and expiry alerts may show as empty. Apply <code>database/19_reports_views.sql</code> to enable every report.
    </div>
  </div>
  <?php endif; ?>

  <!-- ── Filters ────────────────────────────────────────────────── -->
  <div class="card app-card report-filter-card mb-4" id="report-filters">
    <div class="card-header justify-content-between">
      <span class="d-inline-flex align-items-center gap-2"><i class="bi bi-funnel text-primary"></i> Reporting filters</span>
      <span class="small text-muted fw-normal">Applies to every tab</span>
    </div>
    <div class="card-body py-3">
      <form class="row g-3 align-items-end" method="get">
        <input type="hidden" name="r" value="reports">
        <input type="hidden" name="tab" value="<?= e($tab) ?>">
        <div class="col-6 col-md-4 col-xl-2">
          <label class="form-label">Academic year</label>
          <select class="form-select form-select-sm" name="year_id">
            <option value="">All years</option>
            <?php foreach ($years as $y): ?>
            <option value="<?= (int)$y['id'] ?>" <?= (int)$y['id'] === $yearId ? 'selected' : '' ?>><?= e($y['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-6 col-md-4 col-xl-3">
          <label class="form-label">Examination</label>
          <select class="form-select form-select-sm" name="exam_id">
            <option value="">All examinations</option>
            <?php foreach ($exams as $ex): ?>
            <option value="<?= (int)$ex['id'] ?>" <?= (int)$ex['id'] === $examId ? 'selected' : '' ?>><?= e($ex['name']) ?> (<?= e($ex['exam_type'] ?? '') ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
          <label class="form-label">Period from</label>
          <input class="form-control form-control-sm" type="date" name="date_from" value="<?= e($dateFrom) ?>">
        </div>
        <div class="col-6 col-md-4 col-xl-2">
          <label class="form-label">Period to</label>
          <input class="form-control form-control-sm" type="date" name="date_to" value="<?= e($dateTo) ?>">
        </div>
        <div class="col-6 col-md-4 col-xl-1">
          <label class="form-label" title="Single date used by the attendance report">As-of date</label>
          <input class="form-control form-control-sm" type="date" name="date" value="<?= e($date) ?>">
        </div>
        <div class="col-12 col-xl-2 d-flex gap-2">
          <button class="btn btn-primary btn-sm flex-grow-1" type="submit"><i class="bi bi-check2 me-1"></i> Apply</button>
          <a class="btn btn-outline-secondary btn-sm flex-grow-1" href="<?= url('reports') ?>"><i class="bi bi-x-lg me-1"></i> Reset</a>
        </div>
      </form>
    </div>
  </div>

  <!-- ── Tabs ───────────────────────────────────────────────────── -->
  <nav class="report-tabs" aria-label="Report sections">
    <?php foreach ($tabs as $key => [$label, $icon]): ?>
    <a class="report-tab <?= $tab === $key ? 'active' : '' ?>" href="<?= e($tabUrl($key)) ?>" <?= $tab === $key ? 'aria-current="page"' : '' ?>>
      <i class="bi <?= e($icon) ?>"></i><?= e($label) ?>
    </a>
    <?php endforeach; ?>
  </nav>

<?php if ($tab === 'overview'): ?>

  <!-- ── KPI cards ──────────────────────────────────────────────── -->
  <div class="row g-3 mb-4">
    <?php foreach ($overviewKpi as [$label, $value, $icon, $tone, $note]): ?>
      <?php $stat($label, $value, $icon, $tone, $note, str_contains($label, 'fees') || str_contains($label, 'Collected')); ?>
    <?php endforeach; ?>
  </div>

  <!-- ── Charts ─────────────────────────────────────────────────── -->
  <div class="row g-3 mb-4">
    <?php
      $chart('fee_collection', 'bi-graph-up-arrow', 'Fee collection trend', 'Daily collections');
      $chart('students_by_class', 'bi-diagram-3', 'Students by class', 'Active enrolment');
      $chart('students_by_status', 'bi-pie-chart', 'Students by status', 'All records');
      $chart('attendance', 'bi-calendar-check', 'Attendance rate', 'Daily percentage');
    ?>
  </div>

  <!-- ── Placement and watchlist ────────────────────────────────── -->
  <div class="report-section-head">
    <div>
      <h2>Students &amp; compliance</h2>
      <p>Current placement plus contracts and licences that need attention in the next 90 days.</p>
    </div>
    <div class="d-flex gap-2"><?php $downloads('students'); ?></div>
  </div>

  <div class="row g-3">
    <div class="col-xl-8">
      <div class="card app-card h-100">
        <div class="card-header justify-content-between">
          <span class="d-inline-flex align-items-center gap-2"><i class="bi bi-person-vcard text-primary"></i> Student placement <span class="badge text-bg-light"><?= $num($totalStudents) ?></span></span>
          <a class="small" href="<?= e($tabUrl('students')) ?>">Open students report</a>
        </div>
        <div class="table-responsive">
          <table class="table table-hover table-sm align-middle mb-0" data-table>
            <thead><tr><th>Adm. #</th><th>Name</th><th>Gender</th><th>Class</th><th>Stream</th><th>House</th><th>Type</th><th>Status</th></tr></thead>
            <tbody>
              <?php if (!$placement): ?><tr><td colspan="8" class="text-center text-muted py-4">No active students found.</td></tr><?php endif; ?>
              <?php foreach ($placement as $r): ?>
              <tr>
                <td class="small text-muted"><?= e($r['admission_number'] ?? '—') ?></td>
                <td><strong><?= e($r['full_name'] ?? '—') ?></strong></td>
                <td><?= e(ucfirst((string)($r['gender'] ?? '—'))) ?></td>
                <td><?= e($r['class_name'] ?? '—') ?></td>
                <td><?= e($r['stream_name'] ?? '—') ?></td>
                <td><?= e($r['house_name'] ?? '—') ?></td>
                <td><span class="badge text-bg-<?= ($r['student_type'] ?? '') === 'boarding' ? 'info' : 'secondary' ?>"><?= e(ucfirst((string)($r['student_type'] ?? '—'))) ?></span></td>
                <td><span class="badge text-bg-success"><?= e(ucfirst((string)($r['status'] ?? '—'))) ?></span></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="col-xl-4">
      <?php if (!$contractExpiry && !$licenceAlerts): ?>
      <div class="card app-card h-100">
        <div class="card-body d-flex flex-column align-items-center justify-content-center text-center py-5">
          <i class="bi bi-shield-check text-success" style="font-size:2rem"></i>
          <h3 class="h6 mt-3 mb-1">Nothing expiring soon</h3>
          <p class="small text-muted mb-0">No staff contracts or licences fall due in the next 90 days.</p>
        </div>
      </div>
      <?php endif; ?>

      <?php if ($contractExpiry): ?>
      <div class="card app-card mb-3">
        <div class="card-header justify-content-between">
          <span class="d-inline-flex align-items-center gap-2"><i class="bi bi-briefcase text-warning"></i> Contract expiry <span class="badge text-bg-warning"><?= $num(count($contractExpiry)) ?></span></span>
          <a class="small" href="<?= e($exportUrl('contracts')) ?>">Export</a>
        </div>
        <div class="table-responsive">
          <table class="table table-hover table-sm align-middle report-watch-table mb-0" data-order-column="3" data-order-dir="asc">
            <thead><tr><th>Staff</th><th>Ends</th><th>Days</th></tr></thead>
            <tbody>
              <?php foreach (array_slice($contractExpiry, 0, 6) as $r): ?>
              <?php $days = (int)($r['days_to_expiry'] ?? 0); ?>
              <tr>
                <td><strong class="small"><?= e($r['staff_name'] ?? '—') ?></strong><div class="small text-muted"><?= e($r['position'] ?? '') ?></div></td>
                <td class="small"><?= e($r['end_date'] ?? '—') ?></td>
                <td><span class="badge text-bg-<?= $days <= 14 ? 'danger' : ($days <= 30 ? 'warning' : 'secondary') ?>"><?= $days ?>d</span></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php if (count($contractExpiry) > 6): ?>
        <div class="card-body py-2 small text-muted">Showing 6 of <?= $num(count($contractExpiry)) ?> — export for the full list.</div>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <?php if ($licenceAlerts): ?>
      <div class="card app-card">
        <div class="card-header justify-content-between">
          <span class="d-inline-flex align-items-center gap-2"><i class="bi bi-patch-check text-danger"></i> Licence alerts <span class="badge text-bg-danger"><?= $num(count($licenceAlerts)) ?></span></span>
          <a class="small" href="<?= e($exportUrl('licences')) ?>">Export</a>
        </div>
        <div class="table-responsive">
          <table class="table table-hover table-sm align-middle report-watch-table mb-0" data-order-column="3" data-order-dir="asc">
            <thead><tr><th>Licence</th><th>Expires</th><th>Days</th></tr></thead>
            <tbody>
              <?php foreach (array_slice($licenceAlerts, 0, 6) as $r): ?>
              <?php $days = (int)($r['days_to_expiry'] ?? 0); ?>
              <tr>
                <td><strong class="small"><?= e($r['licence_type'] ?? '—') ?></strong><div class="small text-muted"><?= e($r['licence_number'] ?? '') ?></div></td>
                <td class="small"><?= e($r['expiry_date'] ?? '—') ?></td>
                <td><span class="badge text-bg-<?= $days <= 14 ? 'danger' : ($days <= 30 ? 'warning' : 'secondary') ?>"><?= $days ?>d</span></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php if (count($licenceAlerts) > 6): ?>
        <div class="card-body py-2 small text-muted">Showing 6 of <?= $num(count($licenceAlerts)) ?> — export for the full list.</div>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ── Export centre ──────────────────────────────────────────── -->
  <div class="report-section-head" id="export-centre">
    <div>
      <h2>Export centre</h2>
      <p>Download any report as CSV for spreadsheets or PDF for printing and filing.</p>
    </div>
  </div>
  <div class="card app-card mb-2">
    <div class="table-responsive">
      <table class="table align-middle mb-0 report-export-table">
        <thead><tr><th style="width:11%">Area</th><th style="width:30%">Report</th><th>Contents</th><th class="text-end" style="width:20%">Download</th></tr></thead>
        <tbody>
          <?php
          $exportRows = [
              ['Students', 'Student placement', 'Admission number, class, stream, house, type and status', 'students', [], ''],
              ['Finance', 'Fee debtors', 'Balances, overdue amounts and guardian contacts', 'debtors', ['year_id' => $yearId], $yearId ? '' : 'Select a year above to include debtors'],
              ['Finance', 'Fee collection', 'Collections by date, channel and payment method', 'collection', [], ''],
              ['Attendance', 'Daily attendance', 'Present, absent, late and excused counts per class', 'attendance', ['date' => $date], ''],
              ['Examinations', 'Exam performance', 'Average, highest, lowest and pass rate per subject', 'exams', ['exam_id' => $examId], $examId ? '' : 'Select an examination above'],
              ['HR', 'Leave balances', 'Entitlement, taken, remaining and pending leave', 'leave', [], ''],
              ['HR', 'Payroll summary', 'Gross, statutory deductions and net pay by period', 'payroll', [], ''],
              ['Library', 'Library stock', 'Copies held, available, on loan and lost', 'library', [], ''],
              ['Library', 'Overdue books', 'Borrower, due date and days overdue', 'overdue', [], ''],
              ['Operations', 'Low stock items', 'Current stock against reorder level', 'low_stock', [], ''],
              ['Security', 'Audit log', 'Actions grouped by user, module and day', 'audit', [], ''],
              ['HR', 'Contract expiry', 'Staff contracts ending within 90 days', 'contracts', [], ''],
              ['Compliance', 'Licence alerts', 'Licences and permits nearing expiry', 'licences', [], ''],
          ];
          ?>
          <?php foreach ($exportRows as [$area, $name, $description, $report, $extra, $note]): ?>
          <tr>
            <td><span class="badge text-bg-light"><?= e($area) ?></span></td>
            <td>
              <div class="report-name"><?= e($name) ?></div>
              <?php if ($note !== ''): ?><div class="small text-warning-emphasis"><i class="bi bi-info-circle me-1"></i><?= e($note) ?></div><?php endif; ?>
            </td>
            <td class="report-desc"><?= e($description) ?></td>
            <td class="text-end text-nowrap">
              <a class="btn btn-sm btn-outline-secondary" href="<?= e($exportUrl($report, $extra)) ?>"><i class="bi bi-filetype-csv me-1"></i>CSV</a>
              <a class="btn btn-sm btn-outline-primary" href="<?= e($pdfUrl($report, $extra)) ?>"><i class="bi bi-file-earmark-pdf me-1"></i>PDF</a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

<?php elseif ($tab === 'students'): ?>

  <div class="row g-3 mb-4">
    <?php
      $stat('Active students', $num($totalStudents), 'bi-people-fill', 'primary', 'Enrolled learners');
      $stat('Boarders', $num($boarders), 'bi-house-door-fill', 'info', 'Living on campus');
      $stat('Day scholars', $num($dayScholars), 'bi-sun', 'success', 'Commuting daily');
      $stat('Classes represented', $num($classCount), 'bi-grid', 'secondary', 'With active students');
    ?>
  </div>

  <div class="report-section-head">
    <div>
      <h2>Enrolment profile</h2>
      <p>Class distribution and status mix for active students.</p>
    </div>
    <div class="d-flex gap-2"><?php $downloads('students'); ?></div>
  </div>

  <div class="row g-3 mb-4">
    <?php
      $chart('students_by_class', 'bi-diagram-3', 'Students by class', 'Active enrolment', 'col-lg-6');
      $chart('students_by_status', 'bi-pie-chart', 'Students by status', 'All records', 'col-lg-6');
    ?>
  </div>

  <div class="card app-card">
    <div class="card-header justify-content-between">
      <span class="d-inline-flex align-items-center gap-2"><i class="bi bi-person-lines-fill text-primary"></i> Student placement <span class="badge text-bg-light"><?= $num($totalStudents) ?></span></span>
      <div class="d-flex gap-2"><?php $downloads('students'); ?></div>
    </div>
    <div class="table-responsive">
      <table class="table table-hover table-sm align-middle mb-0" data-table>
        <thead><tr><th>Adm. #</th><th>Name</th><th>Gender</th><th>Class</th><th>Stream</th><th>House</th><th>Type</th><th>Status</th></tr></thead>
        <tbody>
          <?php if (!$placement): ?><tr><td colspan="8" class="text-center text-muted py-4">No active students found.</td></tr><?php endif; ?>
          <?php foreach ($placement as $r): ?>
          <tr>
            <td class="small text-muted"><?= e($r['admission_number'] ?? '—') ?></td>
            <td><strong><?= e($r['full_name'] ?? '—') ?></strong></td>
            <td><?= e(ucfirst((string)($r['gender'] ?? '—'))) ?></td>
            <td><?= e($r['class_name'] ?? '—') ?></td>
            <td><?= e($r['stream_name'] ?? '—') ?></td>
            <td><?= e($r['house_name'] ?? '—') ?></td>
            <td><span class="badge text-bg-<?= ($r['student_type'] ?? '') === 'boarding' ? 'info' : 'secondary' ?>"><?= e(ucfirst((string)($r['student_type'] ?? '—'))) ?></span></td>
            <td><span class="badge text-bg-success"><?= e(ucfirst((string)($r['status'] ?? '—'))) ?></span></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

<?php elseif ($tab === 'finance'): ?>

  <div class="row g-3 mb-4">
    <?php
      $bodyCol = 'col-6 col-md-4 col-xl';
      $stat('Charged', $money($totalCharged), 'bi-receipt', 'secondary', 'Selected year', true, $bodyCol);
      $stat('Paid', $money($totalPaid), 'bi-wallet2', 'success', 'Against those bills', true, $bodyCol);
      $stat('Balance', $money($totalBalance), 'bi-cash-stack', 'danger', 'Still owed', true, $bodyCol);
      $stat('Overdue', $money($overdueFees), 'bi-exclamation-octagon', 'warning', 'Past due date', true, $bodyCol);
      $stat('Collected in period', $money($collectedPeriod), 'bi-arrow-down-circle', 'primary', 'Using the filters above', true, $bodyCol);
    ?>
  </div>

  <div class="report-section-head">
    <div>
      <h2>Fee performance</h2>
      <p>Collections for <?= e($dateFrom) ?> to <?= e($dateTo) ?>, plus the current debtor list.</p>
    </div>
    <div class="d-flex gap-2"><?php $downloads('collection'); ?></div>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-lg-7">
      <?php $chart('fee_collection', 'bi-graph-up-arrow', 'Fee collection trend', 'Daily collections', 'col-12'); ?>
    </div>
    <div class="col-lg-5">
      <div class="card app-card h-100">
        <div class="card-header justify-content-between">
          <span class="d-inline-flex align-items-center gap-2"><i class="bi bi-credit-card-2-front text-primary"></i> Collection by channel</span>
          <span class="badge text-bg-light"><?= $num(count($channelTotals)) ?></span>
        </div>
        <div class="card-body">
          <?php if (!$channelTotals): ?>
          <div class="report-empty" style="min-height:200px"><i class="bi bi-inbox"></i><strong>No payments in this period</strong><span>Adjust the period filters to see collections.</span></div>
          <?php else: ?>
          <div class="report-glance">
            <?php foreach ($channelTotals as $name => $totals): ?>
            <?php $share = $channelPeak > 0 ? ($totals['amount'] / $channelPeak) * 100 : 0; ?>
            <div>
              <div class="glance-label"><span><?= e($name) ?> <span class="text-muted">&middot; <?= $num($totals['count']) ?> txns</span></span><strong><?= e($money($totals['amount'])) ?></strong></div>
              <div class="progress" style="height:6px"><div class="progress-bar bg-primary" style="width:<?= min(100, $share) ?>%"></div></div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-lg-6">
      <div class="card app-card h-100">
        <div class="card-header justify-content-between">
          <span class="d-inline-flex align-items-center gap-2"><i class="bi bi-person-exclamation text-danger"></i> Fee debtors <span class="badge text-bg-light"><?= $num(count($debtors)) ?></span></span>
          <div class="d-flex gap-2"><?php $downloads('debtors', ['year_id' => $yearId]); ?></div>
        </div>
        <div class="table-responsive">
          <table class="table table-hover table-sm align-middle mb-0" data-table data-order-column="2" data-order-dir="desc">
            <thead><tr><th>Student</th><th>Class</th><th>Balance</th><th>Overdue</th><th>Days</th></tr></thead>
            <tbody>
              <?php if (!$debtors): ?><tr><td colspan="5" class="text-center text-muted py-3"><?= $yearId ? 'No debtors for this year.' : 'Select an academic year above to list debtors.' ?></td></tr><?php endif; ?>
              <?php foreach ($debtors as $r): ?>
              <tr>
                <td><strong><?= e($r['student_name'] ?? '—') ?></strong><div class="small text-muted"><?= e($r['admission_number'] ?? '') ?></div></td>
                <td class="small"><?= e($r['class_name'] ?? '—') ?></td>
                <td class="fw-bold text-danger"><?= number_format((float)($r['balance'] ?? 0)) ?></td>
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
      <div class="card app-card h-100">
        <div class="card-header justify-content-between">
          <span class="d-inline-flex align-items-center gap-2"><i class="bi bi-journal-arrow-down text-success"></i> Collections <span class="badge text-bg-light"><?= $num(count($collection)) ?></span></span>
          <div class="d-flex gap-2"><?php $downloads('collection'); ?></div>
        </div>
        <div class="table-responsive">
          <table class="table table-hover table-sm align-middle mb-0" data-table data-order-column="0" data-order-dir="desc">
            <thead><tr><th>Date</th><th>Channel</th><th>Method</th><th>Txns</th><th class="text-end">Amount</th></tr></thead>
            <tbody>
              <?php if (!$collection): ?><tr><td colspan="5" class="text-center text-muted py-3">No payments in the selected period.</td></tr><?php endif; ?>
              <?php foreach ($collection as $r): ?>
              <tr>
                <td class="small"><?= e($r['payment_date'] ?? '—') ?></td>
                <td><?= e(ucfirst((string)($r['channel'] ?? '—'))) ?></td>
                <td class="small"><?= e($r['method_name'] ?? '—') ?></td>
                <td><?= (int)($r['payment_count'] ?? 0) ?></td>
                <td class="fw-bold text-success text-end"><?= number_format((float)($r['amount_collected'] ?? 0)) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

<?php elseif ($tab === 'attendance'): ?>

  <div class="row g-3 mb-4">
    <?php
      $bodyCol = 'col-6 col-md-4 col-xl';
      $stat('Classes marked', $num(count($daily)), 'bi-building-check', 'primary', date('d M Y', strtotime($date)), false, $bodyCol);
      $stat('Present', $num($presentTotal), 'bi-check2-circle', 'success', 'All classes', false, $bodyCol);
      $stat('Absent', $num($absentTotal), 'bi-person-x', 'danger', 'All classes', false, $bodyCol);
      $stat('Late', $num($lateTotal), 'bi-clock-history', 'warning', 'Arrived late', false, $bodyCol);
      $stat('Average rate', number_format($avgAttendance, 1) . '%', 'bi-activity', 'info', 'Across marked classes', false, $bodyCol);
    ?>
  </div>

  <div class="report-section-head">
    <div>
      <h2>Attendance for <?= e(date('d F Y', strtotime($date))) ?></h2>
      <p>Daily register by class, with the period trend alongside.</p>
    </div>
    <div class="d-flex gap-2"><?php $downloads('attendance', ['date' => $date]); ?></div>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-lg-7">
      <?php $chart('attendance', 'bi-calendar-check', 'Attendance trend', e($dateFrom) . ' to ' . e($dateTo), 'col-12'); ?>
    </div>
    <div class="col-lg-5">
      <div class="card app-card h-100">
        <div class="card-header justify-content-between">
          <span class="d-inline-flex align-items-center gap-2"><i class="bi bi-clipboard-data text-primary"></i> Today at a glance</span>
          <span class="small text-muted fw-normal"><?= $num($markedTotal) ?> marks</span>
        </div>
        <div class="card-body">
          <?php if ($markedTotal === 0): ?>
          <div class="report-empty" style="min-height:200px">
            <i class="bi bi-calendar-x"></i>
            <strong>No register marked</strong>
            <span>Open <a href="<?= url('attendance') ?>">Daily attendance</a> to record this date.</span>
          </div>
          <?php else: ?>
          <?php
            $glance = [
                ['Present', $presentTotal, 'success'],
                ['Absent', $absentTotal, 'danger'],
                ['Late', $lateTotal, 'warning'],
                ['Excused', $excusedTotal, 'info'],
            ];
          ?>
          <div class="report-glance">
            <?php foreach ($glance as [$label, $count, $tone]): ?>
            <?php $share = $markedTotal > 0 ? ($count / $markedTotal) * 100 : 0; ?>
            <div>
              <div class="glance-label"><span><?= e($label) ?></span><strong><?= $num($count) ?> <span class="text-muted">(<?= number_format($share, 1) ?>%)</span></strong></div>
              <div class="progress" style="height:6px"><div class="progress-bar bg-<?= e($tone) ?>" style="width:<?= min(100, $share) ?>%"></div></div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <div class="card app-card">
    <div class="card-header justify-content-between">
      <span class="d-inline-flex align-items-center gap-2"><i class="bi bi-table text-primary"></i> Class register <span class="badge text-bg-light"><?= $num(count($daily)) ?></span></span>
      <div class="d-flex gap-2"><?php $downloads('attendance', ['date' => $date]); ?></div>
    </div>
    <div class="table-responsive">
      <table class="table table-hover table-sm align-middle mb-0" data-table>
        <thead><tr><th>Class</th><th>Stream</th><th>Marked</th><th>Present</th><th>Absent</th><th>Late</th><th>Excused</th><th style="width:18%">Attendance</th></tr></thead>
        <tbody>
          <?php if (!$daily): ?><tr><td colspan="8" class="text-center text-muted py-4">No attendance marked for this date.</td></tr><?php endif; ?>
          <?php foreach ($daily as $r): ?>
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
                <div class="progress flex-grow-1" style="height:6px"><div class="progress-bar bg-<?= $pct >= 90 ? 'success' : ($pct >= 75 ? 'warning' : 'danger') ?>" style="width:<?= min(100, $pct) ?>%"></div></div>
                <span class="small fw-bold"><?= number_format($pct, 1) ?>%</span>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

<?php elseif ($tab === 'exams'): ?>

  <?php if (!$examId): ?>
  <div class="card app-card">
    <div class="card-body d-flex flex-column align-items-center justify-content-center text-center py-5">
      <i class="bi bi-clipboard-check text-primary" style="font-size:2.2rem"></i>
      <h2 class="h5 mt-3 mb-1">Choose an examination</h2>
      <p class="small text-muted mb-3">Select an examination in the reporting filters to see class and subject performance.</p>
      <a class="btn btn-sm btn-primary" href="#report-filters"><i class="bi bi-funnel me-1"></i> Use the filters above</a>
    </div>
  </div>
  <?php else: ?>

  <div class="row g-3 mb-4">
    <?php
      $stat('Subject rows', $num($subjectRows), 'bi-journal-check', 'primary', 'Class and subject pairs');
      $stat('Candidate entries', $num($candidateEntries), 'bi-people', 'info', 'Across those subjects');
      $stat('Average score', number_format($avgExamScore, 1) . '%', 'bi-graph-up', 'success', 'Mean of subject averages');
      $stat('Average pass rate', number_format($avgPassRate, 1) . '%', 'bi-patch-check', 'warning', 'Mean of subject pass rates');
    ?>
  </div>

  <div class="report-section-head">
    <div>
      <h2>Performance for the selected examination</h2>
      <p>Best performing class: <strong><?= e($bestClassName) ?></strong><?= $bestClassScore >= 0 ? ' at ' . number_format($bestClassScore, 1) . '%' : '' ?>.</p>
    </div>
    <div class="d-flex gap-2"><?php $downloads('exams', ['exam_id' => $examId]); ?></div>
  </div>

  <div class="row g-3 mb-4">
    <?php $chart('exam_performance', 'bi-bar-chart', 'Average score by class', 'Selected examination', 'col-12'); ?>
  </div>

  <div class="card app-card">
    <div class="card-header justify-content-between">
      <span class="d-inline-flex align-items-center gap-2"><i class="bi bi-table text-primary"></i> Subject performance <span class="badge text-bg-light"><?= $num($subjectRows) ?></span></span>
      <div class="d-flex gap-2"><?php $downloads('exams', ['exam_id' => $examId]); ?></div>
    </div>
    <div class="table-responsive">
      <table class="table table-hover table-sm align-middle mb-0" data-table>
        <thead><tr><th>Exam</th><th>Class</th><th>Subject</th><th>Candidates</th><th>Average</th><th>Highest</th><th>Lowest</th><th>Passed</th><th style="width:16%">Pass rate</th></tr></thead>
        <tbody>
          <?php if (!$performance): ?><tr><td colspan="9" class="text-center text-muted py-4">No results for this examination yet. Process results first.</td></tr><?php endif; ?>
          <?php foreach ($performance as $r): ?>
          <?php $pass = (float)($r['pass_rate'] ?? 0); ?>
          <tr>
            <td class="small text-muted"><?= e($r['exam_name'] ?? '—') ?></td>
            <td><strong><?= e($r['class_name'] ?? '—') ?></strong></td>
            <td><?= e($r['subject_name'] ?? '—') ?></td>
            <td><?= (int)($r['candidates'] ?? 0) ?></td>
            <td><?= number_format((float)($r['average_percent'] ?? 0), 1) ?>%</td>
            <td class="text-success"><?= number_format((float)($r['highest_percent'] ?? 0), 1) ?>%</td>
            <td class="text-danger"><?= number_format((float)($r['lowest_percent'] ?? 0), 1) ?>%</td>
            <td><?= (int)($r['passed_count'] ?? 0) ?></td>
            <td>
              <div class="d-flex align-items-center gap-2">
                <div class="progress flex-grow-1" style="height:6px"><div class="progress-bar bg-<?= $pass >= 80 ? 'success' : ($pass >= 50 ? 'warning' : 'danger') ?>" style="width:<?= min(100, $pass) ?>%"></div></div>
                <span class="small fw-bold"><?= number_format($pass, 1) ?>%</span>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

<?php elseif ($tab === 'hr'): ?>

  <div class="row g-3 mb-4">
    <?php
      $stat('Staff with leave', $num($staffWithLeave), 'bi-person-badge', 'primary', 'Leave records found');
      $stat('Leave records', $num(count($leave)), 'bi-calendar2-week', 'info', 'All leave types');
      $stat('Payroll periods', $num(count($payroll)), 'bi-calendar3', 'secondary', 'Most recent first');
      $stat('Net pay recorded', $money($netPayTotal), 'bi-cash-coin', 'success', 'Across those periods', true);
    ?>
  </div>

  <div class="report-section-head">
    <div>
      <h2>Leave and payroll</h2>
      <p>Current leave balances and the latest payroll runs.</p>
    </div>
    <div class="d-flex gap-2">
      <?php $downloads('leave'); ?>
      <?php $downloads('payroll'); ?>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-lg-7">
      <div class="card app-card h-100">
        <div class="card-header justify-content-between">
          <span class="d-inline-flex align-items-center gap-2"><i class="bi bi-calendar2-week text-primary"></i> Leave balances <span class="badge text-bg-light"><?= $num(count($leave)) ?></span></span>
          <?php $downloads('leave'); ?>
        </div>
        <div class="table-responsive">
          <table class="table table-hover table-sm align-middle mb-0" data-table>
            <thead><tr><th>Staff</th><th>Leave type</th><th>Entitlement</th><th>Taken</th><th>Remaining</th><th>Pending</th></tr></thead>
            <tbody>
              <?php if (!$leave): ?><tr><td colspan="6" class="text-center text-muted py-4">No leave data available.</td></tr><?php endif; ?>
              <?php foreach ($leave as $r): ?>
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
      <div class="card app-card h-100">
        <div class="card-header justify-content-between">
          <span class="d-inline-flex align-items-center gap-2"><i class="bi bi-cash-coin text-success"></i> Payroll summary <span class="badge text-bg-light"><?= $num(count($payroll)) ?></span></span>
          <?php $downloads('payroll'); ?>
        </div>
        <div class="table-responsive">
          <table class="table table-hover table-sm align-middle mb-0" data-table>
            <thead><tr><th>Period</th><th>Employees</th><th class="text-end">Gross</th><th class="text-end">Net</th><th>Status</th></tr></thead>
            <tbody>
              <?php if (!$payroll): ?><tr><td colspan="5" class="text-center text-muted py-4">No payroll data available.</td></tr><?php endif; ?>
              <?php foreach ($payroll as $r): ?>
              <tr>
                <td class="small"><strong><?= e($r['period_label'] ?? '—') ?></strong></td>
                <td><?= (int)($r['employees_paid'] ?? 0) ?></td>
                <td class="text-end"><?= number_format((float)($r['total_gross'] ?? 0)) ?></td>
                <td class="fw-bold text-end"><?= number_format((float)($r['total_net_pay'] ?? 0)) ?></td>
                <td><span class="badge text-bg-<?= ($r['status'] ?? '') === 'paid' ? 'success' : 'secondary' ?>"><?= e(ucfirst((string)($r['status'] ?? '—'))) ?></span></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

<?php elseif ($tab === 'operations'): ?>

  <div class="row g-3 mb-4">
    <?php
      $stat('Library titles', $num($bookTitles), 'bi-journals', 'primary', 'Catalogued titles');
      $stat('Copies available', $num($availableCopies), 'bi-book', 'success', 'Ready to borrow');
      $stat('Overdue loans', $num($overdueCount), 'bi-journal-x', 'danger', 'Past their due date');
      $stat('Low stock items', $num($lowStockCount), 'bi-box-seam', 'warning', 'At or below reorder');
    ?>
  </div>

  <div class="report-section-head">
    <div>
      <h2>Operations overview</h2>
      <p>Library, boarding, transport and stores in one place.</p>
    </div>
    <div class="d-flex gap-2">
      <?php $downloads('library'); ?>
      <?php $downloads('low_stock'); ?>
    </div>
  </div>

  <div class="row g-3 mb-4">
    <?php
      $chart('dorm_occupancy', 'bi-house-door', 'Dormitory occupancy', 'By dormitory', 'col-lg-4');
      $chart('route_load', 'bi-bus-front', 'Route load', 'Seats allocated', 'col-lg-4');
      $chart('low_stock', 'bi-box-seam', 'Low stock', 'Current quantities', 'col-lg-4');
    ?>
  </div>

  <div class="card app-card mb-3">
    <div class="card-header justify-content-between">
      <span class="d-inline-flex align-items-center gap-2"><i class="bi bi-journals text-primary"></i> Library stock <span class="badge text-bg-light"><?= $num(count($library)) ?></span></span>
      <?php $downloads('library'); ?>
    </div>
    <div class="table-responsive">
      <table class="table table-hover table-sm align-middle mb-0" data-table>
        <thead><tr><th>Title</th><th>Author</th><th>Category</th><th>Total</th><th>Available</th><th>Out</th><th>Lost</th><th>Status</th></tr></thead>
        <tbody>
          <?php if (!$library): ?><tr><td colspan="8" class="text-center text-muted py-3">No books found.</td></tr><?php endif; ?>
          <?php foreach ($library as $r): ?>
          <tr>
            <td><strong><?= e($r['title'] ?? '—') ?></strong></td>
            <td class="small"><?= e($r['author'] ?? '—') ?></td>
            <td class="small"><?= e($r['category_name'] ?? '—') ?></td>
            <td><?= (int)($r['total_copies'] ?? 0) ?></td>
            <td class="text-success"><?= (int)($r['available_copies'] ?? 0) ?></td>
            <td><?= (int)($r['copies_out'] ?? 0) ?></td>
            <td class="text-danger"><?= (int)($r['lost_copies'] ?? 0) ?></td>
            <td><span class="badge text-bg-<?= ($r['status'] ?? '') === 'active' ? 'success' : 'secondary' ?>"><?= e(ucfirst((string)($r['status'] ?? '—'))) ?></span></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="row g-3">
    <?php if ($overdue): ?>
    <div class="col-lg-6">
      <div class="card app-card h-100">
        <div class="card-header justify-content-between">
          <span class="d-inline-flex align-items-center gap-2"><i class="bi bi-journal-x text-danger"></i> Overdue books <span class="badge text-bg-danger"><?= $num(count($overdue)) ?></span></span>
          <?php $downloads('overdue'); ?>
        </div>
        <div class="table-responsive">
          <table class="table table-hover table-sm align-middle mb-0" data-table data-order-column="3" data-order-dir="desc">
            <thead><tr><th>Title</th><th>Borrower</th><th>Due</th><th>Days</th></tr></thead>
            <tbody>
              <?php foreach ($overdue as $r): ?>
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

    <div class="col-lg-6">
      <div class="card app-card h-100">
        <div class="card-header justify-content-between">
          <span class="d-inline-flex align-items-center gap-2"><i class="bi bi-house-door text-primary"></i> Dormitory occupancy <span class="badge text-bg-light"><?= $num(count($boarding)) ?></span></span>
          <span class="small text-muted fw-normal"><?= $num($occupiedBeds) ?> / <?= $num($capacityBeds) ?> beds</span>
        </div>
        <div class="table-responsive">
          <table class="table table-hover table-sm align-middle mb-0" data-table>
            <thead><tr><th>Dormitory</th><th>Type</th><th>Capacity</th><th>Occupied</th><th>Vacancies</th><th style="width:18%">Occupancy</th></tr></thead>
            <tbody>
              <?php if (!$boarding): ?><tr><td colspan="6" class="text-center text-muted py-3">No dormitory data.</td></tr><?php endif; ?>
              <?php foreach ($boarding as $r): ?>
              <?php $occ = (float)($r['occupancy_percent'] ?? 0); ?>
              <tr>
                <td><strong><?= e($r['dormitory_name'] ?? '—') ?></strong></td>
                <td><?= e(ucfirst((string)($r['dormitory_type'] ?? '—'))) ?></td>
                <td><?= (int)($r['capacity'] ?? 0) ?></td>
                <td><?= (int)($r['occupied_beds'] ?? 0) ?></td>
                <td class="<?= (int)($r['vacancies'] ?? 0) === 0 ? 'text-danger' : 'text-success' ?>"><?= (int)($r['vacancies'] ?? 0) ?></td>
                <td>
                  <div class="d-flex align-items-center gap-2">
                    <div class="progress flex-grow-1" style="height:6px"><div class="progress-bar bg-<?= $occ >= 95 ? 'danger' : ($occ >= 80 ? 'warning' : 'success') ?>" style="width:<?= min(100, $occ) ?>%"></div></div>
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

    <div class="col-lg-6">
      <div class="card app-card h-100">
        <div class="card-header justify-content-between">
          <span class="d-inline-flex align-items-center gap-2"><i class="bi bi-bus-front text-primary"></i> Transport route load <span class="badge text-bg-light"><?= $num(count($transport)) ?></span></span>
        </div>
        <div class="table-responsive">
          <table class="table table-hover table-sm align-middle mb-0" data-table>
            <thead><tr><th>Code</th><th>Route</th><th>Plate</th><th>Capacity</th><th>Allocated</th><th style="width:18%">Load</th><th>Status</th></tr></thead>
            <tbody>
              <?php if (!$transport): ?><tr><td colspan="7" class="text-center text-muted py-3">No route data.</td></tr><?php endif; ?>
              <?php foreach ($transport as $r): ?>
              <?php $load = (float)($r['capacity_percent'] ?? 0); ?>
              <tr>
                <td class="small text-muted"><?= e($r['route_code'] ?? '—') ?></td>
                <td><strong><?= e($r['route_name'] ?? '—') ?></strong></td>
                <td class="small"><?= e($r['plate_number'] ?? '—') ?></td>
                <td><?= (int)($r['seating_capacity'] ?? 0) ?></td>
                <td><?= (int)($r['students_allocated'] ?? 0) ?></td>
                <td>
                  <div class="d-flex align-items-center gap-2">
                    <div class="progress flex-grow-1" style="height:6px"><div class="progress-bar bg-<?= $load >= 95 ? 'danger' : ($load >= 80 ? 'warning' : 'success') ?>" style="width:<?= min(100, $load) ?>%"></div></div>
                    <span class="small"><?= number_format($load, 1) ?>%</span>
                  </div>
                </td>
                <td><span class="badge text-bg-<?= ($r['status'] ?? '') === 'active' ? 'success' : 'secondary' ?>"><?= e(ucfirst((string)($r['status'] ?? '—'))) ?></span></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="col-lg-6">
      <div class="card app-card h-100">
        <div class="card-header justify-content-between">
          <span class="d-inline-flex align-items-center gap-2"><i class="bi bi-box-seam text-warning"></i> Low stock items <span class="badge text-bg-<?= $inventory ? 'warning' : 'light' ?>"><?= $num(count($inventory)) ?></span></span>
          <?php $downloads('low_stock'); ?>
        </div>
        <div class="table-responsive">
          <table class="table table-hover table-sm align-middle mb-0" data-table data-order-column="5" data-order-dir="desc">
            <thead><tr><th>Code</th><th>Item</th><th>Unit</th><th>Stock</th><th>Reorder</th><th>Shortfall</th></tr></thead>
            <tbody>
              <?php if (!$inventory): ?><tr><td colspan="6" class="text-center text-muted py-3">All items are above reorder level.</td></tr><?php endif; ?>
              <?php foreach ($inventory as $r): ?>
              <tr>
                <td class="small text-muted"><?= e($r['item_code'] ?? '—') ?></td>
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

<?php elseif ($tab === 'audit'): ?>

  <div class="row g-3 mb-4">
    <?php
      $stat('Log entries', $num(count($audit)), 'bi-journal-text', 'primary', 'Grouped rows');
      $stat('Total actions', $num($auditActions), 'bi-activity', 'info', 'Within those rows');
      $stat('Users', $num($auditUsers), 'bi-people', 'success', 'Distinct actors');
      $stat('Modules', $num($auditModules), 'bi-grid-3x3-gap', 'secondary', 'Areas touched');
    ?>
  </div>

  <div class="report-section-head">
    <div>
      <h2>Activity summary</h2>
      <p>Actions grouped by user, module, action and day. Newest activity first.</p>
    </div>
    <div class="d-flex gap-2"><?php $downloads('audit'); ?></div>
  </div>

  <div class="card app-card">
    <div class="card-header justify-content-between">
      <span class="d-inline-flex align-items-center gap-2"><i class="bi bi-shield-check text-primary"></i> Audit log <span class="badge text-bg-light"><?= $num(count($audit)) ?></span></span>
      <?php $downloads('audit'); ?>
    </div>
    <div class="table-responsive">
      <table class="table table-hover table-sm align-middle mb-0" data-table data-order-column="3" data-order-dir="desc">
        <thead><tr><th>User</th><th>Module</th><th>Action</th><th>Date</th><th>Count</th></tr></thead>
        <tbody>
          <?php if (!$audit): ?><tr><td colspan="5" class="text-center text-muted py-4">No audit entries.</td></tr><?php endif; ?>
          <?php foreach ($audit as $r): ?>
          <tr>
            <td><strong><?= e($r['user_name'] ?? '—') ?></strong></td>
            <td><span class="badge text-bg-light"><?= e(ucfirst((string)($r['module'] ?? '—'))) ?></span></td>
            <td class="small"><?= e(ucfirst(str_replace('_', ' ', (string)($r['action'] ?? '')))) ?></td>
            <td class="small"><?= e($r['activity_date'] ?? '—') ?></td>
            <td><span class="badge text-bg-secondary"><?= (int)($r['action_count'] ?? 0) ?></span></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

<?php endif; ?>
</div>

<script>
window.APP = window.APP || {};
window.APP.reportsChartConfig = <?= json_encode($chartConfig, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
window.APP.reportsChartTypes = <?= json_encode($chartType, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

document.addEventListener('DOMContentLoaded', function () {
  if (!window.Chart) { return; }

  var config = window.APP.reportsChartConfig || {};
  var types = window.APP.reportsChartTypes || {};

  function palette(type, count) {
    var sets = {
      line:   ['#2563eb', '#16a34a', '#0891b2', '#7c3aed', '#d97706'],
      doughnut: ['#2563eb', '#16a34a', '#d97706', '#dc2626', '#0891b2', '#7c3aed', '#be185d', '#0d9488', '#6366f1', '#84cc16'],
      bar:    ['#2563eb', '#16a34a', '#0891b2', '#7c3aed', '#d97706', '#dc2626', '#be185d']
    };
    var base = sets[type] || sets.line;
    var out = [];
    for (var i = 0; i < count; i++) { out.push(base[i % base.length]); }
    return out;
  }

  function formatValue(value, money) {
    if (value === null || value === undefined) { return ''; }
    return money ? new Intl.NumberFormat().format(value) : value;
  }

  document.querySelectorAll('canvas[data-chart-key]').forEach(function (canvas) {
    var key = canvas.dataset.chartKey;
    var data = config[key];
    if (!data) { return; }

    var type = types[key] || 'line';
    var labels = data.labels || [];
    var source = data.datasets || [];
    var colors = palette(type, Math.max(source.length, 1));

    var datasets = source.map(function (dataset, index) {
      var color = colors[index % colors.length];
      var copy = Object.assign({}, dataset);
      copy.backgroundColor = dataset.backgroundColor || color;
      copy.borderColor = dataset.borderColor || color;
      copy.borderWidth = dataset.borderWidth !== undefined ? dataset.borderWidth : (type === 'line' ? 2.5 : 1);

      if (type === 'line') {
        copy.tension = dataset.tension !== undefined ? dataset.tension : 0.35;
        copy.fill = dataset.fill !== undefined ? dataset.fill : true;
        copy.pointRadius = 3;
        copy.pointHoverRadius = 5;
        copy.pointBackgroundColor = copy.borderColor;
      }
      if (type === 'bar') {
        copy.borderRadius = 6;
        copy.maxBarThickness = 38;
        copy.categoryPercentage = 0.7;
        copy.barPercentage = 0.85;
      }
      return copy;
    });

    var options = {
      responsive: true,
      maintainAspectRatio: false,
      interaction: { mode: type === 'doughnut' ? 'nearest' : 'index', intersect: false },
      plugins: {
        legend: {
          display: type === 'doughnut' || datasets.length > 1,
          position: 'bottom',
          labels: { usePointStyle: true, boxWidth: 8, padding: 14, font: { size: 11 } }
        },
        tooltip: {
          backgroundColor: '#0f172a',
          padding: 10,
          cornerRadius: 8,
          displayColors: type !== 'doughnut',
          callbacks: {
            label: function (context) {
              var money = /fee|collect|amount|pay|gross|net/i.test(context.dataset.label || '');
              var raw = context.parsed && context.parsed.y !== undefined && context.parsed.y !== null
                ? context.parsed.y
                : context.parsed;
              var prefix = context.dataset.label ? context.dataset.label + ': ' : '';
              var text = prefix + formatValue(raw, money);
              if (type === 'doughnut') {
                var total = (context.dataset.data || []).reduce(function (a, b) { return a + Number(b || 0); }, 0);
                text += total ? ' (' + Math.round((Number(raw) / total) * 100) + '%)' : '';
              }
              return text;
            }
          }
        }
      }
    };

    if (type === 'doughnut') {
      options.cutout = '62%';
      datasets = datasets.map(function (dataset) {
        return Object.assign({}, dataset, { borderColor: '#ffffff', borderWidth: 3, hoverOffset: 6 });
      });
    }

    if (type === 'line' || type === 'bar') {
      options.scales = {
        x: {
          grid: { display: false },
          ticks: { maxRotation: 0, autoSkip: true, color: '#64748b', font: { size: 11 } }
        },
        y: {
          beginAtZero: true,
          grid: { color: 'rgba(148, 163, 184, .18)' },
          ticks: { color: '#64748b', font: { size: 11 } }
        }
      };
      if (type === 'bar') {
        options.plugins.legend.display = false;
      }
    }

    new window.Chart(canvas, { type: type, data: { labels: labels, datasets: datasets }, options: options });
  });
});
</script>
