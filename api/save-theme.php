<?php
/**
 * Save theme preference to session
 */
require_once '../config/app.php';
require_once '../includes/auth.php';

$auth->requireAuth();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $theme = $data['theme'] ?? 'light';
    
    if (in_array($theme, ['light', 'dark'])) {
        $_SESSION['theme'] = $theme;
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid theme']);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}

