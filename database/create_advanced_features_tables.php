<?php
/**
 * Create Advanced CMS Features Tables
 * Run this script to create all required database tables
 */
require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$pdo = getDBConnection();

echo "🚀 Creating Advanced CMS Features Tables...\n\n";

// Read SQL file
$sqlFile = __DIR__ . '/cms_advanced_features.sql';
if (!file_exists($sqlFile)) {
    die("❌ SQL file not found: $sqlFile\n");
}

$sql = file_get_contents($sqlFile);

// Remove comments
$sql = preg_replace('/--.*$/m', '', $sql);

// Split by semicolon followed by newline or end of string
// But be careful - we need to handle semicolons inside strings
$statements = [];
$current = '';
$depth = 0;
$inString = false;
$stringChar = '';

for ($i = 0; $i < strlen($sql); $i++) {
    $char = $sql[$i];
    $prev = $i > 0 ? $sql[$i - 1] : '';
    
    // Track parentheses for depth
    if ($char === '(' && !$inString) {
        $depth++;
    } elseif ($char === ')' && !$inString) {
        $depth--;
    }
    
    // Track strings
    if (($char === "'" || $char === '"') && $prev !== '\\') {
        if (!$inString) {
            $inString = true;
            $stringChar = $char;
        } elseif ($char === $stringChar) {
            $inString = false;
            $stringChar = '';
        }
    }
    
    $current .= $char;
    
    // End of statement: semicolon at depth 0, not in string
    if ($char === ';' && $depth === 0 && !$inString) {
        $stmt = trim($current);
        if (!empty($stmt) && strlen($stmt) > 10) {
            $statements[] = $stmt;
        }
        $current = '';
    }
}

$successCount = 0;
$errorCount = 0;

foreach ($statements as $statement) {
    $statement = trim($statement);
    if (empty($statement) || strlen($statement) < 10) {
        continue;
    }
    
    try {
        $pdo->exec($statement);
        $successCount++;
        
        // Extract table name for feedback
        if (preg_match('/CREATE TABLE (?:IF NOT EXISTS )?`?(\w+)`?/i', $statement, $matches)) {
            echo "✅ Created table: {$matches[1]}\n";
        } elseif (preg_match('/INSERT (?:IGNORE )?INTO `?(\w+)`?/i', $statement, $matches)) {
            echo "✅ Inserted data into: {$matches[1]}\n";
        }
    } catch (PDOException $e) {
        // Ignore "table already exists" errors
        if (strpos($e->getMessage(), 'already exists') === false && 
            strpos($e->getMessage(), 'Duplicate entry') === false &&
            strpos($e->getMessage(), 'Duplicate key') === false) {
            $errorCount++;
            echo "⚠️  Error: " . $e->getMessage() . "\n";
            if (preg_match('/CREATE TABLE (?:IF NOT EXISTS )?`?(\w+)`?/i', $statement, $matches)) {
                echo "   Table: {$matches[1]}\n";
            }
            echo "   Statement preview: " . substr($statement, 0, 150) . "...\n\n";
        } else {
            // Table already exists, that's okay
            if (preg_match('/CREATE TABLE (?:IF NOT EXISTS )?`?(\w+)`?/i', $statement, $matches)) {
                echo "ℹ️  Table already exists: {$matches[1]}\n";
            }
        }
    }
}

echo "\n";
echo "✅ Successfully processed: $successCount statements\n";
if ($errorCount > 0) {
    echo "⚠️  Errors encountered: $errorCount\n";
} else {
    echo "🎉 All tables created successfully!\n";
}
