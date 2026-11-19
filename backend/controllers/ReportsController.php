<?php
declare(strict_types=1);

/**
 * Analytics screens plus CSV and PDF export built on the reporting views.
 *
 * Backend controller: receives routed requests, enforces permissions and
 * renders a view or returns JSON through the base Controller helpers.
 */
final class ReportsController extends Controller
{
    public function index(): void
    {
        $schoolId  = school_id();
        $tab       = (string)($_GET['tab'] ?? 'overview');
        $activeYear = ReportsModule::activeYear($schoolId);
        $yearId    = (int)($_GET['year_id'] ?? ($activeYear['id'] ?? 0));
        $examId    = (int)($_GET['exam_id'] ?? 0);
        $date      = (string)($_GET['date'] ?? date('Y-m-d'));
        $dateFrom  = (string)($_GET['date_from'] ?? date('Y-m-01'));
        $dateTo    = (string)($_GET['date_to'] ?? date('Y-m-d'));

        $data = $this->buildTabData($tab, $schoolId, $yearId, $examId, $date, $dateFrom, $dateTo);

        $this->render('reports/index', [
            'pageTitle'   => 'Reports & analytics',
            'activeNav'   => 'reports',
            'tab'         => $tab,
            'years'       => ReportsModule::years($schoolId),
            'exams'       => ReportsModule::exams($schoolId),
            'activeYear'  => $activeYear,
            'yearId'      => $yearId,
            'examId'      => $examId,
            'date'        => $date,
            'dateFrom'    => $dateFrom,
            'dateTo'      => $dateTo,
            'data'        => $data,
        ]);
    }

    public function export(): void
    {
        // Permission already enforced by router; this just streams CSV
        $schoolId   = school_id();
        $report     = (string)($_GET['report'] ?? '');
        $yearId     = (int)($_GET['year_id'] ?? 0);
        $examId     = (int)($_GET['exam_id'] ?? 0);
        $dateFrom   = (string)($_GET['date_from'] ?? date('Y-m-01'));
        $dateTo     = (string)($_GET['date_to'] ?? date('Y-m-d'));

        [$rows, $columns, $filename] = $this->exportData($report, $schoolId, $yearId, $examId, $dateFrom, $dateTo);

        audit('reports', 'exported', 'reports', 0, 'CSV export: ' . $report);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, no-store');

        $f = fopen('php://output', 'w');
        fputcsv($f, $columns);
        foreach ($rows as $row) {
            fputcsv($f, array_values($row));
        }
        fclose($f);
        exit;
    }

    public function exportPdf(): void
    {
        $schoolId   = school_id();
        $report     = (string)($_GET['report'] ?? 'overview');
        $yearId     = (int)($_GET['year_id'] ?? 0);
        $examId     = (int)($_GET['exam_id'] ?? 0);
        $dateFrom   = (string)($_GET['date_from'] ?? date('Y-m-01'));
        $dateTo     = (string)($_GET['date_to'] ?? date('Y-m-d'));
        $date       = (string)($_GET['date'] ?? date('Y-m-d'));

        $db = Database::conn();
        $viewExists = function (string $view) use ($db): bool {
            $stmt = $db->prepare("SELECT COUNT(*) AS c FROM information_schema.views WHERE table_schema = DATABASE() AND table_name = ?");
            $stmt->execute([$view]);
            $row = $stmt->fetch();
            return (int)($row['c'] ?? 0) > 0;
        };
        $hasContractView = $viewExists('vw_contract_expiry_alerts');
        $hasLicenceView  = $viewExists('vw_licence_expiry_alerts');

        $school = school_row() ?? [];
        $currency = $school['currency_symbol'] ?? 'Ugx';

        $rows = [];
        $columns = [];
        $title = 'Report';

        switch ($report) {
            case 'students':
                $title = 'Student Placement Report';
                $rows = ReportsModule::studentPlacement($schoolId);
                $columns = ['Admission #', 'Full name', 'Gender', 'Class', 'Stream', 'House', 'Status', 'Type'];
                break;
            case 'debtors':
                $title = 'Fee Debtors Report';
                $rows = $yearId ? ReportsModule::debtors($schoolId, $yearId) : [];
                $columns = ['Student', 'Adm. #', 'Class', 'Total charged', 'Total paid', 'Balance', 'Overdue', 'Days overdue', 'Guardian', 'Phone'];
                break;
            case 'collection':
                $title = 'Fee Collection Report';
                $rows = ReportsModule::feeCollection($schoolId, $dateFrom, $dateTo);
                $columns = ['Date', 'Channel', 'Method', 'Transactions', 'Amount collected'];
                break;
            case 'library':
                $title = 'Library Stock Report';
                $rows = ReportsModule::libraryStock($schoolId);
                $columns = ['Title', 'Author', 'Category', 'Total copies', 'Available', 'Out', 'Lost', 'Status'];
                break;
            case 'overdue':
                $title = 'Overdue Books Report';
                $rows = ReportsModule::overdueBooks($schoolId);
                $columns = ['Title', 'Accession #', 'Borrower', 'Adm. #', 'Type', 'Issued', 'Due', 'Days overdue', 'Status'];
                break;
            case 'leave':
                $title = 'Leave Balances Report';
                $rows = ReportsModule::leaveBalances($schoolId);
                $columns = ['Staff name', 'Leave type', 'Entitlement (days)', 'Taken', 'Remaining', 'Pending'];
                break;
            case 'payroll':
                $title = 'Payroll Summary Report';
                $rows = ReportsModule::payrollSummary($schoolId);
                $columns = ['Period', 'Status', 'Employees', 'Gross pay', 'PAYE', 'NSSF employee', 'NSSF employer', 'Net pay'];
                break;
            case 'low_stock':
                $title = 'Low Stock Report';
                $rows = ReportsModule::lowStock($schoolId);
                $columns = ['Code', 'Item', 'Category', 'Unit', 'Stock', 'Reorder', 'Shortfall', 'Status'];
                break;
            case 'audit':
                $title = 'Audit Log Report';
                $rows = ReportsModule::auditSummary($schoolId);
                $columns = ['User', 'Module', 'Action', 'Date', 'Count'];
                break;
            case 'contracts':
                $title = 'Contract Expiry Report';
                $rows = $hasContractView ? ReportsModule::contractExpiry($schoolId) : [];
                $columns = ['Staff name', 'Position', 'Contract type', 'Start date', 'Expiry date', 'Days left', 'Status'];
                break;
            case 'licences':
                $title = 'Licence Alerts Report';
                $rows = $hasLicenceView ? ReportsModule::licenceAlerts($schoolId) : [];
                $columns = ['Type', 'Licence #', 'Issuing body', 'Issue date', 'Expiry date', 'Days left', 'Status'];
                break;
            default:
                $title = 'Overview Report';
                $rows = [];
                $columns = [];
        }

        $html = $this->buildReportPdfHtml($title, $school, $currency, $columns, $rows, $dateFrom, $dateTo);
        PdfService::output($html, strtolower(str_replace(' ', '_', $title)) . '_' . date('Ymd') . '.pdf');
    }

    private function buildReportPdfHtml(string $title, array $school, string $currency, array $columns, array $rows, string $dateFrom, string $dateTo): string
    {
        $logo = $school['logo_path'] ? '<img src="'.e($school['logo_path']).'" height="34" alt="logo" style="margin-right:12px">' : '';
        $head = '';
        $body = '';

        if ($columns) {
            $head = '<thead><tr>';
            foreach ($columns as $c) {
                $head .= '<th style="padding:6px 8px;border:1px solid #c7d2fe;background:#eef2ff;color:#1e3a8a;font-weight:600;text-align:left;">'.e($c).'</th>';
            }
            $head .= '</tr></thead>';
            $body = '<tbody>';
            foreach ($rows as $r) {
                $body .= '<tr>';
                foreach ($columns as $c) {
                    $val = $r[$c] ?? '';
                    $body .= '<td style="padding:5px 8px;border:1px solid #e5e7eb;">'.e($val).'</td>';
                }
                $body .= '</tr>';
            }
            $body .= '</tbody>';
        }

        return '<!doctype html><html><head><meta charset="utf-8"><title>'.e($title).'</title>'
            . '<style>'.PdfService::CSS.'</style>'
            . '</head><body>'
            . '<div class="pdf-header">'
            . '<div style="display:flex;align-items:center;gap:10px">'.$logo
            . '<div><div class="pdf-title">'.e($school['name'] ?? APP_NAME).' — '.e($title).'</div>'
            . '<div class="pdf-sub">'.e(date('d M Y')).' &middot; Period: '.e($dateFrom).' to '.e($dateTo).'</div></div></div>'
            . '<div class="school-info">'.e($school['name'] ?? '').'<br>'.e($school['physical_address'] ?? '').'<br>'.e($school['phone'] ?? '').' &middot; '.e($school['email'] ?? '').'</div>'
            . '</div>'
            . ($body ? '<table class="pdf-table" style="margin-top:16px">'.$head.$body.'</table>' : '<p style="margin-top:24px;color:#64748b">No data available for the selected period.</p>')
            . '<div class="footer">'.e(($school['name'] ?? APP_NAME)).' &middot; Page <span class="page-number"></span></div>'
            . '</body></html>';
    }

    // ------------------------------------------------------------------ private

    private function buildTabData(
        string $tab, int $schoolId, int $yearId, int $examId,
        string $date, string $dateFrom, string $dateTo
    ): array {
        $db = Database::conn();

        // Helper: check if a view exists
        $viewExists = function (string $view) use ($db): bool {
            $stmt = $db->prepare("SELECT COUNT(*) AS c FROM information_schema.views WHERE table_schema = DATABASE() AND table_name = ?");
            $stmt->execute([$view]);
            $row = $stmt->fetch();
            return (int)($row['c'] ?? 0) > 0;
        };

        $hasDashboardView = $viewExists('vw_school_dashboard');
        $hasContractView = $viewExists('vw_contract_expiry_alerts');
        $hasLicenceView = $viewExists('vw_licence_expiry_alerts');

        return match($tab) {
            'overview'   => [
                'kpis'            => $hasDashboardView ? ReportsModule::schoolDashboard($schoolId) : null,
                'contract_expiry' => $hasContractView ? ReportsModule::contractExpiry($schoolId) : [],
                'licence_alerts'  => $hasLicenceView ? ReportsModule::licenceAlerts($schoolId) : [],
                'placement'       => ReportsModule::studentPlacement($schoolId),
                'collection'      => ReportsModule::feeCollection($schoolId, $dateFrom, $dateTo),
                'charts'          => [
                    'fee_collection' => ReportsModule::feeCollectionChart($schoolId, $dateFrom, $dateTo),
                    'students_by_class' => ReportsModule::studentsByClassChart($schoolId),
                    'students_by_status' => ReportsModule::studentsByStatusChart($schoolId),
                    'attendance'     => ReportsModule::attendanceChart($schoolId, $dateFrom, $dateTo),
                ],
                'views_missing'   => !$hasDashboardView || !$hasContractView || !$hasLicenceView,
            ],
            'students'   => [
                'placement' => ReportsModule::studentPlacement($schoolId),
                'charts'    => [
                    'students_by_class' => ReportsModule::studentsByClassChart($schoolId),
                    'students_by_status' => ReportsModule::studentsByStatusChart($schoolId),
                ],
            ],
            'finance'    => [
                'debtors'    => $yearId ? ReportsModule::debtors($schoolId, $yearId) : [],
                'collection' => ReportsModule::feeCollection($schoolId, $dateFrom, $dateTo),
                'charts'     => [
                    'fee_collection' => ReportsModule::feeCollectionChart($schoolId, $dateFrom, $dateTo),
                ],
            ],
            'attendance' => [
                'daily' => ReportsModule::attendanceSummary($schoolId, $date),
                'charts'=> [
                    'attendance' => ReportsModule::attendanceChart($schoolId, $dateFrom, $dateTo),
                ],
            ],
            'exams'      => [
                'performance' => $examId ? ReportsModule::examClassPerformance($schoolId, $examId) : [],
                'charts'      => $examId ? [
                    'exam_performance' => ReportsModule::examPerformanceChart($schoolId, $examId),
                ] : [],
            ],
            'hr'         => [
                'leave_balances' => ReportsModule::leaveBalances($schoolId),
                'payroll'        => ReportsModule::payrollSummary($schoolId),
            ],
            'operations' => [
                'library'    => ReportsModule::libraryStock($schoolId),
                'overdue'    => ReportsModule::overdueBooks($schoolId),
                'boarding'   => ReportsModule::dormitoryOccupancy($schoolId),
                'transport'  => ReportsModule::routeLoad($schoolId),
                'inventory'  => ReportsModule::lowStock($schoolId),
                'charts'     => [
                    'dorm_occupancy' => ReportsModule::dormOccupancyChart($schoolId),
                    'route_load'     => ReportsModule::routeLoadChart($schoolId),
                    'low_stock'      => ReportsModule::lowStockChart($schoolId),
                ],
            ],
            'audit'      => [
                'audit' => ReportsModule::auditSummary($schoolId),
            ],
            default      => [],
        };
    }

    private function exportData(
        string $report, int $schoolId, int $yearId, int $examId,
        string $dateFrom, string $dateTo
    ): array {
        $db = Database::conn();
        $viewExists = function (string $view) use ($db): bool {
            $stmt = $db->prepare("SELECT COUNT(*) AS c FROM information_schema.views WHERE table_schema = DATABASE() AND table_name = ?");
            $stmt->execute([$view]);
            $row = $stmt->fetch();
            return (int)($row['c'] ?? 0) > 0;
        };
        $hasContractView = $viewExists('vw_contract_expiry_alerts');
        $hasLicenceView = $viewExists('vw_licence_expiry_alerts');

        return match($report) {
            'students'    => [
                ReportsModule::studentPlacement($schoolId),
                ['Admission #', 'Full name', 'Gender', 'Class', 'Stream', 'House', 'Status', 'Type'],
                'students_placement_' . date('Ymd') . '.csv',
            ],
            'debtors'     => [
                $yearId ? ReportsModule::debtors($schoolId, $yearId) : [],
                ['Student', 'Adm. #', 'Class', 'Total charged', 'Total paid', 'Balance', 'Overdue', 'Days overdue', 'Guardian', 'Phone'],
                'fee_debtors_' . date('Ymd') . '.csv',
            ],
            'collection'  => [
                ReportsModule::feeCollection($schoolId, $dateFrom, $dateTo),
                ['Date', 'Channel', 'Method', 'Transactions', 'Amount collected'],
                'fee_collection_' . $dateFrom . '_to_' . $dateTo . '.csv',
            ],
            'library'     => [
                ReportsModule::libraryStock($schoolId),
                ['Title', 'Author', 'Category', 'Total copies', 'Available', 'Out', 'Lost', 'Status'],
                'library_stock_' . date('Ymd') . '.csv',
            ],
            'overdue'     => [
                ReportsModule::overdueBooks($schoolId),
                ['Title', 'Accession #', 'Borrower', 'Adm. #', 'Type', 'Issued', 'Due', 'Days overdue', 'Status'],
                'overdue_books_' . date('Ymd') . '.csv',
            ],
            'leave'       => [
                ReportsModule::leaveBalances($schoolId),
                ['Staff name', 'Leave type', 'Entitlement (days)', 'Taken', 'Remaining', 'Pending'],
                'leave_balances_' . date('Ymd') . '.csv',
            ],
            'payroll'     => [
                ReportsModule::payrollSummary($schoolId),
                ['Period', 'Status', 'Employees', 'Gross pay', 'PAYE', 'NSSF employee', 'NSSF employer', 'Net pay'],
                'payroll_summary_' . date('Ymd') . '.csv',
            ],
            'low_stock'   => [
                ReportsModule::lowStock($schoolId),
                ['Code', 'Item', 'Category', 'Unit', 'Stock', 'Reorder', 'Shortfall', 'Status'],
                'low_stock_items_' . date('Ymd') . '.csv',
            ],
            'audit'       => [
                ReportsModule::auditSummary($schoolId),
                ['User', 'Module', 'Action', 'Date', 'Count'],
                'audit_log_' . date('Ymd') . '.csv',
            ],
            'contracts'   => [
                $hasContractView ? ReportsModule::contractExpiry($schoolId) : [],
                ['Staff name', 'Position', 'Contract type', 'Start date', 'Expiry date', 'Days left', 'Status'],
                'contract_expiry_' . date('Ymd') . '.csv',
            ],
            'licences'    => [
                $hasLicenceView ? ReportsModule::licenceAlerts($schoolId) : [],
                ['Type', 'Licence #', 'Issuing body', 'Issue date', 'Expiry date', 'Days left', 'Status'],
                'licence_alerts_' . date('Ymd') . '.csv',
            ],
            default       => [[], [], 'report.csv'],
        };
    }
}
