<?php
/**
 * Image Processing Engine - WordPress-like image handling
 * Creates multiple sizes, optimizes images, and manages media library
 */

class ImageResizer {
    private $rootPath;
    private $uploadDir;
    private $pdo;
    
    // Image size definitions (WordPress-like)
    private $imageSizes = [
        'thumbnail' => ['width' => 150, 'height' => 150, 'crop' => true],
        'medium' => ['width' => 300, 'height' => 300, 'crop' => false],
        'medium_large' => ['width' => 768, 'height' => 768, 'crop' => false],
        'large' => ['width' => 1024, 'height' => 1024, 'crop' => false],
        'full' => ['width' => 0, 'height' => 0, 'crop' => false] // Original size
    ];
    
    // Quality settings
    private $jpegQuality = 85;
    private $pngQuality = 9;
    private $webpQuality = 85;
    
    public function __construct($rootPath, $pdo = null) {
        $this->rootPath = $rootPath;
        $this->uploadDir = $rootPath . '/uploads/media/';
        $this->pdo = $pdo;
        
        // Ensure media sizes table exists
        $this->ensureMediaSizesTable();
    }
    
    /**
     * Ensure media_sizes table exists for tracking generated sizes
     */
    private function ensureMediaSizesTable() {
        if (!$this->pdo) {
            try {
                require_once $this->rootPath . '/config/database.php';
                $this->pdo = getDBConnection();
            } catch (Exception $e) {
                // PDO not available, skip table creation
                return;
            }
        }
        
        try {
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS cms_media_sizes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                media_id INT NOT NULL,
                size_name VARCHAR(50) NOT NULL,
                file_path VARCHAR(500) NOT NULL,
                width INT DEFAULT NULL,
                height INT DEFAULT NULL,
                file_size INT DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_media_id (media_id),
                INDEX idx_size_name (size_name),
                UNIQUE KEY unique_media_size (media_id, size_name),
                FOREIGN KEY (media_id) REFERENCES cms_media(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (PDOException $e) {
            // Table might already exist or foreign key constraint issue
            // Try without foreign key
            try {
                $this->pdo->exec("CREATE TABLE IF NOT EXISTS cms_media_sizes (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    media_id INT NOT NULL,
                    size_name VARCHAR(50) NOT NULL,
                    file_path VARCHAR(500) NOT NULL,
                    width INT DEFAULT NULL,
                    height INT DEFAULT NULL,
                    file_size INT DEFAULT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_media_id (media_id),
                    INDEX idx_size_name (size_name),
                    UNIQUE KEY unique_media_size (media_id, size_name)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            } catch (PDOException $e2) {
                // Ignore - table might already exist
            }
        }
    }
    
    /**
     * Check if GD library is available
     */
    public function isAvailable() {
        return extension_loaded('gd');
    }
    
    /**
     * Resize image to specified dimensions
     * @param string $sourcePath Full path to source image
     * @param string $destinationPath Full path to save resized image
     * @param int $maxWidth Maximum width
     * @param int $maxHeight Maximum height
     * @param bool $crop Whether to crop to exact dimensions
     * @return bool Success
     */
    public function resize($sourcePath, $destinationPath, $maxWidth, $maxHeight, $crop = false) {
        if (!file_exists($sourcePath) || !$this->isAvailable()) {
            return false;
        }
        
        $imageInfo = getimagesize($sourcePath);
        if (!$imageInfo) {
            return false;
        }
        
        list($originalWidth, $originalHeight, $imageType) = $imageInfo;
        
        // Calculate new dimensions
        if ($crop) {
            $ratio = max($maxWidth / $originalWidth, $maxHeight / $originalHeight);
            $newWidth = $maxWidth;
            $newHeight = $maxHeight;
            $srcX = ($originalWidth * $ratio - $maxWidth) / 2;
            $srcY = ($originalHeight * $ratio - $maxHeight) / 2;
        } else {
            $ratio = min($maxWidth / $originalWidth, $maxHeight / $originalHeight);
            $newWidth = round($originalWidth * $ratio);
            $newHeight = round($originalHeight * $ratio);
            $srcX = 0;
            $srcY = 0;
        }
        
        // Create image resource from source
        switch ($imageType) {
            case IMAGETYPE_JPEG:
                $sourceImage = imagecreatefromjpeg($sourcePath);
                break;
            case IMAGETYPE_PNG:
                $sourceImage = imagecreatefrompng($sourcePath);
                break;
            case IMAGETYPE_GIF:
                $sourceImage = imagecreatefromgif($sourcePath);
                break;
            case IMAGETYPE_WEBP:
                if (function_exists('imagecreatefromwebp')) {
                    $sourceImage = imagecreatefromwebp($sourcePath);
                } else {
                    return false;
                }
                break;
            default:
                return false;
        }
        
        if (!$sourceImage) {
            return false;
        }
        
        // Create new image
        $newImage = imagecreatetruecolor($newWidth, $newHeight);
        
        // Preserve transparency for PNG and GIF
        if ($imageType == IMAGETYPE_PNG || $imageType == IMAGETYPE_GIF) {
            imagealphablending($newImage, false);
            imagesavealpha($newImage, true);
            $transparent = imagecolorallocatealpha($newImage, 255, 255, 255, 127);
            imagefill($newImage, 0, 0, $transparent);
        }
        
        // Resize
        if ($crop) {
            imagecopyresampled($newImage, $sourceImage, 0, 0, $srcX, $srcY, $newWidth, $newHeight, $originalWidth, $originalHeight);
        } else {
            imagecopyresampled($newImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);
        }
        
        // Ensure directory exists
        $destDir = dirname($destinationPath);
        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }
        
        // Save resized image
        $quality = 85; // Good quality
        switch ($imageType) {
            case IMAGETYPE_JPEG:
                imagejpeg($newImage, $destinationPath, $quality);
                break;
            case IMAGETYPE_PNG:
                imagepng($newImage, $destinationPath, 9);
                break;
            case IMAGETYPE_GIF:
                imagegif($newImage, $destinationPath);
                break;
            case IMAGETYPE_WEBP:
                if (function_exists('imagewebp')) {
                    imagewebp($newImage, $destinationPath, $quality);
                }
                break;
        }
        
        imagedestroy($sourceImage);
        imagedestroy($newImage);
        
        return file_exists($destinationPath);
    }
    
    
    /**
     * Process uploaded image - resize, optimize, and generate sizes
     * @param string $sourcePath Temporary uploaded file path
     * @param string $destinationPath Destination path (relative)
     * @param int $mediaId Optional media ID for tracking sizes
     * @return array Result with success status and generated sizes
     */
    public function processUpload($sourcePath, $destinationPath, $mediaId = null) {
        $fullDestination = $this->rootPath . '/' . $destinationPath;
        $destDir = dirname($fullDestination);
        
        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }
        
        // Check if it's an image
        $imageInfo = @getimagesize($sourcePath);
        if (!$imageInfo) {
            // Not an image, just move it
            if (is_uploaded_file($sourcePath)) {
                move_uploaded_file($sourcePath, $fullDestination);
            } else {
                copy($sourcePath, $fullDestination);
            }
            return ['success' => true, 'is_image' => false, 'sizes' => []];
        }
        
        // Optimize and move original
        $this->optimizeImage($sourcePath, $fullDestination);
        
        // Generate sizes
        $generatedSizes = $this->generateSizes($destinationPath, $mediaId);
        
        return [
            'success' => true,
            'is_image' => true,
            'sizes' => $generatedSizes,
            'original_size' => filesize($fullDestination)
        ];
    }
    
    /**
     * Optimize image (compress, convert format if beneficial)
     * @param string $sourcePath Source image path
     * @param string $destinationPath Destination path
     * @return bool Success
     */
    public function optimizeImage($sourcePath, $destinationPath) {
        if (!file_exists($sourcePath) || !$this->isAvailable()) {
            // Fallback: just copy the file
            if (is_uploaded_file($sourcePath)) {
                return move_uploaded_file($sourcePath, $destinationPath);
            } else {
                return copy($sourcePath, $destinationPath);
            }
        }
        
        $imageInfo = @getimagesize($sourcePath);
        if (!$imageInfo) {
            return false;
        }
        
        list($width, $height, $imageType) = $imageInfo;
        
        // Create image resource
        $sourceImage = $this->createImageFromFile($sourcePath, $imageType);
        if (!$sourceImage) {
            // Fallback: just copy
            if (is_uploaded_file($sourcePath)) {
                return move_uploaded_file($sourcePath, $destinationPath);
            } else {
                return copy($sourcePath, $destinationPath);
            }
        }
        
        // Ensure directory exists
        $destDir = dirname($destinationPath);
        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }
        
        // Save optimized image
        $saved = $this->saveImage($sourceImage, $destinationPath, $imageType);
        
        imagedestroy($sourceImage);
        
        return $saved;
    }
    
    /**
     * Create image resource from file
     */
    private function createImageFromFile($filePath, $imageType) {
        switch ($imageType) {
            case IMAGETYPE_JPEG:
                return imagecreatefromjpeg($filePath);
            case IMAGETYPE_PNG:
                return imagecreatefrompng($filePath);
            case IMAGETYPE_GIF:
                return imagecreatefromgif($filePath);
            case IMAGETYPE_WEBP:
                if (function_exists('imagecreatefromwebp')) {
                    return imagecreatefromwebp($filePath);
                }
                break;
        }
        return false;
    }
    
    /**
     * Save image with optimization
     */
    private function saveImage($imageResource, $destinationPath, $imageType) {
        switch ($imageType) {
            case IMAGETYPE_JPEG:
                return imagejpeg($imageResource, $destinationPath, $this->jpegQuality);
            case IMAGETYPE_PNG:
                // Preserve transparency
                imagealphablending($imageResource, false);
                imagesavealpha($imageResource, true);
                return imagepng($imageResource, $destinationPath, $this->pngQuality);
            case IMAGETYPE_GIF:
                return imagegif($imageResource, $destinationPath);
            case IMAGETYPE_WEBP:
                if (function_exists('imagewebp')) {
                    return imagewebp($imageResource, $destinationPath, $this->webpQuality);
                }
                break;
        }
        return false;
    }
    
    /**
     * Generate multiple image sizes (simple version for portfolio)
     * @param string $imagePath Relative path from root
     * @param int $mediaId Optional media ID for tracking (ignored in simple version)
     * @return array Generated sizes with paths
     */
    public function generateSizes($imagePath, $mediaId = null) {
        $fullPath = $this->rootPath . '/' . $imagePath;
        
        if (!file_exists($fullPath)) {
            return [];
        }
        
        $imageInfo = @getimagesize($fullPath);
        if (!$imageInfo) {
            return []; // Not an image
        }
        
        list($originalWidth, $originalHeight) = $imageInfo;
        
        $pathInfo = pathinfo($imagePath);
        $dir = $pathInfo['dirname'];
        $filename = $pathInfo['filename'];
        $extension = $pathInfo['extension'];
        
        $generated = [];
        
        // Generate each size
        foreach ($this->imageSizes as $sizeName => $size) {
            if ($sizeName === 'full') {
                // Full size is the original
                $generated['full'] = [
                    'path' => $imagePath,
                    'width' => $originalWidth,
                    'height' => $originalHeight,
                    'file_size' => filesize($fullPath)
                ];
                continue;
            }
            
            // Skip if original is smaller than requested size
            if ($originalWidth <= $size['width'] && $originalHeight <= $size['height'] && !$size['crop']) {
                // Use original for this size
                $generated[$sizeName] = [
                    'path' => $imagePath,
                    'width' => $originalWidth,
                    'height' => $originalHeight,
                    'file_size' => filesize($fullPath)
                ];
                continue;
            }
            
            // Use simple directory structure: dir/sizeName/filename.ext
            $sizeDir = $this->rootPath . '/' . $dir . '/' . $sizeName;
            if (!is_dir($sizeDir)) {
                mkdir($sizeDir, 0755, true);
            }
            
            $sizeFilename = $filename . '.' . $extension;
            $sizePath = $sizeDir . '/' . $sizeFilename;
            $relativePath = $dir . '/' . $sizeName . '/' . $sizeFilename;
            
            if ($this->resize($fullPath, $sizePath, $size['width'], $size['height'], $size['crop'])) {
                $sizeInfo = @getimagesize($sizePath);
                $generated[$sizeName] = [
                    'path' => $relativePath,
                    'width' => $sizeInfo ? $sizeInfo[0] : $size['width'],
                    'height' => $sizeInfo ? $sizeInfo[1] : $size['height'],
                    'file_size' => file_exists($sizePath) ? filesize($sizePath) : 0
                ];
                
                // Register in database if media ID provided
                if ($mediaId && $this->pdo) {
                    $this->registerSize($mediaId, $sizeName, $relativePath, $generated[$sizeName]['width'], $generated[$sizeName]['height'], $generated[$sizeName]['file_size']);
                }
            }
        }
        
        return $generated;
    }
    
    /**
     * Register image size in database
     */
    private function registerSize($mediaId, $sizeName, $filePath, $width, $height, $fileSize) {
        if (!$this->pdo) {
            return false;
        }
        
        try {
            $stmt = $this->pdo->prepare("INSERT INTO cms_media_sizes (media_id, size_name, file_path, width, height, file_size) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE file_path=VALUES(file_path), width=VALUES(width), height=VALUES(height), file_size=VALUES(file_size)");
            $stmt->execute([$mediaId, $sizeName, $filePath, $width, $height, $fileSize]);
            return true;
        } catch (PDOException $e) {
            error_log("Failed to register image size: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get image URL with size (updated to use new size structure)
     */
    public function getImageUrl($imagePath, $size = 'full', $baseUrl = '') {
        if ($size === 'full' || empty($size)) {
            return $baseUrl . '/' . $imagePath;
        }
        
        $pathInfo = pathinfo($imagePath);
        $dir = $pathInfo['dirname'];
        $filename = $pathInfo['filename'];
        $extension = $pathInfo['extension'];
        
        // Simple structure: dir/sizeName/filename.ext
        $sizePath = $dir . '/' . $size . '/' . $filename . '.' . $extension;
        $fullSizePath = $this->rootPath . '/' . $sizePath;
        
        // If size exists, return it; otherwise return original
        if (file_exists($fullSizePath)) {
            return $baseUrl . '/' . $sizePath;
        }
        
        // Fallback to original
        return $baseUrl . '/' . $imagePath;
    }
    
    /**
     * Get image sizes from database
     */
    public function getImageSizes($mediaId) {
        if (!$this->pdo || !$mediaId) {
            return [];
        }
        
        try {
            $stmt = $this->pdo->prepare("SELECT size_name, file_path, width, height, file_size FROM cms_media_sizes WHERE media_id = ?");
            $stmt->execute([$mediaId]);
            $sizes = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $sizes[$row['size_name']] = $row;
            }
            return $sizes;
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Process and register image in media library
     * This is the main entry point for processing uploaded images
     */
    public function processAndRegister($sourcePath, $originalName, $fileType, $fileSize, $uploadedBy = null) {
        // Generate unique filename
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $uniqueFilename = uniqid() . '_' . time() . '.' . $ext;
        $relativePath = 'uploads/media/' . $uniqueFilename;
        $fullPath = $this->rootPath . '/' . $relativePath;
        
        // Ensure upload directory exists
        $uploadDir = dirname($fullPath);
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Process image (resize, optimize, generate sizes)
        $result = $this->processUpload($sourcePath, $relativePath);
        
        if (!$result['success']) {
            return ['success' => false, 'error' => 'Failed to process image'];
        }
        
        // Register in media library
        if ($this->pdo) {
            try {
                // Ensure table exists
                try {
                    $this->pdo->query("SELECT 1 FROM cms_media LIMIT 1");
                } catch (PDOException $e) {
                    $this->pdo->exec("CREATE TABLE IF NOT EXISTS cms_media (
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
                
                $stmt = $this->pdo->prepare("INSERT INTO cms_media (filename, original_name, file_path, file_type, file_size, uploaded_by, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                $finalFileSize = file_exists($fullPath) ? filesize($fullPath) : $fileSize;
                $stmt->execute([$uniqueFilename, $originalName, $relativePath, $fileType, $finalFileSize, $uploadedBy]);
                $mediaId = $this->pdo->lastInsertId();
                
                // Verify insertion
                if (!$mediaId || $mediaId <= 0) {
                    // Try to get ID from rowCount or check if it exists
                    $checkStmt = $this->pdo->prepare("SELECT id FROM cms_media WHERE file_path = ? LIMIT 1");
                    $checkStmt->execute([$relativePath]);
                    $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
                    if ($existing) {
                        $mediaId = $existing['id'];
                    } else {
                        throw new Exception('Failed to get media ID after insertion');
                    }
                }
                
                // If sizes were generated, register them
                if ($result['is_image'] && !empty($result['sizes']) && $mediaId) {
                    foreach ($result['sizes'] as $sizeName => $sizeData) {
                        if ($sizeName !== 'full' && !empty($sizeData['path'])) {
                            try {
                                $this->registerSize($mediaId, $sizeName, $sizeData['path'], $sizeData['width'] ?? null, $sizeData['height'] ?? null, $sizeData['file_size'] ?? null);
                            } catch (Exception $e) {
                                // Log but don't fail - sizes are optional
                                error_log("Failed to register size {$sizeName}: " . $e->getMessage());
                            }
                        }
                    }
                }
                
                return [
                    'success' => true,
                    'media_id' => $mediaId,
                    'file_path' => $relativePath,
                    'sizes' => $result['sizes'] ?? []
                ];
            } catch (PDOException $e) {
                // If DB insert fails, return error but keep file path for manual registration
                error_log("ImageResizer DB error: " . $e->getMessage());
                return [
                    'success' => false,
                    'error' => 'Database registration failed: ' . $e->getMessage(),
                    'file_path' => $relativePath,
                    'sizes' => $result['sizes'] ?? []
                ];
            } catch (Exception $e) {
                error_log("ImageResizer error: " . $e->getMessage());
                return [
                    'success' => false,
                    'error' => 'Registration failed: ' . $e->getMessage(),
                    'file_path' => $relativePath,
                    'sizes' => $result['sizes'] ?? []
                ];
            }
        } else {
            // No PDO connection - return file path for manual registration
            return [
                'success' => true,
                'file_path' => $relativePath,
                'sizes' => $result['sizes'] ?? [],
                'warning' => 'No database connection - file processed but not registered'
            ];
        }
    }
}

