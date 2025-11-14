<?php
/**
 * Backward-compatible wrapper for excel exports
 * Redirects to unified export API
 */
$type = $_GET['type'] ?? 'reports';
$format = $_GET['format'] ?? 'csv';

$params = ['module' => $type, 'format' => $format];
$queryString = http_build_query($params) . (empty($_SERVER['QUERY_STRING']) ? '' : '&' . preg_replace('/type=[^&]*&?|format=[^&]*&?/', '', $_SERVER['QUERY_STRING']));
header('Location: export.php?' . $queryString, true, 301);
exit;
?>

