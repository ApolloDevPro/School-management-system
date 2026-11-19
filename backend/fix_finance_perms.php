<?php
declare(strict_types=1);

// Diagnostic tool: command line only. Never expose database metadata over HTTP.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This diagnostic script can only be run from the command line.\n");
}

require __DIR__ . '/config/config.php';
require __DIR__ . '/core/Database.php';

$db = Database::conn();

echo "<h2>Permission Diagnostic</h2>";

// 1. Check if permissions exist
echo "<h3>1. Finance Permissions in DB</h3>";
$stmt = $db->query("SELECT * FROM permissions WHERE module_code = 'finance'");
$perms = $stmt->fetchAll();
if ($perms) {
    echo "<table border='1' cellpadding='5'><tr><th>ID</th><th>Module</th><th>Code</th><th>Name</th></tr>";
    foreach ($perms as $p) {
        echo "<tr><td>{$p['id']}</td><td>{$p['module_code']}</td><td>{$p['code']}</td><td>{$p['name']}</td></tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color:red'>❌ No finance add/edit permissions found!</p>";
}

// 2. Check roles
echo "<h3>2. Roles</h3>";
$stmt = $db->query("SELECT id, name, slug, school_id FROM roles WHERE is_active = 1 ORDER BY name");
$roles = $stmt->fetchAll();
echo "<table border='1' cellpadding='5'><tr><th>ID</th><th>Name</th><th>Slug</th><th>School ID</th></tr>";
foreach ($roles as $r) {
    echo "<tr><td>{$r['id']}</td><td>{$r['name']}</td><td>{$r['slug']}</td><td>{$r['school_id']}</td></tr>";
}
echo "</table>";

// 3. Check role_permissions for finance
echo "<h3>3. Role Permissions for Finance</h3>";
$stmt = $db->prepare(
    "SELECT r.name AS role_name, p.code AS permission, rp.can_view, rp.can_add, rp.can_edit, rp.can_delete, r.school_id AS role_school
     FROM role_permissions rp
     JOIN permissions p ON rp.permission_id = p.id
     JOIN roles r ON rp.role_id = r.id
     WHERE p.module_code = 'finance'
     ORDER BY r.name, p.code"
);
$stmt->execute();
$rows = $stmt->fetchAll();
if ($rows) {
    echo "<table border='1' cellpadding='5'><tr><th>Role</th><th>Permission</th><th>View</th><th>Add</th><th>Edit</th><th>Delete</th><th>Role School</th></tr>";
    foreach ($rows as $row) {
        echo "<tr>";
        echo "<td>{$row['role_name']}</td>";
        echo "<td>{$row['permission']}</td>";
        echo "<td>{$row['can_view']}</td>";
        echo "<td>{$row['can_add']}</td>";
        echo "<td>{$row['can_edit']}</td>";
        echo "<td>{$row['can_delete']}</td>";
        echo "<td>{$row['role_school']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color:red'>❌ No finance role permissions found!</p>";
}

// 4. Check schools
echo "<h3>3. Schools</h3>";
$stmt = $db->query("SELECT * FROM schools");
$schools = $stmt->fetchAll();
echo "<table border='1' cellpadding='5'><tr><th>ID</th><th>Name</th></tr>";
foreach ($schools as $s) {
    echo "<tr><td>{$s['id']}</td><td>{$s['name']}</td></tr>";
}
echo "</table>";

echo "<h3>Next Steps</h3>";
echo "<ul>";
echo "<li>Make sure your logged-in user's role has school_id matching the current school</li>";
echo "<li>Log OUT and LOG BACK IN to reload permissions into session</li>";
echo "<li>If permissions table is empty for finance, run fix_finance_perms.php again</li>";
echo "</ul>";
