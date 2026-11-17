<div class="page-head">
  <div>
    <h1 class="h4 mb-0">Subject registration</h1>
    <p class="text-muted small mb-0">Register a student for the subjects taught in their class</p>
  </div>
  <a class="btn btn-outline-secondary" href="<?= url('students') ?>">Back to students</a>
</div>

<div class="card app-card mb-3">
  <div class="card-body">
    <form method="get" action="<?= url('students/subjects') ?>" class="row g-2 align-items-end">
      <div class="col-md-6">
        <label class="form-label" for="sel_student">Student</label>
        <select class="form-select" id="sel_student" name="student_id" data-submit-on-change>
          <option value="0">— Choose a student —</option>
<?php
          // grouped list of active students
          $allStudents = Student::listForSchool(school_id(), ['status' => 'active']);
?>
<?php foreach ($allStudents as $row): ?>
          <option value="<?= (int)$row['id'] ?>" <?= $student && (int)$student['id'] === (int)$row['id'] ? 'selected' : '' ?>>
            <?= e($row['admission_number'] . ' — ' . $row['last_name'] . ' ' . $row['first_name'] . ($row['class_name'] ? ' (' . $row['class_name'] . ')' : '')) ?>
          </option>
<?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-6">
<?php if ($student && $year): ?>
        <div class="small text-muted">Active year: <strong><?= e($year['name']) ?></strong> &middot; Class: <strong><?= e($student['class_name'] ?? 'Not allocated') ?></strong></div>
<?php endif; ?>
      </div>
    </form>
  </div>
</div>

<?php if ($student && $year): ?>
<?php if (!$student['current_class_id']): ?>
  <div class="alert alert-warning">Allocate this student to a class first — subjects are registered per class.</div>
<?php else: ?>
<div class="row g-3">
  <div class="col-lg-7">
    <div class="card app-card">
      <div class="card-header">Subjects offered in <?= e($student['class_name']) ?></div>
      <div class="card-body">
<?php if (!$allocated): ?>
        <p class="text-muted small mb-0">No subjects are allocated to this class yet. Ask the administrator to set up subject allocations in Academics.</p>
<?php else: ?>
        <form method="post" action="<?= url('students/subjects/save') ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="student_id" value="<?= (int)$student['id'] ?>">
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-3">
              <thead><tr><th style="width:40px"></th><th>Code</th><th>Subject</th><th>Category</th><th>Registered</th></tr></thead>
              <tbody>
<?php foreach ($allocated as $subject): ?>
<?php $subjectId = (int)$subject['id']; $reg = $registeredMap[$subjectId] ?? null; ?>
                <tr>
                  <td><input type="checkbox" name="subjects[]" value="<?= $subjectId ?>" <?= $reg && $reg['status'] !== 'dropped' ? 'checked' : '' ?> <?= $canEdit ? '' : 'disabled' ?>></td>
                  <td><?= e($subject['code']) ?></td>
                  <td><?= e($subject['name']) ?><?= (int)$subject['is_compulsory'] ? ' <span class="badge text-bg-light">Compulsory</span>' : '' ?></td>
                  <td class="small"><?= e($subject['category']) ?></td>
                  <td class="small"><?= $reg ? e($reg['status']) : '—' ?></td>
                </tr>
<?php endforeach; ?>
              </tbody>
            </table>
          </div>
<?php if ($canEdit): ?>
          <button class="btn btn-primary" type="submit">Save subject registration</button>
<?php endif; ?>
        </form>
<?php endif; ?>
      </div>
    </div>
  </div>

<?php if ($isALevel): ?>
  <div class="col-lg-5">
    <div class="card app-card">
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
        <p class="text-muted small">No combination assigned.</p>
<?php endif; ?>
<?php if ($canEdit): ?>
        <form method="post" action="<?= url('students/subjects/combination') ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="student_id" value="<?= (int)$student['id'] ?>">
          <div class="mb-2">
            <select class="form-select form-select-sm" name="combination_id" required>
              <option value="">Choose combination…</option>
<?php foreach ($combinations as $combo): ?>
              <option value="<?= (int)$combo['id'] ?>"><?= e($combo['code'] . ' — ' . $combo['name']) ?></option>
<?php endforeach; ?>
            </select>
          </div>
          <div class="mb-2">
            <input class="form-control form-control-sm" name="notes" maxlength="255" placeholder="Notes (optional)">
          </div>
          <button class="btn btn-sm btn-primary" type="submit">Assign combination</button>
        </form>
<?php endif; ?>
      </div>
    </div>
  </div>
<?php endif; ?>
<?php endif; ?>
<?php else: ?>
  <div class="alert alert-secondary">Choose a student above to manage their subjects.</div>
<?php endif; ?>
