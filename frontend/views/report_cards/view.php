<div class="page-head d-flex justify-content-between align-items-start">
  <div>
    <h1 class="h4 mb-0">Report Card</h1>
    <p class="text-muted small mb-0"><?= e($card['exam']['name'] ?? '') ?> &middot; <?= e($card['student']['admission_number']) ?></p>
  </div>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary" href="<?= url('report_cards', ['term_id' => (int)($_GET['term_id'] ?? 0), 'class_id' => (int)($_GET['class_id'] ?? 0), 'stream_id' => (int)($_GET['stream_id'] ?? 0), 'exam_id' => $card['exam']['id']) ?>"><i class="bi bi-arrow-left"></i> Back to list</a>
    <a class="btn btn-primary" href="<?= url('report_cards/pdf', ['student_id' => (int)$card['student']['id'], 'exam_id' => $card['exam']['id']) ?>" target="_blank"><i class="bi bi-file-earmark-pdf"></i> Download PDF</a>
  </div>
</div>

<div class="card app-card mb-3">
  <div class="card-body">
    <div class="d-flex align-items-center gap-3 mb-4">
      <?php if (!empty($card['student']['photo_path'])): ?>
        <img src="<?= e(asset($card['student']['photo_path'])) ?>" alt="Student photo" class="rounded-circle border" style="width:72px;height:72px;object-fit:cover;">
      <?php endif; ?>
      <div class="row g-3 flex-grow-1">
      <div class="col-md-4">
        <label class="form-label small text-muted">Student</label>
        <p class="mb-0 fw-bold"><?= e($card['student']['first_name'] . ' ' . $card['student']['last_name']) ?></p>
      </div>
      <div class="col-md-3">
        <label class="form-label small text-muted">Admission No.</label>
        <p class="mb-0"><?= e($card['student']['admission_number']) ?></p>
      </div>
      <div class="col-md-3">
        <label class="form-label small text-muted">Class / Stream</label>
        <p class="mb-0"><?= e($card['student']['class_name'] ?? '') ?> / <?= e($card['student']['stream_name'] ?? '—') ?></p>
      </div>
      <div class="col-md-2">
        <label class="form-label small text-muted">House</label>
        <p class="mb-0"><?= e($card['student']['house_name'] ?? '—') ?></p>
      </div>
      </div>
    </div>

    <div class="row g-3 mb-4">
      <div class="col-md-4">
        <label class="form-label small text-muted">Term / Exam</label>
        <p class="mb-0"><?= e($card['exam']['name'] ?? '') ?> &mdash; <?= e($card['exam']['status'] ?? '') ?></p>
      </div>
      <div class="col-md-3">
        <label class="form-label small text-muted">Guardian</label>
        <p class="mb-0"><?= $card['guardian'] ? e($card['guardian']['full_name'] ?? ($card['guardian']['first_name'] . ' ' . $card['guardian']['last_name'])) : '—' ?></p>
      </div>
      <div class="col-md-2">
        <label class="form-label small text-muted">Subjects Taken</label>
        <p class="mb-0 fw-bold"><?= count($card['results']) ?></p>
      </div>
      <div class="col-md-3">
        <label class="form-label small text-muted">Average</label>
        <p class="mb-0 fw-bold">
          <?php
          $summary = $card['summary'] ?? null;
          $avg = $summary ? (float)($summary['average_percent'] ?? 0) : 0;
          echo number_format($avg, 1) . '%';
          ?>
        </p>
      </div>
    </div>

    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th style="width:10%">Code</th>
            <th style="width:26%">Subject</th>
            <th style="width:12%" class="text-center">CA</th>
            <th style="width:12%" class="text-center">Exam</th>
            <th style="width:12%" class="text-right">Total %</th>
            <th style="width:10%" class="text-center">Grade</th>
            <th style="width:14%">Grade Name</th>
            <th>Remarks</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($card['results'] as $r): ?>
          <tr>
            <td><?= e($r['subject_code'] ?? '') ?></td>
            <td><?= e($r['subject_name'] ?? '') ?></td>
            <td class="text-center"><?= $r['ca_mark'] !== null ? number_format((float)$r['ca_mark'], 1) : '—' ?></td>
            <td class="text-center"><?= $r['exam_mark'] !== null ? number_format((float)$r['exam_mark'], 1) : '—' ?></td>
            <td class="text-right"><?php $p = (float)($r['weighted_percent'] ?? $r['mark_percent'] ?? 0); echo number_format($p, 1); ?>%</td>
            <td class="text-center"><strong><?= e($r['grade_code'] ?? '—') ?></strong></td>
            <td><?= e($r['grade_name'] ?? '—') ?></td>
            <td><?= e($r['mark_remarks'] ?? '') ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <?php if ($summary): ?>
        <tfoot>
          <tr>
            <td colspan="4"><strong>Average</strong></td>
            <td class="text-right"><strong><?= number_format((float)($summary['average_percent'] ?? 0), 1) ?>%</strong></td>
            <td class="text-center"><strong><?= $summary['total_points'] !== null ? number_format((float)$summary['total_points'], 1) : '—' ?></strong></td>
            <td colspan="2"><?= $summary['subjects_passed'] ?> of <?= $summary['subjects_taken'] ?> passed</td>
          </tr>
        </tfoot>
        <?php endif; ?>
      </table>
    </div>

    <div class="row mt-4">
      <div class="col-md-6">
        <div class="card app-card p-3">
          <h6 class="mb-2">Teacher’s Comment</h6>
          <div class="border rounded p-2 bg-light" style="min-height:100px;color:#4b5563">
            Keep up the good work. Continue to revise regularly and submit assignments on time.
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="card app-card p-3">
          <h6 class="mb-2">Head Teacher’s Comment</h6>
          <div class="border rounded p-2 bg-light" style="min-height:100px;color:#4b5563">
            Satisfactory progress. Encouraged to maintain discipline and focus in the next term.
          </div>
        </div>
      </div>
    </div>

    <div class="mt-4 border-top pt-3 d-flex justify-content-between">
      <div>
        <small class="text-muted">Class Teacher: ____________________ &nbsp;|&nbsp; Date: <?= date('d M Y') ?></small>
      </div>
      <div>
        <small class="text-muted">Head Teacher: ____________________ &nbsp;|&nbsp; Next term: <?= date('d M Y', strtotime('+1 month')) ?></small>
      </div>
    </div>
  </div>
</div>
