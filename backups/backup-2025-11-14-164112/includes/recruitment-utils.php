<?php
/**
 * Recruitment utility helpers
 * Shared functions for vacancy and applicant management.
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/functions.php';

/**
 * Safely verify and format identifier (table/column) names before interpolation.
 */
function recruitmentValidateIdentifier($identifier) {
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $identifier)) {
        throw new InvalidArgumentException('Invalid identifier provided');
    }
    return $identifier;
}

/**
 * Generate a new formatted code for a recruitment entity.
 */
function recruitmentGenerateCode(PDO $pdo, $table, $column, $prefix = 'RC', $padLength = 6) {
    $table = recruitmentValidateIdentifier($table);
    $column = recruitmentValidateIdentifier($column);

    $likePrefix = $prefix . '-%';
    $sql = "
        SELECT {$column} 
        FROM {$table} 
        WHERE {$column} LIKE ? 
        ORDER BY {$column} DESC 
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$likePrefix]);
    $lastCode = $stmt->fetchColumn();

    if ($lastCode) {
        $numeric = intval(substr($lastCode, strlen($prefix) + 1));
    } else {
        $numeric = 0;
    }

    $next = $numeric + 1;
    return sprintf('%s-%0' . intval($padLength) . 'd', $prefix, $next);
}

/**
 * Fetch recruitment statuses keyed by status_key.
 */
function recruitmentGetStatuses(PDO $pdo) {
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    try {
        $stmt = $pdo->query("
            SELECT status_key, status_label, status_group, sort_order, color_hex, is_terminal
            FROM recruitment_statuses
            WHERE is_active = 1
            ORDER BY sort_order ASC, status_label ASC
        ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $cache = [];
        foreach ($rows as $row) {
            $cache[$row['status_key']] = $row;
        }
    } catch (Throwable $e) {
        $cache = [
            'new' => [
                'status_key' => 'new',
                'status_label' => 'New Application',
                'status_group' => 'pipeline',
                'sort_order' => 10,
                'color_hex' => '#2563eb',
                'is_terminal' => 0,
            ],
        ];
    }

    return $cache;
}

/**
 * Ensure recruitment tables exist.
 */
function recruitmentTablesExist(PDO $pdo) {
    try {
        $pdo->query("SELECT 1 FROM recruitment_vacancies LIMIT 1");
        $pdo->query("SELECT 1 FROM recruitment_applications LIMIT 1");
        $pdo->query("SELECT 1 FROM recruitment_candidates LIMIT 1");
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function recruitmentEnsureInitialized(PDO $pdo) {
    static $initialized = null;

    if ($initialized === true) {
        return true;
    }
    if ($initialized === false) {
        return false;
    }

    if (recruitmentTablesExist($pdo)) {
        $initialized = true;
        return true;
    }

    $rootPath = dirname(__DIR__);
    $migrationFile = $rootPath . '/database/recruitment_module_migration.sql';

    if (!file_exists($migrationFile)) {
        error_log('Recruitment migration file missing: ' . $migrationFile);
        $initialized = false;
        return false;
    }

    $sql = file_get_contents($migrationFile);
    if ($sql === false) {
        error_log('Unable to read recruitment migration file: ' . $migrationFile);
        $initialized = false;
        return false;
    }

    $sql = preg_replace('/USE\s+`?[\w_]+`?\s*;/i', '', $sql);

    $lines = preg_split('/\r\n|\r|\n/', $sql);
    $statements = [];
    $current = '';
    $inPrepareBlock = false;
    $inBlockComment = false;

    foreach ($lines as $line) {
        $trimmedLine = trim($line);

        if ($inBlockComment) {
            if (strpos($trimmedLine, '*/') !== false) {
                $inBlockComment = false;
            }
            continue;
        }

        if ($trimmedLine === '' || strpos($trimmedLine, '--') === 0) {
            continue;
        }

        if (strpos($trimmedLine, '/*') === 0) {
            if (strpos($trimmedLine, '*/') === false) {
                $inBlockComment = true;
            }
            continue;
        }

        if (stripos($trimmedLine, 'DELIMITER') === 0) {
            continue;
        }

        if (preg_match('/SET\s+@sql\s*=/i', $trimmedLine)) {
            $inPrepareBlock = true;
        }

        $current .= ($current !== '' ? "\n" : '') . $trimmedLine;

        if (preg_match('/DEALLOCATE\s+PREPARE/i', $trimmedLine)) {
            $inPrepareBlock = false;
            if (!empty(trim($current))) {
                $statements[] = trim($current);
            }
            $current = '';
            continue;
        }

        if (!$inPrepareBlock && substr($trimmedLine, -1) === ';') {
            if (!empty(trim($current))) {
                $statements[] = trim($current);
            }
            $current = '';
        }
    }

    if (!empty(trim($current))) {
        $statements[] = trim($current);
    }

    try {
        $pdo->beginTransaction();
        try {
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        } catch (PDOException $ignored) {}

        foreach ($statements as $statement) {
            $statement = trim($statement);
            if ($statement === '') {
                continue;
            }

            if (preg_match('/^\s*SELECT\s+1/i', $statement)) {
                continue;
            }

            try {
                $pdo->exec($statement);
            } catch (PDOException $e) {
                $msg = $e->getMessage();
                if (strpos($msg, 'already exists') !== false ||
                    strpos($msg, 'Duplicate') !== false ||
                    strpos($msg, '1060') !== false ||
                    strpos($msg, '1061') !== false ||
                    preg_match('/Duplicate column name/i', $msg) ||
                    preg_match('/Duplicate key name/i', $msg)) {
                    continue;
                }
                throw $e;
            }
        }

        try {
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        } catch (PDOException $ignored) {}

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Recruitment migration failed: ' . $e->getMessage());
        $initialized = false;
        return false;
    }

    $initialized = recruitmentTablesExist($pdo);
    return $initialized;
}

function recruitmentGetWorkerColumns(PDO $pdo) {
    static $columnsCache = null;
    if ($columnsCache !== null) {
        return $columnsCache;
    }

    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM workers");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $columnsCache = array_flip($columns);
    } catch (Throwable $e) {
        $columnsCache = [];
    }

    return $columnsCache;
}

/**
 * Create or update a worker record from an application when hired/employed.
 */
function recruitmentCreateWorkerFromApplication(PDO $pdo, $applicationId, $currentUserId = null) {
    $stmt = $pdo->prepare("
        SELECT 
            app.*, 
            cand.first_name,
            cand.last_name,
            cand.other_names,
            cand.email,
            cand.phone_primary,
            cand.phone_secondary,
            cand.country,
            cand.city,
            cand.address,
            cand.expected_salary,
            cand.availability_date,
            vac.title AS vacancy_title,
            vac.department_id,
            vac.position_id
        FROM recruitment_applications app
        INNER JOIN recruitment_candidates cand ON cand.id = app.candidate_id
        INNER JOIN recruitment_vacancies vac ON vac.id = app.vacancy_id
        WHERE app.id = ?
        LIMIT 1
    ");
    $stmt->execute([$applicationId]);
    $application = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$application) {
        throw new RuntimeException('Application not found');
    }

    if (!empty($application['hired_worker_id'])) {
        return intval($application['hired_worker_id']);
    }

    $pdo->beginTransaction();

    try {
        $workerColumns = recruitmentGetWorkerColumns($pdo);

        // Try to find existing worker by email or phone
        $existingWorkerId = null;
        if (!empty($application['email'])) {
            try {
                $findStmt = $pdo->prepare("SELECT id FROM workers WHERE email = ? LIMIT 1");
                $findStmt->execute([$application['email']]);
                $existingWorkerId = $findStmt->fetchColumn();
            } catch (Throwable $ignored) {
                $existingWorkerId = null;
            }
        }

        if (!$existingWorkerId && !empty($application['phone_primary'])) {
            try {
                $findStmt = $pdo->prepare("SELECT id FROM workers WHERE contact_number = ? LIMIT 1");
                $findStmt->execute([$application['phone_primary']]);
                $existingWorkerId = $findStmt->fetchColumn();
            } catch (Throwable $ignored) {
                $existingWorkerId = null;
            }
        }

        $fullName = trim($application['first_name'] . ' ' . ($application['other_names'] ?: '') . ' ' . $application['last_name']);
        $fullName = preg_replace('/\s+/', ' ', $fullName);
        if (!$fullName) {
            $fullName = $application['first_name'] ?: $application['last_name'] ?: 'New Hire';
        }

        if ($existingWorkerId) {
            $updateStmt = $pdo->prepare("
                UPDATE workers
                SET 
                    worker_name = ?,
                    role = COALESCE(NULLIF(?, ''), role),
                    contact_number = COALESCE(NULLIF(?, ''), contact_number),
                    email = COALESCE(NULLIF(?, ''), email)
                WHERE id = ?
            ");
            $updateStmt->execute([
                $fullName,
                $application['vacancy_title'] ?? 'Staff',
                $application['phone_primary'] ?: $application['phone_secondary'],
                $application['email'],
                $existingWorkerId,
            ]);

            // Additional optional updates if columns exist
            $optionalSets = [];
            $optionalParams = [];

            if (isset($workerColumns['employee_type'])) {
                $optionalSets[] = "employee_type = COALESCE(NULLIF(?, ''), employee_type)";
                $optionalParams[] = 'staff';
            }
            if (isset($workerColumns['department_id'])) {
                $optionalSets[] = "department_id = COALESCE(?, department_id)";
                $optionalParams[] = $application['department_id'];
            }
            if (isset($workerColumns['position_id'])) {
                $optionalSets[] = "position_id = COALESCE(?, position_id)";
                $optionalParams[] = $application['position_id'];
            }
            if (isset($workerColumns['hire_date'])) {
                $optionalSets[] = "hire_date = COALESCE(?, hire_date)";
                $optionalParams[] = $application['availability_date'];
            }
            if (isset($workerColumns['status'])) {
                $optionalSets[] = "status = 'active'";
            }
            if (isset($workerColumns['updated_at'])) {
                $optionalSets[] = "updated_at = NOW()";
            }

            if (!empty($optionalSets)) {
                $optionalSql = "
                    UPDATE workers
                    SET " . implode(', ', $optionalSets) . "
                    WHERE id = ?
                ";
                $optionalParams[] = $existingWorkerId;
                $optStmt = $pdo->prepare($optionalSql);
                $optStmt->execute($optionalParams);
            }
            $workerId = intval($existingWorkerId);

            ensureWorkerHasStaffIdentifier($pdo, $workerId);
        } else {
            $insertColumns = [];
            $placeholders = [];
            $params = [];

            if (isset($workerColumns['employee_code'])) {
                $employeeCode = generateStaffIdentifier($pdo);
                $insertColumns[] = 'employee_code';
                $placeholders[] = '?';
                $params[] = $employeeCode;
            }

            $insertColumns[] = 'worker_name';
            $placeholders[] = '?';
            $params[] = $fullName;

            $insertColumns[] = 'role';
            $placeholders[] = '?';
            $params[] = $application['vacancy_title'] ?? 'Staff';

            if (isset($workerColumns['default_rate'])) {
                $insertColumns[] = 'default_rate';
                $placeholders[] = '?';
                $params[] = 0;
            }

            if (isset($workerColumns['contact_number'])) {
                $insertColumns[] = 'contact_number';
                $placeholders[] = '?';
                $params[] = $application['phone_primary'] ?: $application['phone_secondary'];
            }

            if (isset($workerColumns['email'])) {
                $insertColumns[] = 'email';
                $placeholders[] = '?';
                $params[] = $application['email'];
            }

            if (isset($workerColumns['employee_type'])) {
                $insertColumns[] = 'employee_type';
                $placeholders[] = '?';
                $params[] = 'staff';
            }

            if (isset($workerColumns['department_id'])) {
                $insertColumns[] = 'department_id';
                $placeholders[] = '?';
                $params[] = $application['department_id'];
            }

            if (isset($workerColumns['position_id'])) {
                $insertColumns[] = 'position_id';
                $placeholders[] = '?';
                $params[] = $application['position_id'];
            }

            if (isset($workerColumns['hire_date'])) {
                $insertColumns[] = 'hire_date';
                $placeholders[] = '?';
                $params[] = $application['availability_date'];
            }

            if (isset($workerColumns['status'])) {
                $insertColumns[] = 'status';
                $placeholders[] = '?';
                $params[] = 'active';
            }

            if (isset($workerColumns['created_at'])) {
                $insertColumns[] = 'created_at';
                $placeholders[] = 'NOW()';
            }

            if (isset($workerColumns['updated_at'])) {
                $insertColumns[] = 'updated_at';
                $placeholders[] = 'NOW()';
            }

            // Handle placeholders for NOW()
            $placeholdersSql = [];
            foreach ($placeholders as $placeholder) {
                $placeholdersSql[] = $placeholder === 'NOW()' ? 'NOW()' : '?';
            }

            $sql = "
                INSERT INTO workers (" . implode(', ', $insertColumns) . ")
                VALUES (" . implode(', ', $placeholdersSql) . ")
            ";
            $insertStmt = $pdo->prepare($sql);
            $insertStmt->execute($params);
            $workerId = intval($pdo->lastInsertId());

            ensureWorkerHasStaffIdentifier($pdo, $workerId);
        }

        // Link application to worker
        $updateApp = $pdo->prepare("
            UPDATE recruitment_applications
            SET hired_worker_id = ?, hired_at = COALESCE(hired_at, NOW())
            WHERE id = ?
        ");
        $updateApp->execute([$workerId, $applicationId]);

        // Record note for traceability
        try {
            $noteStmt = $pdo->prepare("
                INSERT INTO recruitment_application_notes (
                    application_id,
                    note_type,
                    note_text,
                    created_by,
                    created_at
                ) VALUES (?, 'decision', ?, ?, NOW())
            ");
            $noteStmt->execute([
                $applicationId,
                'Candidate converted to worker profile (ID ' . $workerId . ').',
                $currentUserId,
            ]);
        } catch (Throwable $ignored) {
            // Notes table may not exist; ignore.
        }

        $pdo->commit();
        return $workerId;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Build dashboard metrics for recruitment overview.
 */
function recruitmentGetDashboardStats(PDO $pdo) {
    $stats = [
        'total_vacancies' => 0,
        'open_vacancies' => 0,
        'applications_total' => 0,
        'applications_this_month' => 0,
        'hired_this_month' => 0,
        'applications_by_status' => [],
    ];

    try {
        $vacancyStmt = $pdo->query("
            SELECT 
                COUNT(*) AS total,
                SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) AS open_count
            FROM recruitment_vacancies
        ");
        $vacancyRow = $vacancyStmt->fetch(PDO::FETCH_ASSOC);
        if ($vacancyRow) {
            $stats['total_vacancies'] = intval($vacancyRow['total']);
            $stats['open_vacancies'] = intval($vacancyRow['open_count']);
        }
    } catch (Throwable $ignored) {}

    try {
        $appStmt = $pdo->query("
            SELECT 
                COUNT(*) AS total,
                SUM(CASE WHEN DATE_FORMAT(created_at, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m') THEN 1 ELSE 0 END) AS month_total,
                SUM(CASE WHEN current_status IN ('hired','onboarding','employed') AND DATE_FORMAT(updated_at, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m') THEN 1 ELSE 0 END) AS hired_month
            FROM recruitment_applications
        ");
        $appRow = $appStmt->fetch(PDO::FETCH_ASSOC);
        if ($appRow) {
            $stats['applications_total'] = intval($appRow['total']);
            $stats['applications_this_month'] = intval($appRow['month_total']);
            $stats['hired_this_month'] = intval($appRow['hired_month']);
        }
    } catch (Throwable $ignored) {}

    try {
        $statusStmt = $pdo->query("
            SELECT current_status, COUNT(*) AS total
            FROM recruitment_applications
            GROUP BY current_status
        ");
        $stats['applications_by_status'] = $statusStmt->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (Throwable $ignored) {
        $stats['applications_by_status'] = [];
    }

    return $stats;
}

/**
 * Purge legacy recruitment demo data (older sample vacancies/candidates).
 */
function recruitmentPurgeLegacyDemoData(PDO $pdo): array {
    $summary = ['vacancies' => 0, 'candidates' => 0];
    $legacyVacancyTitles = [
        'Senior Hydrologist',
        'Field Operations Coordinator',
        'People & Culture Partner',
    ];

    if (empty($legacyVacancyTitles)) {
        return $summary;
    }

    $placeholders = implode(',', array_fill(0, count($legacyVacancyTitles), '?'));
    $stmt = $pdo->prepare("SELECT id FROM recruitment_vacancies WHERE title IN ($placeholders)");
    $stmt->execute($legacyVacancyTitles);
    $legacyIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($legacyIds)) {
        $summary['vacancies'] = count($legacyIds);
        $idPlaceholders = implode(',', array_fill(0, count($legacyIds), '?'));

        // Delete notes
        $deleteNotes = $pdo->prepare("
            DELETE notes FROM recruitment_application_notes notes
            INNER JOIN recruitment_applications app ON notes.application_id = app.id
            WHERE app.vacancy_id IN ($idPlaceholders)
        ");
        $deleteNotes->execute($legacyIds);

        // Delete status history
        $deleteHistory = $pdo->prepare("
            DELETE hist FROM recruitment_application_status_history hist
            INNER JOIN recruitment_applications app ON hist.application_id = app.id
            WHERE app.vacancy_id IN ($idPlaceholders)
        ");
        $deleteHistory->execute($legacyIds);

        // Delete applications
        $deleteApps = $pdo->prepare("
            DELETE FROM recruitment_applications WHERE vacancy_id IN ($idPlaceholders)
        ");
        $deleteApps->execute($legacyIds);

        // Delete vacancies
        $deleteVacancies = $pdo->prepare("
            DELETE FROM recruitment_vacancies WHERE id IN ($idPlaceholders)
        ");
        $deleteVacancies->execute($legacyIds);
    }

    $legacyCandidateEmails = [
        'ama.mensah@example.com',
        'kwame.boateng@example.com',
        'nana.owusu@example.com',
    ];

    $emailPlaceholders = implode(',', array_fill(0, count($legacyCandidateEmails), '?'));
    $deleteCandidates = $pdo->prepare("
        DELETE FROM recruitment_candidates WHERE email IN ($emailPlaceholders)
    ");
    $deleteCandidates->execute($legacyCandidateEmails);
    $summary['candidates'] = $deleteCandidates->rowCount();

    return $summary;
}

function recruitmentGetDemoVacancySeeds(): array {
    return [
        [
            'title' => 'Lead Driller - Rig Operations',
            'location' => 'Tamale, Ghana (Rotational)',
            'employment_type' => 'full_time',
            'seniority_level' => 'senior',
            'salary_currency' => 'GHS',
            'salary_min' => 12000,
            'salary_max' => 15500,
            'salary_visible' => 1,
            'description' => 'Champion safe, high-performance drilling campaigns across multiple rigs while coaching crew members on ABBIS digital workflows.',
            'responsibilities' => "- Supervise daily drilling operations and safety toolbox talks\n- Track rig performance metrics inside ABBIS and escalate downtime trends\n- Mentor assistant drillers and coordinate training with the Operations Manager",
            'requirements' => "- Minimum 6 years rig drilling experience (borehole / geotechnical)\n- Demonstrated leadership in field environments\n- Comfortable with digital reporting tools and crew coaching",
            'benefits' => "Rig performance bonuses, housing rotation allowance, ABBIS Pro user certification, paid wellness weeks",
            'status' => 'published',
            'opening_date' => date('Y-m-d', strtotime('-10 days')),
            'closing_date' => date('Y-m-d', strtotime('+35 days')),
        ],
        [
            'title' => 'Road Crew Technician (Road Boy)',
            'location' => 'Nationwide Field Sites',
            'employment_type' => 'full_time',
            'seniority_level' => 'entry',
            'salary_currency' => 'GHS',
            'salary_min' => 3500,
            'salary_max' => 4800,
            'salary_visible' => 0,
            'description' => 'Support rig mobilisation, site prep, and logistics across remote projects while updating progress through ABBIS mobile checklists.',
            'responsibilities' => "- Assist with rig moves, pipe handling, and site clean-up\n- Capture site readiness photos and updates via ABBIS mobile tools\n- Coordinate consumables restock with the logistics team",
            'requirements' => "- At least 2 years field support experience (construction, drilling or mining)\n- Physically fit and able to travel extensively\n- Basic smartphone literacy; ABBIS training provided",
            'benefits' => "Daily field allowances, overnight travel cover, safety gear, quarterly skills stipend",
            'status' => 'published',
            'opening_date' => date('Y-m-d', strtotime('-5 days')),
            'closing_date' => date('Y-m-d', strtotime('+40 days')),
        ],
        [
            'title' => 'Finance & Systems Accountant (Manager)',
            'location' => 'Accra HQ / Hybrid',
            'employment_type' => 'full_time',
            'seniority_level' => 'senior',
            'salary_currency' => 'GHS',
            'salary_min' => 15000,
            'salary_max' => 20000,
            'salary_visible' => 1,
            'description' => 'Lead month-end close, oversee compliance, and own ABBIS finance module configuration, reporting and user training.',
            'responsibilities' => "- Manage GL, AP, AR and statutory filings\n- Design management reports directly inside ABBIS Analytics\n- Facilitate quarterly IT & ABBIS systems training for finance and operations teams",
            'requirements' => "- Chartered Accountant (ACCA, ICA or equivalent)\n- 6+ years experience, including 2 years in managerial capacity\n- Demonstrated ERP/finance system ownership and user training skills",
            'benefits' => "Executive medical cover, leadership coaching, dedicated ABBIS Systems Academy training budget, hybrid work gear stipend",
            'status' => 'published',
            'opening_date' => date('Y-m-d', strtotime('-12 days')),
            'closing_date' => date('Y-m-d', strtotime('+50 days')),
        ],
    ];
}

function recruitmentGetDemoCandidateSeeds(): array {
    return [
        [
            'first_name' => 'Yaw',
            'last_name' => 'Adjei',
            'email' => 'yaw.adjei@example.com',
            'phone_primary' => '+233541112233',
            'country' => 'Ghana',
            'city' => 'Tamale',
            'address' => 'Rig Camp Road, Tamale',
            'linkedin_url' => 'https://linkedin.com/in/yawadjei',
            'portfolio_url' => '',
            'years_experience' => 9,
            'highest_education' => 'HND Mechanical Engineering',
            'current_employer' => 'North Ridge Drilling Ltd',
            'current_position' => 'Senior Driller',
            'expected_salary' => 15000,
            'availability_date' => date('Y-m-d', strtotime('+20 days')),
            'source' => 'career_portal',
        ],
        [
            'first_name' => 'Joseph',
            'last_name' => 'Adu',
            'email' => 'joseph.adu@example.com',
            'phone_primary' => '+233559998877',
            'country' => 'Ghana',
            'city' => 'Sunyani',
            'address' => '19 Stage Road, Sunyani',
            'linkedin_url' => '',
            'portfolio_url' => '',
            'years_experience' => 3,
            'highest_education' => 'Vocational Training - Heavy Duty Operations',
            'current_employer' => 'Skyline Boreholes',
            'current_position' => 'Rig Assistant',
            'expected_salary' => 4500,
            'availability_date' => date('Y-m-d', strtotime('+10 days')),
            'source' => 'referral',
        ],
        [
            'first_name' => 'Akua',
            'last_name' => 'Daniels',
            'email' => 'akua.daniels@example.com',
            'phone_primary' => '+233509001122',
            'country' => 'Ghana',
            'city' => 'Accra',
            'address' => '34 Burma Camp Road, Accra',
            'linkedin_url' => 'https://linkedin.com/in/akuadaniels',
            'portfolio_url' => '',
            'years_experience' => 7,
            'highest_education' => 'MSc Accounting & Information Systems',
            'current_employer' => 'MetroBuild Group',
            'current_position' => 'Finance Manager',
            'expected_salary' => 18500,
            'availability_date' => date('Y-m-d', strtotime('+28 days')),
            'source' => 'linkedin',
        ],
    ];
}

function recruitmentGetDemoApplicationSeeds(array $vacancyIds, array $candidateIds): array {
    return [
        [
            'vacancy_title' => 'Lead Driller - Rig Operations',
            'candidate_email' => 'yaw.adjei@example.com',
            'current_status' => 'shortlisted',
            'source' => 'career_portal',
            'applicant_message' => 'Driven to lead ABBIS rigs and help digitise rig performance tracking.',
            'desired_salary' => 15000,
            'availability_date' => date('Y-m-d', strtotime('+20 days')),
            'years_experience' => 9,
            'rating' => 4.8,
            'priority' => 'high',
        ],
        [
            'vacancy_title' => 'Road Crew Technician (Road Boy)',
            'candidate_email' => 'joseph.adu@example.com',
            'current_status' => 'interview_scheduled',
            'source' => 'referral',
            'applicant_message' => 'Ready to join ABBIS road crew and learn the full mobile checklist flow.',
            'desired_salary' => 4500,
            'availability_date' => date('Y-m-d', strtotime('+10 days')),
            'years_experience' => 3,
            'rating' => 4.2,
            'priority' => 'medium',
        ],
        [
            'vacancy_title' => 'Finance & Systems Accountant (Manager)',
            'candidate_email' => 'akua.daniels@example.com',
            'current_status' => 'new',
            'source' => 'linkedin',
            'applicant_message' => 'Experienced finance manager with ERP rollouts; ready to manage ABBIS finance workflows.',
            'desired_salary' => 18500,
            'availability_date' => date('Y-m-d', strtotime('+28 days')),
            'years_experience' => 7,
            'rating' => 4.5,
            'priority' => 'high',
        ],
    ];
}

function recruitmentUpsertVacancy(PDO $pdo, array $data): array {
    $stmt = $pdo->prepare("SELECT id, vacancy_code FROM recruitment_vacancies WHERE title = ? LIMIT 1");
    $stmt->execute([$data['title']]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        return ['id' => (int)$existing['id'], 'created' => false];
    }

    $vacancyCode = recruitmentGenerateCode($pdo, 'recruitment_vacancies', 'vacancy_code', 'VAC', 5);
    $insert = $pdo->prepare("
        INSERT INTO recruitment_vacancies (
            vacancy_code, title, department_id, position_id, location,
            employment_type, seniority_level, salary_currency, salary_min, salary_max,
            salary_visible, description, responsibilities, requirements, benefits,
            status, opening_date, closing_date, published_at, created_by, updated_by, created_at
        ) VALUES (
            ?, ?, NULL, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
            CASE WHEN ? = 'published' THEN NOW() ELSE NULL END,
            NULL, NULL, NOW()
        )
    ");
    $insert->execute([
        $vacancyCode,
        $data['title'],
        $data['location'],
        $data['employment_type'],
        $data['seniority_level'],
        $data['salary_currency'],
        $data['salary_min'],
        $data['salary_max'],
        $data['salary_visible'],
        $data['description'],
        $data['responsibilities'],
        $data['requirements'],
        $data['benefits'],
        $data['status'],
        $data['opening_date'],
        $data['closing_date'],
        $data['status'],
    ]);

    return ['id' => (int)$pdo->lastInsertId(), 'created' => true];
}

function recruitmentUpsertCandidate(PDO $pdo, array $data): array {
    $stmt = $pdo->prepare("SELECT id FROM recruitment_candidates WHERE email = ? LIMIT 1");
    $stmt->execute([$data['email']]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        return ['id' => (int)$existing['id'], 'created' => false];
    }

    $candidateCode = recruitmentGenerateCode($pdo, 'recruitment_candidates', 'candidate_code', 'CAND', 6);
    $insert = $pdo->prepare("
        INSERT INTO recruitment_candidates (
            candidate_code, first_name, last_name, email, phone_primary, country, city, address,
            linkedin_url, portfolio_url, years_experience, highest_education, current_employer,
            current_position, expected_salary, availability_date, consent_to_contact, source, created_at
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, NOW()
        )
    ");
    $insert->execute([
        $candidateCode,
        $data['first_name'],
        $data['last_name'],
        $data['email'],
        $data['phone_primary'],
        $data['country'],
        $data['city'],
        $data['address'],
        $data['linkedin_url'],
        $data['portfolio_url'],
        $data['years_experience'],
        $data['highest_education'],
        $data['current_employer'],
        $data['current_position'],
        $data['expected_salary'],
        $data['availability_date'],
        $data['source'],
    ]);

    return ['id' => (int)$pdo->lastInsertId(), 'created' => true];
}

function recruitmentUpsertApplication(PDO $pdo, array $data): array {
    $stmt = $pdo->prepare("
        SELECT id FROM recruitment_applications
        WHERE vacancy_id = ? AND candidate_id = ?
        LIMIT 1
    ");
    $stmt->execute([$data['vacancy_id'], $data['candidate_id']]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        return ['id' => (int)$existing['id'], 'created' => false];
    }

    $applicationCode = recruitmentGenerateCode($pdo, 'recruitment_applications', 'application_code', 'APP', 6);
    $insert = $pdo->prepare("
        INSERT INTO recruitment_applications (
            application_code, vacancy_id, candidate_id, current_status, source, applicant_message,
            desired_salary, availability_date, years_experience, resume_path, cover_letter_path,
            supporting_documents, rating, priority, assigned_to_user_id, hiring_manager_id,
            last_status_change, created_at
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, NULL, NULL, ?, ?, NULL, NULL, NOW(), NOW()
        )
    ");
    $insert->execute([
        $applicationCode,
        $data['vacancy_id'],
        $data['candidate_id'],
        $data['current_status'],
        $data['source'],
        $data['applicant_message'],
        $data['desired_salary'],
        $data['availability_date'],
        $data['years_experience'],
        $data['rating'],
        $data['priority'],
    ]);

    return ['id' => (int)$pdo->lastInsertId(), 'created' => true];
}

function recruitmentEnsureStatusHistoryEntry(PDO $pdo, int $applicationId, string $statusKey): void {
    $stmt = $pdo->prepare("
        SELECT id FROM recruitment_application_status_history
        WHERE application_id = ? AND to_status = ? LIMIT 1
    ");
    $stmt->execute([$applicationId, $statusKey]);
    if ($stmt->fetchColumn()) {
        return;
    }

    $insert = $pdo->prepare("
        INSERT INTO recruitment_application_status_history
        (application_id, from_status, to_status, changed_by_user_id, changed_at, comment)
        VALUES (?, NULL, ?, NULL, NOW(), 'Seeded status for demo data.')
    ");
    $insert->execute([$applicationId, $statusKey]);
}

function recruitmentEnsureDemoData(PDO $pdo): array {
    $summary = [
        'purged_vacancies' => 0,
        'purged_candidates' => 0,
        'vacancies_created' => 0,
        'candidates_created' => 0,
        'applications_created' => 0,
        'seed_skipped' => false,
    ];

    try {
        $purgeSummary = recruitmentPurgeLegacyDemoData($pdo);
        $summary['purged_vacancies'] = $purgeSummary['vacancies'];
        $summary['purged_candidates'] = $purgeSummary['candidates'];
    } catch (Throwable $e) {
        error_log('Recruitment purge failed: ' . $e->getMessage());
    }

    try {
        $existingVacancies = (int) $pdo->query("SELECT COUNT(*) FROM recruitment_vacancies")->fetchColumn();
        if ($existingVacancies > 0) {
            $summary['seed_skipped'] = true;
            return $summary;
        }
    } catch (Throwable $e) {
        error_log('Recruitment seed skip check failed: ' . $e->getMessage());
    }

    $vacancyIds = [];
    $candidateIds = [];

    try {
        foreach (recruitmentGetDemoVacancySeeds() as $seed) {
            $result = recruitmentUpsertVacancy($pdo, $seed);
            $vacancyIds[$seed['title']] = $result['id'];
            if ($result['created']) {
                $summary['vacancies_created']++;
            }
        }
    } catch (Throwable $e) {
        error_log('Recruitment vacancy seed failed: ' . $e->getMessage());
    }

    try {
        foreach (recruitmentGetDemoCandidateSeeds() as $seed) {
            $result = recruitmentUpsertCandidate($pdo, $seed);
            $candidateIds[$seed['email']] = $result['id'];
            if ($result['created']) {
                $summary['candidates_created']++;
            }
        }
    } catch (Throwable $e) {
        error_log('Recruitment candidate seed failed: ' . $e->getMessage());
    }

    try {
        foreach (recruitmentGetDemoApplicationSeeds($vacancyIds, $candidateIds) as $seed) {
            if (empty($vacancyIds[$seed['vacancy_title']]) || empty($candidateIds[$seed['candidate_email']])) {
                continue;
            }
            $result = recruitmentUpsertApplication($pdo, [
                'vacancy_id' => $vacancyIds[$seed['vacancy_title']],
                'candidate_id' => $candidateIds[$seed['candidate_email']],
                'current_status' => $seed['current_status'],
                'source' => $seed['source'],
                'applicant_message' => $seed['applicant_message'],
                'desired_salary' => $seed['desired_salary'],
                'availability_date' => $seed['availability_date'],
                'years_experience' => $seed['years_experience'],
                'rating' => $seed['rating'],
                'priority' => $seed['priority'],
            ]);
            if ($result['created']) {
                $summary['applications_created']++;
            }
            recruitmentEnsureStatusHistoryEntry($pdo, $result['id'], $seed['current_status']);
        }
    } catch (Throwable $e) {
        error_log('Recruitment application seed failed: ' . $e->getMessage());
    }

    return $summary;
}

function recruitmentDeleteVacancy(PDO $pdo, int $vacancyId): bool {
    if ($vacancyId <= 0) {
        throw new InvalidArgumentException('Invalid vacancy id provided for deletion.');
    }

    $pdo->beginTransaction();

    try {
        $params = [$vacancyId];

        $pdo->prepare("
            DELETE docs FROM recruitment_application_documents docs
            INNER JOIN recruitment_applications app ON docs.application_id = app.id
            WHERE app.vacancy_id = ?
        ")->execute($params);

        $pdo->prepare("
            DELETE notes FROM recruitment_application_notes notes
            INNER JOIN recruitment_applications app ON notes.application_id = app.id
            WHERE app.vacancy_id = ?
        ")->execute($params);

        $pdo->prepare("
            DELETE hist FROM recruitment_application_status_history hist
            INNER JOIN recruitment_applications app ON hist.application_id = app.id
            WHERE app.vacancy_id = ?
        ")->execute($params);

        $pdo->prepare("
            DELETE interviews FROM recruitment_interviews interviews
            INNER JOIN recruitment_applications app ON interviews.application_id = app.id
            WHERE app.vacancy_id = ?
        ")->execute($params);

        $pdo->prepare("
            DELETE offers FROM recruitment_offers offers
            INNER JOIN recruitment_applications app ON offers.application_id = app.id
            WHERE app.vacancy_id = ?
        ")->execute($params);

        $pdo->prepare("DELETE FROM recruitment_applications WHERE vacancy_id = ?")->execute($params);

        $deleteVacancy = $pdo->prepare("DELETE FROM recruitment_vacancies WHERE id = ? LIMIT 1");
        $deleteVacancy->execute($params);

        $pdo->commit();

        return $deleteVacancy->rowCount() > 0;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

