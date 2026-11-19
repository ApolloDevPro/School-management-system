<?php /* Runtime settings grouped by section; values live in the settings table. */
$groupIcons = [
    'general'          => 'bi-gear',
    'numbering'        => 'bi-hash',
    'fees'             => 'bi-currency-uganda',
    'examinations'     => 'bi-clipboard-check',
    'security'         => 'bi-shield-lock',
    'communication'    => 'bi-chat-text',
    'reporting'        => 'bi-file-earmark-bar',
    'backup'           => 'bi-cloud-download',
    'data_protection'  => 'bi-shield-check',
    'smtp'             => 'bi-envelope',
    'sms'              => 'bi-phone',
    'grading'          => 'bi-award',
    'portal'           => 'bi-globe',
    'uneb'             => 'bi-building',
    'academic'         => 'bi-book',
    'finance'          => 'bi-wallet',
    'curriculum'       => 'bi-tree',
];
$groupLabels = [
    'general'          => 'General',
    'numbering'        => 'Numbering',
    'fees'             => 'Fees & Payments',
    'examinations'     => 'Examinations',
    'security'         => 'Security',
    'communication'    => 'Communication',
    'reporting'        => 'Reporting',
    'backup'           => 'Backup',
    'data_protection'  => 'Data Protection',
    'smtp'             => 'Email (SMTP)',
    'sms'              => 'SMS Gateway',
    'grading'          => 'Grading',
    'portal'           => 'Parent & Student Portal',
    'uneb'             => 'UNEB Configuration',
    'academic'         => 'Academic Structure',
    'finance'          => 'Finance',
    'curriculum'       => 'Competency-Based Curriculum',
];
?>
<div class="page-head">
  <div class="d-flex justify-content-between align-items-start">
    <div>
      <h1 class="h4 mb-0">System Settings</h1>
      <p class="text-muted small mb-0">School-wide configuration &mdash; changes apply immediately</p>
    </div>
    <?php if ($canEdit): ?>
    <a href="<?= url('settings') ?>" class="btn btn-outline-secondary" onclick="location.reload()">
      <i class="bi bi-arrow-clockwise"></i> Refresh
    </a>
    <?php endif; ?>
  </div>
</div>

<form method="post" action="<?= url('settings/save') ?>" data-ajax>
  <?= csrf_field() ?>

  <?php if ($canEdit): ?>
  <div class="d-flex justify-content-end mb-3">
    <button class="btn btn-primary" type="submit"><i class="bi bi-save"></i> Save changes</button>
  </div>
  <?php endif; ?>

<?php foreach ($groups as $group => $rows): ?>
  <div class="card app-card settings-group mb-3">
    <div class="card-header d-flex align-items-center">
      <i class="bi <?= e($groupIcons[$group] ?? 'bi-sliders') ?> me-2 text-primary"></i>
      <span class="fw-semibold"><?= e($groupLabels[$group] ?? ucwords(str_replace('_', ' ', $group))) ?></span>
      <?php if ($canEdit): ?>
      <span class="badge text-bg-light border ms-auto"><?= count($rows) ?> settings</span>
      <?php endif; ?>
    </div>
    <div class="card-body">
      <div class="form-section"><i class="bi <?= e($groupIcons[$group] ?? 'bi-sliders') ?>"></i> <?= e($groupLabels[$group] ?? ucwords(str_replace('_', ' ', $group))) ?></div>
      <div class="row g-3">
<?php foreach ($rows as $row): ?>
<?php   $editable = $canEdit && (int)$row['is_editable']; ?>
        <div class="col-md-6 col-lg-4">
          <label class="form-label"><?= e(ucwords(str_replace(['.', '_'], ' ', $row['setting_key']))) ?></label>
<?php   if (!$editable): ?>
          <div class="form-control-plaintext py-0"><?= e($row['setting_value'] ?: '-') ?></div>
<?php   elseif ($row['value_type'] === 'bool'): ?>
          <div class="form-check form-switch">
            <input type="hidden" name="value[<?= (int)$row['id'] ?>]" value="0">
            <input class="form-check-input" type="checkbox" name="value[<?= (int)$row['id'] ?>]" value="1"
                   id="setting_<?= (int)$row['id'] ?>" <?= $row['setting_value'] === '1' ? 'checked' : '' ?>>
          </div>
<?php   elseif ($row['value_type'] === 'int' || $row['value_type'] === 'decimal'): ?>
          <input class="form-control" type="number"
                 step="<?= $row['value_type'] === 'decimal' ? '0.01' : '1' ?>"
                 name="value[<?= (int)$row['id'] ?>]" value="<?= e($row['setting_value']) ?>">
<?php   elseif ($row['value_type'] === 'date'): ?>
          <input class="form-control" type="date"
                 name="value[<?= (int)$row['id'] ?>]" value="<?= e($row['setting_value']) ?>">
<?php   else: ?>
          <input class="form-control" name="value[<?= (int)$row['id'] ?>]" value="<?= e($row['setting_value']) ?>">
<?php   endif; ?>
          <div class="form-text"><?= e($row['description'] ?: '') ?></div>
          <div class="invalid-feedback" data-error-for="value_<?= (int)$row['id'] ?>"></div>
        </div>
<?php endforeach; ?>
      </div>
    </div>
  </div>
<?php endforeach; ?>

<?php if ($canEdit): ?>
  <div class="d-flex justify-content-end mt-4">
    <button class="btn btn-primary" type="submit"><i class="bi bi-save"></i> Save all changes</button>
  </div>
<?php endif; ?>
</form>
