<?php
/* Co-curricular activities: clubs, teams, fixtures, competitions, talent and house points. */
$sportLabel = static fn(string $s): string => ucfirst($s);
$teamBadge = static fn(string $s): string => $s === 'active' ? 'success' : ($s === 'archived' ? 'light text-dark' : 'secondary');
$fixtureBadge = static fn(string $s): string => match ($s) {
    'played' => 'success', 'scheduled' => 'primary', 'live' => 'warning',
    'postponed' => 'secondary', 'cancelled', 'abandoned' => 'danger', default => 'secondary'
};
$outcomeBadge = static fn(?string $s): string => match ($s) { 'win' => 'success', 'draw' => 'warning', 'loss' => 'danger', default => 'secondary' };
?>
<div class="page-head">
  <div><h1 class="h4 mb-0">Co-curricular activities</h1><p class="text-muted small mb-0">Clubs, sports teams, fixtures, competitions, talent and house points.</p></div>
</div>

<ul class="nav nav-pills app-pills mb-3" role="tablist">
  <li class="nav-item" role="presentation"><button class="nav-link <?= $tab === 'clubs' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tab-clubs" type="button" role="tab">Clubs</button></li>
  <li class="nav-item" role="presentation"><button class="nav-link <?= $tab === 'teams' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tab-teams" type="button" role="tab">Sports teams</button></li>
  <li class="nav-item" role="presentation"><button class="nav-link <?= $tab === 'fixtures' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tab-fixtures" type="button" role="tab">Fixtures &amp; results</button></li>
  <li class="nav-item" role="presentation"><button class="nav-link <?= $tab === 'competitions' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tab-competitions" type="button" role="tab">Competitions</button></li>
  <li class="nav-item" role="presentation"><button class="nav-link <?= $tab === 'talent' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tab-talent" type="button" role="tab">Talent</button></li>
  <li class="nav-item" role="presentation"><button class="nav-link <?= $tab === 'houses' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tab-houses" type="button" role="tab">House points</button></li>
</ul>

<div class="tab-content">

  <!-- Clubs ------------------------------------------------------------------>
  <div class="tab-pane fade <?= $tab === 'clubs' ? 'show active' : '' ?>" id="tab-clubs" role="tabpanel">
    <div class="row g-3">
<?php if ($canAdd): ?>
      <div class="col-xl-4">
        <div class="card app-card">
          <div class="card-header">Add club / society</div>
          <div class="card-body">
            <form method="post" action="<?= url('cocurricular/clubs/store') ?>" data-ajax>
              <?= csrf_field() ?>
              <div class="form-section"><i class="bi bi-trophy"></i> Club details</div>
              <div class="mb-2">
                <label class="form-label">Club name <span class="text-danger">*</span></label>
                <input class="form-control" name="name" maxlength="160" required>
                <span class="invalid-feedback d-block" data-error-for="name"></span>
              </div>
              <div class="mb-2">
                <label class="form-label">Category</label>
                <select class="form-select" name="club_category">
                  <option value="academic">Academic</option>
                  <option value="sports">Sports</option>
                  <option value="arts">Arts &amp; culture</option>
                  <option value="debate">Debate</option>
                  <option value="science">Science</option>
                  <option value="religious">Religious</option>
                  <option value="community">Community service</option>
                  <option value="general" selected>General</option>
                </select>
              </div>
              <div class="mb-3">
                <label class="form-label">Description</label>
                <textarea class="form-control" name="description" rows="2" maxlength="500"></textarea>
              </div>
              <button class="btn btn-primary" type="submit">Create club</button>
            </form>
          </div>
        </div>
      </div>
<?php endif; ?>
      <div class="<?= $canAdd ? 'col-xl-8' : 'col-12' ?>">
        <div class="card app-card">
          <div class="card-header">Clubs &amp; societies <span class="badge text-bg-light"><?= count($clubs) ?></span></div>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" data-table>
              <thead><tr><th>Name</th><th>Category</th><th>Meeting</th><th>Venue</th><th>Status</th><?php if ($canEdit): ?><th></th><?php endif; ?></tr></thead>
              <tbody>
                <?php if (!$clubs): ?><tr><td colspan="<?= $canEdit ? 6 : 5 ?>" class="text-center text-muted py-4">No clubs registered yet.</td></tr><?php endif; ?>
                <?php foreach ($clubs as $club): ?>
                <tr>
                  <td><strong><?= e($club['name']) ?></strong></td>
                  <td><?= e(ucfirst($club['club_category'])) ?></td>
                  <td><?= e($club['meeting_day'] ?? '—') ?></td>
                  <td><?= e($club['meeting_venue'] ?? '—') ?></td>
                  <td><span class="badge text-bg-<?= $club['status'] === 'active' ? 'success' : 'secondary' ?>"><?= e($club['status']) ?></span></td>
                  <?php if ($canEdit): ?>
                  <td>
                    <form method="post" action="<?= url('cocurricular/clubs/toggle') ?>" data-ajax>
                      <?= csrf_field() ?>
                      <input type="hidden" name="id" value="<?= (int)$club['id'] ?>">
                      <select class="form-select form-select-sm" name="status" onchange="this.form.requestSubmit()" style="width:auto">
                        <option value="active"   <?= $club['status'] === 'active'   ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $club['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                      </select>
                    </form>
                  </td>
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

  <!-- Sports teams ----------------------------------------------------------->
  <div class="tab-pane fade <?= $tab === 'teams' ? 'show active' : '' ?>" id="tab-teams" role="tabpanel">
    <div class="row g-3">
<?php if ($canAdd): ?>
      <div class="col-xl-4">
        <div class="card app-card">
          <div class="card-header">Create sports team</div>
          <div class="card-body">
            <form method="post" action="<?= url('cocurricular/teams/save') ?>">
              <?= csrf_field() ?>
              <div class="form-section"><i class="bi bi-people"></i> Team</div>
              <div class="mb-2"><label class="form-label">Team name <span class="text-danger">*</span></label><input class="form-control" name="team_name" maxlength="140" required></div>
              <div class="row g-2 mb-2">
                <div class="col-6"><label class="form-label">Sport</label><select class="form-select" name="sport_category"><?php foreach (['football','netball','basketball','athletics','volleyball','cricket','handball','swimming','other'] as $sport): ?><option value="<?= $sport ?>"><?= e($sportLabel($sport)) ?></option><?php endforeach; ?></select></div>
                <div class="col-3"><label class="form-label">Team</label><select class="form-select" name="team_type"><option value="boys">Boys</option><option value="girls">Girls</option><option value="mixed">Mixed</option></select></div>
                <div class="col-3"><label class="form-label">Age</label><select class="form-select" name="age_group"><option value="">—</option><option value="junior">Junior</option><option value="senior">Senior</option><option value="mixed">Mixed</option></select></div>
              </div>
              <div class="row g-2 mb-2">
                <div class="col-6"><label class="form-label">Coach</label><select class="form-select" name="coach_staff_id"><option value="0">—</option><?php foreach ($staff as $person): ?><option value="<?= (int)$person['id'] ?>"><?= e($person['last_name'] . ' ' . $person['first_name']) ?></option><?php endforeach; ?></select></div>
                <div class="col-6"><label class="form-label">Assistant coach</label><select class="form-select" name="assistant_coach_staff_id"><option value="0">—</option><?php foreach ($staff as $person): ?><option value="<?= (int)$person['id'] ?>"><?= e($person['last_name'] . ' ' . $person['first_name']) ?></option><?php endforeach; ?></select></div>
              </div>
              <div class="form-section"><i class="bi bi-calendar-week"></i> Training</div>
              <div class="row g-2 mt-1 mb-2">
                <div class="col-6"><label class="form-label">Day</label><select class="form-select" name="training_day"><option value="">—</option><?php foreach (['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'] as $day): ?><option value="<?= $day ?>"><?= $day ?></option><?php endforeach; ?></select></div>
                <div class="col-6"><label class="form-label">Time</label><input class="form-control" type="time" name="training_time"></div>
              </div>
              <div class="row g-2 mb-2">
                <div class="col-6"><label class="form-label">Venue</label><input class="form-control" name="training_venue" maxlength="120"></div>
                <div class="col-6"><label class="form-label">Season</label><input class="form-control" name="season" maxlength="30" placeholder="e.g. 2026 league"></div>
              </div>
              <div class="row g-2 mb-3">
                <div class="col-7"><label class="form-label">Academic year</label><select class="form-select" name="academic_year_id"><option value="0">—</option><?php foreach ($years as $year): ?><option value="<?= (int)$year['id'] ?>" <?= (int)$year['is_current'] === 1 ? 'selected' : '' ?>><?= e($year['name']) ?></option><?php endforeach; ?></select></div>
                <div class="col-5"><label class="form-label">Status</label><select class="form-select" name="status"><option value="active">Active</option><option value="inactive">Inactive</option><option value="archived">Archived</option></select></div>
              </div>
              <button class="btn btn-primary">Save team</button>
            </form>
          </div>
        </div>
      </div>
<?php endif; ?>
      <div class="<?= $canAdd ? 'col-xl-8' : 'col-12' ?>">
        <div class="card app-card">
          <div class="card-header">Sports teams <span class="badge text-bg-light"><?= count($teams) ?></span></div>
          <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
              <thead><tr><th>Team</th><th>Sport</th><th>Type</th><th>Coach</th><th>Training</th><th class="text-end">Members</th><th>Status</th></tr></thead>
              <tbody>
<?php if (!$teams): ?><tr><td colspan="7" class="text-center text-muted py-4">No sports teams yet.</td></tr><?php endif; ?>
<?php foreach ($teams as $team): ?>
                <tr>
                  <td><strong><?= e($team['team_name']) ?></strong><div class="small text-muted"><?= e($team['season'] ?: ($team['year_name'] ?? '—')) ?></div></td>
                  <td><?= e($sportLabel($team['sport_category'])) ?></td>
                  <td><?= e(ucfirst($team['team_type'])) ?><?= $team['age_group'] ? ' · ' . e(ucfirst($team['age_group'])) : '' ?></td>
                  <td class="small"><?= e($team['coach_name'] ?: '—') ?></td>
                  <td class="small"><?= e($team['training_day'] ?: '—') ?><?= $team['training_time'] ? ' ' . e(substr((string)$team['training_time'], 0, 5)) : '' ?><?php if ($team['training_venue']): ?><div class="text-muted"><?= e($team['training_venue']) ?></div><?php endif; ?></td>
                  <td class="text-end"><?= (int)$team['member_count'] ?></td>
                  <td><span class="badge text-bg-<?= $teamBadge($team['status']) ?>"><?= e($team['status']) ?></span></td>
                </tr>
<?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

<?php if ($canAdd || $canEdit): ?>
        <div class="card app-card mt-3">
          <div class="card-header">Team members</div>
          <div class="card-body pb-0">
<?php if ($canAdd): ?>
            <form method="post" action="<?= url('cocurricular/teams/member/add') ?>" class="row g-2 align-items-end mb-3">
              <?= csrf_field() ?>
              <div class="col-md-3"><label class="form-label" for="m_team">Team</label><select class="form-select" id="m_team" name="team_id" required><option value="">Choose…</option><?php foreach ($teams as $team): ?><option value="<?= (int)$team['id'] ?>"><?= e($team['team_name']) ?></option><?php endforeach; ?></select></div>
              <div class="col-md-3"><label class="form-label" for="m_student">Student</label><select class="form-select" id="m_student" name="student_id" required><option value="">Choose…</option><?php foreach ($students as $student): ?><option value="<?= (int)$student['id'] ?>"><?= e($student['admission_number'] . ' — ' . $student['last_name'] . ' ' . $student['first_name']) ?></option><?php endforeach; ?></select></div>
              <div class="col-md-2"><label class="form-label" for="m_role">Role</label><select class="form-select" id="m_role" name="member_role"><option value="player">Player</option><option value="captain">Captain</option><option value="vice_captain">Vice captain</option><option value="substitute">Substitute</option><option value="manager">Manager</option></select></div>
              <div class="col-md-1"><label class="form-label" for="m_shirt">Shirt</label><input class="form-control" id="m_shirt" name="shirt_number" type="number" min="1" max="99"></div>
              <div class="col-md-2"><label class="form-label" for="m_joined">Joined</label><input class="form-control" id="m_joined" type="date" name="joined_date" value="<?= date('Y-m-d') ?>"></div>
              <div class="col-md-1"><button class="btn btn-primary w-100" title="Add member"><i class="bi bi-plus-lg"></i></button></div>
            </form>
<?php endif; ?>
          </div>
          <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0" data-table>
              <thead><tr><th>Player</th><th>Team</th><th>Role</th><th>Shirt</th><th>Joined</th><th>Status</th><?php if ($canEdit): ?><th></th><?php endif; ?></tr></thead>
              <tbody>
<?php if (!$members): ?><tr><td colspan="<?= $canEdit ? 7 : 6 ?>" class="text-center text-muted py-4">No team members yet.</td></tr><?php endif; ?>
<?php foreach ($members as $member): ?>
                <tr>
                  <td><strong><?= e($member['last_name'] . ' ' . $member['first_name']) ?></strong><div class="small text-muted"><?= e($member['admission_number'] . ($member['class_name'] ? ' · ' . $member['class_name'] : '')) ?></div></td>
                  <td><?= e($member['team_name']) ?></td>
                  <td><?= e(str_replace('_', ' ', ucfirst($member['member_role']))) ?></td>
                  <td><?= $member['shirt_number'] !== null ? (int)$member['shirt_number'] : '—' ?></td>
                  <td class="small"><?= e(date('d M Y', strtotime($member['joined_date']))) ?></td>
                  <td><span class="badge text-bg-<?= $member['status'] === 'active' ? 'success' : ($member['status'] === 'injured' ? 'warning' : 'secondary') ?>"><?= e($member['status']) ?></span></td>
<?php if ($canEdit): ?>
                  <td class="text-end">
                    <form method="post" action="<?= url('cocurricular/teams/member/remove') ?>" data-confirm="Remove this player from the team?">
                      <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$member['id'] ?>">
                      <button class="btn btn-outline-danger btn-sm" type="submit"><i class="bi bi-x-lg"></i></button>
                    </form>
                  </td>
<?php endif; ?>
                </tr>
<?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
<?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Fixtures --------------------------------------------------------------->
  <div class="tab-pane fade <?= $tab === 'fixtures' ? 'show active' : '' ?>" id="tab-fixtures" role="tabpanel">
    <div class="row g-3">
<?php if ($canAdd): ?>
      <div class="col-xl-4">
        <div class="card app-card">
          <div class="card-header">Schedule fixture</div>
          <div class="card-body">
            <form method="post" action="<?= url('cocurricular/fixtures/save') ?>">
              <?= csrf_field() ?>
              <div class="mb-2"><label class="form-label">Team <span class="text-danger">*</span></label><select class="form-select" name="team_id" required><option value="">Choose…</option><?php foreach ($teams as $team): ?><option value="<?= (int)$team['id'] ?>"><?= e($team['team_name']) ?></option><?php endforeach; ?></select></div>
              <div class="mb-2"><label class="form-label">Opponent <span class="text-danger">*</span></label><input class="form-control" name="opponent" maxlength="160" required></div>
              <div class="row g-2 mb-2">
                <div class="col-6"><label class="form-label">Opponent type</label><select class="form-select" name="opponent_type"><option value="inter_school">Inter-school</option><option value="house">House</option><option value="national">National</option><option value="friendly">Friendly</option><option value="tournament">Tournament</option></select></div>
                <div class="col-6"><label class="form-label">Venue side</label><select class="form-select" name="home_away"><option value="home">Home</option><option value="away">Away</option><option value="neutral">Neutral</option></select></div>
              </div>
              <div class="row g-2 mb-2">
                <div class="col-7"><label class="form-label">Date <span class="text-danger">*</span></label><input class="form-control" type="date" name="fixture_date" value="<?= date('Y-m-d') ?>" required></div>
                <div class="col-5"><label class="form-label">Kick-off</label><input class="form-control" type="time" name="start_time"></div>
              </div>
              <div class="row g-2 mb-2">
                <div class="col-6"><label class="form-label">Category</label><select class="form-select" name="category"><option value="league">League</option><option value="friendly" selected>Friendly</option><option value="cup">Cup</option><option value="knockout">Knockout</option><option value="house">House</option><option value="training">Training</option></select></div>
                <div class="col-6"><label class="form-label">Status</label><select class="form-select" name="status"><?php foreach (['scheduled','live','played','postponed','cancelled','abandoned'] as $value): ?><option value="<?= $value ?>"><?= e(ucfirst($value)) ?></option><?php endforeach; ?></select></div>
              </div>
              <div class="mb-2"><label class="form-label">Venue</label><input class="form-control" name="venue" maxlength="160"></div>
              <div class="mb-2"><label class="form-label">Competition</label><select class="form-select" name="competition_id"><option value="0">None</option><?php foreach ($competitions as $competition): ?><option value="<?= (int)$competition['id'] ?>"><?= e($competition['name']) ?></option><?php endforeach; ?></select></div>
              <div class="mb-2"><label class="form-label">Notes</label><input class="form-control" name="notes" maxlength="255"></div>
              <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="transport_required" id="f_transport"><label class="form-check-label" for="f_transport">Transport required</label></div>
              <button class="btn btn-primary">Save fixture</button>
            </form>
          </div>
        </div>
      </div>
<?php endif; ?>
      <div class="<?= $canAdd ? 'col-xl-8' : 'col-12' ?>">
        <div class="card app-card">
          <div class="card-header">Fixtures <span class="badge text-bg-light"><?= count($fixtures) ?></span></div>
          <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0" data-table>
              <thead><tr><th>Date</th><th>Fixture</th><th>Venue</th><th>Category</th><th>Result</th><th>Status</th><?php if ($canEdit): ?><th></th><?php endif; ?></tr></thead>
              <tbody>
<?php if (!$fixtures): ?><tr><td colspan="<?= $canEdit ? 7 : 6 ?>" class="text-center text-muted py-4">No fixtures scheduled.</td></tr><?php endif; ?>
<?php foreach ($fixtures as $fixture): ?>
                <tr>
                  <td class="small text-nowrap"><?= e(date('d M Y', strtotime($fixture['fixture_date']))) ?><?= $fixture['start_time'] ? '<div class="text-muted">' . e(substr((string)$fixture['start_time'], 0, 5)) . '</div>' : '' ?></td>
                  <td><strong><?= e($fixture['team_name']) ?></strong> <span class="text-muted">vs</span> <?= e($fixture['opponent']) ?><?php if ((int)$fixture['transport_required'] === 1): ?><span class="badge text-bg-info ms-1">Transport</span><?php endif; ?></td>
                  <td><?= e(ucfirst($fixture['home_away'])) ?><?php if ($fixture['venue']): ?><div class="small text-muted"><?= e($fixture['venue']) ?></div><?php endif; ?></td>
                  <td><?= e(ucfirst($fixture['category'])) ?></td>
                  <td><?php if ($fixture['our_score'] !== null): ?><span class="badge text-bg-<?= $outcomeBadge($fixture['result_outcome']) ?>"><?= (int)$fixture['our_score'] ?>–<?= (int)$fixture['opponent_score'] ?> <?= e(ucfirst((string)$fixture['result_outcome'])) ?></span><?php else: ?><span class="text-muted small">Pending</span><?php endif; ?></td>
                  <td><span class="badge text-bg-<?= $fixtureBadge($fixture['status']) ?>"><?= e($fixture['status']) ?></span></td>
<?php if ($canEdit): ?>
                  <td class="text-end">
                    <button class="btn btn-outline-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#fixtureResultModal"
                            data-fixture-id="<?= (int)$fixture['id'] ?>" data-fixture-name="<?= e($fixture['team_name'] . ' vs ' . $fixture['opponent']) ?>"
                            data-our="<?= (int)($fixture['our_score'] ?? 0) ?>" data-theirs="<?= (int)($fixture['opponent_score'] ?? 0) ?>"
                            data-half="<?= e($fixture['half_time_score'] ?? '') ?>" data-attendance="<?= (int)($fixture['attendance_count'] ?? 0) ?>" data-referee="<?= e($fixture['referee'] ?? '') ?>">Result</button>
                  </td>
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

  <!-- Competitions ----------------------------------------------------------->
  <div class="tab-pane fade <?= $tab === 'competitions' ? 'show active' : '' ?>" id="tab-competitions" role="tabpanel">
    <div class="row g-3">
<?php if ($canAdd): ?>
      <div class="col-xl-4">
        <div class="card app-card">
          <div class="card-header">Add competition</div>
          <div class="card-body">
            <form method="post" action="<?= url('cocurricular/competitions/save') ?>">
              <?= csrf_field() ?>
              <div class="mb-2"><label class="form-label">Name <span class="text-danger">*</span></label><input class="form-control" name="name" maxlength="180" required></div>
              <div class="row g-2 mb-2">
                <div class="col-7"><label class="form-label">Type</label><select class="form-select" name="competition_type"><?php foreach (['music','drama','science_fair','sports','debate','quiz','agriculture','ict','other'] as $value): ?><option value="<?= $value ?>"><?= e(ucwords(str_replace('_', ' ', $value))) ?></option><?php endforeach; ?></select></div>
                <div class="col-5"><label class="form-label">Level</label><select class="form-select" name="level"><option value="school">School</option><option value="district">District</option><option value="regional">Regional</option><option value="national">National</option><option value="international">International</option></select></div>
              </div>
              <div class="row g-2 mb-2">
                <div class="col-6"><label class="form-label">Organiser</label><input class="form-control" name="organiser" maxlength="160"></div>
                <div class="col-6"><label class="form-label">Venue</label><input class="form-control" name="venue" maxlength="160"></div>
              </div>
              <div class="row g-2 mb-2">
                <div class="col-6"><label class="form-label">Start</label><input class="form-control" type="date" name="start_date"></div>
                <div class="col-6"><label class="form-label">End</label><input class="form-control" type="date" name="end_date"></div>
              </div>
              <div class="row g-2 mb-2">
                <div class="col-6"><label class="form-label">Budget (UGX)</label><input class="form-control" type="number" min="0" step="0.01" name="budget"></div>
                <div class="col-6"><label class="form-label">Status</label><select class="form-select" name="status"><option value="planned">Planned</option><option value="registration_open">Registration open</option><option value="participating">Participating</option><option value="completed">Completed</option><option value="cancelled">Cancelled</option></select></div>
              </div>
              <div class="mb-2"><label class="form-label">Results summary</label><textarea class="form-control" name="results_summary" rows="2"></textarea></div>
              <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="transport_required" id="c_transport"><label class="form-check-label" for="c_transport">Transport required</label></div>
              <button class="btn btn-primary">Save competition</button>
            </form>
          </div>
        </div>
      </div>
<?php endif; ?>
      <div class="<?= $canAdd ? 'col-xl-8' : 'col-12' ?>">
        <div class="card app-card">
          <div class="card-header">Competitions <span class="badge text-bg-light"><?= count($competitions) ?></span></div>
          <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
              <thead><tr><th>Competition</th><th>Type / level</th><th>Dates</th><th class="text-end">Entries</th><th class="text-end">Budget</th><th>Status</th></tr></thead>
              <tbody>
<?php if (!$competitions): ?><tr><td colspan="6" class="text-center text-muted py-4">No competitions yet.</td></tr><?php endif; ?>
<?php foreach ($competitions as $competition): ?>
                <tr>
                  <td><strong><?= e($competition['name']) ?></strong><?php if ($competition['organiser']): ?><div class="small text-muted"><?= e($competition['organiser']) ?></div><?php endif; ?></td>
                  <td><?= e(ucwords(str_replace('_', ' ', $competition['competition_type']))) ?><div class="small text-muted"><?= e(ucfirst($competition['level'])) ?></div></td>
                  <td class="small"><?= $competition['start_date'] ? e(date('d M Y', strtotime($competition['start_date']))) : '—' ?><?= $competition['end_date'] ? ' – ' . e(date('d M Y', strtotime($competition['end_date']))) : '' ?></td>
                  <td class="text-end"><?= (int)$competition['entry_count'] ?></td>
                  <td class="text-end"><?= $competition['budget'] !== null ? number_format((float)$competition['budget']) : '—' ?></td>
                  <td><span class="badge text-bg-<?= $competition['status'] === 'completed' ? 'success' : ($competition['status'] === 'cancelled' ? 'danger' : 'secondary') ?>"><?= e(str_replace('_', ' ', $competition['status'])) ?></span></td>
                </tr>
<?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

<?php if ($canAdd): ?>
        <div class="card app-card mt-3">
          <div class="card-header">Competition entries</div>
          <div class="card-body pb-0">
            <form method="post" action="<?= url('cocurricular/competitions/entry') ?>" class="row g-2 align-items-end mb-3">
              <?= csrf_field() ?>
              <div class="col-md-4"><label class="form-label" for="e_comp">Competition</label><select class="form-select" id="e_comp" name="competition_id" required><option value="">Choose…</option><?php foreach ($competitions as $competition): ?><option value="<?= (int)$competition['id'] ?>"><?= e($competition['name']) ?></option><?php endforeach; ?></select></div>
              <div class="col-md-4"><label class="form-label" for="e_student">Student (optional)</label><select class="form-select" id="e_student" name="student_id"><option value="0">—</option><?php foreach ($students as $student): ?><option value="<?= (int)$student['id'] ?>"><?= e($student['admission_number'] . ' — ' . $student['last_name'] . ' ' . $student['first_name']) ?></option><?php endforeach; ?></select></div>
              <div class="col-md-4"><label class="form-label" for="e_name">Entry / group name</label><input class="form-control" id="e_name" name="entry_name" maxlength="180"></div>
              <div class="col-md-3"><label class="form-label" for="e_role">Role</label><select class="form-select" id="e_role" name="participation_role"><option value="solo">Solo</option><option value="team_member">Team member</option><option value="team_leader">Team leader</option><option value="support">Support</option><option value="exhibitor">Exhibitor</option></select></div>
              <div class="col-md-3"><label class="form-label" for="e_team">Team</label><select class="form-select" id="e_team" name="team_id"><option value="0">—</option><?php foreach ($teams as $team): ?><option value="<?= (int)$team['id'] ?>"><?= e($team['team_name']) ?></option><?php endforeach; ?></select></div>
              <div class="col-md-2"><label class="form-label" for="e_fee">Fee</label><input class="form-control" id="e_fee" type="number" min="0" step="0.01" name="fee_amount"></div>
              <div class="col-md-2"><label class="form-label" for="e_status">Status</label><select class="form-select" id="e_status" name="status"><option value="registered">Registered</option><option value="confirmed">Confirmed</option><option value="participated">Participated</option><option value="withdrew">Withdrew</option><option value="disqualified">Disqualified</option></select></div>
              <div class="col-md-2"><button class="btn btn-primary w-100">Add entry</button></div>
            </form>
          </div>
          <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0" data-table>
              <thead><tr><th>Competition</th><th>Entry</th><th>Role</th><th>Position / award</th><th>Status</th></tr></thead>
              <tbody>
<?php if (!$entries): ?><tr><td colspan="5" class="text-center text-muted py-4">No competition entries yet.</td></tr><?php endif; ?>
<?php foreach ($entries as $entry): ?>
                <tr>
                  <td><?= e($entry['competition_name']) ?></td>
                  <td><?= e($entry['last_name'] ? $entry['last_name'] . ' ' . $entry['first_name'] : ($entry['entry_name'] ?? '—')) ?><?php if ($entry['team_name']): ?><div class="small text-muted"><?= e($entry['team_name']) ?></div><?php endif; ?></td>
                  <td><?= e(str_replace('_', ' ', ucfirst($entry['participation_role']))) ?></td>
                  <td class="small"><?= e($entry['position'] ?: '—') ?><?php if ($entry['award']): ?><div class="text-muted"><?= e($entry['award']) ?></div><?php endif; ?></td>
                  <td><span class="badge text-bg-<?= $entry['status'] === 'participated' ? 'success' : ($entry['status'] === 'withdrew' ? 'secondary' : 'info') ?>"><?= e($entry['status']) ?></span></td>
                </tr>
<?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
<?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Talent ----------------------------------------------------------------->
  <div class="tab-pane fade <?= $tab === 'talent' ? 'show active' : '' ?>" id="tab-talent" role="tabpanel">
    <div class="row g-3">
<?php if ($canAdd): ?>
      <div class="col-xl-4">
        <div class="card app-card">
          <div class="card-header">Record talent</div>
          <div class="card-body">
            <form method="post" action="<?= url('cocurricular/talent/save') ?>">
              <?= csrf_field() ?>
              <div class="mb-2"><label class="form-label">Student <span class="text-danger">*</span></label><select class="form-select" name="student_id" required><option value="">Choose…</option><?php foreach ($students as $student): ?><option value="<?= (int)$student['id'] ?>"><?= e($student['admission_number'] . ' — ' . $student['last_name'] . ' ' . $student['first_name']) ?></option><?php endforeach; ?></select></div>
              <div class="row g-2 mb-2">
                <div class="col-6"><label class="form-label">Talent type</label><select class="form-select" name="talent_type"><?php foreach (['music','sport','drama','art','leadership','academics','crafts','other'] as $value): ?><option value="<?= $value ?>"><?= e(ucfirst($value)) ?></option><?php endforeach; ?></select></div>
                <div class="col-6"><label class="form-label">Level</label><select class="form-select" name="level"><option value="class">Class</option><option value="school" selected>School</option><option value="district">District</option><option value="regional">Regional</option><option value="national">National</option><option value="international">International</option></select></div>
              </div>
              <div class="mb-2"><label class="form-label">Talent name <span class="text-danger">*</span></label><input class="form-control" name="talent_name" maxlength="160" required></div>
              <div class="mb-2"><label class="form-label">Description</label><input class="form-control" name="description" maxlength="500"></div>
              <div class="mb-2"><label class="form-label">Achievement</label><input class="form-control" name="achievement" maxlength="255"></div>
              <div class="row g-2 mb-2">
                <div class="col-7"><label class="form-label">Award title</label><input class="form-control" name="award_title" maxlength="160"></div>
                <div class="col-5"><label class="form-label">Award date</label><input class="form-control" type="date" name="award_date"></div>
              </div>
              <div class="mb-2"><label class="form-label">Leadership role</label><input class="form-control" name="leadership_role" maxlength="120"></div>
              <div class="mb-2"><label class="form-label">Notes</label><input class="form-control" name="notes" maxlength="500"></div>
              <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="is_testimonial_note" id="t_testimonial"><label class="form-check-label" for="t_testimonial">Use in testimonial</label></div>
              <button class="btn btn-primary">Save talent record</button>
            </form>
          </div>
        </div>
      </div>
<?php endif; ?>
      <div class="<?= $canAdd ? 'col-xl-8' : 'col-12' ?>">
        <div class="card app-card">
          <div class="card-header">Talent records <span class="badge text-bg-light"><?= count($talents) ?></span></div>
          <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0" data-table>
              <thead><tr><th>Student</th><th>Talent</th><th>Type</th><th>Level</th><th>Award</th><th>Recorded</th></tr></thead>
              <tbody>
<?php if (!$talents): ?><tr><td colspan="6" class="text-center text-muted py-4">No talent records yet.</td></tr><?php endif; ?>
<?php foreach ($talents as $talent): ?>
                <tr>
                  <td><strong><?= e($talent['last_name'] . ' ' . $talent['first_name']) ?></strong><div class="small text-muted"><?= e($talent['admission_number'] . ($talent['class_name'] ? ' · ' . $talent['class_name'] : '')) ?></div></td>
                  <td><?= e($talent['talent_name']) ?><?php if ($talent['achievement']): ?><div class="small text-muted"><?= e($talent['achievement']) ?></div><?php endif; ?></td>
                  <td><?= e(ucfirst($talent['talent_type'])) ?></td>
                  <td><?= e(ucfirst($talent['level'])) ?></td>
                  <td class="small"><?= e($talent['award_title'] ?: '—') ?><?php if ($talent['award_date']): ?><div class="text-muted"><?= e(date('d M Y', strtotime($talent['award_date']))) ?></div><?php endif; ?></td>
                  <td class="small"><?= e(date('d M Y', strtotime($talent['date_recorded']))) ?></td>
                </tr>
<?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- House points ----------------------------------------------------------->
  <div class="tab-pane fade <?= $tab === 'houses' ? 'show active' : '' ?>" id="tab-houses" role="tabpanel">
    <div class="card app-card mb-3">
      <div class="card-body d-flex flex-wrap gap-3 align-items-end justify-content-between">
        <form method="get" class="d-flex flex-wrap gap-2 align-items-end" action="<?= url('cocurricular') ?>">
          <input type="hidden" name="tab" value="houses">
          <div><label class="form-label small mb-0" for="h_year">Academic year</label><select class="form-select form-select-sm" id="h_year" name="year_id"><?php foreach ($years as $year): ?><option value="<?= (int)$year['id'] ?>" <?= (int)$year['id'] === $yearId ? 'selected' : '' ?>><?= e($year['name']) ?></option><?php endforeach; ?></select></div>
          <div><label class="form-label small mb-0" for="h_term">Term</label><select class="form-select form-select-sm" id="h_term" name="term_id"><option value="0">All terms</option><?php foreach ($terms as $term): ?><option value="<?= (int)$term['id'] ?>" <?= (int)$term['id'] === $termId ? 'selected' : '' ?>><?= e($term['year_name'] . ' · ' . $term['name']) ?></option><?php endforeach; ?></select></div>
          <button class="btn btn-outline-secondary btn-sm">Apply</button>
        </form>
        <div class="d-flex flex-wrap gap-2">
<?php if (!$houseTotals): ?><span class="text-muted small">No houses registered.</span><?php endif; ?>
<?php foreach ($houseTotals as $standing): ?>
          <div class="border rounded px-3 py-2">
            <div class="small text-muted"><span class="d-inline-block rounded-circle me-1" style="width:.6rem;height:.6rem;background:<?= e($standing['colour'] ?: '#adb5bd') ?>"></span><?= e($standing['name']) ?></div>
            <div class="h5 mb-0"><?= number_format((float)$standing['total'], (fmod((float)$standing['total'], 1.0) === 0.0 ? 0 : 1)) ?></div>
          </div>
<?php endforeach; ?>
        </div>
      </div>
    </div>

    <div class="row g-3">
<?php if ($canAdd): ?>
      <div class="col-xl-4">
        <div class="card app-card">
          <div class="card-header">Award house points</div>
          <div class="card-body">
            <form method="post" action="<?= url('cocurricular/houses/save') ?>">
              <?= csrf_field() ?>
              <div class="mb-2"><label class="form-label">House <span class="text-danger">*</span></label><select class="form-select" name="house_id" required><option value="">Choose…</option><?php foreach ($houses as $house): ?><option value="<?= (int)$house['id'] ?>"><?= e($house['name']) ?></option><?php endforeach; ?></select></div>
              <div class="row g-2 mb-2">
                <div class="col-7"><label class="form-label">Academic year</label><select class="form-select" name="academic_year_id"><?php foreach ($years as $year): ?><option value="<?= (int)$year['id'] ?>" <?= (int)$year['id'] === $yearId ? 'selected' : '' ?>><?= e($year['name']) ?></option><?php endforeach; ?></select></div>
                <div class="col-5"><label class="form-label">Term</label><select class="form-select" name="term_id"><option value="0">—</option><?php foreach ($terms as $term): ?><option value="<?= (int)$term['id'] ?>"><?= e($term['year_name'] . ' · ' . $term['name']) ?></option><?php endforeach; ?></select></div>
              </div>
              <div class="mb-2"><label class="form-label">Event <span class="text-danger">*</span></label><input class="form-control" name="event_name" maxlength="160" required></div>
              <div class="row g-2 mb-2">
                <div class="col-7"><label class="form-label">Category</label><select class="form-select" name="category"><?php foreach (['sports','academics','discipline','cultural','cleanliness','cooking','other'] as $value): ?><option value="<?= $value ?>"><?= e(ucfirst($value)) ?></option><?php endforeach; ?></select></div>
                <div class="col-5"><label class="form-label">Type</label><select class="form-select" name="points_type"><option value="award">Award</option><option value="penalty">Penalty</option></select></div>
              </div>
              <div class="row g-2 mb-2">
                <div class="col-5"><label class="form-label">Points <span class="text-danger">*</span></label><input class="form-control" type="number" min="0.5" step="0.5" name="points" required></div>
                <div class="col-7"><label class="form-label">Date <span class="text-danger">*</span></label><input class="form-control" type="date" name="event_date" value="<?= date('Y-m-d') ?>" required></div>
              </div>
              <div class="mb-3"><label class="form-label">Description</label><input class="form-control" name="description" maxlength="255"></div>
              <button class="btn btn-primary">Record points</button>
            </form>
          </div>
        </div>
      </div>
<?php endif; ?>
      <div class="<?= $canAdd ? 'col-xl-8' : 'col-12' ?>">
        <div class="card app-card">
          <div class="card-header">Points log <span class="badge text-bg-light"><?= count($housePoints) ?></span></div>
          <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0" data-table>
              <thead><tr><th>Date</th><th>House</th><th>Event</th><th>Category</th><th class="text-end">Points</th></tr></thead>
              <tbody>
<?php if (!$housePoints): ?><tr><td colspan="5" class="text-center text-muted py-4">No house points recorded for this period.</td></tr><?php endif; ?>
<?php foreach ($housePoints as $point): ?>
                <tr>
                  <td class="small text-nowrap"><?= e(date('d M Y', strtotime($point['event_date']))) ?></td>
                  <td><strong><?= e($point['house_name']) ?></strong></td>
                  <td><?= e($point['event_name']) ?><?php if ($point['term_name']): ?><div class="small text-muted"><?= e($point['year_name'] . ' · ' . $point['term_name']) ?></div><?php endif; ?></td>
                  <td><?= e(ucfirst($point['category'])) ?></td>
                  <td class="text-end fw-semibold text-<?= $point['points_type'] === 'penalty' ? 'danger' : 'success' ?>"><?= $point['points_type'] === 'penalty' ? '−' : '+' ?><?= rtrim(rtrim(number_format((float)$point['points'], 2), '0'), '.') ?></td>
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

<?php if ($canEdit): ?>
<div class="modal fade" id="fixtureResultModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Fixture result</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
      <form method="post" action="<?= url('cocurricular/fixtures/result') ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="fixture_id" id="r_fixture" value="0">
        <div class="modal-body">
          <p class="mb-3"><strong id="r_name">Fixture</strong></p>
          <div class="row g-2 mb-2">
            <div class="col-4"><label class="form-label" for="r_our">Our score</label><input class="form-control" id="r_our" type="number" min="0" max="999" name="our_score" required></div>
            <div class="col-4"><label class="form-label" for="r_theirs">Opponent</label><input class="form-control" id="r_theirs" type="number" min="0" max="999" name="opponent_score" required></div>
            <div class="col-4"><label class="form-label" for="r_half">Half time</label><input class="form-control" id="r_half" name="half_time_score" maxlength="20" placeholder="e.g. 1-0"></div>
          </div>
          <div class="mb-2"><label class="form-label" for="r_mvp">MVP</label><select class="form-select" id="r_mvp" name="mvp_student_id"><option value="0">—</option><?php foreach ($students as $student): ?><option value="<?= (int)$student['id'] ?>"><?= e($student['last_name'] . ' ' . $student['first_name']) ?></option><?php endforeach; ?></select></div>
          <div class="mb-2"><label class="form-label" for="r_scorers">Scorers</label><textarea class="form-control" id="r_scorers" name="scorers" rows="2"></textarea></div>
          <div class="row g-2 mb-2">
            <div class="col-7"><label class="form-label" for="r_referee">Referee</label><input class="form-control" id="r_referee" name="referee" maxlength="140"></div>
            <div class="col-5"><label class="form-label" for="r_attendance">Attendance</label><input class="form-control" id="r_attendance" type="number" min="0" name="attendance_count"></div>
          </div>
          <div class="mb-2"><label class="form-label" for="r_remarks">Remarks</label><input class="form-control" id="r_remarks" name="remarks" maxlength="500"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-primary" type="submit">Save result</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
document.getElementById('fixtureResultModal')?.addEventListener('show.bs.modal', function (event) {
  var button = event.relatedTarget;
  document.getElementById('r_fixture').value = button.getAttribute('data-fixture-id') || '0';
  document.getElementById('r_name').textContent = button.getAttribute('data-fixture-name') || 'Fixture';
  document.getElementById('r_our').value = button.getAttribute('data-our') || '0';
  document.getElementById('r_theirs').value = button.getAttribute('data-theirs') || '0';
  document.getElementById('r_half').value = button.getAttribute('data-half') || '';
  document.getElementById('r_attendance').value = button.getAttribute('data-attendance') || '';
  document.getElementById('r_referee').value = button.getAttribute('data-referee') || '';
});
</script>
<?php endif; ?>
