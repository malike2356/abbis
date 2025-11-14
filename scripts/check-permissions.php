<?php
/**
 * Permission Check Script
 * Verifies role-based access controls are working correctly
 * 
 * Usage: php scripts/check-permissions.php [username]
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$username = $argv[1] ?? null;

echo "Permission Check Script\n";
echo "======================\n\n";

if ($username) {
    // Check specific user
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT id, username, full_name, role, is_active FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo "❌ User '{$username}' not found.\n";
        exit(1);
    }
    
    echo "Checking permissions for: {$user['full_name']} ({$user['username']})\n";
    echo "Role: {$user['role']}\n";
    echo "Active: " . ($user['is_active'] ? 'Yes' : 'No') . "\n\n";
    
    // Simulate session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
    
    $auth = new Auth();
    
    // Check POS permissions
    $permissions = [
        'pos.access' => 'Access POS system',
        'pos.sales.process' => 'Process sales',
        'pos.inventory.manage' => 'Manage inventory',
        'pos.admin' => 'POS admin access'
    ];
    
    echo "POS Permissions:\n";
    echo "----------------\n";
    foreach ($permissions as $permission => $description) {
        $hasPermission = $auth->userHasPermission($permission);
        $icon = $hasPermission ? '✅' : '❌';
        echo "  {$icon} {$permission}: {$description}\n";
    }
    
    // Check role-based access
    echo "\nRole-Based Access:\n";
    echo "------------------\n";
    $isAdminOrManager = in_array($user['role'], ['admin', 'manager'], true);
    echo "  " . ($isAdminOrManager ? '✅' : '❌') . " Admin/Manager: " . ($isAdminOrManager ? 'Yes' : 'No') . "\n";
    echo "    → Can see 'Check ABBIS Stock' button: " . ($isAdminOrManager ? 'Yes' : 'No') . "\n";
    
} else {
    // List all users and their permissions
    $pdo = getDBConnection();
    $users = $pdo->query("SELECT id, username, full_name, role, is_active FROM users ORDER BY role, username")->fetchAll(PDO::FETCH_ASSOC);
    
    echo "All Users and Permissions:\n";
    echo "==========================\n\n";
    
    foreach ($users as $user) {
        if (!$user['is_active']) {
            continue; // Skip inactive users
        }
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        
        $auth = new Auth();
        
        echo "{$user['full_name']} ({$user['username']}) - Role: {$user['role']}\n";
        echo str_repeat('-', 50) . "\n";
        
        $hasPosAccess = $auth->userHasPermission('pos.access');
        $hasSalesProcess = $auth->userHasPermission('pos.sales.process');
        $hasInventoryManage = $auth->userHasPermission('pos.inventory.manage');
        $isAdminOrManager = in_array($user['role'], ['admin', 'manager'], true);
        
        echo "  POS Access: " . ($hasPosAccess ? '✅' : '❌') . "\n";
        echo "  Process Sales: " . ($hasSalesProcess ? '✅' : '❌') . "\n";
        echo "  Manage Inventory: " . ($hasInventoryManage ? '✅' : '❌') . "\n";
        echo "  Admin Tools Access: " . ($isAdminOrManager ? '✅' : '❌') . "\n";
        echo "\n";
    }
}

echo "\nDone.\n";

