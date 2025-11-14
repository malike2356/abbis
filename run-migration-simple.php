<?php
/**
 * Simple migration runner - can be accessed via browser or CLI
 */
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['run_migration'] = true;

// Include the migration runner
require_once __DIR__ . '/pos/admin/run-receipt-migration.php';

