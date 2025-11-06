<?php
/**
 * Verification Script - Verify Worker Name Standardization
 */
require_once __DIR__ . '/../config/database.php';

$canonicalWorkers = [
    'Atta', 'Isaac', 'Tawiah', 'Godwin', 'Asare', 'Castro', 
    'Earnest', 'Owusua', 'Rasta', 'Chief', 'Kwesi'
];

$pdo = getDBConnection();

echo "=== Worker Name Standardization Verification ===\n\n";

// 1. Check active workers
echo "1. Active Workers:\n";
$stmt = $pdo->query("SELECT worker_name, role, status FROM workers WHERE status = 'active' ORDER BY worker_name");
$activeWorkers = $stmt->fetchAll(PDO::FETCH_ASSOC);
$activeNames = array_column($activeWorkers, 'worker_name');

foreach ($activeWorkers as $worker) {
    $isCanonical = in_array($worker['worker_name'], $canonicalWorkers);
    $status = $isCanonical ? '✓' : '✗';
    echo "  {$status} {$worker['worker_name']} ({$worker['role']})\n";
}

$invalidActive = array_diff($activeNames, $canonicalWorkers);
if (count($invalidActive) > 0) {
    echo "\n⚠️  WARNING: Found " . count($invalidActive) . " invalid active workers:\n";
    foreach ($invalidActive as $name) {
        echo "    - {$name}\n";
    }
} else {
    echo "\n✓ All active workers are canonical\n";
}

// 2. Check payroll entries
echo "\n2. Payroll Entries:\n";
$stmt = $pdo->query("SELECT DISTINCT worker_name FROM payroll_entries ORDER BY worker_name");
$payrollNames = $stmt->fetchAll(PDO::FETCH_COLUMN);

$invalidPayroll = array_diff($payrollNames, $canonicalWorkers);
if (count($invalidPayroll) > 0) {
    echo "  ✗ Found " . count($invalidPayroll) . " invalid worker names:\n";
    foreach ($invalidPayroll as $name) {
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM payroll_entries WHERE worker_name = ?");
        $countStmt->execute([$name]);
        $count = $countStmt->fetchColumn();
        echo "    - {$name} ({$count} entries)\n";
    }
} else {
    echo "  ✓ All payroll entries use canonical names\n";
}

// 3. Check loans
echo "\n3. Loans:\n";
$stmt = $pdo->query("SELECT DISTINCT worker_name FROM loans ORDER BY worker_name");
$loanNames = $stmt->fetchAll(PDO::FETCH_COLUMN);

$invalidLoans = array_diff($loanNames, $canonicalWorkers);
if (count($invalidLoans) > 0) {
    echo "  ✗ Found " . count($invalidLoans) . " invalid worker names:\n";
    foreach ($invalidLoans as $name) {
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM loans WHERE worker_name = ?");
        $countStmt->execute([$name]);
        $count = $countStmt->fetchColumn();
        echo "    - {$name} ({$count} loans)\n";
    }
} else {
    echo "  ✓ All loans use canonical names (or no loans exist)\n";
}

// 4. Check field reports supervisor field
echo "\n4. Field Reports Supervisor Field:\n";
$stmt = $pdo->query("SELECT DISTINCT supervisor FROM field_reports WHERE supervisor IS NOT NULL AND supervisor != ''");
$supervisors = $stmt->fetchAll(PDO::FETCH_COLUMN);
$foundInvalid = false;

foreach ($supervisors as $supervisor) {
    // Check if supervisor contains any non-canonical names
    $words = explode(' ', $supervisor);
    foreach ($words as $word) {
        $word = trim($word);
        if (!empty($word) && !in_array($word, $canonicalWorkers) && strlen($word) > 2) {
            // Check if it's similar to a canonical name
            $isSimilar = false;
            foreach ($canonicalWorkers as $canonical) {
                if (stripos($word, $canonical) !== false || stripos($canonical, $word) !== false) {
                    $isSimilar = true;
                    break;
                }
            }
            if (!$isSimilar) {
                if (!$foundInvalid) {
                    echo "  ✗ Found potential non-canonical names in supervisor field:\n";
                    $foundInvalid = true;
                }
                echo "    - '{$supervisor}' (contains: {$word})\n";
                break;
            }
        }
    }
}

if (!$foundInvalid) {
    echo "  ✓ No obvious non-canonical names in supervisor field\n";
}

// 5. Check worker_id references
echo "\n5. Worker ID References:\n";
$invalidWorkerIds = [];
$stmt = $pdo->query("SELECT id, worker_name FROM workers WHERE status = 'active'");
$allActiveWorkers = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($allActiveWorkers as $worker) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM payroll_entries WHERE worker_id = ? AND worker_name != ?");
    $stmt->execute([$worker['id'], $worker['worker_name']]);
    $mismatch = $stmt->fetchColumn();
    if ($mismatch > 0) {
        $invalidWorkerIds[] = $worker['worker_name'];
    }
}

if (count($invalidWorkerIds) > 0) {
    echo "  ✗ Found " . count($invalidWorkerIds) . " workers with mismatched worker_id/worker_name:\n";
    foreach ($invalidWorkerIds as $name) {
        echo "    - {$name}\n";
    }
} else {
    echo "  ✓ All worker_id references match worker_name\n";
}

// 6. Summary
echo "\n=== Summary ===\n";
echo "Canonical workers: " . count($canonicalWorkers) . "\n";
echo "Active workers: " . count($activeWorkers) . "\n";
echo "Payroll entries with unique names: " . count($payrollNames) . "\n";
echo "Loans with unique names: " . count($loanNames) . "\n";

$totalIssues = count($invalidActive) + count($invalidPayroll) + count($invalidLoans) + count($invalidWorkerIds) + ($foundInvalid ? 1 : 0);
if ($totalIssues == 0) {
    echo "\n✅ VERIFICATION PASSED: All worker names are standardized!\n";
} else {
    echo "\n⚠️  VERIFICATION FAILED: Found {$totalIssues} issue(s) that need attention.\n";
}

