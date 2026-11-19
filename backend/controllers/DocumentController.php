<?php
declare(strict_types=1);

/**
 * Student document uploads, verification and deletion.
 *
 * Backend controller: receives routed requests, enforces permissions and
 * renders a view or returns JSON through the base Controller helpers.
 */
final class DocumentController extends Controller
{
    public function index(): void
    {
        $schoolId = school_id();
        $type     = trim((string)($_GET['type'] ?? ''));
        $studentId = (int)($_GET['student_id'] ?? 0);

        $documents = StudentDocument::listForSchool($schoolId, $type);
        if ($studentId > 0) {
            $documents = array_values(array_filter($documents, fn (array $d): bool => (int)$d['student_id'] === $studentId));
        }

        $this->render('students/documents', [
            'pageTitle' => 'Student documents',
            'activeNav' => 'students/documents',
            'documents' => $documents,
            'type'      => $type,
            'studentId' => $studentId,
            'students'  => Student::listForSchool($schoolId),
            'canAdd'    => Auth::can('students', 'add'),
            'canEdit'   => Auth::can('students', 'edit'),
        ]);
    }

    public function upload(): void
    {
        $schoolId = school_id();
        $studentId = (int)($_POST['student_id'] ?? 0);
        $student   = $studentId > 0 ? Student::find($studentId) : null;
        if (!$student || (int)$student['school_id'] !== $schoolId) {
            $this->respond(false, 'Choose a valid student.', 'students/documents', [], ['student_id' => 'Required.']);
        }

        $docType = (string)($_POST['doc_type'] ?? 'other');
        $types = ['admission_letter', 'birth_certificate', 'previous_report', 'ple_slip', 'uce_slip', 'uace_slip', 'nin', 'passport_photo', 'medical_form', 'transfer_letter', 'other'];
        if (!in_array($docType, $types, true)) {
            $docType = 'other';
        }

        $title = trim((string)($_POST['title'] ?? ''));
        if ($title === '') {
            $this->respond(false, 'Enter a title for the document.', 'students/documents', [], ['title' => 'Required.']);
        }

        $file = $_FILES['file'] ?? null;
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->respond(false, 'Choose a file to upload.', 'students/documents', [], ['file' => 'A file is required.']);
        }

        if ($file['size'] > 5 * 1024 * 1024) {
            $this->respond(false, 'The file must be 5 MB or smaller.', 'students/documents', [], ['file' => 'Maximum size is 5 MB.']);
        }

        $allowed = [
            'application/pdf' => 'pdf',
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        ];
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if (!isset($allowed[$mime])) {
            $this->respond(false, 'Upload a PDF, image or Word document.', 'students/documents', [], ['file' => 'Unsupported file type.']);
        }

        $dir = dirname(__DIR__, 2) . '/uploads/students/' . $studentId;
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            $this->respond(false, 'The uploads folder could not be created.', 'students/documents');
        }

        $filename = 'doc-' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
        if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $filename)) {
            $this->respond(false, 'The file could not be saved on the server.', 'students/documents');
        }

        $path = 'uploads/students/' . $studentId . '/' . $filename;

        $id = StudentDocument::insert([
            'student_id'  => $studentId,
            'doc_type'    => $docType,
            'title'       => $title,
            'file_path'   => $path,
            'file_size'   => (int)$file['size'],
            'uploaded_by' => (int)Auth::user()['id'],
        ]);

        audit('students', 'upload', 'student_documents', $id, 'Uploaded "' . $title . '" for ' . $student['admission_number']);

        $this->respond(true, 'Document uploaded.', 'students/documents', ['student_id' => $studentId]);
    }

    public function verify(): void
    {
        $id  = (int)($_POST['id'] ?? 0);
        $doc = $this->documentInSchool($id);
        if (!$doc) {
            $this->respond(false, 'That document no longer exists.', 'students/documents');
        }

        $new = (int)$doc['is_verified'] ? 0 : 1;
        StudentDocument::updateRow($id, [
            'is_verified' => $new,
            'verified_by' => $new ? (int)Auth::user()['id'] : null,
        ]);

        audit('students', $new ? 'verify' : 'unverify', 'student_documents', $id,
            ($new ? 'Verified' : 'Unverified') . ' document "' . $doc['title'] . '"');

        $this->respond(true, $new ? 'Document verified.' : 'Verification removed.', 'students/documents');
    }

    public function delete(): void
    {
        $id  = (int)($_POST['id'] ?? 0);
        $doc = $this->documentInSchool($id);
        if (!$doc) {
            $this->respond(false, 'That document no longer exists.', 'students/documents');
        }

        $full = dirname(__DIR__, 2) . '/' . ltrim($doc['file_path'], '/');
        if (is_file($full)) {
            @unlink($full);
        }

        StudentDocument::execute('DELETE FROM student_documents WHERE id = ?', [$id]);
        audit('students', 'delete', 'student_documents', $id, 'Deleted document "' . $doc['title'] . '"');

        $this->respond(true, 'Document deleted.', 'students/documents');
    }

    private function documentInSchool(int $id): ?array
    {
        $doc = $id > 0 ? StudentDocument::find($id) : null;
        if (!$doc) {
            return null;
        }
        $student = Student::find((int)$doc['student_id']);
        if (!$student || (int)$student['school_id'] !== school_id()) {
            return null;
        }
        return $doc;
    }
}
