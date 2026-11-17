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
        ]);
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
        $fileName = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $fileName) . '.pdf';

        PdfService::output($html, $fileName, true);
    }

    private function renderPdfHtml(array $card): string
    {
        $student = $card['student'];
        $exam    = $card['exam'];
        $results = $card['results'];
        $summary = $card['summary'];
        $school  = school_row() ?? [];

        $currency = $school['currency_symbol'] ?? 'Ugx';

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

        $schoolName = e($school['name'] ?? APP_NAME);
        $schoolAddress = e($school['physical_address'] ?? '');
        $schoolPhone = e($school['phone'] ?? '');
        $schoolEmail = e($school['email'] ?? '');

        return '<!doctype html><html><head><meta charset="utf-8"><title>Report Card</title>'
            . PdfService::CSS
            . '</head><body>'
            . '<div class="pdf-header">'
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
            . '<th style="width:20%">Term / Year</th>'
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
            . '<div class="stat-col"><div class="stat-label">Status</div><div class="stat-value">' . e($promotionStatus) . '</div></div>'
            . '<div class="stat-col"><div class="stat-label">Subjects</div><div class="stat-value">' . $count . '</div></div>'
            . '</div>'

            . '<div style="margin-top:24px">'
            . '<h3 style="font-size:11pt;margin-bottom:8px">Teacher’s Comment</h3>'
            . '<div style="border:1px solid #e5e7eb;border-radius:6px;padding:12px;min-height:60px;color:#4b5563">'
            . 'Keep up the good work. Continue to revise regularly and submit assignments on time.'
            . '</div>'
            . '</div>'

            . '<div style="margin-top:24px">'
            . '<h3 style="font-size:11pt;margin-bottom:8px">Head Teacher’s Comment</h3>'
            . '<div style="border:1px solid #e5e7eb;border-radius:6px;padding:12px;min-height:60px;color:#4b5563">'
            . 'Satisfactory progress. Encouraged to maintain discipline and focus in the next term.'
            . '</div>'
            . '</div>'

            . '<div style="margin-top:30px;border-top:1px solid #e5e7eb;padding-top:10px;font-size:7.5pt;color:#6b7280">'
            . 'Signed: ____________________ Class Teacher &nbsp;&nbsp;|&nbsp;&nbsp; Signed: ____________________ Head Teacher<br>'
            . 'Date: ' . date('d M Y') . '&nbsp;&nbsp;|&nbsp;&nbsp; Next term opens: ' . e(date('d M Y', strtotime('+1 month'))) . '<br>'
            . e((string)(Setting::get((int)($school['id'] ?? 1), 'reporting.report_card_footer', 'Generated by SSMS') ?? 'Generated by SSMS'))
            . '</div>'

            . '</body></html>';
    }
}
