<div class="page-head">
  <div>
    <h1 class="h4 mb-0">Student transfers</h1>
    <p class="text-muted small mb-0">Students joining from and leaving for other schools</p>
  </div>
  <form method="get" action="<?= url('students/transfers') ?>" class="d-flex gap-2">
    <select class="form-select form-select-sm" name="direction" data-submit-on-change>
      <option value="">All transfers</option>
      <option value="in" <?= $direction === 'in' ? 'selected' : '' ?>>Transfer in</option>
      <option value="out" <?= $direction === 'out' ? 'selected' : '' ?>>Transfer out</option>
    </select>
  </form>
</div>

<div class="row g-3">
<?php if ($canAdd): ?>
  <div class="col-lg-4">
    <div class="card app-card">
      <div class="card-header">Record a transfer</div>
      <div class="card-body">
        <form method="post" action="<?= url('students/transfers/store') ?>">
          <?= csrf_field() ?>
          <div class="form-section"><i class="bi bi-person-badge"></i> Student transfer</div>
          <div class="mb-3">
            <label class="form-label" for="t_student">Student</label>
            <select class="form-select" id="t_student" name="student_id" required>
              <option value="">Choose…</option>
<?php foreach ($students as $student): ?>
              <option value="<?= (int)$student['id'] ?>"><?= e($student['admission_number'] . ' — ' . $student['last_name'] . ' ' . $student['first_name']) ?></option>
<?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label" for="t_direction">Direction</label>
            <select class="form-select" id="t_direction" name="direction" required>
              <option value="in">Transfer in (joining us)</option>
              <option value="out">Transfer out (leaving us)</option>
            </select>
          </div>
          <div class="form-section"><i class="bi bi-building"></i> Destination details</div>
          <div class="mb-3">
            <label class="form-label" for="t_school">Other school</label>
            <input class="form-control" id="t_school" name="other_school_name" maxlength="160" required>
          </div>
          <div class="mb-3">
            <label class="form-label" for="t_date">Transfer date</label>
            <input class="form-control" id="t_date" name="transfer_date" type="date" value="<?= date('Y-m-d') ?>" required>
          </div>
          <div class="mb-3">
            <label class="form-label" for="t_class">Receiving class (transfer in)</label>
            <select class="form-select" id="t_class" name="receiving_class_id">
              <option value="0">— None —</option>
<?php foreach ($classes as $class): ?>
              <option value="<?= (int)$class['id'] ?>"><?= e($class['name']) ?></option>
<?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label" for="t_reason">Reason</label>
            <input class="form-control" id="t_reason" name="reason" maxlength="255">
          </div>
          <div class="mb-3">
            <label class="form-label" for="t_prev_result">Previous result</label>
            <input class="form-control" id="t_prev_result" name="previous_result" maxlength="120">
          </div>
          <div class="form-check mb-3">
            <input class="form-check-input" id="t_docs" name="documents_received" type="checkbox" checked>
            <label class="form-check-label" for="t_docs">Documents received</label>
          </div>
          <button class="btn btn-primary" type="submit">Record transfer</button>
        </form>
      </div>
    </div>
  </div>
<?php endif; ?>

  <div class="col-lg-8">
    <div class="card app-card">
      <div class="card-header">Transfer history</div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0" data-table>
            <thead><tr><th>Date</th><th>Student</th><th>Direction</th><th>School</th><th>Class</th><th>Docs</th><th class="text-end">Certificate</th></tr></thead>
            <tbody>
<?php if (!$transfers): ?>
              <tr><td colspan="7" class="text-center text-muted py-4">No transfers recorded.</td></tr>
<?php endif; ?>
<?php foreach ($transfers as $transfer): ?>
              <tr>
                <td class="text-nowrap"><?= e(date('d M Y', strtotime($transfer['transfer_date']))) ?></td>
                <td>
                  <a href="<?= url('students/profile', ['id' => (int)$transfer['student_id']]) ?>" class="text-decoration-none fw-semibold">
                    <?= e($transfer['last_name'] . ' ' . $transfer['first_name']) ?>
                  </a>
                  <div class="small text-muted"><?= e($transfer['admission_number']) ?></div>
                </td>
                <td><span class="badge text-bg-<?= $transfer['direction'] === 'in' ? 'success' : 'warning' ?>"><?= $transfer['direction'] === 'in' ? 'In' : 'Out' ?></span></td>
                <td><?= e($transfer['other_school_name']) ?></td>
                <td><?= e($transfer['receiving_class_name'] ?? '—') ?></td>
                <td><?= (int)$transfer['documents_received'] ? '✓' : '—' ?></td>
                <td class="text-end text-nowrap">
<?php if ($transfer['direction'] === 'out'): ?>
                  <a class="btn btn-sm btn-outline-secondary" href="<?= url('students/transfers/certificate', ['student_id' => (int)$transfer['student_id']]) ?>" target="_blank"><i class="bi bi-file-earmark-text"></i> Cert</a>
<?php else: ?>
                  <span class="text-muted small">—</span>
<?php endif; ?>
                </td>
              </tr>
<?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
