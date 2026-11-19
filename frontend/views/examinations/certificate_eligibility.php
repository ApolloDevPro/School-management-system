<div class="page-head">
  <div>
    <h1 class="h4 mb-0">UCE Certificate Eligibility</h1>
    <p class="text-muted small mb-0">Check whether <?= e($student['admission_number'] . ' — ' . $student['first_name'] . ' ' . $student['last_name']) ?> qualifies for a UCE certificate</p>
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
        <label class="form-label">Exam</label>
        <p class="form-control-static"><?= e($exam['name'] . ' — ' . $exam['year_name'] ?? '' . ', ' . $exam['term_name'] ?? '') ?></p>
      </div>
    </div>

    <div class="alert alert-<?= $eligible ? 'success' : 'warning' ?> mt-3">
      <strong>Result code: <?= e($resultCode) ?></strong><br>
      <?= e($reason) ?>
      <p class="small mb-0 mt-2">UNEB's own result remains the official record. Fee clearance is advisory and never blocks national examinations.</p>
    </div>

    <div class="mt-3">
      <table class="table table-hover">
        <thead>
          <tr>
            <th>Subject</th>
            <th>Code</th>
            <th>Grade</th>
            <th>Grade name</th>
            <th>%</th>
          </tr>
        </thead>
        <tbody>
<?php if ($results): foreach ($results as $row): ?>
          <tr>
            <td><?= e($row['subject_name']) ?></td>
            <td><?= e($row['subject_code']) ?></td>
            <td><span class="badge bg-<?= e($row['grade_code'] === 'A' ? 'success' : ($row['grade_code'] === 'B' ? 'info' : ($row['grade_code'] === 'C' ? 'primary' : ($row['grade_code'] === 'D' ? 'warning' : 'danger')))) ?>"><?= e($row['grade_code']) ?></span></td>
            <td><?= e($row['grade_name'] ?? '-') ?></td>
            <td><?= number_format((float)($row['weighted_percent'] ?? 0), 2) ?>%</td>
          </tr>
<?php endforeach; else: ?>
          <tr><td colspan="5" class="text-center text-muted py-3">No results recorded for this exam.</td></tr>
<?php endif; ?>
        </tbody>
      </table>
    </div>

    <?php if ($summary): ?>
    <div class="row g-3 mt-3 small">
      <div class="col-md-4"><strong>Subjects taken:</strong> <?= (int)$summary['subjects_taken'] ?></div>
      <div class="col-md-4"><strong>Subjects passed:</strong> <?= (int)$summary['subjects_passed'] ?></div>
      <div class="col-md-4"><strong>Average:</strong> <?= number_format((float)$summary['average_percent'], 2) ?>%</div>
    </div>
    <?php endif; ?>

    <div class="mt-3 d-flex flex-wrap gap-2">
      <a class="btn btn-primary" href="<?= url('examinations/transcript', ['student_id' => (int)$student['id'], 'exam_id' => (int)$exam['id']]) ?>"><i class="bi bi-file-earmark-text"></i> View transcript</a>
      <a class="btn btn-outline-danger" href="<?= url('examinations/certificate-eligibility', ['student_id' => (int)$student['id'], 'exam_id' => (int)$exam['id'], 'format' => 'pdf']) ?>"><i class="bi bi-download"></i> Download eligibility PDF</a>
    </div>
  </div>
</div>
