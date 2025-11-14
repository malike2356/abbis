<?php
/**
 * Configuration CRUD API
 */
// Suppress warnings/notices that might break JSON output
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Clear any existing output buffers and start fresh
while (ob_get_level() > 0) {
    ob_end_clean();
}
ob_start();

// Register shutdown function to catch any fatal errors and return JSON
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(500);
        }
        echo json_encode([
            'success' => false,
            'message' => 'Server error: ' . $error['message'],
            'file' => $error['file'],
            'line' => $error['line']
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
});

// Wrap entire file in try-catch to ensure JSON response always
try {
    require_once '../config/app.php';
    require_once '../config/security.php';
    require_once '../config/database.php';
    require_once '../includes/auth.php';
    require_once '../includes/helpers.php';

    // Load config-manager only if it exists and we need it
    if (file_exists(__DIR__ . '/../includes/config-manager.php')) {
        require_once '../includes/config-manager.php';
        // Initialize config manager if class exists
        if (class_exists('ConfigManager')) {
            $configManager = new ConfigManager();
        }
    }

    // Set JSON header early
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache, must-revalidate');
    }

    try {
        $auth->requireAuth();
        $auth->requireRole(ROLE_ADMIN);
    } catch (Exception $e) {
        // Clear any output
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        jsonResponse(['success' => false, 'message' => 'Authentication failed: ' . $e->getMessage()], 401);
    }
} catch (Throwable $e) {
    // Catch any fatal errors during includes
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
    }
    error_log('Config CRUD fatal error during initialization: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Server initialization error. Please check server logs or contact support.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Clear any output
while (ob_get_level() > 0) {
    ob_end_clean();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (empty($csrfToken)) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        jsonResponse(['success' => false, 'message' => 'Security token is required. Please refresh the page.'], 403);
    }
    
    if (!CSRF::validateToken($csrfToken)) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        jsonResponse(['success' => false, 'message' => 'Invalid or expired security token. Please refresh the page and try again.'], 403);
    }
    
    $action = $_POST['action'] ?? '';
    
    if (empty($action)) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        jsonResponse(['success' => false, 'message' => 'Action is required'], 400);
    }
    
    try {
        switch ($action) {
            case 'add_rig':
                if (!isset($configManager)) {
                    jsonResponse(['success' => false, 'message' => 'Config manager not available'], 500);
                }
                $result = $configManager->addRig([
                    'rig_name' => $_POST['rig_name'] ?? '',
                    'rig_code' => $_POST['rig_code'] ?? '',
                    'truck_model' => $_POST['truck_model'] ?? null,
                    'registration_number' => $_POST['registration_number'] ?? null,
                    'status' => $_POST['status'] ?? 'active',
                    'current_rpm' => $_POST['current_rpm'] ?? 0,
                    'maintenance_rpm_interval' => $_POST['maintenance_rpm_interval'] ?? 30.00,
                    'maintenance_due_at_rpm' => $_POST['maintenance_due_at_rpm'] ?? null
                ]);
                jsonResponse(['success' => $result, 'message' => $result ? 'Rig added successfully' : 'Failed to add rig']);
                break;
                
            case 'update_rig':
                if (!isset($configManager)) {
                    jsonResponse(['success' => false, 'message' => 'Config manager not available'], 500);
                }
                $result = $configManager->updateRig($_POST['id'] ?? 0, [
                    'rig_name' => $_POST['rig_name'] ?? '',
                    'rig_code' => $_POST['rig_code'] ?? '',
                    'truck_model' => $_POST['truck_model'] ?? null,
                    'registration_number' => $_POST['registration_number'] ?? null,
                    'status' => $_POST['status'] ?? 'active',
                    'current_rpm' => $_POST['current_rpm'] ?? null,
                    'maintenance_rpm_interval' => $_POST['maintenance_rpm_interval'] ?? null,
                    'maintenance_due_at_rpm' => !empty($_POST['maintenance_due_at_rpm']) ? $_POST['maintenance_due_at_rpm'] : null
                ]);
                jsonResponse(['success' => $result, 'message' => $result ? 'Rig updated successfully' : 'Failed to update rig']);
                break;
                
            case 'delete_rig':
                if (!isset($configManager)) {
                    jsonResponse(['success' => false, 'message' => 'Config manager not available'], 500);
                }
                $result = $configManager->deleteRig($_POST['id'] ?? 0);
                jsonResponse($result);
                break;
                
            case 'add_worker':
                if (!isset($configManager)) {
                    jsonResponse(['success' => false, 'message' => 'Config manager not available'], 500);
                }
                $result = $configManager->addWorker([
                    'worker_name' => $_POST['worker_name'] ?? '',
                    'role' => $_POST['role'] ?? '',
                    'default_rate' => $_POST['default_rate'] ?? 0,
                    'contact_number' => $_POST['contact_number'] ?? null,
                    'email' => $_POST['email'] ?? null,
                    'status' => $_POST['status'] ?? 'active'
                ]);
                jsonResponse(['success' => $result, 'message' => $result ? 'Worker added successfully' : 'Failed to add worker']);
                break;
                
            case 'update_worker':
                if (!isset($configManager)) {
                    jsonResponse(['success' => false, 'message' => 'Config manager not available'], 500);
                }
                $result = $configManager->updateWorker($_POST['id'] ?? 0, [
                    'worker_name' => $_POST['worker_name'] ?? '',
                    'role' => $_POST['role'] ?? '',
                    'default_rate' => $_POST['default_rate'] ?? 0,
                    'contact_number' => $_POST['contact_number'] ?? null,
                    'email' => $_POST['email'] ?? null,
                    'status' => $_POST['status'] ?? 'active'
                ]);
                jsonResponse(['success' => $result, 'message' => $result ? 'Worker updated successfully' : 'Failed to update worker']);
                break;
                
            case 'delete_worker':
                if (!isset($configManager)) {
                    jsonResponse(['success' => false, 'message' => 'Config manager not available'], 500);
                }
                $result = $configManager->deleteWorker($_POST['id'] ?? 0);
                jsonResponse($result);
                break;
                
            case 'add_worker_role':
                if (!isset($configManager)) {
                    jsonResponse(['success' => false, 'message' => 'Config manager not available'], 500);
                }
                $roleName = sanitizeInput($_POST['role_name'] ?? '');
                $description = sanitizeInput($_POST['description'] ?? '');
                if (empty($roleName)) {
                    jsonResponse(['success' => false, 'message' => 'Role name is required']);
                }
                $result = $configManager->addWorkerRole($roleName, $description);
                jsonResponse([
                    'success' => $result, 
                    'message' => $result ? 'Role added successfully' : 'Role already exists or failed to add'
                ]);
                break;
                
            case 'update_worker_role':
                if (!isset($configManager)) {
                    jsonResponse(['success' => false, 'message' => 'Config manager not available'], 500);
                }
                $id = (int)($_POST['id'] ?? 0);
                $roleName = sanitizeInput($_POST['role_name'] ?? '');
                $description = sanitizeInput($_POST['description'] ?? '');
                if (empty($roleName)) {
                    jsonResponse(['success' => false, 'message' => 'Role name is required']);
                }
                $result = $configManager->updateWorkerRole($id, $roleName, $description);
                
                if (is_array($result) && isset($result['success'])) {
                    // Role name changed - workers were updated
                    $message = 'Role updated successfully';
                    if ($result['workers_updated'] > 0) {
                        $message .= ". {$result['workers_updated']} worker(s) updated from '{$result['old_role']}' to '{$result['new_role']}'";
                    }
                    jsonResponse([
                        'success' => true,
                        'message' => $message,
                        'workers_updated' => $result['workers_updated'] ?? 0
                    ]);
                } else if ($result === true) {
                    // Only description changed or update succeeded without role name change
                    jsonResponse([
                        'success' => true, 
                        'message' => 'Role updated successfully'
                    ]);
                } else {
                    jsonResponse([
                        'success' => false, 
                        'message' => 'Failed to update role'
                    ]);
                }
                break;
                
            case 'delete_worker_role':
                if (!isset($configManager)) {
                    jsonResponse(['success' => false, 'message' => 'Config manager not available'], 500);
                }
                $id = (int)($_POST['id'] ?? 0);
                $result = $configManager->deleteWorkerRole($id);
                jsonResponse($result);
                break;
                
            case 'toggle_worker_role':
                if (!isset($configManager)) {
                    jsonResponse(['success' => false, 'message' => 'Config manager not available'], 500);
                }
                $id = (int)($_POST['id'] ?? 0);
                $result = $configManager->toggleWorkerRole($id);
                jsonResponse([
                    'success' => $result, 
                    'message' => $result ? 'Role status updated' : 'Failed to update role'
                ]);
                break;
                
            case 'update_material':
                // Clear any output before processing
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }
                
                if (!isset($configManager)) {
                    jsonResponse(['success' => false, 'message' => 'Config manager not available'], 500);
                }
                
                $materialType = $_POST['material_type'] ?? '';
                if (empty($materialType)) {
                    jsonResponse(['success' => false, 'message' => 'Material type is required'], 400);
                }
                
                $quantityReceived = isset($_POST['quantity_received']) ? floatval($_POST['quantity_received']) : null;
                $unitCost = isset($_POST['unit_cost']) ? floatval($_POST['unit_cost']) : null;
                
                if ($quantityReceived === null || $unitCost === null) {
                    jsonResponse(['success' => false, 'message' => 'Quantity received and unit cost are required'], 400);
                }
                
                if ($quantityReceived < 0 || $unitCost < 0) {
                    jsonResponse(['success' => false, 'message' => 'Values cannot be negative'], 400);
                }
                
                try {
                    $result = $configManager->updateMaterial($materialType, [
                        'quantity_received' => $quantityReceived,
                        'unit_cost' => $unitCost
                    ]);
                    
                    if ($result) {
                        jsonResponse(['success' => true, 'message' => 'Material updated successfully']);
                    } else {
                        jsonResponse(['success' => false, 'message' => 'Failed to update material. Please check if the material exists.'], 400);
                    }
                } catch (Exception $e) {
                    error_log("Material update error: " . $e->getMessage());
                    jsonResponse(['success' => false, 'message' => 'Error updating material: ' . $e->getMessage()], 500);
                }
                break;
                
            case 'set_rod_lengths':
                if (!isset($configManager)) {
                    jsonResponse(['success' => false, 'message' => 'Config manager not available'], 500);
                }
                $lengths = json_decode($_POST['rod_lengths'] ?? '[]', true);
                $result = $configManager->setRodLengths($lengths);
                jsonResponse(['success' => $result, 'message' => $result ? 'Rod lengths updated successfully' : 'Failed to update rod lengths']);
                break;
                
            case 'add_rod_length':
                if (!isset($configManager)) {
                    jsonResponse(['success' => false, 'message' => 'Config manager not available'], 500);
                }
                $length = trim($_POST['length'] ?? '');
                if (empty($length) || !is_numeric($length)) {
                    jsonResponse(['success' => false, 'message' => 'Invalid rod length. Please enter a valid number.']);
                }
                $result = $configManager->addRodLength($length);
                jsonResponse(['success' => $result['success'], 'message' => $result['message']]);
                break;
                
            case 'update_rod_length':
                if (!isset($configManager)) {
                    jsonResponse(['success' => false, 'message' => 'Config manager not available'], 500);
                }
                $oldLength = trim($_POST['old_length'] ?? '');
                $newLength = trim($_POST['length'] ?? '');
                if (empty($oldLength) || empty($newLength) || !is_numeric($newLength)) {
                    jsonResponse(['success' => false, 'message' => 'Invalid rod length. Please enter a valid number.']);
                }
                $result = $configManager->updateRodLength($oldLength, $newLength);
                jsonResponse(['success' => $result['success'], 'message' => $result['message']]);
                break;
                
            case 'delete_rod_length':
                if (!isset($configManager)) {
                    jsonResponse(['success' => false, 'message' => 'Config manager not available'], 500);
                }
                $length = trim($_POST['length'] ?? '');
                if (empty($length)) {
                    jsonResponse(['success' => false, 'message' => 'Rod length is required']);
                }
                $result = $configManager->deleteRodLength($length);
                jsonResponse(['success' => $result['success'], 'message' => $result['message']]);
                break;
                
            case 'update_company':
                // Clear output buffers completely
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }
                
                try {
                    $pdo = getDBConnection();
                    
                    if (!$pdo) {
                        jsonResponse(['success' => false, 'message' => 'Database connection failed'], 500);
                    }
                    
                    // Handle logo upload if provided
                    if (isset($_FILES['company_logo']) && $_FILES['company_logo']['error'] === UPLOAD_ERR_OK) {
                        $file = $_FILES['company_logo'];
                        $allowedTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/gif', 'image/svg+xml'];
                        $maxSize = 2 * 1024 * 1024; // 2MB
                        
                        // Check file size first
                        if ($file['size'] > $maxSize) {
                            jsonResponse(['success' => false, 'message' => 'File size exceeds 2MB limit'], 400);
                        }
                        
                        // Check MIME type
                        $finfo = finfo_open(FILEINFO_MIME_TYPE);
                        if (!$finfo) {
                            jsonResponse(['success' => false, 'message' => 'Unable to verify file type'], 500);
                        }
                        $mimeType = finfo_file($finfo, $file['tmp_name']);
                        finfo_close($finfo);
                        
                        if (!in_array($mimeType, $allowedTypes)) {
                            jsonResponse(['success' => false, 'message' => 'Invalid file type. Only PNG, JPG, GIF, and SVG are allowed.'], 400);
                        }
                        
                        $uploadDir = __DIR__ . '/../uploads/logos/';
                        if (!is_dir($uploadDir)) {
                            if (!mkdir($uploadDir, 0755, true)) {
                                jsonResponse(['success' => false, 'message' => 'Unable to create upload directory'], 500);
                            }
                        }
                        
                        // Delete old logo
                        $stmt = $pdo->prepare("SELECT config_value FROM system_config WHERE config_key = 'company_logo'");
                        $stmt->execute();
                        $oldLogo = $stmt->fetch();
                        if ($oldLogo && !empty($oldLogo['config_value'])) {
                            $oldPath = __DIR__ . '/../uploads/logos/' . basename($oldLogo['config_value']);
                            if (file_exists($oldPath)) {
                                @unlink($oldPath);
                            }
                        }
                        
                        // Save new logo
                        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                        $filename = 'company_logo_' . time() . '_' . uniqid() . '.' . $extension;
                        $filePath = $uploadDir . $filename;
                        
                        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                            jsonResponse(['success' => false, 'message' => 'Failed to save uploaded file. Please check directory permissions.'], 500);
                        }
                        
                        // Set proper permissions
                        @chmod($filePath, 0644);
                        
                        $relativePath = 'uploads/logos/' . $filename;
                        $stmt = $pdo->prepare("
                            INSERT INTO system_config (config_key, config_value) 
                            VALUES ('company_logo', ?) 
                            ON DUPLICATE KEY UPDATE config_value = ?
                        ");
                        $stmt->execute([$relativePath, $relativePath]);
                        
                        // Create favicon copy
                        if (in_array($extension, ['png', 'jpg', 'jpeg'])) {
                            $faviconDir = __DIR__ . '/../assets/images/';
                            if (!is_dir($faviconDir)) {
                                @mkdir($faviconDir, 0755, true);
                            }
                            if (is_dir($faviconDir)) {
                                $faviconPath = $faviconDir . 'favicon.' . $extension;
                                @copy($filePath, $faviconPath);
                                @chmod($faviconPath, 0644);
                            }
                        }
                    }
                    
                    // Update company info fields
                    $fieldsToUpdate = [
                        'company_name' => $_POST['config_company_name'] ?? null,
                        'company_tagline' => $_POST['config_company_tagline'] ?? null,
                        'company_address' => $_POST['config_company_address'] ?? null,
                        'company_contact' => $_POST['config_company_contact'] ?? null,
                        'company_email' => $_POST['config_company_email'] ?? null
                    ];
                    
                    $updatedCount = 0;
                    $updateErrors = [];
                    
                    foreach ($fieldsToUpdate as $field => $value) {
                        if ($value !== null && $value !== '') {
                            try {
                                $sanitizedValue = sanitizeInput($value);
                                $stmt = $pdo->prepare("
                                    INSERT INTO system_config (config_key, config_value) 
                                    VALUES (?, ?) 
                                    ON DUPLICATE KEY UPDATE config_value = ?
                                ");
                                
                                $success = $stmt->execute([$field, $sanitizedValue, $sanitizedValue]);
                                
                                if ($success) {
                                    $updatedCount++;
                                } else {
                                    $updateErrors[] = $field;
                                }
                            } catch (PDOException $e) {
                                error_log("Error updating config field '$field': " . $e->getMessage());
                                $updateErrors[] = $field;
                            }
                        }
                    }
                    
                    // Clear output buffers before response
                    while (ob_get_level() > 0) {
                        ob_end_clean();
                    }
                    
                    if ($updatedCount === 0 && empty($updateErrors)) {
                        jsonResponse(['success' => false, 'message' => 'No fields to update'], 400);
                    }
                    
                    if (!empty($updateErrors)) {
                        jsonResponse([
                            'success' => false,
                            'message' => 'Some fields failed to update: ' . implode(', ', $updateErrors),
                            'updated' => $updatedCount
                        ], 500);
                    }
                    
                    jsonResponse([
                        'success' => true,
                        'message' => 'Company information updated successfully',
                        'fields_updated' => $updatedCount
                    ]);
                    
                } catch (PDOException $e) {
                    while (ob_get_level() > 0) {
                        ob_end_clean();
                    }
                    error_log('Company update database error: ' . $e->getMessage());
                    jsonResponse([
                        'success' => false,
                        'message' => 'Database error: ' . $e->getMessage()
                    ], 500);
                } catch (Exception $e) {
                    while (ob_get_level() > 0) {
                        ob_end_clean();
                    }
                    error_log('Company update error: ' . $e->getMessage());
                    error_log('Stack trace: ' . $e->getTraceAsString());
                    jsonResponse([
                        'success' => false,
                        'message' => 'Error updating company info: ' . $e->getMessage()
                    ], 500);
                }
                break;
                
            default:
                jsonResponse(['success' => false, 'message' => 'Invalid action: ' . htmlspecialchars($action)], 400);
        }
    } catch (PDOException $e) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        error_log('Config CRUD API database error: ' . $e->getMessage());
        error_log('Stack trace: ' . $e->getTraceAsString());
        jsonResponse([
            'success' => false, 
            'message' => 'Database error occurred. Please check server logs or try again.',
            'error_code' => $e->getCode()
        ], 500);
    } catch (Exception $e) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        error_log('Config CRUD API error: ' . $e->getMessage());
        error_log('Stack trace: ' . $e->getTraceAsString());
        jsonResponse([
            'success' => false, 
            'message' => 'An error occurred: ' . htmlspecialchars($e->getMessage())
        ], 500);
    } catch (Throwable $e) {
        // Catch any other errors (PHP 7+)
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        error_log('Config CRUD API fatal error: ' . $e->getMessage());
        error_log('Stack trace: ' . $e->getTraceAsString());
        jsonResponse([
            'success' => false, 
            'message' => 'Server error occurred. Please try again or contact support.'
        ], 500);
    }
} else {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    jsonResponse(['success' => false, 'message' => 'Method not allowed. Use POST.'], 405);
}


