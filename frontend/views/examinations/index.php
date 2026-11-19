<?php /* Examinations: exams, schedules, mark entry, processing, publishing and result sheets. */ ?>
<div class="page-head">
  <div>
    <h1 class="h4 mb-0">Examinations and assessment</h1>
    <p class="text-muted small mb-0">Plan papers, enter and verify marks, and process configured grades</p>
  </div>
</div>

<div class="card app-card mb-3">
  <div class="card-body">
    <form method="get" action="<?= url('examinations') ?>" class="row g-2 align-items-end">
      <div class="col-md-8">
        <label class="form-label" for="exam_pick">Exam</label>
        <select class="form-select" id="exam_pick" name="exam_id" data-submit-on-change>
<?php foreach ($exams as $row): ?>
          <option value="<?= (int)$row['id'] ?>" <?= $exam && (int)$exam['id'] === (int)$row['id'] ? 'selected' : '' ?>>
            <?= e($row['name'] . ' — ' . $row['year_name'] . ', ' . $row['term_name'] . ' (' . $row['status'] . ')') ?>
          </option>
<?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4">
<?php if ($exam): ?>
        <div class="small text-muted">Level: <strong><?= e($exam['level_scope']) ?></strong> &middot; Maximum: <strong><?= (int)$exam['max_mark'] ?></strong></div>
<?php endif; ?>
      </div>
    </form>
  </div>
</div>

<?php if ($exam): ?>
<div class="card app-card mb-3">
  <div class="card-body d-flex gap-2">
    <a class="btn btn-outline-primary" href="<?= url('examinations/timetable', ['exam_id' => (int)$exam['id']]) ?>" target="_blank"><i class="bi bi-calendar2-week"></i> Timetable</a>
    <a class="btn btn-outline-secondary" href="<?= url('examinations/result-slip', ['exam_id' => (int)$exam['id']]) ?>" target="_blank"><i class="bi bi-file-earmark-text"></i> Result Slip</a>
    <span class="text-muted small">Select a student below to print their individual result slip.</span>
  </div>
</div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-xl-5">
    <div class="card app-card mb-3">
      <div class="card-header"><?= $exam ? 'Exam setup' : 'Create exam' ?></div>
      <div class="card-body">
<?php if ($canEdit): ?>
        <form method="post" action="<?= url('examinations/save') ?>" class="row g-2">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int)($exam['id'] ?? 0) ?>">
          <div class="col-md-8">
            <label class="form-label">Name</label>
            <input class="form-control" name="name" maxlength="120" required value="<?= e($exam['name'] ?? '') ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">Type</label>
            <select class="form-select" name="exam_type" required>
<?php foreach (['midterm','end_of_term','mock','entrance','remedial','assignment','final'] as $type): ?>
              <option value="<?= e($type) ?>" <?= ($exam['exam_type'] ?? '') === $type ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $type))) ?></option>
<?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Academic year</label>
            <select class="form-select" name="academic_year_id" required>
<?php foreach ($years as $year): ?>
              <option value="<?= (int)$year['id'] ?>" <?= (int)($exam['academic_year_id'] ?? 0) === (int)$year['id'] ? 'selected' : '' ?>><?= e($year['name']) ?></option>
<?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Term</label>
            <select class="form-select" name="term_id" required>
<?php foreach ($terms as $term): ?>
              <option value="<?= (int)$term['id'] ?>" <?= (int)($exam['term_id'] ?? 0) === (int)$term['id'] ? 'selected' : '' ?>><?= e($term['name']) ?></option>
<?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-5">
            <label class="form-label">Level</label>
            <select class="form-select" name="level_scope">
<?php foreach (['Both','O-Level','A-Level'] as $scope): ?>
              <option value="<?= e($scope) ?>" <?= ($exam['level_scope'] ?? 'Both') === $scope ? 'selected' : '' ?>><?= e($scope) ?></option>
<?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">Maximum mark</label>
            <input class="form-control" type="number" min="1" max="32767" name="max_mark" required value="<?= (int)($exam['max_mark'] ?? 100) ?>">
          </div>
          <div class="col-md-2">
            <label class="form-label">Start</label>
            <input class="form-control" type="date" name="start_date" value="<?= e($exam['start_date'] ?? '') ?>">
          </div>
          <div class="col-md-2">
            <label class="form-label">End</label>
            <input class="form-control" type="date" name="end_date" value="<?= e($exam['end_date'] ?? '') ?>">
          </div>
          <div class="col-12">
            <label class="form-label">Notes</label>
            <input class="form-control" name="notes" maxlength="255" value="<?= e($exam['notes'] ?? '') ?>">
          </div>
          <div class="col-12">
            <button class="btn btn-primary" type="submit" <?= !$years || !$terms ? 'disabled' : '' ?>>Save exam</button>
          </div>
        </form>
<?php else: ?>
        <p class="text-muted small mb-0">You have view-only access.</p>
<?php endif; ?>
      </div>
    </div>

<?php if ($exam): ?>
    <div class="card app-card">
      <div class="card-header">Schedule an exam paper</div>
      <div class="card-body">
<?php if ($canEdit && in_array($exam['status'], ['planning', 'scheduled'], true)): ?>
        <form method="post" action="<?= url('examinations/schedule') ?>" class="row g-2">
          <?= csrf_field() ?>
          <input type="hidden" name="exam_id" value="<?= (int)$exam['id'] ?>">
          <div class="col-md-6">
            <label class="form-label">Class</label>
            <select class="form-select" name="class_id" required onchange="this.form.dataset.classChanged='1';window.location='<?= e(url('examinations', ['exam_id' => (int)$exam['id']])) ?>&class_id='+encodeURIComponent(this.value)">
              <option value="">Choose class…</option>
<?php foreach ($classes as $class): ?>
<?php if ($exam['level_scope'] === 'Both' || $exam['level_scope'] === $class['level']): ?>
              <option value="<?= (int)$class['id'] ?>" <?= $formClassId === (int)$class['id'] ? 'selected' : '' ?>><?= e($class['name'] . ' (' . $class['level'] . ')') ?></option>
<?php endif; ?>
<?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Subject</label>
            <select class="form-select" name="subject_id" required>
              <option value="">Choose allocated subject…</option>
<?php foreach ($subjects as $subject): ?>
              <option value="<?= (int)$subject['id'] ?>"><?= e($subject['code'] . ' — ' . $subject['name']) ?></option>
<?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Stream (optional)</label>
            <select class="form-select" name="stream_id">
              <option value="">All streams</option>
<?php foreach ($streams as $stream): ?>
              <option value="<?= (int)$stream['id'] ?>"><?= e($stream['name']) ?></option>
<?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Paper date</label>
            <input class="form-control" type="date" name="paper_date" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Start time</label>
            <input class="form-control" type="time" name="start_time" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">End time</label>
            <input class="form-control" type="time" name="end_time" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Maximum mark</label>
            <input class="form-control" type="number" min="1" max="32767" name="max_mark" value="<?= (int)$exam['max_mark'] ?>" required>
          </div>
          <div class="col-12">
            <label class="form-label">Syllabus scope / notes</label>
            <input class="form-control" name="syllabus_scope" maxlength="255">
          </div>
          <div class="col-12">
            <button class="btn btn-outline-primary" type="submit" <?= !$formClassId || !$subjects ? 'disabled' : '' ?>>Add scheduled paper</button>
          </div>
        </form>
<?php else: ?>
        <p class="text-muted small mb-0">This exam is not open for scheduling.</p>
<?php endif; ?>
      </div>
    </div>
<?php endif; ?>
  </div>

  <div class="col-xl-7">
<?php if ($exam): ?>
    <div class="card app-card mb-3">
      <div class="card-header">Scheduled papers</div>
      <div class="card-body p-0">
<?php if (!$schedules): ?>
        <p class="p-3 text-muted small mb-0">No papers scheduled yet.</p>
<?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm table-hover align-middle mb-0">
            <thead><tr><th>Date / time</th><th>Class</th><th>Subject</th><th>Marks</th><th></th></tr></thead>
            <tbody>
<?php foreach ($schedules as $paper): ?>
              <tr class="<?= $schedule && (int)$schedule['id'] === (int)$paper['id'] ? 'table-primary' : '' ?>">
                <td><?= e($paper['paper_date']) ?><br><span class="small text-muted"><?= e(substr($paper['start_time'], 0, 5) . '–' . substr($paper['end_time'], 0, 5)) ?></span></td>
                <td><?= e($paper['class_name'] . ($paper['stream_name'] ? ' / ' . $paper['stream_name'] : '')) ?></td>
                <td><?= e($paper['subject_code'] . ' — ' . $paper['subject_name']) ?></td>
                <td><?= (int)$paper['mark_count'] ?> / <?= (int)$paper['max_mark'] ?></td>
                <td><a class="btn btn-sm btn-outline-primary" href="<?= url('examinations', ['exam_id' => (int)$exam['id'], 'schedule_id' => (int)$paper['id']]) ?>">Marks</a></td>
              </tr>
<?php endforeach; ?>
            </tbody>
          </table>
        </div>
<?php endif; ?>
      </div>
    </div>

<?php if ($schedule): ?>
    <div class="card app-card mb-3">
      <div class="card-header">Marks — <?= e($schedule['class_name'] . ' / ' . $schedule['subject_name']) ?></div>
      <div class="card-body p-0">
<?php if (!$students): ?>
        <p class="p-3 text-muted small mb-0">No active students are currently allocated to this class and stream.</p>
<?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead><tr><th>Student</th><th style="min-width:320px">Mark entry</th><th>Status / workflow</th></tr></thead>
            <tbody>
<?php foreach ($students as $student): ?>
              <tr>
                <td>
                  <strong><?= e($student['last_name'] . ', ' . $student['first_name']) ?></strong>
                  <div class="small text-muted"><?= e($student['admission_number']) ?></div>
                </td>
                <td>
<?php if ($canEdit && in_array($exam['status'], ['scheduled','in_progress','marks_entry'], true)
    && (!$student['mark_status'] || in_array($student['mark_status'], ['draft','reopened'], true))): ?>
                  <form method="post" action="<?= url('examinations/marks/save') ?>" class="row g-1">
                    <?= csrf_field() ?>
                    <input type="hidden" name="schedule_id" value="<?= (int)$schedule['id'] ?>">
                    <input type="hidden" name="student_id" value="<?= (int)$student['id'] ?>">
                    <div class="col-4"><input class="form-control form-control-sm" type="number" step="0.01" min="0" max="<?= (int)$schedule['max_mark'] ?>" name="exam_mark" placeholder="Exam / <?= (int)$schedule['max_mark'] ?>" value="<?= e($student['exam_mark'] ?? '') ?>" required></div>
                    <div class="col-3"><input class="form-control form-control-sm" type="number" step="0.01" min="0" name="ca_mark" placeholder="CA (optional)" value="<?= e($student['ca_mark'] ?? '') ?>"></div>
                    <div class="col-3"><input class="form-control form-control-sm" type="number" step="0.01" min="0.01" name="max_ca_mark" placeholder="CA max" value="<?= e($student['max_ca_mark'] ?? '') ?>"></div>
                    <div class="col-2"><button class="btn btn-sm btn-primary w-100" type="submit">Save</button></div>
                    <div class="col-12"><input class="form-control form-control-sm" name="remarks" maxlength="255" placeholder="Optional remarks" value="<?= e($student['remarks'] ?? '') ?>"></div>
                  </form>
<?php else: ?>
                  <span><?= $student['total_mark'] !== null ? e($student['total_mark'] . ' / ' . ($student['max_exam_mark'] + (float)($student['max_ca_mark'] ?? 0)) . ' (' . $student['percentage'] . '%)') : '—' ?></span>
<?php endif; ?>
                </td>
                <td>
                  <span class="badge text-bg-light"><?= e($student['mark_status'] ?? 'not entered') ?></span>
<?php if ($canEdit && $student['mark_id']): ?>
                  <div class="d-flex flex-wrap gap-1 mt-1">
<?php
                    $nextAction = ['draft' => 'submit', 'reopened' => 'submit', 'submitted' => 'verify', 'verified' => 'lock', 'locked' => 'unlock'][$student['mark_status']] ?? null;
?>
<?php if ($nextAction): ?>
                    <form method="post" action="<?= url('examinations/marks/action') ?>" class="d-flex gap-1">
                      <?= csrf_field() ?>
                      <input type="hidden" name="mark_id" value="<?= (int)$student['mark_id'] ?>">
                      <input type="hidden" name="action" value="<?= e($nextAction) ?>">
<?php if ($nextAction === 'unlock'): ?>
                      <input class="form-control form-control-sm" name="reason" maxlength="255" placeholder="Reason required" required>
<?php endif; ?>
                      <button class="btn btn-sm btn-outline-secondary" type="submit"><?= e(ucfirst($nextAction)) ?></button>
                    </form>
<?php endif; ?>
                  </div>
<?php endif; ?>
                </td>
              </tr>
<?php endforeach; ?>
            </tbody>
          </table>
        </div>
<?php endif; ?>
      </div>
    </div>
<?php endif; ?>

    <div class="card app-card">
      <div class="card-header">Process configured grades</div>
      <div class="card-body">
        <p class="small text-muted">Processing requires every entered mark to be verified or locked. Grade outcomes come only from the selected active grading scale bands.</p>
<?php if ($canEdit): ?>
        <form method="post" action="<?= url('examinations/results/process') ?>" class="row g-2">
          <?= csrf_field() ?>
          <input type="hidden" name="exam_id" value="<?= (int)$exam['id'] ?>">
<?php foreach (['O-Level' => 'o_level_scale_id', 'A-Level' => 'a_level_scale_id'] as $level => $field): ?>
          <div class="col-md-6">
            <label class="form-label"><?= e($level) ?> grading scale</label>
            <select class="form-select" name="<?= e($field) ?>">
              <option value="0">Choose configured scale…</option>
<?php foreach ($scales as $scale): ?>
<?php if ($scale['level_scope'] === $level): ?>
              <option value="<?= (int)$scale['id'] ?>"><?= e($scale['name'] . ($scale['is_default'] ? ' (default)' : '')) ?></option>
<?php endif; ?>
<?php endforeach; ?>
            </select>
          </div>
<?php endforeach; ?>
          <div class="col-12"><button class="btn btn-outline-primary" type="submit" <?= !$schedules ? 'disabled' : '' ?>>Process results</button></div>
        </form>
<?php if ($exam['status'] === 'results_ready'): ?>
        <form method="post" action="<?= url('examinations/results/publish') ?>" class="mt-2">
          <?= csrf_field() ?>
          <input type="hidden" name="exam_id" value="<?= (int)$exam['id'] ?>">
          <button class="btn btn-success" type="submit">Publish results</button>
        </form>
<?php endif; ?>
<?php else: ?>
        <p class="text-muted small mb-0">You have view-only access.</p>
<?php endif; ?>
      </div>
    </div>
<?php else: ?>
    <div class="alert alert-secondary">Create an exam after setting up the academic year and terms.</div>
<?php endif; ?>
  </div>
</div>
