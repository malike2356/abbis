<?php
class Validation {
    
    public static function validateFieldReport($data) {
        $errors = [];
        
        // Required fields
        $required = ['report_date', 'rig_id', 'job_type', 'site_name'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                $errors[] = "Field '{$field}' is required.";
            }
        }
        
        // Numeric fields validation
        $numericFields = [
            'balance_bf', 'contract_sum', 'rig_fee_charged', 'rig_fee_collected',
            'cash_received', 'materials_income', 'materials_cost', 'momo_transfer',
            'cash_given', 'bank_deposit'
        ];
        
        foreach ($numericFields as $field) {
            if (!empty($data[$field]) && !is_numeric($data[$field])) {
                $errors[] = "Field '{$field}' must be a number.";
            }
        }
        
        // Date validation
        if (!empty($data['report_date']) && !strtotime($data['report_date'])) {
            $errors[] = "Invalid date format.";
        }
        
        // Time validation
        if (!empty($data['start_time']) && !preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $data['start_time'])) {
            $errors[] = "Invalid start time format.";
        }
        
        if (!empty($data['finish_time']) && !preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $data['finish_time'])) {
            $errors[] = "Invalid finish time format.";
        }
        
        return $errors;
    }
    
    public static function sanitizeInput($data) {
        if (is_array($data)) {
            return array_map([self::class, 'sanitizeInput'], $data);
        }
        
        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }
    
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    public static function validatePhone($phone) {
        return preg_match('/^\+?[\d\s\-\(\)]{10,}$/', $phone);
    }
}
?>