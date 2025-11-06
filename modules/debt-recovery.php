<?php
/**
 * Debt Recovery Management
 * Tracks unpaid contract amounts and recovery actions
 */
$page_title = 'Debt Recovery Management';

require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/helpers.php';

$auth->requireAuth();

$pdo = getDBConnection();
$action = $_GET['action'] ?? 'list';
$currentUserId = $_SESSION['user_id'];

// Create debt_recoveries table if it doesn't exist
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `debt_recoveries` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `debt_code` VARCHAR(50) NOT NULL UNIQUE,
        `field_report_id` INT(11) DEFAULT NULL,
        `client_id` INT(11) DEFAULT NULL,
        `debt_type` ENUM('contract_shortfall', 'rig_fee_unpaid', 'partial_payment', 'other') NOT NULL DEFAULT 'contract_shortfall',
        `agreed_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Original agreed/contract amount',
        `collected_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Amount actually collected',
        `debt_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Outstanding debt (agreed - collected)',
        `amount_recovered` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Amount recovered so far',
        `remaining_debt` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Current remaining debt',
        `due_date` DATE DEFAULT NULL COMMENT 'Original payment due date',
        `status` ENUM('outstanding', 'partially_paid', 'in_collection', 'recovered', 'written_off', 'bad_debt') NOT NULL DEFAULT 'outstanding',
        `priority` ENUM('low', 'medium', 'high', 'urgent', 'critical') NOT NULL DEFAULT 'medium',
        `age_days` INT(11) DEFAULT 0 COMMENT 'Days since debt was created',
        `last_followup_date` DATE DEFAULT NULL,
        `next_followup_date` DATE DEFAULT NULL,
        `followup_count` INT(11) DEFAULT 0,
        `payment_terms` VARCHAR(255) DEFAULT NULL,
        `contact_person` VARCHAR(100) DEFAULT NULL,
        `contact_phone` VARCHAR(50) DEFAULT NULL,
        `contact_email` VARCHAR(100) DEFAULT NULL,
        `notes` TEXT DEFAULT NULL,
        `recovery_notes` TEXT DEFAULT NULL,
        `written_off_reason` TEXT DEFAULT NULL,
        `written_off_date` DATE DEFAULT NULL,
        `created_by` INT(11) NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `debt_code` (`debt_code`),
        KEY `field_report_id` (`field_report_id`),
        KEY `client_id` (`client_id`),
        KEY `status` (`status`),
        KEY `due_date` (`due_date`),
        KEY `next_followup_date` (`next_followup_date`),
        FOREIGN KEY (`field_report_id`) REFERENCES `field_reports` (`id`) ON DELETE SET NULL,
        FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (PDOException $e) {
    error_log("Debt recoveries table creation error: " . $e->getMessage());
}

// Handle AJAX actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'error' => 'Invalid security token']);
        exit;
    }
    
    $actionType = $_POST['action'] ?? '';
    
    try {
        switch ($actionType) {
            case 'add_debt':
                $debtCode = 'DEBT-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
                $fieldReportId = !empty($_POST['field_report_id']) ? intval($_POST['field_report_id']) : null;
                $clientId = !empty($_POST['client_id']) ? intval($_POST['client_id']) : null;
                $debtType = sanitizeInput($_POST['debt_type'] ?? 'contract_shortfall');
                $agreedAmount = floatval($_POST['agreed_amount'] ?? 0);
                $collectedAmount = floatval($_POST['collected_amount'] ?? 0);
                $debtAmount = $agreedAmount - $collectedAmount;
                
                if ($debtAmount <= 0) {
                    throw new Exception('Debt amount must be greater than zero');
                }
                
                $dueDate = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
                $status = sanitizeInput($_POST['status'] ?? 'outstanding');
                $priority = sanitizeInput($_POST['priority'] ?? 'medium');
                $contactPerson = sanitizeInput($_POST['contact_person'] ?? '');
                $contactPhone = sanitizeInput($_POST['contact_phone'] ?? '');
                $contactEmail = sanitizeInput($_POST['contact_email'] ?? '');
                $notes = sanitizeInput($_POST['notes'] ?? '');
                
                $stmt = $pdo->prepare("INSERT INTO debt_recoveries (debt_code, field_report_id, client_id, debt_type, agreed_amount, collected_amount, debt_amount, remaining_debt, due_date, status, priority, contact_person, contact_phone, contact_email, notes, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
                $stmt->execute([$debtCode, $fieldReportId, $clientId, $debtType, $agreedAmount, $collectedAmount, $debtAmount, $debtAmount, $dueDate, $status, $priority, $contactPerson, $contactPhone, $contactEmail, $notes, $currentUserId]);
                
                echo json_encode(['success' => true, 'message' => 'Debt record created successfully', 'id' => $pdo->lastInsertId()]);
                break;
                
            case 'update_debt':
                $id = intval($_POST['id'] ?? 0);
                if (!$id) throw new Exception('Invalid debt ID');
                
                $status = sanitizeInput($_POST['status'] ?? '');
                $priority = sanitizeInput($_POST['priority'] ?? '');
                $amountRecovered = floatval($_POST['amount_recovered'] ?? 0);
                $lastFollowupDate = !empty($_POST['last_followup_date']) ? $_POST['last_followup_date'] : null;
                $nextFollowupDate = !empty($_POST['next_followup_date']) ? $_POST['next_followup_date'] : null;
                $recoveryNotes = sanitizeInput($_POST['recovery_notes'] ?? '');
                $writtenOffReason = sanitizeInput($_POST['written_off_reason'] ?? '');
                $writtenOffDate = !empty($_POST['written_off_date']) ? $_POST['written_off_date'] : null;
                
                // Get current debt
                $stmt = $pdo->prepare("SELECT debt_amount, amount_recovered FROM debt_recoveries WHERE id = ?");
                $stmt->execute([$id]);
                $currentDebt = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $newAmountRecovered = max($amountRecovered, floatval($currentDebt['amount_recovered']));
                $remainingDebt = floatval($currentDebt['debt_amount']) - $newAmountRecovered;
                
                // Update followup count if last_followup_date changed
                $followupCount = 0;
                if ($lastFollowupDate) {
                    $stmt = $pdo->prepare("SELECT followup_count FROM debt_recoveries WHERE id = ?");
                    $stmt->execute([$id]);
                    $current = $stmt->fetch(PDO::FETCH_ASSOC);
                    $followupCount = intval($current['followup_count'] ?? 0);
                    if ($lastFollowupDate != $current['last_followup_date'] ?? null) {
                        $followupCount++;
                    }
                }
                
                // Calculate age
                $stmt = $pdo->prepare("SELECT DATEDIFF(CURDATE(), created_at) as age FROM debt_recoveries WHERE id = ?");
                $stmt->execute([$id]);
                $ageDays = $stmt->fetchColumn() ?: 0;
                
                $stmt = $pdo->prepare("UPDATE debt_recoveries SET status=?, priority=?, amount_recovered=?, remaining_debt=?, last_followup_date=?, next_followup_date=?, followup_count=?, age_days=?, recovery_notes=?, written_off_reason=?, written_off_date=? WHERE id=?");
                $stmt->execute([$status, $priority, $newAmountRecovered, $remainingDebt, $lastFollowupDate, $nextFollowupDate, $followupCount, $ageDays, $recoveryNotes, $writtenOffReason, $writtenOffDate, $id]);
                
                echo json_encode(['success' => true, 'message' => 'Debt record updated successfully']);
                break;
                
            case 'delete_debt':
                $id = intval($_POST['id'] ?? 0);
                if (!$id) throw new Exception('Invalid debt ID');
                
                $stmt = $pdo->prepare("DELETE FROM debt_recoveries WHERE id = ?");
                $stmt->execute([$id]);
                
                echo json_encode(['success' => true, 'message' => 'Debt record deleted successfully']);
                break;
                
            default:
                throw new Exception('Invalid action');
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Get debts
$statusFilter = $_GET['status'] ?? '';
$priorityFilter = $_GET['priority'] ?? '';
$search = sanitizeInput($_GET['search'] ?? '');

$where = ['1=1'];
$params = [];

if ($statusFilter) {
    $where[] = "status = ?";
    $params[] = $statusFilter;
}

if ($priorityFilter) {
    $where[] = "priority = ?";
    $params[] = $priorityFilter;
}

if ($search) {
    $where[] = "(debt_code LIKE ? OR contact_person LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$whereClause = implode(' AND ', $where);

// Initialize defaults
$debts = [];
$stats = [
    'total' => 0,
    'outstanding' => 0,
    'total_amount' => 0,
    'recovered_amount' => 0,
    'overdue_count' => 0,
    'due_today' => 0
];

try {
    $stmt = $pdo->prepare("SELECT dr.*, 
        DATEDIFF(CURDATE(), dr.created_at) as age_days,
        fr.report_id, fr.site_name, fr.report_date,
        c.client_name,
        u.username as created_by_name
        FROM debt_recoveries dr
        LEFT JOIN field_reports fr ON dr.field_report_id = fr.id
        LEFT JOIN clients c ON dr.client_id = c.id
        LEFT JOIN users u ON dr.created_by = u.id
        WHERE $whereClause
        ORDER BY dr.created_at DESC, dr.priority DESC");
    $stmt->execute($params);
    $debts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Update age_days in database for all records (if table exists and has records)
    try {
        $pdo->exec("UPDATE debt_recoveries SET age_days = DATEDIFF(CURDATE(), created_at) WHERE age_days IS NULL OR age_days != DATEDIFF(CURDATE(), created_at)");
    } catch (PDOException $e) {
        // Ignore if table doesn't exist or update fails
        error_log("Age update error (non-critical): " . $e->getMessage());
    }
    
    // Calculate stats (stats already initialized above)
    try {
        $statsStmt = $pdo->query("SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status IN ('outstanding', 'partially_paid', 'in_collection') THEN 1 ELSE 0 END) as outstanding,
            SUM(CASE WHEN status IN ('outstanding', 'partially_paid', 'in_collection') THEN remaining_debt ELSE 0 END) as total_amount,
            SUM(amount_recovered) as recovered_amount,
            SUM(CASE WHEN status IN ('outstanding', 'partially_paid', 'in_collection') AND due_date < CURDATE() THEN 1 ELSE 0 END) as overdue_count,
            SUM(CASE WHEN status IN ('outstanding', 'partially_paid', 'in_collection') AND next_followup_date = CURDATE() THEN 1 ELSE 0 END) as due_today
            FROM debt_recoveries");
        $statsRow = $statsStmt->fetch(PDO::FETCH_ASSOC);
        if ($statsRow) {
            $stats = array_merge($stats, [
                'total' => intval($statsRow['total'] ?? 0),
                'outstanding' => intval($statsRow['outstanding'] ?? 0),
                'total_amount' => floatval($statsRow['total_amount'] ?? 0),
                'recovered_amount' => floatval($statsRow['recovered_amount'] ?? 0),
                'overdue_count' => intval($statsRow['overdue_count'] ?? 0),
                'due_today' => intval($statsRow['due_today'] ?? 0)
            ]);
        }
    } catch (PDOException $e) {
        // Stats will remain at defaults
        error_log("Stats calculation error: " . $e->getMessage());
    }
    
} catch (PDOException $e) {
    $debts = [];
    // Stats already initialized above, but reset to defaults on error
    $stats = [
        'total' => 0,
        'outstanding' => 0,
        'total_amount' => 0,
        'recovered_amount' => 0,
        'overdue_count' => 0,
        'due_today' => 0
    ];
    error_log("Debt recovery query error: " . $e->getMessage());
}

// Get clients and field reports for dropdowns
try {
    $clients = $pdo->query("SELECT id, client_name FROM clients ORDER BY client_name")->fetchAll(PDO::FETCH_ASSOC);
    $fieldReports = $pdo->query("SELECT id, report_id, site_name, report_date, client_id, contract_sum, total_income FROM field_reports ORDER BY report_date DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $clients = [];
    $fieldReports = [];
}

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display, but log them
ini_set('log_errors', 1);

require_once '../includes/header.php';
?>

<style>
    .debt-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 16px;
        margin-bottom: 24px;
    }
    .debt-stat-card {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 8px;
        padding: 16px;
        border-left: 4px solid var(--primary);
    }
    .debt-stat-card.urgent {
        border-left-color: var(--danger);
    }
    .debt-stat-card.warning {
        border-left-color: var(--warning);
    }
    .debt-stat-card.success {
        border-left-color: var(--success);
    }
    .debt-stat-value {
        font-size: 24px;
        font-weight: 700;
        color: var(--text);
        margin: 8px 0 4px 0;
    }
    .debt-stat-label {
        font-size: 12px;
        color: var(--secondary);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .debt-table {
        width: 100%;
        border-collapse: collapse;
    }
    .debt-table th {
        background: var(--bg);
        padding: 12px;
        text-align: left;
        font-weight: 600;
        font-size: 12px;
        text-transform: uppercase;
        color: var(--secondary);
        border-bottom: 2px solid var(--border);
    }
    .debt-table td {
        padding: 12px;
        border-bottom: 1px solid var(--border);
    }
    .debt-table tr:hover {
        background: var(--bg);
    }
    .status-badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
    }
    .status-outstanding { background: rgba(239,68,68,0.1); color: var(--danger); }
    .status-partially_paid { background: rgba(245,158,11,0.1); color: var(--warning); }
    .status-in_collection { background: rgba(14,165,233,0.1); color: var(--primary); }
    .status-recovered { background: rgba(16,185,129,0.1); color: var(--success); }
    .status-written_off { background: rgba(107,114,128,0.1); color: var(--secondary); }
    .status-bad_debt { background: rgba(239,68,68,0.2); color: var(--danger); }
    
    .priority-badge {
        display: inline-block;
        padding: 3px 8px;
        border-radius: 8px;
        font-size: 10px;
        font-weight: 600;
        text-transform: uppercase;
    }
    .priority-critical { background: rgba(239,68,68,0.2); color: var(--danger); }
    .priority-urgent { background: rgba(239,68,68,0.15); color: var(--danger); }
    .priority-high { background: rgba(245,158,11,0.15); color: var(--warning); }
    .priority-medium { background: rgba(14,165,233,0.1); color: var(--primary); }
    .priority-low { background: rgba(107,114,128,0.1); color: var(--secondary); }
    
    .age-indicator {
        font-size: 11px;
        padding: 2px 6px;
        border-radius: 4px;
    }
    .age-new { background: rgba(16,185,129,0.1); color: var(--success); }
    .age-old { background: rgba(245,158,11,0.1); color: var(--warning); }
    .age-very-old { background: rgba(239,68,68,0.1); color: var(--danger); }
    
    /* Modal Styles */
    .modal {
        display: none;
        position: fixed;
        z-index: 10000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0,0,0,0.5);
        align-items: center;
        justify-content: center;
    }
    
    .modal-content {
        background-color: var(--card) !important;
        margin: auto !important;
        padding: 0 !important;
        border-radius: 8px !important;
        width: 90% !important;
        max-width: 700px !important;
        box-shadow: 0 4px 20px rgba(0,0,0,0.3) !important;
        max-height: 90vh !important;
        overflow-y: auto !important;
        position: relative !important;
        z-index: 10001 !important;
        visibility: visible !important;
        opacity: 1 !important;
        display: block !important;
        min-height: 400px !important;
        height: auto !important;
    }
    
    #debtModal .modal-content {
        background-color: var(--card) !important;
    }
    
    .modal-header {
        padding: 20px;
        border-bottom: 1px solid var(--border);
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: var(--bg);
    }
    
    .modal-header h2 {
        margin: 0;
        color: var(--text);
    }
    
    .modal-close {
        background: none;
        border: none;
        font-size: 28px;
        cursor: pointer;
        color: var(--secondary);
        padding: 0;
        width: 32px;
        height: 32px;
        line-height: 1;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .modal-close:hover {
        color: var(--text);
    }
    
    .modal form {
        padding: 20px !important;
        display: block !important;
        visibility: visible !important;
        opacity: 1 !important;
        width: 100% !important;
        box-sizing: border-box !important;
        min-height: 400px !important;
        height: auto !important;
    }
    
    /* Form Grid for modal */
    #debtForm .form-grid {
        display: grid !important;
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 16px !important;
        width: 100% !important;
        visibility: visible !important;
        opacity: 1 !important;
        margin: 0 !important;
        padding: 0 !important;
    }
    
    #debtForm .form-grid .form-group {
        margin-bottom: 0 !important;
        display: flex !important;
        flex-direction: column !important;
        visibility: visible !important;
        opacity: 1 !important;
        width: 100% !important;
    }
    
    #debtForm .form-grid .form-group.full-width {
        grid-column: 1 / -1 !important;
    }
    
    #debtForm .form-label,
    #debtForm .form-control,
    #debtForm select,
    #debtForm input,
    #debtForm textarea {
        visibility: visible !important;
        opacity: 1 !important;
        display: block !important;
        width: 100% !important;
    }
    
    #debtForm .form-label {
        display: block !important;
        margin-bottom: 6px !important;
    }
    
    @media (max-width: 768px) {
        .form-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="container-fluid">
    <div class="page-header">
        <div>
            <h1>🔍 Debt Recovery Management</h1>
            <p>Track and recover unpaid contract amounts</p>
        </div>
        <button onclick="openAddDebtModal()" class="btn btn-primary" id="addDebtBtn">
            <i class="fas fa-plus"></i> Add Debt Record
        </button>
    </div>
    
    <script>
    // Ensure function is accessible immediately when button is clicked
    // This runs before the main script block but after modal HTML exists
    window.openAddDebtModal = function() {
        console.log('openAddDebtModal called');
        const modal = document.getElementById('debtModal');
        const form = document.getElementById('debtForm');
        const title = document.getElementById('debtModalTitle');
        
        if (!modal) {
            console.error('Debt modal not found');
            alert('Modal element not found. Please refresh the page.');
            return;
        }
        
        if (!form) {
            console.error('Debt form not found');
            alert('Form element not found. Please refresh the page.');
            return;
        }
        
        if (!title) {
            console.error('Debt modal title not found');
            return;
        }
        
        // Check if form has content
        console.log('Form HTML content length:', form.innerHTML.length);
        console.log('Form has children:', form.children.length);
        
        if (form.innerHTML.length < 100) {
            console.error('Form appears to be empty! HTML:', form.innerHTML.substring(0, 200));
            alert('Form content not found. Please refresh the page.');
            return;
        }
        
        // Reset form and set values
        title.textContent = 'Add Debt Record';
        const actionInput = document.getElementById('debtAction');
        const idInput = document.getElementById('debtId');
        const editFields = document.getElementById('editFields');
        const recoveryFields = document.getElementById('recoveryFields');
        const writtenOffFields = document.getElementById('writtenOffFields');
        
        if (actionInput) actionInput.value = 'add_debt';
        if (idInput) idInput.value = '';
        
        // Reset form AFTER ensuring it's visible
        // Use setTimeout to ensure DOM is ready
        setTimeout(() => {
            form.reset();
            
            // Reset action and id after reset (form.reset() clears them)
            if (actionInput) actionInput.value = 'add_debt';
            if (idInput) idInput.value = '';
        }, 10);
        
        // Hide conditional fields
        if (editFields) editFields.style.display = 'none';
        if (recoveryFields) recoveryFields.style.display = 'none';
        if (writtenOffFields) writtenOffFields.style.display = 'none';
        
        // Ensure modal content is visible
        const modalContent = modal.querySelector('.modal-content');
        if (modalContent) {
            modalContent.style.setProperty('visibility', 'visible', 'important');
            modalContent.style.setProperty('opacity', '1', 'important');
            modalContent.style.setProperty('display', 'block', 'important');
            modalContent.style.setProperty('min-height', '400px', 'important');
        }
        
        // Ensure form is visible with !important
        form.style.setProperty('display', 'block', 'important');
        form.style.setProperty('visibility', 'visible', 'important');
        form.style.setProperty('opacity', '1', 'important');
        form.style.setProperty('padding', '20px', 'important');
        form.style.setProperty('min-height', '400px', 'important');
        form.style.setProperty('height', 'auto', 'important');
        
        // Ensure form-grid is visible - try by ID first, then class
        let formGrid = document.getElementById('debtFormGrid');
        if (!formGrid) {
            formGrid = form.querySelector('.form-grid');
        }
        if (formGrid) {
            formGrid.style.setProperty('display', 'grid', 'important');
            formGrid.style.setProperty('visibility', 'visible', 'important');
            formGrid.style.setProperty('opacity', '1', 'important');
            console.log('Form grid found and made visible. Children:', formGrid.children.length);
        } else {
            console.error('Form grid not found! Form HTML:', form.innerHTML.substring(0, 500));
            // Try to create form-grid if it doesn't exist (shouldn't happen, but fallback)
            alert('Form structure error detected. Please refresh the page. If the issue persists, contact support.');
        }
        
        // Show all form groups
        const formGroups = form.querySelectorAll('.form-group');
        console.log('Form groups found:', formGroups.length);
        formGroups.forEach((group, index) => {
            group.style.setProperty('display', 'flex', 'important');
            group.style.setProperty('visibility', 'visible', 'important');
            group.style.setProperty('opacity', '1', 'important');
        });
        
        // Show modal - use flex for centering, with !important to override inline style
        console.log('Setting modal display to flex');
        modal.style.setProperty('display', 'flex', 'important');
        modal.style.setProperty('visibility', 'visible', 'important');
        modal.style.setProperty('opacity', '1', 'important');
        document.body.style.overflow = 'hidden';
        
        console.log('Modal should now be visible. Display style:', modal.style.display);
        console.log('Modal content:', modalContent);
        console.log('Form:', form);
        console.log('Form children:', form.children.length);
        console.log('Form innerHTML length:', form.innerHTML.length);
        console.log('Form computed display:', window.getComputedStyle(form).display);
    };
    
    // Also define closeDebtModal early
    window.closeDebtModal = function() {
        const modal = document.getElementById('debtModal');
        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = '';
        }
    };
    </script>
    
    <!-- Statistics -->
    <div class="debt-stats">
        <div class="debt-stat-card urgent">
            <div class="debt-stat-label">Outstanding Debts</div>
            <div class="debt-stat-value"><?php echo number_format($stats['outstanding'] ?? 0); ?></div>
            <div style="font-size: 12px; color: var(--secondary); margin-top: 4px;">GHS <?php echo number_format($stats['total_amount'] ?? 0, 2); ?></div>
        </div>
        <div class="debt-stat-card warning">
            <div class="debt-stat-label">Overdue</div>
            <div class="debt-stat-value"><?php echo number_format($stats['overdue_count'] ?? 0); ?></div>
            <div style="font-size: 12px; color: var(--secondary); margin-top: 4px;">Requires immediate attention</div>
        </div>
        <div class="debt-stat-card">
            <div class="debt-stat-label">Due Today (Follow-up)</div>
            <div class="debt-stat-value"><?php echo number_format($stats['due_today'] ?? 0); ?></div>
            <div style="font-size: 12px; color: var(--secondary); margin-top: 4px;">Scheduled follow-ups</div>
        </div>
        <div class="debt-stat-card success">
            <div class="debt-stat-label">Total Recovered</div>
            <div class="debt-stat-value">GHS <?php echo number_format($stats['recovered_amount'] ?? 0, 2); ?></div>
            <div style="font-size: 12px; color: var(--secondary); margin-top: 4px;">Successfully collected</div>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="dashboard-card" style="margin-bottom: 20px;">
        <div style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center;">
            <div style="flex: 1; min-width: 200px;">
                <input type="text" id="searchInput" placeholder="Search by debt code or contact..." value="<?php echo htmlspecialchars($search); ?>" class="form-control">
            </div>
            <select id="statusFilter" class="form-control" style="width: 150px;">
                <option value="">All Status</option>
                <option value="outstanding" <?php echo $statusFilter === 'outstanding' ? 'selected' : ''; ?>>Outstanding</option>
                <option value="partially_paid" <?php echo $statusFilter === 'partially_paid' ? 'selected' : ''; ?>>Partially Paid</option>
                <option value="in_collection" <?php echo $statusFilter === 'in_collection' ? 'selected' : ''; ?>>In Collection</option>
                <option value="recovered" <?php echo $statusFilter === 'recovered' ? 'selected' : ''; ?>>Recovered</option>
                <option value="written_off" <?php echo $statusFilter === 'written_off' ? 'selected' : ''; ?>>Written Off</option>
                <option value="bad_debt" <?php echo $statusFilter === 'bad_debt' ? 'selected' : ''; ?>>Bad Debt</option>
            </select>
            <select id="priorityFilter" class="form-control" style="width: 150px;">
                <option value="">All Priority</option>
                <option value="critical" <?php echo $priorityFilter === 'critical' ? 'selected' : ''; ?>>Critical</option>
                <option value="urgent" <?php echo $priorityFilter === 'urgent' ? 'selected' : ''; ?>>Urgent</option>
                <option value="high" <?php echo $priorityFilter === 'high' ? 'selected' : ''; ?>>High</option>
                <option value="medium" <?php echo $priorityFilter === 'medium' ? 'selected' : ''; ?>>Medium</option>
                <option value="low" <?php echo $priorityFilter === 'low' ? 'selected' : ''; ?>>Low</option>
            </select>
            <button onclick="applyFilters()" class="btn btn-primary">Filter</button>
            <button onclick="clearFilters()" class="btn btn-outline">Clear</button>
        </div>
    </div>
    
    <!-- Debts Table -->
    <div class="dashboard-card">
        <h2 style="margin: 0 0 16px 0;">Debt Records</h2>
        <?php if (empty($debts)): ?>
            <div style="text-align: center; padding: 40px; color: var(--secondary);">
                <p style="font-size: 16px; margin-bottom: 16px;">No debt records found.</p>
                <button onclick="openAddDebtModal()" class="btn btn-primary">Add First Debt Record</button>
            </div>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table class="debt-table">
                    <thead>
                        <tr>
                            <th>Debt Code</th>
                            <th>Client</th>
                            <th>Report/Site</th>
                            <th>Agreed Amount</th>
                            <th>Collected</th>
                            <th>Remaining</th>
                            <th>Age</th>
                            <th>Status</th>
                            <th>Priority</th>
                            <th>Next Follow-up</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($debts as $debt): 
                            $ageDays = intval($debt['age_days'] ?? 0);
                            $ageClass = $ageDays < 30 ? 'age-new' : ($ageDays < 90 ? 'age-old' : 'age-very-old');
                            $isOverdue = $debt['due_date'] && strtotime($debt['due_date']) < time() && in_array($debt['status'], ['outstanding', 'partially_paid', 'in_collection']);
                        ?>
                            <tr style="<?php echo $isOverdue ? 'background: rgba(239,68,68,0.05);' : ''; ?>">
                                <td><code style="font-size: 11px;"><?php echo htmlspecialchars($debt['debt_code']); ?></code></td>
                                <td><?php echo htmlspecialchars($debt['client_name'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php if ($debt['report_id']): ?>
                                        <div style="font-size: 12px;"><?php echo htmlspecialchars($debt['report_id']); ?></div>
                                        <div style="font-size: 11px; color: var(--secondary);"><?php echo htmlspecialchars($debt['site_name'] ?? ''); ?></div>
                                    <?php else: ?>
                                        <span style="color: var(--secondary);">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>GHS <?php echo number_format($debt['agreed_amount'], 2); ?></td>
                                <td>GHS <?php echo number_format($debt['collected_amount'], 2); ?></td>
                                <td style="font-weight: 600; color: <?php echo $debt['remaining_debt'] > 0 ? 'var(--danger)' : 'var(--success)'; ?>;">
                                    GHS <?php echo number_format($debt['remaining_debt'], 2); ?>
                                </td>
                                <td>
                                    <span class="age-indicator <?php echo $ageClass; ?>">
                                        <?php echo $ageDays; ?> days
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $debt['status']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $debt['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="priority-badge priority-<?php echo $debt['priority']; ?>">
                                        <?php echo ucfirst($debt['priority']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($debt['next_followup_date']): 
                                        $followupDate = strtotime($debt['next_followup_date']);
                                        $isDueToday = date('Y-m-d', $followupDate) === date('Y-m-d');
                                        $isOverdue = $followupDate < time();
                                    ?>
                                        <span style="color: <?php echo $isOverdue ? 'var(--danger)' : ($isDueToday ? 'var(--warning)' : 'var(--text)'); ?>;">
                                            <?php echo date('M j, Y', $followupDate); ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: var(--secondary);">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button onclick="openEditDebtModal(<?php echo htmlspecialchars(json_encode($debt, JSON_HEX_APOS | JSON_HEX_QUOT)); ?>)" class="btn btn-sm btn-outline" title="Edit">✏️</button>
                                    <button onclick="deleteDebt(<?php echo $debt['id']; ?>, '<?php echo htmlspecialchars(addslashes($debt['debt_code'])); ?>')" class="btn btn-sm btn-danger" title="Delete">🗑️</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add/Edit Debt Modal -->
<div id="debtModal" class="modal">
    <div class="modal-content" style="max-width: 700px;">
        <div class="modal-header">
            <h2 id="debtModalTitle">Add Debt Record</h2>
            <button class="modal-close" onclick="closeDebtModal()">&times;</button>
        </div>
        <form id="debtForm">
            <input type="hidden" id="debtId" name="id">
            <input type="hidden" name="action" id="debtAction" value="add_debt">
            <input type="hidden" name="csrf_token" value="<?php echo CSRF::getToken(); ?>">
            
            <div class="form-grid" id="debtFormGrid">
                <div class="form-group">
                    <label for="debt_field_report_id" class="form-label">Related Field Report (Optional)</label>
                    <select id="debt_field_report_id" name="field_report_id" class="form-control">
                        <option value="">— Select Report —</option>
                        <?php 
                        if (isset($fieldReports) && is_array($fieldReports) && count($fieldReports) > 0) {
                            foreach ($fieldReports as $report) {
                                $reportId = intval($report['id'] ?? 0);
                                $contractSum = floatval($report['contract_sum'] ?? 0);
                                $totalIncome = floatval($report['total_income'] ?? 0);
                                $debtAmount = $contractSum - $totalIncome;
                                $clientId = intval($report['client_id'] ?? 0);
                                $reportIdStr = htmlspecialchars($report['report_id'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
                                $siteName = htmlspecialchars($report['site_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
                                ?>
                                <option value="<?php echo $reportId; ?>" 
                                        data-contract="<?php echo $contractSum; ?>" 
                                        data-collected="<?php echo $totalIncome; ?>" 
                                        data-client="<?php echo $clientId; ?>">
                                    <?php echo $reportIdStr; ?> - <?php echo $siteName; ?>
                                    <?php if ($debtAmount > 0): ?>
                                        (Debt: GHS <?php echo number_format($debtAmount, 2); ?>)
                                    <?php endif; ?>
                                </option>
                                <?php
                            }
                        }
                        ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="debt_client_id" class="form-label">Client *</label>
                    <select id="debt_client_id" name="client_id" class="form-control" required>
                        <option value="">— Select Client —</option>
                        <?php 
                        if (isset($clients) && is_array($clients) && count($clients) > 0) {
                            foreach ($clients as $client) {
                                $clientId = intval($client['id'] ?? 0);
                                $clientName = htmlspecialchars($client['client_name'] ?? 'Unknown', ENT_QUOTES, 'UTF-8');
                                ?>
                                <option value="<?php echo $clientId; ?>"><?php echo $clientName; ?></option>
                                <?php
                            }
                        }
                        ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="debt_type" class="form-label">Debt Type *</label>
                    <select id="debt_type" name="debt_type" class="form-control" required>
                        <option value="contract_shortfall">Contract Shortfall</option>
                        <option value="rig_fee_unpaid">Rig Fee Unpaid</option>
                        <option value="partial_payment">Partial Payment</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="debt_due_date" class="form-label">Due Date</label>
                    <input type="date" id="debt_due_date" name="due_date" class="form-control">
                </div>
                
                <div class="form-group">
                    <label for="debt_agreed_amount" class="form-label">Agreed Amount (GHS) *</label>
                    <input type="number" id="debt_agreed_amount" name="agreed_amount" class="form-control" step="0.01" min="0" required>
                </div>
                
                <div class="form-group">
                    <label for="debt_collected_amount" class="form-label">Collected Amount (GHS) *</label>
                    <input type="number" id="debt_collected_amount" name="collected_amount" class="form-control" step="0.01" min="0" required>
                </div>
                
                <div class="form-group">
                    <label for="debt_status" class="form-label">Status *</label>
                    <select id="debt_status" name="status" class="form-control" required>
                        <option value="outstanding">Outstanding</option>
                        <option value="partially_paid">Partially Paid</option>
                        <option value="in_collection">In Collection</option>
                        <option value="recovered">Recovered</option>
                        <option value="written_off">Written Off</option>
                        <option value="bad_debt">Bad Debt</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="debt_priority" class="form-label">Priority *</label>
                    <select id="debt_priority" name="priority" class="form-control" required>
                        <option value="low">Low</option>
                        <option value="medium" selected>Medium</option>
                        <option value="high">High</option>
                        <option value="urgent">Urgent</option>
                        <option value="critical">Critical</option>
                    </select>
                </div>
                
                <div class="form-group full-width">
                    <label for="debt_contact_person" class="form-label">Contact Person</label>
                    <input type="text" id="debt_contact_person" name="contact_person" class="form-control">
                </div>
                
                <div class="form-group">
                    <label for="debt_contact_phone" class="form-label">Contact Phone</label>
                    <input type="text" id="debt_contact_phone" name="contact_phone" class="form-control">
                </div>
                
                <div class="form-group">
                    <label for="debt_contact_email" class="form-label">Contact Email</label>
                    <input type="email" id="debt_contact_email" name="contact_email" class="form-control">
                </div>
                
                <div class="form-group full-width" id="editFields" style="display: none;">
                    <label for="debt_amount_recovered" class="form-label">Amount Recovered (GHS)</label>
                    <input type="number" id="debt_amount_recovered" name="amount_recovered" class="form-control" step="0.01" min="0" value="0">
                </div>
                
                <div class="form-group">
                    <label for="debt_last_followup_date" class="form-label">Last Follow-up Date</label>
                    <input type="date" id="debt_last_followup_date" name="last_followup_date" class="form-control">
                </div>
                
                <div class="form-group">
                    <label for="debt_next_followup_date" class="form-label">Next Follow-up Date</label>
                    <input type="date" id="debt_next_followup_date" name="next_followup_date" class="form-control">
                </div>
                
                <div class="form-group full-width">
                    <label for="debt_notes" class="form-label">Notes</label>
                    <textarea id="debt_notes" name="notes" class="form-control" rows="3"></textarea>
                </div>
                
                <div class="form-group full-width" id="recoveryFields" style="display: none;">
                    <label for="debt_recovery_notes" class="form-label">Recovery Notes</label>
                    <textarea id="debt_recovery_notes" name="recovery_notes" class="form-control" rows="3"></textarea>
                </div>
                
                <div class="form-group full-width" id="writtenOffFields" style="display: none;">
                    <label for="debt_written_off_reason" class="form-label">Written Off Reason *</label>
                    <textarea id="debt_written_off_reason" name="written_off_reason" class="form-control" rows="3" required></textarea>
                    <label for="debt_written_off_date" class="form-label" style="margin-top: 12px;">Written Off Date</label>
                    <input type="date" id="debt_written_off_date" name="written_off_date" class="form-control">
                </div>
            </div>
            
            <div style="margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" class="btn btn-outline" onclick="closeDebtModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Debt Record</button>
            </div>
        </form>
    </div>
</div>

<script>
const csrfToken = '<?php echo CSRF::getToken(); ?>';

// openAddDebtModal is already defined earlier in the page, just ensure it's still accessible
if (!window.openAddDebtModal) {
    // Fallback definition if early script didn't run
    window.openAddDebtModal = function() {
        const modal = document.getElementById('debtModal');
        if (modal) {
            modal.style.setProperty('display', 'flex', 'important');
            document.body.style.overflow = 'hidden';
        }
    };
}

function openEditDebtModal(debt) {
    const modal = document.getElementById('debtModal');
    const form = document.getElementById('debtForm');
    const title = document.getElementById('debtModalTitle');
    
    if (!modal || !form || !title) return;
    
    title.textContent = 'Edit Debt Record';
    document.getElementById('debtAction').value = 'update_debt';
    document.getElementById('debtId').value = debt.id;
    document.getElementById('debt_field_report_id').value = debt.field_report_id || '';
    document.getElementById('debt_client_id').value = debt.client_id || '';
    document.getElementById('debt_type').value = debt.debt_type || 'contract_shortfall';
    document.getElementById('debt_agreed_amount').value = debt.agreed_amount || 0;
    document.getElementById('debt_collected_amount').value = debt.collected_amount || 0;
    document.getElementById('debt_due_date').value = debt.due_date || '';
    document.getElementById('debt_status').value = debt.status || 'outstanding';
    document.getElementById('debt_priority').value = debt.priority || 'medium';
    document.getElementById('debt_contact_person').value = debt.contact_person || '';
    document.getElementById('debt_contact_phone').value = debt.contact_phone || '';
    document.getElementById('debt_contact_email').value = debt.contact_email || '';
    document.getElementById('debt_amount_recovered').value = debt.amount_recovered || 0;
    document.getElementById('debt_last_followup_date').value = debt.last_followup_date || '';
    document.getElementById('debt_next_followup_date').value = debt.next_followup_date || '';
    document.getElementById('debt_notes').value = debt.notes || '';
    document.getElementById('debt_recovery_notes').value = debt.recovery_notes || '';
    document.getElementById('debt_written_off_reason').value = debt.written_off_reason || '';
    document.getElementById('debt_written_off_date').value = debt.written_off_date || '';
    
    // Show/hide fields based on status
    const editFields = document.getElementById('editFields');
    const recoveryFields = document.getElementById('recoveryFields');
    const writtenOffFields = document.getElementById('writtenOffFields');
    
    if (editFields) editFields.style.display = 'block';
    if (recoveryFields) recoveryFields.style.display = (debt.status === 'recovered' || debt.status === 'partially_paid' || debt.status === 'in_collection') ? 'block' : 'none';
    if (writtenOffFields) writtenOffFields.style.display = (debt.status === 'written_off' || debt.status === 'bad_debt') ? 'block' : 'none';
    
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

window.openEditDebtModal = openEditDebtModal;

function closeDebtModal() {
    const modal = document.getElementById('debtModal');
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }
}

window.closeDebtModal = closeDebtModal;

function deleteDebt(id, code) {
    if (!confirm(`Delete debt record "${code}"? This action cannot be undone.`)) return;
    
    const formData = new FormData();
    formData.append('action', 'delete_debt');
    formData.append('id', id);
    formData.append('csrf_token', csrfToken);
    
    fetch('debt-recovery.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showNotification(data.error || 'Failed to delete', 'error');
        }
    })
    .catch(error => {
        showNotification('Network error', 'error');
    });
}

window.deleteDebt = deleteDebt;

// Calculate debt amount
function calculateDebtAmount() {
    const agreed = parseFloat(document.getElementById('debt_agreed_amount')?.value || 0) || 0;
    const collected = parseFloat(document.getElementById('debt_collected_amount')?.value || 0) || 0;
    const debt = agreed - collected;
    // You could show this in a preview area
}

// Filters
function applyFilters() {
    const search = document.getElementById('searchInput')?.value || '';
    const status = document.getElementById('statusFilter')?.value || '';
    const priority = document.getElementById('priorityFilter')?.value || '';
    
    const params = new URLSearchParams();
    if (search) params.set('search', search);
    if (status) params.set('status', status);
    if (priority) params.set('priority', priority);
    
    window.location.href = '?' + params.toString();
}

window.applyFilters = applyFilters;

function clearFilters() {
    window.location.href = 'debt-recovery.php';
}

window.clearFilters = clearFilters;

// Initialize all DOM-dependent code when page loads
document.addEventListener('DOMContentLoaded', function() {
    // Form submission
    const debtForm = document.getElementById('debtForm');
    if (debtForm) {
        debtForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const action = formData.get('action');
            
            fetch('debt-recovery.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    if (typeof showNotification === 'function') {
                        showNotification(data.message, 'success');
                    } else {
                        alert(data.message);
                    }
                    closeDebtModal();
                    setTimeout(() => location.reload(), 1000);
                } else {
                    if (typeof showNotification === 'function') {
                        showNotification(data.error || 'Operation failed', 'error');
                    } else {
                        alert(data.error || 'Operation failed');
                    }
                }
            })
            .catch(error => {
                console.error('Form submission error:', error);
                if (typeof showNotification === 'function') {
                    showNotification('Network error', 'error');
                } else {
                    alert('Network error: ' + error.message);
                }
            });
        });
    }

    // Auto-fill from field report selection
    const fieldReportSelect = document.getElementById('debt_field_report_id');
    if (fieldReportSelect) {
        fieldReportSelect.addEventListener('change', function() {
            const option = this.options[this.selectedIndex];
            if (option.value && option.dataset.contract) {
                const agreedInput = document.getElementById('debt_agreed_amount');
                const collectedInput = document.getElementById('debt_collected_amount');
                const clientSelect = document.getElementById('debt_client_id');
                
                if (agreedInput) agreedInput.value = option.dataset.contract;
                if (collectedInput) collectedInput.value = option.dataset.collected;
                if (clientSelect && option.dataset.client) {
                    clientSelect.value = option.dataset.client;
                }
                // Trigger calculation
                calculateDebtAmount();
            }
        });
    }

    // Calculate debt amount on input
    const agreedInput = document.getElementById('debt_agreed_amount');
    const collectedInput = document.getElementById('debt_collected_amount');
    if (agreedInput) agreedInput.addEventListener('input', calculateDebtAmount);
    if (collectedInput) collectedInput.addEventListener('input', calculateDebtAmount);

    // Status change handler
    const statusSelect = document.getElementById('debt_status');
    if (statusSelect) {
        statusSelect.addEventListener('change', function() {
            const status = this.value;
            const recoveryFields = document.getElementById('recoveryFields');
            const writtenOffFields = document.getElementById('writtenOffFields');
            
            if (recoveryFields) {
                recoveryFields.style.display = (status === 'recovered' || status === 'partially_paid' || status === 'in_collection') ? 'block' : 'none';
            }
            if (writtenOffFields) {
                writtenOffFields.style.display = (status === 'written_off' || status === 'bad_debt') ? 'block' : 'none';
                const reasonField = document.getElementById('debt_written_off_reason');
                if (reasonField) {
                    reasonField.required = (status === 'written_off' || status === 'bad_debt');
                }
            }
        });
    }

    // Close modal on outside click
    const modal = document.getElementById('debtModal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeDebtModal();
            }
        });
    }
    
    // ESC key to close
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeDebtModal();
        }
    });
});

function showNotification(message, type) {
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.textContent = message;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 20px;
        border-radius: 8px;
        color: white;
        z-index: 10000;
        box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        animation: slideIn 0.3s forwards;
        background: ${type === 'success' ? 'var(--success)' : 'var(--danger)'};
    `;
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.opacity = '0';
        notification.style.transition = 'opacity 0.3s';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}
</script>

<?php require_once '../includes/footer.php'; ?>

