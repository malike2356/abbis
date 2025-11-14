<?php
/**
 * Create Default Email Templates for ABBIS
 * Run this script to populate the system with pre-built templates
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

$pdo = getDBConnection();

$templates = [
    [
        'name' => 'Job Completion Notification',
        'subject' => 'Job Completed - {{report_id}} for {{client_name}}',
        'body' => "Dear {{contact_name}},

We are pleased to inform you that the drilling job at {{site_name}} has been completed successfully.

Job Details:
- Report ID: {{report_id}}
- Date: {{report_date}}
- Total Depth: {{total_depth}} meters
- Rig Used: {{rig_name}} ({{rig_code}})
- Total RPM: {{total_rpm}}

Financial Summary:
- Contract Amount: {{currency}} {{contract_sum}}
- Total Income: {{currency}} {{total_income}}
- Net Profit: {{currency}} {{net_profit}}

If you have any questions or concerns, please don't hesitate to contact us.

Best regards,
{{sender_name}}
{{company_name}}",
        'category' => 'job_completion',
        'variables' => json_encode(['report_id', 'client_name', 'contact_name', 'site_name', 'report_date', 'total_depth', 'rig_name', 'rig_code', 'total_rpm', 'currency', 'contract_sum', 'total_income', 'net_profit', 'sender_name', 'company_name'])
    ],
    [
        'name' => 'Payment Reminder',
        'subject' => 'Payment Reminder - Outstanding Balance for {{client_name}}',
        'body' => "Dear {{contact_name}},

This is a friendly reminder regarding your outstanding balance with {{company_name}}.

Outstanding Amount: {{currency}} {{outstanding_balance}}

Please arrange payment at your earliest convenience. We accept the following payment methods:
- Mobile Money
- Bank Transfer
- Cash

If you have already made a payment, please ignore this message. If you have any questions, please contact us.

Thank you for your business.

Best regards,
{{sender_name}}
{{company_name}}
{{company_phone}}",
        'category' => 'payment_reminder',
        'variables' => json_encode(['contact_name', 'client_name', 'company_name', 'outstanding_balance', 'currency', 'sender_name', 'company_phone'])
    ],
    [
        'name' => 'Welcome New Client',
        'subject' => 'Welcome to {{company_name}} - {{client_name}}',
        'body' => "Dear {{contact_name}},

Welcome to {{company_name}}! We are thrilled to have {{client_name}} as our valued client.

We specialize in professional drilling services and are committed to providing you with the highest quality service.

Our services include:
- Borehole drilling
- Well construction
- Water system installation
- Maintenance and repairs

If you have any questions or need assistance, please don't hesitate to reach out to us.

We look forward to working with you!

Best regards,
{{sender_name}}
{{company_name}}
{{company_phone}}
{{company_address}}",
        'category' => 'welcome',
        'variables' => json_encode(['contact_name', 'client_name', 'company_name', 'sender_name', 'company_phone', 'company_address'])
    ],
    [
        'name' => 'Follow-up After Job',
        'subject' => 'Follow-up: How was your experience with {{company_name}}?',
        'body' => "Dear {{contact_name}},

We hope this message finds you well. We wanted to follow up on the recent drilling job we completed at {{site_name}} (Report: {{report_id}}).

We would love to hear about your experience:
- How is the water quality?
- Is everything working as expected?
- Do you have any concerns or questions?

Your feedback is important to us and helps us improve our services.

If you need any maintenance or have any issues, please don't hesitate to contact us.

Thank you for choosing {{company_name}}!

Best regards,
{{sender_name}}
{{company_name}}
{{company_phone}}",
        'category' => 'followup',
        'variables' => json_encode(['contact_name', 'company_name', 'site_name', 'report_id', 'sender_name', 'company_phone'])
    ],
    [
        'name' => 'Maintenance Reminder',
        'subject' => 'Maintenance Due - {{rig_name}}',
        'body' => "Dear {{contact_name}},

This is a reminder that maintenance is due for {{rig_name}} ({{rig_code}}).

Maintenance Details:
- Type: {{maintenance_type}}
- Current RPM: {{rpm_at_maintenance}}
- Next Maintenance Due: {{next_maintenance_due}}

Regular maintenance ensures optimal performance and extends the life of your equipment.

Please contact us to schedule a maintenance appointment.

Best regards,
{{sender_name}}
{{company_name}}
{{company_phone}}",
        'category' => 'maintenance',
        'variables' => json_encode(['contact_name', 'rig_name', 'rig_code', 'maintenance_type', 'rpm_at_maintenance', 'next_maintenance_due', 'sender_name', 'company_name', 'company_phone'])
    ],
    [
        'name' => 'Payment Receipt',
        'subject' => 'Payment Receipt - {{receipt_number}}',
        'body' => "Dear {{contact_name}},

Thank you for your payment! Please find your payment receipt below.

Receipt Number: {{receipt_number}}
Payment Date: {{payment_date}}
Payment Amount: {{currency}} {{payment_amount}}
Payment Method: {{payment_method}}

This payment has been recorded in our system. If you have any questions, please contact us.

Thank you for your business!

Best regards,
{{sender_name}}
{{company_name}}
{{company_phone}}",
        'category' => 'receipt',
        'variables' => json_encode(['contact_name', 'receipt_number', 'payment_date', 'currency', 'payment_amount', 'payment_method', 'sender_name', 'company_name', 'company_phone'])
    ],
    [
        'name' => 'Quote Request',
        'subject' => 'Quote Request for Drilling Services - {{client_name}}',
        'body' => "Dear {{contact_name}},

Thank you for your interest in our drilling services. We have received your request for a quote.

We will prepare a detailed quote based on your requirements and send it to you within 24-48 hours.

In the meantime, if you have any questions or need additional information, please don't hesitate to contact us.

We look forward to working with {{client_name}}!

Best regards,
{{sender_name}}
{{company_name}}
{{company_phone}}",
        'category' => 'quote',
        'variables' => json_encode(['contact_name', 'client_name', 'sender_name', 'company_name', 'company_phone'])
    ],
    [
        'name' => 'Rig Request Acknowledgement',
        'subject' => 'Rig Request Received - {{request_number}}',
        'body' => "Dear {{requester_name}},

Thank you for submitting a rig request ({{request_number}}). Our team has received the details below and will contact you shortly.

Request Summary:
- Company/Requester: {{company_name}} ({{requester_type}})
- Contact Email: {{requester_email}}
- Contact Phone: {{requester_phone}}
- Location: {{location_address}}
- Number of Boreholes: {{number_of_boreholes}}
- Preferred Start Date: {{preferred_start_date}}
- Urgency: {{urgency}}
- Estimated Budget: {{currency}} {{estimated_budget}}

If any of this information changes, please let us know so we can update your request.

Best regards,
{{sender_name}}
{{company_name}}
{{company_phone}}",
        'category' => 'rig_request',
        'variables' => json_encode(['request_number', 'requester_name', 'requester_email', 'requester_phone', 'requester_type', 'company_name', 'location_address', 'number_of_boreholes', 'preferred_start_date', 'urgency', 'estimated_budget', 'currency', 'sender_name', 'company_phone'])
    ],
    [
        'name' => 'Thank You Message',
        'subject' => 'Thank You - {{client_name}}',
        'body' => "Dear {{contact_name}},

Thank you for choosing {{company_name}} for your drilling needs!

We truly appreciate your business and trust in our services. It has been a pleasure working with {{client_name}}.

If you need any future services or have any questions, please don't hesitate to reach out to us.

We look forward to serving you again in the future!

Best regards,
{{sender_name}}
{{company_name}}
{{company_phone}}
{{company_address}}",
        'category' => 'thank_you',
        'variables' => json_encode(['contact_name', 'client_name', 'company_name', 'sender_name', 'company_phone', 'company_address'])
    ]
];

$created = 0;
$skipped = 0;

foreach ($templates as $template) {
    try {
        // Check if template already exists
        $checkStmt = $pdo->prepare("SELECT id FROM email_templates WHERE name = ?");
        $checkStmt->execute([$template['name']]);
        if ($checkStmt->fetch()) {
            $skipped++;
            continue;
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO email_templates (name, subject, body, category, variables, is_active, created_by)
            VALUES (?, ?, ?, ?, ?, 1, 1)
        ");
        $stmt->execute([
            $template['name'],
            $template['subject'],
            $template['body'],
            $template['category'],
            $template['variables']
        ]);
        $created++;
    } catch (PDOException $e) {
        echo "Error creating template '{$template['name']}': " . $e->getMessage() . "\n";
    }
}

echo "✅ Default templates created successfully!\n";
echo "   Created: $created templates\n";
if ($skipped > 0) {
    echo "   Skipped: $skipped templates (already exist)\n";
}
?>

