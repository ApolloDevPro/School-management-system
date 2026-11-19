<?php
declare(strict_types=1);

/**
 * Books, categories, borrowing, returns, fines and overdue notices.
 *
 * Backend controller: receives routed requests, enforces permissions and
 * renders a view or returns JSON through the base Controller helpers.
 */
final class LibraryController extends Controller
{
    public function index(): void
    {
        if (!$this->allowed('view')) {
            return;
        }
        $schoolId = school_id();
        $this->render('library/index', [
            'pageTitle'          => 'Library',
            'activeNav'          => 'library',
            'books'              => LibraryModule::books($schoolId),
            'categories'         => LibraryModule::categories($schoolId),
            'students'           => LibraryModule::students($schoolId),
            'staff'              => LibraryModule::staff($schoolId),
            'activeBorrowings'   => LibraryModule::activeBorrowings($schoolId),
            'overdueBorrowings'  => LibraryModule::overdueBorrowings($schoolId),
            'borrowingOptions'   => LibraryModule::borrowingOptions($schoolId),
            'fines'              => LibraryModule::fines($schoolId),
            'canAdd'             => Auth::can('library', 'add'),
            'canEdit'            => Auth::can('library', 'edit'),
        ]);
    }

    public function storeBook(): void
    {
        if (!$this->allowed('add')) {
            return;
        }
        $schoolId   = school_id();
        $title      = trim((string)($_POST['title'] ?? ''));
        $author     = trim((string)($_POST['author'] ?? ''));
        $isbn       = trim((string)($_POST['isbn'] ?? ''));
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $copies     = (int)($_POST['copies'] ?? 1);

        $errors = [];
        if ($title === '' || mb_strlen($title) > 200) { $errors['title'] = 'Enter a book title (max 200 chars).'; }
        if ($author === '' || mb_strlen($author) > 160) { $errors['author'] = 'Enter the author name.'; }
        if ($copies < 1 || $copies > 9999) { $errors['copies'] = 'Enter a valid copy count (1–9999).'; }
        if ($categoryId > 0 && !LibraryModule::validCategory($categoryId, $schoolId)) { $errors['category_id'] = 'Choose a valid book category.'; }
        if ($errors) { $this->respond(false, 'Please correct the highlighted fields.', 'library', [], $errors); }

        $id = LibraryModule::createBook([
            'school_id'   => $schoolId,
            'title'       => $title,
            'author'      => $author,
            'isbn'        => $isbn,
            'category_id' => $categoryId > 0 ? $categoryId : null,
            'copies'      => $copies,
            'user_id'     => (int)Auth::user()['id'],
        ]);

        audit('library', 'book_created', 'books', $id, 'Added book: ' . $title);
        $this->respond(true, 'Book "' . $title . '" added.', 'library');
    }

    public function toggleBook(): void
    {
        if (!$this->allowed('edit')) {
            return;
        }
        $id     = (int)($_POST['id'] ?? 0);
        $status = (string)($_POST['status'] ?? '');
        if (!in_array($status, ['active', 'inactive', 'withdrawn'], true)) {
            $this->respond(false, 'Invalid status.', 'library');
        }
        $ok = LibraryModule::changeStatus($id, school_id(), $status);
        if ($ok) {
            audit('library', 'book_status_changed', 'books', $id, 'Book status changed to ' . $status);
        }
        $this->respond($ok, $ok ? 'Book status updated.' : 'Could not update book status.', 'library');
    }

    public function storeCategory(): void
    {
        if (!$this->allowed('add')) {
            return;
        }
        $schoolId    = school_id();
        $name        = trim((string)($_POST['name'] ?? ''));
        $code        = trim((string)($_POST['code'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));

        $errors = [];
        if ($name === '' || mb_strlen($name) > 80) { $errors['name'] = 'Enter a category name (max 80 chars).'; }
        if (mb_strlen($code) > 20) { $errors['code'] = 'Code must be 20 characters or fewer.'; }
        if (mb_strlen($description) > 255) { $errors['description'] = 'Description must be 255 characters or fewer.'; }
        if (!$errors && LibraryModule::categoryNameTaken($schoolId, $name)) { $errors['name'] = 'A category with this name already exists.'; }
        if ($errors) { $this->respond(false, 'Please correct the highlighted fields.', 'library', [], $errors); }

        $id = LibraryModule::createCategory($schoolId, $name, $code ?: null, $description ?: null);

        audit('library', 'category_created', 'book_categories', $id, 'Added book category: ' . $name);
        $this->respond(true, 'Category "' . $name . '" added.', 'library');
    }

    public function toggleCategory(): void
    {
        if (!$this->allowed('edit')) {
            return;
        }
        $id     = (int)($_POST['id'] ?? 0);
        $active = (int)($_POST['is_active'] ?? -1);
        if (!in_array($active, [0, 1], true)) {
            $this->respond(false, 'Invalid category status.', 'library');
        }
        $ok = LibraryModule::toggleCategory($id, school_id(), $active);
        if ($ok) {
            audit('library', 'category_status_changed', 'book_categories', $id, 'Book category status changed to ' . ($active ? 'active' : 'inactive'));
        }
        $this->respond($ok, $ok ? 'Category status updated.' : 'Could not update category status.', 'library');
    }

    public function issueBorrowing(): void
    {
        if (!$this->allowed('add')) {
            return;
        }
        $schoolId    = school_id();
        $bookId      = (int)($_POST['book_id'] ?? 0);
        $borrowerType = (string)($_POST['borrower_type'] ?? '');
        $studentId   = (int)($_POST['student_id'] ?? 0);
        $staffId     = (int)($_POST['staff_id'] ?? 0);
        $issueDate   = trim((string)($_POST['issue_date'] ?? ''));
        $dueDate     = trim((string)($_POST['due_date'] ?? ''));
        $copies      = (int)($_POST['copies'] ?? 1);
        $notes       = trim((string)($_POST['notes'] ?? ''));

        $errors = [];
        if (!in_array($borrowerType, ['student', 'staff'], true)) { $errors['borrower_type'] = 'Choose a student or staff borrower.'; }
        if ($borrowerType === 'student' && !LibraryModule::validStudent($studentId, $schoolId)) { $errors['student_id'] = 'Choose a valid active student.'; }
        if ($borrowerType === 'staff' && !LibraryModule::validStaff($staffId, $schoolId)) { $errors['staff_id'] = 'Choose a valid staff member.'; }
        if (!LibraryModule::validBook($bookId, $schoolId)) { $errors['book_id'] = 'Choose a valid book.'; }
        if (!$this->validDate($issueDate)) { $errors['issue_date'] = 'Enter a valid issue date.'; }
        if (!$this->validDate($dueDate)) { $errors['due_date'] = 'Enter a valid due date.'; }
        if ($copies < 1 || $copies > 20) { $errors['copies'] = 'Issue between 1 and 20 copies at a time.'; }
        if (mb_strlen($notes) > 255) { $errors['notes'] = 'Notes must be 255 characters or fewer.'; }
        if (!$errors) {
            if (strtotime($dueDate) < strtotime($issueDate)) { $errors['due_date'] = 'Due date cannot be before the issue date.'; }
            if (strtotime($dueDate) > strtotime($issueDate) + 365 * 86400) { $errors['due_date'] = 'Due date cannot be more than a year after the issue date.'; }
        }
        if ($errors) { $this->respond(false, 'Please correct the highlighted fields.', 'library', [], $errors); }

        try {
            $id = LibraryModule::issueBorrowing([
                'school_id'    => $schoolId,
                'book_id'      => $bookId,
                'borrower_type'=> $borrowerType,
                'student_id'   => $borrowerType === 'student' ? $studentId : null,
                'staff_id'     => $borrowerType === 'staff' ? $staffId : null,
                'issue_date'   => $issueDate,
                'due_date'     => $dueDate,
                'copies'       => $copies,
                'notes'        => $notes ?: null,
                'user_id'      => (int)Auth::user()['id'],
            ]);
        } catch (InvalidArgumentException $error) {
            $this->respond(false, $error->getMessage(), 'library', [], ['book_id' => 'This book cannot be issued.']);
        }

        audit('library', 'book_issued', 'borrowings', $id, 'Issued ' . $copies . ' copy/copies of book #' . $bookId
            . ' to ' . $borrowerType . ', due ' . $dueDate);
        $this->respond(true, 'Book issued. Due ' . date('d M Y', strtotime($dueDate)) . '.', 'library');
    }

    public function returnBorrowing(): void
    {
        if (!$this->allowed('edit')) {
            return;
        }
        $id        = (int)($_POST['id'] ?? 0);
        $condition = (string)($_POST['condition'] ?? '');
        if (!in_array($condition, ['good', 'worn', 'damaged', 'missing_pages'], true)) {
            $this->respond(false, 'Choose the condition the book was returned in.', 'library');
        }

        $ok = LibraryModule::returnBorrowing($id, school_id(), $condition, (int)Auth::user()['id']);
        if ($ok) {
            audit('library', 'book_returned', 'borrowings', $id, 'Book returned in condition: ' . $condition);
        }
        $this->respond(
            $ok,
            $ok ? 'Book returned and recorded as ' . str_replace('_', ' ', $condition) . '.' : 'This borrowing record could not be returned.',
            'library'
        );
    }

    public function storeFine(): void
    {
        if (!$this->allowed('add')) {
            return;
        }
        $schoolId    = school_id();
        $fineType    = (string)($_POST['fine_type'] ?? '');
        $borrowingId = (int)($_POST['borrowing_id'] ?? 0);
        $borrowerType = (string)($_POST['borrower_type'] ?? 'student');
        $studentId   = (int)($_POST['student_id'] ?? 0);
        $staffId     = (int)($_POST['staff_id'] ?? 0);
        $amountRaw   = (string)($_POST['amount'] ?? '');
        $amount      = is_numeric($amountRaw) ? (float)$amountRaw : -1.0;
        $days        = (int)($_POST['days_charged'] ?? 0);
        $reason      = trim((string)($_POST['reason'] ?? ''));

        $errors = [];
        if (!in_array($fineType, ['overdue', 'damaged', 'lost'], true)) { $errors['fine_type'] = 'Choose an overdue, damaged or lost fine.'; }
        if ($amount <= 0 || $amount > 99999999.99) { $errors['amount'] = 'Enter an amount greater than 0 (max 99,999,999.99).'; }
        if ($days < 0 || $days > 3650) { $errors['days_charged'] = 'Days charged must be between 0 and 3650.'; }
        if (mb_strlen($reason) > 255) { $errors['reason'] = 'Reason must be 255 characters or fewer.'; }

        $studentOut = null;
        $staffOut   = null;
        if ($borrowingId > 0) {
            $borrowing = LibraryModule::borrowingFor($borrowingId, $schoolId);
            if (!$borrowing) {
                $errors['borrowing_id'] = 'Choose a valid borrowing record.';
            } elseif ($borrowing['student_id'] === null && $borrowing['staff_id'] === null) {
                $errors['borrowing_id'] = 'The borrower of this record no longer exists. Choose a borrower directly instead.';
            } else {
                $studentOut = $borrowing['student_id'] !== null ? (int)$borrowing['student_id'] : null;
                $staffOut   = $borrowing['staff_id'] !== null ? (int)$borrowing['staff_id'] : null;
            }
        } elseif ($borrowerType === 'student') {
            if (!LibraryModule::validStudent($studentId, $schoolId)) { $errors['student_id'] = 'Choose a valid active student.'; }
            else { $studentOut = $studentId; }
        } elseif ($borrowerType === 'staff') {
            if (!LibraryModule::validStaff($staffId, $schoolId)) { $errors['staff_id'] = 'Choose a valid staff member.'; }
            else { $staffOut = $staffId; }
        } else {
            $errors['borrower_type'] = 'Choose a student or staff borrower.';
        }

        if ($errors) { $this->respond(false, 'Please correct the highlighted fields.', 'library', [], $errors); }

        $id = LibraryModule::createFine([
            'school_id'    => $schoolId,
            'borrowing_id' => $borrowingId > 0 ? $borrowingId : null,
            'student_id'   => $studentOut,
            'staff_id'     => $staffOut,
            'fine_type'    => $fineType,
            'days_charged' => $days,
            'amount'       => $amount,
            'reason'       => $reason ?: null,
            'user_id'      => (int)Auth::user()['id'],
        ]);

        audit('library', 'fine_recorded', 'library_fines', $id, 'Recorded ' . $fineType . ' fine of UGX ' . number_format($amount, 2));
        $this->respond(true, 'Fine recorded.', 'library');
    }

    public function markFinePaid(): void
    {
        if (!$this->allowed('edit')) {
            return;
        }
        $id = (int)($_POST['id'] ?? 0);
        $ok = LibraryModule::markFinePaid($id, school_id());
        if ($ok) {
            audit('library', 'fine_paid', 'library_fines', $id, 'Fine marked as paid');
        }
        $this->respond($ok, $ok ? 'Fine marked as paid.' : 'Could not update this fine.', 'library');
    }

    private function validDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }

    protected function allowed(string $module = 'library', string $action = 'view'): bool
    {
        if (Auth::can('library', $action)) {
            return true;
        }
        $this->respond(false, 'You do not have permission to access this library action.', 'dashboard');
        return false;
    }

    public function libraryOverdueNotice(): void
    {
        $schoolId = school_id();
        $studentId = (int)($_GET['student_id'] ?? 0);
        $db = Database::conn();
        $student = Database::query(
            'SELECT s.*, c.name AS class_name, st.name AS stream_name
               FROM students s
               LEFT JOIN classes c ON c.id = s.current_class_id
               LEFT JOIN streams st ON st.id = s.current_stream_id
              WHERE s.id = ? AND s.school_id = ?',
            [$studentId, $schoolId]
        )->fetch();
        if (!$student) {
            $this->respond(false, 'Student not found.', 'library');
            return;
        }
        $books = Database::query(
            'SELECT bk.title, bc.accession_number, b.due_date,
                    DATEDIFF(CURRENT_DATE, b.due_date) AS days_overdue,
                    COALESCE(f.amount, 0) AS fine
               FROM borrowings b
               JOIN book_copies bc ON bc.id = b.copy_id
               JOIN books bk ON bk.id = bc.book_id
               LEFT JOIN library_fines f ON f.borrowing_id = b.id
              WHERE b.student_id = ? AND b.return_date IS NULL
                AND b.due_date < CURRENT_DATE
              ORDER BY b.due_date',
            [$studentId]
        )->fetchAll();
        $school = school_row() ?? [];
        $html = PdfService::libraryOverdueNoticeHtml($school, $student, $books);
        PdfService::output($html, 'overdue_notice_'.$student['admission_number'].'.pdf');
    }
}
