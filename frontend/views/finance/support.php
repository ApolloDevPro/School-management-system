<?php /* Finance support: sponsors, bursaries, discounts and fee waivers. */ ?>
<div class="page-head">
  <div>
    <h1 class="h4 mb-0">Bursaries, sponsors and waivers</h1>
    <p class="text-muted small mb-0">Register funding partners, award bursaries and apply discounts or waivers to fee invoices.</p>
  </div>
  <a class="btn btn-outline-secondary btn-sm" href="<?= url('finance') ?>"><i class="bi bi-arrow-left"></i> Back to finance</a>
</div>

<?php if (!$years): ?>
<div class="alert alert-warning">Create an academic year before recording bursaries or adjustments.</div>
<?php endif; ?>

<ul class="nav nav-pills app-pills mb-3" role="tablist">
  <li class="nav-item" role="presentation"><button class="nav-link <?= $tab === 'bursaries' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tab-bursaries" type="button" role="tab">Bursaries &amp; awards</button></li>
  <li class="nav-item" role="presentation"><button class="nav-link <?= $tab === 'discounts' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tab-discounts" type="button" role="tab">Discounts &amp; waivers</button></li>
  <li class="nav-item" role="presentation"><button class="nav-link <?= $tab === 'sponsors' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tab-sponsors" type="button" role="tab">Sponsors</button></li>
</ul>

<div class="tab-content">
  <div class="tab-pane fade <?= $tab === 'bursaries' ? 'show active' : '' ?>" id="tab-bursaries" role="tabpanel">
    <div class="row g-3">
<?php if ($canAdd): ?>
      <div class="col-xl-4">
        <div class="card app-card">
          <div class="card-header">New bursary scheme</div>
          <div class="card-body">
            <form method="post" action="<?= url('finance/support/bursaries') ?>">
              <?= csrf_field() ?>
              <div class="mb-2"><label class="form-label" for="b_name">Name</label><input class="form-control" id="b_name" name="name" maxlength="140" required></div>
              <div class="row g-2 mb-2">
                <div class="col-6"><label class="form-label" for="b_sponsor">Sponsor</label>
                  <select class="form-select" id="b_sponsor" name="sponsor_id"><option value="0">School funded</option>
<?php foreach ($sponsors as $sponsor): ?><option value="<?= (int)$sponsor['id'] ?>"><?= e($sponsor['name']) ?></option><?php endforeach; ?>
                  </select>
                </div>
                <div class="col-6"><label class="form-label" for="b_source">Funding</label>
                  <select class="form-select" id="b_source" name="funding_source">
                    <option value="donor">Donor</option><option value="government">Government</option><option value="pta">PTA</option><option value="school">School</option><option value="other">Other</option>
                  </select>
                </div>
              </div>
              <div class="row g-2 mb-2">
                <div class="col-6"><label class="form-label" for="b_coverage">Coverage</label>
                  <select class="form-select" id="b_coverage" name="coverage_type"><option value="partial">Partial</option><option value="full">Full</option></select>
                </div>
                <div class="col-6"><label class="form-label" for="b_budget">Annual budget</label><input class="form-control" id="b_budget" type="number" min="0" step="0.01" name="annual_budget"></div>
              </div>
              <div class="row g-2 mb-2">
                <div class="col-6"><label class="form-label" for="b_start">Starts</label><input class="form-control" id="b_start" type="date" name="start_date"></div>
                <div class="col-6"><label class="form-label" for="b_end">Ends</label><input class="form-control" id="b_end" type="date" name="end_date"></div>
              </div>
              <div class="mb-2"><label class="form-label" for="b_status">Status</label>
                <select class="form-select" id="b_status" name="status"><option value="open">Open</option><option value="suspended">Suspended</option><option value="closed">Closed</option></select>
              </div>
              <div class="mb-2"><label class="form-label" for="b_desc">Description</label><input class="form-control" id="b_desc" name="description" maxlength="255"></div>
              <button class="btn btn-primary btn-sm w-100">Save bursary</button>
            </form>
          </div>
        </div>
      </div>
<?php endif; ?>
      <div class="col-xl-8">
        <div class="card app-card mb-3">
          <div class="card-header">Bursary schemes <span class="badge text-bg-light"><?= count($bursaries) ?></span></div>
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead><tr><th>Name</th><th>Sponsor</th><th>Coverage</th><th>Status</th><th class="text-end">Budget</th><th class="text-end">Awards</th></tr></thead>
              <tbody>
<?php if (!$bursaries): ?><tr><td colspan="6" class="text-center text-muted py-4">No bursary schemes yet.</td></tr><?php endif; ?>
<?php foreach ($bursaries as $b): ?>
                <tr>
                  <td><?= e($b['name']) ?><?php if ($b['description']): ?><div class="small text-muted"><?= e($b['description']) ?></div><?php endif; ?></td>
                  <td><?= e($b['sponsor_name'] ?? 'School funded') ?><div class="small text-muted"><?= e(ucfirst(str_replace('_', ' ', $b['funding_source']))) ?></div></td>
                  <td><?= e(ucfirst($b['coverage_type'])) ?></td>
                  <td><span class="badge text-bg-<?= $b['status'] === 'open' ? 'success' : ($b['status'] === 'suspended' ? 'warning' : 'secondary') ?>"><?= e($b['status']) ?></span></td>
                  <td class="text-end"><?= $b['annual_budget'] !== null ? e(number_format((float)$b['annual_budget'], 0)) : '—' ?></td>
                  <td class="text-end"><?= (int)$b['award_count'] ?> (<?= e(number_format((float)$b['awarded_total'], 0)) ?>)</td>
                </tr>
<?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

<?php if ($canAdd): ?>
        <div class="card app-card mb-3">
          <div class="card-header">Award a bursary to a student</div>
          <div class="card-body">
            <form method="post" action="<?= url('finance/support/awards') ?>" class="row g-2 align-items-end">
              <?= csrf_field() ?>
              <div class="col-md-4"><label class="form-label" for="a_bursary">Bursary</label><select class="form-select" id="a_bursary" name="bursary_id" required><option value="">Choose…</option>
<?php foreach ($bursaries as $b): if ($b['status'] !== 'open') continue; ?><option value="<?= (int)$b['id'] ?>"><?= e($b['name']) ?></option><?php endforeach; ?>
              </select></div>
              <div class="col-md-4"><label class="form-label" for="a_student">Student</label><select class="form-select" id="a_student" name="student_id" required><option value="">Choose…</option>
<?php foreach ($students as $s): ?><option value="<?= (int)$s['id'] ?>"><?= e($s['admission_number'] . ' — ' . $s['last_name'] . ' ' . $s['first_name']) ?></option><?php endforeach; ?>
              </select></div>
              <div class="col-md-2"><label class="form-label" for="a_year">Year</label><select class="form-select" id="a_year" name="academic_year_id" required><option value="">Choose…</option>
<?php foreach ($years as $y): ?><option value="<?= (int)$y['id'] ?>"><?= e($y['name']) ?></option><?php endforeach; ?>
              </select></div>
              <div class="col-md-2"><label class="form-label" for="a_term">Term</label><select class="form-select" id="a_term" name="term_id"><option value="0">Whole year</option>
<?php foreach ($terms as $t): ?><option value="<?= (int)$t['id'] ?>"><?= e($t['year_name'] . ' • ' . $t['name']) ?></option><?php endforeach; ?>
              </select></div>
              <div class="col-md-2"><label class="form-label" for="a_type">Type</label><select class="form-select" id="a_type" name="award_type"><option value="amount">Amount</option><option value="percent">Percent</option></select></div>
              <div class="col-md-2"><label class="form-label" for="a_value">Value</label><input class="form-control" id="a_value" type="number" min="0.01" step="0.01" name="award_value" required></div>
              <div class="col-md-2"><label class="form-label" for="a_start">Starts</label><input class="form-control" id="a_start" type="date" name="start_date"></div>
              <div class="col-md-2"><label class="form-label" for="a_end">Ends</label><input class="form-control" id="a_end" type="date" name="end_date"></div>
              <div class="col-md-2"><label class="form-label" for="a_notes">Notes</label><input class="form-control" id="a_notes" name="notes" maxlength="255"></div>
              <div class="col-md-2"><button class="btn btn-primary w-100">Record award</button></div>
            </form>
            <div class="small text-muted mt-2">A percentage award is valued against the student's invoices for the chosen year and term. Applying the award credits the oldest open invoice.</div>
          </div>
        </div>
<?php endif; ?>

        <div class="card app-card">
          <div class="card-header">Bursary awards <span class="badge text-bg-light"><?= count($awards) ?></span></div>
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead><tr><th>Student</th><th>Bursary</th><th>Year / term</th><th class="text-end">Value</th><th class="text-end">Amount</th><th>Status</th><th class="text-end"></th></tr></thead>
              <tbody>
<?php if (!$awards): ?><tr><td colspan="7" class="text-center text-muted py-4">No bursary awards recorded.</td></tr><?php endif; ?>
<?php foreach ($awards as $a): ?>
                <tr>
                  <td><?= e($a['last_name'] . ' ' . $a['first_name']) ?><div class="small text-muted"><?= e($a['admission_number']) ?></div></td>
                  <td><?= e($a['bursary_name']) ?></td>
                  <td><?= e($a['year_name']) ?><?= $a['term_name'] ? ' • ' . e($a['term_name']) : ' • whole year' ?></td>
                  <td class="text-end"><?= e(number_format((float)$a['award_value'], 2)) ?><?= $a['award_type'] === 'percent' ? '%' : '' ?></td>
                  <td class="text-end"><?= e(number_format((float)$a['award_amount'], 0)) ?></td>
                  <td><span class="badge text-bg-<?= $a['status'] === 'active' ? 'success' : ($a['status'] === 'completed' ? 'primary' : 'secondary') ?>"><?= e($a['status']) ?></span></td>
                  <td class="text-end">
<?php if ($canApprove && $a['status'] === 'active'): ?>
                    <form method="post" action="<?= url('finance/support/awards/apply') ?>">
                      <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                      <button class="btn btn-sm btn-outline-primary" type="submit" onclick="return confirm('Apply this award to the student\'s open invoice?')">Apply</button>
                    </form>
<?php endif; ?>
                  </td>
                </tr>
<?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="tab-pane fade <?= $tab === 'discounts' ? 'show active' : '' ?>" id="tab-discounts" role="tabpanel">
    <div class="row g-3">
<?php if ($canAdd): ?>
      <div class="col-xl-4">
        <div class="card app-card">
          <div class="card-header">New discount or waiver</div>
          <div class="card-body">
            <form method="post" action="<?= url('finance/support/discounts') ?>">
              <?= csrf_field() ?>
              <div class="mb-2"><label class="form-label" for="d_student">Student</label><select class="form-select" id="d_student" name="student_id"><option value="0">Whole class</option>
<?php foreach ($students as $s): ?><option value="<?= (int)$s['id'] ?>"><?= e($s['admission_number'] . ' — ' . $s['last_name'] . ' ' . $s['first_name']) ?></option><?php endforeach; ?>
              </select></div>
              <div class="mb-2"><label class="form-label" for="d_class">Class (when no student)</label><select class="form-select" id="d_class" name="class_id"><option value="0">None</option>
<?php foreach ($classes as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['name'] . ' (' . $c['level'] . ')') ?></option><?php endforeach; ?>
              </select></div>
              <div class="row g-2 mb-2">
                <div class="col-6"><label class="form-label" for="d_year">Year</label><select class="form-select" id="d_year" name="academic_year_id" required><option value="">Choose…</option>
<?php foreach ($years as $y): ?><option value="<?= (int)$y['id'] ?>"><?= e($y['name']) ?></option><?php endforeach; ?>
                </select></div>
                <div class="col-6"><label class="form-label" for="d_term">Term</label><select class="form-select" id="d_term" name="term_id"><option value="0">Whole year</option>
<?php foreach ($terms as $t): ?><option value="<?= (int)$t['id'] ?>"><?= e($t['year_name'] . ' • ' . $t['name']) ?></option><?php endforeach; ?>
                </select></div>
              </div>
              <div class="row g-2 mb-2">
                <div class="col-6"><label class="form-label" for="d_type">Adjustment</label><select class="form-select" id="d_type" name="adjustment_type"><option value="discount">Discount</option><option value="waiver">Waiver</option><option value="bursary_offset">Bursary offset</option></select></div>
                <div class="col-6"><label class="form-label" for="d_reason">Reason</label>
                  <select class="form-select" id="d_reason" name="reason"><option value="sibling">Sibling</option><option value="staff_child">Staff child</option><option value="scholarship">Scholarship</option><option value="merit">Merit</option><option value="hardship">Hardship</option><option value="early_payment">Early payment</option><option value="other">Other</option></select>
                </div>
              </div>
              <div class="row g-2 mb-2">
                <div class="col-6"><label class="form-label" for="d_value_type">Value type</label><select class="form-select" id="d_value_type" name="value_type"><option value="amount">Amount</option><option value="percent">Percent</option></select></div>
                <div class="col-6"><label class="form-label" for="d_value">Value</label><input class="form-control" id="d_value" type="number" min="0.01" step="0.01" name="value" required></div>
              </div>
              <div class="row g-2 mb-2">
                <div class="col-6"><label class="form-label" for="d_max">Maximum amount</label><input class="form-control" id="d_max" type="number" min="0.01" step="0.01" name="max_amount"></div>
                <div class="col-6"><label class="form-label" for="d_expires">Expires</label><input class="form-control" id="d_expires" type="date" name="expires_on"></div>
              </div>
              <div class="mb-2"><label class="form-label" for="d_desc">Description</label><input class="form-control" id="d_desc" name="description" maxlength="255"></div>
              <button class="btn btn-primary btn-sm w-100">Record adjustment</button>
            </form>
          </div>
        </div>
      </div>
<?php endif; ?>
      <div class="col-xl-8">
        <div class="card app-card">
          <div class="card-header">Discounts and waivers <span class="badge text-bg-light"><?= count($discounts) ?></span></div>
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead><tr><th>Student / class</th><th>Type</th><th>Reason</th><th class="text-end">Value</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
              <tbody>
<?php if (!$discounts): ?><tr><td colspan="6" class="text-center text-muted py-4">No discounts or waivers recorded.</td></tr><?php endif; ?>
<?php foreach ($discounts as $d): ?>
                <tr>
                  <td><?= $d['student_id'] ? e($d['last_name'] . ' ' . $d['first_name']) . '<div class="small text-muted">' . e($d['admission_number']) . '</div>' : '<span class="text-muted">Class-wide</span><div class="small text-muted">' . e($d['class_name'] ?? '') . '</div>' ?></td>
                  <td><?= e(ucfirst(str_replace('_', ' ', $d['adjustment_type']))) ?><?php if ($d['description']): ?><div class="small text-muted"><?= e($d['description']) ?></div><?php endif; ?></td>
                  <td><?= e(ucfirst(str_replace('_', ' ', $d['reason']))) ?></td>
                  <td class="text-end"><?= e(number_format((float)$d['value'], 2)) ?><?= $d['value_type'] === 'percent' ? '%' : '' ?><?php if ($d['max_amount'] !== null): ?><div class="small text-muted">max <?= e(number_format((float)$d['max_amount'], 0)) ?></div><?php endif; ?></td>
                  <td><span class="badge text-bg-<?= $d['status'] === 'applied' ? 'primary' : ($d['status'] === 'approved' ? 'info' : ($d['status'] === 'pending' ? 'warning' : 'secondary')) ?>"><?= e($d['status']) ?></span><?php if ($d['invoice_number']): ?><div class="small text-muted"><?= e($d['invoice_number']) ?></div><?php endif; ?></td>
                  <td class="text-end">
<?php if ($canApprove && $d['status'] === 'pending'): ?>
                    <form method="post" action="<?= url('finance/support/discounts/approve') ?>" class="d-inline">
                      <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                      <button class="btn btn-sm btn-outline-secondary" type="submit">Approve</button>
                    </form>
<?php endif; ?>
<?php if ($canApprove && $d['student_id'] && in_array($d['status'], ['pending', 'approved'], true)): ?>
                    <form method="post" action="<?= url('finance/support/discounts/apply') ?>" class="d-inline">
                      <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                      <button class="btn btn-sm btn-outline-primary" type="submit" onclick="return confirm('Apply this adjustment to the student\'s open invoice?')">Apply</button>
                    </form>
<?php endif; ?>
                  </td>
                </tr>
<?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="tab-pane fade <?= $tab === 'sponsors' ? 'show active' : '' ?>" id="tab-sponsors" role="tabpanel">
    <div class="row g-3">
<?php if ($canAdd): ?>
      <div class="col-xl-4">
        <div class="card app-card">
          <div class="card-header">New sponsor</div>
          <div class="card-body">
            <form method="post" action="<?= url('finance/support/sponsors') ?>">
              <?= csrf_field() ?>
              <div class="mb-2"><label class="form-label" for="sp_name">Name</label><input class="form-control" id="sp_name" name="name" maxlength="160" required></div>
              <div class="mb-2"><label class="form-label" for="sp_type">Type</label><select class="form-select" id="sp_type" name="sponsor_type"><option value="company">Company</option><option value="ngo">NGO</option><option value="church">Church</option><option value="government">Government</option><option value="individual">Individual</option><option value="other">Other</option></select></div>
              <div class="mb-2"><label class="form-label" for="sp_contact">Contact person</label><input class="form-control" id="sp_contact" name="contact_person" maxlength="140"></div>
              <div class="row g-2 mb-2">
                <div class="col-6"><label class="form-label" for="sp_phone">Phone</label><input class="form-control" id="sp_phone" name="phone" maxlength="40"></div>
                <div class="col-6"><label class="form-label" for="sp_email">Email</label><input class="form-control" id="sp_email" type="email" name="email" maxlength="120"></div>
              </div>
              <div class="mb-2"><label class="form-label" for="sp_address">Address</label><input class="form-control" id="sp_address" name="address" maxlength="255"></div>
              <div class="mb-2"><label class="form-label" for="sp_terms">Payment terms</label><input class="form-control" id="sp_terms" name="payment_terms" maxlength="120"></div>
              <div class="mb-2"><label class="form-label" for="sp_status">Status</label><select class="form-select" id="sp_status" name="status"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
              <div class="mb-2"><label class="form-label" for="sp_notes">Notes</label><input class="form-control" id="sp_notes" name="notes" maxlength="255"></div>
              <button class="btn btn-primary btn-sm w-100">Save sponsor</button>
            </form>
          </div>
        </div>
      </div>
<?php endif; ?>
      <div class="col-xl-8">
        <div class="card app-card">
          <div class="card-header">Sponsors <span class="badge text-bg-light"><?= count($sponsors) ?></span></div>
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead><tr><th>Name</th><th>Type</th><th>Contact</th><th>Status</th><th class="text-end">Bursaries</th><th class="text-end">Balance</th></tr></thead>
              <tbody>
<?php if (!$sponsors): ?><tr><td colspan="6" class="text-center text-muted py-4">No sponsors recorded.</td></tr><?php endif; ?>
<?php foreach ($sponsors as $sp): ?>
                <tr>
                  <td><?= e($sp['name']) ?><?php if ($sp['payment_terms']): ?><div class="small text-muted"><?= e($sp['payment_terms']) ?></div><?php endif; ?></td>
                  <td><?= e(ucfirst($sp['sponsor_type'])) ?></td>
                  <td><?= e($sp['contact_person'] ?? '—') ?><div class="small text-muted"><?= e($sp['phone'] ?? '') ?><?= $sp['phone'] && $sp['email'] ? ' • ' : '' ?><?= e($sp['email'] ?? '') ?></div></td>
                  <td><span class="badge text-bg-<?= $sp['status'] === 'active' ? 'success' : 'secondary' ?>"><?= e($sp['status']) ?></span></td>
                  <td class="text-end"><?= (int)$sp['bursary_count'] ?></td>
                  <td class="text-end"><?= e(number_format((float)$sp['balance'], 2)) ?></td>
                </tr>
<?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
