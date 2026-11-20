<?php
$subjectTabUrl = url('students/subjects', array_filter([
    'tab'        => 'student',
    'class_id'   => $classId ?: null,
    'stream_id'  => $streamId ?: null,
    'student_id' => $student ? (int)$student['id'] : null,
], static fn($value) => $value !== null && $value !== ''));

$bulkTabUrl = url('students/subjects', array_filter([
    'tab'       => 'bulk',
    'class_id'  => $classId ?: null,
    'stream_id' => $streamId ?: null,
], static fn($value) => $value !== null && $value !== ''));

$classStreams = array_values(array_filter(
    $streams,
    static fn(array $stream): bool => $classId > 0 && (int)$stream['class_id'] === $classId
));

$selectedClass = null;
foreach ($classes as $class) {
    if ((int)$class['id'] === $classId) {
        $selectedClass = $class;
        break;
    }
}

$selectedStream = null;
foreach ($classStreams as $stream) {
    if ((int)$stream['id'] === $streamId) {
        $selectedStream = $stream;
        break;
    }
}

$classLabel = ($selectedClass['name'] ?? 'class') . ($selectedStream ? ' — ' . $selectedStream['stream_name'] : '');

$activeRegistrations = 0;
foreach ($registeredMap as $registeredRow) {
    if ($registeredRow['status'] === 'registered') {
        $activeRegistrations++;
    }
}

$rosterRegistered = 0;
foreach ($roster as $rosterRow) {
    if (($counts[(int)$rosterRow['id']] ?? 0) > 0) {
        $rosterRegistered++;
    }
}
?>
<div class="page-head">
  <div>
    <h1 class="h4 mb-0">Subject registration</h1>
    <p class="text-muted small mb-0">
      Register students for the subjects offered in their class
<?php if ($year): ?>
      &middot; <strong><?= e($year['name']) ?></strong>
      &middot; <?= $term ? e($term['name']) : 'No active term' ?>
<?php endif; ?>
    </p>
  </div>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary" href="<?= url('students') ?>">Back to students</a>
  </div>
</div>

<?php if (!$year): ?>
  <div class="alert alert-warning">No active academic year. Activate one in Administration before registering subjects.</div>
<?php else: ?>

<ul class="nav nav-pills app-pills mb-3">
  <li class="nav-item">
    <a class="nav-link <?= $tab === 'student' ? 'active' : '' ?>" href="<?= $subjectTabUrl ?>">Student registration</a>
  </li>
  <li class="nav-item">
    <a class="nav-link <?= $tab === 'bulk' ? 'active' : '' ?>" href="<?= $bulkTabUrl ?>">Whole class</a>
  </li>
</ul>

<?php if ($tab === 'student'): ?>

<div class="card app-card mb-3">
  <div class="card-body">
    <form method="get" action="<?= url('students/subjects') ?>" class="row g-3 align-items-end">
      <input type="hidden" name="tab" value="student">
      <div class="col-sm-4 col-lg-3">
        <label class="form-label" for="sel_class">Class</label>
        <select class="form-select" id="sel_class" name="class_id" data-submit-on-change>
          <option value="0">— Choose a class —</option>
<?php foreach ($classes as $class): ?>
          <option value="<?= (int)$class['id'] ?>" <?= (int)$class['id'] === $classId ? 'selected' : '' ?>><?= e($class['name']) ?></option>
<?php endforeach; ?>
        </select>
      </div>
      <div class="col-sm-4 col-lg-3">
        <label class="form-label" for="sel_stream">Stream</label>
        <select class="form-select" id="sel_stream" name="stream_id" data-submit-on-change <?= $classId ? '' : 'disabled' ?>>
          <option value="0">All streams</option>
<?php foreach ($classStreams as $stream): ?>
          <option value="<?= (int)$stream['id'] ?>" <?= (int)$stream['id'] === $streamId ? 'selected' : '' ?>><?= e($stream['stream_name']) ?></option>
<?php endforeach; ?>
        </select>
      </div>
      <div class="col-sm-4 col-lg-4">
        <label class="form-label" for="sel_student">Student</label>
        <select class="form-select" id="sel_student" name="student_id" data-submit-on-change <?= $roster ? '' : 'disabled' ?>>
          <option value="0">— Choose a student —</option>
<?php foreach ($roster as $row): ?>
          <option value="<?= (int)$row['id'] ?>" <?= $student && (int)$student['id'] === (int)$row['id'] ? 'selected' : '' ?>>
            <?= e($row['admission_number'] . ' — ' . $row['last_name'] . ' ' . $row['first_name']) ?>
          </option>
<?php endforeach; ?>
        </select>
      </div>
      <div class="col-lg-2 d-flex gap-2">
<?php if ($classId || $streamId): ?>
        <a class="btn btn-outline-secondary w-100" href="<?= url('students/subjects') ?>">Clear</a>
<?php endif; ?>
      </div>
    </form>
  </div>
</div>

<?php if (!$classId): ?>
  <div class="alert alert-secondary mb-0">Choose a class above to list its students and manage their subjects.</div>

<?php elseif (!$student): ?>
  <div class="card app-card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
      <span>Students in <?= e($classLabel) ?></span>
      <span class="small text-muted"><?= count($roster) ?> active student(s) &middot; <?= $rosterRegistered ?> registered this year</span>
    </div>
    <div class="card-body p-0">
<?php if (!$roster): ?>
      <div class="p-3"><p class="text-muted small mb-0">No active students are in this class<?= $streamId ? ' and stream' : '' ?> yet.</p></div>
<?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" data-table>
          <thead>
            <tr>
              <th>Student</th>
              <th>Admission no.</th>
              <th>Stream</th>
              <th class="text-end">Registered</th>
              <th class="text-end">Offered</th>
              <th class="text-end">Action</th>
            </tr>
          </thead>
          <tbody>
<?php foreach ($roster as $row): ?>
<?php
  $rowStream = (int)($row['current_stream_id'] ?? 0);
  $offeredCount = $offeredCounts[$rowStream] ?? $offeredCounts[0] ?? 0;
  $registeredCount = $counts[(int)$row['id']] ?? 0;
?>
            <tr>
              <td><?= e($row['last_name'] . ' ' . $row['first_name']) ?></td>
              <td class="text-muted small"><?= e($row['admission_number']) ?></td>
              <td class="small"><?= e($row['stream_name'] ?? '—') ?></td>
              <td class="text-end">
                <?php if ($registeredCount > 0): ?>
                  <span class="badge text-bg-success"><?= $registeredCount ?></span>
                <?php else: ?>
                  <span class="badge text-bg-light">0</span>
                <?php endif; ?>
              </td>
              <td class="text-end small text-muted"><?= $offeredCount ?></td>
              <td class="text-end">
                <a class="btn btn-sm btn-outline-primary" href="<?= url('students/subjects', array_filter([
                    'class_id'   => $classId,
                    'stream_id'  => $streamId ?: null,
                    'student_id' => (int)$row['id'],
                ], static fn($value) => $value !== null)) ?>">Manage subjects</a>
              </td>
            </tr>
<?php endforeach; ?>
          </tbody>
        </table>
      </div>
<?php endif; ?>
    </div>
  </div>

<?php else: ?>
  <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
    <span class="badge text-bg-light"><?= e($student['admission_number']) ?></span>
    <span class="fw-semibold"><?= e($student['last_name'] . ' ' . $student['first_name']) ?></span>
    <span class="text-muted small"><?= e($student['class_name'] ?? 'No class') ?><?= $student['stream_name'] ? ' — ' . e($student['stream_name']) : '' ?></span>
    <span class="badge text-bg-primary"><?= $activeRegistrations ?> registered</span>
  </div>

<?php if (!$student['current_class_id']): ?>
  <div class="alert alert-warning">Allocate this student to a class first — subjects are registered per class.</div>
<?php else: ?>
<div class="row g-3">
  <div class="<?= $isALevel ? 'col-lg-7' : 'col-12' ?>">
    <div class="card app-card h-100">
      <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span>Subjects offered in <?= e($student['class_name']) ?></span>
        <span class="small text-muted">Compulsory subjects are always saved</span>
      </div>
      <div class="card-body">
<?php if (!$allocated): ?>
        <p class="text-muted small mb-0">No subjects are allocated to this class yet. Ask an administrator to set up subject allocations in Academics.</p>
<?php else: ?>
        <form method="post" action="<?= url('students/subjects/save') ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="student_id" value="<?= (int)$student['id'] ?>">
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-3 subject-reg-table">
              <thead>
                <tr>
                  <th style="width:40px"></th>
                  <th>Code</th>
                  <th>Subject</th>
                  <th>Category</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
<?php foreach ($allocated as $subject): ?>
<?php
  $subjectId = (int)$subject['id'];
  $reg = $registeredMap[$subjectId] ?? null;
  $isCompulsory = (int)$subject['is_compulsory'] === 1;
  $isChecked = $isCompulsory || ($reg && $reg['status'] === 'registered');
?>
                <tr>
                  <td>
                    <input class="form-check-input" type="checkbox" name="subjects[]" value="<?= $subjectId ?>"
                      <?= $isChecked ? 'checked' : '' ?>
                      <?= $isCompulsory || !$canEdit ? 'disabled' : '' ?>>
                  </td>
                  <td class="small text-muted"><?= e($subject['code']) ?></td>
                  <td>
                    <?= e($subject['name']) ?>
                    <?php if ($isCompulsory): ?>
                      <span class="badge text-bg-secondary ms-1">Compulsory</span>
                    <?php endif; ?>
                  </td>
                  <td class="small"><?= e(ucfirst((string)$subject['category'])) ?></td>
                  <td class="small">
                    <?php if ($reg && $reg['status'] === 'registered'): ?>
                      <span class="badge text-bg-success">Registered</span>
                    <?php elseif ($reg && $reg['status'] === 'dropped'): ?>
                      <span class="badge text-bg-warning">Dropped</span>
                    <?php else: ?>
                      <span class="text-muted">—</span>
                    <?php endif; ?>
                  </td>
                </tr>
<?php endforeach; ?>
              </tbody>
            </table>
          </div>
<?php if ($canEdit): ?>
          <button class="btn btn-primary" type="submit">Save subject registration</button>
<?php else: ?>
          <p class="text-muted small mb-0">You have view-only access to subject registration.</p>
<?php endif; ?>
        </form>
<?php endif; ?>
      </div>
    </div>
  </div>

<?php if ($isALevel): ?>
  <div class="col-lg-5">
    <div class="card app-card h-100">
      <div class="card-header">A-Level combination</div>
      <div class="card-body">
<?php if ($combination): ?>
        <p class="mb-2">Current: <span class="badge text-bg-primary"><?= e($combination['code']) ?></span> <?= e($combination['name']) ?></p>
        <ul class="small mb-3">
<?php foreach (StudentSubject::combinationSubjects((int)$combination['combination_id']) as $sub): ?>
          <li><?= e($sub['name']) ?> <span class="text-muted">(<?= e($sub['subject_role']) ?>)</span></li>
<?php endforeach; ?>
        </ul>
<?php else: ?>
        <p class="text-muted small">No combination assigned. Assigning a combination registers its subjects automatically.</p>
<?php endif; ?>
<?php if ($canEdit && $combinations): ?>
        <form method="post" action="<?= url('students/subjects/combination') ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="student_id" value="<?= (int)$student['id'] ?>">
          <div class="mb-2">
            <label class="form-label small" for="sel_combination">Combination</label>
            <select class="form-select form-select-sm" id="sel_combination" name="combination_id" required>
              <option value="">Choose combination…</option>
<?php foreach ($combinations as $combo): ?>
              <option value="<?= (int)$combo['id'] ?>" <?= $combination && (int)$combination['combination_id'] === (int)$combo['id'] ? 'selected' : '' ?>><?= e($combo['code'] . ' — ' . $combo['name']) ?></option>
<?php endforeach; ?>
            </select>
          </div>
          <div class="mb-2">
            <label class="form-label small" for="inp_combo_notes">Notes</label>
            <input class="form-control form-control-sm" id="inp_combo_notes" name="notes" maxlength="255" placeholder="Optional">
          </div>
          <button class="btn btn-sm btn-primary" type="submit">Assign combination</button>
        </form>
<?php elseif (!$combinations): ?>
        <p class="text-muted small mb-0">No A-Level combinations have been set up for this school.</p>
<?php endif; ?>
      </div>
    </div>
  </div>
<?php endif; ?>
</div>
<?php endif; ?>

<?php endif; ?>

<?php else: // Whole class tab ?>

<div class="card app-card mb-3">
  <div class="card-body">
    <form method="get" action="<?= url('students/subjects') ?>" class="row g-3 align-items-end">
      <input type="hidden" name="tab" value="bulk">
      <div class="col-sm-5 col-lg-4">
        <label class="form-label" for="bulk_class">Class</label>
        <select class="form-select" id="bulk_class" name="class_id" data-submit-on-change>
          <option value="0">— Choose a class —</option>
<?php foreach ($classes as $class): ?>
          <option value="<?= (int)$class['id'] ?>" <?= (int)$class['id'] === $classId ? 'selected' : '' ?>><?= e($class['name']) ?></option>
<?php endforeach; ?>
        </select>
      </div>
      <div class="col-sm-5 col-lg-4">
        <label class="form-label" for="bulk_stream">Stream</label>
        <select class="form-select" id="bulk_stream" name="stream_id" data-submit-on-change <?= $classId ? '' : 'disabled' ?>>
          <option value="0">All streams</option>
<?php foreach ($classStreams as $stream): ?>
          <option value="<?= (int)$stream['id'] ?>" <?= (int)$stream['id'] === $streamId ? 'selected' : '' ?>><?= e($stream['stream_name']) ?></option>
<?php endforeach; ?>
        </select>
      </div>
      <div class="col-lg-4 d-flex gap-2">
<?php if ($classId || $streamId): ?>
        <a class="btn btn-outline-secondary" href="<?= url('students/subjects', ['tab' => 'bulk']) ?>">Clear</a>
<?php endif; ?>
      </div>
    </form>
  </div>
</div>

<?php if (!$classId): ?>
  <div class="alert alert-secondary mb-0">Choose a class to register subjects for all of its students at once.</div>
<?php elseif (!$bulkSubjects): ?>
  <div class="alert alert-warning mb-0">No subjects are allocated to this class<?= $streamId ? ' and stream' : '' ?> yet. Set up subject allocations in Academics first.</div>
<?php elseif (!$bulkStudents): ?>
  <div class="alert alert-warning mb-0">No active students are in this class<?= $streamId ? ' and stream' : '' ?>.</div>
<?php else: ?>
<form method="post" action="<?= url('students/subjects/bulk') ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="class_id" value="<?= $classId ?>">
  <input type="hidden" name="stream_id" value="<?= $streamId ?>">
  <div class="row g-3">
    <div class="col-lg-7">
      <div class="card app-card h-100">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
          <span>Subjects for <?= e($classLabel) ?></span>
          <span class="small text-muted">Compulsory subjects are always included</span>
        </div>
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0 subject-reg-table">
              <thead>
                <tr>
                  <th style="width:40px"></th>
                  <th>Code</th>
                  <th>Subject</th>
                  <th>Category</th>
                </tr>
              </thead>
              <tbody>
<?php foreach ($bulkSubjects as $subject): ?>
<?php $isCompulsory = (int)$subject['is_compulsory'] === 1; ?>
                <tr>
                  <td>
                    <input class="form-check-input" type="checkbox" name="subjects[]" value="<?= (int)$subject['id'] ?>"
                      <?= $isCompulsory || !$canEdit ? 'checked disabled' : '' ?>>
                  </td>
                  <td class="small text-muted"><?= e($subject['code']) ?></td>
                  <td>
                    <?= e($subject['name']) ?>
                    <?php if ($isCompulsory): ?>
                      <span class="badge text-bg-secondary ms-1">Compulsory</span>
                    <?php endif; ?>
                  </td>
                  <td class="small"><?= e(ucfirst((string)$subject['category'])) ?></td>
                </tr>
<?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
    <div class="col-lg-5">
      <div class="card app-card h-100">
        <div class="card-header">Apply to students</div>
        <div class="card-body">
          <p class="mb-3">
            <span class="badge text-bg-primary"><?= count($bulkStudents) ?></span>
            active student(s) will receive the ticked subjects.
          </p>
          <fieldset class="mb-3">
            <legend class="form-label small text-muted mb-2">Existing registrations</legend>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="mode" id="mode_merge" value="merge" <?= $canEdit ? 'checked' : 'disabled' ?>>
              <label class="form-check-label" for="mode_merge">Keep existing and add these subjects</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="mode" id="mode_replace" value="replace" <?= $canEdit ? '' : 'disabled' ?>>
              <label class="form-check-label" for="mode_replace">Replace — drop subjects that are not ticked</label>
            </div>
          </fieldset>
<?php if ($canEdit): ?>
          <button class="btn btn-primary w-100" type="submit">Register for <?= count($bulkStudents) ?> student(s)</button>
<?php else: ?>
          <p class="text-muted small mb-0">You have view-only access to subject registration.</p>
<?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</form>
<?php endif; ?>

<?php endif; ?>
<?php endif; ?>
