<?php
declare(strict_types=1);

final class UserController extends Controller
{
    public function index(): void
    {
        $this->render('users/index', [
            'pageTitle' => 'Users',
            'activeNav' => 'users',
            'users'     => User::allWithRole(),
            'roles'     => Role::allActive(),
            'canAdd'    => Auth::can('administration', 'add'),
            'canEdit'   => Auth::can('administration', 'edit'),
            'canDelete' => Auth::can('administration', 'delete'),
        ]);
    }

    public function store(): void
    {
        [$data, $errors] = $this->validate($_POST);

        $password = (string)($_POST['password'] ?? '');
        if (strlen($password) < 8) {
            $errors['password'] = 'Set a temporary password of at least 8 characters.';
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
        if (!$existing) {
            $this->respond(false, 'That user no longer exists.', 'users');
        }

        [$data, $errors] = $this->validate($_POST, $id);

        $password = (string)($_POST['password'] ?? '');
        if ($password !== '' && strlen($password) < 8) {
            $errors['password'] = 'Passwords must be at least 8 characters.';
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
        ];

        audit('administration', 'update', 'users', $id, 'Updated user ' . $existing['username'], $old, $fields);

        $this->respond(true, 'User ' . $fields['username'] . ' updated.', 'users');
    }

    public function toggle(): void
    {
        $id   = (int)($_POST['id'] ?? 0);
        $user = User::find($id);
        if (!$user) {
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
        if (!$user) {
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
        $schoolId = $input['school_id'] ?? school_id();
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
        if ($roleId <= 0) {
            $errors['role_id'] = 'Choose a role.';
        } elseif (!Role::find($roleId)) {
            $errors['role_id'] = 'The selected role does not exist.';
        }
        $data['role_id'] = $roleId > 0 ? $roleId : null;

        return [$data, $errors];
    }
}
