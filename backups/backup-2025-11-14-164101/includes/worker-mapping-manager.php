<?php
/**
 * Worker Mapping Manager
 * Handles worker-role and worker-rig mappings
 */
require_once __DIR__ . '/../config/database.php';

class WorkerMappingManager {
    private $pdo;
    
    public function __construct() {
        $this->pdo = getDBConnection();
    }
    
    /**
     * Check if mapping tables exist
     */
    private function tableExists($tableName) {
        try {
            $stmt = $this->pdo->query("SELECT 1 FROM {$tableName} LIMIT 1");
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * Get all roles assigned to a worker
     */
    public function getWorkerRoles($workerId) {
        if (!$this->tableExists('worker_role_assignments')) {
            return [];
        }
        
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    wra.*,
                    wr.description as role_description
                FROM worker_role_assignments wra
                LEFT JOIN worker_roles wr ON wra.role_name = wr.role_name
                WHERE wra.worker_id = ?
                ORDER BY wra.is_primary DESC, wra.role_name ASC
            ");
            $stmt->execute([$workerId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Get all rigs a worker typically works on
     */
    public function getWorkerRigs($workerId) {
        if (!$this->tableExists('worker_rig_preferences')) {
            return [];
        }
        
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    wrp.*,
                    r.rig_name,
                    r.rig_code
                FROM worker_rig_preferences wrp
                LEFT JOIN rigs r ON wrp.rig_id = r.id
                WHERE wrp.worker_id = ?
                ORDER BY 
                    CASE wrp.preference_level 
                        WHEN 'primary' THEN 1 
                        WHEN 'secondary' THEN 2 
                        WHEN 'occasional' THEN 3 
                    END,
                    r.rig_name ASC
            ");
            $stmt->execute([$workerId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Get all workers who typically work on a rig
     */
    public function getRigWorkers($rigId, $includeRoles = false) {
        if (!$this->tableExists('worker_rig_preferences')) {
            return [];
        }
        
        try {
            $query = "
                SELECT 
                    wrp.*,
                    w.id as worker_id,
                    w.worker_name,
                    w.contact_number,
                    w.status as worker_status
                FROM worker_rig_preferences wrp
                LEFT JOIN workers w ON wrp.worker_id = w.id
                WHERE wrp.rig_id = ? AND w.status = 'active'
                ORDER BY 
                    CASE wrp.preference_level 
                        WHEN 'primary' THEN 1 
                        WHEN 'secondary' THEN 2 
                        WHEN 'occasional' THEN 3 
                    END,
                    w.worker_name ASC
            ";
            
            $stmt = $this->pdo->prepare($query);
            $stmt->execute([$rigId]);
            $workers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if ($includeRoles) {
                foreach ($workers as &$worker) {
                    $worker['roles'] = $this->getWorkerRoles($worker['worker_id']);
                }
            }
            
            return $workers;
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Get worker with all roles and rigs
     */
    public function getWorkerFullProfile($workerId) {
        $stmt = $this->pdo->prepare("SELECT * FROM workers WHERE id = ?");
        $stmt->execute([$workerId]);
        $worker = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($worker) {
            $worker['roles'] = $this->getWorkerRoles($workerId);
            $worker['rigs'] = $this->getWorkerRigs($workerId);
        }
        
        return $worker;
    }
    
    /**
     * Get suggested workers for a rig based on preferences
     */
    public function getSuggestedWorkersForRig($rigId) {
        return $this->getRigWorkers($rigId, true);
    }
    
    /**
     * Get suggested roles for a worker
     */
    public function getSuggestedRolesForWorker($workerId) {
        $roles = $this->getWorkerRoles($workerId);
        return array_map(function($role) {
            return [
                'role_name' => $role['role_name'],
                'default_rate' => $role['default_rate'],
                'is_primary' => $role['is_primary']
            ];
        }, $roles);
    }
}

