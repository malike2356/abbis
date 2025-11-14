<?php
/**
 * Dispatch rig requests based on planner recommendations.
 */

require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';

$auth->requireAuth();
$auth->requireRole([ROLE_ADMIN, ROLE_MANAGER, ROLE_SUPERVISOR]);

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
    }

    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        jsonResponse(['success' => false, 'message' => 'Invalid security token'], 403);
    }

    $rigId = (int)($_POST['rig_id'] ?? 0);
    $jobId = (int)($_POST['job_id'] ?? 0);
    $scheduledDate = trim((string)($_POST['scheduled_date'] ?? ''));
    $distanceKm = isset($_POST['distance_km']) ? (float)$_POST['distance_km'] : null;
    $sequence = isset($_POST['sequence']) ? (int)$_POST['sequence'] : 0;
    $notes = trim((string)($_POST['notes'] ?? ''));

    if ($rigId <= 0 || $jobId <= 0 || $scheduledDate === '') {
        jsonResponse(['success' => false, 'message' => 'Rig, job, and scheduled date are required.'], 422);
    }

    $scheduledDateObj = DateTime::createFromFormat('Y-m-d', $scheduledDate);
    if (!$scheduledDateObj) {
        jsonResponse(['success' => false, 'message' => 'Invalid scheduled date.'], 422);
    }

    $pdo = getDBConnection();

    $pdo->beginTransaction();

    // Ensure schedule table exists
    ensureSchedulePlanTable($pdo);

    // Validate rig exists
    $stmt = $pdo->prepare("SELECT rig_name FROM rigs WHERE id = ? LIMIT 1");
    $stmt->execute([$rigId]);
    $rig = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$rig) {
        $pdo->rollBack();
        jsonResponse(['success' => false, 'message' => 'Rig not found.'], 404);
    }

    // Validate rig request
    $stmt = $pdo->prepare("SELECT status FROM rig_requests WHERE id = ? LIMIT 1");
    $stmt->execute([$jobId]);
    $job = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$job) {
        $pdo->rollBack();
        jsonResponse(['success' => false, 'message' => 'Rig request not found.'], 404);
    }

    $userId = $_SESSION['user_id'] ?? null;

    // Insert or update schedule plan
    $planSql = "
        INSERT INTO rig_schedule_plan (rig_id, rig_request_id, scheduled_date, travel_distance_km, sequence, status, notes, created_by)
        VALUES (:rig_id, :job_id, :scheduled_date, :distance_km, :sequence, 'planned', :notes, :created_by)
        ON DUPLICATE KEY UPDATE
            rig_id = VALUES(rig_id),
            scheduled_date = VALUES(scheduled_date),
            travel_distance_km = VALUES(travel_distance_km),
            sequence = VALUES(sequence),
            status = 'planned',
            notes = VALUES(notes),
            updated_at = NOW()
    ";
    $stmt = $pdo->prepare($planSql);
    $stmt->execute([
        ':rig_id' => $rigId,
        ':job_id' => $jobId,
        ':scheduled_date' => $scheduledDateObj->format('Y-m-d'),
        ':distance_km' => $distanceKm,
        ':sequence' => max(1, $sequence),
        ':notes' => $notes !== '' ? $notes : null,
        ':created_by' => $userId,
    ]);

    // Update rig request status and assignment
    $dispatchedAt = null;
    if ($scheduledDateObj <= new DateTime('today')) {
        $dispatchedAt = $scheduledDateObj->format('Y-m-d') . ' 08:00:00';
    }

    $updateSql = "
        UPDATE rig_requests
        SET
            assigned_rig_id = :rig_id,
            status = CASE
                WHEN status IN ('completed','declined','cancelled') THEN status
                ELSE 'dispatched'
            END,
            dispatched_at = CASE
                WHEN :dispatched_at IS NOT NULL THEN :dispatched_at
                ELSE dispatched_at
            END
        WHERE id = :job_id
    ";
    $stmt = $pdo->prepare($updateSql);
    $stmt->execute([
        ':rig_id' => $rigId,
        ':dispatched_at' => $dispatchedAt,
        ':job_id' => $jobId,
    ]);

    $pdo->commit();

    jsonResponse([
        'success' => true,
        'message' => 'Dispatch saved successfully.',
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Dispatch planner error: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
}

/**
 * Ensure the rig_schedule_plan table exists.
 */
function ensureSchedulePlanTable(PDO $pdo): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $sql = "
        CREATE TABLE IF NOT EXISTS rig_schedule_plan (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            rig_id INT NOT NULL,
            rig_request_id INT NOT NULL UNIQUE,
            scheduled_date DATE NOT NULL,
            travel_distance_km DECIMAL(10,2) DEFAULT NULL,
            sequence INT NOT NULL DEFAULT 1,
            status ENUM('planned','dispatched','completed','cancelled') DEFAULT 'planned',
            notes TEXT DEFAULT NULL,
            created_by INT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (rig_id) REFERENCES rigs(id) ON DELETE CASCADE,
            FOREIGN KEY (rig_request_id) REFERENCES rig_requests(id) ON DELETE CASCADE,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";

    $pdo->exec($sql);
    $ensured = true;
}


