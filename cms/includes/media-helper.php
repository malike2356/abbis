<?php
/**
 * Centralized Media Helper
 * Automatically tracks all media uploads to the media library
 */

/**
 * Register a media file in the media library
 * Call this after any file upload to automatically track it
 */
function registerMediaFile($filePath, $originalName, $fileType, $fileSize, $uploadedBy = null) {
    try {
        $pdo = getDBConnection();
        
        // Ensure media table exists with UNIQUE constraint
        try {
            $pdo->query("SELECT 1 FROM cms_media LIMIT 1");
            // Check if UNIQUE constraint exists, add it if not
            try {
                $constraintCheck = $pdo->query("SHOW INDEX FROM cms_media WHERE Key_name = 'unique_file_path'");
                if (!$constraintCheck->fetch()) {
                    // Add UNIQUE constraint if it doesn't exist
                    try {
                        $pdo->exec("ALTER TABLE cms_media ADD UNIQUE KEY unique_file_path (file_path)");
                    } catch (PDOException $e) {
                        // Constraint might already exist with different name, ignore
                    }
                }
            } catch (PDOException $e) {
                // Ignore constraint check errors
            }
        } catch (PDOException $e) {
            // Table doesn't exist, create it with UNIQUE constraint
            $pdo->exec("CREATE TABLE IF NOT EXISTS cms_media (
                id INT AUTO_INCREMENT PRIMARY KEY,
                filename VARCHAR(255) NOT NULL,
                original_name VARCHAR(255) NOT NULL,
                file_path VARCHAR(500) NOT NULL,
                file_type VARCHAR(100) NOT NULL,
                file_size INT NOT NULL,
                uploaded_by INT DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_uploaded_by (uploaded_by),
                INDEX idx_file_type (file_type),
                INDEX idx_created_at (created_at),
                UNIQUE KEY unique_file_path (file_path)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
        
        // Extract filename from path
        $filename = basename($filePath);
        
        // Normalize path for duplicate checking
        $normalizedPath = normalizeFilePath($filePath);
        
        // Check if already exists (check both original and normalized paths)
        $checkStmt = $pdo->prepare("SELECT id FROM cms_media WHERE file_path = ? OR file_path = ? LIMIT 1");
        $checkStmt->execute([$filePath, $normalizedPath]);
        if ($checkStmt->fetch()) {
            return true; // Already registered
        }
        
        // Insert into media library - use INSERT IGNORE to prevent duplicates
        $stmt = $pdo->prepare("INSERT IGNORE INTO cms_media (filename, original_name, file_path, file_type, file_size, uploaded_by, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$filename, $originalName, $filePath, $fileType, $fileSize, $uploadedBy]);
        
        // Check if insert was successful (INSERT IGNORE returns 0 rows if duplicate)
        if ($stmt->rowCount() === 0) {
            return true; // Duplicate prevented, consider it successful
        }
        
        return true;
    } catch (Exception $e) {
        // Silently fail - don't break uploads if media tracking fails
        error_log("Media registration failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Scan directories for media files and register them
 */
function scanAndRegisterMedia($rootPath = null, $verbose = false) {
    if ($rootPath === null) {
        $rootPath = dirname(dirname(__DIR__));
    }
    
    $pdo = getDBConnection();
    
    $scannedPaths = [];
    $foundFiles = [];
    
    // Ensure media table exists
    try {
        $pdo->query("SELECT 1 FROM cms_media LIMIT 1");
    } catch (PDOException $e) {
        // Check if UNIQUE constraint exists, add it if not
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS cms_media (
                id INT AUTO_INCREMENT PRIMARY KEY,
                filename VARCHAR(255) NOT NULL,
                original_name VARCHAR(255) NOT NULL,
                file_path VARCHAR(500) NOT NULL,
                file_type VARCHAR(100) NOT NULL,
                file_size INT NOT NULL,
                uploaded_by INT DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_uploaded_by (uploaded_by),
                INDEX idx_file_type (file_type),
                INDEX idx_created_at (created_at),
                UNIQUE KEY unique_file_path (file_path)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            
            // Ensure UNIQUE constraint exists (in case table was created without it)
            try {
                $pdo->query("SELECT 1 FROM cms_media LIMIT 1");
                // Check if unique constraint exists
                $constraintCheck = $pdo->query("SHOW INDEX FROM cms_media WHERE Key_name = 'unique_file_path'");
                if (!$constraintCheck->fetch()) {
                    // Add UNIQUE constraint if it doesn't exist
                    try {
                        $pdo->exec("ALTER TABLE cms_media ADD UNIQUE KEY unique_file_path (file_path)");
                        if ($verbose) {
                            echo "Added UNIQUE constraint on file_path to prevent duplicates\n";
                            @flush();
                        }
                    } catch (PDOException $e) {
                        // Constraint might already exist with different name, ignore
                        if ($verbose && strpos($e->getMessage(), 'Duplicate key name') === false) {
                            echo "Note: Could not add UNIQUE constraint: " . $e->getMessage() . "\n";
                            @flush();
                        }
                    }
                }
            } catch (PDOException $e) {
                // Table might not exist, that's OK
            }
        } catch (PDOException $e) {
            // Table creation failed, might already exist
            if ($verbose) {
                echo "Note: " . $e->getMessage() . "\n";
                @flush();
            }
        }
    }
    
    // Directories to scan - comprehensive list for system-wide image discovery
    $scanDirs = [
        'uploads/media',
        'uploads/avatars',
        'uploads/products',
        'uploads/portfolio',
        'uploads/profiles',
        'uploads/site',
        'uploads/reports', // Field reports may have images
        'uploads/clients', // Client-related images
        'uploads/workers', // Worker photos
        'uploads/rigs', // Rig images
        'uploads/documents', // Document attachments
        'uploads/temp', // Temporary uploads
        'uploads', // Root uploads directory
        'assets/images', // Static assets
        'assets/img', // Alternative assets folder
        'images', // Root images folder
        'img', // Alternative root images
        'cms/public/uploads', // CMS public uploads
        'cms/uploads' // CMS uploads
    ];
    
    $allowedExtensions = [
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'ico',
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'rtf',
        'mp4', 'avi', 'mov', 'wmv', 'flv', 'webm',
        'mp3', 'wav', 'ogg', 'm4a', 'wma',
        'zip', 'rar', '7z', 'tar', 'gz'
    ];
    
    $registered = 0;
    $skipped = 0;
    $errors = [];
    
    // Get existing file paths from database - also check by filename and size for better duplicate detection
    $existingPaths = [];
    $existingFiles = []; // Track by filename+size for additional duplicate detection
    try {
        $stmt = $pdo->query("SELECT file_path, filename, file_size FROM cms_media");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!empty($row['file_path'])) {
                // Normalize path for comparison
                $normalizedPath = normalizeFilePath($row['file_path']);
                $existingPaths[$normalizedPath] = true;
                $existingPaths[$row['file_path']] = true; // Also keep original
                
                // Also track by filename + size for duplicate detection
                if (!empty($row['filename']) && !empty($row['file_size'])) {
                    $fileKey = strtolower($row['filename']) . '|' . $row['file_size'];
                    $existingFiles[$fileKey] = $row['file_path'];
                }
            }
        }
    } catch (PDOException $e) {
        // Table might not exist yet
        if ($verbose) {
            echo "Note: Could not load existing paths from database: " . $e->getMessage() . "\n";
            @flush();
        }
    }
    
    // Function to get MIME type
    function getMimeType($filepath) {
        if (function_exists('mime_content_type')) {
            return mime_content_type($filepath);
        } elseif (function_exists('finfo_file')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $filepath);
            finfo_close($finfo);
            return $mime;
        } else {
            // Fallback based on extension
            $ext = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
            $mimeTypes = [
                'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
                'gif' => 'image/gif', 'webp' => 'image/webp', 'svg' => 'image/svg+xml',
                'pdf' => 'application/pdf', 'doc' => 'application/msword',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'xls' => 'application/vnd.ms-excel',
                'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'mp4' => 'video/mp4', 'mp3' => 'audio/mpeg'
            ];
            return $mimeTypes[$ext] ?? 'application/octet-stream';
        }
    }
    
    // Recursive directory scanner
    function scanDirectory($dir, $rootPath, $allowedExtensions, $existingPaths, $existingFiles, $pdo, &$registered, &$skipped, &$errors, &$scannedPaths, &$foundFiles, $verbose = false) {
        if (!is_dir($dir)) {
            return;
        }
        
        // Track scanned path - normalize it
        $dirNormalized = str_replace('\\', '/', $dir);
        $rootPathNormalized = str_replace('\\', '/', $rootPath);
        $relativeDir = $dirNormalized;
        
        // Remove root path if present
        if (strpos($dirNormalized, $rootPathNormalized) === 0) {
            $relativeDir = substr($dirNormalized, strlen($rootPathNormalized));
            if (substr($relativeDir, 0, 1) === '/') {
                $relativeDir = substr($relativeDir, 1);
            }
        }
        
        if (!in_array($relativeDir, $scannedPaths)) {
            $scannedPaths[] = $relativeDir;
            if ($verbose) {
                echo "Scanning: " . $relativeDir . "\n";
                @flush();
            }
        }
        
        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            
            foreach ($iterator as $file) {
                try {
                    if ($file->isFile()) {
                        $filepath = $file->getRealPath();
                        if (!$filepath) {
                            continue;
                        }
                        
                        $ext = strtolower($file->getExtension());
                        
                        if (in_array($ext, $allowedExtensions)) {
                            // Get relative path - normalize it
                            $relativePath = str_replace('\\', '/', $filepath);
                            $rootPathNormalized = str_replace('\\', '/', $rootPath);
                            
                            // Remove root path
                            if (strpos($relativePath, $rootPathNormalized) === 0) {
                                $relativePath = substr($relativePath, strlen($rootPathNormalized));
                                if (substr($relativePath, 0, 1) === '/') {
                                    $relativePath = substr($relativePath, 1);
                                }
                            }
                            
                            // Skip if path is empty or invalid
                            if (empty($relativePath)) {
                                if ($verbose) {
                                    echo "Warning: Could not determine relative path for: " . $filepath . "\n";
                                    @flush();
                                }
                                continue;
                            }
                            
                            // Normalize path for duplicate checking
                            $normalizedPath = normalizeFilePath($relativePath);
                            
                            // Track found file
                            $foundFiles[] = [
                                'path' => $relativePath,
                                'name' => $file->getFilename(),
                                'size' => $file->getSize(),
                                'type' => $ext
                            ];
                            
                            // Skip if already registered (check both original and normalized paths)
                            if (isset($existingPaths[$relativePath]) || isset($existingPaths[$normalizedPath])) {
                                $skipped++;
                                if ($verbose) {
                                    echo "Skipped (already in database): " . $relativePath . "\n";
                                    @flush();
                                }
                                continue;
                            }
                            
                            // Additional duplicate check: same filename + size (might be same file in different location)
                            $filename = $file->getFilename();
                            $fileSize = $file->getSize();
                            $fileKey = strtolower($filename) . '|' . $fileSize;
                            
                            if (isset($existingFiles[$fileKey])) {
                                // Same file already exists - skip to avoid duplicates
                                $skipped++;
                                if ($verbose) {
                                    echo "Skipped (duplicate file - same name and size): " . $relativePath . " (existing: " . $existingFiles[$fileKey] . ")\n";
                                    @flush();
                                }
                                continue;
                            }
                            
                            try {
                                $filename = $file->getFilename();
                                $originalName = $filename;
                                $fileSize = $file->getSize();
                                
                                // Validate file size
                                if ($fileSize <= 0) {
                                    $errors[] = $relativePath . ': Invalid file size (0 bytes)';
                                    if ($verbose) {
                                        echo "Error: " . $relativePath . " - Invalid file size\n";
                                        @flush();
                                    }
                                    continue;
                                }
                                
                                $fileType = getMimeType($filepath);
                                
                                // Validate file path length
                                if (strlen($relativePath) > 500) {
                                    $errors[] = $relativePath . ': File path too long';
                                    if ($verbose) {
                                        echo "Error: " . $relativePath . " - Path too long\n";
                                        @flush();
                                    }
                                    continue;
                                }
                                
                                // Final check: query database one more time to ensure no duplicates
                                // Check both normalized and original paths
                                $checkStmt = $pdo->prepare("SELECT id, file_path FROM cms_media WHERE file_path = ? OR file_path = ? LIMIT 1");
                                $checkStmt->execute([$relativePath, $normalizedPath]);
                                $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
                                
                                if ($existing) {
                                    $skipped++;
                                    $existingPaths[$relativePath] = true;
                                    $existingPaths[$normalizedPath] = true;
                                    $existingFiles[$fileKey] = $existing['file_path'];
                                    if ($verbose) {
                                        echo "Skipped (duplicate in database): " . $relativePath . "\n";
                                        @flush();
                                    }
                                    continue;
                                }
                                
                                // Insert into database - use INSERT IGNORE with UNIQUE constraint on file_path
                                try {
                                    // Store the actual relative path (not normalized) so it matches the file system
                                    // But we check for duplicates using normalized paths in PHP before insertion
                                    $stmt = $pdo->prepare("INSERT IGNORE INTO cms_media (filename, original_name, file_path, file_type, file_size, uploaded_by, created_at) VALUES (?, ?, ?, ?, ?, NULL, FROM_UNIXTIME(?))");
                                    $stmt->execute([
                                        $filename,
                                        $originalName,
                                        $relativePath,
                                        $fileType,
                                        $fileSize,
                                        $file->getMTime() // Use file modification time
                                    ]);
                                    
                                    // Check if row was actually inserted
                                    if ($stmt->rowCount() > 0) {
                                        $registered++;
                                        // Mark as registered in both formats
                                        $existingPaths[$relativePath] = true;
                                        $existingPaths[$normalizedPath] = true;
                                        $existingFiles[$fileKey] = $relativePath;
                                        if ($verbose) {
                                            echo "Registered: " . $relativePath . "\n";
                                            @flush();
                                        }
                                    } else {
                                        // Row wasn't inserted (duplicate prevented by UNIQUE constraint)
                                        $skipped++;
                                        $existingPaths[$relativePath] = true;
                                        $existingPaths[$normalizedPath] = true;
                                        $existingFiles[$fileKey] = $relativePath;
                                        if ($verbose) {
                                            echo "Skipped (duplicate prevented): " . $relativePath . "\n";
                                            @flush();
                                        }
                                    }
                                } catch (PDOException $e) {
                                    // If INSERT IGNORE doesn't work, try regular INSERT with duplicate check
                                    throw $e;
                                }
                            } catch (PDOException $e) {
                                $errorCode = $e->getCode();
                                $errorMsg = $e->getMessage();
                                
                                // Handle duplicate entry error (1062)
                                if ($errorCode == 23000 || strpos($errorMsg, 'Duplicate entry') !== false || strpos($errorMsg, 'UNIQUE constraint') !== false) {
                                    $skipped++;
                                    $existingPaths[$relativePath] = true;
                                    if ($verbose) {
                                        echo "Skipped (duplicate): " . $relativePath . "\n";
                                        @flush();
                                    }
                                } else {
                                    $errorMsg = $relativePath . ': ' . $errorMsg . ' (Code: ' . $errorCode . ')';
                                    $errors[] = $errorMsg;
                                    if ($verbose) {
                                        echo "Error: " . $errorMsg . "\n";
                                        @flush();
                                    }
                                }
                            } catch (Exception $e) {
                                $errorMsg = $relativePath . ': ' . $e->getMessage();
                                $errors[] = $errorMsg;
                                if ($verbose) {
                                    echo "Error: " . $errorMsg . "\n";
                                    @flush();
                                }
                            }
                        }
                    }
                } catch (Exception $e) {
                    // Skip files that can't be read
                    if ($verbose) {
                        echo "Warning: Could not process file - " . $e->getMessage() . "\n";
                        @flush();
                    }
                    continue;
                }
            }
        } catch (Exception $e) {
            $errors[] = "Error scanning directory {$relativeDir}: " . $e->getMessage();
            if ($verbose) {
                echo "Error scanning directory {$relativeDir}: " . $e->getMessage() . "\n";
                @flush();
            }
        }
    }
    
    // Scan each directory
    foreach ($scanDirs as $dir) {
        $fullPath = $rootPath . '/' . $dir;
        if ($verbose) {
            echo "Checking directory: " . $dir . "\n";
            @flush();
        }
        if (is_dir($fullPath)) {
            if ($verbose) {
                echo "✓ Directory exists, scanning...\n";
                @flush();
            }
            try {
                scanDirectory($fullPath, $rootPath, $allowedExtensions, $existingPaths, $existingFiles, $pdo, $registered, $skipped, $errors, $scannedPaths, $foundFiles, $verbose);
                if ($verbose) {
                    echo "Completed scanning: " . $dir . "\n";
                    @flush();
                }
            } catch (Exception $e) {
                $errors[] = "Error processing directory {$dir}: " . $e->getMessage();
                if ($verbose) {
                    echo "✗ Error scanning {$dir}: " . $e->getMessage() . "\n";
                    @flush();
                }
            }
        } elseif ($verbose) {
            echo "✗ Directory not found: " . $dir . "\n";
            @flush();
        }
    }
    
    if ($verbose) {
        echo "\n=== Scan Summary ===\n";
        echo "Directories scanned: " . count($scannedPaths) . "\n";
        echo "Total files found: " . count($foundFiles) . "\n";
        echo "New files registered: " . $registered . "\n";
        echo "Files skipped: " . $skipped . "\n";
        echo "Errors: " . count($errors) . "\n";
        @flush();
    }
    
    return [
        'registered' => $registered,
        'skipped' => $skipped,
        'errors' => $errors,
        'scanned_paths' => $scannedPaths,
        'found_files' => $foundFiles,
        'total_found' => count($foundFiles)
    ];
}

/**
 * Handle file upload and automatically register in media library
 * Now with automatic image processing
 */
function handleMediaUpload($file, $uploadDir, $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip', 'mp4', 'mp3'], $maxSize = 10000000) {
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Upload error'];
    }
    
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($ext, $allowedExtensions)) {
        return ['success' => false, 'error' => 'Invalid file type'];
    }
    
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'error' => 'File too large'];
    }
    
    // Get root path
    $rootPath = dirname(dirname(__DIR__));
    
    // Check if it's an image
    $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
    
    if ($isImage) {
        // Use image processing engine
        try {
            require_once $rootPath . '/cms/includes/image-resizer.php';
            $pdo = getDBConnection();
            $imageResizer = new ImageResizer($rootPath, $pdo);
            
            if ($imageResizer->isAvailable()) {
                // Get current user ID if available
                $uploadedBy = null;
                if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['cms_user_id'])) {
                    $uploadedBy = $_SESSION['cms_user_id'];
                }
                
                // Process image through engine
                $result = $imageResizer->processAndRegister(
                    $file['tmp_name'],
                    $file['name'],
                    $file['type'],
                    $file['size'],
                    $uploadedBy
                );
                
                if ($result['success']) {
                    return [
                        'success' => true,
                        'filepath' => $rootPath . '/' . $result['file_path'],
                        'relative_path' => $result['file_path'],
                        'filename' => basename($result['file_path']),
                        'media_id' => $result['media_id'] ?? null,
                        'sizes' => $result['sizes'] ?? [],
                        'processed' => true
                    ];
                }
            }
        } catch (Exception $e) {
            // Fall through to standard upload if image processing fails
            error_log("Image processing failed: " . $e->getMessage());
        }
    }
    
    // Standard upload for non-images or if image processing failed
    // Create upload directory if it doesn't exist
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Generate unique filename
    $uniqueFilename = uniqid() . '_' . time() . '.' . $ext;
    $filepath = $uploadDir . $uniqueFilename;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        // Get relative path for database
        // Normalize paths
        $filepath = str_replace('\\', '/', $filepath);
        $rootPathNormalized = str_replace('\\', '/', $rootPath);
        $relativePath = str_replace($rootPathNormalized . '/', '', $filepath);
        
        // Get current user ID if available
        $uploadedBy = null;
        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['cms_user_id'])) {
            $uploadedBy = $_SESSION['cms_user_id'];
        }
        
        // Register in media library
        registerMediaFile($relativePath, $file['name'], $file['type'], $file['size'], $uploadedBy);
        
        return [
            'success' => true,
            'filepath' => $filepath,
            'relative_path' => $relativePath,
            'filename' => $uniqueFilename,
            'processed' => false
        ];
    }
    
    return ['success' => false, 'error' => 'Failed to move uploaded file'];
}

/**
 * Normalize file path for duplicate detection
 * Handles various path formats and normalizes them consistently
 */
function normalizeFilePath($filePath) {
    if (empty($filePath)) {
        return '';
    }
    
    // Normalize directory separators
    $normalized = str_replace('\\', '/', $filePath);
    
    // Remove leading/trailing slashes
    $normalized = trim($normalized, '/');
    
    // Remove double slashes
    $normalized = preg_replace('#/+#', '/', $normalized);
    
    return $normalized;
}

/**
 * Find and remove duplicate media entries
 * Returns array with statistics about duplicates found and removed
 */
function findAndRemoveDuplicates($rootPath = null, $dryRun = false) {
    if ($rootPath === null) {
        $rootPath = dirname(dirname(__DIR__));
    }
    
    $pdo = getDBConnection();
    $removedCount = 0;
    $duplicateGroups = [];
    $errors = [];
    
    try {
        // Get all media entries
        $stmt = $pdo->query("SELECT id, file_path, filename, file_size, created_at FROM cms_media ORDER BY created_at ASC");
        $allMedia = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Group by normalized file path
        $pathGroups = [];
        foreach ($allMedia as $media) {
            $normalizedPath = normalizeFilePath($media['file_path']);
            
            if (empty($normalizedPath)) {
                continue;
            }
            
            if (!isset($pathGroups[$normalizedPath])) {
                $pathGroups[$normalizedPath] = [];
            }
            
            $pathGroups[$normalizedPath][] = $media;
        }
        
        // Find duplicates - same normalized path
        foreach ($pathGroups as $normalizedPath => $group) {
            if (count($group) > 1) {
                // Sort by ID (keep the oldest/earliest one)
                usort($group, function($a, $b) {
                    return $a['id'] - $b['id'];
                });
                
                // Keep the first one, mark others for deletion
                $keep = array_shift($group);
                $duplicateGroups[$normalizedPath] = [
                    'keep' => $keep,
                    'duplicates' => $group
                ];
            }
        }
        
        // Also check for duplicates by filename + size (same file, different paths)
        $fileGroups = [];
        foreach ($allMedia as $media) {
            $fileKey = strtolower($media['filename']) . '|' . $media['file_size'];
            
            if (!isset($fileGroups[$fileKey])) {
                $fileGroups[$fileKey] = [];
            }
            
            $fileGroups[$fileKey][] = $media;
        }
        
        // Check if files with same name+size actually exist and are identical
        foreach ($fileGroups as $fileKey => $group) {
            if (count($group) > 1) {
                // Check if files actually exist and are the same
                $validPaths = [];
                $nonExistent = [];
                
                foreach ($group as $media) {
                    $fullPath = $rootPath . '/' . $media['file_path'];
                    if (file_exists($fullPath)) {
                        $validPaths[] = $media;
                    } else {
                        $nonExistent[] = $media;
                    }
                }
                
                // If we have multiple valid paths with same name+size, they might be duplicates
                if (count($validPaths) > 1) {
                    // Sort by ID (keep the oldest)
                    usort($validPaths, function($a, $b) {
                        return $a['id'] - $b['id'];
                    });
                    
                    // Compare file contents to see if they're actually the same
                    $keep = array_shift($validPaths);
                    $keepPath = $rootPath . '/' . $keep['file_path'];
                    $keepHash = file_exists($keepPath) ? md5_file($keepPath) : null;
                    
                    $duplicates = [];
                    foreach ($validPaths as $candidate) {
                        $candidatePath = $rootPath . '/' . $candidate['file_path'];
                        $candidateHash = file_exists($candidatePath) ? md5_file($candidatePath) : null;
                        
                        // If hashes match, it's a duplicate
                        if ($keepHash && $candidateHash && $keepHash === $candidateHash) {
                            $duplicates[] = $candidate;
                        }
                    }
                    
                    if (!empty($duplicates)) {
                        $normalizedKeepPath = normalizeFilePath($keep['file_path']);
                        if (!isset($duplicateGroups[$normalizedKeepPath])) {
                            $duplicateGroups[$normalizedKeepPath] = [
                                'keep' => $keep,
                                'duplicates' => []
                            ];
                        }
                        // Merge duplicates
                        $duplicateGroups[$normalizedKeepPath]['duplicates'] = array_merge(
                            $duplicateGroups[$normalizedKeepPath]['duplicates'],
                            $duplicates
                        );
                    }
                }
                
                // Mark non-existent files as duplicates to remove
                if (!empty($nonExistent)) {
                    foreach ($nonExistent as $media) {
                        $normalizedPath = normalizeFilePath($media['file_path']);
                        if (!isset($duplicateGroups[$normalizedPath])) {
                            // Find if there's a valid version of this file
                            $hasValidVersion = false;
                            foreach ($group as $other) {
                                if ($other['id'] != $media['id']) {
                                    $otherPath = $rootPath . '/' . $other['file_path'];
                                    if (file_exists($otherPath)) {
                                        $hasValidVersion = true;
                                        break;
                                    }
                                }
                            }
                            
                            if ($hasValidVersion) {
                                // This non-existent file is a duplicate
                                $firstValid = null;
                                foreach ($group as $other) {
                                    $otherPath = $rootPath . '/' . $other['file_path'];
                                    if (file_exists($otherPath)) {
                                        $firstValid = $other;
                                        break;
                                    }
                                }
                                
                                if ($firstValid) {
                                    $normalizedFirstPath = normalizeFilePath($firstValid['file_path']);
                                    if (!isset($duplicateGroups[$normalizedFirstPath])) {
                                        $duplicateGroups[$normalizedFirstPath] = [
                                            'keep' => $firstValid,
                                            'duplicates' => []
                                        ];
                                    }
                                    $duplicateGroups[$normalizedFirstPath]['duplicates'][] = $media;
                                }
                            }
                        }
                    }
                }
            }
        }
        
        // Remove duplicates
        if (!$dryRun) {
            foreach ($duplicateGroups as $normalizedPath => $group) {
                foreach ($group['duplicates'] as $duplicate) {
                    try {
                        // Delete size records first
                        try {
                            $sizeStmt = $pdo->prepare("DELETE FROM cms_media_sizes WHERE media_id = ?");
                            $sizeStmt->execute([$duplicate['id']]);
                        } catch (PDOException $e) {
                            // Size table might not exist, that's okay
                        }
                        
                        // Delete the duplicate record
                        $deleteStmt = $pdo->prepare("DELETE FROM cms_media WHERE id = ?");
                        $deleteStmt->execute([$duplicate['id']]);
                        $removedCount++;
                    } catch (PDOException $e) {
                        $errors[] = "Error removing duplicate ID {$duplicate['id']}: " . $e->getMessage();
                    }
                }
            }
        }
        
        // Ensure UNIQUE constraint exists
        if (!$dryRun) {
            try {
                // Check if UNIQUE constraint exists
                $constraintCheck = $pdo->query("SHOW INDEX FROM cms_media WHERE Key_name = 'unique_file_path'");
                if (!$constraintCheck->fetch()) {
                    // Remove any remaining duplicates first - use normalized paths in PHP
                    $normalizedPathMap = [];
                    $dupStmt = $pdo->query("SELECT id, file_path FROM cms_media ORDER BY id ASC");
                    while ($row = $dupStmt->fetch(PDO::FETCH_ASSOC)) {
                        $normalized = normalizeFilePath($row['file_path']);
                        if (!isset($normalizedPathMap[$normalized])) {
                            $normalizedPathMap[$normalized] = [];
                        }
                        $normalizedPathMap[$normalized][] = $row;
                    }
                    
                    // Find and remove duplicates
                    foreach ($normalizedPathMap as $normalizedPath => $entries) {
                        if (count($entries) > 1) {
                            // Keep the first one (lowest ID), delete the rest
                            $keepId = $entries[0]['id'];
                            $deleteIds = [];
                            for ($i = 1; $i < count($entries); $i++) {
                                $deleteIds[] = $entries[$i]['id'];
                            }
                            
                            if (!empty($deleteIds)) {
                                foreach ($deleteIds as $deleteId) {
                                    try {
                                        // Delete size records first
                                        try {
                                            $sizeStmt = $pdo->prepare("DELETE FROM cms_media_sizes WHERE media_id = ?");
                                            $sizeStmt->execute([$deleteId]);
                                        } catch (PDOException $e) {
                                            // Size table might not exist, that's okay
                                        }
                                        
                                        // Delete the duplicate record
                                        $delStmt = $pdo->prepare("DELETE FROM cms_media WHERE id = ?");
                                        $delStmt->execute([$deleteId]);
                                    } catch (PDOException $e) {
                                        $errors[] = "Error removing duplicate ID {$deleteId}: " . $e->getMessage();
                                    }
                                }
                            }
                        }
                    }
                    
                    // Now add UNIQUE constraint
                    try {
                        $pdo->exec("ALTER TABLE cms_media ADD UNIQUE KEY unique_file_path (file_path)");
                    } catch (PDOException $e) {
                        $errors[] = "Could not add UNIQUE constraint: " . $e->getMessage();
                    }
                }
            } catch (PDOException $e) {
                $errors[] = "Error checking UNIQUE constraint: " . $e->getMessage();
            }
        }
        
    } catch (Exception $e) {
        $errors[] = "Error finding duplicates: " . $e->getMessage();
    }
    
    return [
        'duplicate_groups' => count($duplicateGroups),
        'total_duplicates' => array_sum(array_map(function($group) {
            return count($group['duplicates']);
        }, $duplicateGroups)),
        'removed' => $removedCount,
        'errors' => $errors,
        'duplicate_groups_details' => $duplicateGroups
    ];
}

