<?php
/**
 * Ensure Advanced CMS Features Tables Exist
 * Helper function to auto-create tables if missing
 */
function ensureAdvancedTablesExist($pdo) {
    // Check if any table exists
    try {
        $pdo->query("SELECT 1 FROM cms_content_types LIMIT 1");
        return true; // Tables exist
    } catch (PDOException $e) {
        // Tables don't exist, create them
        $sqlFile = dirname(dirname(__DIR__)) . '/database/cms_advanced_features.sql';
        if (!file_exists($sqlFile)) {
            return false;
        }
        
        $sql = file_get_contents($sqlFile);
        
        // Remove comments
        $sql = preg_replace('/--.*$/m', '', $sql);
        
        // Split by semicolon, handling strings and parentheses
        $statements = [];
        $current = '';
        $depth = 0;
        $inString = false;
        $stringChar = '';
        
        for ($i = 0; $i < strlen($sql); $i++) {
            $char = $sql[$i];
            $prev = $i > 0 ? $sql[$i - 1] : '';
            
            // Track parentheses
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
            
            // End of statement
            if ($char === ';' && $depth === 0 && !$inString) {
                $stmt = trim($current);
                if (!empty($stmt) && strlen($stmt) > 10) {
                    $statements[] = $stmt;
                }
                $current = '';
            }
        }
        
        // Execute statements
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (empty($statement) || strlen($statement) < 10) {
                continue;
            }
            
            try {
                $pdo->exec($statement);
            } catch (PDOException $e) {
                // Ignore "already exists" errors
                if (strpos($e->getMessage(), 'already exists') === false && 
                    strpos($e->getMessage(), 'Duplicate entry') === false &&
                    strpos($e->getMessage(), 'Duplicate key') === false) {
                    // Log but continue
                    error_log("Table creation error: " . $e->getMessage());
                }
            }
        }
        
        // Verify tables were created
        try {
            $pdo->query("SELECT 1 FROM cms_content_types LIMIT 1");
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }
}

