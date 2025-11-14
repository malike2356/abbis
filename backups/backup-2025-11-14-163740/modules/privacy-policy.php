<?php
/**
 * Privacy Policy Page
 * Compliance with Ghana Data Protection Act and GDPR
 */
$page_title = 'Privacy Policy';

require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';

require_once '../includes/header.php';
?>

<div class="container-fluid" style="max-width: 900px; margin: 40px auto;">
    <div class="dashboard-card">
        <h1 style="margin-bottom: 30px; color: #1e293b;">🔒 Privacy Policy</h1>
        
        <p style="color: #64748b; margin-bottom: 30px;">
            <strong>Last Updated:</strong> <?php echo date('F j, Y'); ?><br>
            This privacy policy describes how ABBIS collects, uses, and protects your personal information.
        </p>

        <div style="line-height: 1.8; color: #475569;">
            <section style="margin-bottom: 40px;">
                <h2 style="color: #1e293b; margin-bottom: 15px; font-size: 24px;">1. Data Collection</h2>
                <p style="margin-bottom: 15px;">
                    We collect the following types of personal information:
                </p>
                <ul style="margin-left: 30px; margin-bottom: 15px;">
                    <li><strong>Account Information:</strong> Username, email address, password (hashed), full name, role</li>
                    <li><strong>Profile Information:</strong> Phone number, date of birth, profile photo, bio, address, emergency contacts</li>
                    <li><strong>Client Information:</strong> Client name, contact person, phone number, email, address</li>
                    <li><strong>Operational Data:</strong> Field reports, financial transactions, job records</li>
                    <li><strong>Technical Data:</strong> IP address, browser type, device information, usage logs</li>
                </ul>
                <p>
                    <strong>Legal Basis:</strong> We process your data based on legitimate business interests, contract performance, and your explicit consent where required.
                </p>
            </section>

            <section style="margin-bottom: 40px;">
                <h2 style="color: #1e293b; margin-bottom: 15px; font-size: 24px;">2. How We Use Your Data</h2>
                <p style="margin-bottom: 15px;">We use your personal information to:</p>
                <ul style="margin-left: 30px; margin-bottom: 15px;">
                    <li>Provide and improve our services</li>
                    <li>Process field reports and transactions</li>
                    <li>Manage client relationships and communications</li>
                    <li>Ensure system security and prevent fraud</li>
                    <li>Comply with legal obligations</li>
                    <li>Send important system notifications</li>
                </ul>
                <p>
                    We do not sell, rent, or share your personal information with third parties except as necessary to provide our services or as required by law.
                </p>
            </section>

            <section style="margin-bottom: 40px;">
                <h2 style="color: #1e293b; margin-bottom: 15px; font-size: 24px;">3. Data Sharing</h2>
                <p style="margin-bottom: 15px;">
                    We may share your data with:
                </p>
                <ul style="margin-left: 30px; margin-bottom: 15px;">
                    <li><strong>Service Providers:</strong> Third-party services that help us operate (e.g., email providers, hosting services)</li>
                    <li><strong>Legal Requirements:</strong> When required by law, court order, or government regulation</li>
                    <li><strong>Business Transfers:</strong> In case of merger, acquisition, or sale of assets</li>
                </ul>
                <p>
                    All third parties are required to maintain the confidentiality and security of your data.
                </p>
            </section>

            <section style="margin-bottom: 40px;">
                <h2 style="color: #1e293b; margin-bottom: 15px; font-size: 24px;">4. Your Rights</h2>
                <p style="margin-bottom: 15px;">
                    Under Ghana Data Protection Act and GDPR (where applicable), you have the right to:
                </p>
                <ul style="margin-left: 30px; margin-bottom: 15px;">
                    <li><strong>Access:</strong> Request a copy of your personal data</li>
                    <li><strong>Rectification:</strong> Correct inaccurate or incomplete data</li>
                    <li><strong>Erasure:</strong> Request deletion of your data (subject to legal obligations)</li>
                    <li><strong>Data Portability:</strong> Receive your data in a structured, machine-readable format</li>
                    <li><strong>Object:</strong> Object to processing of your data for certain purposes</li>
                    <li><strong>Withdraw Consent:</strong> Withdraw consent at any time where processing is based on consent</li>
                </ul>
                <p>
                    To exercise these rights, contact us through your account settings or system administrator.
                </p>
            </section>

            <section style="margin-bottom: 40px;">
                <h2 style="color: #1e293b; margin-bottom: 15px; font-size: 24px;">5. Data Retention</h2>
                <p style="margin-bottom: 15px;">
                    We retain your personal data for as long as necessary to:
                </p>
                <ul style="margin-left: 30px; margin-bottom: 15px;">
                    <li>Provide our services</li>
                    <li>Comply with legal obligations</li>
                    <li>Resolve disputes and enforce agreements</li>
                </ul>
                <p>
                    Financial records are retained for 7 years as required by law. Personal account data is retained while your account is active. You may request deletion of your account at any time.
                </p>
            </section>

            <section style="margin-bottom: 40px;">
                <h2 style="color: #1e293b; margin-bottom: 15px; font-size: 24px;">6. Security Measures</h2>
                <p style="margin-bottom: 15px;">
                    We implement comprehensive security measures to protect your data:
                </p>
                <ul style="margin-left: 30px; margin-bottom: 15px;">
                    <li>Encryption of passwords and sensitive data</li>
                    <li>Secure authentication and access controls</li>
                    <li>Regular security audits and updates</li>
                    <li>Secure file storage and transmission</li>
                    <li>Role-based access permissions</li>
                    <li>Activity logging and monitoring</li>
                </ul>
            </section>

            <section style="margin-bottom: 40px;">
                <h2 style="color: #1e293b; margin-bottom: 15px; font-size: 24px;">7. Cookies and Tracking</h2>
                <p style="margin-bottom: 15px;">
                    We use essential cookies for system functionality, including:
                </p>
                <ul style="margin-left: 30px; margin-bottom: 15px;">
                    <li>Session management cookies</li>
                    <li>Authentication tokens</li>
                    <li>User preference settings</li>
                </ul>
                <p>
                    We do not use third-party tracking cookies or analytics without your explicit consent.
                </p>
            </section>

            <section style="margin-bottom: 40px;">
                <h2 style="color: #1e293b; margin-bottom: 15px; font-size: 24px;">8. Data Breach Notification</h2>
                <p>
                    In the event of a data breach that may affect your personal information, we will:
                </p>
                <ul style="margin-left: 30px; margin-bottom: 15px;">
                    <li>Notify affected users within 72 hours</li>
                    <li>Report to relevant data protection authorities as required</li>
                    <li>Take immediate steps to contain and remediate the breach</li>
                </ul>
            </section>

            <section style="margin-bottom: 40px;">
                <h2 style="color: #1e293b; margin-bottom: 15px; font-size: 24px;">9. Changes to Privacy Policy</h2>
                <p>
                    We may update this privacy policy from time to time. Material changes will be communicated through the system and the "Last Updated" date will be revised. Continued use of the system after changes constitutes acceptance.
                </p>
            </section>

            <section style="margin-bottom: 40px;">
                <h2 style="color: #1e293b; margin-bottom: 15px; font-size: 24px;">10. Contact Information</h2>
                <p style="margin-bottom: 10px;">
                    For questions, concerns, or to exercise your rights regarding personal data, please contact:
                </p>
                <div style="background: #f1f5f9; padding: 20px; border-radius: 8px; margin-top: 15px;">
                    <p style="margin-bottom: 8px;"><strong>Data Protection Officer</strong></p>
                    <p style="margin-bottom: 5px;">ABBIS System Administrator</p>
                    <p style="margin-bottom: 5px;">Email: <?php 
                        $pdo = getDBConnection();
                        $stmt = $pdo->query("SELECT config_value FROM system_config WHERE config_key = 'company_email'");
                        $email = $stmt->fetchColumn();
                        echo e($email ?: 'admin@abbis.africa');
                    ?></p>
                    <p style="margin-bottom: 5px;">Phone: <?php 
                        $stmt = $pdo->query("SELECT config_value FROM system_config WHERE config_key = 'company_contact'");
                        $phone = $stmt->fetchColumn();
                        echo e($phone ?: '+233 XXX XXX XXX');
                    ?></p>
                    <p style="margin-bottom: 0;">
                        Address: <?php 
                        $stmt = $pdo->query("SELECT config_value FROM system_config WHERE config_key = 'company_address'");
                        $address = $stmt->fetchColumn();
                        echo e($address ?: 'Ghana');
                    ?></p>
                </div>
            </section>

            <section style="margin-bottom: 20px; padding: 20px; background: #f0f9ff; border-left: 4px solid #0ea5e9; border-radius: 6px;">
                <h3 style="color: #0369a1; margin-bottom: 10px;">🇬🇭 Ghana Data Protection Commission</h3>
                <p style="margin-bottom: 5px;">
                    For complaints or inquiries regarding data protection in Ghana:
                </p>
                <p style="margin-bottom: 0;">
                    <strong>Data Protection Commission, Ghana</strong><br>
                    Website: <a href="https://data.gov.gh" target="_blank" style="color: #0ea5e9;">data.gov.gh</a>
                </p>
            </section>
        </div>

        <div style="margin-top: 40px; padding-top: 20px; border-top: 2px solid #e5e7eb; text-align: center;">
            <a href="<?php echo $is_module ? '' : 'modules/'; ?>help.php" class="btn btn-outline">📚 View Help Documentation</a>
            <a href="<?php echo $is_module ? '' : 'modules/'; ?>dashboard.php" class="btn btn-primary">← Back to Dashboard</a>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>

