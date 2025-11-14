<?php
/**
 * Consent Management for Data Protection Compliance
 */

require_once __DIR__ . '/helpers.php';

class ConsentManager {
    private $pdo;
    
    public function __construct() {
        $this->pdo = getDBConnection();
        $this->ensureConsentTable();
    }
    
    /**
     * Ensure consent table exists
     */
    private function ensureConsentTable() {
        try {
            $this->pdo->query("SELECT 1 FROM user_consents LIMIT 1");
        } catch (PDOException $e) {
            // Table doesn't exist, but migration should handle it
            error_log("Consent table may not exist. Run database/crm_migration.sql");
        }
    }
    
    /**
     * Record user consent
     */
    public function recordConsent($userId, $email, $consentType, $version = null, $consented = true) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO user_consents (user_id, email, consent_type, version, consented, ip_address, user_agent)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            
            $stmt->execute([
                $userId,
                $email,
                $consentType,
                $version,
                $consented ? 1 : 0,
                $ipAddress,
                $userAgent
            ]);
            
            return true;
        } catch (PDOException $e) {
            error_log("Consent recording error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if user has consented
     */
    public function hasConsented($userId, $email, $consentType, $version = null) {
        try {
            $sql = "
                SELECT consented 
                FROM user_consents 
                WHERE consent_type = ? 
                AND (user_id = ? OR email = ?)
                ORDER BY created_at DESC 
                LIMIT 1
            ";
            
            $params = [$consentType, $userId, $email];
            
            if ($version) {
                $sql = "
                    SELECT consented 
                    FROM user_consents 
                    WHERE consent_type = ? AND version = ?
                    AND (user_id = ? OR email = ?)
                    ORDER BY created_at DESC 
                    LIMIT 1
                ";
                $params = [$consentType, $version, $userId, $email];
            }
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch();
            
            return $result && $result['consented'] == 1;
        } catch (PDOException $e) {
            // If table doesn't exist, return false
            return false;
        }
    }
    
    /**
     * Get consent history for user
     */
    public function getConsentHistory($userId, $email = null) {
        try {
            $sql = "SELECT * FROM user_consents WHERE user_id = ?";
            $params = [$userId];
            
            if ($email && !$userId) {
                $sql = "SELECT * FROM user_consents WHERE email = ?";
                $params = [$email];
            }
            
            $sql .= " ORDER BY created_at DESC";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
}

