<?php
session_start();

$rootPath = dirname(dirname(__DIR__));
require_once $rootPath . '/config/app.php';
require_once $rootPath . '/config/security.php';
require_once $rootPath . '/includes/functions.php';
require_once $rootPath . '/includes/recruitment-utils.php';
require_once __DIR__ . '/auth.php';

$cmsAuth = new CMSAuth();
if (!$cmsAuth->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$currentCmsUser = $cmsAuth->getCurrentUser();
$currentCmsUserId = $currentCmsUser['id'] ?? null;

$pdo = getDBConnection();
$featureEnabled = isFeatureEnabled('recruitment');
$initialized = $featureEnabled ? recruitmentEnsureInitialized($pdo) : false;

$statusMap = [];
$vacancyOptions = [];
$abbisUsers = [];
$applicationOptions = [];
$metrics = [
    'vacancies_total' => 0,
    'vacancies_published' => 0,
    'applications_total' => 0,
    'applications_this_month' => 0,
    'applications_week' => 0,
    'applications_hired' => 0,
    'candidates_total' => 0,
];
$statusBreakdown = [];
$sourceBreakdown = [];
$vacancyLeaderboard = [];
$recentVacancies = [];
$recentApplications = [];
$filteredApplications = [];
$noteTypes = [];

$filters = [
    'status' => $_GET['status'] ?? 'all',
    'vacancy' => $_GET['vacancy'] ?? 'all',
    'timeframe' => $_GET['timeframe'] ?? '30',
    'search' => trim($_GET['search'] ?? ''),
];

$validTimeframes = ['7', '30', '90', '180', '365', 'all'];
if (!in_array($filters['timeframe'], $validTimeframes, true)) {
    $filters['timeframe'] = '30';
}

$companyName = getSystemConfig('company_name', 'CMS Admin');
$baseUrl = app_base_path();

if ($featureEnabled && $initialized) {
    try {
        recruitmentEnsureDemoData($pdo);
    } catch (Throwable $e) {
        error_log('CMS recruitment demo data refresh failed: ' . $e->getMessage());
    }

    $statusMap = loadRecruitmentStatuses($pdo);
    $vacancyOptions = loadVacancyOptions($pdo);
    $abbisUsers = loadAbbIsUsers($pdo);
}

$employmentTypeOptions = [
    'full_time' => 'Full-time',
    'part_time' => 'Part-time',
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

$vacancyDataMap = [];
if (!empty($vacancyOptions)) {
    foreach ($vacancyOptions as $vacancy) {
        $vacancyDataMap[$vacancy['id']] = $vacancy;
    }
}

if ($featureEnabled && $initialized && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        flash('error', 'Invalid security token. Please try again.');
        redirect('recruitment.php');
        exit;
    }

    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {
            case 'create_vacancy':
                $title = sanitizeInput($_POST['title'] ?? '');
                if ($title === '') {
                    throw new Exception('Vacancy title is required.');
                }

                $statusValue = $_POST['status'] ?? 'draft';
                $allowedStatuses = ['draft', 'published', 'closed', 'archived'];
                if (!in_array($statusValue, $allowedStatuses, true)) {
                    throw new Exception('Invalid vacancy status.');
                }

                $employmentType = $_POST['employment_type'] ?? 'full_time';
                $allowedEmployment = ['full_time', 'part_time', 'contract', 'temporary', 'internship'];
                if (!in_array($employmentType, $allowedEmployment, true)) {
                    $employmentType = 'full_time';
                }

                $seniorityLevel = $_POST['seniority_level'] ?? 'entry';
                $allowedSeniority = ['entry', 'mid', 'senior', 'executive', 'intern'];
                if (!in_array($seniorityLevel, $allowedSeniority, true)) {
                    $seniorityLevel = 'entry';
                }

                $location = sanitizeInput($_POST['location'] ?? '');
                $openingDate = !empty($_POST['opening_date']) ? $_POST['opening_date'] : null;
                $closingDate = !empty($_POST['closing_date']) ? $_POST['closing_date'] : null;
                $description = trim($_POST['description'] ?? '');
                $requirements = trim($_POST['requirements'] ?? '');
                $responsibilities = trim($_POST['responsibilities'] ?? '');
                $benefits = trim($_POST['benefits'] ?? '');

                $vacancyCode = recruitmentGenerateCode($pdo, 'recruitment_vacancies', 'vacancy_code', 'VAC', 5);

                $stmt = $pdo->prepare("
                    INSERT INTO recruitment_vacancies (
                        vacancy_code, title, status, location, employment_type,
                        seniority_level, opening_date, closing_date,
                        description, requirements, responsibilities, benefits,
                        created_by, updated_by, published_at
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CASE WHEN ? = 'published' THEN NOW() ELSE NULL END
                    )
                ");
                $stmt->execute([
                    $vacancyCode,
                    $title,
                    $statusValue,
                    $location ?: null,
                    $employmentType,
                    $seniorityLevel,
                    $openingDate ?: null,
                    $closingDate ?: null,
                    $description ?: null,
                    $requirements ?: null,
                    $responsibilities ?: null,
                    $benefits ?: null,
                    null,
                    null,
                    $statusValue,
                ]);

                flash('success', 'Vacancy created successfully.');
                break;

            case 'update_vacancy':
                $vacancyId = intval($_POST['vacancy_id'] ?? 0);
                if ($vacancyId <= 0) {
                    throw new Exception('Select a vacancy to update.');
                }

                $title = sanitizeInput($_POST['title'] ?? '');
                if ($title === '') {
                    throw new Exception('Vacancy title is required.');
                }

                $statusValue = $_POST['status'] ?? 'draft';
                $allowedStatuses = ['draft', 'published', 'closed', 'archived'];
                if (!in_array($statusValue, $allowedStatuses, true)) {
                    throw new Exception('Invalid vacancy status.');
                }

                $employmentType = $_POST['employment_type'] ?? 'full_time';
                if (!array_key_exists($employmentType, $employmentTypeOptions)) {
                    $employmentType = 'full_time';
                }

                $seniorityLevel = $_POST['seniority_level'] ?? 'entry';
                if (!array_key_exists($seniorityLevel, $seniorityLevelOptions)) {
                    $seniorityLevel = 'entry';
                }

                $location = sanitizeInput($_POST['location'] ?? '');
                $salaryCurrency = strtoupper(trim($_POST['salary_currency'] ?? 'USD')) ?: 'USD';
                $salaryMin = $_POST['salary_min'] !== '' ? floatval($_POST['salary_min']) : null;
                $salaryMax = $_POST['salary_max'] !== '' ? floatval($_POST['salary_max']) : null;
                $salaryVisible = !empty($_POST['salary_visible']) ? 1 : 0;
                $openingDate = !empty($_POST['opening_date']) ? $_POST['opening_date'] : null;
                $closingDate = !empty($_POST['closing_date']) ? $_POST['closing_date'] : null;
                $recruiterId = !empty($_POST['recruiter_user_id']) ? intval($_POST['recruiter_user_id']) : null;
                $hiringManagerId = !empty($_POST['hiring_manager_id']) ? intval($_POST['hiring_manager_id']) : null;
                $description = trim($_POST['description'] ?? '') ?: null;
                $requirements = trim($_POST['requirements'] ?? '') ?: null;
                $responsibilities = trim($_POST['responsibilities'] ?? '') ?: null;
                $benefits = trim($_POST['benefits'] ?? '') ?: null;
                $departmentId = isset($_POST['department_id']) && $_POST['department_id'] !== '' ? intval($_POST['department_id']) : null;
                $positionId = isset($_POST['position_id']) && $_POST['position_id'] !== '' ? intval($_POST['position_id']) : null;

                $stmt = $pdo->prepare("
                    UPDATE recruitment_vacancies
                    SET 
                        title = ?,
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
                        department_id = ?,
                        position_id = ?,
                        updated_at = NOW(),
                        updated_by = NULL,
                        published_at = CASE
                            WHEN ? = 'published' AND (published_at IS NULL OR published_at = '0000-00-00 00:00:00') THEN NOW()
                            WHEN ? != 'published' THEN published_at
                            ELSE published_at
                        END
                    WHERE id = ?
                ");
                $stmt->execute([
                    $title,
                    $location ?: null,
                    $employmentType,
                    $seniorityLevel,
                    $salaryCurrency,
                    $salaryMin,
                    $salaryMax,
                    $salaryVisible,
                    $description,
                    $requirements,
                    $responsibilities,
                    $benefits,
                    $statusValue,
                    $openingDate ?: null,
                    $closingDate ?: null,
                    $recruiterId ?: null,
                    $hiringManagerId ?: null,
                    $departmentId ?: null,
                    $positionId ?: null,
                    $statusValue,
                    $statusValue,
                    $vacancyId,
                ]);

                flash('success', 'Vacancy updated successfully.');
                break;

            case 'delete_vacancy':
                $vacancyId = intval($_POST['vacancy_id'] ?? 0);
                if ($vacancyId <= 0) {
                    throw new Exception('Select a vacancy to delete.');
                }

                if (!recruitmentDeleteVacancy($pdo, $vacancyId)) {
                    throw new Exception('Vacancy could not be deleted or was already removed.');
                }

                flash('success', 'Vacancy deleted successfully.');
                break;

            case 'update_application_status':
                $applicationId = intval($_POST['application_id'] ?? 0);
                $newStatus = $_POST['new_status'] ?? '';
                $statusNote = trim($_POST['status_note'] ?? '');

                if ($applicationId <= 0 || $newStatus === '') {
                    throw new Exception('Select an application and status.');
                }

                if (!isset($statusMap[$newStatus])) {
                    throw new Exception('Unknown status selected.');
                }

                $pdo->beginTransaction();

                $currentStmt = $pdo->prepare("SELECT current_status FROM recruitment_applications WHERE id = ? FOR UPDATE");
                $currentStmt->execute([$applicationId]);
                $currentStatus = $currentStmt->fetchColumn();
                if (!$currentStatus) {
                    throw new Exception('Application not found.');
                }

                $historyStmt = $pdo->prepare("
                    INSERT INTO recruitment_application_status_history
                    (application_id, from_status, to_status, changed_by_user_id, changed_at, comment)
                    VALUES (?, ?, ?, ?, NOW(), ?)
                ");
                $historyStmt->execute([
                    $applicationId,
                    $currentStatus,
                    $newStatus,
                    null,
                    $statusNote ?: null,
                ]);

                $updateStmt = $pdo->prepare("
                    UPDATE recruitment_applications
                    SET current_status = ?, last_status_change = NOW(), updated_at = NOW()
                    WHERE id = ?
                ");
                $updateStmt->execute([$newStatus, $applicationId]);

                if (in_array($newStatus, ['hired', 'onboarding', 'employed'], true)) {
                    recruitmentCreateWorkerFromApplication($pdo, $applicationId, null);
                }

                $pdo->commit();

                flash('success', 'Application status updated.');
                break;

            case 'assign_application':
                $applicationId = intval($_POST['application_id'] ?? 0);
                $assignedUserId = !empty($_POST['assigned_to_user_id']) ? intval($_POST['assigned_to_user_id']) : null;

                if ($applicationId <= 0) {
                    throw new Exception('Select an application to assign.');
                }

                $stmt = $pdo->prepare("
                    UPDATE recruitment_applications
                    SET assigned_to_user_id = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$assignedUserId ?: null, $applicationId]);

                flash('success', 'Application assignment updated.');
                break;

            case 'add_application_note':
                $applicationId = intval($_POST['application_id'] ?? 0);
                $noteType = $_POST['note_type'] ?? 'general';
                $noteText = trim($_POST['note_text'] ?? '');

                if ($applicationId <= 0 || $noteText === '') {
                    throw new Exception('Application and note text are required.');
                }

                $stmt = $pdo->prepare("
                    INSERT INTO recruitment_application_notes (application_id, note_type, note_text, created_by, created_at)
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $applicationId,
                    $noteType,
                    $noteText,
                    null,
                ]);

                flash('success', 'Note added to application.');
                break;

            default:
                throw new Exception('Unknown action requested.');
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash('error', $e->getMessage());
    }

    redirect('recruitment.php');
    exit;
}

if ($featureEnabled && $initialized) {
    $metrics = getRecruitmentMetrics($pdo);
    $statusBreakdown = getRecruitmentBreakdown($pdo, 'current_status');
    $sourceBreakdown = getRecruitmentBreakdown($pdo, 'source');
    $vacancyLeaderboard = getVacancyLeaderboard($pdo);
    $recentVacancies = fetchRecentVacancies($pdo);
    $recentApplications = fetchRecentApplications($pdo, $statusMap);
    $applicationOptions = fetchApplications($pdo, ['status' => 'all', 'vacancy' => 'all', 'timeframe' => '365', 'search' => ''], $statusMap, 200);
    $noteTypes = [
        'general' => 'General',
        'interview' => 'Interview',
        'feedback' => 'Feedback',
        'offer' => 'Offer',
        'rejection' => 'Rejection',
    ];

    if (isset($_GET['export']) && in_array($_GET['export'], ['csv', 'json'], true)) {
        $exportRows = fetchApplications($pdo, $filters, $statusMap, null);

        if ($_GET['export'] === 'csv') {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="recruitment-applications-' . date('Ymd-His') . '.csv"');
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'Application Code',
                'Candidate',
                'Email',
                'Phone',
                'Vacancy',
                'Status',
                'Priority',
                'Source',
                'Applied',
                'Updated',
            ]);
            foreach ($exportRows as $row) {
                fputcsv($out, [
                    $row['application_code'],
                    $row['candidate_name'],
                    $row['email'],
                    $row['phone'],
                    $row['vacancy_title'],
                    $row['status_label'],
                    $row['priority'] ?: '',
                    $row['source'] ?: 'unknown',
                    $row['created_at'],
                    $row['last_status_change'] ?: $row['created_at'],
                ]);
            }
            fclose($out);
            exit;
        }

        header('Content-Type: application/json');
        echo json_encode([
            'generated_at' => date(DATE_ATOM),
            'filters' => $filters,
            'count' => count($exportRows),
            'data' => $exportRows,
        ]);
        exit;
    }

    $filteredApplications = fetchApplications($pdo, $filters, $statusMap, 60);
}

$flashMessage = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recruitment Workspace - <?php echo htmlspecialchars($companyName); ?> CMS</title>
    <?php
    $currentPage = 'recruitment';
    include 'header.php';
    ?>
    <style>
        .hidden { display: none; }
        .hero-card {
            background: linear-gradient(135deg, #1e3a8a, #2563eb, #38bdf8);
            color: #ffffff;
            padding: 28px 32px;
            border-radius: 20px;
            margin-bottom: 28px;
            box-shadow: 0 20px 35px rgba(30, 58, 138, 0.32);
            position: relative;
            overflow: hidden;
        }
        .hero-card::after {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at top right, rgba(255,255,255,0.32), transparent 55%);
            pointer-events: none;
        }
        .hero-card h1 {
            font-size: 28px;
            margin: 0 0 12px;
        }
        .hero-card p {
            margin: 0;
            font-size: 15px;
            max-width: 620px;
            color: rgba(255,255,255,0.85);
        }
        .hero-actions {
            margin-top: 20px;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        .hero-actions a {
            display: inline-flex;
            padding: 10px 18px;
            border-radius: 999px;
            text-decoration: none;
            font-weight: 600;
            color: #0f172a;
            background: #ffffff;
            box-shadow: 0 12px 28px rgba(15, 23, 42, 0.22);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .hero-actions a.secondary {
            background: transparent;
            color: #ffffff;
            border: 1px solid rgba(255,255,255,0.6);
            box-shadow: none;
        }
        .hero-actions a:hover {
            transform: translateY(-2px);
            box-shadow: 0 16px 36px rgba(15, 23, 42, 0.26);
        }
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }
        .metric-card {
            position: relative;
            overflow: hidden;
            border-radius: 16px;
            padding: 22px;
            color: #ffffff;
            box-shadow: 0 14px 32px rgba(15, 23, 42, 0.2);
        }
        .metric-card::after {
            content: '';
            position: absolute;
            top: -30px;
            right: -30px;
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: rgba(255,255,255,0.35);
        }
        .metric-card h2 {
            margin: 0;
            font-size: 15px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            opacity: 0.88;
        }
        .metric-card .metric-value {
            margin: 18px 0 6px;
            font-size: 32px;
            font-weight: 700;
        }
        .metric-card .metric-subtitle {
            font-size: 13px;
            opacity: 0.85;
        }
        .grid-two {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }
        .card {
            background: #ffffff;
            border: 1px solid #d6dae3;
            border-radius: 14px;
            padding: 22px;
            box-shadow: 0 12px 26px rgba(15, 23, 42, 0.1);
        }
        .card h3 {
            margin: 0;
            font-size: 18px;
            color: #1f2937;
        }
        .card p.helper {
            margin: 6px 0 16px;
            font-size: 13px;
            color: #6b7280;
        }
        .actions-list a {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 14px;
            border-radius: 10px;
            border: 1px solid rgba(37, 99, 235, 0.18);
            background: linear-gradient(135deg, rgba(37,99,235,0.1), rgba(59,130,246,0.08));
            text-decoration: none;
            color: #1f2937;
            font-weight: 600;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            margin-bottom: 10px;
        }
        .actions-list a:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 28px rgba(37, 99, 235, 0.22);
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 12px;
        }
        .form-grid textarea {
            min-height: 110px;
        }
        .form-label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            color: #64748b;
            letter-spacing: 0.05em;
            margin-bottom: 4px;
        }
        .form-grid input[type="text"],
        .form-grid input[type="search"],
        .form-grid input[type="date"],
        .form-grid select,
        .form-grid textarea {
            width: 100%;
            padding: 8px 10px;
            border-radius: 8px;
            border: 1px solid #cbd5f5;
            background: #ffffff;
            font-size: 13px;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        .form-grid input:focus,
        .form-grid select:focus,
        .form-grid textarea:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.16);
        }
        .form-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 6px;
        }
        .status-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 12px;
            border-radius: 10px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            margin-bottom: 10px;
            font-size: 14px;
        }
        .leaderboard {
            list-style: none;
            margin: 0;
            padding: 0;
        }
        .leaderboard li {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 12px;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            background: #f9fafb;
            margin-bottom: 10px;
            font-size: 14px;
        }
        .vacancy-entry,
        .application-entry {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 14px;
            background: #ffffff;
        }
        .vacancy-entry header,
        .application-entry header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
        }
        .inline-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            font-size: 12px;
            color: #6b7280;
            margin-top: 8px;
        }
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
            background: rgba(37, 99, 235, 0.12);
            color: var(--status-color, #2563eb);
        }
        .filters-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
            margin: 16px 0;
            padding: 14px 18px;
            border-radius: 12px;
            background: rgba(37, 99, 235, 0.08);
            border: 1px solid rgba(37, 99, 235, 0.2);
        }
        .filters-bar label {
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            color: #1f2937;
            letter-spacing: 0.05em;
        }
        .filters-bar select,
        .filters-bar input {
            border-radius: 8px;
            border: 1px solid #cbd5f5;
            padding: 7px 10px;
            font-size: 13px;
            background: #ffffff;
            min-width: 140px;
        }
        .filters-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
        }
        .btn-primary {
            background: #2563eb;
            color: #ffffff;
            box-shadow: 0 10px 20px rgba(37, 99, 235, 0.22);
        }
        .btn-secondary {
            background: #0ea5e9;
            color: #ffffff;
            box-shadow: 0 10px 20px rgba(14, 165, 233, 0.22);
        }
        .btn-outline {
            background: #ffffff;
            color: #2563eb;
            border: 1px solid #a3bffa;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 18px;
        }
        table.data-table th,
        table.data-table td {
            padding: 12px 14px;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
            font-size: 13px;
        }
        table.data-table th {
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #6b7280;
            font-size: 12px;
        }
        table.data-table tbody tr:hover {
            background: #f8fafc;
        }
        .empty-state {
            padding: 28px;
            border: 1px dashed #cbd5f5;
            border-radius: 12px;
            text-align: center;
            color: #6b7280;
            background: rgba(37, 99, 235, 0.05);
        }
        @media (max-width: 768px) {
            .hero-card h1 {
                font-size: 24px;
            }
            .filters-bar {
                flex-direction: column;
                align-items: flex-start;
            }
            .filters-bar select,
            .filters-bar input {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <main class="admin-main" style="padding: 32px;">
        <section class="hero-card">
            <h1>Recruitment Intelligence Hub</h1>
            <p>Monitor vacancy health, accelerate candidate review, and keep your talent pipeline humming — all without leaving the CMS.</p>
            <div class="hero-actions">
                <a href="<?php echo $baseUrl; ?>/modules/recruitment.php" target="_blank">Open ABBIS Recruitment</a>
                <a class="secondary" href="<?php echo $baseUrl; ?>/cms/public/vacancies.php" target="_blank">Preview Careers Page</a>
                <a class="secondary" href="<?php echo $baseUrl; ?>/api/recruitment-submit.php" target="_blank">API Endpoint</a>
            </div>
        </section>

        <?php if ($flashMessage): ?>
            <?php
                $flashPalette = [
                    'success' => ['#16a34a', 'rgba(22,163,74,0.12)'],
                    'error' => ['#dc2626', 'rgba(220,38,38,0.12)'],
                    'warning' => ['#d97706', 'rgba(217,119,6,0.12)'],
                    'info' => ['#2563eb', 'rgba(37,99,235,0.12)'],
                ];
                $flashColors = $flashPalette[$flashMessage['type']] ?? ['#2563eb', 'rgba(37,99,235,0.12)'];
            ?>
            <div class="card" style="border-left: 4px solid <?php echo $flashColors[0]; ?>; background: <?php echo $flashColors[1]; ?>;">
                <h3 style="margin-bottom:6px;"><?php echo ucfirst($flashMessage['type']); ?></h3>
                <p class="helper" style="margin:0;"><?php echo htmlspecialchars($flashMessage['message']); ?></p>
            </div>
        <?php endif; ?>

        <?php if (!$featureEnabled): ?>
            <div class="card" style="border-left: 4px solid #f97316; background: rgba(251, 191, 36, 0.1);">
                <h3>Recruitment module disabled</h3>
                <p class="helper">Enable the <strong>recruitment</strong> feature toggle within ABBIS to manage vacancies and candidates from the CMS.</p>
            </div>
        <?php elseif (!$initialized): ?>
            <div class="card" style="border-left: 4px solid #f97316; background: rgba(251, 191, 36, 0.1);">
                <h3>Database setup required</h3>
                <p class="helper">Run the recruitment initialization inside ABBIS (or execute the migration script) to create the necessary tables, then refresh this page.</p>
            </div>
        <?php endif; ?>

        <?php if ($featureEnabled && $initialized): ?>
        <section class="metrics-grid">
            <div class="metric-card" style="background:linear-gradient(135deg,#2563eb,#60a5fa);">
                <h2>Open Vacancies</h2>
                <div class="metric-value"><?php echo number_format($metrics['vacancies_published']); ?></div>
                <div class="metric-subtitle">of <?php echo number_format($metrics['vacancies_total']); ?> total postings</div>
            </div>
            <div class="metric-card" style="background:linear-gradient(135deg,#0ea5e9,#22d3ee);">
                <h2>Applications This Month</h2>
                <div class="metric-value"><?php echo number_format($metrics['applications_this_month']); ?></div>
                <div class="metric-subtitle">Total applications: <?php echo number_format($metrics['applications_total']); ?></div>
            </div>
            <div class="metric-card" style="background:linear-gradient(135deg,#ec4899,#f97316);">
                <h2>New This Week</h2>
                <div class="metric-value"><?php echo number_format($metrics['applications_week']); ?></div>
                <div class="metric-subtitle">Candidate submissions over the last seven days</div>
            </div>
            <div class="metric-card" style="background:linear-gradient(135deg,#10b981,#34d399);">
                <h2>Hires & Onboarding</h2>
                <div class="metric-value"><?php echo number_format($metrics['applications_hired']); ?></div>
                <div class="metric-subtitle">Candidates marked hired/onboarding/employed</div>
            </div>
            <div class="metric-card" style="background:linear-gradient(135deg,#8b5cf6,#c084fc);">
                <h2>Talent Pool Size</h2>
                <div class="metric-value"><?php echo number_format($metrics['candidates_total']); ?></div>
                <div class="metric-subtitle">Candidates searchable for future roles</div>
            </div>
        </section>

        <section class="grid-two">
            <div class="card">
                <h3>Create Vacancy</h3>
                <p class="helper">Publish new openings directly from the CMS.</p>
                <form method="post" class="form-grid">
                    <?php echo CSRF::getTokenField(); ?>
                    <input type="hidden" name="action" value="create_vacancy">

                    <div>
                        <label class="form-label" for="vacancy-title">Title *</label>
                        <input id="vacancy-title" type="text" name="title" required placeholder="e.g. Senior Drilling Engineer">
                    </div>

                    <div>
                        <label class="form-label" for="vacancy-location">Location</label>
                        <input id="vacancy-location" type="text" name="location" placeholder="City / Region">
                    </div>

                    <div>
                        <label class="form-label" for="vacancy-employment">Employment Type</label>
                        <select id="vacancy-employment" name="employment_type">
                            <?php foreach ($employmentTypeOptions as $value => $label): ?>
                                <option value="<?php echo $value; ?>"><?php echo htmlspecialchars($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="form-label" for="vacancy-seniority">Seniority</label>
                        <select id="vacancy-seniority" name="seniority_level">
                            <?php foreach ($seniorityLevelOptions as $value => $label): ?>
                                <option value="<?php echo $value; ?>"><?php echo htmlspecialchars($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="form-label" for="vacancy-status">Status</label>
                        <select id="vacancy-status" name="status">
                            <?php foreach (['draft' => 'Draft', 'published' => 'Published', 'closed' => 'Closed', 'archived' => 'Archived'] as $value => $label): ?>
                                <option value="<?php echo $value; ?>"><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="form-label" for="vacancy-opening">Opening Date</label>
                        <input id="vacancy-opening" type="date" name="opening_date">
                    </div>

                    <div>
                        <label class="form-label" for="vacancy-closing">Closing Date</label>
                        <input id="vacancy-closing" type="date" name="closing_date">
                    </div>

                    <div style="grid-column: 1 / -1;">
                        <label class="form-label" for="vacancy-description">Role Overview</label>
                        <textarea id="vacancy-description" name="description" placeholder="High-level description of the role"></textarea>
                    </div>

                    <div style="grid-column: 1 / -1;">
                        <label class="form-label" for="vacancy-responsibilities">Key Responsibilities</label>
                        <textarea id="vacancy-responsibilities" name="responsibilities" placeholder="Bullet points for responsibilities"></textarea>
                    </div>

                    <div style="grid-column: 1 / -1;">
                        <label class="form-label" for="vacancy-requirements">Requirements</label>
                        <textarea id="vacancy-requirements" name="requirements" placeholder="Required skills, qualifications, or experience"></textarea>
                    </div>

                    <div style="grid-column: 1 / -1;">
                        <label class="form-label" for="vacancy-benefits">Benefits</label>
                        <textarea id="vacancy-benefits" name="benefits" placeholder="Benefits offered to candidates"></textarea>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Create Vacancy</button>
                    </div>
                </form>
            </div>

            <div class="card">
                <h3>Update Vacancy</h3>
                <p class="helper">Close roles, reassign owners, or adjust timelines.</p>
                <?php if (empty($vacancyOptions)): ?>
                    <div class="empty-state">No vacancies available yet.</div>
                <?php else: ?>
                    <form method="post" class="form-grid" id="updateVacancyForm">
                        <?php echo CSRF::getTokenField(); ?>
                        <input type="hidden" name="action" value="update_vacancy">
                        <input type="hidden" name="department_id" id="update-department-id">
                        <input type="hidden" name="position_id" id="update-position-id">

                        <div>
                            <label class="form-label" for="update-vacancy-id">Vacancy</label>
                            <select id="update-vacancy-id" name="vacancy_id" required>
                                <option value="">Select vacancy</option>
                                <?php foreach ($vacancyOptions as $vacancy): ?>
                                    <option value="<?php echo (int) $vacancy['id']; ?>">
                                        <?php
                                            $label = $vacancy['title'];
                                            if (!empty($vacancy['vacancy_code'])) {
                                                $label .= ' (' . $vacancy['vacancy_code'] . ')';
                                            }
                                            echo htmlspecialchars($label);
                                        ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="form-label" for="update-vacancy-title">Title *</label>
                            <input id="update-vacancy-title" type="text" name="title" required>
                        </div>

                        <div>
                            <label class="form-label" for="update-vacancy-location">Location</label>
                            <input id="update-vacancy-location" type="text" name="location">
                        </div>

                        <div>
                            <label class="form-label" for="update-vacancy-employment">Employment Type</label>
                            <select id="update-vacancy-employment" name="employment_type">
                                <?php foreach ($employmentTypeOptions as $value => $label): ?>
                                    <option value="<?php echo $value; ?>"><?php echo htmlspecialchars($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="form-label" for="update-vacancy-seniority">Seniority</label>
                            <select id="update-vacancy-seniority" name="seniority_level">
                                <?php foreach ($seniorityLevelOptions as $value => $label): ?>
                                    <option value="<?php echo $value; ?>"><?php echo htmlspecialchars($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="form-label" for="update-salary-currency">Salary Currency</label>
                            <input id="update-salary-currency" type="text" name="salary_currency" placeholder="e.g. GHS">
                        </div>

                        <div>
                            <label class="form-label" for="update-salary-min">Salary Min</label>
                            <input id="update-salary-min" type="number" step="0.01" name="salary_min">
                        </div>

                        <div>
                            <label class="form-label" for="update-salary-max">Salary Max</label>
                            <input id="update-salary-max" type="number" step="0.01" name="salary_max">
                        </div>

                        <div style="display:flex; align-items:center; gap:8px;">
                            <input type="hidden" name="salary_visible" value="0">
                            <label class="form-checkbox" style="margin-top:22px;">
                                <input id="update-salary-visible" type="checkbox" name="salary_visible" value="1">
                                <span>Display salary range</span>
                            </label>
                        </div>

                        <div>
                            <label class="form-label" for="update-vacancy-status">Status</label>
                            <select id="update-vacancy-status" name="status">
                                <?php foreach (['draft' => 'Draft', 'published' => 'Published', 'closed' => 'Closed', 'archived' => 'Archived'] as $value => $label): ?>
                                    <option value="<?php echo $value; ?>"><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="form-label" for="update-opening-date">Opening Date</label>
                            <input id="update-opening-date" type="date" name="opening_date">
                        </div>

                        <div>
                            <label class="form-label" for="update-vacancy-closing">Closing Date</label>
                            <input id="update-vacancy-closing" type="date" name="closing_date">
                        </div>

                        <div>
                            <label class="form-label" for="update-recruiter">Recruiter</label>
                            <select id="update-recruiter" name="recruiter_user_id">
                                <option value="">Unassigned</option>
                                <?php foreach ($abbisUsers as $user): ?>
                                    <option value="<?php echo (int) $user['id']; ?>"><?php echo htmlspecialchars($user['full_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="form-label" for="update-hiring-manager">Hiring Manager</label>
                            <select id="update-hiring-manager" name="hiring_manager_id">
                                <option value="">Unassigned</option>
                                <?php foreach ($abbisUsers as $user): ?>
                                    <option value="<?php echo (int) $user['id']; ?>"><?php echo htmlspecialchars($user['full_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div style="grid-column: 1 / -1;">
                            <label class="form-label" for="update-vacancy-description">Role Overview</label>
                            <textarea id="update-vacancy-description" name="description" placeholder="High-level description of the role"></textarea>
                        </div>

                        <div style="grid-column: 1 / -1;">
                            <label class="form-label" for="update-vacancy-responsibilities">Key Responsibilities</label>
                            <textarea id="update-vacancy-responsibilities" name="responsibilities" placeholder="Bullet points for responsibilities"></textarea>
                        </div>

                        <div style="grid-column: 1 / -1;">
                            <label class="form-label" for="update-vacancy-requirements">Requirements</label>
                            <textarea id="update-vacancy-requirements" name="requirements" placeholder="Required skills, qualifications, or experience"></textarea>
                        </div>

                        <div style="grid-column: 1 / -1;">
                            <label class="form-label" for="update-vacancy-benefits">Benefits</label>
                            <textarea id="update-vacancy-benefits" name="benefits" placeholder="Benefits offered to candidates"></textarea>
                        </div>

                        <div class="form-actions">
                            <button type="button" class="btn btn-danger" id="update-vacancy-delete">Delete Vacancy</button>
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </section>

        <section class="grid-two">
            <div class="card">
                <h3>Update Application Status</h3>
                <p class="helper">Move candidates through the pipeline.</p>
                <?php if (empty($applicationOptions)): ?>
                    <div class="empty-state">No applications available yet.</div>
                <?php else: ?>
                    <form method="post" class="form-grid">
                        <?php echo CSRF::getTokenField(); ?>
                        <input type="hidden" name="action" value="update_application_status">

                        <div>
                            <label class="form-label" for="status-application-id">Application</label>
                            <select id="status-application-id" name="application_id" required>
                                <option value="">Select application</option>
                                <?php foreach ($applicationOptions as $application): ?>
                                    <option value="<?php echo (int) $application['id']; ?>">
                                        <?php
                                            $optionLabel = $application['candidate_name'] ?: ('Application ' . $application['application_code']);
                                            $optionLabel .= ' • ' . ($application['vacancy_title'] ?? 'Vacancy');
                                            $optionLabel .= ' • ' . ($application['status_label'] ?? ucfirst($application['current_status']));
                                            echo htmlspecialchars($optionLabel);
                                        ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="form-label" for="status-new-status">Status</label>
                            <select id="status-new-status" name="new_status" required>
                                <?php foreach ($statusMap as $key => $meta): ?>
                                    <option value="<?php echo htmlspecialchars($key); ?>"><?php echo htmlspecialchars($meta['status_label']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div style="grid-column: 1 / -1;">
                            <label class="form-label" for="status-note">Comment (optional)</label>
                            <textarea id="status-note" name="status_note" placeholder="Add context for the status change"></textarea>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Update Status</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>

            <div class="card">
                <h3>Assignment & Notes</h3>
                <p class="helper">Assign ownership or capture internal notes.</p>
                <?php if (empty($applicationOptions)): ?>
                    <div class="empty-state">No applications available yet.</div>
                <?php else: ?>
                    <form method="post" class="form-grid" style="margin-bottom: 18px;">
                        <?php echo CSRF::getTokenField(); ?>
                        <input type="hidden" name="action" value="assign_application">

                        <div>
                            <label class="form-label" for="assign-application-id">Application</label>
                            <select id="assign-application-id" name="application_id" required>
                                <option value="">Select application</option>
                                <?php foreach ($applicationOptions as $application): ?>
                                    <option value="<?php echo (int) $application['id']; ?>">
                                        <?php
                                            $optionLabel = $application['candidate_name'] ?: ('Application ' . $application['application_code']);
                                            $optionLabel .= ' • ' . ($application['vacancy_title'] ?? 'Vacancy');
                                            echo htmlspecialchars($optionLabel);
                                        ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="form-label" for="assign-user-id">Assign To</label>
                            <select id="assign-user-id" name="assigned_to_user_id">
                                <option value="">Unassigned</option>
                                <?php foreach ($abbisUsers as $user): ?>
                                    <option value="<?php echo (int) $user['id']; ?>"><?php echo htmlspecialchars($user['full_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Assign Owner</button>
                        </div>
                    </form>

                    <form method="post" class="form-grid">
                        <?php echo CSRF::getTokenField(); ?>
                        <input type="hidden" name="action" value="add_application_note">

                        <div>
                            <label class="form-label" for="note-application-id">Application</label>
                            <select id="note-application-id" name="application_id" required>
                                <option value="">Select application</option>
                                <?php foreach ($applicationOptions as $application): ?>
                                    <option value="<?php echo (int) $application['id']; ?>">
                                        <?php
                                            $optionLabel = $application['candidate_name'] ?: ('Application ' . $application['application_code']);
                                            $optionLabel .= ' • ' . ($application['vacancy_title'] ?? 'Vacancy');
                                            echo htmlspecialchars($optionLabel);
                                        ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="form-label" for="note-type">Note Type</label>
                            <select id="note-type" name="note_type">
                                <?php foreach ($noteTypes as $key => $label): ?>
                                    <option value="<?php echo htmlspecialchars($key); ?>"><?php echo htmlspecialchars($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div style="grid-column: 1 / -1;">
                            <label class="form-label" for="note-text">Internal Note</label>
                            <textarea id="note-text" name="note_text" required placeholder="Interview feedback, client comments, next steps..."></textarea>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Add Note</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </section>

        <section class="grid-two">
            <div class="card">
                <h3>Quick Actions</h3>
                <p class="helper">Jump straight to the most common recruitment tasks.</p>
                <div class="actions-list">
                    <a href="<?php echo $baseUrl; ?>/modules/recruitment.php" target="_blank">Manage workflow<span>↗</span></a>
                    <a href="<?php echo $baseUrl; ?>/cms/public/vacancies.php" target="_blank">Review public vacancies<span>↗</span></a>
                    <a href="<?php echo $baseUrl; ?>/api/recruitment-submit.php" target="_blank">Submit via API<span>↗</span></a>
                    <a href="<?php echo $baseUrl; ?>/cms/admin/menus.php">Add to navigation<span>⚙︎</span></a>
                </div>
            </div>
            <div class="card">
                <h3>Pipeline Snapshot</h3>
                <p class="helper">Applications grouped by current status.</p>
                <?php if (empty($statusBreakdown)): ?>
                    <div class="empty-state">No applications captured yet.</div>
                <?php else: ?>
                    <?php foreach ($statusBreakdown as $row): ?>
                        <div class="status-row">
                            <?php echo formatStatusBadge($row['label'], $statusMap); ?>
                            <strong><?php echo number_format($row['total']); ?></strong>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="card">
                <h3>Top Candidate Sources</h3>
                <p class="helper">Where applicants are coming from (top 6).</p>
                <?php if (empty($sourceBreakdown)): ?>
                    <div class="empty-state">Source tags will appear once applications arrive.</div>
                <?php else: ?>
                    <?php foreach ($sourceBreakdown as $row): ?>
                        <div class="status-row">
                            <span><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $row['label']))); ?></span>
                            <strong><?php echo number_format($row['total']); ?></strong>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="card">
                <h3>Vacancies by Applications</h3>
                <p class="helper">Roles attracting the most activity.</p>
                <?php if (empty($vacancyLeaderboard)): ?>
                    <div class="empty-state">No vacancies published yet.</div>
                <?php else: ?>
                    <ul class="leaderboard">
                        <?php foreach ($vacancyLeaderboard as $entry): ?>
                            <li>
                                <span>
                                    <strong><?php echo htmlspecialchars($entry['title']); ?></strong><br>
                                    <small style="color:#6b7280;">Code: <?php echo htmlspecialchars($entry['vacancy_code']); ?></small>
                                </span>
                                <strong><?php echo number_format($entry['total']); ?></strong>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </section>

        <section class="card">
            <h3>Latest Vacancies</h3>
            <p class="helper">Newest openings synced from ABBIS.</p>
            <?php if (empty($recentVacancies)): ?>
                <div class="empty-state">No vacancies have been created yet.</div>
            <?php else: ?>
                <?php foreach ($recentVacancies as $vacancy): ?>
                    <article class="vacancy-entry">
                        <header>
                            <div>
                                <strong style="font-size:16px;"><?php echo htmlspecialchars($vacancy['title']); ?></strong>
                                <div class="inline-meta">
                                    <span>Code: <?php echo htmlspecialchars($vacancy['vacancy_code']); ?></span>
                                    <?php if (!empty($vacancy['location'])): ?>
                                        <span>📍 <?php echo htmlspecialchars($vacancy['location']); ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($vacancy['employment_type'])): ?>
                                        <span><?php echo ucfirst(str_replace('_', ' ', $vacancy['employment_type'])); ?></span>
                                    <?php endif; ?>
                                    <span>Applications: <?php echo number_format($vacancy['application_count']); ?></span>
                                </div>
                            </div>
                            <?php echo formatStatusBadge($vacancy['status'], $statusMap); ?>
                        </header>
                        <div class="inline-meta" style="margin-top:10px;">
                            <span>Opened: <?php echo $vacancy['opening_date'] ? date('M d, Y', strtotime($vacancy['opening_date'])) : '—'; ?></span>
                            <span>Closing: <?php echo $vacancy['closing_date'] ? date('M d, Y', strtotime($vacancy['closing_date'])) : '—'; ?></span>
                            <span>Created: <?php echo date('M d, Y', strtotime($vacancy['created_at'])); ?></span>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>

        <section class="card">
            <h3>Candidate Pipeline</h3>
            <p class="helper">Filter applications and export your view when needed.</p>

            <div class="filters-bar">
                <form method="get" style="display:flex; flex-wrap:wrap; gap:12px; align-items:center;">
                    <label for="filter-status">Status</label>
                    <select id="filter-status" name="status">
                        <option value="all" <?php echo $filters['status'] === 'all' ? 'selected' : ''; ?>>Any</option>
                        <?php foreach ($statusMap as $key => $meta): ?>
                            <option value="<?php echo htmlspecialchars($key); ?>" <?php echo $filters['status'] === $key ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($meta['status_label']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label for="filter-vacancy">Vacancy</label>
                    <select id="filter-vacancy" name="vacancy">
                        <option value="all" <?php echo $filters['vacancy'] === 'all' ? 'selected' : ''; ?>>All vacancies</option>
                        <?php foreach ($vacancyOptions as $option): ?>
                            <option value="<?php echo (int) $option['id']; ?>" <?php echo (string)$filters['vacancy'] === (string)$option['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($option['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label for="filter-timeframe">Created</label>
                    <select id="filter-timeframe" name="timeframe">
                        <option value="7" <?php echo $filters['timeframe'] === '7' ? 'selected' : ''; ?>>7 days</option>
                        <option value="30" <?php echo $filters['timeframe'] === '30' ? 'selected' : ''; ?>>30 days</option>
                        <option value="90" <?php echo $filters['timeframe'] === '90' ? 'selected' : ''; ?>>90 days</option>
                        <option value="180" <?php echo $filters['timeframe'] === '180' ? 'selected' : ''; ?>>6 months</option>
                        <option value="365" <?php echo $filters['timeframe'] === '365' ? 'selected' : ''; ?>>12 months</option>
                        <option value="all" <?php echo $filters['timeframe'] === 'all' ? 'selected' : ''; ?>>All time</option>
                    </select>

                    <label for="filter-search">Search</label>
                    <input id="filter-search" type="search" name="search" placeholder="Name, email, application code" value="<?php echo htmlspecialchars($filters['search']); ?>">

                    <div class="filters-actions">
                        <button class="btn btn-primary" type="submit">Apply</button>
                        <?php if ($filters['status'] !== 'all' || $filters['vacancy'] !== 'all' || $filters['timeframe'] !== '30' || $filters['search'] !== ''): ?>
                            <a class="btn btn-outline" href="recruitment.php">Reset filters</a>
                        <?php endif; ?>
                    </div>
                </form>

                <div class="filters-actions">
                    <a class="btn btn-primary" href="<?php echo buildExportUrl('csv'); ?>">Export CSV</a>
                    <a class="btn btn-secondary" href="<?php echo buildExportUrl('json'); ?>">Export JSON</a>
                </div>
            </div>

            <?php if (empty($filteredApplications)): ?>
                <div class="empty-state">No applications match the current filters.</div>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Candidate</th>
                            <th>Vacancy</th>
                            <th>Status</th>
                            <th>Source</th>
                            <th>Priority</th>
                            <th>Applied</th>
                            <th>Updated</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($filteredApplications as $row): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($row['candidate_name']); ?></strong><br>
                                    <span style="color:#6b7280;"><?php echo htmlspecialchars($row['email']); ?></span>
                                </td>
                                <td><?php echo htmlspecialchars($row['vacancy_title']); ?></td>
                                <td><?php echo formatStatusBadge($row['current_status'], $statusMap); ?></td>
                                <td><?php echo htmlspecialchars($row['source'] ?: 'unknown'); ?></td>
                                <td><?php echo $row['priority'] ? ucfirst($row['priority']) : '—'; ?></td>
                                <td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                                <td><?php echo date('M d, Y', strtotime($row['last_status_change'] ?: $row['created_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
        <?php endif; ?>
    </main>

    <?php if ($featureEnabled && $initialized): ?>
    <script>
    const cmsVacancyData = <?php echo json_encode($vacancyDataMap, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    const updateForm = document.getElementById('updateVacancyForm');
    const deleteButton = document.getElementById('update-vacancy-delete');
    const actionInput = updateForm?.querySelector('input[name="action"]');
    const defaultActionValue = actionInput ? actionInput.value : 'update_vacancy';

    function resetUpdateVacancyForm() {
        const title = document.getElementById('update-vacancy-title');
        if (!title) return;
        if (actionInput) {
            actionInput.value = defaultActionValue;
        }
        title.value = '';
        document.getElementById('update-vacancy-location').value = '';
        document.getElementById('update-vacancy-employment').selectedIndex = 0;
        document.getElementById('update-vacancy-seniority').selectedIndex = 0;
        document.getElementById('update-salary-currency').value = '';
        document.getElementById('update-salary-min').value = '';
        document.getElementById('update-salary-max').value = '';
        document.getElementById('update-salary-visible').checked = false;
        document.getElementById('update-vacancy-status').selectedIndex = 0;
        document.getElementById('update-opening-date').value = '';
        document.getElementById('update-vacancy-closing').value = '';
        document.getElementById('update-recruiter').selectedIndex = 0;
        document.getElementById('update-hiring-manager').selectedIndex = 0;
        document.getElementById('update-vacancy-description').value = '';
        document.getElementById('update-vacancy-responsibilities').value = '';
        document.getElementById('update-vacancy-requirements').value = '';
        document.getElementById('update-vacancy-benefits').value = '';
        document.getElementById('update-department-id').value = '';
        document.getElementById('update-position-id').value = '';
    }

    function populateUpdateVacancyForm(vacancyId) {
        resetUpdateVacancyForm();
        const data = cmsVacancyData[vacancyId];
        if (!data) {
            return;
        }

        document.getElementById('update-vacancy-title').value = data.title || '';
        document.getElementById('update-vacancy-location').value = data.location || '';
        if (data.employment_type && document.querySelector(`#update-vacancy-employment option[value="${data.employment_type}"]`)) {
            document.getElementById('update-vacancy-employment').value = data.employment_type;
        }
        if (data.seniority_level && document.querySelector(`#update-vacancy-seniority option[value="${data.seniority_level}"]`)) {
            document.getElementById('update-vacancy-seniority').value = data.seniority_level;
        }
        document.getElementById('update-salary-currency').value = data.salary_currency || '';
        document.getElementById('update-salary-min').value = data.salary_min !== null ? data.salary_min : '';
        document.getElementById('update-salary-max').value = data.salary_max !== null ? data.salary_max : '';
        document.getElementById('update-salary-visible').checked = Number(data.salary_visible) === 1;
        if (data.status && document.querySelector(`#update-vacancy-status option[value="${data.status}"]`)) {
            document.getElementById('update-vacancy-status').value = data.status;
        }
        document.getElementById('update-opening-date').value = data.opening_date ? String(data.opening_date).substring(0, 10) : '';
        document.getElementById('update-vacancy-closing').value = data.closing_date ? String(data.closing_date).substring(0, 10) : '';
        if (data.recruiter_user_id && document.querySelector(`#update-recruiter option[value="${data.recruiter_user_id}"]`)) {
            document.getElementById('update-recruiter').value = data.recruiter_user_id;
        }
        if (data.hiring_manager_id && document.querySelector(`#update-hiring-manager option[value="${data.hiring_manager_id}"]`)) {
            document.getElementById('update-hiring-manager').value = data.hiring_manager_id;
        }
        document.getElementById('update-vacancy-description').value = data.description || '';
        document.getElementById('update-vacancy-responsibilities').value = data.responsibilities || '';
        document.getElementById('update-vacancy-requirements').value = data.requirements || '';
        document.getElementById('update-vacancy-benefits').value = data.benefits || '';
        document.getElementById('update-department-id').value = data.department_id || '';
        document.getElementById('update-position-id').value = data.position_id || '';
    }

    const vacancySelect = document.getElementById('update-vacancy-id');
    if (vacancySelect) {
        vacancySelect.addEventListener('change', function () {
            populateUpdateVacancyForm(this.value);
        });
        if (vacancySelect.value) {
            populateUpdateVacancyForm(vacancySelect.value);
        } else {
            resetUpdateVacancyForm();
        }
    }

    if (deleteButton && updateForm && actionInput) {
        deleteButton.addEventListener('click', function () {
            const selectedId = vacancySelect?.value;
            if (!selectedId) {
                alert('Select a vacancy before attempting to delete.');
                return;
            }
            const title = cmsVacancyData[selectedId]?.title || 'this vacancy';
            if (confirm(`Delete ${title}? This will remove all associated applications and notes.`)) {
                actionInput.value = 'delete_vacancy';
                updateForm.submit();
                actionInput.value = defaultActionValue;
            }
        });
    }
    </script>
    <?php endif; ?>
</body>
</html>
<?php

function loadRecruitmentStatuses(PDO $pdo): array {
    try {
        $stmt = $pdo->query("SELECT status_key, status_label, color_hex FROM recruitment_statuses WHERE is_active = 1 ORDER BY sort_order ASC");
        return $stmt->fetchAll(PDO::FETCH_UNIQUE|PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function loadVacancyOptions(PDO $pdo): array {
    try {
        return $pdo->query("
            SELECT 
                id,
                title,
                vacancy_code,
                status,
                location,
                employment_type,
                seniority_level,
                opening_date,
                closing_date,
                description,
                requirements,
                responsibilities,
                benefits,
                salary_currency,
                salary_min,
                salary_max,
                salary_visible,
                recruiter_user_id,
                hiring_manager_id,
                department_id,
                position_id
            FROM recruitment_vacancies
            ORDER BY created_at DESC
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function loadAbbIsUsers(PDO $pdo): array {
    try {
        return $pdo->query("SELECT id, full_name FROM users WHERE is_active = 1 ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function getRecruitmentMetrics(PDO $pdo): array {
    $metrics = [
        'vacancies_total' => 0,
        'vacancies_published' => 0,
        'applications_total' => 0,
        'applications_this_month' => 0,
        'applications_week' => 0,
        'applications_hired' => 0,
        'candidates_total' => 0,
    ];

    try {
        $metrics['vacancies_total'] = (int) $pdo->query("SELECT COUNT(*) FROM recruitment_vacancies")->fetchColumn();
        $metrics['vacancies_published'] = (int) $pdo->query("SELECT COUNT(*) FROM recruitment_vacancies WHERE status = 'published'")->fetchColumn();
        $metrics['applications_total'] = (int) $pdo->query("SELECT COUNT(*) FROM recruitment_applications")->fetchColumn();
        $metrics['applications_this_month'] = (int) $pdo->query("
            SELECT COUNT(*) FROM recruitment_applications
            WHERE DATE_FORMAT(created_at, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')
        ")->fetchColumn();
        $metrics['applications_week'] = (int) $pdo->query("
            SELECT COUNT(*) FROM recruitment_applications
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ")->fetchColumn();
        $metrics['applications_hired'] = (int) $pdo->query("
            SELECT COUNT(*) FROM recruitment_applications
            WHERE current_status IN ('hired','onboarding','employed')
        ")->fetchColumn();
        $metrics['candidates_total'] = (int) $pdo->query("SELECT COUNT(*) FROM recruitment_candidates")->fetchColumn();
    } catch (Throwable $e) {
        // ignore
    }

    return $metrics;
}

function getRecruitmentBreakdown(PDO $pdo, string $column): array {
    $allowed = [
        'current_status' => 'current_status',
        'source' => "COALESCE(NULLIF(source, ''), 'unknown')",
    ];

    if (!isset($allowed[$column])) {
        return [];
    }

    try {
        $stmt = $pdo->query("
            SELECT {$allowed[$column]} AS label, COUNT(*) AS total
            FROM recruitment_applications
            GROUP BY label
            ORDER BY total DESC
            LIMIT 12
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function getVacancyLeaderboard(PDO $pdo): array {
    try {
        $stmt = $pdo->query("
            SELECT v.title, v.vacancy_code, COUNT(a.id) AS total
            FROM recruitment_vacancies v
            LEFT JOIN recruitment_applications a ON a.vacancy_id = v.id
            GROUP BY v.id
            ORDER BY total DESC, v.created_at DESC
            LIMIT 5
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function fetchRecentVacancies(PDO $pdo, int $limit = 6): array {
    try {
        $stmt = $pdo->prepare("
            SELECT v.id, v.vacancy_code, v.title, v.status, v.employment_type, v.location,
                   v.opening_date, v.closing_date, v.created_at,
                   (SELECT COUNT(*) FROM recruitment_applications a WHERE a.vacancy_id = v.id) AS application_count
            FROM recruitment_vacancies v
            ORDER BY v.created_at DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function fetchRecentApplications(PDO $pdo, array $statusMap, int $limit = 10): array {
    try {
        $stmt = $pdo->prepare("
            SELECT a.application_code, a.current_status, a.created_at, a.last_status_change,
                   a.priority, a.rating, a.source,
                   c.first_name, c.last_name, c.email, c.phone_primary,
                   v.title AS vacancy_title
            FROM recruitment_applications a
            INNER JOIN recruitment_candidates c ON c.id = a.candidate_id
            INNER JOIN recruitment_vacancies v ON v.id = a.vacancy_id
            ORDER BY a.created_at DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return array_map(function ($row) use ($statusMap) {
            $row['candidate_name'] = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
            $row['status_label'] = $statusMap[$row['current_status']]['status_label'] ?? ucfirst(str_replace('_', ' ', $row['current_status']));
            return $row;
        }, $rows);
    } catch (Throwable $e) {
        return [];
    }
}

function fetchApplications(PDO $pdo, array $filters, array $statusMap, ?int $limit = 200): array {
    $conditions = [];
    $params = [];

    if ($filters['status'] !== 'all') {
        $conditions[] = 'a.current_status = ?';
        $params[] = $filters['status'];
    }

    if ($filters['vacancy'] !== 'all') {
        $conditions[] = 'a.vacancy_id = ?';
        $params[] = (int) $filters['vacancy'];
    }

    if ($filters['search'] !== '') {
        $search = '%' . $filters['search'] . '%';
        $conditions[] = "(c.first_name LIKE ? OR c.last_name LIKE ? OR c.email LIKE ? OR a.application_code LIKE ?)";
        $params = array_merge($params, [$search, $search, $search, $search]);
    }

    if ($filters['timeframe'] !== 'all') {
        $conditions[] = 'a.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)';
        $params[] = (int) $filters['timeframe'];
    }

    $whereClause = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
    $limitClause = $limit ? 'LIMIT ' . (int) $limit : '';

    $sql = "
        SELECT a.id, a.application_code, a.current_status, a.created_at, a.last_status_change,
               a.priority, a.source,
               c.first_name, c.last_name, c.email, c.phone_primary,
               v.title AS vacancy_title
        FROM recruitment_applications a
        INNER JOIN recruitment_candidates c ON c.id = a.candidate_id
        INNER JOIN recruitment_vacancies v ON v.id = a.vacancy_id
        $whereClause
        ORDER BY a.created_at DESC
        $limitClause
    ";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(function ($row) use ($statusMap) {
            $row['id'] = (int) ($row['id'] ?? 0);
            $row['candidate_name'] = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
            $row['status_label'] = $statusMap[$row['current_status']]['status_label'] ?? ucfirst(str_replace('_', ' ', $row['current_status']));
            $row['email'] = $row['email'] ?? '';
            $row['phone'] = $row['phone_primary'] ?? '';
            return $row;
        }, $rows);
    } catch (Throwable $e) {
        return [];
    }
}

function buildExportUrl(string $type): string {
    $query = $_GET;
    $query['export'] = $type;
    return 'recruitment.php?' . http_build_query($query);
}

function getSystemConfig(string $key, string $default = ''): string {
    static $cache = [];
    if (isset($cache[$key])) {
        return $cache[$key];
    }

    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT config_value FROM system_config WHERE config_key = ?");
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();
        $cache[$key] = $value ?: $default;
        return $cache[$key];
    } catch (Throwable $e) {
        return $default;
    }
}

function formatStatusBadge(string $statusKey, array $statusMap): string {
    $label = $statusMap[$statusKey]['status_label'] ?? ucfirst(str_replace('_', ' ', $statusKey));
    $color = $statusMap[$statusKey]['color_hex'] ?? '#2563eb';
    return '<span class="status-badge" style="--status-color:' . htmlspecialchars($color) . ';">' . htmlspecialchars($label) . '</span>';
}

