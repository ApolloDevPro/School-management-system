<?php
declare(strict_types=1);

final class ReportCardController extends Controller
{
    public function index(): void
    {
        if (!$this->allowed('academics', 'view')) {
            return;
        }

        $schoolId = school_id();
        $terms    = ReportCard::termsForSchool($schoolId);
        $classes  = Examination::classes($schoolId);

        $termId   = (int)($_GET['term_id'] ?? 0);
        $classId  = (int)($_GET['class_id'] ?? 0);
        $streamId = (int)($_GET['stream_id'] ?? 0);
        $examId   = (int)($_GET['exam_id'] ?? 0);

        $exams    = [];
        $students = [];
        $streams  = [];

        if ($termId > 0) {
            $exams = ReportCard::examsForTerm($termId, $schoolId);
        }
        if ($classId > 0 && ($examId > 0 || $termId > 0)) {
            $streamId = $streamId > 0 ? $streamId : null;
            $streams  = Examination::streams($classId);
            $students = ReportCard::studentsForReportCard($classId, $streamId, $schoolId);
        }

        if (($_GET['export'] ?? '') === 'csv') {
            if ($classId < 1 || $examId < 1) {
                $this->respond(false, 'Choose a class and an exam before exporting.', 'report_cards');
            }
            $this->exportCsv($students, $examId, $classId, $streamId ? (int)$streamId : null, $schoolId);
        }

        $this->render('report_cards/index', [
            'pageTitle'   => 'Report Cards',
            'activeNav'   => 'academics',
            'terms'       => $terms,
            'classes'     => $classes,
            'exams'       => $exams,
            'streams'     => $streams,
            'students'    => $students,
            'termId'      => $termId,
            'classId'     => $classId,
            'streamId'    => $streamId,
            'examId'      => $examId,
        ]);
    }

    public function view(): void
    {
        if (!$this->allowed('academics', 'view')) {
            return;
        }

        $schoolId = school_id();
        $studentId = (int)($_GET['student_id'] ?? 0);
        $examId    = (int)($_GET['exam_id'] ?? 0);

        if ($studentId < 1 || $examId < 1) {
            $this->respond(false, 'Invalid student or exam.', 'report_cards');
            return;
        }

        $card = ReportCard::studentResultCard($studentId, $examId, $schoolId);
        if (!$card) {
            $this->respond(false, 'Report card not found.', 'report_cards');
            return;
        }

        $this->render('report_cards/view', [
            'pageTitle'   => 'Report Card - ' . $card['student']['admission_number'],
            'activeNav'   => 'academics',
            'card'        => $card,
            'canEdit'     => Auth::can('academics', 'edit'),
        ]);
    }

    public function saveComments(): void
    {
        if (!$this->allowed('academics', 'edit')) {
            return;
        }

        $schoolId  = school_id();
        $studentId = (int)($_POST['student_id'] ?? 0);
        $examId    = (int)($_POST['exam_id'] ?? 0);

        if ($studentId < 1 || $examId < 1) {
            $this->respond(false, 'Invalid student or exam.', 'report_cards');
        }

        $teacher = trim((string)($_POST['teacher_remarks'] ?? ''));
        $head    = trim((string)($_POST['head_remarks'] ?? ''));
        $teacher = $teacher === '' ? null : mb_substr($teacher, 0, 255);
        $head    = $head === '' ? null : mb_substr($head, 0, 255);

        $back = ['student_id' => $studentId, 'exam_id' => $examId];
        if (!ReportCard::saveComments($studentId, $examId, $schoolId, $teacher, $head)) {
            $this->respond(false, 'Comments can only be saved after the exam results have been processed.', 'report_cards/view', $back);
        }

        audit('academics', 'update', 'result_summaries', null, 'Updated report card comments for student ' . $studentId . ' on exam ' . $examId);
        $this->respond(true, 'Comments saved.', 'report_cards/view', $back);
    }

    public function pdf(): void
    {
        if (!$this->allowed('academics', 'view')) {
            return;
        }

        $schoolId = school_id();
        $studentId = (int)($_GET['student_id'] ?? 0);
        $examId    = (int)($_GET['exam_id'] ?? 0);

        if ($studentId < 1 || $examId < 1) {
            $this->respond(false, 'Invalid student or exam.', 'report_cards');
            return;
        }

        $card = ReportCard::studentResultCard($studentId, $examId, $schoolId);
        if (!$card) {
            $this->respond(false, 'Report card not found.', 'report_cards');
            return;
        }

        $html = $this->renderPdfHtml($card);

        $fileName = 'report_card_' . $card['student']['admission_number'] . '_' . $card['exam']['name'] . '.pdf';
        $fileName = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $fileName);

        PdfService::output($html, $fileName, true);
    }

    /**
     * Printable batch of every processed report card for one class/stream and exam.
     * Output is a plain HTML page (one A4 page per student) that the browser can
     * print to paper or save as a single PDF.
     */
    public function batch(): void
    {
        if (!$this->allowed('academics', 'view')) {
            return;
        }

        $schoolId = school_id();
        $classId  = (int)($_GET['class_id'] ?? 0);
        $examId   = (int)($_GET['exam_id'] ?? 0);
        $streamId = (int)($_GET['stream_id'] ?? 0);

        if ($classId < 1 || $examId < 1) {
            flash_set('danger', 'Choose a class and an exam before printing a batch.');
            redirect('report_cards');
        }

        $exam = Examination::examInSchool($examId, $schoolId);
        if (!$exam) {
            flash_set('danger', 'Exam not found.');
            redirect('report_cards');
        }

        $students = ReportCard::studentsForReportCard($classId, $streamId > 0 ? $streamId : null, $schoolId);
        $cards = [];
        foreach ($students as $student) {
            $card = ReportCard::studentResultCard((int)$student['id'], $examId, $schoolId);
            if ($card && $card['summary']) {
                $cards[] = $card;
            }
        }

        if (!$cards) {
            flash_set('danger', 'No processed results were found for this class and exam. Process the results first.');
            redirect('report_cards', ['term_id' => (int)$exam['term_id'], 'class_id' => $classId, 'exam_id' => $examId]);
        }

        security_headers();
        header('Content-Type: text/html; charset=utf-8');
        echo $this->renderBatchHtml($cards, $exam, $classId, $streamId);
        exit;
    }

    private function exportCsv(array $students, int $examId, int $classId, ?int $streamId, int $schoolId): never
    {
        if (!$students) {
            $this->respond(false, 'There are no active students in this class to export.', 'report_cards');
        }

        $summaries = ReportCard::summariesForClass($examId, $classId, $streamId, $schoolId);
        $ranked = [];
        foreach ($summaries as $studentId => $row) {
            if ($row['average_percent'] !== null) {
                $ranked[$studentId] = (float)$row['average_percent'];
            }
        }
        arsort($ranked);
        $positions = [];
        $rank = 0;
        $previous = null;
        $index = 0;
        foreach ($ranked as $studentId => $average) {
            $index++;
            if ($previous === null || $average < $previous) {
                $rank = $index;
                $previous = $average;
            }
            $positions[$studentId] = $rank;
        }

        $fileName = 'report_cards_class' . $classId . '_exam' . $examId . '_' . date('Ymd_His') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Cache-Control: no-cache');

        $out = fopen('php://output', 'w');
        if ($out === false) {
            $this->respond(false, 'Could not open the export stream.', 'report_cards');
        }
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Admission No', 'Student', 'Class', 'Stream', 'Subjects taken', 'Subjects passed', 'Average %', 'Total points', 'Position'], ',', '"', '');

        foreach ($students as $student) {
            $studentId = (int)$student['id'];
            $summary = $summaries[$studentId] ?? null;
            fputcsv($out, [
                $student['admission_number'],
                trim(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? '')),
                $student['class_name'] ?? '',
                $student['stream_name'] ?? '',
                $summary['subjects_taken'] ?? '',
                $summary['subjects_passed'] ?? '',
                ($summary['average_percent'] ?? null) !== null ? number_format((float)$summary['average_percent'], 1) : '',
                ($summary['total_points'] ?? null) !== null ? number_format((float)$summary['total_points'], 1) : '',
                $positions[$studentId] ?? '',
            ], ',', '"', '');
        }

        fclose($out);
        exit;
    }

    private function renderPdfHtml(array $card): string
    {
        return '<!doctype html><html><head><meta charset="utf-8"><title>Report Card</title>'
            . PdfService::CSS
            . '</head><body>'
            . $this->renderCardHtml($card)
            . '</body></html>';
    }

    private function renderBatchHtml(array $cards, array $exam, int $classId, int $streamId): string
    {
        $back = url('report_cards', ['class_id' => $classId, 'stream_id' => $streamId, 'exam_id' => (int)$exam['id']]);

        $html = '<!doctype html><html><head><meta charset="utf-8"><title>Report Cards - ' . e((string)$exam['name']) . '</title>'
            . PdfService::CSS
            . '<style>
                body { background: #eef2f7; }
                .print-toolbar { position: sticky; top: 0; z-index: 20; background: #1e3a8a; color: #ffffff;
                    padding: 10px 18px; display: flex; align-items: center; gap: 12px; }
                .print-toolbar h1 { font-size: 12pt; margin: 0; font-weight: 600; }
                .print-toolbar .spacer { flex: 1; }
                .print-toolbar button, .print-toolbar a { border: 0; border-radius: 6px; padding: 7px 14px;
                    font-size: 9pt; text-decoration: none; cursor: pointer; }
                .print-toolbar button { background: #ffffff; color: #1e3a8a; font-weight: 700; }
                .print-toolbar a { background: rgba(255,255,255,.16); color: #ffffff; }
                .batch-page { background: #ffffff; max-width: 210mm; margin: 14px auto; padding: 10mm 12mm;
                    box-shadow: 0 2px 10px rgba(15,23,42,.12); }
                @media print {
                    body { background: #ffffff; }
                    .no-print { display: none !important; }
                    .batch-page { box-shadow: none; margin: 0; max-width: none; padding: 0;
                        page-break-after: always; }
                    .batch-page:last-child { page-break-after: auto; }
                }
            </style>'
            . '</head><body>'
            . '<div class="print-toolbar no-print">'
            . '<h1>' . e((string)$exam['name']) . ' &middot; ' . count($cards) . ' report card(s)</h1>'
            . '<div class="spacer"></div>'
            . '<button type="button" onclick="window.print()">Print all</button>'
            . '<a href="' . e($back) . '">Back to list</a>'
            . '</div>';

        foreach ($cards as $card) {
            $html .= '<div class="batch-page">' . $this->renderCardHtml($card) . '</div>';
        }

        return $html . '</body></html>';
    }

    private function renderCardHtml(array $card): string
    {
        $student = $card['student'];
        $exam    = $card['exam'];
        $results = $card['results'];
        $summary = $card['summary'];
        $school  = school_row() ?? [];

        $rows = '';
        $totalPercent = 0.0;
        $count = 0;
        foreach ($results as $r) {
            $percent = (float)($r['weighted_percent'] ?? $r['mark_percent'] ?? 0);
            $totalPercent += $percent;
            $count++;
            $ca = $r['ca_mark'] !== null ? number_format((float)$r['ca_mark'], 1) : '—';
            $examMark = $r['exam_mark'] !== null ? number_format((float)$r['exam_mark'], 1) : '—';
            $rows .= '<tr>'
                . '<td>' . e($r['subject_code'] ?? '') . '</td>'
                . '<td>' . e($r['subject_name'] ?? '') . '</td>'
                . '<td class="text-center">' . e($ca) . '</td>'
                . '<td class="text-center">' . e($examMark) . '</td>'
                . '<td class="text-right">' . number_format($percent, 1) . '</td>'
                . '<td class="text-center"><strong>' . e($r['grade_code'] ?? '—') . '</strong></td>'
                . '<td>' . e($r['grade_name'] ?? '—') . '</td>'
                . '<td>' . e($r['mark_remarks'] ?? '') . '</td>'
                . '</tr>';
        }

        $average = $count > 0 ? round($totalPercent / $count, 1) : 0;
        $totalPoints = $summary['total_points'] ?? null;

        $guardian = $card['guardian'] ?? null;
        $guardianName = $guardian ? e($guardian['full_name'] ?? ($guardian['first_name'] . ' ' . $guardian['last_name'])) : '—';
        $guardianPhone = $guardian ? e($guardian['phone'] ?? '') : '—';

        $promotionStatus = 'Promoted';
        if ($summary && (float)($summary['average_percent'] ?? 0) < 50) {
            $promotionStatus = 'Review required';
        }

        $position = $card['position'] ?? null;
        $classSize = $card['class_size'] ?? null;
        $streamPosition = $card['stream_position'] ?? null;
        $streamSize = $card['stream_size'] ?? null;
        $positionLabel = $position !== null && $classSize ? $position . ' / ' . $classSize : '—';
        $streamLabel = ($streamPosition !== null && $streamSize && $streamSize > 1)
            ? $streamPosition . ' / ' . $streamSize
            : '—';

        $teacherComment = trim((string)($summary['teacher_remarks'] ?? ''));
        $headComment = trim((string)($summary['head_remarks'] ?? ''));

        $schoolName = e($school['name'] ?? APP_NAME);
        $schoolAddress = e($school['physical_address'] ?? '');
        $schoolPhone = e($school['phone'] ?? '');
        $schoolEmail = e($school['email'] ?? '');

        return '<div class="pdf-header">'
            . '<div>'
            . '<div class="pdf-title">' . $schoolName . ' — Report Card</div>'
            . '<div class="pdf-sub">' . e($exam['name'] ?? '') . ' &middot; ' . e($exam['status'] ?? '') . '</div>'
            . '</div>'
            . '<div class="school-info">'
            . $schoolName . '<br>'
            . $schoolAddress . '<br>'
            . $schoolPhone . ' &middot; ' . $schoolEmail
            . '</div>'
            . '</div>'

            . '<table class="pdf-table" style="margin-top:16px">'
            . '<thead><tr>'
            . '<th style="width:22%">Student</th><th style="width:18%">Admission No.</th>'
            . '<th style="width:20%">Class / Stream</th><th style="width:20%">House</th>'
            . '<th style="width:20%">Exam</th>'
            . '</tr></thead>'
            . '<tbody><tr>'
            . '<td>' . e($student['first_name'] . ' ' . $student['last_name']) . '</td>'
            . '<td>' . e($student['admission_number']) . '</td>'
            . '<td>' . e($student['class_name'] ?? '') . ' / ' . e($student['stream_name'] ?? '—') . '</td>'
            . '<td>' . e($student['house_name'] ?? '—') . '</td>'
            . '<td>' . e($exam['name'] ?? '') . '</td>'
            . '</tr></tbody></table>'

            . '<table class="pdf-table" style="margin-top:16px">'
            . '<thead><tr>'
            . '<th style="width:10%">Code</th><th style="width:26%">Subject</th>'
            . '<th style="width:12%" class="text-center">CA</th>'
            . '<th style="width:12%" class="text-center">Exam</th>'
            . '<th style="width:12%" class="text-right">Total %</th>'
            . '<th style="width:10%" class="text-center">Grade</th>'
            . '<th style="width:14%">Grade Name</th>'
            . '<th>Remarks</th>'
            . '</tr></thead>'
            . '<tbody>' . $rows . '</tbody>'
            . '<tfoot><tr>'
            . '<td colspan="4"><strong>Average</strong></td>'
            . '<td class="text-right"><strong>' . number_format($average, 1) . '%</strong></td>'
            . '<td class="text-center"><strong>' . ($totalPoints !== null ? number_format($totalPoints, 1) : '—') . '</strong></td>'
            . '<td colspan="2">' . e($promotionStatus) . '</td>'
            . '</tr></tfoot></table>'

            . '<div class="stat-row" style="margin-top:20px">'
            . '<div class="stat-col"><div class="stat-label">Guardian</div><div class="stat-value">' . $guardianName . '</div></div>'
            . '<div class="stat-col"><div class="stat-label">Phone</div><div class="stat-value">' . $guardianPhone . '</div></div>'
            . '<div class="stat-col"><div class="stat-label">Class position</div><div class="stat-value">' . e($positionLabel) . '</div></div>'
            . '<div class="stat-col"><div class="stat-label">Stream position</div><div class="stat-value">' . e($streamLabel) . '</div></div>'
            . '<div class="stat-col"><div class="stat-label">Subjects</div><div class="stat-value">' . $count . '</div></div>'
            . '</div>'

            . '<div style="margin-top:24px">'
            . '<h3 style="font-size:11pt;margin-bottom:8px">Teacher’s Comment</h3>'
            . '<div style="border:1px solid #e5e7eb;border-radius:6px;padding:12px;min-height:60px;color:#4b5563">'
            . ($teacherComment !== '' ? nl2br(e($teacherComment)) : '&nbsp;')
            . '</div>'
            . '</div>'

            . '<div style="margin-top:24px">'
            . '<h3 style="font-size:11pt;margin-bottom:8px">Head Teacher’s Comment</h3>'
            . '<div style="border:1px solid #e5e7eb;border-radius:6px;padding:12px;min-height:60px;color:#4b5563">'
            . ($headComment !== '' ? nl2br(e($headComment)) : '&nbsp;')
            . '</div>'
            . '</div>'

            . '<div style="margin-top:30px;border-top:1px solid #e5e7eb;padding-top:10px;font-size:7.5pt;color:#6b7280">'
            . 'Signed: ____________________ Class Teacher &nbsp;&nbsp;|&nbsp;&nbsp; Signed: ____________________ Head Teacher<br>'
            . 'Date: ' . date('d M Y') . '&nbsp;&nbsp;|&nbsp;&nbsp; Next term opens: ' . e(date('d M Y', strtotime('+1 month'))) . '<br>'
            . e((string)(Setting::get((int)($school['id'] ?? 1), 'reporting.report_card_footer', 'Generated by SSMS') ?? 'Generated by SSMS'))
            . '</div>';
    }
}
