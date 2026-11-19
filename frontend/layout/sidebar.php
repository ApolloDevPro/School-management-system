<?php
/**
 * Sidebar navigation.
 *
 * Each section is wrapped in Auth::can() checks, so people only see the modules
 * their role allows. Parent and student accounts get a single "My dashboard"
 * link straight to the portal instead of the staff menus.
 */
$nav = $activeNav ?? '';
$route = (string)($_GET['r'] ?? '');
$link = function (string $key, string $r, string $label, string $icon, bool $active = false) use ($nav): void {
    $isActive = $active || $nav === $key;
    ?>
    <li>
      <a class="sidebar-link <?= $isActive ? 'active' : '' ?>" href="<?= url($r) ?>"<?= $isActive ? ' aria-current="page"' : '' ?>>
        <span class="sidebar-link-inner"><i class="bi <?= e($icon) ?>" aria-hidden="true"></i><span><?= $label ?></span></span>
      </a>
    </li>
    <?php
};
$section = function (string $title): void { ?>
    <li class="sidebar-section"><?= e($title) ?></li>
<?php };
?>
<?php /* Brand block: school logo when uploaded, otherwise the school initial. */ ?>
<nav class="app-sidebar" id="appSidebar" aria-label="Primary">
  <?php $school = school_row(); $logo = $school['logo_path'] ?? null; ?>
  <div class="sidebar-brand">
    <?php if (!empty($logo)): ?>
      <img class="brand-mark brand-logo" src="<?= e(asset($logo)) ?>" alt="<?= e($school['name'] ?? 'School logo') ?>">
    <?php else: ?>
      <span class="brand-mark"><?= e(mb_strtoupper(mb_substr((string)($school['name'] ?? 'S'), 0, 1))) ?></span>
    <?php endif; ?>
    <div class="brand-text">
      <span class="brand-name"><?= e($school['name'] ?? APP_NAME) ?></span>
      <span class="brand-tag">School Management</span>
    </div>
  </div>

  <ul class="sidebar-nav">
    <?php $section('Main');
      $portalUser = in_array(Auth::user()['user_type'] ?? '', ['parent', 'student'], true);
      $link('dashboard', $portalUser ? 'portal' : 'dashboard', $portalUser ? 'My dashboard' : 'Dashboard', 'bi-speedometer2');
    ?>

<?php if (Auth::can('administration', 'view')): ?>
    <?php $section('Administration');
      if ((Auth::user()['role_slug'] ?? '') === 'administrator') { $link('users', 'users', 'Users', 'bi-people'); }
      if ((Auth::user()['role_slug'] ?? '') === 'administrator') { $link('roles', 'roles', 'Roles & permissions', 'bi-shield-lock'); }
      $link('setup_profile', 'setup/profile', 'School profile', 'bi-building');
      $link('setup_years', 'setup/years', 'Academic years', 'bi-calendar3');
      $link('setup_terms', 'setup/terms', 'Terms', 'bi-calendar-range');
      $link('setup_classes', 'setup/classes', 'Classes & streams', 'bi-diagram-3');
      $link('setup_departments', 'setup/departments', 'Departments', 'bi-diagram-2');
      $link('setup_houses', 'setup/houses', 'Houses', 'bi-houses');
      $link('setup_audit', 'setup/audit', 'Audit logs', 'bi-journal-text');
      if ((Auth::user()['role_slug'] ?? '') === 'administrator') { $link('settings', 'settings', 'Settings', 'bi-gear'); }
    ?>
<?php endif; ?>

<?php if (Auth::can('students', 'view')): ?>
    <?php $section('Students');
      $link('students', 'students', 'Students', 'bi-person-vcard');
      $link('admissions', 'admissions', 'Admissions', 'bi-person-plus');
      $link('guardians', 'guardians', 'Parents & guardians', 'bi-people-fill');
      $link('students_subjects', 'students/subjects', 'Subject registration', 'bi-journal-bookmark');
      $link('students_allocation', 'students/allocation', 'Class allocation', 'bi-layout-three-columns');
      $link('students_promotions', 'students/promotions', 'Promotion', 'bi-arrow-up-right-circle');
      $link('students_transfers', 'students/transfers', 'Transfers', 'bi-arrow-left-right');
      $link('students_discipline', 'students/discipline', 'Discipline', 'bi-exclamation-octagon');
      $link('students_documents', 'students/documents', 'Documents', 'bi-folder2-open');
      $link('students_alumni', 'students/alumni', 'Alumni', 'bi-mortarboard');
    ?>
<?php endif; ?>

<?php if (Auth::can('academics', 'view')): ?>
    <?php $section('Academics'); $link('academics', 'academics', 'Curriculum & subjects', 'bi-book'); ?>
    <li>
      <a class="sidebar-link <?= str_starts_with($route, 'report_cards') ? 'active' : '' ?>" href="<?= url('report_cards') ?>">
        <span class="sidebar-link-inner"><i class="bi bi-file-earmark-text"></i><span>Report cards</span></span>
      </a>
    </li>
<?php endif; ?>

<?php if (Auth::can('examinations', 'view')): ?>
    <?php $section('Examinations'); $link('examinations', 'examinations', 'Exams, marks & results', 'bi-clipboard2-check'); ?>
<?php endif; ?>

<?php if (Auth::can('uneb', 'view')): ?>
    <?php $section('UNEB'); ?>
    <li>
      <a class="sidebar-link <?= $nav === 'uneb' && $route !== 'uneb/ca-submission' ? 'active' : '' ?>" href="<?= url('uneb') ?>">
        <span class="sidebar-link-inner"><i class="bi bi-award"></i><span>Candidates &amp; results</span></span>
      </a>
    </li>
    <li>
      <a class="sidebar-link <?= $route === 'uneb/ca-submission' ? 'active' : '' ?>" href="<?= url('uneb/ca-submission') ?>">
        <span class="sidebar-link-inner"><i class="bi bi-file-earmark-check"></i><span>CA submission</span></span>
      </a>
    </li>
<?php endif; ?>

<?php if (Auth::can('finance', 'view') || Auth::can('online_payments', 'view')): ?>
    <?php $section('Finance');
      if (Auth::can('finance', 'view')) {
        $link('finance', 'finance', 'Fees & payments', 'bi-cash-stack');
        $link('finance_chart_of_accounts', 'finance/chart-of-accounts', 'Chart of accounts', 'bi-list-columns-reverse');
        $link('finance_budgets', 'finance/budgets', 'Budgets', 'bi-pie-chart');
        $link('finance_expenses', 'finance/expenses', 'Expenses', 'bi-receipt');
        $link('finance_petty_cash', 'finance/petty-cash', 'Petty cash', 'bi-wallet2');
      }
      if (Auth::can('online_payments', 'view')) { $link('finance_online', 'finance_online', 'Online payments', 'bi-credit-card'); }
    ?>
<?php endif; ?>

<?php if (Auth::can('hr', 'view')): ?>
    <?php $section('Human resources'); $link('hr', 'hr', 'Staff, leave & payroll', 'bi-person-badge'); ?>
<?php endif; ?>

<?php if (Auth::can('attendance', 'view')): ?>
    <?php $section('Attendance'); $link('attendance', 'attendance', 'Daily attendance', 'bi-calendar-check'); ?>
<?php endif; ?>

<?php if (Auth::can('health', 'view')): ?>
    <?php $section('Health & welfare'); $link('health', 'health', 'Medical & sickbay', 'bi-heart-pulse'); ?>
<?php endif; ?>

<?php if (Auth::can('library', 'view') || Auth::can('boarding', 'view') || Auth::can('transport', 'view') || Auth::can('inventory', 'view') || Auth::can('canteen', 'view') || Auth::can('gate', 'view')): ?>
    <?php $section('Operations');
      if (Auth::can('library', 'view')) { $link('library', 'library', 'Library', 'bi-journals'); }
      if (Auth::can('boarding', 'view')) { $link('boarding', 'boarding', 'Boarding', 'bi-house-door'); }
      if (Auth::can('transport', 'view')) { $link('transport', 'transport', 'Transport', 'bi-bus-front'); }
      if (Auth::can('inventory', 'view')) { $link('inventory', 'inventory', 'Inventory', 'bi-box-seam'); }
      if (Auth::can('canteen', 'view')) { $link('canteen', 'canteen', 'Canteen & shop', 'bi-cup-hot'); }
      if (Auth::can('gate', 'view')) { $link('gate', 'gate', 'Gate & visitors', 'bi-door-open'); }
    ?>
<?php endif; ?>

<?php if (Auth::can('communication', 'view')): ?>
    <?php $section('Communication'); $link('communication', 'communication', 'Announcements', 'bi-megaphone'); ?>
<?php endif; ?>

<?php if (Auth::can('cocurricular', 'view')): ?>
    <?php $section('Co-curricular'); $link('cocurricular', 'cocurricular', 'Clubs & activities', 'bi-trophy'); ?>
<?php endif; ?>

<?php if (Auth::can('compliance', 'view')): ?>
    <?php $section('Compliance'); $link('compliance', 'compliance', 'Govt. compliance', 'bi-patch-check'); ?>
<?php endif; ?>

<?php if (Auth::can('portal', 'view') && empty($portalUser)): ?>
    <?php $section('My portal'); $link('portal', 'portal', 'Portal dashboard', 'bi-person-workspace'); ?>
<?php endif; ?>

<?php if (Auth::can('administration', 'view') || Auth::can('security', 'view')): ?>
    <?php $section('Reports & support');
      if (Auth::can('administration', 'view')) { $link('reports', 'reports', 'Reports & analytics', 'bi-bar-chart-line'); }
      if (Auth::can('security', 'view')) { $link('security', 'security', 'Support tickets', 'bi-life-preserver'); }
      if (Auth::can('security', 'view')) { $link('security_sessions', 'security/sessions', 'Active sessions', 'bi-person-check'); }
    ?>
<?php endif; ?>
  </ul>
</nav>
