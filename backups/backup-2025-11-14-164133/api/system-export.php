<?php
/**
 * Backward-compatible wrapper for system exports
 * Redirects to unified export API
 */
$format = $_GET['format'] ?? 'json';
header('Location: export.php?module=system&format=' . urlencode($format), true, 301);
exit;

