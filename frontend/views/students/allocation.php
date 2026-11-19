<div class="page-head">
  <div>
    <h1 class="h4 mb-0">Class &amp; stream allocation</h1>
    <p class="text-muted small mb-0">Move students between classes and streams in bulk</p>
  </div>
  <a class="btn btn-outline-secondary" href="<?= url('students') ?>">Back to students</a>
</div>

<div class="card app-card">
  <div class="card-body">
    <form method="get" action="<?= url('students/allocation') ?>" class="row g-3 mb-4 pb-3 border-bottom">
      <div class="col-md-4">
        <label class="form-label" for="from_class">1. Students currently in</label>
        <select class="form-select" id="from_class" name="from_class_id">
          <option value="0">— Choose class —</option>
<?php foreach ($classes as $class): ?>
          <option value="<?= (int)$class['id'] ?>" <?= $fromClass === (int)$class['id'] ? 'selected' : '' ?>><?= e($class['name']) ?></option>
<?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label" for="to_class">2. Move to class</label>
        <select class="form-select" id="to_class" name="class_id">
          <option value="0">— Choose target class —</option>
<?php foreach ($classes as $class): ?>
          <option value="<?= (int)$class['id'] ?>" <?= $toClass === (int)$class['id'] ? 'selected' : '' ?>><?= e($class['name']) ?></option>
<?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4 d-flex align-items-end gap-2">
        <button class="btn btn-primary" type="submit">Load students</button>
        <a class="btn btn-outline-secondary" href="<?= url('students/allocation') ?>">Reset</a>
      </div>
    </form>

<?php if ($fromClass > 0 && $toClass > 0): ?>
    <form method="post" action="<?= url('students/allocation/save') ?>">
      <?= csrf_field() ?>
      <div class="form-section"><i class="bi bi-people"></i> Add all selected students</div>
      <input type="hidden" name="class_id" value="<?= $toClass ?>">
      <div class="row g-3 mb-3">
        <div class="col-md-4">
          <label class="form-label" for="to_stream">Target stream</label>
          <select class="form-select" id="to_stream" name="stream_id">
            <option value="0">— No stream —</option>
<?php foreach ($streams as $stream): ?>
            <option value="<?= (int)$stream['id'] ?>"><?= e($stream['name']) ?> (<?= (int)$stream['student_count'] ?>/<?= (int)$stream['capacity'] ?>)</option>
<?php endforeach; ?>
          </select>
        </div>
      </div>

<?php if (!$students): ?>
      <p class="text-muted">No active students in the selected class.</p>
<?php else: ?>
      <div class="d-flex justify-content-between align-items-center mb-2">
        <span class="text-muted small"><?= count($students) ?> student(s) found</span>
        <div class="d-flex gap-3 small">
          <label class="form-check-label"><input type="checkbox" id="checkAll"> Select all</label>
        </div>
      </div>
      <div class="table-responsive mb-3">
        <table class="table table-sm table-hover align-middle mb-0">
          <thead><tr><th style="width:40px"><input type="checkbox" id="checkAllHead"></th><th>Admission no.</th><th>Name</th></tr></thead>
          <tbody>
<?php foreach ($students as $student): ?>
            <tr>
              <td><input type="checkbox" name="student_ids[]" value="<?= (int)$student['id'] ?>" class="student-check"></td>
              <td><?= e($student['admission_number']) ?></td>
              <td><?= e($student['first_name'] . ' ' . $student['last_name']) ?></td>
            </tr>
<?php endforeach; ?>
          </tbody>
        </table>
      </div>
<?php if ($canEdit): ?>
      <button class="btn btn-primary" type="submit" data-confirm="Allocate the selected students to the target class?">Allocate selected students</button>
<?php else: ?>
      <div class="alert alert-info mb-0">You do not have permission to allocate students.</div>
<?php endif; ?>
<?php endif; ?>
    </form>
<?php elseif ($fromClass > 0): ?>
    <div class="alert alert-secondary">Now choose the target class above to continue.</div>
<?php else: ?>
    <p class="text-muted mb-0">Choose a class to load its students.</p>
<?php endif; ?>
  </div>
</div>

<script>
(function () {
  var all = document.getElementById('checkAll');
  var head = document.getElementById('checkAllHead');
  function toggle(checked) {
    document.querySelectorAll('.student-check').forEach(function (box) { box.checked = checked; });
  }
  if (all) { all.addEventListener('change', function () { toggle(all.checked); }); }
  if (head) { head.addEventListener('change', function () { toggle(head.checked); }); }
})();
</script>
