<?php
declare(strict_types=1);

/**
 * Front controller and route table.
 *
 * The public entry point stays at the project root (../index.php), which just
 * requires this file. Apache denies direct HTTP access to backend/, so every
 * request still arrives through the root index.php and BASE_URL stays correct.
 */

// Load .env first so the session settings below actually reflect it.
require __DIR__ . '/config/config.php';

// Session security settings (must be set before session_start)
$https = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
    || (string)($_SERVER['SERVER_PORT'] ?? '') === '443';
$sessionSecure = filter_var($_ENV['SESSION_SECURE'] ?? $_SERVER['SESSION_SECURE'] ?? ($https ? '1' : '0'), FILTER_VALIDATE_BOOLEAN);
$sessionHttpOnly = filter_var($_ENV['SESSION_HTTPONLY'] ?? $_SERVER['SESSION_HTTPONLY'] ?? '1', FILTER_VALIDATE_BOOLEAN);
ini_set('session.cookie_httponly', $sessionHttpOnly ? '1' : '0');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.use_only_cookies', '1');
if ($sessionSecure) {
    ini_set('session.cookie_secure', '1');
}
ini_set('session.use_strict_mode', '1');
ini_set('session.gc_maxlifetime', '14400'); // 4 hours
ini_set('session.cookie_lifetime', '14400');

session_start();

require __DIR__ . '/core/helpers.php';

spl_autoload_register(function (string $class): void {
    foreach (['core', 'controllers', 'models', 'lib'] as $folder) {
        $file = __DIR__ . '/' . $folder . '/' . $class . '.php';
        if (is_file($file)) {
            require $file;
            return;
        }
    }
});

$router = new Router();

$router->get('install', [AuthController::class, 'install']);
$router->post('install', [AuthController::class, 'saveInstall']);
$router->get('login', [AuthController::class, 'loginForm']);
$router->post('login', [AuthController::class, 'login']);
$router->post('logout', [AuthController::class, 'logout']);
// Account & password
$router->get('forgot-password', [AuthController::class, 'forgotPasswordForm']);
$router->post('forgot-password', [AuthController::class, 'forgotPassword']);
$router->get('reset-password', [AuthController::class, 'resetPasswordForm']);
$router->post('reset-password', [AuthController::class, 'resetPassword']);
$router->get('two-factor/challenge', [AuthController::class, 'twoFactorChallenge']);
$router->post('two-factor/verify', [AuthController::class, 'twoFactorVerify']);
$router->get('change-password', [AuthController::class, 'changePasswordForm'], ['auth' => true]);
$router->post('change-password/save', [AuthController::class, 'changePassword'], ['auth' => true]);
$router->get('profile', [ProfileController::class, 'index'], ['auth' => true]);
$router->post('profile/update', [ProfileController::class, 'update'], ['auth' => true]);

$router->get('dashboard', [DashboardController::class, 'index'], ['auth' => true]);

$router->get('users', [UserController::class, 'index'], ['auth' => true, 'perm' => ['administration', 'view']]);
$router->post('users/store', [UserController::class, 'store'], ['auth' => true, 'perm' => ['administration', 'add']]);
$router->post('users/update', [UserController::class, 'update'], ['auth' => true, 'perm' => ['administration', 'edit']]);
$router->post('users/toggle', [UserController::class, 'toggle'], ['auth' => true, 'perm' => ['administration', 'edit']]);
$router->post('users/reset', [UserController::class, 'resetPassword'], ['auth' => true, 'perm' => ['administration', 'edit']]);

$router->get('roles', [RoleController::class, 'index'], ['auth' => true, 'perm' => ['administration', 'edit']]);
$router->post('roles/save', [RoleController::class, 'save'], ['auth' => true, 'perm' => ['administration', 'edit']]);

$router->get('settings', [SettingController::class, 'index'], ['auth' => true, 'perm' => ['administration', 'view']]);
$router->post('settings/save', [SettingController::class, 'save'], ['auth' => true, 'perm' => ['administration', 'edit']]);

$router->get('setup/profile', [SetupController::class, 'profile'], ['auth' => true, 'perm' => ['administration', 'view']]);
$router->post('setup/profile', [SetupController::class, 'saveProfile'], ['auth' => true, 'perm' => ['administration', 'edit']]);

$router->get('setup/years', [SetupController::class, 'years'], ['auth' => true, 'perm' => ['administration', 'view']]);
$router->post('setup/years/save', [SetupController::class, 'saveYear'], ['auth' => true, 'perm' => ['administration', 'edit']]);
$router->post('setup/years/activate', [SetupController::class, 'activateYear'], ['auth' => true, 'perm' => ['administration', 'edit']]);

$router->get('setup/terms', [SetupController::class, 'terms'], ['auth' => true, 'perm' => ['administration', 'view']]);
$router->post('setup/terms/save', [SetupController::class, 'saveTerm'], ['auth' => true, 'perm' => ['administration', 'edit']]);
$router->post('setup/terms/activate', [SetupController::class, 'activateTerm'], ['auth' => true, 'perm' => ['administration', 'edit']]);

$router->get('setup/classes', [SetupController::class, 'classes'], ['auth' => true, 'perm' => ['administration', 'view']]);
$router->post('setup/classes/save', [SetupController::class, 'saveClass'], ['auth' => true, 'perm' => ['administration', 'edit']]);
$router->post('setup/classes/toggle', [SetupController::class, 'toggleClass'], ['auth' => true, 'perm' => ['administration', 'edit']]);
$router->post('setup/streams/save', [SetupController::class, 'saveStream'], ['auth' => true, 'perm' => ['administration', 'edit']]);
$router->post('setup/streams/toggle', [SetupController::class, 'toggleStream'], ['auth' => true, 'perm' => ['administration', 'edit']]);

$router->get('setup/departments', [SetupController::class, 'departments'], ['auth' => true, 'perm' => ['administration', 'view']]);
$router->post('setup/departments/save', [SetupController::class, 'saveDepartment'], ['auth' => true, 'perm' => ['administration', 'edit']]);
$router->post('setup/departments/toggle', [SetupController::class, 'toggleDepartment'], ['auth' => true, 'perm' => ['administration', 'edit']]);

$router->get('setup/houses', [SetupController::class, 'houses'], ['auth' => true, 'perm' => ['administration', 'view']]);
$router->post('setup/houses/save', [SetupController::class, 'saveHouse'], ['auth' => true, 'perm' => ['administration', 'edit']]);
$router->post('setup/houses/toggle', [SetupController::class, 'toggleHouse'], ['auth' => true, 'perm' => ['administration', 'edit']]);

$router->get('setup/audit', [SetupController::class, 'audit'], ['auth' => true, 'perm' => ['administration', 'view']]);

// Students
$router->get('students', [StudentController::class, 'index'], ['auth' => true, 'perm' => ['students', 'view']]);
$router->post('students/store', [StudentController::class, 'store'], ['auth' => true, 'perm' => ['students', 'add']]);
$router->post('students/update', [StudentController::class, 'update'], ['auth' => true, 'perm' => ['students', 'edit']]);
$router->get('students/profile', [StudentController::class, 'profile'], ['auth' => true, 'perm' => ['students', 'view']]);
$router->post('students/allocate', [StudentController::class, 'allocate'], ['auth' => true, 'perm' => ['students', 'edit']]);
$router->get('students/allocation', [StudentController::class, 'allocationPage'], ['auth' => true, 'perm' => ['students', 'view']]);
$router->post('students/allocation/save', [StudentController::class, 'bulkAllocate'], ['auth' => true, 'perm' => ['students', 'edit']]);
$router->post('students/withdraw', [StudentController::class, 'withdraw'], ['auth' => true, 'perm' => ['students', 'edit']]);
$router->get('students/export-csv', [StudentController::class, 'exportCsv'], ['auth' => true, 'perm' => ['students', 'view']]);
$router->post('students/import-csv', [StudentController::class, 'importCsv'], ['auth' => true, 'perm' => ['students', 'add']]);

$router->get('admissions', [AdmissionController::class, 'index'], ['auth' => true, 'perm' => ['students', 'view']]);
$router->post('admissions/store', [AdmissionController::class, 'store'], ['auth' => true, 'perm' => ['students', 'add']]);
$router->post('admissions/decide', [AdmissionController::class, 'decide'], ['auth' => true, 'perm' => ['students', 'edit']]);
$router->get('admissions/letter', [AdmissionController::class, 'admissionLetter'], ['auth' => true, 'perm' => ['students', 'view']]);

$router->get('guardians', [GuardianController::class, 'index'], ['auth' => true, 'perm' => ['students', 'view']]);
$router->post('guardians/store', [GuardianController::class, 'store'], ['auth' => true, 'perm' => ['students', 'add']]);
$router->post('guardians/update', [GuardianController::class, 'update'], ['auth' => true, 'perm' => ['students', 'edit']]);
$router->post('guardians/link', [GuardianController::class, 'link'], ['auth' => true, 'perm' => ['students', 'edit']]);
$router->post('guardians/unlink', [GuardianController::class, 'unlink'], ['auth' => true, 'perm' => ['students', 'edit']]);

$router->get('students/subjects', [SubjectRegistrationController::class, 'index'], ['auth' => true, 'perm' => ['students', 'view']]);
$router->post('students/subjects/save', [SubjectRegistrationController::class, 'save'], ['auth' => true, 'perm' => ['students', 'edit']]);
$router->post('students/subjects/combination', [SubjectRegistrationController::class, 'saveCombination'], ['auth' => true, 'perm' => ['students', 'edit']]);
$router->post('students/subjects/bulk', [SubjectRegistrationController::class, 'bulkSave'], ['auth' => true, 'perm' => ['students', 'edit']]);

// REST API for subject registration (mobile / third-party integration)
$router->post('api/v1/subjects/register', [SubjectRegistrationController::class, 'apiRegister'], ['auth' => true, 'perm' => ['students', 'edit']]);
$router->post('api/v1/subjects/bulk', [SubjectRegistrationController::class, 'apiBulkRegister'], ['auth' => true, 'perm' => ['students', 'edit']]);

$router->get('students/promotions', [PromotionController::class, 'index'], ['auth' => true, 'perm' => ['students', 'view']]);
$router->post('promotions/store', [PromotionController::class, 'store'], ['auth' => true, 'perm' => ['students', 'edit']]);
$router->post('promotions/process', [PromotionController::class, 'process'], ['auth' => true, 'perm' => ['students', 'edit']]);

$router->get('students/transfers', [TransferController::class, 'index'], ['auth' => true, 'perm' => ['students', 'view']]);
$router->post('students/transfers/store', [TransferController::class, 'store'], ['auth' => true, 'perm' => ['students', 'add']]);
$router->get('students/transfers/certificate', [TransferController::class, 'transferCertificate'], ['auth' => true, 'perm' => ['students', 'view']]);

$router->get('students/discipline', [DisciplineController::class, 'index'], ['auth' => true, 'perm' => ['students', 'view']]);
$router->post('students/discipline/store', [DisciplineController::class, 'store'], ['auth' => true, 'perm' => ['students', 'add']]);
$router->post('students/discipline/update', [DisciplineController::class, 'update'], ['auth' => true, 'perm' => ['students', 'edit']]);

$router->get('students/documents', [DocumentController::class, 'index'], ['auth' => true, 'perm' => ['students', 'view']]);
$router->post('students/documents/upload', [DocumentController::class, 'upload'], ['auth' => true, 'perm' => ['students', 'add']]);
$router->post('students/documents/verify', [DocumentController::class, 'verify'], ['auth' => true, 'perm' => ['students', 'edit']]);
$router->post('students/documents/delete', [DocumentController::class, 'delete'], ['auth' => true, 'perm' => ['students', 'edit']]);

$router->get('students/alumni', [AlumniController::class, 'index'], ['auth' => true, 'perm' => ['students', 'view']]);
$router->post('students/alumni/store', [AlumniController::class, 'store'], ['auth' => true, 'perm' => ['students', 'add']]);
$router->post('students/alumni/update', [AlumniController::class, 'update'], ['auth' => true, 'perm' => ['students', 'edit']]);
$router->get('students/id-card', [StudentController::class, 'studentIdCard'], ['auth' => true, 'perm' => ['students', 'view']]);

// Academics
$router->get('academics', [AcademicsController::class, 'index'], ['auth' => true, 'perm' => ['academics', 'view']]);
$router->post('academics/curricula/save', [AcademicsController::class, 'saveCurriculum'], ['auth' => true, 'perm' => ['academics', 'edit']]);
$router->post('academics/subjects/save', [AcademicsController::class, 'saveSubject'], ['auth' => true, 'perm' => ['academics', 'edit']]);
$router->post('academics/allocations/subjects/save', [AcademicsController::class, 'saveSubjectAllocation'], ['auth' => true, 'perm' => ['academics', 'edit']]);
$router->post('academics/allocations/subjects/deactivate', [AcademicsController::class, 'deactivateSubjectAllocation'], ['auth' => true, 'perm' => ['academics', 'edit']]);
$router->post('academics/allocations/teachers/save', [AcademicsController::class, 'saveTeacherAllocation'], ['auth' => true, 'perm' => ['academics', 'edit']]);
$router->post('academics/allocations/teachers/remove', [AcademicsController::class, 'removeTeacherAllocation'], ['auth' => true, 'perm' => ['academics', 'edit']]);
$router->post('academics/schemes/save', [AcademicsController::class, 'saveScheme'], ['auth' => true, 'perm' => ['academics', 'edit']]);

$router->post('academics/timetables/save', [AcademicsController::class, 'saveTimetable'], ['auth' => true, 'perm' => ['academics', 'edit']]);
$router->post('academics/timetables/delete', [AcademicsController::class, 'deleteTimetable'], ['auth' => true, 'perm' => ['academics', 'edit']]);
$router->post('academics/timetables/slots/save', [AcademicsController::class, 'saveTimetableSlot'], ['auth' => true, 'perm' => ['academics', 'edit']]);
$router->post('academics/timetables/slots/delete', [AcademicsController::class, 'deleteTimetableSlot'], ['auth' => true, 'perm' => ['academics', 'edit']]);

$router->post('academics/homework/save', [AcademicsController::class, 'saveHomework'], ['auth' => true, 'perm' => ['academics', 'edit']]);
$router->post('academics/homework/submissions/save', [AcademicsController::class, 'saveHomeworkSubmissions'], ['auth' => true, 'perm' => ['academics', 'edit']]);
$router->post('academics/events/save', [AcademicsController::class, 'saveEvent'], ['auth' => true, 'perm' => ['academics', 'edit']]);
$router->post('academics/syllabi/save', [AcademicsController::class, 'saveSyllabus'], ['auth' => true, 'perm' => ['academics', 'edit']]);
$router->post('academics/syllabi/topics/save', [AcademicsController::class, 'saveSyllabusTopic'], ['auth' => true, 'perm' => ['academics', 'edit']]);
$router->post('academics/coverage/save', [AcademicsController::class, 'saveCoverage'], ['auth' => true, 'perm' => ['academics', 'edit']]);
$router->post('academics/activities/save', [AcademicsController::class, 'saveActivity'], ['auth' => true, 'perm' => ['academics', 'edit']]);
$router->post('academics/activities/status', [AcademicsController::class, 'updateActivityStatus'], ['auth' => true, 'perm' => ['academics', 'edit']]);

// Report cards
$router->get('report_cards', [ReportCardController::class, 'index'], ['auth' => true, 'perm' => ['academics', 'view']]);
$router->get('report_cards/view', [ReportCardController::class, 'view'], ['auth' => true, 'perm' => ['academics', 'view']]);
$router->get('report_cards/pdf', [ReportCardController::class, 'pdf'], ['auth' => true, 'perm' => ['academics', 'view']]);
$router->get('report_cards/batch', [ReportCardController::class, 'batch'], ['auth' => true, 'perm' => ['academics', 'view']]);
$router->post('report_cards/comments', [ReportCardController::class, 'saveComments'], ['auth' => true, 'perm' => ['academics', 'edit']]);

// Examinations & assessment
$router->get('examinations', [ExaminationController::class, 'index'], ['auth' => true, 'perm' => ['examinations', 'view']]);
$router->post('examinations/save', [ExaminationController::class, 'saveExam'], ['auth' => true, 'perm' => ['examinations', 'edit']]);
$router->post('examinations/schedule', [ExaminationController::class, 'saveSchedule'], ['auth' => true, 'perm' => ['examinations', 'edit']]);
$router->post('examinations/marks/save', [ExaminationController::class, 'saveMark'], ['auth' => true, 'perm' => ['examinations', 'edit']]);
$router->post('examinations/marks/action', [ExaminationController::class, 'markAction'], ['auth' => true, 'perm' => ['examinations', 'approve']]);
$router->post('examinations/results/process', [ExaminationController::class, 'processResults'], ['auth' => true, 'perm' => ['examinations', 'approve']]);
$router->post('examinations/results/publish', [ExaminationController::class, 'publishResults'], ['auth' => true, 'perm' => ['examinations', 'approve']]);
$router->get('examinations/timetable', [ExaminationController::class, 'examTimetable'], ['auth' => true, 'perm' => ['examinations', 'view']]);
$router->get('examinations/result-slip', [ExaminationController::class, 'resultSlip'], ['auth' => true, 'perm' => ['examinations', 'view']]);
$router->get('examinations/transcript', [ExaminationController::class, 'transcript'], ['auth' => true, 'perm' => ['examinations', 'view']]);
$router->get('examinations/certificate-eligibility', [ExaminationController::class, 'certificateEligibility'], ['auth' => true, 'perm' => ['examinations', 'view']]);

// UNEB (candidate registration, CA submission, national results)
$router->get('uneb', [UnebController::class, 'index'], ['auth' => true, 'perm' => ['uneb', 'view']]);
$router->get('uneb/register', [UnebController::class, 'register'], ['auth' => true, 'perm' => ['uneb', 'add']]);
$router->post('uneb/save-candidate', [UnebController::class, 'saveCandidate'], ['auth' => true, 'perm' => ['uneb', 'add']]);
$router->get('uneb/edit', [UnebController::class, 'editCandidate'], ['auth' => true, 'perm' => ['uneb', 'edit']]);
$router->post('uneb/update-candidate', [UnebController::class, 'updateCandidate'], ['auth' => true, 'perm' => ['uneb', 'edit']]);
$router->post('uneb/delete', [UnebController::class, 'deleteCandidate'], ['auth' => true, 'perm' => ['uneb', 'delete']]);
$router->get('uneb/ca-submission', [UnebController::class, 'casubmission'], ['auth' => true, 'perm' => ['uneb', 'view']]);
$router->get('uneb/ca-submission/new', [UnebController::class, 'newCasubmission'], ['auth' => true, 'perm' => ['uneb', 'add']]);
$router->post('uneb/save-ca-submission', [UnebController::class, 'saveCasubmission'], ['auth' => true, 'perm' => ['uneb', 'add']]);
$router->get('uneb/ca-scores', [UnebController::class, 'caScores'], ['auth' => true, 'perm' => ['uneb', 'view']]);
$router->post('uneb/save-ca-scores', [UnebController::class, 'saveCaScores'], ['auth' => true, 'perm' => ['uneb', 'edit']]);
$router->post('uneb/ca-submit', [UnebController::class, 'submitCasubmission'], ['auth' => true, 'perm' => ['uneb', 'approve']]);
$router->get('uneb/results', [UnebController::class, 'results'], ['auth' => true, 'perm' => ['uneb', 'view']]);
$router->post('uneb/save-result', [UnebController::class, 'saveResult'], ['auth' => true, 'perm' => ['uneb', 'add']]);

// Finance & fees
$router->get('finance', [FinanceController::class, 'index'], ['auth' => true, 'perm' => ['finance', 'view']]);
$router->post('finance/store-invoice', [FinanceController::class, 'storeInvoice'], ['auth' => true, 'perm' => ['finance', 'add']]);
$router->post('finance/structures/store', [FinanceController::class, 'storeStructure'], ['auth' => true, 'perm' => ['finance', 'add']]);
$router->post('finance/structures/items/store', [FinanceController::class, 'storeStructureItem'], ['auth' => true, 'perm' => ['finance', 'edit']]);
$router->post('finance/structures/status', [FinanceController::class, 'setStructureStatus'], ['auth' => true, 'perm' => ['finance', 'edit']]);
$router->post('finance/structures/bulk-invoice', [FinanceController::class, 'bulkInvoice'], ['auth' => true, 'perm' => ['finance', 'add']]);
$router->post('finance/clearances/save', [FinanceController::class, 'saveClearance'], ['auth' => true, 'perm' => ['finance', 'edit']]);
$router->post('finance/clearances/approve', [FinanceController::class, 'approveClearance'], ['auth' => true, 'perm' => ['finance', 'approve']]);
$router->post('finance/reminders/send', [FinanceController::class, 'sendReminder'], ['auth' => true, 'perm' => ['finance', 'add']]);
$router->post('finance/record-payment', [FinanceController::class, 'recordPayment'], ['auth' => true, 'perm' => ['finance', 'add']]);
$router->post('finance/verify-payment', [FinanceController::class, 'verifyPayment'], ['auth' => true, 'perm' => ['finance', 'approve']]);
$router->get('finance/invoice-pdf', [FinanceController::class, 'invoicePdf'], ['auth' => true, 'perm' => ['finance', 'view']]);
$router->get('finance/receipts', [FinanceController::class, 'receipts'], ['auth' => true, 'perm' => ['finance', 'view']]);
$router->get('finance/receipt-pdf', [FinanceController::class, 'receiptPdf'], ['auth' => true, 'perm' => ['finance', 'view']]);
$router->get('finance/bank-reconciliation', [FinanceController::class, 'bankReconciliation'], ['auth' => true, 'perm' => ['finance', 'view']]);
$router->post('finance/bank-account/save', [FinanceController::class, 'saveBankAccount'], ['auth' => true, 'perm' => ['finance', 'edit']]);
$router->post('finance/bank-transaction/save', [FinanceController::class, 'saveBankTransaction'], ['auth' => true, 'perm' => ['finance', 'edit']]);
$router->post('finance/bank-reconcile', [FinanceController::class, 'reconcileBank'], ['auth' => true, 'perm' => ['finance', 'approve']]);
$router->post('finance/receipts/void', [FinanceController::class, 'voidReceipt'], ['auth' => true, 'perm' => ['finance', 'edit']]);
$router->post('finance/receipts/reprint', [FinanceController::class, 'reprintReceipt'], ['auth' => true, 'perm' => ['finance', 'view']]);
$router->post('finance/receipts/email', [FinanceController::class, 'emailReceipt'], ['auth' => true, 'perm' => ['finance', 'edit']]);
$router->post('finance/receipts/sms', [FinanceController::class, 'smsReceipt'], ['auth' => true, 'perm' => ['finance', 'edit']]);
$router->get('finance/export-csv', [FinanceController::class, 'exportCsv'], ['auth' => true, 'perm' => ['finance', 'view']]);
$router->get('finance/clearance-certificate', [FinanceController::class, 'feeClearanceCertificate'], ['auth' => true, 'perm' => ['finance', 'view']]);
$router->get('finance/payment-reminder', [FinanceController::class, 'paymentReminder'], ['auth' => true, 'perm' => ['finance', 'view']]);

// Chart of accounts
$router->get('finance/chart-of-accounts', [ChartOfAccountsController::class, 'index'], ['auth' => true, 'perm' => ['finance', 'view']]);
$router->post('finance/chart-of-accounts/save', [ChartOfAccountsController::class, 'save'], ['auth' => true, 'perm' => ['finance', 'add']]);
$router->post('finance/chart-of-accounts/delete', [ChartOfAccountsController::class, 'delete'], ['auth' => true, 'perm' => ['finance', 'delete']]);
$router->get('finance/chart-of-accounts/list', [ChartOfAccountsController::class, 'list'], ['auth' => true, 'perm' => ['finance', 'view']]);

// Budgets
$router->get('finance/budgets', [BudgetController::class, 'index'], ['auth' => true, 'perm' => ['finance', 'view']]);
$router->post('finance/budgets/save', [BudgetController::class, 'save'], ['auth' => true, 'perm' => ['finance', 'add']]);
$router->post('finance/budgets/delete', [BudgetController::class, 'delete'], ['auth' => true, 'perm' => ['finance', 'delete']]);
$router->get('finance/budgets/lines', [BudgetController::class, 'lines'], ['auth' => true, 'perm' => ['finance', 'view']]);
$router->post('finance/budgets/lines/save', [BudgetController::class, 'saveLine'], ['auth' => true, 'perm' => ['finance', 'add']]);
$router->post('finance/budgets/lines/delete', [BudgetController::class, 'deleteLine'], ['auth' => true, 'perm' => ['finance', 'delete']]);

// Expenses
$router->get('finance/expenses', [ExpenseController::class, 'index'], ['auth' => true, 'perm' => ['finance', 'view']]);
$router->post('finance/expenses/save', [ExpenseController::class, 'save'], ['auth' => true, 'perm' => ['finance', 'add']]);
$router->post('finance/expenses/delete', [ExpenseController::class, 'delete'], ['auth' => true, 'perm' => ['finance', 'delete']]);

// Petty cash
$router->get('finance/petty-cash', [PettyCashController::class, 'index'], ['auth' => true, 'perm' => ['finance', 'view']]);
$router->post('finance/petty-cash/save-fund', [PettyCashController::class, 'saveFund'], ['auth' => true, 'perm' => ['finance', 'add']]);
$router->post('finance/petty-cash/save-voucher', [PettyCashController::class, 'saveVoucher'], ['auth' => true, 'perm' => ['finance', 'add']]);
$router->post('finance/petty-cash/delete-fund', [PettyCashController::class, 'deleteFund'], ['auth' => true, 'perm' => ['finance', 'delete']]);

// Online payment integration
$router->get('finance_online', [FinanceController::class, 'online'], ['auth' => true, 'perm' => ['online_payments', 'view']]);
$router->post('finance/online/gateway', [FinanceController::class, 'storeGateway'], ['auth' => true, 'perm' => ['online_payments', 'edit']]);
$router->post('finance/online/gateway-toggle', [FinanceController::class, 'toggleGateway'], ['auth' => true, 'perm' => ['online_payments', 'edit']]);
$router->post('finance/online/transaction', [FinanceController::class, 'logGatewayTransaction'], ['auth' => true, 'perm' => ['online_payments', 'add']]);
$router->post('finance/online/transaction-update', [FinanceController::class, 'updateGatewayTransaction'], ['auth' => true, 'perm' => ['online_payments', 'edit']]);

// Human resource & payroll
$router->get('hr', [HrController::class, 'index'], ['auth' => true, 'perm' => ['hr', 'view']]);
$router->post('hr/staff/store', [HrController::class, 'storeStaff'], ['auth' => true, 'perm' => ['hr', 'add']]);
$router->post('hr/staff/update', [HrController::class, 'updateStaff'], ['auth' => true, 'perm' => ['hr', 'edit']]);
$router->post('hr/leave-types/store', [HrController::class, 'storeLeaveType'], ['auth' => true, 'perm' => ['hr', 'edit']]);
$router->post('hr/leave/apply', [HrController::class, 'applyLeave'], ['auth' => true, 'perm' => ['hr', 'add']]);
$router->post('hr/leave/review', [HrController::class, 'reviewLeave'], ['auth' => true, 'perm' => ['hr', 'approve']]);
$router->post('hr/payroll/periods/create', [HrController::class, 'createPayrollPeriod'], ['auth' => true, 'perm' => ['hr', 'add']]);
$router->post('hr/payroll/items/add', [HrController::class, 'addPayrollItem'], ['auth' => true, 'perm' => ['hr', 'edit']]);
$router->get('hr/appointment-letter', [HrController::class, 'staffAppointmentLetter'], ['auth' => true, 'perm' => ['hr', 'view']]);
$router->get('hr/id-card', [HrController::class, 'staffIdCard'], ['auth' => true, 'perm' => ['hr', 'view']]);
$router->get('hr/recruitment', [HrController::class, 'recruitment'], ['auth' => true, 'perm' => ['hr', 'view']]);
$router->post('hr/vacancies/save', [HrController::class, 'saveVacancy'], ['auth' => true, 'perm' => ['hr', 'edit']]);
$router->post('hr/vacancies/status', [HrController::class, 'vacancyStatus'], ['auth' => true, 'perm' => ['hr', 'edit']]);
$router->post('hr/applications/save', [HrController::class, 'saveApplication'], ['auth' => true, 'perm' => ['hr', 'add']]);
$router->post('hr/applications/decide', [HrController::class, 'decideApplication'], ['auth' => true, 'perm' => ['hr', 'approve']]);
$router->get('hr/appraisals', [HrController::class, 'appraisals'], ['auth' => true, 'perm' => ['hr', 'view']]);
$router->post('hr/appraisals/save', [HrController::class, 'saveAppraisal'], ['auth' => true, 'perm' => ['hr', 'edit']]);

// Attendance
$router->get('attendance', [AttendanceController::class, 'index'], ['auth' => true, 'perm' => ['attendance', 'view']]);
$router->post('attendance/students/record', [AttendanceController::class, 'recordStudents'], ['auth' => true, 'perm' => ['attendance', 'add']]);
$router->post('attendance/staff/record', [AttendanceController::class, 'recordStaff'], ['auth' => true, 'perm' => ['attendance', 'add']]);

// Health & student welfare
$router->get('health', [HealthController::class, 'index'], ['auth' => true, 'perm' => ['health', 'view']]);
$router->post('health/medical/save', [HealthController::class, 'saveMedical'], ['auth' => true, 'perm' => ['health', 'edit']]);
$router->post('health/sickbay/record', [HealthController::class, 'recordVisit'], ['auth' => true, 'perm' => ['health', 'add']]);
$router->get('health/referral', [HealthController::class, 'medicalReferralForm'], ['auth' => true, 'perm' => ['health', 'view']]);

// Library
$router->get('library', [LibraryController::class, 'index'], ['auth' => true, 'perm' => ['library', 'view']]);
$router->post('library/books/store', [LibraryController::class, 'storeBook'], ['auth' => true, 'perm' => ['library', 'add']]);
$router->post('library/books/toggle', [LibraryController::class, 'toggleBook'], ['auth' => true, 'perm' => ['library', 'edit']]);
$router->post('library/categories/store', [LibraryController::class, 'storeCategory'], ['auth' => true, 'perm' => ['library', 'add']]);
$router->post('library/categories/toggle', [LibraryController::class, 'toggleCategory'], ['auth' => true, 'perm' => ['library', 'edit']]);
$router->post('library/borrowings/issue', [LibraryController::class, 'issueBorrowing'], ['auth' => true, 'perm' => ['library', 'add']]);
$router->post('library/borrowings/return', [LibraryController::class, 'returnBorrowing'], ['auth' => true, 'perm' => ['library', 'edit']]);
$router->post('library/fines/store', [LibraryController::class, 'storeFine'], ['auth' => true, 'perm' => ['library', 'add']]);
$router->post('library/fines/pay', [LibraryController::class, 'markFinePaid'], ['auth' => true, 'perm' => ['library', 'edit']]);
$router->get('library/overdue-notice', [LibraryController::class, 'libraryOverdueNotice'], ['auth' => true, 'perm' => ['library', 'view']]);

// Boarding
$router->get('boarding', [BoardingController::class, 'index'], ['auth' => true, 'perm' => ['boarding', 'view']]);
$router->post('boarding/register', [BoardingController::class, 'register'], ['auth' => true, 'perm' => ['boarding', 'add']]);
$router->post('boarding/status', [BoardingController::class, 'updateStatus'], ['auth' => true, 'perm' => ['boarding', 'edit']]);
$router->get('boarding/allocation-letter', [BoardingController::class, 'boardingAllocationLetter'], ['auth' => true, 'perm' => ['boarding', 'view']]);

// Transport
$router->get('transport', [TransportController::class, 'index'], ['auth' => true, 'perm' => ['transport', 'view']]);
$router->post('transport/routes/store', [TransportController::class, 'storeRoute'], ['auth' => true, 'perm' => ['transport', 'add']]);
$router->post('transport/routes/toggle', [TransportController::class, 'toggleRoute'], ['auth' => true, 'perm' => ['transport', 'edit']]);
$router->get('transport/route-sheet', [TransportController::class, 'transportRouteSheet'], ['auth' => true, 'perm' => ['transport', 'view']]);
$router->post('transport/vehicles/save', [TransportController::class, 'saveVehicle'], ['auth' => true, 'perm' => ['transport', 'add']]);
$router->post('transport/vehicles/toggle', [TransportController::class, 'toggleVehicle'], ['auth' => true, 'perm' => ['transport', 'edit']]);
$router->post('transport/drivers/save', [TransportController::class, 'saveDriver'], ['auth' => true, 'perm' => ['transport', 'add']]);
$router->post('transport/drivers/toggle', [TransportController::class, 'toggleDriver'], ['auth' => true, 'perm' => ['transport', 'edit']]);

// Inventory & procurement
$router->get('inventory', [InventoryController::class, 'index'], ['auth' => true, 'perm' => ['inventory', 'view']]);
$router->post('inventory/requests/create', [InventoryController::class, 'createRequest'], ['auth' => true, 'perm' => ['inventory', 'add']]);
$router->post('inventory/requests/approve', [InventoryController::class, 'approveRequest'], ['auth' => true, 'perm' => ['inventory', 'edit']]);

// Communication
$router->get('communication', [CommunicationController::class, 'index'], ['auth' => true, 'perm' => ['communication', 'view']]);
$router->post('communication/store', [CommunicationController::class, 'store'], ['auth' => true, 'perm' => ['communication', 'add']]);
$router->post('communication/publish', [CommunicationController::class, 'publish'], ['auth' => true, 'perm' => ['communication', 'approve']]);

// Co-curricular
$router->get('cocurricular', [CocurricularController::class, 'index'], ['auth' => true, 'perm' => ['cocurricular', 'view']]);
$router->post('cocurricular/clubs/store', [CocurricularController::class, 'storeClub'], ['auth' => true, 'perm' => ['cocurricular', 'add']]);
$router->post('cocurricular/clubs/toggle', [CocurricularController::class, 'toggleClub'], ['auth' => true, 'perm' => ['cocurricular', 'edit']]);

// Gate & visitors
$router->get('gate', [GateController::class, 'index'], ['auth' => true, 'perm' => ['gate', 'view']]);
$router->post('gate/checkin', [GateController::class, 'checkIn'], ['auth' => true, 'perm' => ['gate', 'add']]);
$router->post('gate/checkout', [GateController::class, 'checkOut'], ['auth' => true, 'perm' => ['gate', 'edit']]);
$router->get('gate/pass', [GateController::class, 'gatePass'], ['auth' => true, 'perm' => ['gate', 'view']]);

// Canteen & shop
$router->get('canteen', [CanteenController::class, 'index'], ['auth' => true, 'perm' => ['canteen', 'view']]);
$router->post('canteen/products/store', [CanteenController::class, 'storeProduct'], ['auth' => true, 'perm' => ['canteen', 'add']]);
$router->post('canteen/products/toggle', [CanteenController::class, 'toggleProduct'], ['auth' => true, 'perm' => ['canteen', 'edit']]);
$router->get('canteen/wallet', [CanteenController::class, 'walletTransactions'], ['auth' => true, 'perm' => ['canteen', 'view']]);
$router->post('canteen/wallet/deposit', [CanteenController::class, 'walletDeposit'], ['auth' => true, 'perm' => ['canteen', 'add']]);

// Reports & analytics
$router->get('reports',        [ReportsController::class, 'index'],  ['auth' => true, 'perm' => ['reports', 'view']]);
$router->get('reports/export', [ReportsController::class, 'export'], ['auth' => true, 'perm' => ['reports', 'view']]);
$router->get('reports/pdf',    [ReportsController::class, 'exportPdf'], ['auth' => true, 'perm' => ['reports', 'view']]);

// Support & security
$router->get('security', [SecurityController::class, 'index'], ['auth' => true, 'perm' => ['security', 'view']]);
$router->post('security/tickets/create', [SecurityController::class, 'createTicket'], ['auth' => true, 'perm' => ['security', 'add']]);
$router->post('security/tickets/update', [SecurityController::class, 'updateTicket'], ['auth' => true, 'perm' => ['security', 'edit']]);
$router->get('security/backups', [BackupController::class, 'index'], ['auth' => true, 'perm' => ['security', 'view']]);
$router->post('security/backups/create', [BackupController::class, 'create'], ['auth' => true, 'perm' => ['security', 'add']]);
$router->get('security/backups/download', [BackupController::class, 'download'], ['auth' => true, 'perm' => ['security', 'view']]);
$router->post('security/backups/delete', [BackupController::class, 'delete'], ['auth' => true, 'perm' => ['security', 'delete']]);
$router->get('security/2fa-setup', [TwoFactorController::class, 'setup'], ['auth' => true, 'perm' => ['security', 'edit']]);
$router->post('security/2fa-verify-setup', [TwoFactorController::class, 'verifySetup'], ['auth' => true, 'perm' => ['security', 'edit']]);
$router->post('security/2fa-disable', [TwoFactorController::class, 'disable'], ['auth' => true, 'perm' => ['security', 'edit']]);
$router->post('security/2fa-send-code', [TwoFactorController::class, 'sendCode'], ['auth' => true, 'perm' => ['security', 'edit']]);
$router->post('security/2fa-verify-code', [TwoFactorController::class, 'verifyCode'], ['auth' => true, 'perm' => ['security', 'edit']]);
$router->get('security/db-setup', [DatabaseSetupController::class, 'index'], ['auth' => true, 'perm' => ['security', 'edit']]);
$router->post('security/db-setup', [DatabaseSetupController::class, 'index'], ['auth' => true, 'perm' => ['security', 'edit']]);
$router->get('system/cron-jobs', [CronController::class, 'index'], ['auth' => true, 'perm' => ['security', 'edit']]);
$router->post('system/cron-jobs/save', [CronController::class, 'save'], ['auth' => true, 'perm' => ['security', 'edit']]);
$router->post('system/cron-jobs/toggle', [CronController::class, 'toggle'], ['auth' => true, 'perm' => ['security', 'edit']]);
$router->post('system/cron-jobs/run', [CronController::class, 'runJob'], ['auth' => true, 'perm' => ['security', 'edit']]);

// Government compliance
$router->get('compliance', [ComplianceController::class, 'index'], ['auth' => true, 'perm' => ['compliance', 'view']]);
$router->post('compliance/emis/create',   [ComplianceController::class, 'createEmisReturn'],   ['auth' => true, 'perm' => ['compliance', 'add']]);
$router->post('compliance/emis/validate', [ComplianceController::class, 'validateEmisReturn'], ['auth' => true, 'perm' => ['compliance', 'approve']]);
$router->post('compliance/emis/submit',   [ComplianceController::class, 'submitEmisReturn'],   ['auth' => true, 'perm' => ['compliance', 'approve']]);
$router->post('compliance/grants/create', [ComplianceController::class, 'createGrant'],        ['auth' => true, 'perm' => ['compliance', 'add']]);
$router->post('compliance/grants/receipt',[ComplianceController::class, 'recordGrantReceipt'], ['auth' => true, 'perm' => ['compliance', 'edit']]);
$router->post('compliance/grants/expenditure', [FinanceController::class, 'recordGrantExpenditure'], ['auth' => true, 'perm' => ['compliance', 'edit']]);

// Parent & student portal
$router->get('portal',  [PortalController::class, 'index'],         ['auth' => true, 'perm' => ['portal', 'view']]);
$router->post('portal/consent/update', [PortalController::class, 'updateConsent'], ['auth' => true, 'perm' => ['portal', 'view']]);

try {
    $router->dispatch();
} catch (Throwable $error) {
    error_log('[SSMS] ' . $error->getMessage() . ' @ ' . $error->getFile() . ':' . $error->getLine());
    http_response_code(500);
    security_headers();

    if (expects_json()) {
        json_out(['ok' => false, 'message' => 'The server could not complete this request.', 'errors' => []], 500);
    }

    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Something went wrong</title>'
        . '<style>body{font-family:system-ui,sans-serif;background:#f1f5f9;color:#0f172a}'
        . '.box{max-width:560px;margin:80px auto;background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:32px}'
        . 'h1{font-size:20px;margin:0 0 8px}p{color:#64748b;margin:0}</style></head><body>'
        . '<div class="box"><h1>Something went wrong</h1>'
        . '<p>The system could not complete this request. Please try again, and contact the administrator if it persists.</p>'
        . '</div></body></html>';
}