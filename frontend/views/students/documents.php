<div class="page-head">
  <div>
    <h1 class="h4 mb-0">Student documents</h1>
    <p class="text-muted small mb-0">Scanned admission letters, certificates, results slips and photos</p>
  </div>
</div>

<div class="row g-3">
<?php if ($canAdd): ?>
  <div class="col-lg-4">
    <div class="card app-card">
      <div class="card-header">Upload a document</div>
      <div class="card-body">
        <form method="post" action="<?= url('students/documents/upload') ?>" enctype="multipart/form-data">
          <?= csrf_field() ?>
          <div class="mb-3">
            <label class="form-label" for="doc_student">Student</label>
            <select class="form-select" id="doc_student" name="student_id" required>
              <option value="">Choose…</option>
<?php foreach ($students as $student): ?>
              <option value="<?= (int)$student['id'] ?>" <?= $studentId === (int)$student['id'] ? 'selected' : '' ?>>
                <?= e($student['admission_number'] . ' — ' . $student['last_name'] . ' ' . $student['first_name']) ?>
              </option>
<?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label" for="doc_type">Document type</label>
            <select class="form-select" id="doc_type" name="doc_type">
<?php foreach (['admission_letter' => 'Admission letter', 'birth_certificate' => 'Birth certificate', 'previous_report' => 'Previous school report', 'ple_slip' => 'PLE results slip', 'uce_slip' => 'UCE results slip', 'uace_slip' => 'UACE results slip', 'nin' => 'NIN', 'passport_photo' => 'Passport photo', 'medical_form' => 'Medical form', 'transfer_letter' => 'Transfer letter', 'other' => 'Other'] as $value => $label): ?>
              <option value="<?= $value ?>"><?= $label ?></option>
<?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label" for="doc_title">Title</label>
            <input class="form-control" id="doc_title" name="title" maxlength="160" required>
          </div>
          <div class="mb-3">
            <label class="form-label" for="doc_file">File</label>
            <input class="form-control" id="doc_file" name="file" type="file" accept=".pdf,.png,.jpg,.jpeg,.doc,.docx" required>
            <div class="form-text">PDF, image or Word document, up to 5 MB.</div>
          </div>
          <button class="btn btn-primary" type="submit">Upload</button>
        </form>
      </div>
    </div>
  </div>
<?php endif; ?>

  <div class="col-lg-8">
    <div class="card app-card">
      <div class="card-header">
        Documents
        <form method="get" action="<?= url('students/documents') ?>" class="float-end d-flex gap-2">
          <input type="hidden" name="student_id" value="<?= $studentId ?>">
          <select class="form-select form-select-sm" name="type" data-submit-on-change>
            <option value="">All types</option>
<?php foreach (['admission_letter' => 'Admission letter', 'birth_certificate' => 'Birth certificate', 'previous_report' => 'Previous report', 'ple_slip' => 'PLE slip', 'uce_slip' => 'UCE slip', 'uace_slip' => 'UACE slip', 'nin' => 'NIN', 'passport_photo' => 'Passport photo', 'medical_form' => 'Medical form', 'transfer_letter' => 'Transfer letter', 'other' => 'Other'] as $value => $label): ?>
            <option value="<?= $value ?>" <?= $type === $value ? 'selected' : '' ?>><?= $label ?></option>
<?php endforeach; ?>
          </select>
        </form>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0" data-table>
            <thead><tr><th>Student</th><th>Title</th><th>Type</th><th>Uploaded</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
<?php if (!$documents): ?>
              <tr><td colspan="6" class="text-center text-muted py-4">No documents found.</td></tr>
<?php endif; ?>
<?php foreach ($documents as $document): ?>
              <tr>
                <td>
                  <a class="fw-semibold text-decoration-none" href="<?= url('students/profile', ['id' => (int)$document['student_id']]) ?>">
                    <?= e($document['last_name'] . ' ' . $document['first_name']) ?>
                  </a>
                  <div class="small text-muted"><?= e($document['admission_number']) ?></div>
                </td>
                <td><a href="<?= asset($document['file_path']) ?>" target="_blank"><?= e($document['title']) ?></a></td>
                <td><span class="badge text-bg-light"><?= e(str_replace('_', ' ', $document['doc_type'])) ?></span></td>
                <td class="small text-muted"><?= e(date('d M Y', strtotime($document['created_at']))) ?></td>
                <td>
<?php if ((int)$document['is_verified']): ?>
                  <span class="badge text-bg-success">Verified</span>
<?php else: ?>
                  <span class="badge text-bg-secondary">Unverified</span>
<?php endif; ?>
                </td>
                <td class="text-end text-nowrap">
<?php if ($canEdit): ?>
                  <form method="post" action="<?= url('students/documents/verify') ?>" class="d-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$document['id'] ?>">
                    <button class="btn btn-sm btn-outline-<?= (int)$document['is_verified'] ? 'secondary' : 'success' ?>" type="submit">
                      <?= (int)$document['is_verified'] ? 'Unverify' : 'Verify' ?>
                    </button>
                  </form>
                  <form method="post" action="<?= url('students/documents/delete') ?>" class="d-inline" data-confirm="Delete <?= e($document['title']) ?>?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$document['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
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
