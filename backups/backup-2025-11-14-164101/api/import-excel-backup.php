<?php
/**
 * Import Excel Backup API
 * Imports field reports from Excel backup files created in offline mode
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/AccountingAutoTracker.php';

header('Content-Type: application/json');

$auth->requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

// Check if file was uploaded
if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
    jsonResponse(['success' => false, 'message' => 'No file uploaded or upload error'], 400);
}

$file = $_FILES['excel_file'];
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

if (!in_array($ext, ['xlsx', 'xls'])) {
    jsonResponse(['success' => false, 'message' => 'Invalid file type. Please upload an Excel file (.xlsx or .xls)'], 400);
}

try {
    // Use PhpSpreadsheet for Excel reading (if available) or fallback to manual parsing
    $excelData = [];
    
    // Try to use PhpSpreadsheet if available
    if (class_exists('PhpOffice\PhpSpreadsheet\IOFactory')) {
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file['tmp_name']);
        $worksheet = $spreadsheet->getSheetByName('Field Reports');
        
        if (!$worksheet) {
            jsonResponse(['success' => false, 'message' => 'Invalid Excel file: "Field Reports" sheet not found'], 400);
        }
        
        $highestRow = $worksheet->getHighestRow();
        $headers = [];
        
        // Get headers from first row
        for ($col = 1; $col <= $worksheet->getHighestColumn(); $col++) {
            $cellValue = $worksheet->getCellByColumnAndRow($col, 1)->getValue();
            if ($cellValue) {
                $headers[$col] = $cellValue;
            }
        }
        
        // Read data rows
        for ($row = 2; $row <= $highestRow; $row++) {
            $rowData = [];
            foreach ($headers as $col => $header) {
                $rowData[$header] = $worksheet->getCellByColumnAndRow($col, $row)->getValue();
            }
            if (!empty($rowData['Report Date']) || !empty($rowData['Site Name'])) {
                $excelData[] = $rowData;
            }
        }
    } else {
        // Fallback: Return instructions to use client-side import
        jsonResponse([
            'success' => false,
            'message' => 'Server-side Excel parsing not available. Please use the "Import from Excel" button in the offline mode page.',
            'use_client_import' => true
        ], 400);
    }
    
    if (empty($excelData)) {
        jsonResponse(['success' => false, 'message' => 'No data found in Excel file'], 400);
    }
    
    $pdo = getDBConnection();
    $abbis = new ABBISFunctions();
    $imported = 0;
    $skipped = 0;
    $errors = [];
    
    foreach ($excelData as $row) {
        try {
            // Check if report already exists
            $reportDate = $row['Report Date'] ?? date('Y-m-d');
            $siteName = $row['Site Name'] ?? '';
            
            if (empty($siteName)) {
                $skipped++;
                continue;
            }
            
            $checkStmt = $pdo->prepare("SELECT id FROM field_reports WHERE report_date = ? AND site_name = ? LIMIT 1");
            $checkStmt->execute([$reportDate, $siteName]);
            if ($checkStmt->fetch()) {
                $skipped++;
                continue;
            }
            
            // Get or create rig
            $rigName = $row['Rig Name'] ?? '';
            $rigId = 1; // Default
            if (!empty($rigName)) {
                $rigStmt = $pdo->prepare("SELECT id FROM rigs WHERE rig_name = ? OR rig_code = ? LIMIT 1");
                $rigStmt->execute([$rigName, $rigName]);
                $rig = $rigStmt->fetch();
                if ($rig) {
                    $rigId = $rig['id'];
                }
            }
            
            // Get or create client
            $clientName = $row['Client Name'] ?? '';
            $clientId = null;
            if (!empty($clientName)) {
                $clientId = $abbis->extractAndSaveClient([
                    'client_name' => $clientName,
                    'contact_person' => $row['Client Contact Person'] ?? '',
                    'client_contact' => $row['Client Contact'] ?? '',
                    'email' => $row['Client Email'] ?? ''
                ]);
            }
            
            // Prepare report data
            $reportData = [
                'report_date' => $reportDate,
                'rig_id' => $rigId,
                'job_type' => $row['Job Type'] ?? 'direct',
                'site_name' => $siteName,
                'region' => $row['Region'] ?? '',
                'client_id' => $clientId,
                'supervisor' => $row['Supervisor'] ?? '',
                'total_workers' => intval($row['Total Workers'] ?? 0),
                'start_time' => $row['Start Time'] ?? '',
                'finish_time' => $row['Finish Time'] ?? '',
                'total_duration' => intval($row['Total Duration (min)'] ?? 0),
                'start_rpm' => $row['Start RPM'] ?? '',
                'finish_rpm' => $row['Finish RPM'] ?? '',
                'total_rpm' => $row['Total RPM'] ?? '',
                'rod_length' => $row['Rod Length'] ?? '',
                'rods_used' => intval($row['Rods Used'] ?? 0),
                'total_depth' => $row['Total Depth'] ?? '',
                'screen_pipes_used' => intval($row['Screen Pipes Used'] ?? 0),
                'plain_pipes_used' => intval($row['Plain Pipes Used'] ?? 0),
                'gravel_used' => intval($row['Gravel Used'] ?? 0),
                'construction_depth' => $row['Construction Depth'] ?? '',
                'balance_bf' => floatval($row['Balance B/F'] ?? 0),
                'contract_sum' => floatval($row['Contract Sum'] ?? 0),
                'rig_fee_charged' => floatval($row['Rig Fee Charged'] ?? 0),
                'rig_fee_collected' => floatval($row['Rig Fee Collected'] ?? 0),
                'cash_received' => floatval($row['Cash Received'] ?? 0),
                'materials_income' => floatval($row['Materials Income'] ?? 0),
                'materials_cost' => floatval($row['Materials Cost'] ?? 0),
                'momo_transfer' => floatval($row['MoMo Transfer'] ?? 0),
                'cash_given' => floatval($row['Cash Given'] ?? 0),
                'bank_deposit' => floatval($row['Bank Deposit'] ?? 0),
                'remarks' => $row['Remarks'] ?? '',
                'incident_log' => $row['Incident Log'] ?? '',
                'solution_log' => $row['Solution Log'] ?? '',
                'recommendation_log' => $row['Recommendation Log'] ?? ''
            ];
            
            // Calculate totals
            $totals = $abbis->calculateFinancialTotals($reportData);
            $reportData = array_merge($reportData, $totals);
            
            // Generate report ID
            $rigCode = null;
            if ($rigId > 0) {
                $rigStmt = $pdo->prepare("SELECT rig_code FROM rigs WHERE id = ?");
                $rigStmt->execute([$rigId]);
                $rig = $rigStmt->fetch();
                $rigCode = $rig ? $rig['rig_code'] : null;
            }
            $reportId = $abbis->generateReportId($rigCode);
            $reportData['report_id'] = $reportId;
            
            // Insert report (similar to save-report.php)
            $columns = [
                'report_id', 'report_date', 'rig_id', 'job_type', 'site_name', 'region', 'client_id',
                'supervisor', 'total_workers', 'start_time', 'finish_time', 'total_duration',
                'start_rpm', 'finish_rpm', 'total_rpm', 'rod_length', 'rods_used', 'total_depth',
                'screen_pipes_used', 'plain_pipes_used', 'gravel_used', 'construction_depth',
                'balance_bf', 'contract_sum', 'rig_fee_charged', 'rig_fee_collected', 'cash_received',
                'materials_income', 'materials_cost', 'momo_transfer', 'cash_given', 'bank_deposit',
                'total_income', 'total_expenses', 'total_wages', 'net_profit', 'total_money_banked',
                'days_balance', 'outstanding_rig_fee', 'remarks', 'incident_log', 'solution_log',
                'recommendation_log', 'created_by'
            ];
            
            $placeholders = implode(',', array_fill(0, count($columns), '?'));
            $columnsList = implode(', ', $columns);
            
            $values = [
                $reportId, $reportData['report_date'], $rigId, $reportData['job_type'], $reportData['site_name'],
                $reportData['region'], $clientId, $reportData['supervisor'], $reportData['total_workers'],
                $reportData['start_time'] ?: null, $reportData['finish_time'] ?: null, $reportData['total_duration'] ?: null,
                $reportData['start_rpm'] ?: null, $reportData['finish_rpm'] ?: null, $reportData['total_rpm'] ?: null,
                $reportData['rod_length'] ?: null, $reportData['rods_used'], $reportData['total_depth'] ?: null,
                $reportData['screen_pipes_used'], $reportData['plain_pipes_used'], $reportData['gravel_used'],
                $reportData['construction_depth'] ?: null, $reportData['balance_bf'], $reportData['contract_sum'],
                $reportData['rig_fee_charged'], $reportData['rig_fee_collected'], $reportData['cash_received'],
                $reportData['materials_income'], $reportData['materials_cost'], $reportData['momo_transfer'],
                $reportData['cash_given'], $reportData['bank_deposit'], $totals['total_income'],
                $totals['total_expenses'], $totals['total_wages'], $totals['net_profit'],
                $totals['total_money_banked'], $totals['days_balance'], $totals['outstanding_rig_fee'],
                $reportData['remarks'], $reportData['incident_log'], $reportData['solution_log'],
                $reportData['recommendation_log'], $_SESSION['user_id']
            ];
            
            $stmt = $pdo->prepare("INSERT INTO field_reports ($columnsList) VALUES ($placeholders)");
            $stmt->execute($values);
            $reportInsertId = $pdo->lastInsertId();
            
            // Automatically track in accounting
            try {
                $accountingTracker = new AccountingAutoTracker($pdo);
                $reportDataForAccounting = array_merge($reportData, [
                    'client_name' => $clientName,
                    'created_by' => $_SESSION['user_id']
                ]);
                $accountingTracker->trackFieldReport($reportInsertId, $reportDataForAccounting);
            } catch (Exception $e) {
                error_log("Accounting tracking error for imported report: " . $e->getMessage());
            }
            
            $imported++;
        } catch (Exception $e) {
            $errors[] = "Row " . ($imported + $skipped + count($errors) + 1) . ": " . $e->getMessage();
        }
    }
    
    jsonResponse([
        'success' => true,
        'message' => "Imported {$imported} report(s), skipped {$skipped} duplicate(s)",
        'imported' => $imported,
        'skipped' => $skipped,
        'errors' => $errors
    ]);
    
} catch (Exception $e) {
    jsonResponse([
        'success' => false,
        'message' => 'Import failed: ' . $e->getMessage()
    ], 500);
}


