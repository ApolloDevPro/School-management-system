<?php /* Daily attendance: capture for learners and staff, with summaries. */ ?>
<div class="page-head"><div><h1 class="h4 mb-0">Daily attendance</h1><p class="text-muted small mb-0">Record and update daily learner and staff attendance</p></div></div>

<form method="get" action="<?= url('attendance') ?>" class="card app-card mb-3">
  <div class="card-body row g-2 align-items-end">
    <div class="col-sm-4"><label class="form-label">Attendance date</label><input class="form-control" type="date" name="date" value="<?= e($date) ?>" required></div>
    <div class="col-sm-5"><label class="form-label">Class</label><select class="form-select" name="class_id"><option value="0">Choose class…</option><?php foreach ($classes as $class): ?><option value="<?= (int)$class['id'] ?>" <?= $classId === (int)$class['id'] ? 'selected' : '' ?>><?= e($class['name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-sm-3"><button class="btn btn-outline-primary">Load rosters</button></div>
  </div>
</form>

<div class="row g-3">
  <div class="col-xl-7">
    <div class="card app-card">
      <div class="card-header">Student attendance · <?= e($date) ?></div>
<?php if ($canAdd && $classId): ?>
      <form method="post" action="<?= url('attendance/students/record') ?>">
        <?= csrf_field() ?><input type="hidden" name="class_id" value="<?= $classId ?>"><input type="hidden" name="attendance_date" value="<?= e($date) ?>">
        <div class="table-responsive"><table class="table table-sm align-middle mb-0">
          <thead><tr><th>Learner</th><th>Status</th><th>Late min.</th><th>Reason</th></tr></thead><tbody>
<?php if (!$students): ?><tr><td colspan="4" class="text-center text-muted py-4">No active learners in this class.</td></tr><?php endif; ?>
<?php foreach ($students as $student): $status = $student['attendance_status'] ?? 'present'; ?>
          <tr><td><?= e($student['last_name'] . ' ' . $student['first_name']) ?><div class="small text-muted"><?= e($student['admission_number']) ?></div></td>
            <td><select class="form-select form-select-sm" name="student_status[<?= (int)$student['id'] ?>]"><?php foreach (['present','absent','late','excused','sick','suspended'] as $option): ?><option value="<?= $option ?>" <?= $status === $option ? 'selected' : '' ?>><?= e(ucfirst($option)) ?></option><?php endforeach; ?></select></td>
            <td><input class="form-control form-control-sm" type="number" min="0" max="1440" name="minutes_late[<?= (int)$student['id'] ?>]" value="<?= (int)($student['minutes_late'] ?? 0) ?>"></td>
            <td><input class="form-control form-control-sm" maxlength="255" name="reason[<?= (int)$student['id'] ?>]" value="<?= e($student['reason'] ?? '') ?>"></td></tr>
<?php endforeach; ?>
          </tbody>
        </table></div>
        <div class="card-body border-top"><button class="btn btn-primary" <?= !$students ? 'disabled' : '' ?>>Save student attendance</button></div>
      </form>
<?php else: ?><div class="card-body text-muted"><?= $classId ? 'You do not have permission to record attendance.' : 'Select a class to load its roster.' ?></div><?php endif; ?>
    </div>
  </div>
  <div class="col-xl-5">
    <div class="card app-card">
      <div class="card-header">Staff attendance · <?= e($date) ?></div>
<?php if ($canAdd): ?>
      <form method="post" action="<?= url('attendance/staff/record') ?>">
        <?= csrf_field() ?><input type="hidden" name="attendance_date" value="<?= e($date) ?>">
        <div class="table-responsive"><table class="table table-sm align-middle mb-0">
          <thead><tr><th>Staff member</th><th>Status</th><th>Time in</th></tr></thead><tbody>
<?php if (!$staff): ?><tr><td colspan="3" class="text-center text-muted py-4">No active staff records.</td></tr><?php endif; ?>
<?php foreach ($staff as $member): $status = $member['attendance_status'] ?? 'present'; ?>
          <tr><td><?= e($member['last_name'] . ' ' . $member['first_name']) ?><div class="small text-muted"><?= e($member['staff_number']) ?></div></td>
            <td><select class="form-select form-select-sm" name="staff_status[<?= (int)$member['id'] ?>]"><?php foreach (['present','absent','late','on_leave','duty_off','sick'] as $option): ?><option value="<?= $option ?>" <?= $status === $option ? 'selected' : '' ?>><?= e(str_replace('_', ' ', ucfirst($option))) ?></option><?php endforeach; ?></select></td>
            <td><input class="form-control form-control-sm" type="time" name="time_in[<?= (int)$member['id'] ?>]" value="<?= e(substr((string)($member['time_in'] ?? ''), 0, 5)) ?>"></td>
            <input type="hidden" name="time_out[<?= (int)$member['id'] ?>]" value="<?= e(substr((string)($member['time_out'] ?? ''), 0, 5)) ?>">
            <input type="hidden" name="minutes_late[<?= (int)$member['id'] ?>]" value="<?= (int)($member['minutes_late'] ?? 0) ?>"></tr>
<?php endforeach; ?>
          </tbody>
        </table></div>
        <div class="card-body border-top"><button class="btn btn-primary" <?= !$staff ? 'disabled' : '' ?>>Save staff attendance</button></div>
      </form>
<?php else: ?><div class="card-body text-muted">You do not have permission to record staff attendance.</div><?php endif; ?>
    </div>
  </div>
</div>
