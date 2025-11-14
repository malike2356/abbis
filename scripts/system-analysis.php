<?php
/**
 * Comprehensive System Analysis Script
 * Analyzes the entire ABBIS system for errors, dependencies, and interconnections
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

class SystemAnalyzer {
    private $basePath;
    private $issues = [];
    private $warnings = [];
    private $info = [];
    private $dependencies = [];
    private $apiEndpoints = [];
    private $databaseTables = [];
    private $calculations = [];
    
    public function __construct($basePath) {
        $this->basePath = $basePath;
    }
    
    public function analyze() {
        echo "🔍 Starting Comprehensive System Analysis...\n\n";
        
        $this->analyzeDatabase();
        $this->analyzeModules();
        $this->analyzeAPIs();
        $this->analyzeIncludes();
        $this->analyzeDependencies();
        $this->analyzeCalculations();
        $this->analyzeEmailSystem();
        $this->analyzeInterconnections();
        
        $this->generateReport();
    }
    
    private function analyzeDatabase() {
        echo "📊 Analyzing Database...\n";
        $pdo = getDBConnection();
        
        // Get all tables
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        $this->databaseTables = $tables;
        
        // Check for missing foreign keys
        foreach ($tables as $table) {
            try {
                $createTableResult = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_ASSOC);
                $createTable = $createTableResult['Create Table'] ?? '';
                if (empty($createTable) || strpos($createTable, 'FOREIGN KEY') === false) {
                // Check if table should have foreign keys
                    if (in_array($table, ['field_reports', 'payroll_entries', 'loans', 'rig_locations'])) {
                        $this->warnings[] = "Table '{$table}' may be missing foreign key constraints";
                    }
                }
            } catch (PDOException $e) {
                // Skip if table doesn't exist or can't be accessed
            }
        }
        
        // Check for orphaned records
        $this->checkOrphanedRecords($pdo);
        
        echo "   ✓ Database analysis complete\n";
    }
    
    private function checkOrphanedRecords($pdo) {
        // Check field_reports without valid rig_id
        try {
            $orphaned = $pdo->query("
                SELECT COUNT(*) as count 
                FROM field_reports fr 
                LEFT JOIN rigs r ON fr.rig_id = r.id 
                WHERE fr.rig_id IS NOT NULL AND r.id IS NULL
            ")->fetch();
            if ($orphaned['count'] > 0) {
                $this->issues[] = "Found {$orphaned['count']} field reports with invalid rig_id";
            }
        } catch (Exception $e) {
            $this->warnings[] = "Could not check orphaned field_reports: " . $e->getMessage();
        }
        
        // Check payroll_entries without valid worker_id
        try {
            $orphaned = $pdo->query("
                SELECT COUNT(*) as count 
                FROM payroll_entries pe 
                LEFT JOIN workers w ON pe.worker_id = w.id 
                WHERE pe.worker_id IS NOT NULL AND w.id IS NULL
            ")->fetch();
            if ($orphaned['count'] > 0) {
                $this->issues[] = "Found {$orphaned['count']} payroll entries with invalid worker_id";
            }
        } catch (Exception $e) {
            // Table might not exist
        }
    }
    
    private function analyzeModules() {
        echo "📁 Analyzing Modules...\n";
        $modulesPath = $this->basePath . '/modules';
        $modules = glob($modulesPath . '/*.php');
        
        foreach ($modules as $module) {
            $filename = basename($module);
            $content = file_get_contents($module);
            
            // Check for required includes
            if (strpos($content, 'require_once') === false && strpos($content, 'require') === false) {
                $this->warnings[] = "Module '{$filename}' may be missing required includes";
            }
            
            // Check for authentication
            if (strpos($content, 'requireAuth') === false && strpos($content, 'auth') === false) {
                if (!in_array($filename, ['login.php', 'logout.php', 'cookie-policy.php', 'privacy-policy.php', 'terms.php', 'dpa.php'])) {
                    $this->issues[] = "Module '{$filename}' may be missing authentication check";
                }
            }
            
            // Check for CSRF protection on POST
            if (strpos($content, 'POST') !== false && strpos($content, 'CSRF') === false && strpos($content, 'csrf') === false) {
                $this->warnings[] = "Module '{$filename}' may be missing CSRF protection";
            }
            
            // Extract API calls
            preg_match_all('/fetch\([\'"]([^\'"]+)[\'"]\)/', $content, $matches);
            foreach ($matches[1] as $url) {
                if (strpos($url, 'api/') !== false) {
                    $this->dependencies[] = [
                        'from' => $filename,
                        'to' => $url,
                        'type' => 'api_call'
                    ];
                }
            }
        }
        
        echo "   ✓ Module analysis complete\n";
    }
    
    private function analyzeAPIs() {
        echo "🔌 Analyzing API Endpoints...\n";
        $apiPath = $this->basePath . '/api';
        $apis = glob($apiPath . '/*.php');
        
        foreach ($apis as $api) {
            $filename = basename($api);
            $content = file_get_contents($api);
            
            $this->apiEndpoints[] = $filename;
            
            // Check for authentication
            if (strpos($content, 'requireAuth') === false && strpos($content, 'auth') === false) {
                if (!in_array($filename, ['social-auth.php', 'password-recovery.php'])) {
                    $this->issues[] = "API '{$filename}' may be missing authentication";
                }
            }
            
            // Check for error handling
            if (strpos($content, 'try') === false && strpos($content, 'catch') === false) {
                $this->warnings[] = "API '{$filename}' may be missing error handling";
            }
            
            // Check for JSON response
            if (strpos($content, 'Content-Type: application/json') === false && 
                strpos($content, 'json_encode') === false) {
                $this->warnings[] = "API '{$filename}' may not be returning JSON";
            }
        }
        
        echo "   ✓ API analysis complete\n";
    }
    
    private function analyzeIncludes() {
        echo "📚 Analyzing Includes...\n";
        $includesPath = $this->basePath . '/includes';
        $includes = glob($includesPath . '/*.php');
        
        foreach ($includes as $include) {
            $filename = basename($include);
            $content = file_get_contents($include);
            
            // Check for function definitions
            preg_match_all('/function\s+(\w+)\s*\(/', $content, $matches);
            foreach ($matches[1] as $func) {
                if (strpos($func, '__') === 0) {
                    continue; // Skip magic methods
                }
                $this->info[] = "Function found: {$func}() in includes/{$filename}";
            }
        }
        
        echo "   ✓ Includes analysis complete\n";
    }
    
    private function analyzeDependencies() {
        echo "🔗 Analyzing Dependencies...\n";
        
        // Check if modules reference non-existent APIs
        foreach ($this->dependencies as $dep) {
            $apiFile = $this->basePath . '/' . $dep['to'];
            if (!file_exists($apiFile)) {
                $this->issues[] = "Module '{$dep['from']}' references non-existent API: {$dep['to']}";
            }
        }
        
        echo "   ✓ Dependency analysis complete\n";
    }
    
    private function analyzeCalculations() {
        echo "🧮 Analyzing Calculations...\n";
        $pdo = getDBConnection();
        
        // Check field report calculations
        try {
            // Use actual column names from field_reports table
            $reports = $pdo->query("
                SELECT id, total_income, total_expenses, net_profit 
                FROM field_reports 
                WHERE total_income > 0 OR total_expenses > 0 
                LIMIT 10
            ")->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($reports as $report) {
                if ($report['total_income'] > 0 && $report['total_expenses'] > 0) {
                    $expectedProfit = $report['total_income'] - $report['total_expenses'];
                    if (abs($expectedProfit - ($report['net_profit'] ?? 0)) > 0.01) {
                        $this->warnings[] = "Field report #{$report['id']} has inconsistent net_profit calculation";
                    }
                }
            }
        } catch (Exception $e) {
            $this->warnings[] = "Could not verify calculation consistency: " . $e->getMessage();
        }
        
        echo "   ✓ Calculation analysis complete\n";
    }
    
    private function analyzeEmailSystem() {
        echo "📧 Analyzing Email System...\n";
        
        // Check email.php exists
        if (!file_exists($this->basePath . '/includes/email.php')) {
            $this->issues[] = "Email system file missing: includes/email.php";
        } else {
            $emailContent = file_get_contents($this->basePath . '/includes/email.php');
            
            // Check for email queue processing
            if (strpos($emailContent, 'process-email') === false) {
                $this->warnings[] = "Email system may not have queue processing";
            }
            
            // Check for SMTP configuration
            if (strpos($emailContent, 'smtp') === false && strpos($emailContent, 'SMTP') === false) {
                $this->warnings[] = "Email system may not support SMTP configuration";
            }
        }
        
        // Check email queue processor
        if (!file_exists($this->basePath . '/api/process-emails.php')) {
            $this->warnings[] = "Email queue processor missing: api/process-emails.php";
        }
        
        echo "   ✓ Email system analysis complete\n";
    }
    
    private function analyzeInterconnections() {
        echo "🌐 Analyzing System Interconnections...\n";
        
        // Check field reports -> clients connection
        $pdo = getDBConnection();
        try {
            $connected = $pdo->query("
                SELECT COUNT(*) as count 
                FROM field_reports fr 
                INNER JOIN clients c ON fr.client_id = c.id
            ")->fetch();
            $this->info[] = "Field reports connected to clients: {$connected['count']} records";
        } catch (Exception $e) {
            $this->warnings[] = "Could not verify field_reports -> clients connection";
        }
        
        // Check payroll -> workers connection
        try {
            $connected = $pdo->query("
                SELECT COUNT(*) as count 
                FROM payroll_entries pe 
                INNER JOIN workers w ON pe.worker_id = w.id
            ")->fetch();
            $this->info[] = "Payroll entries connected to workers: {$connected['count']} records";
        } catch (Exception $e) {
            // Table might not exist
        }
        
        echo "   ✓ Interconnection analysis complete\n";
    }
    
    private function generateReport() {
        echo "\n" . str_repeat("=", 80) . "\n";
        echo "📋 SYSTEM ANALYSIS REPORT\n";
        echo str_repeat("=", 80) . "\n\n";
        
        echo "🔴 CRITICAL ISSUES (" . count($this->issues) . ")\n";
        echo str_repeat("-", 80) . "\n";
        if (empty($this->issues)) {
            echo "   ✓ No critical issues found!\n";
        } else {
            foreach ($this->issues as $issue) {
                echo "   ✗ {$issue}\n";
            }
        }
        echo "\n";
        
        echo "🟡 WARNINGS (" . count($this->warnings) . ")\n";
        echo str_repeat("-", 80) . "\n";
        if (empty($this->warnings)) {
            echo "   ✓ No warnings!\n";
        } else {
            foreach ($this->warnings as $warning) {
                echo "   ⚠ {$warning}\n";
            }
        }
        echo "\n";
        
        echo "ℹ️  INFORMATION\n";
        echo str_repeat("-", 80) . "\n";
        echo "   • Database Tables: " . count($this->databaseTables) . "\n";
        echo "   • API Endpoints: " . count($this->apiEndpoints) . "\n";
        echo "   • Dependencies: " . count($this->dependencies) . "\n";
        if (!empty($this->info)) {
            foreach (array_slice($this->info, 0, 10) as $info) {
                echo "   • {$info}\n";
            }
            if (count($this->info) > 10) {
                echo "   • ... and " . (count($this->info) - 10) . " more\n";
            }
        }
        echo "\n";
        
        // Save detailed report
        $report = [
            'timestamp' => date('Y-m-d H:i:s'),
            'issues' => $this->issues,
            'warnings' => $this->warnings,
            'info' => $this->info,
            'database_tables' => $this->databaseTables,
            'api_endpoints' => $this->apiEndpoints,
            'dependencies' => $this->dependencies
        ];
        
        file_put_contents(
            $this->basePath . '/logs/system-analysis-' . date('Y-m-d') . '.json',
            json_encode($report, JSON_PRETTY_PRINT)
        );
        
        echo "📄 Detailed report saved to: logs/system-analysis-" . date('Y-m-d') . ".json\n";
        echo "\n" . str_repeat("=", 80) . "\n";
    }
}

// Run analysis
$analyzer = new SystemAnalyzer(__DIR__ . '/..');
$analyzer->analyze();

