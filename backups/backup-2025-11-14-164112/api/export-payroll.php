<?php
/**
 * Backward-compatible wrapper for payroll exports
 * Redirects to unified export API
 */
header('Location: export.php?module=payroll&format=csv' . (empty($_SERVER['QUERY_STRING']) ? '' : '&' . $_SERVER['QUERY_STRING']), true, 301);
exit;
