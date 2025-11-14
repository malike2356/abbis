<?php
// User roles
define('ROLE_SUPER_ADMIN', 'super_admin'); // Development/Maintenance bypass - DEV ONLY
define('ROLE_ADMIN', 'admin');
define('ROLE_MANAGER', 'manager');
define('ROLE_SUPERVISOR', 'supervisor');
define('ROLE_CLERK', 'clerk');
define('ROLE_ACCOUNTANT', 'accountant');
define('ROLE_HR', 'hr');
define('ROLE_FIELD_MANAGER', 'field_manager');
define('ROLE_CLIENT', 'client');

// Job types
define('JOB_DIRECT', 'direct');
define('JOB_SUBCONTRACT', 'subcontract');

// Wage types
define('WAGE_PER_BOREHOLE', 'per_borehole');
define('WAGE_DAILY', 'daily');
define('WAGE_HOURLY', 'hourly');
define('WAGE_CUSTOM', 'custom');

// Material providers
define('MATERIALS_CLIENT', 'client');
define('MATERIALS_COMPANY', 'company');
define('MATERIALS_SHOP', 'material_shop');

// Loan statuses
define('LOAN_ACTIVE', 'active');
define('LOAN_REPAID', 'repaid');
define('LOAN_WRITTEN_OFF', 'written_off');

// Report ID prefix
define('REPORT_PREFIX', 'FR');
define('RECEIPT_PREFIX', 'RC');
define('FINANCE_PREFIX', 'FT');

// AI defaults
define('AI_PERMISSION_KEY', 'ai.assistant');
define('AI_DEFAULT_HOURLY_LIMIT', 60);
define('AI_DEFAULT_DAILY_LIMIT', 400);
define('AI_DEFAULT_PROVIDER_FAILOVER', ['openai', 'deepseek', 'gemini', 'ollama']);
?>