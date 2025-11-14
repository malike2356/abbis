<?php
/**
 * POS Schema Verification Script
 * Verifies all required POS tables and columns exist
 * 
 * Usage: php scripts/verify-pos-schema.php
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';

$pdo = getDBConnection();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "POS Schema Verification\n";
echo "=======================\n\n";

$requiredTables = [
    'pos_sales' => [
        'sale_number', 'store_id', 'cashier_id', 'total_amount', 
        'amount_paid', 'sale_status', 'payment_status'
    ],
    'pos_sale_items' => [
        'sale_id', 'product_id', 'quantity', 'unit_price', 'line_total'
    ],
    'pos_sale_payments' => [
        'sale_id', 'payment_method', 'amount'
    ],
    'pos_products' => [
        'name', 'sku', 'unit_price', 'is_active'
    ],
    'pos_stores' => [
        'store_code', 'store_name', 'is_active'
    ],
    'pos_categories' => [
        'name', 'is_active'
    ],
    'pos_inventory' => [
        'store_id', 'product_id', 'quantity_on_hand'
    ],
    'pos_accounting_queue' => [
        'sale_id', 'status', 'payload'
    ]
];

$optionalTables = [
    'pos_cash_drawer_sessions' => [
        'store_id', 'cashier_id', 'status', 'opening_amount'
    ],
    'pos_refunds' => [
        'refund_number', 'original_sale_id', 'total_amount'
    ],
    'pos_categories' => [
        'name', 'description'
    ]
];

$errors = [];
$warnings = [];
$success = [];

// Check required tables
echo "Required Tables:\n";
echo "----------------\n";

foreach ($requiredTables as $table => $columns) {
    try {
        // Check if table exists
        $stmt = $pdo->query("SHOW TABLES LIKE '{$table}'");
        if ($stmt->rowCount() === 0) {
            $errors[] = "Table '{$table}' does not exist";
            echo "  ❌ {$table}: MISSING\n";
            continue;
        }
        
        // Check columns
        $stmt = $pdo->query("SHOW COLUMNS FROM {$table}");
        $existingColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $missingColumns = [];
        
        foreach ($columns as $column) {
            if (!in_array($column, $existingColumns)) {
                $missingColumns[] = $column;
            }
        }
        
        if (empty($missingColumns)) {
            $success[] = "Table '{$table}' is complete";
            echo "  ✅ {$table}: OK\n";
        } else {
            $warnings[] = "Table '{$table}' missing columns: " . implode(', ', $missingColumns);
            echo "  ⚠️  {$table}: Missing columns: " . implode(', ', $missingColumns) . "\n";
        }
    } catch (PDOException $e) {
        $errors[] = "Error checking table '{$table}': " . $e->getMessage();
        echo "  ❌ {$table}: ERROR - " . $e->getMessage() . "\n";
    }
}

// Check optional tables
echo "\nOptional Tables:\n";
echo "----------------\n";

foreach ($optionalTables as $table => $columns) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE '{$table}'");
        if ($stmt->rowCount() === 0) {
            echo "  ⚪ {$table}: Not present (optional)\n";
            continue;
        }
        
        $stmt = $pdo->query("SHOW COLUMNS FROM {$table}");
        $existingColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $missingColumns = [];
        
        foreach ($columns as $column) {
            if (!in_array($column, $existingColumns)) {
                $missingColumns[] = $column;
            }
        }
        
        if (empty($missingColumns)) {
            echo "  ✅ {$table}: OK\n";
        } else {
            echo "  ⚠️  {$table}: Missing columns: " . implode(', ', $missingColumns) . "\n";
        }
    } catch (PDOException $e) {
        echo "  ⚠️  {$table}: " . $e->getMessage() . "\n";
    }
}

// Summary
echo "\n";
echo "Summary:\n";
echo "--------\n";
echo "✅ Successful: " . count($success) . "\n";
echo "⚠️  Warnings: " . count($warnings) . "\n";
echo "❌ Errors: " . count($errors) . "\n";

if (!empty($warnings)) {
    echo "\nWarnings:\n";
    foreach ($warnings as $warning) {
        echo "  - {$warning}\n";
    }
}

if (!empty($errors)) {
    echo "\nErrors:\n";
    foreach ($errors as $error) {
        echo "  - {$error}\n";
    }
    echo "\n⚠️  Some required tables or columns are missing.\n";
    echo "   Run database migrations to fix these issues.\n";
    exit(1);
}

if (empty($errors) && empty($warnings)) {
    echo "\n✅ All required tables and columns are present!\n";
    exit(0);
}

exit(0);

