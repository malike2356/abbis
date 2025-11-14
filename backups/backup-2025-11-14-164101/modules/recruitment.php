<?php
/**
 * Recruitment Module - Vacancy and Applicant Tracking integrated with HR
 */

$page_title = 'Recruitment & Talent Acquisition';

require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';
require_once '../includes/recruitment-utils.php';

$auth->requireAuth();
$auth->requirePermission('recruitment.access');

if (!isFeatureEnabled('recruitment')) {
    $auth->requireRole(ROLE_ADMIN); // Only admin can access when disabled
    flash('warning', 'Recruitment module is disabled. Enable it in Feature Management.');
}

$pdo = getDBConnection();
$currentUserId = $_SESSION['user_id'];
$currentUserRole = $_SESSION['role'] ?? null;

$tablesReady = recruitmentEnsureInitialized($pdo);

if (!$tablesReady) {
    flash('warning', 'Recruitment tables could not be initialized automatically. Please run <code>database/recruitment_module_migration.sql</code> via Database Migrations.');
}

if ($tablesReady) {
    try {
        recruitmentEnsureDemoData($pdo);
    } catch (Throwable $e) {
        error_log('Recruitment demo data refresh failed: ' . $e->getMessage());
    }
}

$statuses = recruitmentGetStatuses($pdo);

$message = null;
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid security token. Please retry.';
        $messageType = 'danger';
    } elseif (!$tablesReady) {
        $message = 'Recruitment tables are not initialized.';
        $messageType = 'danger';
    } else {
        $action = $_POST['action'];

        try {
            switch ($action) {
                case 'create_vacancy':
                    $title = sanitizeInput($_POST['title'] ?? '');
                    $departmentId = !empty($_POST['department_id']) ? intval($_POST['department_id']) : null;
                    $positionId = !empty($_POST['position_id']) ? intval($_POST['position_id']) : null;
                    $location = sanitizeInput($_POST['location'] ?? '');
                    $employmentType = $_POST['employment_type'] ?? 'full_time';
                    $seniorityLevel = $_POST['seniority_level'] ?? 'entry';
                    $salaryCurrency = strtoupper(trim($_POST['salary_currency'] ?? 'USD'));
                    $salaryMin = $_POST['salary_min'] !== '' ? floatval($_POST['salary_min']) : null;
                    $salaryMax = $_POST['salary_max'] !== '' ? floatval($_POST['salary_max']) : null;
                    $salaryVisible = !empty($_POST['salary_visible']) ? 1 : 0;
                    $description = $_POST['description'] ?? null;
                    $requirements = $_POST['requirements'] ?? null;
                    $responsibilities = $_POST['responsibilities'] ?? null;
                    $benefits = $_POST['benefits'] ?? null;
                    $statusValue = $_POST['status'] ?? 'draft';
                    $openingDate = !empty($_POST['opening_date']) ? $_POST['opening_date'] : null;
                    $closingDate = !empty($_POST['closing_date']) ? $_POST['closing_date'] : null;
                    $recruiterId = !empty($_POST['recruiter_user_id']) ? intval($_POST['recruiter_user_id']) : null;
                    $hiringManagerId = !empty($_POST['hiring_manager_id']) ? intval($_POST['hiring_manager_id']) : null;

                    if (!$title) {
                        throw new Exception('Vacancy title is required.');
                    }

                    $vacancyCode = recruitmentGenerateCode($pdo, 'recruitment_vacancies', 'vacancy_code', 'VAC', 5);

                    $stmt = $pdo->prepare("
                        INSERT INTO recruitment_vacancies (
                            vacancy_code, title, department_id, position_id, location,
                            employment_type, seniority_level, salary_currency, salary_min, salary_max,
                            salary_visible, description, requirements, responsibilities, benefits,
                            status, opening_date, closing_date, published_at,
                            recruiter_user_id, hiring_manager_id, created_by, updated_by, created_at
                        ) VALUES (
                            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 
                            CASE WHEN ? = 'published' THEN NOW() ELSE NULL END,
                            ?, ?, ?, ?, NOW()
                        )
                    ");
                    $stmt->execute([
                        $vacancyCode,
                        $title,
                        $departmentId,
                        $positionId,
                        $location ?: null,
                        $employmentType,
                        $seniorityLevel,
                        $salaryCurrency ?: 'USD',
                        $salaryMin,
                        $salaryMax,
                        $salaryVisible,
                        $description,
                        $requirements,
                        $responsibilities,
                        $benefits,
                        $statusValue,
                        $openingDate,
                        $closingDate,
                        $statusValue,
                        $recruiterId,
                        $hiringManagerId,
                        $currentUserId,
                        $currentUserId,
                    ]);

                    $message = 'Vacancy created successfully.';
                    break;

                case 'update_vacancy':
                    $vacancyId = intval($_POST['vacancy_id'] ?? 0);
                    if ($vacancyId <= 0) {
                        throw new Exception('Invalid vacancy selected.');
                    }

                    $title = sanitizeInput($_POST['title'] ?? '');
                    $departmentId = !empty($_POST['department_id']) ? intval($_POST['department_id']) : null;
                    $positionId = !empty($_POST['position_id']) ? intval($_POST['position_id']) : null;
                    $location = sanitizeInput($_POST['location'] ?? '');
                    $employmentType = $_POST['employment_type'] ?? 'full_time';
                    $seniorityLevel = $_POST['seniority_level'] ?? 'entry';
                    $salaryCurrency = strtoupper(trim($_POST['salary_currency'] ?? 'USD'));
                    $salaryMin = $_POST['salary_min'] !== '' ? floatval($_POST['salary_min']) : null;
                    $salaryMax = $_POST['salary_max'] !== '' ? floatval($_POST['salary_max']) : null;
                    $salaryVisible = !empty($_POST['salary_visible']) ? 1 : 0;
                    $description = $_POST['description'] ?? null;
                    $requirements = $_POST['requirements'] ?? null;
                    $responsibilities = $_POST['responsibilities'] ?? null;
                    $benefits = $_POST['benefits'] ?? null;
                    $statusValue = $_POST['status'] ?? 'draft';
                    $openingDate = !empty($_POST['opening_date']) ? $_POST['opening_date'] : null;
                    $closingDate = !empty($_POST['closing_date']) ? $_POST['closing_date'] : null;
                    $recruiterId = !empty($_POST['recruiter_user_id']) ? intval($_POST['recruiter_user_id']) : null;
                    $hiringManagerId = !empty($_POST['hiring_manager_id']) ? intval($_POST['hiring_manager_id']) : null;

                    $stmt = $pdo->prepare("
                        UPDATE recruitment_vacancies
                        SET 
                            title = ?,
                            department_id = ?,
                            position_id = ?,
                            location = ?,
                            employment_type = ?,
                            seniority_level = ?,
                            salary_currency = ?,
                            salary_min = ?,
                            salary_max = ?,
                            salary_visible = ?,
                            description = ?,
                            requirements = ?,
                            responsibilities = ?,
                            benefits = ?,
                            status = ?,
                            opening_date = ?,
                            closing_date = ?,
                            recruiter_user_id = ?,
                            hiring_manager_id = ?,
                            updated_by = ?,
                            updated_at = NOW(),
                            published_at = CASE 
                                WHEN ? = 'published' AND (published_at IS NULL OR published_at = '') THEN NOW()
                                WHEN ? != 'published' THEN published_at
                                ELSE published_at
                            END
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $title,
                        $departmentId,
                        $positionId,
                        $location ?: null,
                        $employmentType,
                        $seniorityLevel,
                        $salaryCurrency ?: 'USD',
                        $salaryMin,
                        $salaryMax,
                        $salaryVisible,
                        $description,
                        $requirements,
                        $responsibilities,
                        $benefits,
                        $statusValue,
                        $openingDate,
                        $closingDate,
                        $recruiterId,
                        $hiringManagerId,
                        $currentUserId,
                        $statusValue,
                        $statusValue,
                        $vacancyId,
                    ]);

                    $message = 'Vacancy updated successfully.';
                    break;

                case 'delete_vacancy':
                    $vacancyId = intval($_POST['vacancy_id'] ?? 0);
                    if ($vacancyId <= 0) {
                        throw new Exception('Invalid vacancy selected.');
                    }

                    if (!recruitmentDeleteVacancy($pdo, $vacancyId)) {
                        throw new Exception('Vacancy could not be deleted. It may have already been removed.');
                    }

                    $message = 'Vacancy deleted successfully.';
                    break;

                case 'update_application_status':
                    $applicationId = intval($_POST['application_id'] ?? 0);
                    $newStatus = $_POST['new_status'] ?? null;
                    $statusNote = trim($_POST['status_note'] ?? '');

                    if ($applicationId <= 0 || !$newStatus) {
                        throw new Exception('Invalid application or status.');
                    }
                    if (!isset($statuses[$newStatus])) {
                        throw new Exception('Unknown status selected.');
                    }

                    $pdo->beginTransaction();

                    $currentStmt = $pdo->prepare("SELECT current_status FROM recruitment_applications WHERE id = ? FOR UPDATE");
                    $currentStmt->execute([$applicationId]);
                    $current = $currentStmt->fetchColumn();
                    if (!$current) {
                        throw new Exception('Application not found.');
                    }

                    $historyStmt = $pdo->prepare("
                        INSERT INTO recruitment_application_status_history
                        (application_id, from_status, to_status, changed_by_user_id, changed_at, comment)
                        VALUES (?, ?, ?, ?, NOW(), ?)
                    ");
                    $historyStmt->execute([
                        $applicationId,
                        $current,
                        $newStatus,
                        $currentUserId,
                        $statusNote ?: null,
                    ]);

                    $updateStmt = $pdo->prepare("
                        UPDATE recruitment_applications
                        SET current_status = ?, last_status_change = NOW(), updated_at = NOW()
                        WHERE id = ?
                    ");
                    $updateStmt->execute([$newStatus, $applicationId]);

                    if (in_array($newStatus, ['hired', 'onboarding', 'employed'], true)) {
                        recruitmentCreateWorkerFromApplication($pdo, $applicationId, $currentUserId);
                    }

                    $pdo->commit();

                    $message = 'Application status updated.';
                    break;

                case 'assign_application':
                    $applicationId = intval($_POST['application_id'] ?? 0);
                    $assignedUserId = !empty($_POST['assigned_to_user_id']) ? intval($_POST['assigned_to_user_id']) : null;

                    $stmt = $pdo->prepare("
                        UPDATE recruitment_applications
                        SET assigned_to_user_id = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$assignedUserId, $applicationId]);

                    $message = 'Application assignment updated.';
                    break;

                case 'add_application_note':
                    $applicationId = intval($_POST['application_id'] ?? 0);
                    $noteType = $_POST['note_type'] ?? 'general';
                    $noteText = trim($_POST['note_text'] ?? '');

                    if ($applicationId <= 0 || !$noteText) {
                        throw new Exception('Note text is required.');
                    }

                    $stmt = $pdo->prepare("
                        INSERT INTO recruitment_application_notes (application_id, note_type, note_text, created_by, created_at)
                        VALUES (?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([$applicationId, $noteType, $noteText, $currentUserId]);

                    $message = 'Note added to application.';
                    break;

                default:
                    $message = 'Unknown action.';
                    $messageType = 'danger';
                    break;
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// Prefetch dropdown data
$departments = [];
$positions = [];
$employmentTypeOptions = [
    'full_time' => 'Full Time',
    'part_time' => 'Part Time',
    'contract' => 'Contract',
    'temporary' => 'Temporary',
    'internship' => 'Internship',
];

$seniorityLevelOptions = [
    'entry' => 'Entry',
    'mid' => 'Mid',
    'senior' => 'Senior',
    'executive' => 'Executive',
    'intern' => 'Intern',
];

$users = [];

try {
    $departments = $pdo->query("SELECT id, department_name FROM departments ORDER BY department_name")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $ignored) {}

try {
    $positions = $pdo->query("SELECT id, position_title FROM positions ORDER BY position_title")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $ignored) {}

try {
    $users = $pdo->query("SELECT id, full_name FROM users WHERE is_active = 1 ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $ignored) {}

$stats = $tablesReady ? recruitmentGetDashboardStats($pdo) : [];

$vacancies = [];
$applications = [];
$applicantPool = [];
$statusBreakdown = [];

if ($tablesReady) {
    try {
        $vacancyStmt = $pdo->query("
            SELECT 
                v.*,
                COALESCE(SUM(CASE WHEN a.id IS NOT NULL THEN 1 ELSE 0 END), 0) AS application_count,
                COALESCE(SUM(CASE WHEN a.current_status IN ('hired','onboarding','employed') THEN 1 ELSE 0 END), 0) AS hired_count
            FROM recruitment_vacancies v
            LEFT JOIN recruitment_applications a ON a.vacancy_id = v.id
            GROUP BY v.id
            ORDER BY v.created_at DESC
        ");
        $vacancies = $vacancyStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $ignored) {
        $vacancies = [];
    }

    try {
        $appStmt = $pdo->query("
            SELECT 
                a.*, 
                c.first_name, c.last_name, c.email, c.phone_primary,
                v.title AS vacancy_title
            FROM recruitment_applications a
            INNER JOIN recruitment_candidates c ON c.id = a.candidate_id
            INNER JOIN recruitment_vacancies v ON v.id = a.vacancy_id
            ORDER BY a.created_at DESC
            LIMIT 50
        ");
        $applications = $appStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $ignored) {
        $applications = [];
    }

    try {
        $poolStmt = $pdo->query("
            SELECT 
                c.id,
                c.first_name,
                c.last_name,
                c.email,
                c.phone_primary,
                c.source,
                MAX(a.created_at) AS last_applied_at,
                GROUP_CONCAT(DISTINCT v.title ORDER BY a.created_at DESC SEPARATOR ', ') AS applied_roles,
                MAX(a.current_status) AS latest_status
            FROM recruitment_candidates c
            LEFT JOIN recruitment_applications a ON a.candidate_id = c.id
            LEFT JOIN recruitment_vacancies v ON v.id = a.vacancy_id
            GROUP BY c.id
            ORDER BY last_applied_at DESC
            LIMIT 100
        ");
        $applicantPool = $poolStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $ignored) {
        $applicantPool = [];
    }

    $statusBreakdown = $stats['applications_by_status'] ?? [];
}

require_once '../includes/header.php';
?>

<style>
.hr-tabs {
    display: flex;
    gap: 8px;
    margin-bottom: 24px;
    border-bottom: 2px solid var(--border);
    flex-wrap: wrap;
}

.hr-tab {
    padding: 12px 20px;
    background: transparent;
    border: none;
    border-bottom: 3px solid transparent;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    color: var(--secondary);
    text-decoration: none;
    transition: all 0.2s;
    position: relative;
    bottom: -2px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.hr-tab:hover {
    color: var(--primary);
    background: color-mix(in srgb, var(--primary) 5%, transparent);
}

.hr-tab.active {
    color: var(--primary);
    border-bottom-color: var(--primary);
}

.dashboard-grid .dashboard-card {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 18px 20px;
    border-radius: 18px;
    background: var(--card);
    border: 1px solid var(--border);
    box-shadow: var(--shadow-sm, 0 12px 24px rgba(15, 23, 42, 0.06));
}

.dashboard-grid .dashboard-card .card-icon {
    flex: 0 0 58px;
    height: 58px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 16px;
    font-size: 24px;
    background: rgba(14, 165, 233, 0.12);
}

.dashboard-grid .dashboard-card .card-content {
    display: flex;
    flex-direction: column;
    gap: 6px;
    line-height: 1.1;
}

.dashboard-grid .dashboard-card .card-content h3 {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
    color: var(--text, #1f2937);
}

.dashboard-grid .dashboard-card .card-content .metric {
    margin: 0;
    font-size: 28px;
    font-weight: 700;
    color: var(--heading, #0f172a);
}

.dashboard-grid .dashboard-card .card-content .metric-sub {
    display: block;
    font-size: 13px;
    color: var(--secondary, #6b7280);
}

.vacancy-edit-form {
    margin-top: 12px;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.vacancy-edit-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 12px;
}

.vacancy-edit-grid textarea {
    min-height: 90px;
}

.vacancy-edit-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

.vacancy-modal-backdrop {
    position: fixed;
    inset: 0;
    display: none;
    align-items: center;
    justify-content: center;
    background: rgba(15, 23, 42, 0.45);
    backdrop-filter: blur(2px);
    z-index: 2050;
    padding: 20px;
}

.vacancy-modal-backdrop.active {
    display: flex;
}

.vacancy-modal {
    background: var(--card);
    border-radius: 16px;
    box-shadow: var(--shadow-lg);
    width: min(820px, 95vw);
    max-height: 90vh;
    overflow-y: auto;
    padding: 24px;
    border: 1px solid var(--border);
}

.vacancy-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}

.vacancy-modal-close {
    background: none;
    border: none;
    font-size: 20px;
    cursor: pointer;
    color: var(--secondary);
}

.vacancy-modal-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 14px;
}

.vacancy-modal-grid textarea {
    min-height: 90px;
}

.vacancy-modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 18px;
}

body.modal-open {
    overflow: hidden;
}
</style>

<div class="container-fluid">
    <nav aria-label="Breadcrumb" style="margin-bottom: 12px;">
        <div style="display:inline-block; padding:6px 10px; border:1px solid var(--border); background: var(--bg); border-radius: 6px; font-size: 13px; color: var(--text);">
            <span>Human Resources</span> <span style="opacity:0.6;">→</span> <span>Recruitment</span>
        </div>
    </nav>

    <div class="hr-tabs">
        <a href="hr.php?action=dashboard" class="hr-tab">
            📊 Dashboard
        </a>
        <a href="hr.php?action=employees" class="hr-tab">
            👥 Employees
        </a>
        <a href="hr.php?action=departments" class="hr-tab">
            🏢 Departments
        </a>
        <a href="hr.php?action=positions" class="hr-tab">
            💼 Positions
        </a>
        <a href="hr.php?action=attendance" class="hr-tab">
            ⏰ Attendance
        </a>
        <a href="hr.php?action=leave" class="hr-tab">
            🏖️ Leave
        </a>
        <a href="hr.php?action=performance" class="hr-tab">
            ⭐ Performance
        </a>
        <a href="hr.php?action=training" class="hr-tab">
            📚 Training
        </a>
        <a href="hr.php?action=stakeholders" class="hr-tab">
            🤝 Stakeholders
        </a>
        <a href="hr.php?action=roles" class="hr-tab">
            👔 Roles
        </a>
        <a href="recruitment.php" class="hr-tab active">
            🧑‍💼 Recruitment
        </a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo e($messageType); ?>">
            <?php echo e($message); ?>
        </div>
    <?php endif; ?>

    <?php if (!$tablesReady): ?>
        <div class="alert alert-warning">
            Recruitment tables have not been initialized. Please run <code>database/recruitment_module_migration.sql</code>.
        </div>
    <?php endif; ?>

    <div class="page-header">
        <h1>🧑‍💼 Recruitment & Talent Acquisition</h1>
        <p>Manage vacancies, applicants, and hiring workflow synced with HR.</p>
    </div>

    <?php if ($tablesReady): ?>
    <section class="dashboard-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 18px; margin-bottom: 24px;">
        <div class="dashboard-card">
            <div class="card-icon" style="background: rgba(37,99,235,0.1); color: #2563eb;">📢</div>
            <div class="card-content">
                <h3>Total Vacancies</h3>
                <p class="metric"><?php echo number_format($stats['total_vacancies'] ?? 0); ?></p>
                <span class="metric-sub"><?php echo number_format($stats['open_vacancies'] ?? 0); ?> published</span>
            </div>
        </div>
        <div class="dashboard-card">
            <div class="card-icon" style="background: rgba(14,165,233,0.1); color: #0ea5e9;">📄</div>
            <div class="card-content">
                <h3>Total Applications</h3>
                <p class="metric"><?php echo number_format($stats['applications_total'] ?? 0); ?></p>
                <span class="metric-sub"><?php echo number_format($stats['applications_this_month'] ?? 0); ?> this month</span>
            </div>
        </div>
        <div class="dashboard-card">
            <div class="card-icon" style="background: rgba(22,163,74,0.1); color: #16a34a;">✅</div>
            <div class="card-content">
                <h3>Hired / Onboarding</h3>
                <p class="metric"><?php echo number_format($stats['hired_this_month'] ?? 0); ?></p>
                <span class="metric-sub">This month</span>
            </div>
        </div>
        <div class="dashboard-card">
            <div class="card-icon" style="background: rgba(240,149,12,0.1); color: #f59e0b;">📊</div>
            <div class="card-content">
                <h3>Pipeline Stages</h3>
                <p class="metric"><?php echo count($statusBreakdown); ?></p>
                <span class="metric-sub">Active stages with candidates</span>
            </div>
        </div>
    </section>

    <section class="dashboard-card" style="margin-bottom: 24px;">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <div>
                <h2 style="margin: 0;">Create Vacancy</h2>
                <p style="margin: 0; opacity: 0.7;">Publish openings that sync with the career portal.</p>
            </div>
        </div>
        <form method="post" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px;">
            <input type="hidden" name="csrf_token" value="<?php echo e(CSRF::generateToken()); ?>">
            <input type="hidden" name="action" value="create_vacancy">
            <div>
                <label class="form-label">Vacancy Title *</label>
                <input type="text" name="title" class="form-control" required placeholder="e.g. Lead Driller - Rig Operations">
            </div>
            <div>
                <label class="form-label">Department</label>
                <select name="department_id" class="form-control">
                    <option value="">-- Select --</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?php echo intval($dept['id']); ?>"><?php echo e($dept['department_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label">Position</label>
                <select name="position_id" class="form-control">
                    <option value="">-- Select --</option>
                    <?php foreach ($positions as $pos): ?>
                        <option value="<?php echo intval($pos['id']); ?>"><?php echo e($pos['position_title']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label">Location</label>
                <input type="text" name="location" class="form-control" placeholder="Accra, Ghana">
            </div>
            <div>
                <label class="form-label">Employment Type</label>
                <select name="employment_type" class="form-control">
                    <?php foreach ($employmentTypeOptions as $value => $label): ?>
                        <option value="<?php echo e($value); ?>"><?php echo e($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label">Seniority</label>
                <select name="seniority_level" class="form-control">
                    <?php foreach ($seniorityLevelOptions as $value => $label): ?>
                        <option value="<?php echo e($value); ?>"><?php echo e($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label">Salary Currency</label>
                <input type="text" name="salary_currency" class="form-control" value="USD">
            </div>
            <div>
                <label class="form-label">Salary Min</label>
                <input type="number" step="0.01" name="salary_min" class="form-control">
            </div>
            <div>
                <label class="form-label">Salary Max</label>
                <input type="number" step="0.01" name="salary_max" class="form-control">
            </div>
            <div style="display:flex; align-items:flex-end;">
                <label class="form-checkbox">
                    <input type="checkbox" name="salary_visible" value="1">
                    <span>Display salary range</span>
                </label>
            </div>
            <div>
                <label class="form-label">Opening Date</label>
                <input type="date" name="opening_date" class="form-control">
            </div>
            <div>
                <label class="form-label">Closing Date</label>
                <input type="date" name="closing_date" class="form-control">
            </div>
            <div>
                <label class="form-label">Recruiter</label>
                <select name="recruiter_user_id" class="form-control">
                    <option value="">-- Select --</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?php echo intval($user['id']); ?>"><?php echo e($user['full_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label">Hiring Manager</label>
                <select name="hiring_manager_id" class="form-control">
                    <option value="">-- Select --</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?php echo intval($user['id']); ?>"><?php echo e($user['full_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label">Status</label>
                <select name="status" class="form-control">
                    <option value="draft">Draft</option>
                    <option value="published">Published</option>
                    <option value="closed">Closed</option>
                    <option value="archived">Archived</option>
                </select>
            </div>
            <div style="grid-column: 1 / -1;">
                <label class="form-label">Responsibilities</label>
                <textarea name="responsibilities" class="form-control" rows="2" placeholder="Key responsibilities for this role"></textarea>
            </div>
            <div style="grid-column: 1 / -1;">
                <label class="form-label">Requirements</label>
                <textarea name="requirements" class="form-control" rows="2" placeholder="Required skills, experience, qualifications"></textarea>
            </div>
            <div style="grid-column: 1 / -1;">
                <label class="form-label">Benefits</label>
                <textarea name="benefits" class="form-control" rows="2" placeholder="Benefits offered to candidates"></textarea>
            </div>
            <div style="grid-column: 1 / -1;">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="3" placeholder="Detailed job description shown on the vacancy page"></textarea>
            </div>
            <div style="grid-column: 1 / -1; display:flex; justify-content:flex-end;">
                <button type="submit" class="btn btn-primary">Create Vacancy</button>
            </div>
        </form>
    </section>

    <section class="dashboard-card" style="margin-bottom: 24px;">
        <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
            <div>
                <h2 style="margin:0;">Vacancies Overview</h2>
                <p style="margin:0; opacity:0.7;">Track recruitment progress and publish status.</p>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Vacancy</th>
                        <th>Status</th>
                        <th>Applications</th>
                        <th>Recruiter</th>
                        <th>Dates</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($vacancies)): ?>
                        <tr><td colspan="6" class="text-center">No vacancies created yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($vacancies as $vacancy): ?>
                            <tr>
                                <td>
                                    <strong><?php echo e($vacancy['title']); ?></strong><br>
                                    <span class="text-muted">
                                        <?php echo e($vacancy['vacancy_code']); ?>
                                        <?php if (!empty($vacancy['location'])): ?>
                                            · <?php echo e($vacancy['location']); ?>
                                        <?php endif; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo e($vacancy['status']); ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $vacancy['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <strong><?php echo intval($vacancy['application_count']); ?></strong> total<br>
                                    <span class="text-muted"><?php echo intval($vacancy['hired_count']); ?> hired</span>
                                </td>
                                <td>
                                    <?php
                                    $recruiter = '';
                                    if (!empty($vacancy['recruiter_user_id'])) {
                                        foreach ($users as $user) {
                                            if ($user['id'] == $vacancy['recruiter_user_id']) {
                                                $recruiter = $user['full_name'];
                                                break;
                                            }
                                        }
                                    }
                                    echo e($recruiter ?: 'Unassigned');
                                    ?>
                                </td>
                                <td>
                                    <div style="font-size:12px;">
                                        <div>Opening: <?php echo e($vacancy['opening_date'] ?: 'n/a'); ?></div>
                                        <div>Closing: <?php echo e($vacancy['closing_date'] ?: 'n/a'); ?></div>
                                    </div>
                                </td>
                                <td style="text-align:right;">
                                    <button
                                        type="button"
                                        class="btn btn-outline btn-small vacancy-manage-btn"
                                        data-vacancy-id="<?php echo intval($vacancy['id']); ?>"
                                        data-title="<?php echo e($vacancy['title']); ?>"
                                        data-location="<?php echo e($vacancy['location'] ?? ''); ?>"
                                        data-employment-type="<?php echo e($vacancy['employment_type'] ?? 'full_time'); ?>"
                                        data-seniority-level="<?php echo e($vacancy['seniority_level'] ?? 'entry'); ?>"
                                        data-salary-currency="<?php echo e($vacancy['salary_currency'] ?? ''); ?>"
                                        data-salary-min="<?php echo htmlspecialchars($vacancy['salary_min'] !== null ? $vacancy['salary_min'] : '', ENT_QUOTES, 'UTF-8'); ?>"
                                        data-salary-max="<?php echo htmlspecialchars($vacancy['salary_max'] !== null ? $vacancy['salary_max'] : '', ENT_QUOTES, 'UTF-8'); ?>"
                                        data-salary-visible="<?php echo !empty($vacancy['salary_visible']) ? '1' : '0'; ?>"
                                        data-status="<?php echo e($vacancy['status'] ?? 'draft'); ?>"
                                        data-opening-date="<?php echo e($vacancy['opening_date'] ?? ''); ?>"
                                        data-closing-date="<?php echo e($vacancy['closing_date'] ?? ''); ?>"
                                        data-recruiter-id="<?php echo intval($vacancy['recruiter_user_id'] ?? 0); ?>"
                                        data-hiring-manager-id="<?php echo intval($vacancy['hiring_manager_id'] ?? 0); ?>"
                                        data-department-id="<?php echo intval($vacancy['department_id'] ?? 0); ?>"
                                        data-position-id="<?php echo intval($vacancy['position_id'] ?? 0); ?>"
                                        data-responsibilities="<?php echo htmlspecialchars($vacancy['responsibilities'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                        data-requirements="<?php echo htmlspecialchars($vacancy['requirements'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                        data-benefits="<?php echo htmlspecialchars($vacancy['benefits'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                        data-description="<?php echo htmlspecialchars($vacancy['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                    >
                                        Manage
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <div class="vacancy-modal-backdrop" id="vacancyEditModal" aria-hidden="true">
        <div class="vacancy-modal" role="dialog" aria-modal="true" aria-labelledby="vacancyEditTitle">
            <div class="vacancy-modal-header">
                <h3 id="vacancyEditTitle" style="margin:0;">Edit Vacancy</h3>
                <button type="button" class="vacancy-modal-close" id="vacancyModalClose" aria-label="Close">&times;</button>
            </div>
            <form method="post" id="vacancyEditForm">
                <input type="hidden" name="csrf_token" value="<?php echo e(CSRF::generateToken()); ?>">
                <input type="hidden" name="action" value="update_vacancy">
                <input type="hidden" name="vacancy_id" id="modalVacancyId">

                <div class="vacancy-modal-grid">
                    <div>
                        <label class="form-label">Title *</label>
                        <input type="text" name="title" id="modalVacancyTitle" class="form-control" required>
                    </div>
                    <div>
                        <label class="form-label">Department</label>
                        <select name="department_id" id="modalDepartment" class="form-control">
                            <option value="">-- Select --</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo intval($dept['id']); ?>"><?php echo e($dept['department_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Position</label>
                        <select name="position_id" id="modalPosition" class="form-control">
                            <option value="">-- Select --</option>
                            <?php foreach ($positions as $pos): ?>
                                <option value="<?php echo intval($pos['id']); ?>"><?php echo e($pos['position_title']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Location</label>
                        <input type="text" name="location" id="modalLocation" class="form-control">
                    </div>
                    <div>
                        <label class="form-label">Employment Type</label>
                        <select name="employment_type" id="modalEmploymentType" class="form-control">
                            <?php foreach ($employmentTypeOptions as $value => $label): ?>
                                <option value="<?php echo e($value); ?>"><?php echo e($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Seniority</label>
                        <select name="seniority_level" id="modalSeniorityLevel" class="form-control">
                            <?php foreach ($seniorityLevelOptions as $value => $label): ?>
                                <option value="<?php echo e($value); ?>"><?php echo e($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Salary Currency</label>
                        <input type="text" name="salary_currency" id="modalSalaryCurrency" class="form-control">
                    </div>
                    <div>
                        <label class="form-label">Salary Min</label>
                        <input type="number" step="0.01" name="salary_min" id="modalSalaryMin" class="form-control">
                    </div>
                    <div>
                        <label class="form-label">Salary Max</label>
                        <input type="number" step="0.01" name="salary_max" id="modalSalaryMax" class="form-control">
                    </div>
                    <div style="display:flex; align-items:center; gap:8px; margin-top:24px;">
                        <input type="hidden" name="salary_visible" value="0">
                        <label class="form-checkbox">
                            <input type="checkbox" name="salary_visible" id="modalSalaryVisible" value="1">
                            <span>Display salary range</span>
                        </label>
                    </div>
                    <div>
                        <label class="form-label">Opening Date</label>
                        <input type="date" name="opening_date" id="modalOpeningDate" class="form-control">
                    </div>
                    <div>
                        <label class="form-label">Closing Date</label>
                        <input type="date" name="closing_date" id="modalClosingDate" class="form-control">
                    </div>
                    <div>
                        <label class="form-label">Status</label>
                        <select name="status" id="modalStatus" class="form-control">
                            <option value="draft">Draft</option>
                            <option value="published">Published</option>
                            <option value="closed">Closed</option>
                            <option value="archived">Archived</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Recruiter</label>
                        <select name="recruiter_user_id" id="modalRecruiter" class="form-control">
                            <option value="">Unassigned</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo intval($user['id']); ?>"><?php echo e($user['full_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Hiring Manager</label>
                        <select name="hiring_manager_id" id="modalHiringManager" class="form-control">
                            <option value="">Unassigned</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo intval($user['id']); ?>"><?php echo e($user['full_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="grid-column: 1 / -1;">
                        <label class="form-label">Responsibilities</label>
                        <textarea name="responsibilities" id="modalResponsibilities" class="form-control" rows="3"></textarea>
                    </div>
                    <div style="grid-column: 1 / -1;">
                        <label class="form-label">Requirements</label>
                        <textarea name="requirements" id="modalRequirements" class="form-control" rows="3"></textarea>
                    </div>
                    <div style="grid-column: 1 / -1;">
                        <label class="form-label">Benefits</label>
                        <textarea name="benefits" id="modalBenefits" class="form-control" rows="3"></textarea>
                    </div>
                    <div style="grid-column: 1 / -1;">
                        <label class="form-label">Description</label>
                        <textarea name="description" id="modalDescription" class="form-control" rows="3"></textarea>
                    </div>
                </div>

                <div class="vacancy-modal-actions">
                    <button type="button" class="btn btn-danger" id="vacancyModalDelete">Delete Vacancy</button>
                    <button type="button" class="btn btn-outline" id="vacancyModalCancel">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    (function () {
        const modalBackdrop = document.getElementById('vacancyEditModal');
        const modalClose = document.getElementById('vacancyModalClose');
        const modalCancel = document.getElementById('vacancyModalCancel');
        const modalDelete = document.getElementById('vacancyModalDelete');
        const modalForm = document.getElementById('vacancyEditForm');
        const manageButtons = document.querySelectorAll('.vacancy-manage-btn');
        const actionInput = modalForm?.querySelector('input[name="action"]');
        const defaultActionValue = actionInput ? actionInput.value : 'update_vacancy';

        const fieldMap = {
            id: document.getElementById('modalVacancyId'),
            title: document.getElementById('modalVacancyTitle'),
            department: document.getElementById('modalDepartment'),
            position: document.getElementById('modalPosition'),
            location: document.getElementById('modalLocation'),
            employmentType: document.getElementById('modalEmploymentType'),
            seniority: document.getElementById('modalSeniorityLevel'),
            salaryCurrency: document.getElementById('modalSalaryCurrency'),
            salaryMin: document.getElementById('modalSalaryMin'),
            salaryMax: document.getElementById('modalSalaryMax'),
            salaryVisible: document.getElementById('modalSalaryVisible'),
            openingDate: document.getElementById('modalOpeningDate'),
            closingDate: document.getElementById('modalClosingDate'),
            status: document.getElementById('modalStatus'),
            recruiter: document.getElementById('modalRecruiter'),
            hiringManager: document.getElementById('modalHiringManager'),
            responsibilities: document.getElementById('modalResponsibilities'),
            requirements: document.getElementById('modalRequirements'),
            benefits: document.getElementById('modalBenefits'),
            description: document.getElementById('modalDescription'),
        };

        function resetModal() {
            modalForm.reset();
            fieldMap.salaryVisible.checked = false;
        }

        function normaliseDate(value) {
            if (!value) {
                return '';
            }
            return String(value).substring(0, 10);
        }

        function openModal() {
            modalBackdrop.classList.add('active');
            document.body.classList.add('modal-open');
        }

        function closeModal() {
            modalBackdrop.classList.remove('active');
            document.body.classList.remove('modal-open');
            resetModal();
        }

        function populateModalFromDataset(dataset) {
            fieldMap.id.value = dataset.vacancyId || '';
            fieldMap.title.value = dataset.title || '';
            fieldMap.location.value = dataset.location || '';

            if (dataset.employmentType && fieldMap.employmentType.querySelector(`option[value="${dataset.employmentType}"]`)) {
                fieldMap.employmentType.value = dataset.employmentType;
            }

            if (dataset.seniorityLevel && fieldMap.seniority.querySelector(`option[value="${dataset.seniorityLevel}"]`)) {
                fieldMap.seniority.value = dataset.seniorityLevel;
            }

            fieldMap.salaryCurrency.value = dataset.salaryCurrency || '';
            fieldMap.salaryMin.value = dataset.salaryMin || '';
            fieldMap.salaryMax.value = dataset.salaryMax || '';
            fieldMap.salaryVisible.checked = dataset.salaryVisible === '1';

            fieldMap.openingDate.value = normaliseDate(dataset.openingDate || '');
            fieldMap.closingDate.value = normaliseDate(dataset.closingDate || '');

            if (dataset.status && fieldMap.status.querySelector(`option[value="${dataset.status}"]`)) {
                fieldMap.status.value = dataset.status;
            }

            if (dataset.recruiterId && fieldMap.recruiter.querySelector(`option[value="${dataset.recruiterId}"]`)) {
                fieldMap.recruiter.value = dataset.recruiterId;
            } else {
                fieldMap.recruiter.value = '';
            }

            if (dataset.hiringManagerId && fieldMap.hiringManager.querySelector(`option[value="${dataset.hiringManagerId}"]`)) {
                fieldMap.hiringManager.value = dataset.hiringManagerId;
            } else {
                fieldMap.hiringManager.value = '';
            }

            if (fieldMap.department.querySelector(`option[value="${dataset.departmentId}"]`)) {
                fieldMap.department.value = dataset.departmentId;
            } else {
                fieldMap.department.value = '';
            }

            if (fieldMap.position.querySelector(`option[value="${dataset.positionId}"]`)) {
                fieldMap.position.value = dataset.positionId;
            } else {
                fieldMap.position.value = '';
            }

            fieldMap.responsibilities.value = dataset.responsibilities ? dataset.responsibilities.replace(/\\n/g, '\n') : '';
            fieldMap.requirements.value = dataset.requirements ? dataset.requirements.replace(/\\n/g, '\n') : '';
            fieldMap.benefits.value = dataset.benefits ? dataset.benefits.replace(/\\n/g, '\n') : '';
            fieldMap.description.value = dataset.description ? dataset.description.replace(/\\n/g, '\n') : '';
        }

        manageButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                populateModalFromDataset(button.dataset);
                openModal();
            });
        });

        modalClose?.addEventListener('click', closeModal);
        modalCancel?.addEventListener('click', closeModal);

        modalDelete?.addEventListener('click', function () {
            if (!fieldMap.id.value) {
                return;
            }
            if (confirm('Delete this vacancy and all associated applications? This action cannot be undone.')) {
                if (actionInput) {
                    actionInput.value = 'delete_vacancy';
                }
                modalForm.submit();
                if (actionInput) {
                    actionInput.value = defaultActionValue;
                }
            }
        });

        modalBackdrop.addEventListener('click', function (event) {
            if (event.target === modalBackdrop) {
                closeModal();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && modalBackdrop.classList.contains('active')) {
                closeModal();
            }
        });
    })();
    </script>

    <section class="dashboard-card" style="margin-bottom:24px;">
        <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
            <div>
                <h2 style="margin:0;">Recent Applications</h2>
                <p style="margin:0; opacity:0.7;">Monitor candidate pipeline and update statuses.</p>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Candidate</th>
                        <th>Vacancy</th>
                        <th>Status</th>
                        <th>Assigned</th>
                        <th>Applied</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($applications)): ?>
                        <tr><td colspan="6" class="text-center">No applications yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($applications as $application): ?>
                            <tr>
                                <td>
                                    <strong><?php echo e($application['first_name'] . ' ' . $application['last_name']); ?></strong><br>
                                    <span class="text-muted"><?php echo e($application['email']); ?></span><br>
                                    <?php if (!empty($application['phone_primary'])): ?>
                                    <span class="text-muted"><?php echo e($application['phone_primary']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo e($application['vacancy_title']); ?></strong><br>
                                    <span class="text-muted"><?php echo e($application['application_code']); ?></span>
                                </td>
                                <td>
                                    <?php 
                                    $statusKey = $application['current_status'];
                                    $statusLabel = $statuses[$statusKey]['status_label'] ?? ucfirst(str_replace('_', ' ', $statusKey));
                                    $statusColor = $statuses[$statusKey]['color_hex'] ?? '#6b7280';
                                    ?>
                                    <span class="badge" style="background: <?php echo e($statusColor); ?>20; color: <?php echo e($statusColor); ?>;">
                                        <?php echo e($statusLabel); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $assigned = 'Unassigned';
                                    if (!empty($application['assigned_to_user_id'])) {
                                        foreach ($users as $user) {
                                            if ($user['id'] == $application['assigned_to_user_id']) {
                                                $assigned = $user['full_name'];
                                                break;
                                            }
                                        }
                                    }
                                    echo e($assigned);
                                    ?>
                                </td>
                                <td>
                                    <?php echo e(date('Y-m-d', strtotime($application['created_at']))); ?>
                                </td>
                                <td>
                                    <details>
                                        <summary class="btn btn-outline btn-small">Pipeline</summary>
                                        <div style="margin-top:10px;">
                                            <form method="post" style="display:flex; flex-direction:column; gap:8px;">
                                                <input type="hidden" name="csrf_token" value="<?php echo e(CSRF::generateToken()); ?>">
                                                <input type="hidden" name="action" value="update_application_status">
                                                <input type="hidden" name="application_id" value="<?php echo intval($application['id']); ?>">
                                                <label class="form-label">Status</label>
                                                <select name="new_status" class="form-control">
                                                    <?php foreach ($statuses as $key => $status): ?>
                                                        <option value="<?php echo e($key); ?>" <?php echo $key === $statusKey ? 'selected' : ''; ?>>
                                                            <?php echo e($status['status_label']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <label class="form-label">Note</label>
                                                <textarea name="status_note" class="form-control" rows="2"></textarea>
                                                <button type="submit" class="btn btn-primary btn-small">Update Status</button>
                                            </form>
                                            <hr>
                                            <form method="post" style="display:flex; flex-direction:column; gap:8px;">
                                                <input type="hidden" name="csrf_token" value="<?php echo e(CSRF::generateToken()); ?>">
                                                <input type="hidden" name="action" value="assign_application">
                                                <input type="hidden" name="application_id" value="<?php echo intval($application['id']); ?>">
                                                <label class="form-label">Assign to</label>
                                                <select name="assigned_to_user_id" class="form-control">
                                                    <option value="">-- Unassigned --</option>
                                                    <?php foreach ($users as $user): ?>
                                                        <option value="<?php echo intval($user['id']); ?>" <?php echo ($application['assigned_to_user_id'] == $user['id']) ? 'selected' : ''; ?>>
                                                            <?php echo e($user['full_name']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button type="submit" class="btn btn-secondary btn-small">Assign</button>
                                            </form>
                                            <hr>
                                            <form method="post" style="display:flex; flex-direction:column; gap:8px;">
                                                <input type="hidden" name="csrf_token" value="<?php echo e(CSRF::generateToken()); ?>">
                                                <input type="hidden" name="action" value="add_application_note">
                                                <input type="hidden" name="application_id" value="<?php echo intval($application['id']); ?>">
                                                <label class="form-label">Add Note</label>
                                                <select name="note_type" class="form-control">
                                                    <option value="general">General</option>
                                                    <option value="interview">Interview</option>
                                                    <option value="evaluation">Evaluation</option>
                                                    <option value="offer">Offer</option>
                                                    <option value="decision">Decision</option>
                                                </select>
                                                <textarea name="note_text" class="form-control" rows="2" required></textarea>
                                                <button type="submit" class="btn btn-outline btn-small">Add Note</button>
                                            </form>
                                        </div>
                                    </details>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="dashboard-card">
        <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
            <div>
                <h2 style="margin:0;">Applicant Pool</h2>
                <p style="margin:0; opacity:0.7;">Talent database with current status indicators.</p>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Candidate</th>
                        <th>Contact</th>
                        <th>Source</th>
                        <th>Latest Status</th>
                        <th>Applied Roles</th>
                        <th>Last Applied</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($applicantPool)): ?>
                        <tr><td colspan="6" class="text-center">No applicants recorded yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($applicantPool as $candidate): ?>
                            <tr>
                                <td><strong><?php echo e($candidate['first_name'] . ' ' . $candidate['last_name']); ?></strong></td>
                                <td>
                                    <div><?php echo e($candidate['email']); ?></div>
                                    <?php if (!empty($candidate['phone_primary'])): ?>
                                        <div><?php echo e($candidate['phone_primary']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo e($candidate['source'] ?: 'Unknown'); ?></td>
                                <td>
                                    <?php
                                    $latestStatus = $candidate['latest_status'] ?: 'new';
                                    $statusLabel = $statuses[$latestStatus]['status_label'] ?? ucfirst(str_replace('_', ' ', $latestStatus));
                                    ?>
                                    <span class="badge badge-light"><?php echo e($statusLabel); ?></span>
                                </td>
                                <td><?php echo e($candidate['applied_roles'] ?: '—'); ?></td>
                                <td><?php echo e($candidate['last_applied_at'] ?: '—'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>

