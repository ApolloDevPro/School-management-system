<?php /* Campaigns, notification templates and PTA meetings. */ ?>
<div class="page-head">
  <div>
    <h1 class="h4 mb-0">Campaigns and PTA</h1>
    <p class="text-muted small mb-0">Send bulk messages, manage templates and record PTA meetings and attendance.</p>
  </div>
  <a class="btn btn-outline-secondary btn-sm" href="<?= url('communication') ?>"><i class="bi bi-arrow-left"></i> Announcements</a>
</div>

<ul class="nav nav-pills app-pills mb-3" role="tablist">
  <li class="nav-item" role="presentation"><button class="nav-link <?= $tab === 'campaigns' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tab-campaigns" type="button" role="tab">Campaigns</button></li>
  <li class="nav-item" role="presentation"><button class="nav-link <?= $tab === 'templates' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tab-templates" type="button" role="tab">Templates</button></li>
  <li class="nav-item" role="presentation"><button class="nav-link <?= $tab === 'pta' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tab-pta" type="button" role="tab">PTA meetings</button></li>
</ul>

<div class="tab-content">
  <div class="tab-pane fade <?= $tab === 'campaigns' ? 'show active' : '' ?>" id="tab-campaigns" role="tabpanel">
    <div class="row g-3">
<?php if ($canAdd): ?>
      <div class="col-xl-4">
        <div class="card app-card">
          <div class="card-header">New campaign</div>
          <div class="card-body">
            <form method="post" action="<?= url('communication/campaigns/send') ?>">
              <?= csrf_field() ?>
              <div class="mb-2"><label class="form-label" for="c_template">Template</label>
                <select class="form-select" id="c_template" name="template_id"><option value="0">No template</option>
<?php foreach ($templates as $tpl): if ((int)$tpl['is_active'] !== 1) continue; ?>
                  <option value="<?= (int)$tpl['id'] ?>" data-subject="<?= e($tpl['subject'] ?? '') ?>" data-body="<?= e($tpl['body']) ?>"><?= e($tpl['name'] . ' (' . $tpl['channel'] . ')') ?></option>
<?php endforeach; ?>
                </select>
              </div>
              <div class="row g-2 mb-2">
                <div class="col-6"><label class="form-label" for="c_channel">Channel</label><select class="form-select" id="c_channel" name="channel"><option value="sms">SMS</option><option value="email">Email</option><option value="portal">Portal</option></select></div>
                <div class="col-6"><label class="form-label" for="c_category">Category</label>
                  <select class="form-select" id="c_category" name="category"><option value="general">General</option><option value="fees">Fees</option><option value="attendance">Attendance</option><option value="results">Results</option><option value="announcement">Announcement</option><option value="event">Event</option><option value="emergency">Emergency</option><option value="reminder">Reminder</option><option value="canteen">Canteen</option><option value="transport">Transport</option></select>
                </div>
              </div>
              <div class="row g-2 mb-2">
                <div class="col-6"><label class="form-label" for="c_audience">Audience</label><select class="form-select" id="c_audience" name="audience"><option value="guardians">Guardians</option><option value="students">Students</option><option value="staff">Staff</option><option value="all">Everyone</option></select></div>
                <div class="col-6"><label class="form-label" for="c_class">Class (guardians)</label><select class="form-select" id="c_class" name="class_id"><option value="0">All classes</option>
<?php foreach ($classes as $class): ?><option value="<?= (int)$class['id'] ?>"><?= e($class['name']) ?></option><?php endforeach; ?>
                </select></div>
              </div>
              <div class="mb-2"><label class="form-label" for="c_subject">Subject</label><input class="form-control" id="c_subject" name="subject" maxlength="180"></div>
              <div class="mb-2"><label class="form-label" for="c_message">Message</label><textarea class="form-control" id="c_message" name="message" rows="4" maxlength="4000" required></textarea></div>
              <div class="row g-2 mb-2">
                <div class="col-6"><label class="form-label" for="c_priority">Priority</label><select class="form-select" id="c_priority" name="priority"><option value="normal">Normal</option><option value="high">High</option><option value="urgent">Urgent</option><option value="low">Low</option></select></div>
                <div class="col-6"><label class="form-label" for="c_schedule">Schedule</label><input class="form-control" id="c_schedule" type="datetime-local" name="scheduled_for"></div>
              </div>
              <div class="form-check mb-2"><input class="form-check-input" type="checkbox" id="c_emergency" name="is_emergency"><label class="form-check-label" for="c_emergency">Emergency message</label></div>
              <button class="btn btn-primary btn-sm w-100">Create campaign</button>
            </form>
            <div class="small text-muted mt-2">SMS and email campaigns are queued for your gateway; download the recipient CSV after creating one. Portal campaigns are delivered immediately.</div>
          </div>
        </div>
      </div>
<?php endif; ?>
      <div class="<?= $canAdd ? 'col-xl-8' : 'col-12' ?>">
        <div class="card app-card <?= $selectedCampaign ? 'mb-3' : '' ?>">
          <div class="card-header">Campaigns <span class="badge text-bg-light"><?= count($campaigns) ?></span></div>
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead><tr><th>Message</th><th>Channel</th><th>Audience</th><th class="text-end">Recipients</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
              <tbody>
<?php if (!$campaigns): ?><tr><td colspan="6" class="text-center text-muted py-4">No campaigns yet.</td></tr><?php endif; ?>
<?php foreach ($campaigns as $campaign): ?>
                <tr>
                  <td><a href="<?= url('communication/center', ['tab' => 'campaigns', 'campaign_id' => (int)$campaign['id']]) ?>"><strong><?= e($campaign['subject'] ?: mb_substr($campaign['message'], 0, 40)) ?></strong></a><div class="small text-muted"><?= e(mb_substr($campaign['message'], 0, 80)) ?></div></td>
                  <td><?= e(ucfirst($campaign['channel'])) ?></td>
                  <td><?= e(ucfirst($campaign['audience'])) ?></td>
                  <td class="text-end"><?= (int)$campaign['delivered_count'] ?> / <?= (int)$campaign['recipient_count'] ?></td>
                  <td><span class="badge text-bg-<?= $campaign['status'] === 'sent' ? 'success' : ($campaign['status'] === 'queued' ? 'warning' : 'secondary') ?>"><?= e($campaign['status']) ?></span></td>
                  <td class="text-end text-nowrap">
                    <?php if ($campaign['channel'] !== 'portal'): ?>
                    <a class="btn btn-sm btn-outline-secondary" href="<?= url('communication/campaigns/recipients', ['id' => (int)$campaign['id']]) ?>">CSV</a>
                    <?php endif; ?>
                    <?php if ($canApprove && $campaign['status'] === 'queued'): ?>
                    <form method="post" action="<?= url('communication/campaigns/mark-sent') ?>" class="d-inline">
                      <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$campaign['id'] ?>">
                      <button class="btn btn-sm btn-outline-primary" type="submit">Mark sent</button>
                    </form>
                    <form method="post" action="<?= url('communication/campaigns/cancel') ?>" class="d-inline">
                      <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$campaign['id'] ?>">
                      <button class="btn btn-sm btn-outline-danger" type="submit">Cancel</button>
                    </form>
                    <?php endif; ?>
                  </td>
                </tr>
<?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

<?php if ($selectedCampaign): ?>
        <div class="card app-card">
          <div class="card-header">Recipients &mdash; <?= e($selectedCampaign['subject'] ?: 'Campaign #' . $selectedCampaign['id']) ?> <span class="badge text-bg-light"><?= count($recipients) ?></span></div>
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead><tr><th>Name</th><th>Type</th><th>Destination</th><th>Status</th><th>Sent</th></tr></thead>
              <tbody>
<?php if (!$recipients): ?><tr><td colspan="5" class="text-center text-muted py-4">No recipients were captured.</td></tr><?php endif; ?>
<?php foreach ($recipients as $recipient): ?>
                <tr><td><?= e($recipient['name']) ?></td><td><?= e($recipient['recipient_type']) ?></td><td><?= e($recipient['destination']) ?></td><td><?= e($recipient['delivery_status']) ?></td><td class="small"><?= $recipient['sent_at'] ? e(date('d M Y H:i', strtotime($recipient['sent_at']))) : '—' ?></td></tr>
<?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
<?php endif; ?>
      </div>
    </div>
  </div>

  <div class="tab-pane fade <?= $tab === 'templates' ? 'show active' : '' ?>" id="tab-templates" role="tabpanel">
    <div class="row g-3">
<?php if ($canAdd): ?>
      <div class="col-xl-4">
        <div class="card app-card">
          <div class="card-header"><?= $editTemplate ? 'Edit template' : 'New template' ?></div>
          <div class="card-body">
            <form method="post" action="<?= url('communication/templates/save') ?>">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= $editTemplate ? (int)$editTemplate['id'] : 0 ?>">
              <div class="row g-2 mb-2">
                <div class="col-6"><label class="form-label" for="t_code">Code</label><input class="form-control" id="t_code" name="template_code" maxlength="60" value="<?= e($editTemplate['template_code'] ?? '') ?>" required></div>
                <div class="col-6"><label class="form-label" for="t_channel">Channel</label><select class="form-select" id="t_channel" name="channel"><?php foreach (['sms' => 'SMS', 'email' => 'Email', 'portal' => 'Portal'] as $value => $label): ?><option value="<?= $value ?>" <?= ($editTemplate['channel'] ?? '') === $value ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></div>
              </div>
              <div class="mb-2"><label class="form-label" for="t_name">Name</label><input class="form-control" id="t_name" name="name" maxlength="120" value="<?= e($editTemplate['name'] ?? '') ?>" required></div>
              <div class="mb-2"><label class="form-label" for="t_subject">Subject</label><input class="form-control" id="t_subject" name="subject" maxlength="180" value="<?= e($editTemplate['subject'] ?? '') ?>"></div>
              <div class="mb-2"><label class="form-label" for="t_body">Body</label><textarea class="form-control" id="t_body" name="body" rows="4" required><?= e($editTemplate['body'] ?? '') ?></textarea></div>
              <div class="mb-2"><label class="form-label" for="t_placeholders">Placeholders</label><input class="form-control" id="t_placeholders" name="placeholders" maxlength="255" value="<?= e($editTemplate['placeholders'] ?? '') ?>" placeholder="{{student_name}}, {{balance}}"></div>
              <div class="mb-2"><label class="form-label" for="t_module">Related module</label><input class="form-control" id="t_module" name="related_module" maxlength="40" value="<?= e($editTemplate['related_module'] ?? '') ?>"></div>
              <div class="form-check mb-2"><input class="form-check-input" type="checkbox" id="t_active" name="is_active" <?= !$editTemplate || (int)$editTemplate['is_active'] === 1 ? 'checked' : '' ?>><label class="form-check-label" for="t_active">Active</label></div>
              <button class="btn btn-primary btn-sm w-100">Save template</button>
            </form>
          </div>
        </div>
      </div>
<?php endif; ?>
      <div class="<?= $canAdd ? 'col-xl-8' : 'col-12' ?>">
        <div class="card app-card">
          <div class="card-header">Templates <span class="badge text-bg-light"><?= count($templates) ?></span></div>
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead><tr><th>Code</th><th>Name</th><th>Channel</th><th>Subject</th><th>Active</th><?php if ($canAdd): ?><th></th><?php endif; ?></tr></thead>
              <tbody>
<?php if (!$templates): ?><tr><td colspan="<?= $canAdd ? 6 : 5 ?>" class="text-center text-muted py-4">No templates yet.</td></tr><?php endif; ?>
<?php foreach ($templates as $template): ?>
                <tr>
                  <td><code><?= e($template['template_code']) ?></code></td>
                  <td><?= e($template['name']) ?><div class="small text-muted"><?= e(mb_substr($template['body'], 0, 70)) ?></div></td>
                  <td><?= e(ucfirst($template['channel'])) ?></td>
                  <td><?= e($template['subject'] ?? '—') ?></td>
                  <td><span class="badge text-bg-<?= (int)$template['is_active'] === 1 ? 'success' : 'secondary' ?>"><?= (int)$template['is_active'] === 1 ? 'active' : 'inactive' ?></span></td>
<?php if ($canAdd): ?>
                  <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= url('communication/center', ['tab' => 'templates', 'edit_template' => (int)$template['id']]) ?>">Edit</a></td>
<?php endif; ?>
                </tr>
<?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="tab-pane fade <?= $tab === 'pta' ? 'show active' : '' ?>" id="tab-pta" role="tabpanel">
    <div class="row g-3">
<?php if ($canAdd): ?>
      <div class="col-xl-4">
        <div class="card app-card">
          <div class="card-header"><?= $selectedMeeting ? 'Edit meeting' : 'New PTA meeting' ?></div>
          <div class="card-body">
            <form method="post" action="<?= url('communication/pta/save') ?>">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= $selectedMeeting ? (int)$selectedMeeting['id'] : 0 ?>">
              <div class="mb-2"><label class="form-label" for="m_title">Title</label><input class="form-control" id="m_title" name="title" maxlength="180" value="<?= e($selectedMeeting['title'] ?? '') ?>" required></div>
              <div class="row g-2 mb-2">
                <div class="col-6"><label class="form-label" for="m_year">Year</label><select class="form-select" id="m_year" name="academic_year_id" required><option value="">Choose…</option>
<?php foreach ($years as $year): ?><option value="<?= (int)$year['id'] ?>" <?= $selectedMeeting && (int)$selectedMeeting['academic_year_id'] === (int)$year['id'] ? 'selected' : '' ?>><?= e($year['name']) ?></option><?php endforeach; ?>
                </select></div>
                <div class="col-6"><label class="form-label" for="m_term">Term</label><select class="form-select" id="m_term" name="term_id"><option value="0">None</option>
<?php foreach ($terms as $term): ?><option value="<?= (int)$term['id'] ?>" <?= $selectedMeeting && (int)$selectedMeeting['term_id'] === (int)$term['id'] ? 'selected' : '' ?>><?= e($term['year_name'] . ' • ' . $term['name']) ?></option><?php endforeach; ?>
                </select></div>
              </div>
              <div class="row g-2 mb-2">
                <div class="col-6"><label class="form-label" for="m_date">Date</label><input class="form-control" id="m_date" type="date" name="meeting_date" value="<?= e($selectedMeeting['meeting_date'] ?? date('Y-m-d')) ?>" required></div>
                <div class="col-6"><label class="form-label" for="m_time">Start</label><input class="form-control" id="m_time" type="time" name="start_time" value="<?= e(substr((string)($selectedMeeting['start_time'] ?? ''), 0, 5)) ?>"></div>
              </div>
              <div class="mb-2"><label class="form-label" for="m_venue">Venue</label><input class="form-control" id="m_venue" name="venue" maxlength="160" value="<?= e($selectedMeeting['venue'] ?? '') ?>"></div>
              <div class="mb-2"><label class="form-label" for="m_agenda">Agenda</label><textarea class="form-control" id="m_agenda" name="agenda" rows="3"><?= e($selectedMeeting['agenda'] ?? '') ?></textarea></div>
              <div class="mb-2"><label class="form-label" for="m_resolutions">Resolutions</label><textarea class="form-control" id="m_resolutions" name="resolutions" rows="3"><?= e($selectedMeeting['resolutions'] ?? '') ?></textarea></div>
              <div class="row g-2 mb-2">
                <div class="col-6"><label class="form-label" for="m_agreed">Contributions agreed</label><input class="form-control" id="m_agreed" type="number" min="0" step="0.01" name="contributions_agreed" value="<?= e((string)($selectedMeeting['contributions_agreed'] ?? '')) ?>"></div>
                <div class="col-6"><label class="form-label" for="m_received">Received</label><input class="form-control" id="m_received" type="number" min="0" step="0.01" name="contributions_received" value="<?= e((string)($selectedMeeting['contributions_received'] ?? '')) ?>"></div>
              </div>
              <div class="row g-2 mb-2">
                <div class="col-6"><label class="form-label" for="m_chair">Chairperson</label><input class="form-control" id="m_chair" name="chairperson" maxlength="140" value="<?= e($selectedMeeting['chairperson'] ?? '') ?>"></div>
                <div class="col-6"><label class="form-label" for="m_secretary">Secretary</label><input class="form-control" id="m_secretary" name="secretary" maxlength="140" value="<?= e($selectedMeeting['secretary'] ?? '') ?>"></div>
              </div>
              <div class="mb-2"><label class="form-label" for="m_status">Status</label><select class="form-select" id="m_status" name="status"><?php foreach (['planned' => 'Planned', 'held' => 'Held', 'minutes_recorded' => 'Minutes recorded', 'closed' => 'Closed'] as $value => $label): ?><option value="<?= $value ?>" <?= ($selectedMeeting['status'] ?? '') === $value ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></div>
              <button class="btn btn-primary btn-sm w-100"><?= $selectedMeeting ? 'Update meeting' : 'Save meeting' ?></button>
            </form>
          </div>
        </div>
      </div>
<?php endif; ?>
      <div class="<?= $canAdd ? 'col-xl-8' : 'col-12' ?>">
        <div class="card app-card <?= $selectedMeeting ? 'mb-3' : '' ?>">
          <div class="card-header">PTA meetings <span class="badge text-bg-light"><?= count($meetings) ?></span></div>
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead><tr><th>Title</th><th>Date</th><th>Year / term</th><th class="text-end">Signed in</th><th>Status</th><th></th></tr></thead>
              <tbody>
<?php if (!$meetings): ?><tr><td colspan="6" class="text-center text-muted py-4">No PTA meetings recorded.</td></tr><?php endif; ?>
<?php foreach ($meetings as $meeting): ?>
                <tr>
                  <td><?= e($meeting['title']) ?><div class="small text-muted"><?= e($meeting['venue'] ?? '') ?></div></td>
                  <td><?= e(date('d M Y', strtotime($meeting['meeting_date']))) ?></td>
                  <td><?= e($meeting['year_name']) ?><?= $meeting['term_name'] ? ' • ' . e($meeting['term_name']) : '' ?></td>
                  <td class="text-end"><?= (int)$meeting['sign_in_count'] ?></td>
                  <td><span class="badge text-bg-<?= $meeting['status'] === 'closed' ? 'secondary' : ($meeting['status'] === 'planned' ? 'warning' : 'success') ?>"><?= e(str_replace('_', ' ', $meeting['status'])) ?></span></td>
                  <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= url('communication/center', ['tab' => 'pta', 'meeting_id' => (int)$meeting['id']]) ?>">Open</a></td>
                </tr>
<?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

<?php if ($selectedMeeting): ?>
        <div class="card app-card">
          <div class="card-header">Attendance &mdash; <?= e($selectedMeeting['title']) ?> <span class="badge text-bg-light"><?= count($attendance) ?></span></div>
          <div class="card-body border-bottom">
<?php if ($canAdd): ?>
            <form method="post" action="<?= url('communication/pta/sign-in') ?>" class="row g-2 align-items-end">
              <?= csrf_field() ?><input type="hidden" name="meeting_id" value="<?= (int)$selectedMeeting['id'] ?>">
              <div class="col-md-5"><label class="form-label" for="att_guardian">Guardian</label><select class="form-select" id="att_guardian" name="guardian_id" required><option value="">Choose…</option>
<?php foreach ($guardians as $guardian): ?><option value="<?= (int)$guardian['id'] ?>"><?= e(($guardian['full_name'] ?: $guardian['last_name'] . ' ' . $guardian['first_name'])) ?></option><?php endforeach; ?>
              </select></div>
              <div class="col-md-3"><label class="form-label" for="att_student">Student (optional)</label><input class="form-control" id="att_student" type="number" min="1" name="student_id" placeholder="Student ID"></div>
              <div class="col-md-2"><label class="form-label" for="att_proxy">Proxy name</label><input class="form-control" id="att_proxy" name="proxy_name" maxlength="140"></div>
              <div class="col-md-1"><div class="form-check"><input class="form-check-input" type="checkbox" id="att_is_proxy" name="is_proxy"><label class="form-check-label" for="att_is_proxy">Proxy</label></div></div>
              <div class="col-md-1"><button class="btn btn-primary w-100">Sign in</button></div>
            </form>
<?php endif; ?>
          </div>
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead><tr><th>Guardian</th><th>Student</th><th>Arrived</th><th>Proxy</th></tr></thead>
              <tbody>
<?php if (!$attendance): ?><tr><td colspan="4" class="text-center text-muted py-4">No one has signed in yet.</td></tr><?php endif; ?>
<?php foreach ($attendance as $row): ?>
                <tr><td><?= e($row['full_name'] ?: ($row['last_name'] . ' ' . $row['first_name'])) ?><div class="small text-muted"><?= e($row['phone'] ?? '') ?></div></td><td><?= $row['admission_number'] ? e($row['student_last'] . ' ' . $row['student_first'] . ' (' . $row['admission_number'] . ')') : '—' ?></td><td class="small"><?= e($row['arrived_at'] ? date('d M Y H:i', strtotime($row['arrived_at'])) : '—') ?></td><td><?= (int)$row['is_proxy'] === 1 ? e($row['proxy_name'] ?? 'yes') : '—' ?></td></tr>
<?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
<?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script>
document.getElementById('c_template')?.addEventListener('change', function () {
  var option = this.options[this.selectedIndex];
  var subject = document.getElementById('c_subject');
  var message = document.getElementById('c_message');
  if (option && option.value !== '0') {
    subject.value = option.getAttribute('data-subject') || '';
    message.value = option.getAttribute('data-body') || '';
    var channel = document.getElementById('c_channel');
    if (channel) {
      channel.value = option.textContent.indexOf('(email)') !== -1 ? 'email' : (option.textContent.indexOf('(portal)') !== -1 ? 'portal' : 'sms');
    }
  }
});
</script>
