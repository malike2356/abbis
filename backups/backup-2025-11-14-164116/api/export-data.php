<?php
/**
 * Backward-compatible wrapper for data exports
 * Redirects to unified export API
 */
$type = $_GET['type'] ?? 'reports';
$format = $_GET['format'] ?? 'csv';

// Map old parameter names to new ones
$params = ['module' => $type, 'format' => $format];

// Handle old parameter names
if (isset($_GET['start_date'])) $params['date_from'] = $_GET['start_date'];
if (isset($_GET['end_date'])) $params['date_to'] = $_GET['end_date'];
if (isset($_GET['date_from'])) $params['date_from'] = $_GET['date_from'];
if (isset($_GET['date_to'])) $params['date_to'] = $_GET['date_to'];

// Add any other parameters
foreach ($_GET as $key => $value) {
    if (!in_array($key, ['type', 'format', 'start_date', 'end_date'])) {
        $params[$key] = $value;
    }
}

$queryString = http_build_query($params);
header('Location: export.php?' . $queryString, true, 301);
exit;
?>