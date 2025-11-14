<?php
/**
 * Record User Consent
 */
require_once '../config/app.php';
require_once '../config/security.php';
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';
require_once '../includes/consent-manager.php';

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
    }

$userId = $_SESSION['user_id'] ?? null;
$userEmail = $_SESSION['email'] ?? null;

if (!$userId && !$userEmail) {
    jsonResponse(['success' => false, 'message' => 'Not authenticated'], 401);
}

$data = json_decode(file_get_contents('php://input'), true);
$consentType = sanitizeInput($data['consent_type'] ?? '');
$consented = isset($data['consented']) ? (bool)$data['consented'] : true;
$version = sanitizeInput($data['version'] ?? null);

if (empty($consentType)) {
    jsonResponse(['success' => false, 'message' => 'Consent type required'], 400);
}

$consentManager = new ConsentManager();
$result = $consentManager->recordConsent($userId, $userEmail, $consentType, $version, $consented);

if ($result) {
    jsonResponse(['success' => true, 'message' => 'Consent recorded']);
    } else {
        jsonResponse(['success' => false, 'message' => 'Failed to record consent'], 500);
    }
} catch (Exception $e) {
    http_response_code(500);
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
    error_log("record-consent.php error: " . $e->getMessage());
}

