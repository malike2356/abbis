<?php
/**
 * Complete System Data Import
 * Imports system data from JSON/SQL/CSV exports
 */
require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';

$auth->requireAuth();
$auth->requireRole(ROLE_ADMIN);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
    jsonResponse(['success' => false, 'message' => 'Invalid security token'], 403);
}

$pdo = getDBConnection();

try {
    if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
        jsonResponse(['success' => false, 'message' => 'No file uploaded'], 400);
    }
    
    $file = $_FILES['import_file'];
    $fileName = $file['name'];
    $fileTmp = $file['tmp_name'];
    $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    
    $importMode = $_POST['import_mode'] ?? 'append'; // append, replace
    
    $pdo->beginTransaction();
    
    if ($fileExtension === 'json') {
        $jsonData = file_get_contents($fileTmp);
        $data = json_decode($jsonData, true);
        
        if (!$data || !isset($data['tables'])) {
            throw new Exception('Invalid JSON format');
        }
        
        $imported = [];
        
        foreach ($data['tables'] as $tableName => $rows) {
            if (empty($rows)) continue;
            
            // Skip users table on import for security
            if ($tableName === 'users' && $importMode === 'replace') {
                $imported[$tableName] = 0;
                continue;
            }
            
            if ($importMode === 'replace') {
                // Truncate table (except users)
                $pdo->exec("DELETE FROM `$tableName`");
            }
            
            $importedCount = 0;
            foreach ($rows as $row) {
                $columns = array_keys($row);
                $placeholders = array_fill(0, count($columns), '?');
                
                $sql = "INSERT INTO `$tableName` (`" . implode('`, `', $columns) . "`) VALUES (" . implode(', ', $placeholders) . ")";
                
                // Handle duplicates - use INSERT IGNORE for most tables
                if ($tableName !== 'users') {
                    $sql = str_replace('INSERT INTO', 'INSERT IGNORE INTO', $sql);
                }
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute(array_values($row));
                $importedCount++;
            }
            
            $imported[$tableName] = $importedCount;
        }
        
        $pdo->commit();
        
        jsonResponse([
            'success' => true,
            'message' => 'Data imported successfully',
            'imported' => $imported,
            'metadata' => $data['metadata'] ?? null
        ]);
        
    } elseif ($fileExtension === 'sql') {
        $sqlContent = file_get_contents($fileTmp);
        
        // Execute SQL statements
        $statements = explode(';', $sqlContent);
        $importedCount = 0;
        
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (empty($statement) || strpos($statement, '--') === 0) {
                continue;
            }
            
            try {
                $pdo->exec($statement);
                $importedCount++;
            } catch (PDOException $e) {
                // Skip errors for missing tables
                if (strpos($e->getMessage(), "doesn't exist") === false) {
                    throw $e;
                }
            }
        }
        
        $pdo->commit();
        
        jsonResponse([
            'success' => true,
            'message' => 'SQL imported successfully',
            'statements_executed' => $importedCount
        ]);
        
    } elseif ($fileExtension === 'zip') {
        // Handle ZIP with CSV files
        $zip = new ZipArchive();
        if ($zip->open($fileTmp) === TRUE) {
            $imported = [];
            
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $filename = $zip->getNameIndex($i);
                if (pathinfo($filename, PATHINFO_EXTENSION) !== 'csv') {
                    continue;
                }
                
                $tableName = pathinfo($filename, PATHINFO_FILENAME);
                $csvContent = $zip->getFromIndex($i);
                
                $lines = explode("\n", $csvContent);
                if (count($lines) < 2) continue;
                
                $headers = str_getcsv($lines[0]);
                $importedCount = 0;
                
                if ($importMode === 'replace') {
                    $pdo->exec("DELETE FROM `$tableName`");
                }
                
                for ($j = 1; $j < count($lines); $j++) {
                    if (empty(trim($lines[$j]))) continue;
                    
                    $row = str_getcsv($lines[$j]);
                    if (count($row) !== count($headers)) continue;
                    
                    $rowData = array_combine($headers, $row);
                    $placeholders = array_fill(0, count($headers), '?');
                    
                    $sql = "INSERT IGNORE INTO `$tableName` (`" . implode('`, `', $headers) . "`) VALUES (" . implode(', ', $placeholders) . ")";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute(array_values($rowData));
                    $importedCount++;
                }
                
                $imported[$tableName] = $importedCount;
            }
            
            $zip->close();
            $pdo->commit();
            
            jsonResponse([
                'success' => true,
                'message' => 'CSV files imported successfully',
                'imported' => $imported
            ]);
        } else {
            throw new Exception('Unable to open ZIP file');
        }
    } else {
        jsonResponse(['success' => false, 'message' => 'Unsupported file format. Use JSON, SQL, or ZIP.'], 400);
    }
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log('Import error: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Import failed: ' . $e->getMessage()], 500);
}

