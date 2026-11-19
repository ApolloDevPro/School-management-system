<?php
declare(strict_types=1);

/**
 * User accounts, role assignment, staff/guardian/student links and password resets.
 *
 * Backend controller: receives routed requests, enforces permissions and
 * renders a view or returns JSON through the base Controller helpers.
 */
final class UserController extends Controller
{
    public function index(): void
    {
        $schoolId = school_id();
        $this->render('users/index', [
            'pageTitle' => 'Users',
            'activeNav' => 'users',
            'users'     => User::allWithRole($schoolId),
            'roles'     => Role::allActive($schoolId),
            'staffList' => HrModule::staffForSchool($schoolId),
            'guardianList' => Guardian::listForSchool($schoolId),
            'studentList'  => Student::listForSchool($schoolId),
            'canAdd'    => Auth::can('administration', 'add'),
            'canEdit'   => Auth::can('administration', 'edit'),
            'canDelete' => Auth::can('administration', 'delete'),
        ]);
    }

    public function store(): void
    {
        [$data, $errors] = $this->validate($_POST);

        $password = (string)($_POST['password'] ?? '');
        $minLength = password_min_length();
        if (strlen($password) < $minLength) {
            $errors['password'] = 'Set a temporary password of at least ' . $minLength . ' characters.';
        }
        if ($errors) {
            $this->respond(false, 'Please correct the highlighted fields.', 'users', [], $errors);
        }

        $id = User::create([
            'school_id' => school_id(),
            ...$data,
            'created_by' => (int)Auth::user()['id'],
        ], $password);

        audit('administration', 'create', 'users', $id, 'Created user ' . $data['username'], null, $data);

        $this->respond(true, 'User ' . $data['username'] . ' created.', 'users');
    }

    public function update(): void
    {
        $id       = (int)($_POST['id'] ?? 0);
        $existing = User::find($id);
        if (!$existing || (int)$existing['school_id'] !== school_id()) {
            $this->respond(false, 'That user no longer exists.', 'users');
        }

        [$data, $errors] = $this->validate($_POST, $id);

        $password = (string)($_POST['password'] ?? '');
        $minLength = password_min_length();
        if ($password !== '' && strlen($password) < $minLength) {
            $errors['password'] = 'Passwords must be at least ' . $minLength . ' characters.';
        }
        if ($errors) {
            $this->respond(false, 'Please correct the highlighted fields.', 'users', [], $errors);
        }

        $fields = [
            'username'  => $data['username'],
            'full_name' => $data['full_name'],
            'email'     => $data['email'] ?: null,
            'phone'     => $data['phone'] ?: null,
            'user_type' => $data['user_type'],
            'role_id'   => $data['role_id'],
            'staff_id'  => $data['staff_id'],
            'guardian_id' => $data['guardian_id'],
            'student_id'  => $data['student_id'],
        ];

        User::updateRow($id, $fields);

        if ($password !== '') {
            User::setPassword($id, $password);
            User::execute('UPDATE users SET must_change_password = 1 WHERE id = ?', [$id]);
        }

        $old = [
            'username'  => $existing['username'],
            'full_name' => $existing['full_name'],
            'email'     => $existing['email'],
            'phone'     => $existing['phone'],
            'user_type' => $existing['user_type'],
            'role_id'   => $existing['role_id'],
            'staff_id'  => $existing['staff_id'],
            'guardian_id' => $existing['guardian_id'],
            'student_id'  => $existing['student_id'],
        ];

        audit('administration', 'update', 'users', $id, 'Updated user ' . $existing['username'], $old, $fields);

        $this->respond(true, 'User ' . $fields['username'] . ' updated.', 'users');
    }

    public function toggle(): void
    {
        $id   = (int)($_POST['id'] ?? 0);
        $user = User::find($id);
        if (!$user || (int)$user['school_id'] !== school_id()) {
            $this->respond(false, 'That user no longer exists.', 'users');
        }
        if ($id === (int)Auth::user()['id']) {
            $this->respond(false, 'You cannot deactivate your own account.', 'users');
        }

        $newStatus = (int)$user['is_active'] ? 0 : 1;
        User::updateRow($id, ['is_active' => $newStatus]);

        $verb = $newStatus ? 'Activated' : 'Deactivated';
        audit('administration', $newStatus ? 'activate' : 'deactivate', 'users', $id, $verb . ' ' . $user['username']);

        $this->respond(true, $verb . ' ' . $user['username'] . '.', 'users');
    }

    public function resetPassword(): void
    {
        $id   = (int)($_POST['id'] ?? 0);
        $user = User::find($id);
        if (!$user || (int)$user['school_id'] !== school_id()) {
            $this->respond(false, 'That user no longer exists.', 'users');
        }

        $plain = User::resetPassword($id, (int)Auth::user()['id']);
        audit('administration', 'reset_password', 'users', $id, 'Reset password for ' . $user['username']);

        $this->respond(
            true,
            'New password for ' . $user['username'] . ': ' . $plain . ' — share it securely; the user must change it after signing in.',
            'users'
        );
    }

    private function validate(array $input, ?int $ignoreId = null): array
    {
        $schoolId = school_id();
        $errors   = [];
        $data     = [];

        $username = trim((string)($input['username'] ?? ''));
        if (!preg_match('/^[A-Za-z0-9._-]{3,60}$/', $username)) {
            $errors['username'] = 'Use 3-60 characters: letters, numbers, dot, dash or underscore.';
        } elseif (User::usernameTaken($username, (int)$schoolId, $ignoreId)) {
            $errors['username'] = 'This username is already in use.';
        }
        $data['username'] = $username;

        $fullName = trim((string)($input['full_name'] ?? ''));
        if ($fullName === '' || mb_strlen($fullName) > 140) {
            $errors['full_name'] = 'Enter the full name (up to 140 characters).';
        }
        $data['full_name'] = $fullName;

        $email = trim((string)($input['email'] ?? ''));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Enter a valid email address.';
        } elseif ($email !== '' && User::emailTaken($email, (int)$schoolId, $ignoreId)) {
            $errors['email'] = 'This email is already used by another account.';
        }
        $data['email'] = $email;

        $phone = trim((string)($input['phone'] ?? ''));
        if ($phone !== '' && !preg_match('/^[0-9+()\s-]{6,40}$/', $phone)) {
            $errors['phone'] = 'Enter a valid phone number.';
        }
        $data['phone'] = $phone;

        $userType = (string)($input['user_type'] ?? 'staff');
        if (!in_array($userType, ['staff', 'parent', 'student', 'system'], true)) {
            $userType = 'staff';
        }
        $data['user_type'] = $userType;

        $roleId = (int)($input['role_id'] ?? 0);
        $role   = $roleId > 0 ? Role::find($roleId) : null;
        if ($roleId <= 0) {
            $errors['role_id'] = 'Choose a role.';
        } elseif (!$role || (int)$role['school_id'] !== $schoolId) {
            $errors['role_id'] = 'The selected role does not exist for this school.';
        }
        $data['role_id'] = $roleId > 0 ? $roleId : null;

        $roleSlug = (string)($role['slug'] ?? '');
        if (in_array($roleSlug, ['parent', 'student'], true) && $userType !== $roleSlug) {
            $errors['user_type'] = 'The Parent and Student roles require the matching account type.';
        }

        // Link the account to the person it represents. Staff accounts can be
        // linked to a staff record (required for teachers); parent and student
        // accounts are linked to a guardian or student record.
        $data['staff_id']    = null;
        $data['guardian_id'] = null;
        $data['student_id']  = null;

        $linkField = match ($userType) {
            'staff'   => 'staff_id',
            'parent'  => 'guardian_id',
            'student' => 'student_id',
            default   => null,
        };

        if ($linkField !== null) {
            $linkId = (int)($input[$linkField] ?? 0);
            $required = $userType === 'parent' || $userType === 'student' || $roleSlug === 'teacher';

            if ($linkId <= 0) {
                if ($required) {
                    $errors[$linkField] = $userType === 'staff'
                        ? 'Link this teacher account to a staff record.'
                        : 'Choose the ' . ($userType === 'parent' ? 'parent/guardian' : 'student') . ' record for this account.';
                }
            } else {
                $linked = match ($userType) {
                    'staff'   => HrModule::staff($linkId, $schoolId),
                    'parent'  => Guardian::find($linkId),
                    'student' => Student::find($linkId),
                };
                $ownedBySchool = $linked && (int)($linked['school_id'] ?? 0) === $schoolId;

                $takenSql = 'SELECT id FROM users WHERE school_id = ? AND ' . $linkField . ' = ?';
                $takenParams = [$schoolId, $linkId];
                if ($ignoreId !== null) {
                    $takenSql .= ' AND id <> ?';
                    $takenParams[] = $ignoreId;
                }

                if (!$ownedBySchool) {
                    $errors[$linkField] = 'The selected record does not belong to this school.';
                } elseif (User::query($takenSql . ' LIMIT 1', $takenParams)) {
                    $errors[$linkField] = 'That record is already linked to another user account.';
                } else {
                    $data[$linkField] = $linkId;
                }
            }
        }

        return [$data, $errors];
    }
}
