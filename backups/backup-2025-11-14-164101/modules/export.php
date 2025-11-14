<?php
/**
 * Export Redirect Wrapper
 * Redirects to the unified export API
 */
require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';

$auth->requireAuth();

// Get all query parameters
$params = $_GET;
$params['module'] = $params['module'] ?? $params['type'] ?? 'reports';
$params['format'] = $params['format'] ?? 'csv';

// Build redirect URL
$queryString = http_build_query($params);
header('Location: ../api/export.php?' . $queryString, true, 302);
exit;

