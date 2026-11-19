<div class="page-head">
  <div>
    <h1 class="h4 mb-0">Academic Transcript</h1>
    <p class="text-muted small mb-0">Cumulative academic record for <?= e($student['admission_number'] . ' — ' . $student['first_name'] . ' ' . $student['last_name']) ?></p>
  </div>
  <div class="text-end">
    <a class="btn btn-outline-secondary" href="<?= url('examinations') ?>"><i class="bi bi-arrow-left"></i> Back to examinations</a>
  </div>
</div>

<div class="card app-card mb-3">
  <div class="card-body">
    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label">Admission number</label>
        <p class="form-control-static"><?= e($student['admission_number']) ?></p>
      </div>
      <div class="col-md-6">
        <label class="form-label">Name</label>
        <p class="form-control-static"><?= e($student['first_name'] . ' ' . $student['last_name']) ?></p>
      </div>
      <div class="col-md-6">
        <label class="form-label">Class / Stream</label>
        <p class="form-control-static"><?= e($student['class_name'] ?? '-') . ' / ' . e($student['stream_name'] ?? '-') ?></p>
      </div>
      <div class="col-md-4">
        <label class="form-label">Select exam</label>
        <select class="form-select" id="exam_pick" data-submit-on-change>
<?php foreach ($exams as $row): ?>
          <option value="<?= (int)$row['id'] ?>" <?= $exam && (int)$exam['id'] === (int)$row['id'] ? 'selected' : '' ?>>
            <?= e($row['name'] . ' — ' . $row['year_name'] . ', ' . $row['term_name']) ?>
          </option>
<?php endforeach; ?>
        </select>
      </div>
    </div>

    <?php if ($exam): ?>
    <div class="mt-3">
      <label class="form-label">Exam: <strong><?= e($exam['name'] . ' — ' . $exam['year_name'] . ', ' . $exam['term_name']) ?></strong></label>
      <?php if ($summary): ?>
        <div class="row g-3 mt-2 small">
          <div class="col-md-3"><strong>Subjects taken:</strong> <?= (int)$summary['subjects_taken'] ?></div>
          <div class="col-md-3"><strong>Average:</strong> <?= number_format((float)$summary['average_percent'], 2) ?>%</div>
          <div class="col-md-3"><strong>Total points:</strong> <?= $summary['total_points'] !== null ? number_format((float)$summary['total_points'], 2) : 'n/a' ?></div>
        </div>
      <?php endif; ?>
    </div>

    <div class="card app-card mt-3">
      <div class="card-body p-0">
        <table class="table table-hover mb-0">
          <thead>
            <tr>
              <th>Subject</th>
              <th>Code</th>
              <th>Level</th>
              <th>Grade</th>
              <th>Grade name</th>
              <th>Points</th>
              <th>%</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
<?php if ($results): foreach ($results as $row): ?>
            <tr>
              <td><?= e($row['subject_name']) ?></td>
              <td><?= e($row['subject_code']) ?></td>
              <td><?= e($row['subject_level'] ?? '-') ?></td>
              <td><span class="badge bg-<?= e($row['grade_code'] === 'A' ? 'success' : ($row['grade_code'] === 'B' ? 'info' : ($row['grade_code'] === 'C' ? 'primary' : ($row['grade_code'] === 'D' ? 'warning' : 'danger')))) ?>"><?= e($row['grade_code']) ?></span></td>
              <td><?= e($row['grade_name'] ?? '-') ?></td>
              <td><?= $row['points'] !== null ? number_format((float)$row['points'], 2) : 'n/a' ?></td>
              <td><?= number_format((float)($row['percentage'] ?? 0), 2) ?>%</td>
              <td><span class="badge bg-<?= $row['mark_status'] === 'published' ? 'success' : ($row['mark_status'] === 'locked' ? 'secondary' : 'warning') ?>"><?= e(ucfirst($row['mark_status'] ?? '-')) ?></span></td>
            </tr>
<?php endforeach; else: ?>
            <tr><td colspan="8" class="text-center text-muted py-3">No results published for this exam.</td></tr>
<?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="mt-3 d-flex flex-wrap gap-2">
      <a class="btn btn-primary" href="<?= url('report_cards/pdf', ['student_id' => (int)$student['id'], 'exam_id' => (int)$exam['id']]) ?>" target="_blank"><i class="bi bi-file-earmark-pdf"></i> Print report card</a>
      <a class="btn btn-outline-danger" href="<?= url('examinations/transcript', ['student_id' => (int)$student['id'], 'exam_id' => (int)$exam['id'], 'format' => 'pdf']) ?>"><i class="bi bi-download"></i> Download transcript PDF</a>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php if ($exam): ?>
<script>
  document.getElementById('exam_pick').addEventListener('change', function () {
    const url = new URL(window.location.href);
    url.searchParams.set('exam_id', this.value);
    window.location.href = url.toString();
  });
</script>
<?php endif; ?>
