<?php
/**
 * Shared dashboard header card.
 *
 * Views set $headTitle, $headIcon, $headSubtitle (pre-escaped HTML) and,
 * optionally, $headBadges before requiring this file. The live clock sits in
 * the top school header, shared by every page.
 */
$headTitle = $headTitle ?? 'Dashboard';
$headIcon = $headIcon ?? 'bi-grid-1x2';
$headSubtitle = $headSubtitle ?? '';
$headBadges = $headBadges ?? '';

// Every dashboard shows the active year and term. Leadership passes them in;
// other dashboards read them here so the header stays identical everywhere.
if ($headBadges === '') {
    $headActiveYear = $activeYear ?? null;
    $headActiveTerm = $activeTerm ?? null;
    if ($headActiveYear === null) {
        try {
            $headActiveYear = AcademicYear::active(school_id());
        } catch (Throwable $e) {
            $headActiveYear = null;
        }
    }
    if ($headActiveTerm === null) {
        try {
            $headActiveTerm = Term::activeForSchool(school_id());
        } catch (Throwable $e) {
            $headActiveTerm = null;
        }
    }
    $headBadges = '<span class="badge text-bg-' . ($headActiveYear ? 'success' : 'warning') . '">'
        . ($headActiveYear ? 'Year active' : 'No active year') . '</span>'
        . ' <span class="badge text-bg-' . ($headActiveTerm ? 'success' : 'warning') . '">'
        . ($headActiveTerm ? 'Term active' : 'No active term') . '</span>';
}
?>
<header class="dashboard-head">
  <div class="dashboard-head-main">
    <div class="dashboard-head-icon" aria-hidden="true"><i class="bi <?= e($headIcon) ?>"></i></div>
    <div class="dashboard-head-text">
      <h1 class="dashboard-title"><?= e($headTitle) ?></h1>
<?php if ($headSubtitle !== ''): ?>
      <p class="dashboard-subtitle mb-0"><?= $headSubtitle ?></p>
<?php endif; ?>
    </div>
  </div>
  <div class="dashboard-head-side">
<?php if ($headBadges !== ''): ?>
    <div class="dashboard-status"><?= $headBadges ?></div>
<?php endif; ?>
  </div>
</header>
