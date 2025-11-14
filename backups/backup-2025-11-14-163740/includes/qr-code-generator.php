<?php
/**
 * QR Code Generator using phpqrcode library
 * Install via: composer require phpqrcode/phpqrcode
 * Or download: https://github.com/t0k4rt/phpqrcode
 */

// Check if phpqrcode library exists
$qrLibraryPath = __DIR__ . '/../vendor/phpqrcode/phpqrcode/qrlib.php';
$qrLibraryPathAlt = __DIR__ . '/../libs/phpqrcode/qrlib.php';

if (file_exists($qrLibraryPath)) {
    require_once $qrLibraryPath;
} elseif (file_exists($qrLibraryPathAlt)) {
    require_once $qrLibraryPathAlt;
} else {
    // Fallback: Use API-based QR generation
    define('QR_FALLBACK_API', true);
}

class QRCodeGenerator {
    /**
     * Generate QR code for receipt or technical report
     * @param string $url The URL to encode
     * @param string $type 'receipt' or 'technical'
     * @param int $size QR code size (default 200)
     * @return string Path to QR code image or data URI
     */
    public static function generate($url, $type = 'report', $size = 200) {
        // Ensure uploads directory exists
        $uploadsDir = __DIR__ . '/../uploads/';
        if (!file_exists($uploadsDir)) {
            @mkdir($uploadsDir, 0755, true);
        }
        
        // Ensure uploads/qrcodes directory exists
        $qrDir = $uploadsDir . 'qrcodes/';
        if (!file_exists($qrDir)) {
            @mkdir($qrDir, 0755, true);
        }
        
        // Check write permissions
        if (!is_writable($qrDir)) {
            @chmod($qrDir, 0755);
        }
        
        // Generate filename (simpler, without timestamp to reuse existing)
        $filename = $type . '_' . md5($url) . '.png';
        $filepath = $qrDir . $filename;
        
        // If file already exists and is readable, return it
        if (file_exists($filepath) && is_readable($filepath)) {
            return 'uploads/qrcodes/' . $filename;
        }
        
        // Check if library is available
        if (!defined('QR_FALLBACK_API')) {
            try {
                // Use phpqrcode library
                QRcode::png($url, $filepath, QR_ECLEVEL_M, 10, 2);
                if (file_exists($filepath) && filesize($filepath) > 0) {
                    return 'uploads/qrcodes/' . $filename;
                }
            } catch (Exception $e) {
                error_log("QR code generation error: " . $e->getMessage());
                // Fall through to API method
            }
        }
        
        // Fallback: Use API
        return self::generateViaAPI($url, $filepath);
    }
    
    /**
     * Generate QR code via API (fallback)
     */
    private static function generateViaAPI($url, $filepath) {
        // Try multiple QR code APIs
        $apis = [
            // Google Charts API (deprecated but still works)
            'https://chart.googleapis.com/chart?chs=200x200&cht=qr&chl=' . urlencode($url),
            // QR Server API (free, reliable)
            'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($url),
            // QuickChart API
            'https://quickchart.io/qr?text=' . urlencode($url) . '&size=200'
        ];
        
        foreach ($apis as $apiUrl) {
            $imageData = @file_get_contents($apiUrl, false, stream_context_create([
                'http' => [
                    'timeout' => 5,
                    'method' => 'GET',
                    'header' => 'User-Agent: ABBIS/3.2'
                ]
            ]));
            
            if ($imageData && strlen($imageData) > 100) { // Valid image should be > 100 bytes
                // Verify it's actually an image
                if (strpos($imageData, "\x89PNG") === 0 || strpos($imageData, "\xFF\xD8") === 0) {
                    if (@file_put_contents($filepath, $imageData)) {
                        @chmod($filepath, 0644);
                        return 'uploads/qrcodes/' . basename($filepath);
                    }
                }
            }
        }
        
        // Last resort: Return data URI (for inline display)
        if (isset($imageData) && !empty($imageData)) {
            return 'data:image/png;base64,' . base64_encode($imageData);
        }
        
        // Final fallback: return empty string (will be handled in display code)
        error_log("QR code generation failed for URL: " . $url);
        return '';
    }
    
    /**
     * Generate QR code data URI (inline, for direct embedding)
     */
    public static function generateDataURI($url, $size = 200) {
        if (!defined('QR_FALLBACK_API')) {
            try {
                ob_start();
                QRcode::png($url, null, QR_ECLEVEL_M, 10, 2);
                $imageData = ob_get_contents();
                ob_end_clean();
                return 'data:image/png;base64,' . base64_encode($imageData);
            } catch (Exception $e) {
                // Fall through
            }
        }
        
        // Use API
        $apiUrl = 'https://chart.googleapis.com/chart?chs=' . $size . 'x' . $size . '&cht=qr&chl=' . urlencode($url);
        $imageData = @file_get_contents($apiUrl);
        return $imageData ? 'data:image/png;base64,' . base64_encode($imageData) : '';
    }
}

