<?php
/**
 * Bulk Payslip Generation & Email API
 * Generate payslips for multiple workers and send via email
 */
// Set execution time limit
set_time_limit(300); // 5 minutes max execution time

// Enable error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Start output buffering to catch any errors
ob_start();

try {
    require_once __DIR__ . '/../config/app.php';
    require_once __DIR__ . '/../config/security.php';
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../includes/auth.php';
    require_once __DIR__ . '/../includes/helpers.php';
    require_once __DIR__ . '/../includes/email.php';
    require_once __DIR__ . '/../includes/payslip-generator.php';
    
    $auth->requireAuth();
    $auth->requireRole(ROLE_ADMIN);
    
    header('Content-Type: application/json');
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
    }
    
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        jsonResponse(['success' => false, 'message' => 'Invalid security token'], 403);
    }
} catch (Exception $e) {
    ob_end_clean();
    error_log("Bulk payslip API initialization error: " . $e->getMessage());
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error during initialization: ' . $e->getMessage()
    ]);
    exit;
}

$pdo = getDBConnection();
$emailer = new Email();

// Get parameters
$workerNames = $_POST['workers'] ?? [];
$dateFrom = $_POST['date_from'] ?? '';
$dateTo = $_POST['date_to'] ?? '';
$downloadLocally = isset($_POST['download_locally']) && $_POST['download_locally'] === '1';
$sendEmails = isset($_POST['send_emails']) && $_POST['send_emails'] === '1';

if (empty($workerNames) || !is_array($workerNames) || empty($dateFrom) || empty($dateTo)) {
    jsonResponse(['success' => false, 'message' => 'Missing required parameters'], 400);
}

if (!$downloadLocally && !$sendEmails) {
    jsonResponse(['success' => false, 'message' => 'Please select at least one option: Download locally or Send emails'], 400);
}

// Get company information
$configStmt = $pdo->query("SELECT config_key, config_value FROM system_config");
$config = [];
while ($row = $configStmt->fetch()) {
    $config[$row['config_key']] = $row['config_value'];
}

$companyName = $config['company_name'] ?? 'Company';
$companyEmail = $config['company_email'] ?? '';

$results = [];
$successCount = 0;
$failCount = 0;
$savedFiles = []; // Store all saved payslip files for zip creation

// Get base URL
$baseUrl = app_base_path();

// Payslip save directory
$payslipDir = __DIR__ . '/../uploads/payslips/';
if (!is_dir($payslipDir)) {
    @mkdir($payslipDir, 0755, true);
}

foreach ($workerNames as $workerName) {
    $workerName = trim($workerName);
    if (empty($workerName)) continue;
    
    try {
        // Get worker info
        $workerStmt = $pdo->prepare("SELECT * FROM workers WHERE worker_name = ? LIMIT 1");
        $workerStmt->execute([$workerName]);
        $worker = $workerStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$worker) {
            $results[] = [
                'worker' => $workerName,
                'success' => false,
                'message' => 'Worker not found in database'
            ];
            $failCount++;
            continue;
        }
        
        // Get payroll entries - check if any exist first (without date filter for debugging)
        $checkStmt = $pdo->prepare("SELECT COUNT(*) as count FROM payroll_entries WHERE worker_name = ?");
        $checkStmt->execute([$workerName]);
        $totalCount = $checkStmt->fetch()['count'];
        
        // Get payroll entries for the period
        $entriesStmt = $pdo->prepare("
            SELECT 
                pe.*,
                fr.report_id as field_report_id,
                fr.report_date,
                fr.site_name,
                r.rig_code
            FROM payroll_entries pe
            LEFT JOIN field_reports fr ON pe.report_id = fr.id
            LEFT JOIN rigs r ON fr.rig_id = r.id
            WHERE pe.worker_name = ?
              AND DATE(fr.report_date) >= ?
              AND DATE(fr.report_date) <= ?
            ORDER BY fr.report_date ASC
        ");
        $entriesStmt->execute([$workerName, $dateFrom, $dateTo]);
        $entries = $entriesStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Continue even if empty - we'll generate a payslip with zero amounts
        if (empty($entries)) {
            error_log("Bulk payslip: No entries for {$workerName} in period {$dateFrom} to {$dateTo}, generating zero-amount payslip");
        }
        
        // Generate payslip URL - use URL helper
        require_once __DIR__ . '/../includes/url-manager.php';
        $payslipUrl = module_url('payslip.php', [
            'worker' => $workerName,
            'date_from' => $dateFrom,
            'date_to' => $dateTo
        ]);
        
        // Save payslip HTML file (always if email is enabled, or if download_locally is enabled)
        $savedFile = null;
        $payslipFilepath = null;
        $payslipFilename = null;
        $saveError = null;
        $isTemporaryFile = false; // Track if file is only for email attachment
        
        if ($downloadLocally || $sendEmails) {
            try {
                $savedFile = PayslipGenerator::saveToFile($pdo, $workerName, $dateFrom, $dateTo, $baseUrl, $payslipDir);
                if ($savedFile) {
                    $payslipFilepath = $savedFile['filepath'];
                    $payslipFilename = $savedFile['filename'];
                    
                    // Only add to ZIP if download_locally is enabled
                    if ($downloadLocally) {
                        $savedFiles[] = $savedFile;
                    } else {
                        // File is temporary, only for email attachment
                        $isTemporaryFile = true;
                    }
                } else {
                    $saveError = 'Failed to save payslip file';
                }
            } catch (Exception $saveEx) {
                $saveError = 'File save error: ' . $saveEx->getMessage();
                error_log("Payslip save error for {$workerName}: " . $saveEx->getMessage());
            }
        }
        
        // If email sending is enabled and worker has email
        $emailSent = false;
        $emailError = null;
        
        if ($sendEmails) {
            // Check if workers table has email column, if not use contact info
            $workerEmail = null;
            
            // Try to get email from workers table (if column exists)
            try {
                $emailCheckStmt = $pdo->prepare("SHOW COLUMNS FROM workers LIKE 'email'");
                $emailCheckStmt->execute();
                if ($emailCheckStmt->rowCount() > 0) {
                    $emailStmt = $pdo->prepare("SELECT email FROM workers WHERE worker_name = ?");
                    $emailStmt->execute([$workerName]);
                    $emailResult = $emailStmt->fetch();
                    $workerEmail = $emailResult ? $emailResult['email'] : null;
                }
            } catch (PDOException $e) {
                // Email column doesn't exist, skip email sending
            }
            
            if ($workerEmail && filter_var($workerEmail, FILTER_VALIDATE_EMAIL)) {
                // Send email with payslip
                $subject = "Your Payslip - " . date('F Y', strtotime($dateTo)) . " - " . $companyName;
                
                $emailBody = "
                    <h2>Dear {$workerName},</h2>
                    <p>Your payslip for the period <strong>" . date('d M', strtotime($dateFrom)) . " - " . date('d M, Y', strtotime($dateTo)) . "</strong> is ready.</p>
                    <p>Please find your payslip attached to this email, or view it online:</p>
                    <p><a href=\"{$payslipUrl}\" style=\"display: inline-block; padding: 12px 24px; background: #7c3aed; color: white; text-decoration: none; border-radius: 6px; margin: 15px 0;\">View Your Payslip Online</a></p>
                    <p>Or copy this link to your browser:<br><small>{$payslipUrl}</small></p>
                    <hr>
                    <p><strong>Payday:</strong> " . date('l d/m/Y', strtotime($dateTo)) . "</p>
                    <p>If you have any questions about your payslip, please contact us at {$companyEmail}.</p>
                    <p>Best regards,<br>{$companyName}</p>
                ";
                
                try {
                    // Attach payslip file if saved
                    $attachment = null;
                    if ($payslipFilepath && file_exists($payslipFilepath)) {
                        $attachment = [
                            'path' => $payslipFilepath,
                            'name' => $payslipFilename ?: 'payslip.html',
                            'mime' => 'text/html'
                        ];
                    }
                    
                    $emailSent = $emailer->send($workerEmail, $subject, $emailBody, ['attachment' => $attachment]);
                    if (!$emailSent) {
                        $emailError = 'Email sending failed';
                    }
                } catch (Exception $e) {
                    $emailError = $e->getMessage();
                    $emailSent = false;
                }
            } else {
                $emailError = 'Worker email not available or invalid';
            }
        }
        
        // Build success message based on actions taken
        $messageParts = [];
        
        if (empty($entries)) {
            $messageParts[] = 'Payslip generated (zero amounts - no entries for this period)';
        } else {
            $messageParts[] = 'Payslip generated';
        }
        
        if ($downloadLocally) {
            if ($savedFile) {
                $messageParts[] = 'and saved locally';
            } elseif ($saveError) {
                $messageParts[] = '(file save failed: ' . $saveError . ')';
            }
        } elseif ($isTemporaryFile && $savedFile) {
            // File was saved temporarily for email attachment only
            // Don't mention it in the message since user didn't request download
        }
        
        if ($sendEmails) {
            if ($emailSent) {
                $messageParts[] = 'and emailed';
            } elseif ($emailError) {
                $messageParts[] = '(email not sent: ' . $emailError . ')';
            } else {
                $messageParts[] = '(email not sent: worker has no email address)';
            }
        }
        
        // If neither action succeeded (but both were attempted), mark as partial failure
        $hasActionSuccess = false;
        if ($downloadLocally) {
            $hasActionSuccess = $hasActionSuccess || $savedFile;
        } else {
            // If not downloading locally, file success doesn't count (it's just for email)
            $hasActionSuccess = $hasActionSuccess || ($sendEmails && $emailSent);
        }
        if ($sendEmails) {
            $hasActionSuccess = $hasActionSuccess || $emailSent;
        }
        
        if (!$hasActionSuccess && ($downloadLocally || $sendEmails)) {
            if ($downloadLocally && !$savedFile && $sendEmails && !$emailSent) {
                $messageParts = ['Payslip generated but failed to save locally and failed to send email'];
            } elseif ($sendEmails && !$emailSent && !$downloadLocally) {
                $messageParts = ['Payslip generated but failed to send email'];
            }
        }
        
        // Clean up temporary file if it was only created for email and email sending failed
        if ($isTemporaryFile && $savedFile && (!$sendEmails || !$emailSent)) {
            // Keep file for now - user might want to retry email
            // Could add cleanup logic here if needed
        }
        
        // Determine if this is actually a success
        // Payslip generation is always successful if we got this far (even with zero entries)
        // The payslip URL is always available (viewable online)
        // Only mark as failure if ALL explicitly requested actions failed
        
        $downloadSuccess = !$downloadLocally || $savedFile; // Success if not requested OR saved successfully
        $emailSuccess = !$sendEmails || $emailSent || !$workerEmail; // Success if not requested OR sent OR worker has no email
        
        // If download was requested and failed, and email wasn't requested or also failed, mark as failure
        if ($downloadLocally && !$savedFile && $saveError) {
            // Download failed
            if (!$sendEmails || !$emailSent) {
                // Email wasn't requested or also failed - this is a failure
                $isSuccess = false;
            } else {
                // Email succeeded, so overall success
                $isSuccess = true;
            }
        } elseif ($sendEmails && !$emailSent && $workerEmail && $emailError) {
            // Email was requested, worker has email, but sending failed
            if (!$downloadLocally || !$savedFile) {
                // Download wasn't requested or also failed - this is a failure
                $isSuccess = false;
            } else {
                // Download succeeded, so overall success
                $isSuccess = true;
            }
        } else {
            // At least one requested action succeeded, or payslip is viewable online
            $isSuccess = true;
        }
        
        $results[] = [
            'worker' => $workerName,
            'success' => $isSuccess,
            'message' => implode(' ', $messageParts),
            'payslip_url' => $payslipUrl,
            'payslip_file' => $payslipFilename,
            'payslip_file_url' => $savedFile ? $savedFile['url'] : null,
            'email_sent' => $emailSent,
            'email_error' => $emailError,
            'entries_count' => count($entries),
            'save_error' => $saveError,
            'zero_amount' => empty($entries),
            'download_requested' => $downloadLocally,
            'email_requested' => $sendEmails,
            'worker_has_email' => !empty($workerEmail)
        ];
        
        if ($isSuccess) {
            $successCount++;
        } else {
            $failCount++;
        }
        
    } catch (Exception $e) {
        error_log("Bulk payslip error for {$workerName}: " . $e->getMessage());
        $results[] = [
            'worker' => $workerName,
            'success' => false,
            'message' => 'Error: ' . $e->getMessage() . ' (check server logs for details)'
        ];
        $failCount++;
    } catch (PDOException $e) {
        error_log("Bulk payslip database error for {$workerName}: " . $e->getMessage());
        $results[] = [
            'worker' => $workerName,
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ];
        $failCount++;
    }
}

// Create ZIP file if we have saved files and download_locally is enabled
$zipFilepath = null;
$zipFilename = null;
$zipUrl = null;

if ($downloadLocally && !empty($savedFiles)) {
    try {
        $zipFilename = 'payslips_' . date('Y-m-d_His') . '.zip';
        $zipFilepath = $payslipDir . $zipFilename;
        
        // Create ZIP archive
        $zip = new ZipArchive();
        if ($zip->open($zipFilepath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
            foreach ($savedFiles as $file) {
                if (file_exists($file['filepath'])) {
                    $zip->addFile($file['filepath'], $file['filename']);
                }
            }
            $zip->close();
            
            if (file_exists($zipFilepath)) {
                $zipUrl = site_url('uploads/payslips/' . $zipFilename);
            }
        }
    } catch (Exception $e) {
        error_log("ZIP creation error: " . $e->getMessage());
    }
}

// Build summary message
$summaryMessage = '';
if ($successCount > 0) {
    $parts = ["Successfully generated {$successCount} payslip(s)"];
    if ($downloadLocally) {
        $parts[] = count($savedFiles) . " saved locally";
    }
    if ($sendEmails) {
        $emailedCount = 0;
        foreach ($results as $result) {
            if ($result['email_sent'] ?? false) {
                $emailedCount++;
            }
        }
        $parts[] = $emailedCount . " emailed";
    }
    if ($failCount > 0) {
        $parts[] = "({$failCount} failed)";
    }
    $summaryMessage = implode(', ', $parts) . '.';
} else {
    $summaryMessage = "Failed to generate payslips for all workers ({$failCount} failed). Check results for details.";
}

// Clear any output that might have been accidentally sent
ob_end_clean();

try {
    jsonResponse([
        'success' => $successCount > 0,
        'message' => $summaryMessage,
        'results' => $results,
        'summary' => [
            'total' => count($workerNames),
            'success' => $successCount,
            'failed' => $failCount,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'download_locally' => $downloadLocally,
            'send_emails' => $sendEmails,
            'files_saved' => count($savedFiles),
            'zip_file' => $zipFilename,
            'zip_url' => $zipUrl
        ]
    ]);
} catch (Exception $e) {
    error_log("Bulk payslip API response error: " . $e->getMessage());
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error generating response: ' . $e->getMessage(),
        'partial_results' => [
            'total' => count($workerNames),
            'success' => $successCount,
            'failed' => $failCount,
            'results' => $results
        ]
    ]);
    exit;
}
