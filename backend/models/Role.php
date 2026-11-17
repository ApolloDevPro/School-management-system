<?php
declare(strict_types=1);

final class Role extends Model
{
    protected static string $table = 'roles';

    public static function allActive(): array
    {
        return static::query('SELECT * FROM roles WHERE is_active = 1 ORDER BY name');
    }

    public static function findBySlug(string $slug, ?int $schoolId = null): ?array
    {
        $sql = 'SELECT * FROM roles WHERE slug = ?';
        $params = [$slug];
        if ($schoolId !== null) {
            $sql .= ' AND school_id = ?';
            $params[] = $schoolId;
        }
        $sql .= ' ORDER BY id LIMIT 1';
        return static::one($sql, $params);
    }

    public static function permissions(): array
    {
        return static::query(
            'SELECT * FROM permissions WHERE is_active = 1 ORDER BY sort_order, name'
        );
    }

    public static function permissionsFor(int $roleId): array
    {
        $rows = static::query(
            'SELECT p.module_code, rp.can_view, rp.can_add, rp.can_edit, rp.can_approve, rp.can_print, rp.can_delete
               FROM role_permissions rp
               JOIN permissions p ON p.id = rp.permission_id
              WHERE rp.role_id = ? AND p.is_active = 1',
            [$roleId]
        );

        $map = [];
        foreach ($rows as $row) {
            $map[$row['module_code']] = [
                'view'    => (bool)$row['can_view'],
                'add'     => (bool)$row['can_add'],
                'edit'    => (bool)$row['can_edit'],
                'approve' => (bool)$row['can_approve'],
                'print'   => (bool)$row['can_print'],
                'delete'  => (bool)$row['can_delete'],
            ];
        }
        return $map;
    }

    public static function permissionMap(int $roleId): array
    {
        $rows = static::query(
            'SELECT permission_id, can_view, can_add, can_edit, can_approve, can_print, can_delete
               FROM role_permissions WHERE role_id = ?',
            [$roleId]
        );

        $map = [];
        foreach ($rows as $row) {
            $map[(int)$row['permission_id']] = [
                'can_view'    => (int)$row['can_view'],
                'can_add'     => (int)$row['can_add'],
                'can_edit'    => (int)$row['can_edit'],
                'can_approve' => (int)$row['can_approve'],
                'can_print'   => (int)$row['can_print'],
                'can_delete'  => (int)$row['can_delete'],
            ];
        }
        return $map;
    }

    public static function replacePermissions(int $roleId, array $rows): void
    {
        $connection = static::db();
        $connection->beginTransaction();
        try {
            $connection->prepare('DELETE FROM role_permissions WHERE role_id = ?')->execute([$roleId]);
            $statement = $connection->prepare(
                'INSERT INTO role_permissions (role_id, permission_id, can_view, can_add, can_edit, can_approve, can_print, can_delete)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            foreach ($rows as $permissionId => $flags) {
                $statement->execute([
                    $roleId,
                    $permissionId,
                    $flags['can_view'] ? 1 : 0,
                    $flags['can_add'] ? 1 : 0,
                    $flags['can_edit'] ? 1 : 0,
                    $flags['can_approve'] ? 1 : 0,
                    $flags['can_print'] ? 1 : 0,
                    $flags['can_delete'] ? 1 : 0,
                ]);
            }
            $connection->commit();
        } catch (Throwable $error) {
            $connection->rollBack();
            throw $error;
        }
    }

    public static function grantAllPermissions(int $roleId): void
    {
        $rows = [];
        foreach (static::permissions() as $permission) {
            $rows[(int)$permission['id']] = [
                'can_view' => 1, 'can_add' => 1, 'can_edit' => 1,
                'can_approve' => 1, 'can_print' => 1, 'can_delete' => 1,
            ];
        }
        static::replacePermissions($roleId, $rows);
    }
}
