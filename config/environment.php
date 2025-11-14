<?php
/**
 * Environment bootstrap and global URL helpers.
 */

if (!defined('APP_ENV')) {
    $env = getenv('APP_ENV');
    define('APP_ENV', $env ? strtolower($env) : 'development');
}

if (!defined('DEBUG')) {
    define('DEBUG', APP_ENV === 'development');
}

if (!function_exists('env_normalize_base_path')) {
    function env_normalize_base_path(?string $path): string
    {
        if ($path === null) {
            return '';
        }

        $trimmed = trim($path);
        if ($trimmed === '' || $trimmed === '/') {
            return '';
        }

        $normalized = '/' . trim($trimmed, '/');

        return preg_replace('#/{2,}#', '/', $normalized);
    }
}

if (!function_exists('env_detect_scheme')) {
    function env_detect_scheme(): string
    {
        $forwardedProto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
        if ($forwardedProto !== '') {
            $proto = strtolower(trim(explode(',', $forwardedProto)[0]));
            return $proto === 'https' ? 'https' : 'http';
        }

        $requestScheme = $_SERVER['REQUEST_SCHEME'] ?? '';
        if ($requestScheme !== '') {
            return strtolower($requestScheme) === 'https' ? 'https' : 'http';
        }

        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return 'https';
        }

        if (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443) {
            return 'https';
        }

        return 'http';
    }
}

if (!function_exists('env_detect_host')) {
    function env_detect_host(): ?string
    {
        $candidates = [];

        if (!empty($_SERVER['HTTP_HOST'])) {
            $candidates[] = $_SERVER['HTTP_HOST'];
        }

        if (!empty($_SERVER['HTTP_X_FORWARDED_HOST'])) {
            $forwarded = trim($_SERVER['HTTP_X_FORWARDED_HOST']);
            if ($forwarded !== '') {
                $candidates[] = explode(',', $forwarded)[0];
            }
        }

        if (!empty($_SERVER['SERVER_NAME'])) {
            $candidates[] = $_SERVER['SERVER_NAME'];
        }

        if (!empty($_SERVER['SERVER_ADDR'])) {
            $candidates[] = $_SERVER['SERVER_ADDR'];
        }

        foreach ($candidates as $host) {
            $host = trim($host);
            if ($host !== '') {
                return $host;
            }
        }

        return null;
    }
}

if (!function_exists('env_detect_base_path')) {
    function env_detect_base_path(): string
    {
        $projectRoot = realpath(__DIR__ . '/..');
        if ($projectRoot === false) {
            return '';
        }

        $docRoots = [];

        if (!empty($_SERVER['DOCUMENT_ROOT'])) {
            $docRoots[] = $_SERVER['DOCUMENT_ROOT'];
        }

        if (!empty($_SERVER['CONTEXT_DOCUMENT_ROOT'])) {
            $docRoots[] = $_SERVER['CONTEXT_DOCUMENT_ROOT'];
        }

        foreach ($docRoots as $root) {
            $rootReal = realpath($root);
            if ($rootReal && strpos($projectRoot, $rootReal) === 0) {
                $relative = trim(str_replace('\\', '/', substr($projectRoot, strlen($rootReal))), '/');
                $basePath = $relative !== '' ? '/' . $relative : '';

                return env_normalize_base_path($basePath);
            }
        }

        return '';
    }
}

if (!function_exists('env_detect_app_context')) {
    function env_detect_app_context(): ?array
    {
        $host = env_detect_host();
        if (!$host) {
            return null;
        }

        $scheme = env_detect_scheme();
        $basePath = env_normalize_base_path(env_detect_base_path());

        $origin = rtrim($scheme . '://' . $host, '/');
        $url = $basePath !== '' ? $origin . $basePath : $origin;

        return [
            'url' => rtrim($url, '/'),
            'base_path' => $basePath,
        ];
    }
}

$detectedContext = env_detect_app_context();

if (!defined('APP_URL')) {
    // First, check for deployment.php (highest priority for production)
    $deploymentConfig = __DIR__ . '/deployment.php';
    if (file_exists($deploymentConfig)) {
        require_once $deploymentConfig;
    }
    
    // If still not defined, check environment variable
    if (!defined('APP_URL')) {
        $envUrl = getenv('APP_URL');

        if ($envUrl) {
            $appUrl = rtrim($envUrl, '/');
        } elseif ($detectedContext) {
            $appUrl = $detectedContext['url'];
        } else {
            $defaults = [
                'production'  => 'https://kariboreholes.com',
                'staging'     => 'https://abbis.veloxpsi.com',
                'development' => 'http://localhost:8080/abbis3.2',
            ];

            $appUrl = $defaults[APP_ENV] ?? $defaults['development'];
        }

        define('APP_URL', rtrim($appUrl, '/'));
    }
}

if (!defined('APP_BASE_PATH')) {
    $basePath = $detectedContext['base_path'] ?? env_normalize_base_path(parse_url(APP_URL, PHP_URL_PATH) ?? '');

    define('APP_BASE_PATH', $basePath);
}

if (!function_exists('app_base_path')) {
    function app_base_path(): string
    {
        return APP_BASE_PATH;
    }
}

if (!function_exists('app_url')) {
    function app_url(string $path = ''): string
    {
        $base = rtrim(APP_URL, '/');
        if ($path === '' || $path === '/') {
            return $base . ($path === '/' ? '/' : '');
        }

        return $base . '/' . ltrim($path, '/');
    }
}

if (!function_exists('app_path')) {
    function app_path(string $path = ''): string
    {
        $base = APP_BASE_PATH;
        $trimmed = ltrim($path, '/');

        if ($trimmed === '') {
            return $base !== '' ? $base : '/';
        }

        $combined = ($base !== '' ? $base . '/' : '/') . $trimmed;

        if (substr($path, -1) === '/' && substr($combined, -1) !== '/') {
            $combined .= '/';
        }

        // Ensure no duplicate slashes (other than the scheme separator)
        return preg_replace('#/{2,}#', '/', $combined);
    }
}

if (!function_exists('asset_url')) {
    function asset_url(string $path = ''): string
    {
        return app_url($path);
    }
}

