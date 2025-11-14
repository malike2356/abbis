<?php
/**
 * Legal Documents Management - System-wide Legal Documents
 */
session_start();
$rootPath = dirname(dirname(__DIR__));
require_once $rootPath . '/config/app.php';
require_once $rootPath . '/includes/functions.php';
require_once __DIR__ . '/auth.php';

$cmsAuth = new CMSAuth();
if (!$cmsAuth->isLoggedIn() || !$cmsAuth->isAdmin()) {
    header('Location: login.php');
    exit;
}

$pdo = getDBConnection();

// Ensure legal documents table exists
try {
    $pdo->query("SELECT * FROM cms_legal_documents LIMIT 1");
} catch (PDOException $e) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_legal_documents (
          id INT AUTO_INCREMENT PRIMARY KEY,
          document_type VARCHAR(50) NOT NULL,
          title VARCHAR(255) NOT NULL,
          slug VARCHAR(100) UNIQUE NOT NULL,
          content LONGTEXT NOT NULL,
          version VARCHAR(20) DEFAULT '1.0',
          effective_date DATE DEFAULT NULL,
          is_active TINYINT(1) DEFAULT 1,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          INDEX idx_type (document_type),
          INDEX idx_slug (slug),
          INDEX idx_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        // Insert default documents
        $defaultDocs = [
            ['drilling_agreement', 'Terms & Conditions / Agreement for Drilling', 'drilling-agreement', '', '1.0'],
            ['terms_of_service', 'Terms of Service', 'terms-of-service', '', '1.0'],
            ['privacy_policy', 'Privacy Policy', 'privacy-policy', '', '1.0']
        ];
        
        foreach ($defaultDocs as $doc) {
            $pdo->prepare("INSERT IGNORE INTO cms_legal_documents (document_type, title, slug, content, version) VALUES (?,?,?,?,?)")
                ->execute($doc);
        }
    } catch (PDOException $e2) {}
}

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;
$message = null;
$error = null;

// Handle document save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_document'])) {
    $documentId = $_POST['document_id'] ?? null;
    $title = trim($_POST['title'] ?? '');
    $content = $_POST['content'] ?? '';
    $version = trim($_POST['version'] ?? '1.0');
    $effectiveDate = $_POST['effective_date'] ?? null;
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    
    if ($documentId) {
        // Update existing
        $stmt = $pdo->prepare("UPDATE cms_legal_documents SET title=?, content=?, version=?, effective_date=?, is_active=?, updated_at=NOW() WHERE id=?");
        $stmt->execute([$title, $content, $version, $effectiveDate ?: null, $isActive, $documentId]);
        $message = 'Document updated successfully';
    } else {
        $documentType = $_POST['document_type'] ?? 'other';
        $slug = $_POST['slug'] ?? '';
        
        if (empty($slug)) {
            $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $title));
        }
        
        $stmt = $pdo->prepare("INSERT INTO cms_legal_documents (document_type, title, slug, content, version, effective_date, is_active) VALUES (?,?,?,?,?,?,?)");
        $stmt->execute([$documentType, $title, $slug, $content, $version, $effectiveDate ?: null, $isActive]);
        $message = 'Document created successfully';
    }
}

// Get documents
$documents = $pdo->query("SELECT * FROM cms_legal_documents ORDER BY document_type, title")->fetchAll();

// Calculate statistics
$statsQuery = $pdo->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN is_active=1 THEN 1 ELSE 0 END) as active,
    SUM(CASE WHEN is_active=0 THEN 1 ELSE 0 END) as inactive,
    COUNT(DISTINCT document_type) as types_count
    FROM cms_legal_documents");
$stats = $statsQuery->fetch(PDO::FETCH_ASSOC);

$documentTypes = [
    'drilling_agreement' => 'Drilling Agreement',
    'terms_of_service' => 'Terms of Service',
    'privacy_policy' => 'Privacy Policy',
    'warranty' => 'Warranty Policy',
    'refund' => 'Refund Policy',
    'cancellation' => 'Cancellation Policy',
    'other' => 'Other'
];

// Count by type
$typeCounts = [];
foreach ($documentTypes as $typeKey => $typeName) {
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM cms_legal_documents WHERE document_type=?");
    $countStmt->execute([$typeKey]);
    $typeCounts[$typeKey] = $countStmt->fetchColumn();
}

// Get document for editing
$document = null;
if ($id && $action === 'edit') {
    $stmt = $pdo->prepare("SELECT * FROM cms_legal_documents WHERE id=?");
    $stmt->execute([$id]);
    $document = $stmt->fetch(PDO::FETCH_ASSOC);
}

$configStmt = $pdo->query("SELECT config_value FROM system_config WHERE config_key='company_name'");
$companyName = $configStmt->fetchColumn() ?: 'CMS Admin';
$baseUrl = app_url();

// Default drilling agreement content
$defaultDrillingAgreement = <<<'EOD'
<h2>SCOPE OF WORK</h2>

<p>Our standard quotation covers <strong>Drilling of borehole</strong>, <strong>Construction of the borehole</strong> and <strong>mechanization of borehole</strong> (Installation of submersible pump and it's Accessories). Below are details of work involved in the scope.</p>

<h3>WHAT IS INCLUDED</h3>

<ol>
    <li>A drilling depth min/max of <strong>40m-100m (98ft – 328ft)</strong> depending on the yield of the borehole. When to stop drilling (between 40m and 100m) is discretionary and or based of the ground formation observed during drilling</li>
    
    <li>Construction of borehole (Including PVC pipes, gravels and if necessary grouting cement – quantities will be estimated and determined based on the geological formation and history of the area selected for drilling)</li>
    
    <li>Mechanization of borehole after drilling (Installation of submersible pump and it's accessories.)</li>
</ol>

<p>The type of Pump to use is determined after drilling, however the standard is to use <strong>0.5HP, 0.7HP or 1.0HP</strong> for wells of Depth 30m to 60m and <strong>1.5</strong> for wells between 60m to 100m taking into consideration the yield and recovery of the borehole before making those choices. Higher Pumps may be considered for wells more than 150m.</p>

<p><strong>WE DO NOT PROMISE ON ANY SPECIFIC BRANDS AS BRAND AVAILABILITY IS NOT STATIC BUT WE DO OUR BEST TO USE HIGH QUALITY BRANDS FOR ALL OUR WORKS.</strong></p>

<h3>WHAT IS NOT INCLUDED</h3>

<p>The following are <strong>NOT INCLUDED</strong> UNLESS PAID for in advance or on request (We are happy to give professional advice, consultation and or recommendations of who, where and how to go about them):</p>

<ol>
    <li>Extra depth of drilling after a maximum of 100m (328feet). Cost calculated based on Each extra rod (5m)</li>
    <li>Plumbing works (laying of hose/pipes and all manner of plumbing works and Accessories) after mechanization</li>
    <li>Electrical works (E.g Meters, Electrical Accessories, Power connections to site etc)</li>
    <li>Logging, Yield testing, Water Quality testing, Water treatment of any kind</li>
    <li>Hydrofracture (In the case of marginal wells)</li>
    <li>System Automations of any kind (E.g. Automatic control devices/timers, Automatic Voltage Supplies etc)</li>
    <li>Platforms, Stands, Pads, Encasements, chambers etc (Masonry, block/concrete works and all forms of civil works)</li>
    <li>Polytanks, Reserviors and all forms storage systems</li>
    <li>Manual/Hand Pumps (ONLY on request and at a cost)</li>
    <li>Clearing and Cleaning drilling site</li>
</ol>

<p>In as much as a survey shall be or has been conducted to determine to potential (Prime) location for the drilling, <strong>THE QUANTITY AND QUALITY OF WATER CAN NOT BE DETERMINED or guaranteed until/after the drilling is/has been done.</strong> All recommendations by the Surveyor and or Consultant may be carried out for the successful completion of the borehole but where the recommendation shall require extra cost (as a result of E.g. Extra depth, hydrofracture, logging, testing, water treatment, automation, etc), <strong>THESE EXTRA COST SHALL BE PAID BY THE CLIENT.</strong></p>

<h3>CLIENT SPECIFICATIONS & ADJUSTMENTS</h3>

<p>In the situation where a client requests for a specific brand of any material or Accessory, we shall compare the prices against availability, quality, durability, functionality and cost and make appropriate recommendations. Any Extra cost arising as a result of this new requirement and recommendation shall be borne by the client.</p>

<h3>OVERDUE</h3>

<p>On Agreement or Acceptance of Invoice/Estimates a minimum of <strong>15days</strong> is allowed for payment to be effected and a maximum of <strong>30days overdue</strong> allowed. After 30 days, Invoice/Estimate may not be valid and a new one may be issued if client still wants to continue business with us. This is considered for possible price changes of current market prices/Rates of products/services.</p>

<h3>CANCELLATION</h3>

<p>If Agreed and contract commenced, any cancellation on the part of the client may result in forfeiture of funds paid for services already rendered. If the cancellation is requested mid way through the contract, only the payments phases of the contract yet to be done shall be refunded less the full service charge for the job. Products (materials) already acquired for the job may be given to the client or sold, and whatever proceeds realized refunded to the client. Cancellations rights on the parts of Velox reserved.</p>

<h3>PAYMENT</h3>

<p><strong>80% of full quotation</strong> shall be required and paid by client before commencement of Job. The remaining <strong>20%</strong> shall be collected after the drilling is done or before mechanization (installation of pumps). We do not prefinance and even if we have to, it shall NOT be more than 20% of full contract.</p>

<p>We Accept Payments by Cash, MoMo or Bank Transfer.</p>

<p>Bank detail: [To be configured in settings]</p>
<p>MOMO PAY NUMBER: [To be configured in settings]</p>
<p>MOMO PAY ID: [To be configured in settings]</p>

<h3>AGREEMENT</h3>

<p><strong>PAYMENT (IN PART OR FULL) SHALL CONSTITUTE YOUR AGREEMENT WITH OUR TERMS AND IMPLY A BINDING CONTRACT BETWEEN VELOX AND THE CLIENT.</strong></p>

<p>We provide services in Boreholes drilling and construction, Mechanization, Automation, Maintenance, and Rehabilitation of wells.</p>
EOD;

// If drilling agreement doesn't exist or is empty, create/update it
if ($action === 'list') {
    $drillingStmt = $pdo->prepare("SELECT * FROM cms_legal_documents WHERE document_type='drilling_agreement' LIMIT 1");
    $drillingStmt->execute();
    $drillingDoc = $drillingStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$drillingDoc || empty($drillingDoc['content'])) {
        try {
            if ($drillingDoc) {
                $pdo->prepare("UPDATE cms_legal_documents SET content=? WHERE id=?")->execute([$defaultDrillingAgreement, $drillingDoc['id']]);
            } else {
                $pdo->prepare("INSERT INTO cms_legal_documents (document_type, title, slug, content, version) VALUES (?,?,?,?,?)")
                    ->execute(['drilling_agreement', 'Terms & Conditions / Agreement for Drilling', 'drilling-agreement', $defaultDrillingAgreement, '1.0']);
            }
            // Reload documents
            $documents = $pdo->query("SELECT * FROM cms_legal_documents ORDER BY document_type, title")->fetchAll();
        } catch (PDOException $e) {}
    }
}

$recentDocumentsStmt = $pdo->query("
    SELECT id, title, document_type, slug, updated_at, is_active, version, effective_date
    FROM cms_legal_documents
    ORDER BY updated_at DESC
    LIMIT 6
");
$recentDocuments = $recentDocumentsStmt->fetchAll(PDO::FETCH_ASSOC);

$undatedDocuments = (int) $pdo->query("
    SELECT COUNT(*) FROM cms_legal_documents 
    WHERE effective_date IS NULL OR effective_date = '' OR effective_date = '0000-00-00'
")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Legal Documents - <?php echo htmlspecialchars($companyName); ?> CMS</title>
    <!-- CKEditor 5 -->
    <script src="https://cdn.ckeditor.com/ckeditor5/41.1.0/classic/ckeditor.js"></script>
    <?php 
    $currentPage = 'legal-documents';
    include 'header.php'; 
    ?>
    <style>
        :root {
            --legal-bg: #f5f7fb;
            --legal-border: #e2e8f0;
            --legal-shadow: 0 16px 32px rgba(15, 23, 42, 0.08);
            --legal-primary: #2271b1;
            --legal-primary-dark: #135e96;
            --legal-success: #16a34a;
            --legal-danger: #dc2626;
            --legal-muted: #64748b;
        }
        body.cms-admin-legal {
            background: var(--legal-bg);
        }
        body.cms-admin-legal {
            background: var(--legal-bg);
        }
        .wrap.legal-wrap {
            max-width: 100%;
            width: min(1100px, 92vw);
            margin-left: auto;
            margin-right: auto;
        }
        .legal-page-header {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin-bottom: 24px;
        }
        .legal-page-header h1 {
            margin: 0;
            font-size: 28px;
            color: #0f172a;
        }
        .legal-page-header p {
            margin: 0;
            color: var(--legal-muted);
            font-size: 14px;
        }
        .legal-layout {
            display: grid;
            grid-template-columns: minmax(260px, 320px) 1fr;
            gap: 24px;
            align-items: flex-start;
        }
        @media (max-width: 1100px) {
            .legal-layout {
                grid-template-columns: 1fr;
            }
        }
        .legal-sidebar,
        .legal-main {
            display: grid;
            gap: 20px;
        }
        .sidebar-card {
            background: #fff;
            border: 1px solid var(--legal-border);
            border-radius: 16px;
            padding: 20px 22px;
            box-shadow: var(--legal-shadow);
            display: grid;
            gap: 16px;
        }
        .sidebar-card h3 {
            margin: 0;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #1d2939;
        }
        .stats-list {
            list-style: none;
            margin: 0;
            padding: 0;
            display: grid;
            gap: 12px;
        }
        .stats-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            font-size: 14px;
            color: #1f2937;
        }
        .stats-item span:last-child {
            font-weight: 700;
            color: #0f172a;
        }
        .stats-item .tag {
            display: inline-flex;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }
        .type-chip-group {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .type-chip {
            border: 1px solid var(--legal-border);
            border-radius: 999px;
            padding: 6px 12px;
            font-size: 12px;
            font-weight: 600;
            background: #f8fafc;
            color: #0f172a;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .type-chip:hover {
            border-color: var(--legal-primary);
        }
        .type-chip.active {
            background: var(--legal-primary);
            color: #fff;
            border-color: var(--legal-primary);
        }
        .type-chip.disabled {
            opacity: 0.45;
            cursor: not-allowed;
        }
        .type-chip .count {
            background: rgba(255,255,255,0.25);
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 11px;
        }
        .recent-list {
            list-style: none;
            margin: 0;
            padding: 0;
            display: grid;
            gap: 16px;
        }
        .recent-item {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: center;
        }
        .recent-item__meta {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .recent-item__title {
            font-weight: 600;
            color: #0f172a;
            font-size: 14px;
        }
        .recent-item__details {
            font-size: 12px;
            color: var(--legal-muted);
        }
        .recent-item__status {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            color: var(--legal-muted);
        }
        .recent-item__action {
            font-size: 12px;
            text-decoration: none;
            color: var(--legal-primary);
            font-weight: 600;
        }
        .legal-toolbar {
            background: #fff;
            border: 1px solid var(--legal-border);
            border-radius: 16px;
            padding: 20px 22px;
            box-shadow: var(--legal-shadow);
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            align-items: center;
            justify-content: space-between;
        }
        .legal-toolbar__title {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .legal-toolbar__title h2 {
            margin: 0;
            font-size: 20px;
            color: #0f172a;
        }
        .legal-toolbar__title span {
            font-size: 13px;
            color: var(--legal-muted);
        }
        .legal-toolbar__actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .legal-filters {
            background: #fff;
            border: 1px solid var(--legal-border);
            border-radius: 16px;
            box-shadow: var(--legal-shadow);
            padding: 18px 22px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
        }
        .filter-field {
            display: grid;
            gap: 6px;
        }
        .filter-field label {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--legal-muted);
            font-weight: 600;
        }
        .filter-field input[type="text"],
        .filter-field select {
            padding: 9px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            font-size: 13px;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        .filter-field input[type="text"]:focus,
        .filter-field select:focus {
            outline: none;
            border-color: var(--legal-primary);
            box-shadow: 0 0 0 3px rgba(34, 113, 177, 0.15);
        }
        .document-empty {
            display: none;
            margin-top: 20px;
            padding: 32px;
            border: 1px dashed var(--legal-border);
            border-radius: 16px;
            text-align: center;
            color: var(--legal-muted);
            background: rgba(255,255,255,0.7);
        }
        .document-section {
            background: #fff;
            border: 1px solid var(--legal-border);
            border-radius: 18px;
            box-shadow: var(--legal-shadow);
            padding: 22px;
            display: grid;
            gap: 20px;
        }
        .document-section.is-hidden {
            display: none;
        }
        .document-section__header {
            display: flex;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }
        .document-section__icon {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            background: linear-gradient(135deg, rgba(34, 113, 177, 0.18), rgba(19, 94, 150, 0.28));
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }
        .document-section__title {
            margin: 0;
            font-size: 18px;
            color: #111827;
            font-weight: 700;
        }
        .document-section__meta {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 12px;
            color: var(--legal-muted);
        }
        .document-section__count {
            margin-left: auto;
            background: #f1f5f9;
            color: #0f172a;
            padding: 4px 12px;
            border-radius: 999px;
            font-weight: 600;
            font-size: 12px;
        }
        .documents-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 18px;
        }
        .document-card {
            background: #fff;
            border: 1px solid var(--legal-border);
            border-radius: 14px;
            padding: 20px;
            display: grid;
            gap: 14px;
            transition: all 0.25s ease;
            position: relative;
        }
        .document-card::before {
            content: "";
            position: absolute;
            inset: 0;
            border-radius: inherit;
            border: 1px solid transparent;
            transition: border-color 0.25s ease, box-shadow 0.25s ease;
        }
        .document-card:hover::before {
            border-color: rgba(34,113,177,0.45);
            box-shadow: 0 12px 32px rgba(15,23,42,0.12);
        }
        .document-card.active {
            border-left: 4px solid var(--legal-success);
        }
        .document-card.inactive {
            border-left: 4px solid var(--legal-border);
            opacity: 0.92;
        }
        .document-card.is-hidden {
            display: none !important;
        }
        .document-card__heading {
            display: flex;
            gap: 12px;
            align-items: flex-start;
            justify-content: space-between;
        }
        .document-card__title {
            margin: 0;
            font-size: 17px;
            font-weight: 700;
            color: #0f172a;
        }
        .document-card__badges {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .document-badge {
            border-radius: 999px;
            padding: 4px 10px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }
        .badge-status--active {
            background: rgba(22, 163, 74, 0.15);
            color: #166534;
        }
        .badge-status--inactive {
            background: rgba(220, 38, 38, 0.12);
            color: #b91c1c;
        }
        .badge-type {
            background: rgba(34, 113, 177, 0.14);
            color: var(--legal-primary-dark);
        }
        .document-card__meta {
            display: grid;
            gap: 10px;
            font-size: 13px;
            color: var(--legal-muted);
        }
        .document-card__meta-row {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .document-card__meta-row span {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #f8fafc;
            padding: 4px 10px;
            border-radius: 10px;
            border: 1px solid rgba(148,163,184,0.35);
        }
        .document-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .document-actions a {
            padding: 8px 14px;
            border-radius: 10px;
            font-size: 13px;
            text-decoration: none;
            border: 1px solid #d1d9e6;
            color: var(--legal-primary);
            font-weight: 600;
            transition: all 0.2s ease;
            background: #fff;
        }
        .document-actions a:hover {
            border-color: var(--legal-primary);
            background: rgba(34,113,177,0.08);
        }
        .document-actions .button-primary {
            background: var(--legal-primary);
            color: #fff;
            border-color: var(--legal-primary);
        }
        .document-actions .button-primary:hover {
            background: var(--legal-primary-dark);
            border-color: var(--legal-primary-dark);
        }
        .sidebar-card small {
            color: var(--legal-muted);
            font-size: 12px;
            line-height: 1.4;
        }
        .legal-muted-note {
            font-size: 12px;
            color: var(--legal-muted);
            margin-top: -4px;
        }
        /* Editor / form styles */
        .editor-container {
            background: #fff;
            border: 1px solid var(--legal-border);
            border-radius: 18px;
            padding: 24px;
            box-shadow: var(--legal-shadow);
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #0f172a;
        }
        .form-group input[type="text"],
        .form-group input[type="date"],
        .form-group select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        .form-group input[type="text"]:focus,
        .form-group input[type="date"]:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--legal-primary);
            box-shadow: 0 0 0 3px rgba(34, 113, 177, 0.15);
        }
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
        }
        .documents-placeholder {
            font-size: 13px;
            color: var(--legal-muted);
        }
        @media (max-width: 768px) {
            .documents-grid {
                grid-template-columns: 1fr;
            }
            .legal-toolbar {
                flex-direction: column;
                align-items: flex-start;
            }
            .legal-toolbar__actions {
                width: 100%;
                justify-content: flex-start;
            }
        }
    </style>
</head>
<body class="cms-admin-legal">
    <?php include 'footer.php'; ?>
    
    <div class="wrap legal-wrap">
        <div class="legal-page-header">
            <h1>📄 Legal Documents</h1>
            <p>Manage system-wide agreements, policies, and legal artefacts shared between the CMS and ABBIS platforms.</p>
        </div>
        
        <?php if ($message): ?>
            <div class="notice notice-success"><p><?php echo htmlspecialchars($message); ?></p></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="notice notice-error"><p><?php echo htmlspecialchars($error); ?></p></div>
        <?php endif; ?>
        
        <?php if (($action === 'edit' && $document) || $action === 'add'): ?>
            <div style="margin-bottom: 20px;">
                <a href="legal-documents.php" class="button">← Back to Documents</a>
                <h2 style="margin-top: 20px;">
                    <?php echo $action === 'add' ? 'Add New Document' : 'Edit: ' . htmlspecialchars($document['title']); ?>
                </h2>
            </div>
            
            <div class="editor-container">
                <form method="post" class="post-form">
                    <?php if ($action === 'edit' && $document): ?>
                        <input type="hidden" name="document_id" value="<?php echo $document['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label>Document Type <?php echo $action === 'add' ? '<span style="color: red;">*</span>' : ''; ?></label>
                        <?php if ($action === 'edit' && $document): ?>
                            <select name="document_type" disabled>
                                <option value="<?php echo htmlspecialchars($document['document_type']); ?>">
                                    <?php echo htmlspecialchars($documentTypes[$document['document_type']] ?? $document['document_type']); ?>
                                </option>
                            </select>
                            <p class="description" style="margin-top: 5px; color: #646970; font-size: 13px;">Document type cannot be changed after creation.</p>
                        <?php else: ?>
                            <select name="document_type" required>
                                <option value="">Select Document Type</option>
                                <?php foreach ($documentTypes as $typeKey => $typeName): ?>
                                    <option value="<?php echo htmlspecialchars($typeKey); ?>">
                                        <?php echo htmlspecialchars($typeName); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($action === 'add'): ?>
                        <div class="form-group">
                            <label>Slug</label>
                            <input type="text" name="slug" placeholder="auto-generated-from-title">
                            <p class="description" style="margin-top: 5px; color: #646970; font-size: 13px;">Leave blank to auto-generate from title. Must be unique.</p>
                        </div>
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label>Title <span style="color: red;">*</span></label>
                        <input type="text" name="title" value="<?php echo htmlspecialchars($document['title'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Content <span style="color: red;">*</span></label>
                        <textarea name="content" id="content-editor" rows="30" style="width: 100%; min-height: 400px; padding: 10px; border: 1px solid #8c8f94; border-radius: 4px; font-family: monospace; font-size: 14px;"><?php echo htmlspecialchars($document['content'] ?? ''); ?></textarea>
                        <p class="description" style="margin-top: 5px; color: #646970; font-size: 13px;">Use the rich text editor to format your legal document content.</p>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Version</label>
                            <input type="text" name="version" value="<?php echo htmlspecialchars($document['version'] ?? '1.0'); ?>" placeholder="1.0">
                        </div>
                        
                        <div class="form-group">
                            <label>Effective Date</label>
                            <input type="date" name="effective_date" value="<?php echo isset($document['effective_date']) && $document['effective_date'] ? date('Y-m-d', strtotime($document['effective_date'])) : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" name="is_active" value="1" <?php echo (isset($document) && ($document['is_active'] ?? 1)) || !isset($document) ? 'checked' : ''; ?>>
                            <span>Active (visible on website)</span>
                        </label>
                    </div>
                    
                    <p class="submit" style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb;">
                        <input type="submit" name="save_document" class="button button-primary" value="💾 <?php echo $action === 'add' ? 'Create Document' : 'Save Document'; ?>">
                        <a href="legal-documents.php" class="button">Cancel</a>
                    </p>
                </form>
            </div>
            
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const contentTextarea = document.getElementById('content-editor');
                    if (contentTextarea) {
                        ClassicEditor
                            .create(contentTextarea, {
                                toolbar: {
                                    items: [
                                        'heading', '|',
                                        'bold', 'italic', 'link', 'bulletedList', 'numberedList', '|',
                                        'outdent', 'indent', '|',
                                        'blockQuote', 'insertTable', '|',
                                        'undo', 'redo', '|',
                                        'sourceEditing'
                                    ]
                                },
                                heading: {
                                    options: [
                                        { model: 'paragraph', title: 'Paragraph', class: 'ck-heading_paragraph' },
                                        { model: 'heading1', view: 'h1', title: 'Heading 1', class: 'ck-heading_heading1' },
                                        { model: 'heading2', view: 'h2', title: 'Heading 2', class: 'ck-heading_heading2' },
                                        { model: 'heading3', view: 'h3', title: 'Heading 3', class: 'ck-heading_heading3' }
                                    ]
                                }
                            })
                            .catch(error => {
                                console.error('CKEditor initialization error:', error);
                            });
                    }
                });
            </script>
        <?php else: ?>
            <?php
            $totalDocuments = (int) ($stats['total'] ?? count($documents));
            $grouped = [];
            foreach ($documents as $doc) {
                $type = $doc['document_type'];
                if (!isset($grouped[$type])) {
                    $grouped[$type] = [];
                }
                $grouped[$type][] = $doc;
            }

            $typeIcons = [
                'drilling_agreement' => '🔧',
                'terms_of_service' => '📋',
                'privacy_policy' => '🔒',
                'warranty' => '🛡️',
                'refund' => '💰',
                'cancellation' => '❌',
                'other' => '📄'
            ];
            ?>
            <div class="legal-layout">
                <aside class="legal-sidebar">
                    <div class="sidebar-card">
                        <h3>Overview</h3>
                        <ul class="stats-list">
                            <li class="stats-item">
                                <span>Total documents</span>
                                <span><?php echo $totalDocuments; ?></span>
                            </li>
                            <li class="stats-item">
                                <span>Active</span>
                                <span><?php echo (int) ($stats['active'] ?? 0); ?></span>
                            </li>
                            <li class="stats-item">
                                <span>Inactive</span>
                                <span><?php echo (int) ($stats['inactive'] ?? 0); ?></span>
                            </li>
                            <li class="stats-item">
                                <span>Document types</span>
                                <span><?php echo (int) ($stats['types_count'] ?? 0); ?></span>
                            </li>
                            <li class="stats-item">
                                <span>Missing effective date</span>
                                <span><?php echo $undatedDocuments; ?></span>
                            </li>
                        </ul>
                        <small>Use the filters and quick actions to keep every legal asset up-to-date across ABBIS and the CMS.</small>
                    </div>
                    <div class="sidebar-card">
                        <h3>Document Types</h3>
                        <div class="type-chip-group" id="typeChipGroup">
                            <button type="button" class="type-chip active" data-type-chip="all">
                                All
                                <span class="count"><?php echo $totalDocuments; ?></span>
                            </button>
                            <?php foreach ($documentTypes as $typeKey => $typeLabel): 
                                $count = (int) ($typeCounts[$typeKey] ?? 0);
                                $chipClasses = 'type-chip';
                                if ($count === 0) {
                                    $chipClasses .= ' disabled';
                                }
                            ?>
                                <button type="button"
                                        class="<?php echo $chipClasses; ?>"
                                        data-type-chip="<?php echo htmlspecialchars($typeKey); ?>"
                                        <?php echo $count === 0 ? 'disabled' : ''; ?>>
                                    <?php echo htmlspecialchars($typeLabel); ?>
                                    <span class="count"><?php echo $count; ?></span>
                                </button>
                            <?php endforeach; ?>
                        </div>
                        <small>Select a type to focus on a specific policy family.</small>
                    </div>
                    <?php if (!empty($recentDocuments)): ?>
                        <div class="sidebar-card">
                            <h3>Recent Updates</h3>
                            <ul class="recent-list">
                                <?php foreach ($recentDocuments as $recent): ?>
                                    <li class="recent-item" data-type="<?php echo htmlspecialchars($recent['document_type']); ?>">
                                        <div class="recent-item__meta">
                                            <span class="recent-item__title"><?php echo htmlspecialchars($recent['title']); ?></span>
                                            <span class="recent-item__details">
                                                <?php echo htmlspecialchars($documentTypes[$recent['document_type']] ?? ucfirst(str_replace('_', ' ', $recent['document_type']))); ?>
                                                • v<?php echo htmlspecialchars($recent['version'] ?? '1.0'); ?>
                                                • <?php echo date('M j, Y', strtotime($recent['updated_at'])); ?>
                                            </span>
                                            <span class="recent-item__status">
                                                <?php echo $recent['is_active'] ? 'Active' : 'Inactive'; ?>
                                                <?php if (empty($recent['effective_date'])): ?> · No effective date<?php endif; ?>
                                            </span>
                                        </div>
                                        <a class="recent-item__action" href="?action=edit&id=<?php echo $recent['id']; ?>">Edit</a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </aside>
                <section class="legal-main">
                    <div class="legal-toolbar">
                        <div class="legal-toolbar__title">
                            <h2>Document Library</h2>
                            <span>Search, filter, and manage every public-facing legal asset.</span>
                        </div>
                        <div class="legal-toolbar__actions">
                            <a href="?action=add" class="button button-primary">➕ Add New Document</a>
                        </div>
                    </div>
                    <div class="legal-filters">
                        <div class="filter-field">
                            <label for="documentSearch">Search</label>
                            <input type="text" id="documentSearch" placeholder="Search by title, slug, or keyword">
                        </div>
                        <div class="filter-field">
                            <label for="filterType">Document Type</label>
                            <select id="filterType">
                                <option value="all">All types</option>
                                <?php foreach ($documentTypes as $typeKey => $typeLabel): ?>
                                    <option value="<?php echo htmlspecialchars($typeKey); ?>"
                                        <?php echo (int) ($typeCounts[$typeKey] ?? 0) === 0 ? 'disabled' : ''; ?>>
                                        <?php echo htmlspecialchars($typeLabel); ?> (<?php echo (int) ($typeCounts[$typeKey] ?? 0); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-field">
                            <label for="filterStatus">Status</label>
                            <select id="filterStatus">
                                <option value="all">All statuses</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="filter-field">
                            <label>Undated Policies</label>
                            <div class="documents-placeholder">
                                <?php echo $undatedDocuments; ?> document<?php echo $undatedDocuments === 1 ? '' : 's'; ?> missing an effective date
                            </div>
                        </div>
                    </div>
                    <div id="documentsEmptyState" class="document-empty" style="<?php echo empty($documents) ? '' : 'display: none;'; ?>">
                        <strong>No documents match your filters.</strong>
                        <div class="documents-placeholder">
                            <?php if (empty($documents)): ?>
                                Create your first policy to populate this workspace.
                            <?php else: ?>
                                Adjust the search, type, or status filters to see more results.
                            <?php endif; ?>
                        </div>
                        <div style="margin-top: 16px;">
                            <a href="?action=add" class="button button-primary">➕ Create Document</a>
                        </div>
                    </div>
                    <?php if (!empty($documents)): ?>
                        <div class="document-collection">
                            <?php foreach ($grouped as $type => $docs): ?>
                                <section class="document-section" data-section-type="<?php echo htmlspecialchars($type); ?>">
                                    <div class="document-section__header">
                                        <span class="document-section__icon" aria-hidden="true"><?php echo $typeIcons[$type] ?? '📄'; ?></span>
                                        <div>
                                            <h3 class="document-section__title"><?php echo htmlspecialchars($documentTypes[$type] ?? ucfirst(str_replace('_', ' ', $type))); ?></h3>
                                            <div class="document-section__meta">
                                                <span><?php echo count($docs); ?> document<?php echo count($docs) !== 1 ? 's' : ''; ?></span>
                                            </div>
                                        </div>
                                        <span class="document-section__count"><?php echo count($docs); ?></span>
                                    </div>
                                    <div class="documents-grid">
                                        <?php foreach ($docs as $doc):
                                            $statusKey = $doc['is_active'] ? 'active' : 'inactive';
                                            $keywords = strtolower($doc['title'] . ' ' . $doc['slug'] . ' ' . ($documentTypes[$type] ?? $type));
                                        ?>
                                            <article class="document-card <?php echo $statusKey; ?>"
                                                     data-type="<?php echo htmlspecialchars($type); ?>"
                                                     data-status="<?php echo $statusKey; ?>"
                                                     data-keywords="<?php echo htmlspecialchars($keywords); ?>">
                                                <div class="document-card__heading">
                                                    <h4 class="document-card__title"><?php echo htmlspecialchars($doc['title']); ?></h4>
                                                    <div class="document-card__badges">
                                                        <span class="document-badge badge-type">
                                                            <?php echo htmlspecialchars($documentTypes[$type] ?? ucfirst(str_replace('_', ' ', $type))); ?>
                                                        </span>
                                                        <span class="document-badge <?php echo $doc['is_active'] ? 'badge-status--active' : 'badge-status--inactive'; ?>">
                                                            <?php echo $doc['is_active'] ? 'Active' : 'Inactive'; ?>
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="document-card__meta">
                                                    <div class="document-card__meta-row">
                                                        <span>Slug · <?php echo htmlspecialchars($doc['slug']); ?></span>
                                                        <span>Version · <?php echo htmlspecialchars($doc['version'] ?? '1.0'); ?></span>
                                                        <span>Updated · <?php echo date('M j, Y', strtotime($doc['updated_at'])); ?></span>
                                                    </div>
                                                    <?php if (!empty($doc['effective_date'])): ?>
                                                        <div class="document-card__meta-row">
                                                            <span>Effective · <?php echo date('M j, Y', strtotime($doc['effective_date'])); ?></span>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="document-actions">
                                                    <a href="?action=edit&id=<?php echo $doc['id']; ?>" class="button button-primary">✏️ Edit</a>
                                                    <a href="<?php echo $baseUrl; ?>/cms/legal/<?php echo htmlspecialchars($doc['slug']); ?>" target="_blank">👁️ View</a>
                                                    <a href="<?php echo $baseUrl; ?>/cms/legal/<?php echo htmlspecialchars($doc['slug']); ?>/print" target="_blank">🖨️ Print</a>
                                                </div>
                                            </article>
                                        <?php endforeach; ?>
                                    </div>
                                </section>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
            
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    const searchInput = document.getElementById('documentSearch');
                    const typeSelect = document.getElementById('filterType');
                    const statusSelect = document.getElementById('filterStatus');
                    const typeChips = Array.from(document.querySelectorAll('[data-type-chip]'));
                    const cards = Array.from(document.querySelectorAll('.document-card'));
                    const sections = Array.from(document.querySelectorAll('.document-section'));
                    const emptyState = document.getElementById('documentsEmptyState');

                    function syncTypeChip(value) {
                        typeChips.forEach(chip => {
                            const matches = value === 'all'
                                ? chip.dataset.typeChip === 'all'
                                : chip.dataset.typeChip === value;
                            chip.classList.toggle('active', matches);
                        });
                    }

                    function filterDocuments() {
                        const searchTerm = (searchInput?.value || '').toLowerCase().trim();
                        const typeFilter = typeSelect?.value || 'all';
                        const statusFilter = statusSelect?.value || 'all';
                        let visibleCount = 0;

                        cards.forEach(card => {
                            const matchesType = typeFilter === 'all' || card.dataset.type === typeFilter;
                            const matchesStatus = statusFilter === 'all' || card.dataset.status === statusFilter;
                            const keywords = (card.dataset.keywords || '').toLowerCase();
                            const matchesSearch = !searchTerm || keywords.includes(searchTerm);
                            const shouldShow = matchesType && matchesStatus && matchesSearch;

                            card.classList.toggle('is-hidden', !shouldShow);
                            if (shouldShow) {
                                visibleCount += 1;
                            }
                        });

                        sections.forEach(section => {
                            const hasVisibleCard = Array.from(section.querySelectorAll('.document-card'))
                                .some(card => !card.classList.contains('is-hidden'));
                            section.classList.toggle('is-hidden', !hasVisibleCard);
                        });

                        if (emptyState) {
                            emptyState.style.display = visibleCount === 0 ? 'block' : 'none';
                        }
                    }

                    if (searchInput) {
                        searchInput.addEventListener('input', filterDocuments);
                    }
                    if (typeSelect) {
                        typeSelect.addEventListener('change', () => {
                            syncTypeChip(typeSelect.value);
                            filterDocuments();
                        });
                    }
                    if (statusSelect) {
                        statusSelect.addEventListener('change', filterDocuments);
                    }

                    typeChips.forEach(chip => {
                        if (chip.disabled || chip.classList.contains('disabled')) {
                            return;
                        }
                        chip.addEventListener('click', () => {
                            const value = chip.dataset.typeChip || 'all';
                            if (typeSelect) {
                                typeSelect.value = value;
                                typeSelect.dispatchEvent(new Event('change', { bubbles: true }));
                            } else {
                                syncTypeChip(value);
                                filterDocuments();
                            }
                        });
                    });

                    // Initial sync
                    syncTypeChip(typeSelect?.value || 'all');
                    filterDocuments();
                });
            </script>
        <?php endif; ?>
    </div>
</body>
</html>

