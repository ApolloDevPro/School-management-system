<div class="page-head">
  <div>
    <h1 class="h4 mb-0">Library</h1>
    <p class="text-muted small mb-0">Manage the school book catalogue.</p>
  </div>
</div>

<div class="row g-3">

<?php if ($canAdd): ?>
  <div class="col-xl-4">
    <div class="card app-card">
      <div class="card-header">Add book</div>
      <div class="card-body">
        <form method="post" action="<?= url('library/books/store') ?>" data-ajax>
          <?= csrf_field() ?>
          <div class="mb-2">
            <label class="form-label">Title <span class="text-danger">*</span></label>
            <input class="form-control" name="title" maxlength="200" required>
            <span class="invalid-feedback d-block" data-error-for="title"></span>
          </div>
          <div class="mb-2">
            <label class="form-label">Author <span class="text-danger">*</span></label>
            <input class="form-control" name="author" maxlength="160" required>
            <span class="invalid-feedback d-block" data-error-for="author"></span>
          </div>
          <div class="mb-2">
            <label class="form-label">ISBN</label>
            <input class="form-control" name="isbn" maxlength="30">
          </div>
          <?php if ($categories): ?>
          <div class="mb-2">
            <label class="form-label">Category</label>
            <select class="form-select" name="category_id">
              <option value="0">No category</option>
              <?php foreach ($categories as $category): ?>
                <?php if ((int)$category['is_active']): ?>
                <option value="<?= (int)$category['id'] ?>"><?= e($category['name']) ?></option>
                <?php endif; ?>
              <?php endforeach; ?>
            </select>
            <span class="invalid-feedback d-block" data-error-for="category_id"></span>
          </div>
          <?php endif; ?>
          <div class="mb-3">
            <label class="form-label">Copies <span class="text-danger">*</span></label>
            <input class="form-control" name="copies" type="number" min="1" value="1" required>
            <span class="invalid-feedback d-block" data-error-for="copies"></span>
          </div>
          <button class="btn btn-primary" type="submit">Add book</button>
        </form>
      </div>
    </div>
  </div>
<?php endif; ?>

  <div class="<?= $canAdd ? 'col-xl-8' : 'col-12' ?>">
    <div class="card app-card">
      <div class="card-header">
        Book catalogue
        <span class="badge text-bg-light"><?= count($books) ?></span>
      </div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" data-table>
          <thead>
            <tr>
              <th>Title</th>
              <th>Author</th>
              <th>ISBN</th>
              <th>Copies</th>
              <th>Available</th>
              <th>Status</th>
              <?php if ($canEdit): ?><th></th><?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php if (!$books): ?>
              <tr>
                <td colspan="<?= $canEdit ? 7 : 6 ?>" class="text-center text-muted py-4">No books in the catalogue yet.</td>
              </tr>
            <?php endif; ?>
            <?php foreach ($books as $book): ?>
              <tr>
                <td><strong><?= e($book['title']) ?></strong></td>
                <td><?= e($book['author']) ?></td>
                <td><?= e($book['isbn'] ?? '—') ?></td>
                <td><?= (int)$book['total_copies'] ?></td>
                <td><?= (int)$book['available_copies'] ?></td>
                <td>
                  <span class="badge text-bg-<?= $book['status'] === 'active' ? 'success' : ($book['status'] === 'withdrawn' ? 'danger' : 'secondary') ?>">
                    <?= e($book['status']) ?>
                  </span>
                </td>
                <?php if ($canEdit): ?>
                  <td>
                    <form method="post" action="<?= url('library/books/toggle') ?>" data-ajax>
                      <?= csrf_field() ?>
                      <input type="hidden" name="id" value="<?= (int)$book['id'] ?>">
                      <select class="form-select form-select-sm" name="status" onchange="this.form.requestSubmit()" style="width:auto">
                        <option value="active"    <?= $book['status'] === 'active'    ? 'selected' : '' ?>>Active</option>
                        <option value="inactive"  <?= $book['status'] === 'inactive'  ? 'selected' : '' ?>>Inactive</option>
                        <option value="withdrawn" <?= $book['status'] === 'withdrawn' ? 'selected' : '' ?>>Withdrawn</option>
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

<div class="row g-3 mt-1">

<?php if ($canAdd): ?>
  <div class="col-xl-4">
    <div class="card app-card">
      <div class="card-header">Add category</div>
      <div class="card-body">
        <form method="post" action="<?= url('library/categories/store') ?>" data-ajax>
          <?= csrf_field() ?>
          <div class="mb-2">
            <label class="form-label">Name <span class="text-danger">*</span></label>
            <input class="form-control" name="name" maxlength="80" required>
            <span class="invalid-feedback d-block" data-error-for="name"></span>
          </div>
          <div class="mb-2">
            <label class="form-label">Code</label>
            <input class="form-control" name="code" maxlength="20" placeholder="e.g. FIC">
            <span class="invalid-feedback d-block" data-error-for="code"></span>
          </div>
          <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea class="form-control" name="description" rows="2" maxlength="255"></textarea>
            <span class="invalid-feedback d-block" data-error-for="description"></span>
          </div>
          <button class="btn btn-primary" type="submit">Add category</button>
        </form>
      </div>
    </div>
  </div>
<?php endif; ?>

  <div class="<?= $canAdd ? 'col-xl-8' : 'col-12' ?>">
    <div class="card app-card">
      <div class="card-header">
        Book categories
        <span class="badge text-bg-light"><?= count($categories) ?></span>
      </div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" data-table>
          <thead>
            <tr>
              <th>Name</th>
              <th>Code</th>
              <th>Description</th>
              <th>Status</th>
              <?php if ($canEdit): ?><th></th><?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php if (!$categories): ?>
              <tr>
                <td colspan="<?= $canEdit ? 5 : 4 ?>" class="text-center text-muted py-4">No book categories yet.</td>
              </tr>
            <?php endif; ?>
            <?php foreach ($categories as $category): ?>
              <tr>
                <td><strong><?= e($category['name']) ?></strong></td>
                <td><?= e($category['code'] ?? '—') ?></td>
                <td class="small"><?= e($category['description'] ?? '—') ?></td>
                <td>
                  <span class="badge text-bg-<?= (int)$category['is_active'] ? 'success' : 'secondary' ?>">
                    <?= (int)$category['is_active'] ? 'Active' : 'Inactive' ?>
                  </span>
                </td>
                <?php if ($canEdit): ?>
                  <td>
                    <form method="post" action="<?= url('library/categories/toggle') ?>" data-ajax>
                      <?= csrf_field() ?>
                      <input type="hidden" name="id" value="<?= (int)$category['id'] ?>">
                      <select class="form-select form-select-sm" name="is_active" onchange="this.form.requestSubmit()" style="width:auto">
                        <option value="1" <?= (int)$category['is_active'] === 1 ? 'selected' : '' ?>>Active</option>
                        <option value="0" <?= (int)$category['is_active'] === 0 ? 'selected' : '' ?>>Inactive</option>
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

<div class="row g-3 mt-1">

<?php if ($canAdd): ?>
  <div class="col-xl-4">
    <div class="card app-card">
      <div class="card-header">Issue a book</div>
      <div class="card-body">
        <form method="post" action="<?= url('library/borrowings/issue') ?>" data-ajax>
          <?= csrf_field() ?>
          <div class="mb-2">
            <label class="form-label">Book <span class="text-danger">*</span></label>
            <select class="form-select" name="book_id" required>
              <option value="">Choose book…</option>
              <?php foreach ($books as $book): ?>
                <?php if ($book['status'] === 'active' && (int)$book['available_copies'] > 0): ?>
                <option value="<?= (int)$book['id'] ?>"><?= e($book['title']) ?> (<?= (int)$book['available_copies'] ?>/<?= (int)$book['total_copies'] ?> available)</option>
                <?php endif; ?>
              <?php endforeach; ?>
            </select>
            <span class="invalid-feedback d-block" data-error-for="book_id"></span>
          </div>
          <div class="mb-2">
            <label class="form-label">Borrower type <span class="text-danger">*</span></label>
            <select class="form-select" name="borrower_type" id="borrowerType" required>
              <option value="student">Student</option>
              <option value="staff">Staff member</option>
            </select>
            <span class="invalid-feedback d-block" data-error-for="borrower_type"></span>
          </div>
          <div class="mb-2" id="issueStudentWrap">
            <label class="form-label">Student <span class="text-danger">*</span></label>
            <select class="form-select" name="student_id">
              <option value="">Choose student…</option>
              <?php foreach ($students as $student): ?>
              <option value="<?= (int)$student['id'] ?>"><?= e($student['admission_number'] . ' — ' . $student['last_name'] . ' ' . $student['first_name']) ?></option>
              <?php endforeach; ?>
            </select>
            <span class="invalid-feedback d-block" data-error-for="student_id"></span>
          </div>
          <div class="mb-2 d-none" id="issueStaffWrap">
            <label class="form-label">Staff member <span class="text-danger">*</span></label>
            <select class="form-select" name="staff_id">
              <option value="">Choose staff…</option>
              <?php foreach ($staff as $member): ?>
              <option value="<?= (int)$member['id'] ?>"><?= e($member['staff_number'] . ' — ' . $member['last_name'] . ' ' . $member['first_name']) ?></option>
              <?php endforeach; ?>
            </select>
            <span class="invalid-feedback d-block" data-error-for="staff_id"></span>
          </div>
          <div class="row g-2 mb-2">
            <div class="col">
              <label class="form-label">Issue date <span class="text-danger">*</span></label>
              <input class="form-control" type="date" name="issue_date" value="<?= e(date('Y-m-d')) ?>" required>
              <span class="invalid-feedback d-block" data-error-for="issue_date"></span>
            </div>
            <div class="col">
              <label class="form-label">Due date <span class="text-danger">*</span></label>
              <input class="form-control" type="date" name="due_date" value="<?= e(date('Y-m-d', strtotime('+14 days'))) ?>" required>
              <span class="invalid-feedback d-block" data-error-for="due_date"></span>
            </div>
          </div>
          <div class="row g-2 mb-2">
            <div class="col-4">
              <label class="form-label">Copies <span class="text-danger">*</span></label>
              <input class="form-control" type="number" name="copies" min="1" max="20" value="1" required>
              <span class="invalid-feedback d-block" data-error-for="copies"></span>
            </div>
            <div class="col">
              <label class="form-label">Notes</label>
              <input class="form-control" name="notes" maxlength="255" placeholder="Optional">
              <span class="invalid-feedback d-block" data-error-for="notes"></span>
            </div>
          </div>
          <button class="btn btn-primary" type="submit">Issue book</button>
        </form>
        <script>
          (function () {
            var type = document.getElementById('borrowerType');
            if (!type) { return; }
            var studentWrap = document.getElementById('issueStudentWrap');
            var staffWrap = document.getElementById('issueStaffWrap');
            function sync() {
              var isStudent = type.value === 'student';
              studentWrap.classList.toggle('d-none', !isStudent);
              staffWrap.classList.toggle('d-none', isStudent);
            }
            type.addEventListener('change', sync);
            sync();
          })();
        </script>
      </div>
    </div>
  </div>
<?php endif; ?>

  <div class="<?= $canAdd ? 'col-xl-8' : 'col-12' ?>">
    <div class="card app-card">
      <div class="card-header">
        Books out — returns
        <span class="badge text-bg-light"><?= count($activeBorrowings) ?></span>
      </div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" data-table>
          <thead>
            <tr>
              <th>Book</th>
              <th>Borrower</th>
              <th>Issued</th>
              <th>Due</th>
              <th>Status</th>
              <?php if ($canEdit): ?><th>Return</th><?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php if (!$activeBorrowings): ?>
              <tr>
                <td colspan="<?= $canEdit ? 6 : 5 ?>" class="text-center text-muted py-4">No books currently out on loan.</td>
              </tr>
            <?php endif; ?>
            <?php foreach ($activeBorrowings as $loan): $daysLeft = (int)$loan['days_until_due']; ?>
              <tr>
                <td><strong><?= e($loan['title']) ?></strong><div class="small text-muted"><?= e($loan['accession_number']) ?></div></td>
                <td><?= e($loan['borrower_name']) ?><div class="small text-muted"><?= e($loan['borrower_ref']) ?></div></td>
                <td class="small"><?= e(date('d M Y', strtotime($loan['issue_date']))) ?></td>
                <td class="small">
                  <?= e(date('d M Y', strtotime($loan['due_date']))) ?>
                  <?php if ($daysLeft < 0): ?>
                    <div><span class="badge text-bg-danger"><?= abs($daysLeft) ?> day(s) overdue</span></div>
                  <?php elseif ($daysLeft <= 3): ?>
                    <div><span class="badge text-bg-warning"><?= $daysLeft ?> day(s) left</span></div>
                  <?php endif; ?>
                </td>
                <td><span class="badge text-bg-<?= $loan['status'] === 'overdue' ? 'danger' : 'primary' ?>"><?= e($loan['status']) ?></span></td>
                <?php if ($canEdit): ?>
                  <td>
                    <form method="post" action="<?= url('library/borrowings/return') ?>" data-ajax class="d-flex gap-1">
                      <?= csrf_field() ?>
                      <input type="hidden" name="id" value="<?= (int)$loan['id'] ?>">
                      <select class="form-select form-select-sm" name="condition" style="width:auto">
                        <option value="good">Good</option>
                        <option value="worn">Worn</option>
                        <option value="damaged">Damaged</option>
                        <option value="missing_pages">Missing pages</option>
                      </select>
                      <button class="btn btn-sm btn-outline-primary" type="submit">Return</button>
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

<div class="row g-3 mt-1">

  <div class="col-12">
    <div class="card app-card">
      <div class="card-header">
        Overdue borrowings
        <span class="badge text-bg-light"><?= count($overdueBorrowings) ?></span>
      </div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" data-table>
          <thead>
            <tr>
              <th>Book</th>
              <th>Borrower</th>
              <th>Issued</th>
              <th>Due date</th>
              <th>Days overdue</th>
              <th>Status</th>
              <th class="text-end">Notice</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$overdueBorrowings): ?>
              <tr>
                <td colspan="6" class="text-center text-muted py-4">No overdue borrowings — everything is up to date.</td>
              </tr>
            <?php endif; ?>
            <?php foreach ($overdueBorrowings as $loan): ?>
              <tr>
                <td><strong><?= e($loan['title']) ?></strong><div class="small text-muted"><?= e($loan['accession_number']) ?></div></td>
                <td><?= e($loan['borrower_name']) ?><div class="small text-muted"><?= e($loan['borrower_ref']) ?></div></td>
                <td class="small"><?= e(date('d M Y', strtotime($loan['issue_date']))) ?></td>
                <td class="small"><?= e(date('d M Y', strtotime($loan['due_date']))) ?></td>
                <td><span class="badge text-bg-danger"><?= (int)$loan['days_overdue'] ?> day(s)</span></td>
                <td><span class="badge text-bg-<?= $loan['status'] === 'overdue' ? 'danger' : 'warning' ?>"><?= e($loan['status']) ?></span></td>
                <td class="text-end text-nowrap">
                  <a class="btn btn-sm btn-outline-danger" href="<?= url('library/overdue-notice', ['student_id' => (int)($loan['student_id'] ?? 0)]) ?>" target="_blank"><i class="bi bi-file-earmark-text"></i> Notice</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>

<div class="row g-3 mt-1">

<?php if ($canAdd): ?>
  <div class="col-xl-4">
    <div class="card app-card">
      <div class="card-header">Record a fine</div>
      <div class="card-body">
        <form method="post" action="<?= url('library/fines/store') ?>" data-ajax>
          <?= csrf_field() ?>
          <div class="mb-2">
            <label class="form-label">Fine type <span class="text-danger">*</span></label>
            <select class="form-select" name="fine_type" required>
              <option value="overdue">Overdue</option>
              <option value="damaged">Damaged</option>
              <option value="lost">Lost</option>
            </select>
            <span class="invalid-feedback d-block" data-error-for="fine_type"></span>
          </div>
          <div class="mb-2">
            <label class="form-label">Borrowing record</label>
            <select class="form-select" name="borrowing_id" id="fineBorrowing">
              <option value="0">Not linked — pick a borrower below…</option>
              <?php foreach ($borrowingOptions as $option): ?>
              <option value="<?= (int)$option['id'] ?>"><?= e($option['title'] . ' — ' . $option['borrower_name']) ?> · due <?= e(date('d M Y', strtotime($option['due_date']))) ?></option>
              <?php endforeach; ?>
            </select>
            <span class="invalid-feedback d-block" data-error-for="borrowing_id"></span>
          </div>
          <div id="fineBorrowerBlock">
            <div class="mb-2">
              <label class="form-label">Borrower type</label>
              <select class="form-select" name="borrower_type" id="fineBorrowerType">
                <option value="student">Student</option>
                <option value="staff">Staff member</option>
              </select>
              <span class="invalid-feedback d-block" data-error-for="borrower_type"></span>
            </div>
            <div class="mb-2" id="fineStudentWrap">
              <label class="form-label">Student</label>
              <select class="form-select" name="student_id">
                <option value="">Choose student…</option>
                <?php foreach ($students as $student): ?>
                <option value="<?= (int)$student['id'] ?>"><?= e($student['admission_number'] . ' — ' . $student['last_name'] . ' ' . $student['first_name']) ?></option>
                <?php endforeach; ?>
              </select>
              <span class="invalid-feedback d-block" data-error-for="student_id"></span>
            </div>
            <div class="mb-2 d-none" id="fineStaffWrap">
              <label class="form-label">Staff member</label>
              <select class="form-select" name="staff_id">
                <option value="">Choose staff…</option>
                <?php foreach ($staff as $member): ?>
                <option value="<?= (int)$member['id'] ?>"><?= e($member['staff_number'] . ' — ' . $member['last_name'] . ' ' . $member['first_name']) ?></option>
                <?php endforeach; ?>
              </select>
              <span class="invalid-feedback d-block" data-error-for="staff_id"></span>
            </div>
          </div>
          <div class="row g-2 mb-2">
            <div class="col-7">
              <label class="form-label">Amount (UGX) <span class="text-danger">*</span></label>
              <input class="form-control" type="number" name="amount" min="1" step="0.01" required>
              <span class="invalid-feedback d-block" data-error-for="amount"></span>
            </div>
            <div class="col-5">
              <label class="form-label">Days charged</label>
              <input class="form-control" type="number" name="days_charged" min="0" max="3650" value="0">
              <span class="invalid-feedback d-block" data-error-for="days_charged"></span>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Reason</label>
            <textarea class="form-control" name="reason" rows="2" maxlength="255"></textarea>
            <span class="invalid-feedback d-block" data-error-for="reason"></span>
          </div>
          <button class="btn btn-primary" type="submit">Record fine</button>
        </form>
        <script>
          (function () {
            var borrowing = document.getElementById('fineBorrowing');
            var type = document.getElementById('fineBorrowerType');
            if (!borrowing || !type) { return; }
            var borrowerBlock = document.getElementById('fineBorrowerBlock');
            var studentWrap = document.getElementById('fineStudentWrap');
            var staffWrap = document.getElementById('fineStaffWrap');
            function sync() {
              var linked = borrowing.value !== '0';
              borrowerBlock.classList.toggle('d-none', linked);
              var isStudent = type.value === 'student';
              studentWrap.classList.toggle('d-none', linked || !isStudent);
              staffWrap.classList.toggle('d-none', linked || isStudent);
            }
            borrowing.addEventListener('change', sync);
            type.addEventListener('change', sync);
            sync();
          })();
        </script>
      </div>
    </div>
  </div>
<?php endif; ?>

  <div class="<?= $canAdd ? 'col-xl-8' : 'col-12' ?>">
    <div class="card app-card">
      <div class="card-header">
        Library fines
        <span class="badge text-bg-light"><?= count($fines) ?></span>
      </div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" data-table>
          <thead>
            <tr>
              <th>Borrower</th>
              <th>Book</th>
              <th>Type</th>
              <th>Amount</th>
              <th>Paid</th>
              <th>Days</th>
              <th>Status</th>
              <th>Reason</th>
              <?php if ($canEdit): ?><th></th><?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php if (!$fines): ?>
              <tr>
                <td colspan="<?= $canEdit ? 9 : 8 ?>" class="text-center text-muted py-4">No library fines recorded.</td>
              </tr>
            <?php endif; ?>
            <?php foreach ($fines as $fine): ?>
              <tr>
                <td><strong><?= e($fine['borrower_name']) ?></strong><div class="small text-muted"><?= e($fine['borrower_ref']) ?></div></td>
                <td class="small"><?= e($fine['book_title'] ?? '—') ?></td>
                <td>
                  <span class="badge text-bg-<?= $fine['fine_type'] === 'overdue' ? 'warning' : ($fine['fine_type'] === 'lost' ? 'danger' : 'info') ?>">
                    <?= e($fine['fine_type']) ?>
                  </span>
                </td>
                <td><?= number_format((float)$fine['amount'], 2) ?></td>
                <td><?= number_format((float)$fine['amount_paid'], 2) ?></td>
                <td><?= $fine['days_charged'] !== null ? (int)$fine['days_charged'] : '—' ?></td>
                <td>
                  <span class="badge text-bg-<?= $fine['status'] === 'paid' ? 'success' : ($fine['status'] === 'waived' ? 'secondary' : 'danger') ?>">
                    <?= e(str_replace('_', ' ', $fine['status'])) ?>
                  </span>
                </td>
                <td class="small"><?= e($fine['reason'] ?? '—') ?></td>
                <?php if ($canEdit): ?>
                  <td>
                    <?php if (in_array($fine['status'], ['unpaid', 'part_paid'], true)): ?>
                      <form method="post" action="<?= url('library/fines/pay') ?>" data-ajax data-confirm="Mark this fine as fully paid?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int)$fine['id'] ?>">
                        <button class="btn btn-sm btn-success" type="submit">Mark paid</button>
                      </form>
                    <?php endif; ?>
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
