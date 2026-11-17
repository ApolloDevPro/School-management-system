<div class="page-head d-flex justify-content-between align-items-start">
  <div>
    <h1 class="h4 mb-0">Database Setup</h1>
    <p class="text-muted small mb-0">Install all required SQL views for reports and analytics.</p>
  </div>
  <a class="btn btn-outline-secondary" href="<?= url('security') ?>"><i class="bi bi-arrow-left me-1"></i> Back to security</a>
</div>

<div class="row g-3">
  <div class="col-lg-8">
    <div class="card app-card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span>Install database views</span>
      </div>
      <div class="card-body">
        <p class="small text-muted mb-3">These views power the Reports &amp; analytics dashboard across all modules (students, finance, attendance, exams, HR, operations, audit).</p>

        <form method="post" action="<?= url('security/db-setup') ?>">
          <?= csrf_field() ?>
          <div class="d-grid gap-2">
            <button class="btn btn-primary" type="submit"><i class="bi bi-database-check me-1"></i> Install all views</button>
          </div>
        </form>

        <?php if ($errors): ?>
        <div class="alert alert-danger mt-3">
          <strong><?= count($errors) ?> view(s) failed to install:</strong>
          <ul class="mb-0 mt-2 small">
            <?php foreach ($errors as $name => $msg): ?>
            <li><?= e($name) ?>: <?= e($msg) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
        <?php endif; ?>
        <?php if ($created): ?>
        <div class="alert alert-success mt-3">
          <strong><?= count($created) ?> database view(s) installed successfully.</strong>
          <ul class="mb-0 mt-2 small text-muted">
            <?php foreach ($created as $name): ?>
            <li><?= e($name) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
        <?php endif; ?>
        <?php if (!$created && !$errors): ?>
        <div class="text-muted text-center p-4">Click "Install all views" to create the required SQL views.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card app-card h-100">
      <div class="card-header">About views</div>
      <div class="card-body small">
        <ul class="mb-0 ps-3">
          <li>Views are <code>CREATE OR REPLACE VIEW</code> — safe to re-run.</li>
          <li>They aggregate data from all module tables without modifying anything.</li>
          <li>Required for: KPI dashboard, debtors, fee collection, exam performance, leave balances, payroll, library stock, dorm occupancy, transport load, low stock, contract/licence alerts, audit log.</li>
        </ul>
        <p class="text-muted mt-3 small">If any view fails, check that the underlying module tables exist (install all module SQL files first).</p>
      </div>
    </div>
  </div>
</div>
