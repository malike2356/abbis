<?php

require_once __DIR__ . '/../ContextBuilderInterface.php';
require_once __DIR__ . '/../ContextSlice.php';
require_once __DIR__ . '/../../../../config/database.php';

class OrganisationContextBuilder implements ContextBuilderInterface
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?: getDBConnection();
    }

    public function getKey(): string
    {
        return 'organisation';
    }

    public function supports(array $options): bool
    {
        return true;
    }

    public function build(array $options): array
    {
        $org = null;

        // Try to get organisation info from system_config table first (most common)
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    MAX(CASE WHEN config_key = 'company_name' THEN config_value END) as company_name,
                    MAX(CASE WHEN config_key = 'company_email' THEN config_value END) as contact_email,
                    MAX(CASE WHEN config_key = 'company_contact' THEN config_value END) as contact_phone,
                    MAX(CASE WHEN config_key = 'company_address' THEN config_value END) as address
                FROM system_config
                WHERE config_key IN ('company_name', 'company_email', 'company_contact', 'company_address')
            ");
            $stmt->execute();
            $org = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Clean up the data - trim strings and check if we have valid data
            if ($org) {
                $hasValidData = false;
                // Trim string values and check for non-empty values
                foreach ($org as $key => $value) {
                    if ($value !== null && $value !== '') {
                        if (is_string($value)) {
                            $org[$key] = trim($value);
                            if ($org[$key] !== '') {
                                $hasValidData = true;
                            }
                        } else {
                            $hasValidData = true;
                        }
                    } else {
                        // Remove null/empty values
                        unset($org[$key]);
                    }
                }
                // If we don't have any valid data, treat as not found
                if (!$hasValidData) {
                    $org = null;
                }
            }
        } catch (PDOException $e) {
            // Table might not exist, try next option
            error_log('[AI Context] system_config query failed in OrganisationContextBuilder: ' . $e->getMessage());
            $org = null;
        }

        // Try company_profile table if it exists (fallback)
        if (!$org) {
            try {
                // Check if table exists first
                $checkStmt = $this->pdo->query("SHOW TABLES LIKE 'company_profile'");
                if ($checkStmt && $checkStmt->rowCount() > 0) {
                    $stmt = $this->pdo->query("SELECT company_name, contact_email, contact_phone, industry, country, timezone FROM company_profile LIMIT 1");
                    $org = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
                }
            } catch (PDOException $e) {
                // Table doesn't exist or query failed, use default
                error_log('[AI Context] company_profile query failed in OrganisationContextBuilder: ' . $e->getMessage());
                $org = null;
            }
        }

        // Use default if no organisation data found
        if (!$org || empty($org)) {
            $companyName = getenv('APP_COMPANY_NAME') ?: 'ABBIS Organisation';
            
            // Try to get company name from system_config one more time (simpler query)
            try {
                $nameStmt = $this->pdo->prepare("SELECT config_value FROM system_config WHERE config_key = 'company_name' LIMIT 1");
                $nameStmt->execute();
                $nameValue = $nameStmt->fetchColumn();
                if ($nameValue && trim($nameValue) !== '') {
                    $companyName = trim($nameValue);
                }
            } catch (PDOException $e) {
                // Ignore - use environment variable or default
            }
            
            $org = [
                'company_name' => $companyName,
                'industry' => 'Service Delivery',
                'timezone' => date_default_timezone_get() ?: 'UTC',
            ];
        } else {
            // Ensure required fields exist
            if (!isset($org['company_name']) || empty($org['company_name'])) {
                $org['company_name'] = getenv('APP_COMPANY_NAME') ?: 'ABBIS Organisation';
            }
            if (!isset($org['timezone'])) {
                $org['timezone'] = date_default_timezone_get() ?: 'UTC';
            }
            if (!isset($org['industry'])) {
                $org['industry'] = 'Service Delivery';
            }
        }

        return [
            new ContextSlice('organisation', $org, priority: 20, approxTokens: 160),
        ];
    }
}


