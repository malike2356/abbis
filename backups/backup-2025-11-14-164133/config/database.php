<?php
/**
 * Database Configuration for ABBIS v3.2 - XAMPP + SQLite fallback
 */

require_once __DIR__ . '/environment.php';

$envDriver = getenv('DB_CONNECTION');
$useSqliteFlag = getenv('USE_SQLITE');
$defaultDriver = $envDriver ?: ($useSqliteFlag ? 'sqlite' : 'mysql');

if (!defined('DB_CONNECTION')) {
    define('DB_CONNECTION', strtolower($defaultDriver));
}

if (DB_CONNECTION === 'sqlite') {
    $sqlitePath = getenv('DB_DATABASE');
    if (!$sqlitePath) {
        $sqlitePath = __DIR__ . '/../storage/sqlite/abbis.sqlite';
    }
    if (!defined('DB_SQLITE_PATH')) {
        define('DB_SQLITE_PATH', $sqlitePath);
    }
    if (!defined('DB_CHARSET')) {
        define('DB_CHARSET', 'utf8');
    }
} else {
    // Database configuration - XAMPP Defaults
    if (!defined('DB_HOST')) {
        define('DB_HOST', 'localhost');
    }
    if (!defined('DB_USER')) {
        define('DB_USER', 'root');
    }
    if (!defined('DB_PASS')) {
        define('DB_PASS', ''); // XAMPP empty password
    }
    if (!defined('DB_NAME')) {
        define('DB_NAME', 'abbis_3_2');
    }
    if (!defined('DB_CHARSET')) {
        define('DB_CHARSET', 'utf8mb4');
    }
}

// Application configuration (only define if not already defined)
if (!defined('APP_NAME')) {
    define('APP_NAME', 'ABBIS v3.2');
}
if (!defined('APP_VERSION')) {
    define('APP_VERSION', '3.2.0');
}
if (!defined('UPLOAD_PATH')) {
    define('UPLOAD_PATH', __DIR__ . '/../uploads/');
}

// Create database connection
function getDBConnection() {
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    if (DB_CONNECTION === 'sqlite') {
        $dbPath = DB_SQLITE_PATH;
        $dir = dirname($dbPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $pdo = new PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys = ON');
        return $pdo;
    }

    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        die("Database connection failed: " . $e->getMessage() .
            " - Check if database '" . DB_NAME . "' exists and credentials are correct.");
    }
}

// Auto-create database if it doesn't exist (optional)
function createDatabaseIfNotExists() {
    if (DB_CONNECTION === 'sqlite') {
        $dbPath = DB_SQLITE_PATH;
        $dir = dirname($dbPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        if (!file_exists($dbPath)) {
            touch($dbPath);
        }
        return true;
    }

    try {
        $temp_pdo = new PDO('mysql:host=' . DB_HOST, DB_USER, DB_PASS);
        $temp_pdo->exec('CREATE DATABASE IF NOT EXISTS ' . DB_NAME . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        return true;
    } catch (PDOException $e) {
        error_log('Could not create database: ' . $e->getMessage());
        return false;
    }
}
?>