<?php
/**
 * Centralized access-control configuration.
 *
 * Define system roles and the permissions (logical capabilities) they have.
 * Each permission may be linked to one or more PHP pages so that UI guards
 * can automatically enforce restrictions without touching every module.
 */

require_once __DIR__ . '/constants.php';

return [
    /*
     * Human-friendly labels for each role. Use these labels when displaying
     * role selectors or audit reports.
     */
    'roles' => [
        ROLE_ADMIN => 'Administrator',
        ROLE_MANAGER => 'Operations Manager',
        ROLE_SUPERVISOR => 'Supervisor',
        ROLE_CLERK => 'Clerk / Front Desk',
        ROLE_ACCOUNTANT => 'Accountant',
        ROLE_HR => 'Human Resources',
        ROLE_FIELD_MANAGER => 'Field Manager',
    ],

    /*
     * Permission matrix. Each permission contains:
     *  - label: short description for UI/logging
     *  - roles: array of role slugs that are allowed (admins are added automatically)
     *  - pages: list of PHP files associated with the permission. Basenames are used
     *           when matching, so both `modules/hr.php` and `hr.php` resolve to `hr.php`.
     */
    'permissions' => [
        'dashboard.view' => [
            'label' => 'Dashboards & Analytics',
            'roles' => [
                ROLE_ADMIN,
                ROLE_MANAGER,
                ROLE_SUPERVISOR,
                ROLE_CLERK,
                ROLE_ACCOUNTANT,
                ROLE_HR,
                ROLE_FIELD_MANAGER,
            ],
            'pages' => [
                'dashboard.php',
                'analytics.php',
            ],
        ],
        'field_reports.manage' => [
            'label' => 'Field Reports',
            'roles' => [
                ROLE_ADMIN,
                ROLE_MANAGER,
                ROLE_SUPERVISOR,
                ROLE_FIELD_MANAGER,
            ],
            'pages' => [
                'field-reports.php',
                'field-report-detail.php',
                'field-report-edit.php',
                'field-report-history.php',
                'field-report-export.php',
            ],
        ],
        'crm.access' => [
            'label' => 'CRM & Client Engagement',
            'roles' => [
                ROLE_ADMIN,
                ROLE_MANAGER,
                ROLE_CLERK,
                ROLE_SUPERVISOR,
            ],
            'pages' => [
                'crm.php',
                'clients.php',
                'crm-dashboard.php',
                'crm-followups.php',
                'crm-templates.php',
                'crm-emails.php',
                'complaints.php',
            ],
        ],
        'hr.access' => [
            'label' => 'Human Resources',
            'roles' => [
                ROLE_ADMIN,
                ROLE_HR,
            ],
            'pages' => [
                'hr.php',
                'workers.php',
                'hr-dashboard.php',
                'hr-reports.php',
            ],
        ],
        'recruitment.access' => [
            'label' => 'Recruitment & Applicant Tracking',
            'roles' => [
                ROLE_ADMIN,
                ROLE_HR,
                ROLE_MANAGER,
            ],
            'pages' => [
                'recruitment.php',
                'recruitment-dashboard.php',
                'recruitment-applications.php',
                'recruitment-vacancies.php',
            ],
        ],
        'resources.access' => [
            'label' => 'Inventory & Resources',
            'roles' => [
                ROLE_ADMIN,
                ROLE_MANAGER,
                ROLE_SUPERVISOR,
            ],
            'pages' => [
                'resources.php',
                'materials.php',
                'catalog.php',
                'inventory-advanced.php',
                'assets.php',
                'maintenance.php',
            ],
        ],
        'finance.access' => [
            'label' => 'Finance & Accounting',
            'roles' => [
                ROLE_ADMIN,
                ROLE_ACCOUNTANT,
            ],
            'pages' => [
                'financial.php',
                'finance.php',
                'payroll.php',
                'loans.php',
                'accounting.php',
                'accounting-dashboard.php',
                'accounting-reports.php',
                'collections.php',
                'debt-recovery.php',
            ],
        ],
        'pos.access' => [
            'label' => 'POS Access',
            'roles' => [
                ROLE_ADMIN,
                ROLE_MANAGER,
                ROLE_ACCOUNTANT,
            ],
            'pages' => [
                'pos.php',
            ],
        ],
        'pos.inventory.manage' => [
            'label' => 'POS Inventory Management',
            'roles' => [
                ROLE_ADMIN,
                ROLE_MANAGER,
            ],
            'pages' => [
                'pos-admin.php',
            ],
        ],
        'pos.sales.process' => [
            'label' => 'POS Sales Processing',
            'roles' => [
                ROLE_ADMIN,
                ROLE_MANAGER,
                ROLE_ACCOUNTANT,
                ROLE_CLERK,
            ],
            'pages' => [
                'pos.php',
            ],
        ],
        'system.admin' => [
            'label' => 'System Administration',
            'roles' => [
                ROLE_ADMIN,
            ],
            'pages' => [
                'system.php',
                'config.php',
                'data-management.php',
                'users.php',
                'feature-management.php',
                'database-migrations.php',
                'api-keys.php',
                'zoho-integration.php',
                'looker-studio-integration.php',
                'elk-integration.php',
                'rig-tracking.php',
                'access-logs.php',
            ],
        ],
        AI_PERMISSION_KEY => [
            'label' => 'AI Assistant & Insights',
            'roles' => [
                ROLE_ADMIN,
                ROLE_MANAGER,
                ROLE_SUPERVISOR,
            ],
            'pages' => [
                'ai-assistant.php',
                'analytics.php',
                'dashboard.php',
            ],
        ],
    ],
];

