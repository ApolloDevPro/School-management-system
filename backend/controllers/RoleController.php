<?php
declare(strict_types=1);

final class RoleController extends Controller
{
    public function index(): void
    {
        $roles   = Role::allActive();
        $roleId  = (int)($_GET['id'] ?? 0);
        $current = null;

        foreach ($roles as $role) {
            if ((int)$role['id'] === $roleId) {
                $current = $role;
                break;
            }
        }
        if ($current === null && $roles) {
            $current = $roles[0];
        }

        $this->render('roles/index', [
            'pageTitle'   => 'Roles & permissions',
            'activeNav'   => 'roles',
            'roles'       => $roles,
            'current'     => $current,
            'permissions' => Role::permissions(),
            'matrix'      => $current ? Role::permissionMap((int)$current['id']) : [],
        ]);
    }

    public function save(): void
    {
        $roleId = (int)($_POST['role_id'] ?? 0);
        $role   = Role::find($roleId);
        if (!$role) {
            $this->respond(false, 'That role no longer exists.', 'roles');
        }

        $flags  = ['can_view', 'can_add', 'can_edit', 'can_approve', 'can_print', 'can_delete'];
        $posted = $_POST['perm'] ?? [];
        $rows   = [];

        foreach (Role::permissions() as $permission) {
            $permissionId = (int)$permission['id'];
            $row          = [];
            foreach ($flags as $flag) {
                $row[$flag] = !empty($posted[$permissionId][$flag]) ? 1 : 0;
            }
            $rows[$permissionId] = $row;
        }

        Role::replacePermissions($roleId, $rows);
        audit('administration', 'update', 'role_permissions', $roleId, 'Updated permissions for role ' . $role['name']);

        $this->respond(true, 'Permissions updated for ' . $role['name'] . '.', 'roles', ['id' => $roleId]);
    }
}
