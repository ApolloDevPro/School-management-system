<?php
declare(strict_types=1);

/**
 * SSMS Import/Export Service
 * CSV import and export for students, staff, fee invoices, payments and reports.
 * Native PHP (fgetcsv/fputcsv) - no Composer dependencies required.
 */
final class ImportExportService
{
    public static function exportCsv(string $filename, array $headers, array $rows): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="'.basename($filename).'"');
        header('Cache-Control: no-cache');

        $out = fopen('php://output', 'w');
        fputcsv($out, $headers);
        foreach ($rows as $row) {
            fputcsv($out, array_values($row));
        }
        fclose($out);
        exit;
    }

    public static function studentsExport(int $schoolId): void
    {
        $rows = Student::listForSchool($schoolId, []);
        $data = [];
        foreach ($rows as $s) {
            $data[] = [
                $s['admission_number'] ?? '',
                $s['first_name'] ?? '',
                $s['last_name'] ?? '',
                $s['gender'] ?? '',
                $s['date_of_birth'] ?? '',
                $s['class_name'] ?? '',
                $s['stream_name'] ?? '',
                $s['house_name'] ?? '',
                $s['status'] ?? '',
                $s['admission_date'] ?? '',
            ];
        }
        self::exportCsv('students_export.csv',
            ['Admission No','First Name','Last Name','Gender','Date of Birth','Class','Stream','House','Status','Admission Date'],
            $data);
    }

    public static function staffExport(int $schoolId): void
    {
        $rows = Staff::forSchool($schoolId);
        $data = [];
        foreach ($rows as $s) {
            $data[] = [
                $s['staff_number'] ?? '',
                $s['first_name'] ?? '',
                $s['last_name'] ?? '',
                $s['middle_name'] ?? '',
                $s['phone'] ?? '',
                $s['email'] ?? '',
                $s['position'] ?? '',
                $s['employment_type'] ?? '',
                $s['employment_status'] ?? '',
                $s['department_name'] ?? '',
                $s['date_of_appointment'] ?? '',
            ];
        }
        self::exportCsv('staff_export.csv',
            ['Staff No','First Name','Last Name','Middle Name','Phone','Email','Position','Employment Type','Status','Department','Date of Appointment'],
            $data);
    }

    public static function financeExport(int $schoolId): void
    {
        $invoices = FinanceModel::invoices($schoolId);
        $data = [];
        foreach ($invoices as $i) {
            $data[] = [
                $i['invoice_number'] ?? '',
                $i['admission_number'] ?? '',
                $i['first_name'] ?? '',
                $i['last_name'] ?? '',
                $i['year_name'] ?? '',
                $i['term_name'] ?? '',
                $i['issue_date'] ?? '',
                $i['due_date'] ?? '',
                number_format((float)$i['total_amount'], 2),
                number_format((float)$i['paid_amount'], 2),
                number_format((float)$i['balance'], 2),
                $i['status'] ?? '',
            ];
        }
        self::exportCsv('finance_export.csv',
            ['Invoice No','Admission No','First Name','Last Name','Year','Term','Issue Date','Due Date','Total','Paid','Balance','Status'],
            $data);
    }

    public static function importStudents(array $fileRows, int $schoolId, int $createdBy): array
    {
        $created = 0;
        $errors = [];
        foreach ($fileRows as $lineNo => $row) {
            if ($lineNo === 1) { continue; } // header
            $row = array_map('trim', $row);
            if (empty($row[0]) && empty($row[1]) && empty($row[2])) { continue; }

            try {
                $admission = $row[0] ?? '';
                $first = $row[1] ?? '';
                $last = $row[2] ?? '';
                $gender = $row[3] ?? '';
                $dob = $row[4] ?? null;
                $className = $row[5] ?? null;
                $status = $row[8] ?? 'active';

                if ($admission === '' || $first === '' || $last === '') {
                    $errors[] = 'Line '.($lineNo + 1).': missing admission number or name';
                    continue;
                }

                $classId = null;
                if ($className !== null && $className !== '') {
                    $c = SchoolClass::one('SELECT id FROM classes WHERE school_id = ? AND name = ?', [$schoolId, $className]);
                    if ($c) { $classId = (int)$c['id']; }
                }

                $id = Student::insert([
                    'school_id' => $schoolId,
                    'admission_number' => $admission,
                    'first_name' => $first,
                    'last_name' => $last,
                    'gender' => $gender,
                    'date_of_birth' => $dob,
                    'current_class_id' => $classId,
                    'status' => in_array($status, ['active','suspended','withdrawn'], true) ? $status : 'active',
                    'admission_date' => $row[9] ?? date('Y-m-d'),
                    'created_by' => $createdBy,
                ]);
                $created++;
            } catch (Throwable $e) {
                $errors[] = 'Line '.($lineNo + 1).': '.$e->getMessage();
            }
        }
        return ['created' => $created, 'errors' => $errors];
    }

    public static function parseUploadedCsv(string $tmpPath): array
    {
        $rows = [];
        $handle = fopen($tmpPath, 'r');
        if (!$handle) { return $rows; }
        while (($row = fgetcsv($handle, 0, ",")) !== false) {
            $rows[] = $row;
        }
        fclose($handle);
        return $rows;
    }
}
