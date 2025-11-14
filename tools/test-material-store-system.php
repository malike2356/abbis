<?php
/**
 * Material Store System Test Script
 * Tests all Material Store functionality
 */
$rootPath = __DIR__;
require_once $rootPath . '/config/app.php';
require_once $rootPath . '/config/database.php';
require_once $rootPath . '/includes/helpers.php';
require_once $rootPath . '/includes/pos/MaterialStoreService.php';
require_once $rootPath . '/includes/pos/UnifiedInventoryService.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Material Store System Test</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            max-width: 1200px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #1e3a5f;
            margin-bottom: 20px;
        }
        .test-section {
            margin-bottom: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 6px;
            border-left: 4px solid #0ea5e9;
        }
        .test-section h2 {
            margin-top: 0;
            color: #0ea5e9;
        }
        .test-result {
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
        }
        .test-result.success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .test-result.error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        .test-result.info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #f8f9fa;
            font-weight: 600;
        }
        .btn {
            background: #0ea5e9;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            margin: 5px;
        }
        .btn:hover {
            background: #0284c7;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧪 Material Store System Test</h1>
        
        <?php
        $pdo = getDBConnection();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $materialStoreService = new MaterialStoreService($pdo);
        
        $tests = [];
        $allPassed = true;
        
        // Test 1: Check if tables exist
        echo '<div class="test-section">';
        echo '<h2>1. Database Tables Check</h2>';
        
        $requiredTables = [
            'material_store_inventory',
            'material_store_transactions',
            'materials_inventory',
            'catalog_items',
            'pos_products'
        ];
        
        foreach ($requiredTables as $table) {
            try {
                $stmt = $pdo->query("SHOW TABLES LIKE '{$table}'");
                $exists = $stmt->rowCount() > 0;
                if ($exists) {
                    echo '<div class="test-result success">✅ Table <code>' . $table . '</code> exists</div>';
                    $tests[$table] = true;
                } else {
                    echo '<div class="test-result error">❌ Table <code>' . $table . '</code> does not exist</div>';
                    $tests[$table] = false;
                    $allPassed = false;
                }
            } catch (PDOException $e) {
                echo '<div class="test-result error">❌ Error checking table <code>' . $table . '</code>: ' . htmlspecialchars($e->getMessage()) . '</div>';
                $tests[$table] = false;
                $allPassed = false;
            }
        }
        echo '</div>';
        
        // Test 2: Check Material Store Inventory
        echo '<div class="test-section">';
        echo '<h2>2. Material Store Inventory</h2>';
        try {
            $inventory = $materialStoreService->getStoreInventory();
            if (empty($inventory)) {
                echo '<div class="test-result info">ℹ️ No materials in Material Store yet. This is normal if you haven\'t transferred any materials.</div>';
            } else {
                echo '<div class="test-result success">✅ Found ' . count($inventory) . ' material(s) in Material Store</div>';
                echo '<table>';
                echo '<thead><tr><th>Material</th><th>Received</th><th>Used</th><th>Remaining</th><th>Value</th></tr></thead>';
                echo '<tbody>';
                foreach ($inventory as $item) {
                    echo '<tr>';
                    echo '<td>' . htmlspecialchars($item['material_name']) . '</td>';
                    echo '<td>' . number_format($item['quantity_received'], 0) . '</td>';
                    echo '<td>' . number_format($item['quantity_used'], 0) . '</td>';
                    echo '<td>' . number_format($item['quantity_remaining'], 0) . '</td>';
                    echo '<td>' . formatCurrency($item['total_value']) . '</td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';
            }
            $tests['inventory'] = true;
        } catch (Exception $e) {
            echo '<div class="test-result error">❌ Error getting inventory: ' . htmlspecialchars($e->getMessage()) . '</div>';
            $tests['inventory'] = false;
            $allPassed = false;
        }
        echo '</div>';
        
        // Test 3: Check Low Stock Alerts
        echo '<div class="test-section">';
        echo '<h2>3. Low Stock Alerts</h2>';
        try {
            $alerts = $materialStoreService->getLowStockAlerts(20.0);
            if (empty($alerts)) {
                echo '<div class="test-result success">✅ No low stock alerts (all materials are well stocked)</div>';
            } else {
                echo '<div class="test-result error">⚠️ Found ' . count($alerts) . ' low stock alert(s)</div>';
                echo '<table>';
                echo '<thead><tr><th>Material</th><th>Remaining</th><th>Status</th></tr></thead>';
                echo '<tbody>';
                foreach ($alerts as $alert) {
                    echo '<tr>';
                    echo '<td>' . htmlspecialchars($alert['material_name']) . '</td>';
                    echo '<td>' . number_format($alert['quantity_remaining'], 0) . '</td>';
                    echo '<td><strong>' . strtoupper($alert['stock_status']) . '</strong></td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';
            }
            $tests['alerts'] = true;
        } catch (Exception $e) {
            echo '<div class="test-result error">❌ Error getting alerts: ' . htmlspecialchars($e->getMessage()) . '</div>';
            $tests['alerts'] = false;
            $allPassed = false;
        }
        echo '</div>';
        
        // Test 4: Check Transactions
        echo '<div class="test-section">';
        echo '<h2>4. Recent Transactions</h2>';
        try {
            $transactions = $materialStoreService->getTransactions(['date_from' => date('Y-m-d', strtotime('-7 days'))]);
            if (empty($transactions)) {
                echo '<div class="test-result info">ℹ️ No transactions in the last 7 days</div>';
            } else {
                echo '<div class="test-result success">✅ Found ' . count($transactions) . ' transaction(s) in the last 7 days</div>';
                echo '<table>';
                echo '<thead><tr><th>Date</th><th>Type</th><th>Material</th><th>Quantity</th></tr></thead>';
                echo '<tbody>';
                foreach (array_slice($transactions, 0, 10) as $txn) {
                    echo '<tr>';
                    echo '<td>' . date('M j, Y H:i', strtotime($txn['created_at'])) . '</td>';
                    echo '<td>' . htmlspecialchars($txn['transaction_type']) . '</td>';
                    echo '<td>' . htmlspecialchars($txn['material_type']) . '</td>';
                    echo '<td>' . number_format($txn['quantity'], 2) . '</td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';
            }
            $tests['transactions'] = true;
        } catch (Exception $e) {
            echo '<div class="test-result error">❌ Error getting transactions: ' . htmlspecialchars($e->getMessage()) . '</div>';
            $tests['transactions'] = false;
            $allPassed = false;
        }
        echo '</div>';
        
        // Test 5: Check Analytics
        echo '<div class="test-section">';
        echo '<h2>5. Usage Analytics</h2>';
        try {
            $analytics = $materialStoreService->getUsageAnalytics(['date_from' => date('Y-m-d', strtotime('-30 days'))]);
            echo '<div class="test-result success">✅ Analytics data retrieved successfully</div>';
            echo '<h3>Usage by Material:</h3>';
            if (empty($analytics['by_material'])) {
                echo '<div class="test-result info">ℹ️ No usage data for the last 30 days</div>';
            } else {
                echo '<table>';
                echo '<thead><tr><th>Material</th><th>Received</th><th>Used</th><th>Returned</th></tr></thead>';
                echo '<tbody>';
                foreach ($analytics['by_material'] as $stat) {
                    echo '<tr>';
                    echo '<td>' . htmlspecialchars($stat['material_type']) . '</td>';
                    echo '<td>' . number_format($stat['total_received'], 0) . '</td>';
                    echo '<td>' . number_format($stat['total_used'], 0) . '</td>';
                    echo '<td>' . number_format($stat['total_returned'], 0) . '</td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';
            }
            $tests['analytics'] = true;
        } catch (Exception $e) {
            echo '<div class="test-result error">❌ Error getting analytics: ' . htmlspecialchars($e->getMessage()) . '</div>';
            $tests['analytics'] = false;
            $allPassed = false;
        }
        echo '</div>';
        
        // Test 6: Check Materials Value Column
        echo '<div class="test-section">';
        echo '<h2>6. Field Reports - Materials Value Column</h2>';
        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM field_reports LIKE 'materials_value'");
            $exists = $stmt->rowCount() > 0;
            if ($exists) {
                echo '<div class="test-result success">✅ Column <code>materials_value</code> exists in <code>field_reports</code> table</div>';
                $tests['materials_value_column'] = true;
            } else {
                echo '<div class="test-result error">❌ Column <code>materials_value</code> does not exist. Run the migration: <a href="modules/admin/run-materials-value-migration.php">Run Migration</a></div>';
                $tests['materials_value_column'] = false;
                $allPassed = false;
            }
        } catch (PDOException $e) {
            echo '<div class="test-result error">❌ Error checking column: ' . htmlspecialchars($e->getMessage()) . '</div>';
            $tests['materials_value_column'] = false;
            $allPassed = false;
        }
        echo '</div>';
        
        // Summary
        echo '<div class="test-section">';
        echo '<h2>📊 Test Summary</h2>';
        $passedCount = count(array_filter($tests));
        $totalCount = count($tests);
        
        if ($allPassed) {
            echo '<div class="test-result success">';
            echo '<strong>✅ All tests passed! (' . $passedCount . '/' . $totalCount . ')</strong><br>';
            echo 'The Material Store system is ready to use.';
            echo '</div>';
        } else {
            echo '<div class="test-result error">';
            echo '<strong>⚠️ Some tests failed (' . $passedCount . '/' . $totalCount . ' passed)</strong><br>';
            echo 'Please fix the issues above before using the Material Store system.';
            echo '</div>';
        }
        echo '</div>';
        
        // Quick Actions
        echo '<div class="test-section">';
        echo '<h2>🚀 Quick Actions</h2>';
        echo '<a href="modules/admin/run-material-store-migration.php" class="btn">Run Material Store Migration</a>';
        echo '<a href="modules/admin/run-materials-value-migration.php" class="btn">Run Materials Value Migration</a>';
        echo '<a href="modules/material-store-dashboard.php" class="btn">Open Material Store Dashboard</a>';
        echo '<a href="modules/resources.php?action=materials" class="btn">Go to Resources</a>';
        echo '<a href="modules/field-reports.php" class="btn">Create Field Report</a>';
        echo '</div>';
        ?>
    </div>
</body>
</html>

