<?php
/**
 * RPM Validation API
 * Validates RPM values before saving to prevent errors
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

$pdo = getDBConnection();

$startRpm = isset($_POST['start_rpm']) ? floatval($_POST['start_rpm']) : null;
$finishRpm = isset($_POST['finish_rpm']) ? floatval($_POST['finish_rpm']) : null;
$rigId = isset($_POST['rig_id']) ? intval($_POST['rig_id']) : null;

$warnings = [];
$errors = [];

// Get current rig RPM for context
$currentRpm = 0;
if ($rigId) {
    try {
        $stmt = $pdo->prepare("SELECT current_rpm FROM rigs WHERE id = ?");
        $stmt->execute([$rigId]);
        $rig = $stmt->fetch();
        if ($rig) {
            $currentRpm = floatval($rig['current_rpm'] ?? 0);
        }
    } catch (PDOException $e) {
        // Ignore
    }
}

// Validate start_rpm
if ($startRpm !== null && $startRpm > 0) {
    if ($startRpm > 1000) {
        $errors[] = "Start RPM ({$startRpm}) is unrealistic. Did you mean " . ($startRpm / 100) . "?";
    } elseif ($startRpm > 100) {
        $warnings[] = "Start RPM ({$startRpm}) seems high. Please verify this is correct.";
    }
    
    // Check if start_rpm is significantly different from current_rpm
    if ($currentRpm > 0 && abs($startRpm - $currentRpm) > 50 && $startRpm < $currentRpm) {
        $warnings[] = "Start RPM ({$startRpm}) is much lower than current rig RPM ({$currentRpm}). Is this correct?";
    }
}

// Validate finish_rpm
if ($finishRpm !== null && $finishRpm > 0) {
    if ($finishRpm > 1000) {
        $errors[] = "Finish RPM ({$finishRpm}) is unrealistic. Did you mean " . ($finishRpm / 100) . "?";
    } elseif ($finishRpm > 100) {
        $warnings[] = "Finish RPM ({$finishRpm}) seems high. Please verify this is correct.";
    }
    
    // Check if finish_rpm is less than start_rpm
    if ($startRpm !== null && $finishRpm < $startRpm) {
        $errors[] = "Finish RPM ({$finishRpm}) cannot be less than Start RPM ({$startRpm}).";
    }
    
    // Check if finish_rpm is significantly different from start_rpm
    if ($startRpm !== null && ($finishRpm - $startRpm) > 10) {
        $warnings[] = "Total RPM for this job (" . ($finishRpm - $startRpm) . ") seems high. Please verify.";
    }
}

// Calculate total RPM
$totalRpm = null;
if ($startRpm !== null && $finishRpm !== null) {
    $totalRpm = max(0, $finishRpm - $startRpm);
    
    if ($totalRpm > 10) {
        $warnings[] = "Total RPM ({$totalRpm}) for this job seems high. Typical range is 0.5-5.0.";
    }
}

$response = [
    'valid' => empty($errors),
    'errors' => $errors,
    'warnings' => $warnings,
    'calculated_total_rpm' => $totalRpm,
    'current_rig_rpm' => $currentRpm
];

if (!empty($errors)) {
    http_response_code(400);
}

echo json_encode($response);

