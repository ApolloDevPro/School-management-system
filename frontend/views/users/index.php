<?php /* User accounts: create, edit, link to staff/guardian/student records and reset passwords. */ ?>
<div class="page-head">
  <div>
    <h1 class="h4 mb-0">Users</h1>
    <p class="text-muted small mb-0">Accounts that can sign in to the system</p>
  </div>
<?php if ($canAdd): ?>
  <button class="btn btn-primary" type="button" data-new-user>Add user</button>
<?php endif; ?>
</div>

<div class="card app-card">
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0" data-table>
        <thead>
          <tr>
            <th>Full name</th>
            <th>Username</th>
            <th>Email</th>
            <th>Role</th>
            <th>Type</th>
            <th>Linked record</th>
            <th>Status</th>
            <th>Last login</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
<?php if (!$users): ?>
          <tr><td colspan="9" class="text-center text-muted py-4">No users found.</td></tr>
<?php endif; ?>
<?php foreach ($users as $user): ?>
          <tr data-user='<?= e(json_encode([
              'id'         => (int)$user['id'],
              'username'   => $user['username'],
              'full_name'  => $user['full_name'],
              'email'      => $user['email'],
              'phone'      => $user['phone'],
              'user_type'  => $user['user_type'],
              'role_id'    => $user['role_id'] !== null ? (int)$user['role_id'] : '',
              'staff_id'   => $user['staff_id'] !== null ? (int)$user['staff_id'] : '',
              'guardian_id'=> $user['guardian_id'] !== null ? (int)$user['guardian_id'] : '',
              'student_id' => $user['student_id'] !== null ? (int)$user['student_id'] : '',
              'is_active'  => (int)$user['is_active'],
          ])) ?>'>
            <td>
              <div class="fw-semibold"><?= e($user['full_name']) ?></div>
<?php if ((int)$user['must_change_password']): ?>
              <div class="small text-warning">Password must be changed at next sign-in</div>
<?php endif; ?>
            </td>
            <td><?= e($user['username']) ?></td>
            <td><?= e($user['email'] ?: '-') ?></td>
            <td><?= e($user['role_name'] ?: 'No role') ?></td>
            <td><span class="badge text-bg-light"><?= e($user['user_type']) ?></span></td>
            <td class="small">
<?php if (!empty($user['staff_id'])): ?>
              <?= e($user['staff_name'] ?: 'Staff #' . (int)$user['staff_id']) ?>
<?php elseif (!empty($user['guardian_id'])): ?>
              <?= e($user['guardian_name'] ?: 'Guardian #' . (int)$user['guardian_id']) ?>
<?php elseif (!empty($user['student_id'])): ?>
              <?= e($user['student_name'] ?: 'Student #' . (int)$user['student_id']) ?>
<?php else: ?>
              <span class="text-muted">Not linked</span>
<?php endif; ?>
            </td>
            <td>
<?php if ((int)$user['is_active']): ?>
              <span class="badge text-bg-success">Active</span>
<?php else: ?>
              <span class="badge text-bg-secondary">Inactive</span>
<?php endif; ?>
<?php if ($user['locked_until'] !== null && strtotime($user['locked_until']) > time()): ?>
              <span class="badge text-bg-danger">Locked</span>
<?php endif; ?>
            </td>
            <td class="small text-muted"><?= $user['last_login_at'] ? e(date('d M Y, H:i', strtotime($user['last_login_at']))) : 'Never' ?></td>
            <td class="text-end text-nowrap">
<?php if ($canEdit): ?>
              <button class="btn btn-sm btn-outline-primary" type="button" data-edit>Edit</button>
              <form method="post" action="<?= url('users/reset') ?>" class="d-inline" data-confirm="Generate a new password for <?= e($user['username']) ?>? The current one will stop working.">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$user['id'] ?>">
                <button class="btn btn-sm btn-outline-secondary" type="submit">New password</button>
              </form>
              <?php if ($user['id'] !== Auth::user()['id']): ?>
              <form method="post" action="<?= url('users/toggle') ?>" class="d-inline" data-confirm="<?= (int)$user['is_active'] ? 'Deactivate' : 'Reactivate' ?> <?= e($user['username']) ?>?">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$user['id'] ?>">
                <button class="btn btn-sm btn-outline-warning" type="submit"><?= (int)$user['is_active'] ? 'Deactivate' : 'Activate' ?></button>
              </form>
              <?php endif; ?>
<?php endif; ?>
            </td>
          </tr>
<?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="modal fade" id="userModal" tabindex="-1" aria-hidden="true" aria-labelledby="userModalTitle">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <form id="userForm" method="post" action="<?= url('users/store') ?>" data-ajax>
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="">
        <div class="modal-header">
          <h5 class="modal-title" id="userModalTitle">Add user</h5>
          <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label" for="f_username">Username</label>
              <input class="form-control" id="f_username" name="username" maxlength="60" autocomplete="off" required>
              <div class="invalid-feedback" data-error-for="username"></div>
              <div class="form-text">Letters, numbers, dot, dash or underscore.</div>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="f_full_name">Full name</label>
              <input class="form-control" id="f_full_name" name="full_name" maxlength="140" autocomplete="off" required>
              <div class="invalid-feedback" data-error-for="full_name"></div>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="f_email">Email</label>
              <input class="form-control" id="f_email" name="email" type="email" maxlength="120" autocomplete="off">
              <div class="invalid-feedback" data-error-for="email"></div>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="f_phone">Phone</label>
              <input class="form-control" id="f_phone" name="phone" maxlength="40" autocomplete="off">
              <div class="invalid-feedback" data-error-for="phone"></div>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="f_user_type">Account type</label>
              <select class="form-select" id="f_user_type" name="user_type">
                <option value="staff">Staff</option>
                <option value="parent">Parent</option>
                <option value="student">Student</option>
                <option value="system">System</option>
              </select>
              <div class="invalid-feedback" data-error-for="user_type"></div>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="f_role_id">Role</label>
              <select class="form-select" id="f_role_id" name="role_id" required>
                <option value="">Choose a role...</option>
<?php foreach ($roles as $role): ?>
                <option value="<?= (int)$role['id'] ?>"><?= e($role['name']) ?></option>
<?php endforeach; ?>
              </select>
              <div class="invalid-feedback" data-error-for="role_id"></div>
            </div>
            <div class="col-12 d-none" data-link-group="staff">
              <label class="form-label" for="f_staff_id">Staff record</label>
              <select class="form-select" id="f_staff_id" name="staff_id" disabled>
                <option value="">Not linked</option>
<?php foreach ($staffList as $staff): ?>
                <option value="<?= (int)$staff['id'] ?>">
                  <?= e(trim(($staff['staff_number'] ?? '') . ' · ' . ($staff['first_name'] ?? '') . ' ' . ($staff['middle_name'] ?? '') . ' ' . ($staff['last_name'] ?? ''))) ?><?= !empty($staff['position']) ? ' — ' . e($staff['position']) : '' ?>
                </option>
<?php endforeach; ?>
              </select>
              <div class="invalid-feedback" data-error-for="staff_id"></div>
              <div class="form-text">Required for teachers so their classes and allocations appear on the teacher dashboard.</div>
            </div>
            <div class="col-12 d-none" data-link-group="parent">
              <label class="form-label" for="f_guardian_id">Parent / guardian record</label>
              <select class="form-select" id="f_guardian_id" name="guardian_id" disabled>
                <option value="">Choose a guardian...</option>
<?php foreach ($guardianList as $guardian): ?>
                <option value="<?= (int)$guardian['id'] ?>">
                  <?= e(trim(($guardian['first_name'] ?? '') . ' ' . ($guardian['last_name'] ?? ''))) ?><?= !empty($guardian['phone']) ? ' — ' . e($guardian['phone']) : '' ?>
                </option>
<?php endforeach; ?>
              </select>
              <div class="invalid-feedback" data-error-for="guardian_id"></div>
              <div class="form-text">The portal shows the children linked to this guardian.</div>
            </div>
            <div class="col-12 d-none" data-link-group="student">
              <label class="form-label" for="f_student_id">Student record</label>
              <select class="form-select" id="f_student_id" name="student_id" disabled>
                <option value="">Choose a student...</option>
<?php foreach ($studentList as $student): ?>
                <option value="<?= (int)$student['id'] ?>">
                  <?= e(trim(($student['admission_number'] ?? '') . ' · ' . ($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? ''))) ?><?= !empty($student['class_name']) ? ' — ' . e($student['class_name']) : '' ?>
                </option>
<?php endforeach; ?>
              </select>
              <div class="invalid-feedback" data-error-for="student_id"></div>
              <div class="form-text">The portal shows this student's fees, results and timetable.</div>
            </div>
            <div class="col-12">
              <label class="form-label" for="f_password">Password</label>
              <input class="form-control" id="f_password" name="password" type="password" minlength="8" autocomplete="new-password" required>
              <div class="invalid-feedback" data-error-for="password"></div>
              <div class="form-text">Minimum 8 characters. The user should change it after signing in.</div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-primary" type="submit">Save user</button>
        </div>
      </form>
    </div>
  </div>
</div>
