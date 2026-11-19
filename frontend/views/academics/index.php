<?php /* Academic management: subjects, class allocations, schemes of work, timetables and homework. */ ?>
<div class="page-head">
  <div>
    <h1 class="h4 mb-0">Academic management</h1>
    <p class="text-muted small mb-0">Manage curricula, subjects, class offerings, teaching assignments, and schemes of work.</p>
  </div>
</div>

<div class="card app-card mb-3">
  <div class="card-body">
    <form method="get" action="<?= url('academics') ?>" class="row g-2 align-items-end">
      <div class="col-md-5">
        <label class="form-label" for="academic_year_filter">Academic year</label>
        <select class="form-select" id="academic_year_filter" name="year_id" data-submit-on-change>
<?php foreach ($years as $item): ?>
          <option value="<?= (int)$item['id'] ?>" <?= $yearId === (int)$item['id'] ? 'selected' : '' ?>>
            <?= e($item['name']) ?><?= (int)$item['is_active'] ? ' (Active)' : '' ?>
          </option>
<?php endforeach; ?>
        </select>
      </div>
<?php if (!$years): ?>
      <div class="col-md-7"><div class="alert alert-warning mb-0">Create an academic year in Administration before allocating subjects or teachers.</div></div>
<?php elseif ($year): ?>
      <div class="col-md-7 small text-muted">Showing allocations and schemes for <strong><?= e($year['name']) ?></strong>.</div>
<?php endif; ?>
    </form>
  </div>
</div>

<div class="row g-3">
  <div class="col-xl-5">
    <div class="card app-card h-100">
      <div class="card-header"><?= $curriculumEdit ? 'Edit curriculum' : 'New curriculum' ?></div>
      <div class="card-body">
        <form method="post" action="<?= url('academics/curricula/save') ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int)($curriculumEdit['id'] ?? 0) ?>">
          <div class="row g-2">
            <div class="col-md-8">
              <label class="form-label" for="curriculum_name">Name</label>
              <input class="form-control" id="curriculum_name" name="name" maxlength="120" required value="<?= e($curriculumEdit['name'] ?? '') ?>" placeholder="Uganda Lower Secondary Curriculum">
            </div>
            <div class="col-md-4">
              <label class="form-label" for="curriculum_code">Code</label>
              <input class="form-control text-uppercase" id="curriculum_code" name="code" maxlength="30" required value="<?= e($curriculumEdit['code'] ?? '') ?>" placeholder="CBC">
            </div>
            <div class="col-md-6">
              <label class="form-label" for="curriculum_level">Level scope</label>
              <select class="form-select" id="curriculum_level" name="level_scope">
<?php foreach (['O-Level', 'A-Level', 'Both'] as $scope): ?>
                <option value="<?= e($scope) ?>" <?= ($curriculumEdit['level_scope'] ?? 'O-Level') === $scope ? 'selected' : '' ?>><?= e($scope) ?></option>
<?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="curriculum_status">Status</label>
              <select class="form-select" id="curriculum_status" name="status">
<?php foreach (['active' => 'Active', 'draft' => 'Draft', 'archived' => 'Archived'] as $value => $label): ?>
                <option value="<?= e($value) ?>" <?= ($curriculumEdit['status'] ?? 'active') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
<?php endforeach; ?>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label" for="curriculum_description">Description</label>
              <textarea class="form-control" id="curriculum_description" name="description" rows="2"><?= e($curriculumEdit['description'] ?? '') ?></textarea>
            </div>
          </div>
          <div class="mt-3 d-flex gap-2">
            <button class="btn btn-primary" type="submit"><?= $curriculumEdit ? 'Save curriculum' : 'Create curriculum' ?></button>
<?php if ($curriculumEdit): ?>
            <a class="btn btn-outline-secondary" href="<?= url('academics') ?>">Cancel</a>
<?php endif; ?>
          </div>
        </form>
      </div>
      <div class="table-responsive border-top">
        <table class="table table-sm table-hover align-middle mb-0">
          <thead><tr><th>Curriculum</th><th>Level</th><th>Status</th><th></th></tr></thead>
          <tbody>
<?php if (!$curricula): ?>
            <tr><td colspan="4" class="text-center text-muted py-3">No curricula configured.</td></tr>
<?php endif; ?>
<?php foreach ($curricula as $item): ?>
            <tr>
              <td><span class="fw-semibold"><?= e($item['name']) ?></span><div class="small text-muted"><?= e($item['code']) ?></div></td>
              <td><?= e($item['level_scope']) ?></td>
              <td><?= e(ucfirst($item['status'])) ?></td>
              <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= url('academics', ['curriculum_id' => (int)$item['id'], 'year_id' => $yearId]) ?>">Edit</a></td>
            </tr>
<?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-xl-7">
    <div class="card app-card h-100">
      <div class="card-header"><?= $subjectEdit ? 'Edit subject' : 'New subject' ?></div>
      <div class="card-body">
        <form method="post" action="<?= url('academics/subjects/save') ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int)($subjectEdit['id'] ?? 0) ?>">
          <div class="row g-2">
            <div class="col-md-3">
              <label class="form-label" for="subject_code">Code</label>
              <input class="form-control text-uppercase" id="subject_code" name="code" maxlength="20" required value="<?= e($subjectEdit['code'] ?? '') ?>" placeholder="MTC">
            </div>
            <div class="col-md-5">
              <label class="form-label" for="subject_name">Subject name</label>
              <input class="form-control" id="subject_name" name="name" maxlength="120" required value="<?= e($subjectEdit['name'] ?? '') ?>" placeholder="Mathematics">
            </div>
            <div class="col-md-4">
              <label class="form-label" for="subject_short_name">Short name</label>
              <input class="form-control" id="subject_short_name" name="short_name" maxlength="30" value="<?= e($subjectEdit['short_name'] ?? '') ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label" for="subject_level">Level</label>
              <select class="form-select" id="subject_level" name="level">
<?php foreach (['O-Level', 'A-Level', 'Both'] as $level): ?>
                <option value="<?= e($level) ?>" <?= ($subjectEdit['level'] ?? 'O-Level') === $level ? 'selected' : '' ?>><?= e($level) ?></option>
<?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="subject_category">Category</label>
              <select class="form-select" id="subject_category" name="category">
<?php foreach (['compulsory', 'elective', 'principal', 'subsidiary'] as $category): ?>
                <option value="<?= e($category) ?>" <?= ($subjectEdit['category'] ?? 'compulsory') === $category ? 'selected' : '' ?>><?= e(ucfirst($category)) ?></option>
<?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="subject_department">Department</label>
              <select class="form-select" id="subject_department" name="department_id">
                <option value="">— None —</option>
<?php foreach ($departments as $department): ?>
                <option value="<?= (int)$department['id'] ?>" <?= (int)($subjectEdit['department_id'] ?? 0) === (int)$department['id'] ? 'selected' : '' ?>><?= e($department['name']) ?></option>
<?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="subject_uneb">UNEB code</label>
              <input class="form-control" id="subject_uneb" name="uneb_subject_code" maxlength="20" value="<?= e($subjectEdit['uneb_subject_code'] ?? '') ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label" for="subject_credits">Credit points</label>
              <input class="form-control" id="subject_credits" name="credit_points" type="number" min="0" max="999.9" step="0.1" value="<?= e($subjectEdit['credit_points'] ?? '') ?>">
            </div>
            <div class="col-md-2">
              <label class="form-label" for="subject_order">Order</label>
              <input class="form-control" id="subject_order" name="order_index" type="number" min="0" max="32767" value="<?= e($subjectEdit['order_index'] ?? '0') ?>">
            </div>
            <div class="col-md-3 d-flex align-items-end gap-3 pb-2">
              <div class="form-check">
                <input class="form-check-input" id="subject_composite" name="is_composite" type="checkbox" <?= (int)($subjectEdit['is_composite'] ?? 0) ? 'checked' : '' ?>>
                <label class="form-check-label" for="subject_composite">Composite</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" id="subject_active" name="is_active" type="checkbox" <?= !$subjectEdit || (int)$subjectEdit['is_active'] ? 'checked' : '' ?>>
                <label class="form-check-label" for="subject_active">Active</label>
              </div>
            </div>
          </div>
          <div class="mt-3 d-flex gap-2">
            <button class="btn btn-primary" type="submit"><?= $subjectEdit ? 'Save subject' : 'Create subject' ?></button>
<?php if ($subjectEdit): ?>
            <a class="btn btn-outline-secondary" href="<?= url('academics', ['year_id' => $yearId]) ?>">Cancel</a>
<?php endif; ?>
          </div>
        </form>
      </div>
      <div class="table-responsive border-top">
        <table class="table table-sm table-hover align-middle mb-0">
          <thead><tr><th>Code</th><th>Subject</th><th>Level / category</th><th>Status</th><th></th></tr></thead>
          <tbody>
<?php if (!$subjects): ?>
            <tr><td colspan="5" class="text-center text-muted py-3">No subjects configured.</td></tr>
<?php endif; ?>
<?php foreach ($subjects as $item): ?>
            <tr>
              <td><?= e($item['code']) ?></td>
              <td><?= e($item['name']) ?><div class="small text-muted"><?= e($item['department_name'] ?? '') ?></div></td>
              <td><?= e($item['level']) ?><div class="small text-muted"><?= e(ucfirst($item['category'])) ?></div></td>
              <td><?= (int)$item['is_active'] ? 'Active' : 'Inactive' ?></td>
              <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= url('academics', ['subject_id' => (int)$item['id'], 'year_id' => $yearId]) ?>">Edit</a></td>
            </tr>
<?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<div class="card app-card mt-3">
  <div class="card-header">Subject to class allocation</div>
  <div class="card-body">
<?php if (!$year): ?>
    <p class="text-muted mb-0">Set up an academic year to allocate subjects to classes.</p>
<?php elseif (!$classes || !$subjects): ?>
    <p class="text-muted mb-0">Add active classes and subjects before creating allocations.</p>
<?php else: ?>
    <form method="post" action="<?= url('academics/allocations/subjects/save') ?>" class="row g-2 align-items-end">
      <?= csrf_field() ?>
      <input type="hidden" name="academic_year_id" value="<?= $yearId ?>">
      <div class="col-md-3">
        <label class="form-label" for="allocation_subject">Subject</label>
        <select class="form-select" id="allocation_subject" name="subject_id" required>
<?php foreach ($subjects as $item): if (!(int)$item['is_active']) continue; ?>
          <option value="<?= (int)$item['id'] ?>"><?= e($item['code'] . ' — ' . $item['name']) ?></option>
<?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label" for="allocation_class">Class</label>
        <select class="form-select" id="allocation_class" name="class_id" required>
<?php foreach ($classes as $item): ?>
          <option value="<?= (int)$item['id'] ?>"><?= e($item['name']) ?></option>
<?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label" for="allocation_stream">Stream</label>
        <select class="form-select" id="allocation_stream" name="stream_id">
          <option value="">All streams / class-wide</option>
<?php foreach ($streams as $item): ?>
          <option value="<?= (int)$item['id'] ?>"><?= e($item['class_name'] . ' — ' . $item['stream_name']) ?></option>
<?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label" for="allocation_term">Term (optional)</label>
        <select class="form-select" id="allocation_term" name="term_id">
          <option value="">All terms in <?= e($year['name']) ?></option>
<?php foreach ($terms as $term): ?>
          <option value="<?= (int)$term['id'] ?>"><?= e($term['name']) ?></option>
<?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <div class="form-check mb-2">
          <input class="form-check-input" id="allocation_compulsory" name="is_compulsory" type="checkbox" checked>
          <label class="form-check-label" for="allocation_compulsory">Compulsory</label>
        </div>
        <button class="btn btn-primary w-100" type="submit">Allocate subject</button>
      </div>
    </form>
<?php endif; ?>
  </div>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead><tr><th>Class / stream</th><th>Subject</th><th>Term</th><th>Type</th><th>Status</th><th></th></tr></thead>
      <tbody>
<?php if (!$subjectAllocations): ?>
        <tr><td colspan="6" class="text-center text-muted py-3">No subject allocations for this year.</td></tr>
<?php endif; ?>
<?php foreach ($subjectAllocations as $item): ?>
        <tr>
          <td><?= e($item['class_name']) ?><?= $item['stream_name'] ? ' — ' . e($item['stream_name']) : ' — All streams' ?></td>
          <td><?= e($item['subject_code'] . ' — ' . $item['subject_name']) ?></td>
          <td><?= e($item['term_name'] ?: 'All year') ?></td>
          <td><?= (int)$item['is_compulsory'] ? 'Compulsory' : 'Elective' ?></td>
          <td><?= (int)$item['is_active'] ? 'Active' : 'Inactive' ?></td>
          <td class="text-end">
<?php if ((int)$item['is_active']): ?>
            <form method="post" action="<?= url('academics/allocations/subjects/deactivate') ?>" class="d-inline" data-confirm="Deactivate this subject allocation?">
              <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
              <button class="btn btn-sm btn-outline-warning" type="submit">Deactivate</button>
            </form>
<?php endif; ?>
          </td>
        </tr>
<?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card app-card mt-3">
  <div class="card-header">Teacher allocation</div>
  <div class="card-body">
<?php if (!$year): ?>
    <p class="text-muted mb-0">Set up an academic year before allocating teachers.</p>
<?php elseif (!$staff || !$subjectAllocations): ?>
    <p class="text-muted mb-0">Add active staff and subject/class allocations before assigning teachers.</p>
<?php else: ?>
    <form method="post" action="<?= url('academics/allocations/teachers/save') ?>" class="row g-2 align-items-end">
      <?= csrf_field() ?>
      <input type="hidden" name="academic_year_id" value="<?= $yearId ?>">
      <div class="col-md-2">
        <label class="form-label" for="teacher_staff">Teacher</label>
        <select class="form-select" id="teacher_staff" name="staff_id" required>
<?php foreach ($staff as $item): ?>
          <option value="<?= (int)$item['id'] ?>"><?= e(trim($item['first_name'] . ' ' . $item['last_name'])) ?></option>
<?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label" for="teacher_subject">Subject</label>
        <select class="form-select" id="teacher_subject" name="subject_id" required>
<?php foreach ($subjects as $item): if (!(int)$item['is_active']) continue; ?>
          <option value="<?= (int)$item['id'] ?>"><?= e($item['code'] . ' — ' . $item['name']) ?></option>
<?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label" for="teacher_class">Class</label>
        <select class="form-select" id="teacher_class" name="class_id" required>
<?php foreach ($classes as $item): ?>
          <option value="<?= (int)$item['id'] ?>"><?= e($item['name']) ?></option>
<?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label" for="teacher_stream">Stream</label>
        <select class="form-select" id="teacher_stream" name="stream_id">
          <option value="">All streams / class-wide</option>
<?php foreach ($streams as $item): ?>
          <option value="<?= (int)$item['id'] ?>"><?= e($item['class_name'] . ' — ' . $item['stream_name']) ?></option>
<?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label" for="teacher_term">Term (optional)</label>
        <select class="form-select" id="teacher_term" name="term_id">
          <option value="">All terms in <?= e($year['name']) ?></option>
<?php foreach ($terms as $term): ?>
          <option value="<?= (int)$term['id'] ?>"><?= e($term['name']) ?></option>
<?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-1">
        <label class="form-label" for="teacher_lessons">Lessons/wk</label>
        <input class="form-control" id="teacher_lessons" name="lessons_per_week" type="number" min="1" max="60" value="1" required>
      </div>
      <div class="col-12 d-flex align-items-center gap-3">
        <div class="form-check">
          <input class="form-check-input" id="teacher_primary" name="is_primary" type="checkbox" checked>
          <label class="form-check-label" for="teacher_primary">Primary teacher</label>
        </div>
        <button class="btn btn-primary" type="submit">Allocate teacher</button>
      </div>
    </form>
<?php endif; ?>
  </div>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead><tr><th>Teacher</th><th>Class / stream</th><th>Subject</th><th>Term</th><th>Lessons/wk</th><th>Role</th><th></th></tr></thead>
      <tbody>
<?php if (!$teacherAllocations): ?>
        <tr><td colspan="7" class="text-center text-muted py-3">No teacher allocations for this year.</td></tr>
<?php endif; ?>
<?php foreach ($teacherAllocations as $item): ?>
        <tr>
          <td><?= e($item['teacher_name']) ?></td>
          <td><?= e($item['class_name']) ?><?= $item['stream_name'] ? ' — ' . e($item['stream_name']) : ' — All streams' ?></td>
          <td><?= e($item['subject_code'] . ' — ' . $item['subject_name']) ?></td>
          <td><?= e($item['term_name'] ?: 'All year') ?></td>
          <td><?= (int)$item['lessons_per_week'] ?></td>
          <td><?= (int)$item['is_primary'] ? 'Primary' : 'Assistant' ?></td>
          <td class="text-end">
            <form method="post" action="<?= url('academics/allocations/teachers/remove') ?>" class="d-inline" data-confirm="Remove this teacher allocation?">
              <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
              <button class="btn btn-sm btn-outline-danger" type="submit">Remove</button>
            </form>
          </td>
        </tr>
<?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card app-card mt-3">
  <div class="card-header"><?= $schemeEdit ? 'Edit scheme of work' : 'Scheme of work' ?></div>
  <div class="card-body">
<?php if (!$year): ?>
    <p class="text-muted mb-0">Set up an academic year before planning teaching weeks.</p>
<?php elseif (!$terms || !$staff || !$teacherAllocations): ?>
    <p class="text-muted mb-0">Set up terms and teacher allocations before drafting a scheme of work.</p>
<?php else: ?>
    <form method="post" action="<?= url('academics/schemes/save') ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= (int)($schemeEdit['id'] ?? 0) ?>">
      <input type="hidden" name="academic_year_id" value="<?= $yearId ?>">
      <div class="row g-2">
        <div class="col-md-3">
          <label class="form-label" for="scheme_staff">Teacher</label>
          <select class="form-select" id="scheme_staff" name="staff_id" required>
<?php foreach ($staff as $item): ?>
            <option value="<?= (int)$item['id'] ?>" <?= (int)($schemeEdit['staff_id'] ?? 0) === (int)$item['id'] ? 'selected' : '' ?>><?= e(trim($item['first_name'] . ' ' . $item['last_name'])) ?></option>
<?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label" for="scheme_subject">Subject</label>
          <select class="form-select" id="scheme_subject" name="subject_id" required>
<?php foreach ($subjects as $item): if (!(int)$item['is_active']) continue; ?>
            <option value="<?= (int)$item['id'] ?>" <?= (int)($schemeEdit['subject_id'] ?? 0) === (int)$item['id'] ? 'selected' : '' ?>><?= e($item['code'] . ' — ' . $item['name']) ?></option>
<?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label" for="scheme_class">Class</label>
          <select class="form-select" id="scheme_class" name="class_id" required>
<?php foreach ($classes as $item): ?>
            <option value="<?= (int)$item['id'] ?>" <?= (int)($schemeEdit['class_id'] ?? 0) === (int)$item['id'] ? 'selected' : '' ?>><?= e($item['name']) ?></option>
<?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label" for="scheme_stream">Stream</label>
          <select class="form-select" id="scheme_stream" name="stream_id">
            <option value="">All streams / class-wide</option>
<?php foreach ($streams as $item): ?>
            <option value="<?= (int)$item['id'] ?>" <?= (int)($schemeEdit['stream_id'] ?? 0) === (int)$item['id'] ? 'selected' : '' ?>><?= e($item['class_name'] . ' — ' . $item['stream_name']) ?></option>
<?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label" for="scheme_term">Term</label>
          <select class="form-select" id="scheme_term" name="term_id" required>
<?php foreach ($terms as $term): ?>
            <option value="<?= (int)$term['id'] ?>" <?= (int)($schemeEdit['term_id'] ?? 0) === (int)$term['id'] ? 'selected' : '' ?>><?= e($term['name']) ?></option>
<?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label" for="scheme_week">Week number</label>
          <input class="form-control" id="scheme_week" name="week_number" type="number" min="1" max="53" required value="<?= e($schemeEdit['week_number'] ?? '1') ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label" for="scheme_week_start">Week starting</label>
          <input class="form-control" id="scheme_week_start" name="week_starting" type="date" value="<?= e($schemeEdit['week_starting'] ?? '') ?>">
        </div>
        <div class="col-md-7">
          <label class="form-label" for="scheme_topic">Topic</label>
          <input class="form-control" id="scheme_topic" name="topic" maxlength="180" required value="<?= e($schemeEdit['topic'] ?? '') ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label" for="scheme_subtopics">Sub-topics</label>
          <textarea class="form-control" id="scheme_subtopics" name="sub_topics" rows="2"><?= e($schemeEdit['sub_topics'] ?? '') ?></textarea>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="scheme_objectives">Learning objectives</label>
          <textarea class="form-control" id="scheme_objectives" name="objectives" rows="2"><?= e($schemeEdit['objectives'] ?? '') ?></textarea>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="scheme_experiences">Learning experiences</label>
          <textarea class="form-control" id="scheme_experiences" name="learning_experiences" rows="2"><?= e($schemeEdit['learning_experiences'] ?? '') ?></textarea>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="scheme_assessment">Assessment</label>
          <textarea class="form-control" id="scheme_assessment" name="assessment" rows="2"><?= e($schemeEdit['assessment'] ?? '') ?></textarea>
        </div>
        <div class="col-md-4">
          <label class="form-label" for="scheme_methods">Teaching methods</label>
          <input class="form-control" id="scheme_methods" name="teaching_methods" maxlength="255" value="<?= e($schemeEdit['teaching_methods'] ?? '') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label" for="scheme_materials">Learning materials</label>
          <input class="form-control" id="scheme_materials" name="learning_materials" maxlength="255" value="<?= e($schemeEdit['learning_materials'] ?? '') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label" for="scheme_remarks">Remarks</label>
          <input class="form-control" id="scheme_remarks" name="remarks" maxlength="255" value="<?= e($schemeEdit['remarks'] ?? '') ?>">
        </div>
      </div>
      <div class="mt-3 d-flex gap-2">
        <button class="btn btn-outline-primary" name="intent" value="draft" type="submit">Save draft</button>
        <button class="btn btn-primary" name="intent" value="submit" type="submit">Submit for review</button>
<?php if ($schemeEdit): ?>
        <a class="btn btn-outline-secondary" href="<?= url('academics', ['year_id' => $yearId]) ?>">Cancel</a>
<?php endif; ?>
      </div>
    </form>
<?php endif; ?>
  </div>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead><tr><th>Week / term</th><th>Teacher</th><th>Class</th><th>Subject / topic</th><th>Status</th><th></th></tr></thead>
      <tbody>
<?php if (!$schemes): ?>
        <tr><td colspan="6" class="text-center text-muted py-3">No schemes of work for this year.</td></tr>
<?php endif; ?>
<?php foreach ($schemes as $item): ?>
        <tr>
          <td>Week <?= (int)$item['week_number'] ?><div class="small text-muted"><?= e($item['term_name']) ?></div></td>
          <td><?= e($item['teacher_name']) ?></td>
          <td><?= e($item['class_name']) ?><?= $item['stream_name'] ? ' — ' . e($item['stream_name']) : '' ?></td>
          <td><?= e($item['subject_code'] . ' — ' . $item['subject_name']) ?><div class="small text-muted"><?= e($item['topic']) ?></div></td>
          <td><?= e(ucfirst($item['status'])) ?></td>
          <td class="text-end">
<?php if (in_array($item['status'], ['draft', 'returned'], true)): ?>
            <a class="btn btn-sm btn-outline-primary" href="<?= url('academics', ['year_id' => $yearId, 'scheme_id' => (int)$item['id']]) ?>">Edit</a>
<?php endif; ?>
          </td>
        </tr>
<?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="row g-3 mt-1">
  <div class="col-xl-4">
    <div class="card app-card h-100">
      <div class="card-header">New timetable</div>
      <div class="card-body">
<?php if (!$year): ?>
        <p class="text-muted mb-0">Set up an academic year before creating timetables.</p>
<?php elseif (!$classes || !$terms): ?>
        <p class="text-muted mb-0">Add active classes and terms before creating a timetable.</p>
<?php else: ?>
        <form method="post" action="<?= url('academics/timetables/save') ?>" data-ajax>
          <?= csrf_field() ?>
          <input type="hidden" name="academic_year_id" value="<?= $yearId ?>">
          <div class="row g-2">
            <div class="col-12">
              <label class="form-label" for="timetable_name">Timetable name</label>
              <input class="form-control" id="timetable_name" name="name" maxlength="120" required placeholder="S.1 Blue — Term 1">
              <span class="invalid-feedback d-block" data-error-for="name"></span>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="timetable_class">Class</label>
              <select class="form-select" id="timetable_class" name="class_id" required>
<?php foreach ($classes as $item): ?>
                <option value="<?= (int)$item['id'] ?>"><?= e($item['name']) ?></option>
<?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="timetable_stream">Stream</label>
              <select class="form-select" id="timetable_stream" name="stream_id">
                <option value="">All streams / class-wide</option>
<?php foreach ($streams as $item): ?>
                <option value="<?= (int)$item['id'] ?>"><?= e($item['class_name'] . ' — ' . $item['stream_name']) ?></option>
<?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-7">
              <label class="form-label" for="timetable_term">Term</label>
              <select class="form-select" id="timetable_term" name="term_id" required>
<?php foreach ($terms as $term): ?>
                <option value="<?= (int)$term['id'] ?>"><?= e($term['name']) ?></option>
<?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-5">
              <label class="form-label" for="timetable_status">Status</label>
              <select class="form-select" id="timetable_status" name="status">
                <option value="draft">Draft</option>
                <option value="published">Published</option>
              </select>
            </div>
          </div>
          <button class="btn btn-primary mt-3" type="submit">Create timetable</button>
        </form>
<?php endif; ?>
      </div>
      <div class="table-responsive border-top">
        <table class="table table-sm table-hover align-middle mb-0">
          <thead><tr><th>Timetable</th><th>Term</th><th>Status</th><th></th></tr></thead>
          <tbody>
<?php if (!$timetables): ?>
            <tr><td colspan="4" class="text-center text-muted py-3">No timetables for this year.</td></tr>
<?php endif; ?>
<?php foreach ($timetables as $item): ?>
            <tr>
              <td><span class="fw-semibold"><?= e($item['name']) ?></span><div class="small text-muted"><?= e($item['class_name']) ?><?= $item['stream_name'] ? ' — ' . e($item['stream_name']) : '' ?></div></td>
              <td><?= e($item['term_name']) ?></td>
              <td><span class="badge text-bg-<?= $item['status'] === 'published' ? 'success' : 'secondary' ?>"><?= e(ucfirst($item['status'])) ?></span></td>
              <td class="text-end">
                <a class="btn btn-sm btn-outline-primary" href="<?= url('academics', ['year_id' => $yearId, 'timetable_id' => (int)$item['id']]) ?>">Open</a>
                <form method="post" action="<?= url('academics/timetables/delete') ?>" data-ajax data-confirm="Delete this timetable and all of its slots?" class="d-inline">
                  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                  <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                </form>
              </td>
            </tr>
<?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-xl-4">
    <div class="card app-card h-100">
      <div class="card-header">Add lesson slot<?= $timetable ? ' — ' . e($timetable['name']) : '' ?></div>
      <div class="card-body">
<?php if (!$timetable): ?>
        <p class="text-muted mb-0">Create a timetable to schedule weekly lesson slots.</p>
<?php elseif (!$subjects || !$staff): ?>
        <p class="text-muted mb-0">Add active subjects and staff before scheduling slots.</p>
<?php else: ?>
        <form method="post" action="<?= url('academics/timetables/slots/save') ?>" data-ajax>
          <?= csrf_field() ?>
          <input type="hidden" name="timetable_id" value="<?= (int)$timetable['id'] ?>">
          <div class="row g-2">
            <div class="col-md-6">
              <label class="form-label" for="slot_day">Day</label>
              <select class="form-select" id="slot_day" name="day_of_week" required>
<?php foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'] as $day): ?>
                <option value="<?= e($day) ?>"><?= e($day) ?></option>
<?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label" for="slot_period">Period</label>
              <input class="form-control" id="slot_period" name="period_number" type="number" min="1" max="30" value="1" required>
              <span class="invalid-feedback d-block" data-error-for="period_number"></span>
            </div>
            <div class="col-md-3">
              <label class="form-label" for="slot_room">Room</label>
              <select class="form-select" id="slot_room" name="room_id">
                <option value="">— None —</option>
<?php foreach ($rooms as $item): ?>
                <option value="<?= (int)$item['id'] ?>"><?= e($item['name']) ?></option>
<?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="slot_start">Start time</label>
              <input class="form-control" id="slot_start" name="start_time" type="time" value="08:00" required>
              <span class="invalid-feedback d-block" data-error-for="start_time"></span>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="slot_end">End time</label>
              <input class="form-control" id="slot_end" name="end_time" type="time" value="08:40" required>
            </div>
            <div class="col-12">
              <label class="form-label" for="slot_subject">Subject</label>
              <select class="form-select" id="slot_subject" name="subject_id" required>
<?php foreach ($subjects as $item): if (!(int)$item['is_active']) continue; ?>
                <option value="<?= (int)$item['id'] ?>"><?= e($item['code'] . ' — ' . $item['name']) ?></option>
<?php endforeach; ?>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label" for="slot_staff">Teacher</label>
              <select class="form-select" id="slot_staff" name="staff_id" required>
<?php foreach ($staff as $item): ?>
                <option value="<?= (int)$item['id'] ?>"><?= e(trim($item['first_name'] . ' ' . $item['last_name'])) ?></option>
<?php endforeach; ?>
              </select>
              <span class="invalid-feedback d-block" data-error-for="staff_id"></span>
            </div>
            <div class="col-12">
              <label class="form-label" for="slot_notes">Notes</label>
              <input class="form-control" id="slot_notes" name="notes" maxlength="160">
            </div>
          </div>
          <button class="btn btn-primary mt-3" type="submit">Add slot</button>
        </form>
<?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-xl-4">
    <div class="card app-card h-100">
      <div class="card-header">Scheduled slots <span class="badge text-bg-light"><?= count($timetableSlots) ?></span></div>
      <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0" data-table>
          <thead><tr><th>Day / period</th><th>Time</th><th>Subject</th><th>Teacher</th><th></th></tr></thead>
          <tbody>
<?php if (!$timetableSlots): ?>
            <tr><td colspan="5" class="text-center text-muted py-3">No slots scheduled yet.</td></tr>
<?php endif; ?>
<?php foreach ($timetableSlots as $slot): ?>
            <tr>
              <td><?= e(substr($slot['day_of_week'], 0, 3)) ?> · P<?= (int)$slot['period_number'] ?></td>
              <td class="small"><?= e(substr((string)$slot['start_time'], 0, 5)) ?>–<?= e(substr((string)$slot['end_time'], 0, 5)) ?></td>
              <td><?= e($slot['subject_short_name'] ?: $slot['subject_code']) ?><div class="small text-muted"><?= e($slot['room_name'] ?? '') ?></div></td>
              <td><?= e($slot['teacher_name']) ?></td>
              <td class="text-end">
                <form method="post" action="<?= url('academics/timetables/slots/delete') ?>" data-ajax data-confirm="Remove this timetable slot?">
                  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$slot['id'] ?>">
                  <button class="btn btn-sm btn-outline-danger" type="submit">Remove</button>
                </form>
              </td>
            </tr>
<?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-12">
    <div class="card app-card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span>Class timetable<?= $timetable ? ' — ' . e($timetable['name']) : '' ?></span>
        <form method="get" action="<?= url('academics') ?>" class="d-flex gap-2">
          <input type="hidden" name="year_id" value="<?= $yearId ?>">
          <select class="form-select form-select-sm" name="timetable_id" data-submit-on-change style="width:auto" aria-label="Choose timetable">
<?php if (!$timetables): ?>
            <option value="0">No timetables</option>
<?php endif; ?>
<?php foreach ($timetables as $item): ?>
            <option value="<?= (int)$item['id'] ?>" <?= $timetableId === (int)$item['id'] ? 'selected' : '' ?>><?= e($item['name']) ?></option>
<?php endforeach; ?>
          </select>
        </form>
      </div>
<?php if (!$timetable): ?>
      <div class="card-body text-muted">Create a timetable to view the weekly class grid.</div>
<?php else: ?>
<?php
$gridDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
$gridSlots = [];
$periodTimes = [];
foreach ($timetableSlots as $slot) {
    $periodNo = (int)$slot['period_number'];
    $gridSlots[$slot['day_of_week']][$periodNo] = $slot;
    if (!isset($periodTimes[$periodNo])) {
        $periodTimes[$periodNo] = substr((string)$slot['start_time'], 0, 5) . '–' . substr((string)$slot['end_time'], 0, 5);
    }
}
ksort($periodTimes);
?>
      <div class="table-responsive">
        <table class="table table-bordered table-sm align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th style="width:110px">Period</th>
              <th style="width:95px">Time</th>
<?php foreach ($gridDays as $day): ?>
              <th><?= e($day) ?></th>
<?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
<?php if (!$periodTimes): ?>
            <tr><td colspan="<?= 2 + count($gridDays) ?>" class="text-center text-muted py-3">No lessons scheduled in this timetable yet.</td></tr>
<?php endif; ?>
<?php foreach ($periodTimes as $periodNo => $time): ?>
            <tr>
              <td class="fw-semibold">Period <?= (int)$periodNo ?></td>
              <td class="small text-muted"><?= e($time) ?></td>
<?php foreach ($gridDays as $day): $cell = $gridSlots[$day][$periodNo] ?? null; ?>
              <td class="small">
<?php if ($cell): ?>
                <span class="fw-semibold"><?= e($cell['subject_short_name'] ?: $cell['subject_code']) ?></span>
                <div class="text-muted"><?= e($cell['teacher_name']) ?></div>
                <div class="text-muted"><?= e(substr((string)$cell['start_time'], 0, 5)) ?>–<?= e(substr((string)$cell['end_time'], 0, 5)) ?><?= $cell['room_name'] ? ' · ' . e($cell['room_name']) : '' ?></div>
<?php else: ?>
                <span class="text-muted">—</span>
<?php endif; ?>
              </td>
<?php endforeach; ?>
            </tr>
<?php endforeach; ?>
          </tbody>
        </table>
      </div>
<?php endif; ?>
    </div>
  </div>
</div>

<div class="row g-3 mt-1">
  <div class="col-xl-5">
    <div class="card app-card h-100">
      <div class="card-header">Assign homework</div>
      <div class="card-body">
<?php if (!$year): ?>
        <p class="text-muted mb-0">Set up an academic year before assigning homework.</p>
<?php elseif (!$staff || !$subjects || !$classes || !$terms): ?>
        <p class="text-muted mb-0">Add staff, subjects, classes, and terms before assigning homework.</p>
<?php else: ?>
        <form method="post" action="<?= url('academics/homework/save') ?>" data-ajax>
          <?= csrf_field() ?>
          <input type="hidden" name="academic_year_id" value="<?= $yearId ?>">
          <div class="row g-2">
            <div class="col-md-6">
              <label class="form-label" for="homework_staff">Teacher</label>
              <select class="form-select" id="homework_staff" name="staff_id" required>
<?php foreach ($staff as $item): ?>
                <option value="<?= (int)$item['id'] ?>"><?= e(trim($item['first_name'] . ' ' . $item['last_name'])) ?></option>
<?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="homework_subject">Subject</label>
              <select class="form-select" id="homework_subject" name="subject_id" required>
<?php foreach ($subjects as $item): if (!(int)$item['is_active']) continue; ?>
                <option value="<?= (int)$item['id'] ?>"><?= e($item['code'] . ' — ' . $item['name']) ?></option>
<?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="homework_class">Class</label>
              <select class="form-select" id="homework_class" name="class_id" required>
<?php foreach ($classes as $item): ?>
                <option value="<?= (int)$item['id'] ?>"><?= e($item['name']) ?></option>
<?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="homework_stream">Stream</label>
              <select class="form-select" id="homework_stream" name="stream_id">
                <option value="">All streams / class-wide</option>
<?php foreach ($streams as $item): ?>
                <option value="<?= (int)$item['id'] ?>"><?= e($item['class_name'] . ' — ' . $item['stream_name']) ?></option>
<?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="homework_term">Term</label>
              <select class="form-select" id="homework_term" name="term_id" required>
<?php foreach ($terms as $term): ?>
                <option value="<?= (int)$term['id'] ?>"><?= e($term['name']) ?></option>
<?php endforeach; ?>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label" for="homework_title">Title</label>
              <input class="form-control" id="homework_title" name="title" maxlength="180" required placeholder="Algebra exercises 3">
              <span class="invalid-feedback d-block" data-error-for="title"></span>
            </div>
            <div class="col-12">
              <label class="form-label" for="homework_description">Description</label>
              <textarea class="form-control" id="homework_description" name="description" rows="2"></textarea>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="homework_assigned">Assigned date</label>
              <input class="form-control" id="homework_assigned" name="assigned_date" type="date" value="<?= e(date('Y-m-d')) ?>" required>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="homework_due">Due date</label>
              <input class="form-control" id="homework_due" name="due_date" type="date" value="<?= e(date('Y-m-d', strtotime('+7 days'))) ?>" required>
              <span class="invalid-feedback d-block" data-error-for="due_date"></span>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="homework_marks">Total marks</label>
              <input class="form-control" id="homework_marks" name="total_marks" type="number" min="0" max="999999.99" step="0.01" placeholder="Optional">
            </div>
            <div class="col-12 d-flex align-items-center gap-3">
              <select class="form-select" name="status" style="width:auto" aria-label="Homework status">
                <option value="published">Published</option>
                <option value="draft">Draft</option>
              </select>
              <button class="btn btn-primary" type="submit">Assign homework</button>
            </div>
          </div>
        </form>
<?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-xl-7">
    <div class="card app-card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span>Homework<?php if ($homeworkClassId): foreach ($classes as $filterClass): if ((int)$filterClass['id'] !== $homeworkClassId) continue; ?> — <?= e($filterClass['name']) ?><?php break; endforeach; endif; ?></span>
        <form method="get" action="<?= url('academics') ?>" class="d-flex gap-2">
          <input type="hidden" name="year_id" value="<?= $yearId ?>">
          <select class="form-select form-select-sm" name="homework_class_id" data-submit-on-change style="width:auto" aria-label="Filter homework by class">
            <option value="0">All classes</option>
<?php foreach ($classes as $item): ?>
            <option value="<?= (int)$item['id'] ?>" <?= $homeworkClassId === (int)$item['id'] ? 'selected' : '' ?>><?= e($item['name']) ?></option>
<?php endforeach; ?>
          </select>
        </form>
      </div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" data-table>
          <thead><tr><th>Homework</th><th>Class</th><th>Teacher</th><th>Due</th><th>Submitted</th><th>Status</th><th></th></tr></thead>
          <tbody>
<?php if (!$homeworkList): ?>
            <tr><td colspan="7" class="text-center text-muted py-3">No homework recorded for this year.</td></tr>
<?php endif; ?>
<?php foreach ($homeworkList as $item): ?>
            <tr>
              <td><span class="fw-semibold"><?= e($item['title']) ?></span><div class="small text-muted"><?= e($item['subject_code'] . ' — ' . $item['subject_name']) ?></div></td>
              <td><?= e($item['class_name']) ?><?= $item['stream_name'] ? ' — ' . e($item['stream_name']) : '' ?><div class="small text-muted"><?= e($item['term_name']) ?></div></td>
              <td><?= e($item['teacher_name']) ?></td>
              <td class="small"><?= e($item['due_date']) ?></td>
              <td><span class="badge text-bg-light"><?= (int)$item['submitted_count'] ?> / <?= (int)$item['student_count'] ?></span></td>
              <td><span class="badge text-bg-<?= $item['status'] === 'published' ? 'success' : ($item['status'] === 'draft' ? 'secondary' : 'warning') ?>"><?= e(ucfirst($item['status'])) ?></span></td>
              <td class="text-end">
                <a class="btn btn-sm btn-outline-primary" href="<?= url('academics', ['year_id' => $yearId, 'homework_class_id' => $homeworkClassId, 'homework_id' => (int)$item['id']]) ?>">Roster</a>
              </td>
            </tr>
<?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

<?php if ($homework): ?>
  <div class="col-12">
    <div class="card app-card">
      <div class="card-header">Submission roster — <?= e($homework['title']) ?> · <?= e($homework['class_name']) ?><?= $homework['stream_name'] ? ' — ' . e($homework['stream_name']) : '' ?> · due <?= e($homework['due_date']) ?></div>
      <form method="post" action="<?= url('academics/homework/submissions/save') ?>" data-ajax>
        <?= csrf_field() ?>
        <input type="hidden" name="homework_id" value="<?= (int)$homework['id'] ?>">
        <div class="table-responsive">
          <table class="table table-sm table-hover align-middle mb-0" data-table>
            <thead><tr><th>Learner</th><th>Admission no.</th><th>Status</th><th>Submitted</th></tr></thead>
            <tbody>
<?php if (!$homeworkRoster): ?>
              <tr><td colspan="4" class="text-center text-muted py-3">No active learners in this class.</td></tr>
<?php endif; ?>
<?php foreach ($homeworkRoster as $student): $subStatus = $student['submission_status'] ?? 'pending'; ?>
              <tr>
                <td><?= e($student['last_name'] . ' ' . $student['first_name']) ?></td>
                <td class="small"><?= e($student['admission_number']) ?></td>
                <td><span class="badge text-bg-<?= in_array($subStatus, ['submitted', 'marked'], true) ? 'success' : ($subStatus === 'late' ? 'warning' : ($subStatus === 'pending' ? 'secondary' : 'info')) ?>"><?= e(ucfirst(str_replace('_', ' ', $subStatus))) ?></span></td>
                <td class="text-center">
                  <input class="form-check-input" type="checkbox" name="submitted[]" value="<?= (int)$student['id'] ?>" <?= in_array($subStatus, ['submitted', 'late', 'marked', 'returned'], true) ? 'checked' : '' ?>>
                </td>
              </tr>
<?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="card-body border-top">
          <button class="btn btn-primary" <?= !$homeworkRoster ? 'disabled' : '' ?>>Save submission status</button>
          <a class="btn btn-outline-secondary" href="<?= url('academics', ['year_id' => $yearId, 'homework_class_id' => $homeworkClassId]) ?>">Close roster</a>
        </div>
      </form>
    </div>
  </div>
<?php endif; ?>
</div>

<div class="row g-3 mt-1">
  <div class="col-xl-4">
    <div class="card app-card h-100">
      <div class="card-header">Add academic event</div>
      <div class="card-body">
        <form method="post" action="<?= url('academics/events/save') ?>" data-ajax>
          <?= csrf_field() ?>
          <input type="hidden" name="academic_year_id" value="<?= $yearId ?>">
          <div class="row g-2">
            <div class="col-12">
              <label class="form-label" for="event_title">Title</label>
              <input class="form-control" id="event_title" name="title" maxlength="160" required placeholder="Term 1 opening">
              <span class="invalid-feedback d-block" data-error-for="title"></span>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="event_type">Type</label>
              <select class="form-select" id="event_type" name="event_type">
<?php foreach (['term_start' => 'Term start', 'term_end' => 'Term end', 'examination' => 'Examination', 'holiday' => 'Holiday', 'event' => 'Event', 'deadline' => 'Deadline', 'meeting' => 'Meeting', 'sports' => 'Sports', 'visiting_day' => 'Visiting day'] as $value => $label): ?>
                <option value="<?= e($value) ?>"><?= e($label) ?></option>
<?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="event_term">Term (optional)</label>
              <select class="form-select" id="event_term" name="term_id">
                <option value="">— None —</option>
<?php foreach ($terms as $term): ?>
                <option value="<?= (int)$term['id'] ?>"><?= e($term['name']) ?></option>
<?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="event_start">Start date</label>
              <input class="form-control" id="event_start" name="start_date" type="date" required>
              <span class="invalid-feedback d-block" data-error-for="start_date"></span>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="event_end">End date</label>
              <input class="form-control" id="event_end" name="end_date" type="date">
            </div>
            <div class="col-12">
              <label class="form-label" for="event_description">Description</label>
              <textarea class="form-control" id="event_description" name="description" rows="2"></textarea>
            </div>
          </div>
          <button class="btn btn-primary mt-3" type="submit">Add event</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-xl-8">
    <div class="card app-card h-100">
      <div class="card-header">Upcoming academic events <span class="badge text-bg-light"><?= count($events) ?></span></div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" data-table>
          <thead><tr><th>Event</th><th>Type</th><th>Dates</th><th>Term</th></tr></thead>
          <tbody>
<?php if (!$events): ?>
            <tr><td colspan="4" class="text-center text-muted py-3">No upcoming events on the calendar.</td></tr>
<?php endif; ?>
<?php foreach ($events as $event): ?>
            <tr>
              <td><span class="fw-semibold"><?= e($event['title']) ?></span><?= $event['description'] ? '<div class="small text-muted">' . e(mb_substr($event['description'], 0, 90)) . '</div>' : '' ?></td>
              <td><span class="badge text-bg-light"><?= e(str_replace('_', ' ', ucfirst($event['event_type']))) ?></span></td>
              <td class="small"><?= e($event['start_date']) ?><?= $event['end_date'] ? ' → ' . e($event['end_date']) : '' ?></td>
              <td class="small"><?= e($event['term_name'] ?: ($event['year_name'] ?: '—')) ?></td>
            </tr>
<?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mt-1">
  <div class="col-xl-5">
    <div class="card app-card h-100">
      <div class="card-header">New syllabus</div>
      <div class="card-body">
<?php if (!$subjects): ?>
        <p class="text-muted mb-0">Add subjects before registering syllabi.</p>
<?php else: ?>
        <form method="post" action="<?= url('academics/syllabi/save') ?>" data-ajax>
          <?= csrf_field() ?>
          <div class="row g-2">
            <div class="col-md-6">
              <label class="form-label" for="syllabus_subject">Subject</label>
              <select class="form-select" id="syllabus_subject" name="subject_id" required>
<?php foreach ($subjects as $item): if (!(int)$item['is_active']) continue; ?>
                <option value="<?= (int)$item['id'] ?>"><?= e($item['code'] . ' — ' . $item['name']) ?></option>
<?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="syllabus_curriculum">Curriculum</label>
              <select class="form-select" id="syllabus_curriculum" name="curriculum_id">
                <option value="">— None —</option>
<?php foreach ($curricula as $item): ?>
                <option value="<?= (int)$item['id'] ?>"><?= e($item['name']) ?></option>
<?php endforeach; ?>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label" for="syllabus_title">Title</label>
              <input class="form-control" id="syllabus_title" name="title" maxlength="180" required placeholder="Lower Secondary Mathematics syllabus">
              <span class="invalid-feedback d-block" data-error-for="title"></span>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="syllabus_level">Level</label>
              <select class="form-select" id="syllabus_level" name="level">
                <option value="O-Level">O-Level</option>
                <option value="A-Level">A-Level</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="syllabus_version">Version</label>
              <input class="form-control" id="syllabus_version" name="version" maxlength="30" placeholder="2020">
            </div>
            <div class="col-md-4 d-flex align-items-center">
              <div class="form-check mt-3">
                <input class="form-check-input" id="syllabus_current" name="is_current" type="checkbox" checked>
                <label class="form-check-label" for="syllabus_current">Current syllabus</label>
              </div>
            </div>
          </div>
          <button class="btn btn-primary mt-3" type="submit">Save syllabus</button>
        </form>
<?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-xl-7">
    <div class="card app-card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span>Syllabus topics<?= $syllabus ? ' — ' . e($syllabus['subject_name']) : '' ?></span>
        <form method="get" action="<?= url('academics') ?>" class="d-flex gap-2">
          <input type="hidden" name="year_id" value="<?= $yearId ?>">
          <select class="form-select form-select-sm" name="syllabus_id" data-submit-on-change style="width:auto" aria-label="Choose syllabus">
            <option value="0">Choose syllabus…</option>
<?php foreach ($syllabi as $item): ?>
            <option value="<?= (int)$item['id'] ?>" <?= $syllabusId === (int)$item['id'] ? 'selected' : '' ?>><?= e($item['subject_code'] . ' — ' . $item['title']) ?></option>
<?php endforeach; ?>
          </select>
        </form>
      </div>
<?php if (!$syllabus): ?>
      <div class="card-body text-muted">Register a syllabus, then select it here to add topics for coverage tracking.</div>
<?php else: ?>
      <div class="card-body border-bottom">
        <form method="post" action="<?= url('academics/syllabi/topics/save') ?>" data-ajax class="row g-2">
          <?= csrf_field() ?>
          <input type="hidden" name="syllabus_id" value="<?= (int)$syllabus['id'] ?>">
          <div class="col-md-1">
            <label class="form-label" for="topic_order">Order</label>
            <input class="form-control" id="topic_order" name="order_index" type="number" min="0" max="32767" value="1" required>
          </div>
          <div class="col-md-2">
            <label class="form-label" for="topic_code">Code</label>
            <input class="form-control" id="topic_code" name="code" maxlength="20" placeholder="U1.1">
          </div>
          <div class="col-md-4">
            <label class="form-label" for="topic_title">Topic title</label>
            <input class="form-control" id="topic_title" name="title" maxlength="180" required placeholder="Linear equations">
            <span class="invalid-feedback d-block" data-error-for="title"></span>
          </div>
          <div class="col-md-2">
            <label class="form-label" for="topic_lessons">Lessons</label>
            <input class="form-control" id="topic_lessons" name="suggested_lessons" type="number" min="0" max="32767" placeholder="4">
          </div>
          <div class="col-md-3 d-flex align-items-end gap-3 pb-2">
            <div class="form-check">
              <input class="form-check-input" id="topic_project" name="is_project_topic" type="checkbox">
              <label class="form-check-label" for="topic_project">Project topic</label>
            </div>
            <button class="btn btn-primary btn-sm" type="submit">Add topic</button>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="topic_subtopics">Sub-topics</label>
            <input class="form-control" id="topic_subtopics" name="sub_topics" maxlength="65535">
          </div>
          <div class="col-md-6">
            <label class="form-label" for="topic_outcomes">Learning outcomes</label>
            <input class="form-control" id="topic_outcomes" name="learning_outcomes" maxlength="65535">
          </div>
        </form>
      </div>
      <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0" data-table>
          <thead><tr><th>#</th><th>Code</th><th>Topic</th><th>Lessons</th><th>Project</th></tr></thead>
          <tbody>
<?php if (!$syllabusTopics): ?>
            <tr><td colspan="5" class="text-center text-muted py-3">No topics in this syllabus yet.</td></tr>
<?php endif; ?>
<?php foreach ($syllabusTopics as $topic): ?>
            <tr>
              <td><?= (int)$topic['order_index'] ?></td>
              <td class="small"><?= e($topic['code'] ?: '—') ?></td>
              <td><span class="fw-semibold"><?= e($topic['title']) ?></span><?= $topic['sub_topics'] ? '<div class="small text-muted">' . e(mb_substr($topic['sub_topics'], 0, 90)) . '</div>' : '' ?></td>
              <td><?= $topic['suggested_lessons'] !== null ? (int)$topic['suggested_lessons'] : '—' ?></td>
              <td><?= (int)$topic['is_project_topic'] ? 'Yes' : 'No' ?></td>
            </tr>
<?php endforeach; ?>
          </tbody>
        </table>
      </div>
<?php endif; ?>
    </div>
  </div>
</div>

<div class="row g-3 mt-1">
  <div class="col-12">
    <div class="card app-card">
      <div class="card-header">Syllabus coverage<?= $coverageSyllabus ? ' — ' . e($coverageSyllabus['subject_name']) : '' ?></div>
      <div class="card-body">
        <form method="get" action="<?= url('academics') ?>" class="row g-2 align-items-end">
          <input type="hidden" name="year_id" value="<?= $yearId ?>">
          <div class="col-md-3">
            <label class="form-label" for="coverage_subject">Subject</label>
            <select class="form-select" id="coverage_subject" name="coverage_subject_id" data-submit-on-change>
              <option value="0">Choose subject…</option>
<?php foreach ($subjects as $item): if (!(int)$item['is_active']) continue; ?>
              <option value="<?= (int)$item['id'] ?>" <?= $coverageSubjectId === (int)$item['id'] ? 'selected' : '' ?>><?= e($item['code'] . ' — ' . $item['name']) ?></option>
<?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label" for="coverage_class">Class</label>
            <select class="form-select" id="coverage_class" name="coverage_class_id" data-submit-on-change>
              <option value="0">Choose class…</option>
<?php foreach ($classes as $item): ?>
              <option value="<?= (int)$item['id'] ?>" <?= $coverageClassId === (int)$item['id'] ? 'selected' : '' ?>><?= e($item['name']) ?></option>
<?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label" for="coverage_stream">Stream</label>
            <select class="form-select" id="coverage_stream" name="coverage_stream_id" data-submit-on-change>
              <option value="0">All streams</option>
<?php foreach ($streams as $item): ?>
              <option value="<?= (int)$item['id'] ?>" <?= $coverageStreamId === (int)$item['id'] ? 'selected' : '' ?>><?= e($item['class_name'] . ' — ' . $item['stream_name']) ?></option>
<?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label" for="coverage_term">Term</label>
            <select class="form-select" id="coverage_term" name="coverage_term_id" data-submit-on-change>
              <option value="0">Choose term…</option>
<?php foreach ($terms as $term): ?>
              <option value="<?= (int)$term['id'] ?>" <?= $coverageTermId === (int)$term['id'] ? 'selected' : '' ?>><?= e($term['name']) ?></option>
<?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3">
            <button class="btn btn-outline-primary" type="submit">Load coverage</button>
          </div>
        </form>
<?php if (!$coverageTopics): ?>
        <p class="text-muted mb-0 mt-3">Choose a subject, class, and term with a current syllabus to view and mark topics taught.</p>
<?php else: ?>
        <div class="d-flex align-items-center gap-3 mt-3 mb-2">
          <div class="progress flex-grow-1" style="height: 22px" role="progressbar" aria-valuenow="<?= (int)$coveragePct ?>" aria-valuemin="0" aria-valuemax="100">
            <div class="progress-bar" style="width: <?= (int)$coveragePct ?>%"><?= (int)$coveragePct ?>% covered</div>
          </div>
          <span class="small text-muted"><?= count($coverageTopics) ?> topics in current syllabus</span>
        </div>
        <form method="post" action="<?= url('academics/coverage/save') ?>" data-ajax>
          <?= csrf_field() ?>
          <input type="hidden" name="subject_id" value="<?= $coverageSubjectId ?>">
          <input type="hidden" name="class_id" value="<?= $coverageClassId ?>">
          <input type="hidden" name="stream_id" value="<?= $coverageStreamId ?>">
          <input type="hidden" name="academic_year_id" value="<?= $yearId ?>">
          <input type="hidden" name="term_id" value="<?= $coverageTermId ?>">
          <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0" data-table>
              <thead><tr><th>#</th><th>Topic</th><th>Taught</th><th>Taught date</th></tr></thead>
              <tbody>
<?php foreach ($coverageTopics as $topic): $topicId = (int)$topic['id']; $covered = $coverageByTopic[$topicId] ?? null; ?>
              <tr>
                <td><?= (int)$topic['order_index'] ?></td>
                <td><span class="fw-semibold"><?= e($topic['title']) ?></span><?= $topic['code'] ? '<div class="small text-muted">' . e($topic['code']) . '</div>' : '' ?></td>
                <td class="text-center">
                  <input class="form-check-input" type="checkbox" name="taught[]" value="<?= $topicId ?>" <?= $covered ? 'checked' : '' ?>>
                </td>
                <td>
                  <input class="form-control form-control-sm" type="date" name="taught_date[<?= $topicId ?>]" value="<?= e($covered['taught_date'] ?? date('Y-m-d')) ?>">
                </td>
              </tr>
<?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <div class="mt-3">
            <button class="btn btn-primary" type="submit">Save coverage</button>
          </div>
        </form>
<?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mt-1">
  <div class="col-12">
    <div class="card app-card">
      <div class="card-header">Teaching workload<?= $year ? ' — ' . e($year['name']) : '' ?> <span class="badge text-bg-light"><?= count($workload) ?></span></div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" data-table>
          <thead><tr><th>Teacher</th><th>Employee no.</th><th>Allocations</th><th>Lessons per week</th></tr></thead>
          <tbody>
<?php if (!$workload): ?>
            <tr><td colspan="4" class="text-center text-muted py-3">No teacher allocations for this year.</td></tr>
<?php endif; ?>
<?php foreach ($workload as $row): ?>
            <tr>
              <td><span class="fw-semibold"><?= e($row['teacher_name']) ?></span></td>
              <td class="small"><?= e($row['staff_number'] ?: '—') ?></td>
              <td><?= (int)$row['allocation_count'] ?></td>
              <td><span class="badge text-bg-light"><?= (int)$row['lessons_per_week'] ?></span></td>
            </tr>
<?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mt-1">
  <div class="col-xl-4">
    <div class="card app-card h-100">
      <div class="card-header">New integrated activity</div>
      <div class="card-body">
<?php if (!$year): ?>
        <p class="text-muted mb-0">Set up an academic year before planning integrated activities.</p>
<?php elseif (!$classes || !$terms): ?>
        <p class="text-muted mb-0">Add classes and terms before planning integrated activities.</p>
<?php else: ?>
        <form method="post" action="<?= url('academics/activities/save') ?>" data-ajax>
          <?= csrf_field() ?>
          <input type="hidden" name="academic_year_id" value="<?= $yearId ?>">
          <div class="row g-2">
            <div class="col-12">
              <label class="form-label" for="activity_title">Title</label>
              <input class="form-control" id="activity_title" name="title" maxlength="180" required placeholder="Water purification project">
              <span class="invalid-feedback d-block" data-error-for="title"></span>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="activity_type">Type</label>
              <select class="form-select" id="activity_type" name="activity_type">
                <option value="project">Project</option>
                <option value="practical">Practical</option>
                <option value="integrated_activity">Integrated activity</option>
                <option value="field_work">Field work</option>
                <option value="presentation">Presentation</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="activity_subject">Subject (optional)</label>
              <select class="form-select" id="activity_subject" name="subject_id">
                <option value="">— None —</option>
<?php foreach ($subjects as $item): if (!(int)$item['is_active']) continue; ?>
                <option value="<?= (int)$item['id'] ?>"><?= e($item['code'] . ' — ' . $item['name']) ?></option>
<?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="activity_class">Class</label>
              <select class="form-select" id="activity_class" name="class_id" required>
<?php foreach ($classes as $item): ?>
                <option value="<?= (int)$item['id'] ?>"><?= e($item['name']) ?></option>
<?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="activity_stream">Stream</label>
              <select class="form-select" id="activity_stream" name="stream_id">
                <option value="">All streams / class-wide</option>
<?php foreach ($streams as $item): ?>
                <option value="<?= (int)$item['id'] ?>"><?= e($item['class_name'] . ' — ' . $item['stream_name']) ?></option>
<?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="activity_term">Term</label>
              <select class="form-select" id="activity_term" name="term_id" required>
<?php foreach ($terms as $term): ?>
                <option value="<?= (int)$term['id'] ?>"><?= e($term['name']) ?></option>
<?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="activity_start">Start date</label>
              <input class="form-control" id="activity_start" name="start_date" type="date">
            </div>
            <div class="col-md-4">
              <label class="form-label" for="activity_due">Due date</label>
              <input class="form-control" id="activity_due" name="due_date" type="date">
              <span class="invalid-feedback d-block" data-error-for="due_date"></span>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="activity_score">Max score</label>
              <input class="form-control" id="activity_score" name="max_score" type="number" min="1" max="127" value="3" required>
            </div>
            <div class="col-12">
              <label class="form-label" for="activity_description">Description</label>
              <textarea class="form-control" id="activity_description" name="description" rows="2"></textarea>
            </div>
            <div class="col-12">
              <label class="form-label" for="activity_instructions">Instructions</label>
              <textarea class="form-control" id="activity_instructions" name="instructions" rows="2"></textarea>
            </div>
          </div>
          <button class="btn btn-primary mt-3" type="submit">Save activity</button>
        </form>
<?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-xl-8">
    <div class="card app-card h-100">
      <div class="card-header">Integrated activities <span class="badge text-bg-light"><?= count($activities) ?></span></div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" data-table>
          <thead><tr><th>Activity</th><th>Class</th><th>Due</th><th>Status</th></tr></thead>
          <tbody>
<?php if (!$activities): ?>
            <tr><td colspan="4" class="text-center text-muted py-3">No integrated activities for this year.</td></tr>
<?php endif; ?>
<?php foreach ($activities as $activity): ?>
            <tr>
              <td><span class="fw-semibold"><?= e($activity['title']) ?></span><div class="small text-muted"><?= e(str_replace('_', ' ', ucfirst($activity['activity_type']))) ?><?= $activity['subject_name'] ? ' · ' . e($activity['subject_name']) : '' ?> · max <?= (int)$activity['max_score'] ?></div></td>
              <td><?= e($activity['class_name']) ?><?= $activity['stream_name'] ? ' — ' . e($activity['stream_name']) : '' ?><div class="small text-muted"><?= e($activity['term_name']) ?></div></td>
              <td class="small"><?= e($activity['due_date'] ?? '—') ?></td>
              <td>
                <form method="post" action="<?= url('academics/activities/status') ?>" data-ajax class="d-inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="id" value="<?= (int)$activity['id'] ?>">
                  <select class="form-select form-select-sm" name="status" onchange="this.form.requestSubmit()" style="width:auto">
<?php foreach (['planned', 'assigned', 'in_progress', 'submitted', 'marked'] as $status): ?>
                    <option value="<?= e($status) ?>" <?= $activity['status'] === $status ? 'selected' : '' ?>><?= e(ucfirst(str_replace('_', ' ', $status))) ?></option>
<?php endforeach; ?>
                  </select>
                </form>
              </td>
            </tr>
<?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
