<?php
/**
 * Recruitment Application Submission API
 * Accepts candidate applications from public career portal.
 */

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Start a session for CSRF/flash handling if needed
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/recruitment-utils.php';

$pdo = getDBConnection();

if (!recruitmentEnsureInitialized($pdo)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Recruitment module not initialized']);
    exit;
}

// Basic anti-spam honeypot
$honeypot = trim($_POST['company'] ?? '');
if (!empty($honeypot)) {
    echo json_encode(['success' => true, 'message' => 'Submission received']);
    exit;
}

$vacancyId = intval($_POST['vacancy_id'] ?? 0);
$firstName = trim($_POST['first_name'] ?? '');
$lastName = trim($_POST['last_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$country = trim($_POST['country'] ?? '');
$city = trim($_POST['city'] ?? '');
$address = trim($_POST['address'] ?? '');
$linkedinUrl = trim($_POST['linkedin_url'] ?? '');
$portfolioUrl = trim($_POST['portfolio_url'] ?? '');
$yearsExperience = $_POST['years_experience'] !== '' ? floatval($_POST['years_experience']) : null;
$education = trim($_POST['highest_education'] ?? '');
$currentEmployer = trim($_POST['current_employer'] ?? '');
$currentPosition = trim($_POST['current_position'] ?? '');
$expectedSalary = $_POST['expected_salary'] !== '' ? floatval($_POST['expected_salary']) : null;
$availabilityDate = !empty($_POST['availability_date']) ? $_POST['availability_date'] : null;
$coverLetterText = trim($_POST['cover_letter'] ?? '');
$source = trim($_POST['source'] ?? 'career_portal');

if ($vacancyId <= 0 || !$firstName || !$lastName || !$email) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Required fields missing.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
    exit;
}

// Verify vacancy exists and is open
$vacancyStmt = $pdo->prepare("SELECT * FROM recruitment_vacancies WHERE id = ? AND status IN ('published','draft')");
$vacancyStmt->execute([$vacancyId]);
$vacancy = $vacancyStmt->fetch(PDO::FETCH_ASSOC);

if (!$vacancy) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Vacancy not found or no longer accepting applications.']);
    exit;
}

$uploadDir = UPLOAD_PATH . '/recruitment';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$resumePath = null;
$coverLetterPath = null;
$supportingDocs = [];

$allowedExtensions = ['pdf', 'doc', 'docx', 'rtf'];
$maxFileSize = 8 * 1024 * 1024; // 8 MB

function recruitmentStoreUpload($file, $prefix, $allowedExtensions, $maxFileSize, $targetDir) {
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('File upload failed with error code ' . $file['error']);
    }

    if ($file['size'] > $maxFileSize) {
        throw new RuntimeException('File exceeds maximum size of 8MB.');
    }

    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedExtensions, true)) {
        throw new RuntimeException('Unsupported file format. Please upload PDF or DOC files.');
    }

    $dateFolder = date('Y/m');
    $targetFolder = $targetDir . '/' . $dateFolder;
    if (!is_dir($targetFolder) && !mkdir($targetFolder, 0755, true)) {
        throw new RuntimeException('Failed to create storage directory.');
    }

    $safeName = preg_replace('/[^a-zA-Z0-9_\-]/', '-', strtolower($prefix));
    $uniqueName = $safeName . '-' . time() . '-' . bin2hex(random_bytes(4)) . '.' . $extension;
    $targetPath = $targetFolder . '/' . $uniqueName;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new RuntimeException('Unable to save the uploaded file.');
    }

    return str_replace(ROOT_PATH . '/', '', $targetPath);
}

try {
    if (!empty($_FILES['resume']['name'] ?? '')) {
        $resumePath = recruitmentStoreUpload($_FILES['resume'], $firstName . '-resume', $allowedExtensions, $maxFileSize, $uploadDir);
    }
    if (!empty($_FILES['cover_letter_file']['name'] ?? '')) {
        $coverLetterPath = recruitmentStoreUpload($_FILES['cover_letter_file'], $firstName . '-cover', $allowedExtensions, $maxFileSize, $uploadDir);
    }

    // Additional supporting documents (optional multiple)
    if (!empty($_FILES['supporting_documents']['name']) && is_array($_FILES['supporting_documents']['name'])) {
        $count = count($_FILES['supporting_documents']['name']);
        for ($i = 0; $i < $count; $i++) {
            if (empty($_FILES['supporting_documents']['name'][$i])) {
                continue;
            }
            $fileArray = [
                'name' => $_FILES['supporting_documents']['name'][$i],
                'type' => $_FILES['supporting_documents']['type'][$i],
                'tmp_name' => $_FILES['supporting_documents']['tmp_name'][$i],
                'error' => $_FILES['supporting_documents']['error'][$i],
                'size' => $_FILES['supporting_documents']['size'][$i],
            ];
            $storedPath = recruitmentStoreUpload($fileArray, $firstName . '-supporting', $allowedExtensions, $maxFileSize, $uploadDir);
            if ($storedPath) {
                $supportingDocs[] = $storedPath;
            }
        }
    }
} catch (Throwable $uploadError) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => $uploadError->getMessage()]);
    exit;
}

try {
    $pdo->beginTransaction();

    // Check if candidate exists
    $candidateStmt = $pdo->prepare("SELECT * FROM recruitment_candidates WHERE email = ? LIMIT 1 FOR UPDATE");
    $candidateStmt->execute([$email]);
    $candidate = $candidateStmt->fetch(PDO::FETCH_ASSOC);

    if ($candidate) {
        $candidateId = intval($candidate['id']);
        $updateCandidate = $pdo->prepare("
            UPDATE recruitment_candidates SET
                first_name = ?,
                last_name = ?,
                phone_primary = ?,
                phone_secondary = COALESCE(NULLIF(?, ''), phone_secondary),
                country = COALESCE(NULLIF(?, ''), country),
                city = COALESCE(NULLIF(?, ''), city),
                address = COALESCE(NULLIF(?, ''), address),
                linkedin_url = COALESCE(NULLIF(?, ''), linkedin_url),
                portfolio_url = COALESCE(NULLIF(?, ''), portfolio_url),
                years_experience = COALESCE(?, years_experience),
                highest_education = COALESCE(NULLIF(?, ''), highest_education),
                current_employer = COALESCE(NULLIF(?, ''), current_employer),
                current_position = COALESCE(NULLIF(?, ''), current_position),
                expected_salary = COALESCE(?, expected_salary),
                availability_date = COALESCE(?, availability_date),
                source = COALESCE(NULLIF(?, ''), source),
                updated_at = NOW()
            WHERE id = ?
        ");
        $updateCandidate->execute([
            $firstName,
            $lastName,
            $phone,
            $phone,
            $country,
            $city,
            $address,
            $linkedinUrl,
            $portfolioUrl,
            $yearsExperience,
            $education,
            $currentEmployer,
            $currentPosition,
            $expectedSalary,
            $availabilityDate,
            $source,
            $candidateId,
        ]);
    } else {
        $candidateCode = recruitmentGenerateCode($pdo, 'recruitment_candidates', 'candidate_code', 'CAND', 6);
        $insertCandidate = $pdo->prepare("
            INSERT INTO recruitment_candidates (
                candidate_code, first_name, last_name, email, phone_primary, country, city,
                address, linkedin_url, portfolio_url, years_experience, highest_education,
                current_employer, current_position, expected_salary, availability_date,
                consent_to_contact, source, created_at
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, NOW()
            )
        ");
        $insertCandidate->execute([
            $candidateCode,
            $firstName,
            $lastName,
            $email,
            $phone ?: null,
            $country ?: null,
            $city ?: null,
            $address ?: null,
            $linkedinUrl ?: null,
            $portfolioUrl ?: null,
            $yearsExperience,
            $education ?: null,
            $currentEmployer ?: null,
            $currentPosition ?: null,
            $expectedSalary,
            $availabilityDate,
            $source ?: 'career_portal',
        ]);
        $candidateId = intval($pdo->lastInsertId());
    }

    // Create application
    $applicationCode = recruitmentGenerateCode($pdo, 'recruitment_applications', 'application_code', 'APP', 6);
    $insertApp = $pdo->prepare("
        INSERT INTO recruitment_applications (
            application_code, vacancy_id, candidate_id, current_status, source, applicant_message,
            desired_salary, availability_date, years_experience, resume_path, cover_letter_path,
            supporting_documents, created_at
        ) VALUES (
            ?, ?, ?, 'new', ?, ?, ?, ?, ?, ?, ?, ?, NOW()
        )
    ");
    $insertApp->execute([
        $applicationCode,
        $vacancyId,
        $candidateId,
        $source ?: 'career_portal',
        $coverLetterText ?: null,
        $expectedSalary,
        $availabilityDate,
        $yearsExperience,
        $resumePath,
        $coverLetterPath,
        !empty($supportingDocs) ? json_encode($supportingDocs) : null,
    ]);
    $applicationId = intval($pdo->lastInsertId());

    // Log uploaded documents in table
    $docStmt = $pdo->prepare("
        INSERT INTO recruitment_application_documents
        (application_id, document_type, file_name, storage_path, mime_type, file_size_bytes, uploaded_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");

    if ($resumePath) {
        $docStmt->execute([
            $applicationId,
            'cv',
            basename($resumePath),
            $resumePath,
            $_FILES['resume']['type'] ?? null,
            $_FILES['resume']['size'] ?? null,
        ]);
    }
    if ($coverLetterPath) {
        $docStmt->execute([
            $applicationId,
            'cover_letter',
            basename($coverLetterPath),
            $coverLetterPath,
            $_FILES['cover_letter_file']['type'] ?? null,
            $_FILES['cover_letter_file']['size'] ?? null,
        ]);
    }
    foreach ($supportingDocs as $index => $docPath) {
        $docStmt->execute([
            $applicationId,
            'other',
            basename($docPath),
            $docPath,
            null,
            null,
        ]);
    }

    // Create initial status history
    $historyStmt = $pdo->prepare("
        INSERT INTO recruitment_application_status_history
        (application_id, from_status, to_status, changed_by_user_id, changed_at, comment)
        VALUES (?, NULL, 'new', NULL, NOW(), 'Application submitted via career portal')
    ");
    $historyStmt->execute([$applicationId]);

    // Optional: create notification task
    try {
        $notifyStmt = $pdo->prepare("
            INSERT INTO notifications (
                notification_type,
                title,
                message,
                link_url,
                recipient_role,
                created_at
            ) VALUES (
                'recruitment',
                ?,
                ?,
                ?,
                'ROLE_HR',
                NOW()
            )
        ");
        $notifTitle = 'New applicant for ' . $vacancy['title'];
        $notifMessage = $firstName . ' ' . $lastName . ' applied via the career portal.';
        $notifLink = 'modules/recruitment.php';
        $notifyStmt->execute([$notifTitle, $notifMessage, $notifLink]);
    } catch (Throwable $ignored) {
        // Notifications table might not exist; ignore silently.
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Application submitted successfully.',
        'application_code' => $applicationCode,
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Submission failed: ' . $e->getMessage()]);
}

