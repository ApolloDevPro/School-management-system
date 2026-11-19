<div class="page-head">
  <div>
    <h1 class="h4 mb-0">Report Cards</h1>
    <p class="text-muted small mb-0">Generate and view student report cards by term and exam.</p>
  </div>
  <?php if ($examId > 0 && $classId > 0): ?>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-primary" target="_blank" href="<?= url('report_cards/batch', ['class_id' => $classId, 'stream_id' => (int)$streamId, 'exam_id' => $examId]) ?>"><i class="bi bi-printer"></i> Print all cards</a>
    <a class="btn btn-outline-secondary" href="<?= url('report_cards', ['term_id' => $termId, 'class_id' => $classId, 'stream_id' => (int)$streamId, 'exam_id' => $examId, 'export' => 'csv']) ?>"><i class="bi bi-file-earmark-excel"></i> Export CSV</a>
  </div>
  <?php endif; ?>
</div>

<div class="card app-card filter-bar mb-3">
  <div class="card-body">
    <form method="get" action="<?= url('report_cards') ?>" class="row g-2 align-items-end">
      <div class="col-md-3">
        <label class="form-label" for="f_term">Academic Term</label>
        <select class="form-select form-select-sm" id="f_term" name="term_id">
          <option value="0">Select term</option>
          <?php foreach ($terms as $t): ?>
            <option value="<?= (int)$t['id'] ?>" <?= $termId === (int)$t['id'] ? 'selected' : '' ?>>
              <?= e($t['year_name']) ?> &bull; <?= e($t['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label" for="f_class">Class</label>
        <select class="form-select form-select-sm" id="f_class" name="class_id">
          <option value="0">All classes</option>
          <?php foreach ($classes as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= $classId === (int)$c['id'] ? 'selected' : '' ?>>
              <?= e($c['name']) ?> (<?= e($c['level']) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label" for="f_stream">Stream</label>
        <select class="form-select form-select-sm" id="f_stream" name="stream_id">
          <option value="0">All streams</option>
          <?php foreach ($streams as $s): ?>
            <option value="<?= (int)$s['id'] ?>" <?= $streamId === (int)$s['id'] ? 'selected' : '' ?>>
              <?= e($s['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label" for="f_exam">Exam</label>
        <select class="form-select form-select-sm" id="f_exam" name="exam_id">
          <option value="0">Select exam</option>
          <?php foreach ($exams as $ex): ?>
            <option value="<?= (int)$ex['id'] ?>" <?= $examId === (int)$ex['id'] ? 'selected' : '' ?>>
              <?= e($ex['name']) ?> &mdash; <?= e($ex['status']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2 d-flex gap-2">
        <button class="btn btn-sm btn-primary flex-fill" type="submit">Apply</button>
        <a class="btn btn-sm btn-outline-secondary" href="<?= url('report_cards') ?>">Clear</a>
      </div>
    </form>
  </div>
</div>

<?php if ($examId > 0 && $students): ?>
<div class="card app-card mb-3">
  <div class="card-body">
    <h5 class="card-title mb-3">Students &mdash; Select a student to view report card</h5>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0" data-table>
        <thead>
          <tr>
            <th>Admission no.</th>
            <th>Name</th>
            <th>Class / Stream</th>
            <th>House</th>
            <th class="text-end">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($students as $s): ?>
          <tr>
            <td><?= e($s['admission_number']) ?></td>
            <td><?= e($s['first_name'] . ' ' . $s['last_name']) ?></td>
            <td><?= e($s['class_name'] ?? '') ?> / <?= e($s['stream_name'] ?? '—') ?></td>
            <td><?= e($s['house_name'] ?? '—') ?></td>
            <td class="text-end">
              <a class="btn btn-sm btn-outline-primary" href="<?= url('report_cards/view', ['student_id' => (int)$s['id'], 'exam_id' => $examId]) ?>">View</a>
              <a class="btn btn-sm btn-outline-secondary" href="<?= url('report_cards/pdf', ['student_id' => (int)$s['id'], 'exam_id' => $examId]) ?>" target="_blank">PDF</a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php elseif ($examId > 0): ?>
<div class="alert alert-info">
  No active students found for the selected class and exam.
</div>
<?php endif; ?>
