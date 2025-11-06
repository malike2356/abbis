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

// Get document for editing
$document = null;
if ($id && $action === 'edit') {
    $stmt = $pdo->prepare("SELECT * FROM cms_legal_documents WHERE id=?");
    $stmt->execute([$id]);
    $document = $stmt->fetch(PDO::FETCH_ASSOC);
}

$documentTypes = [
    'drilling_agreement' => 'Drilling Agreement',
    'terms_of_service' => 'Terms of Service',
    'privacy_policy' => 'Privacy Policy',
    'warranty' => 'Warranty Policy',
    'refund' => 'Refund Policy',
    'cancellation' => 'Cancellation Policy',
    'other' => 'Other'
];

$configStmt = $pdo->query("SELECT config_value FROM system_config WHERE config_key='company_name'");
$companyName = $configStmt->fetchColumn() ?: 'CMS Admin';
$baseUrl = '/abbis3.2';
if (defined('APP_URL')) {
    $parsed = parse_url(APP_URL);
    $baseUrl = $parsed['path'] ?? '/abbis3.2';
}

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
        .document-card { background: white; border: 1px solid #c3c4c7; padding: 20px; margin-bottom: 15px; border-radius: 4px; }
        .document-card h3 { margin-top: 0; }
        .document-meta { color: #646970; font-size: 12px; margin-top: 10px; }
        .document-actions { margin-top: 15px; display: flex; gap: 10px; }
    </style>
</head>
<body>
    <?php include 'footer.php'; ?>
    
    <div class="wrap">
        <h1>Legal Documents</h1>
        <p>Manage system-wide legal documents including drilling agreements, terms of service, and privacy policies. These documents are accessible from both CMS and ABBIS systems.</p>
        
        <?php if ($message): ?>
            <div class="notice notice-success"><p><?php echo htmlspecialchars($message); ?></p></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="notice notice-error"><p><?php echo htmlspecialchars($error); ?></p></div>
        <?php endif; ?>
        
        <?php if ($action === 'edit' && $document): ?>
            <div style="margin-bottom: 20px;">
                <a href="legal-documents.php" class="button">← Back to Documents</a>
                <h2 style="margin-top: 20px;">Edit: <?php echo htmlspecialchars($document['title']); ?></h2>
            </div>
            
            <form method="post" class="post-form" style="background: white; padding: 20px; border: 1px solid #c3c4c7;">
                <input type="hidden" name="document_id" value="<?php echo $document['id']; ?>">
                
                <div class="form-group">
                    <label>Document Type</label>
                    <select name="document_type" disabled class="regular-text">
                        <option value="<?php echo htmlspecialchars($document['document_type']); ?>">
                            <?php echo htmlspecialchars($documentTypes[$document['document_type']] ?? $document['document_type']); ?>
                        </option>
                    </select>
                    <p class="description">Document type cannot be changed after creation.</p>
                </div>
                
                <div class="form-group">
                    <label>Title <span style="color: red;">*</span></label>
                    <input type="text" name="title" value="<?php echo htmlspecialchars($document['title']); ?>" required class="large-text">
                </div>
                
                <div class="form-group">
                    <label>Content <span style="color: red;">*</span></label>
                    <textarea name="content" id="content-editor" rows="30" class="large-text"><?php echo htmlspecialchars($document['content']); ?></textarea>
                    <p class="description">Use the rich text editor to format your legal document content.</p>
                </div>
                
                <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="form-group">
                        <label>Version</label>
                        <input type="text" name="version" value="<?php echo htmlspecialchars($document['version'] ?? '1.0'); ?>" class="regular-text" placeholder="1.0">
                    </div>
                    
                    <div class="form-group">
                        <label>Effective Date</label>
                        <input type="date" name="effective_date" value="<?php echo $document['effective_date'] ? date('Y-m-d', strtotime($document['effective_date'])) : ''; ?>" class="regular-text">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_active" value="1" <?php echo ($document['is_active'] ?? 1) ? 'checked' : ''; ?>>
                        Active (visible on website)
                    </label>
                </div>
                
                <p class="submit">
                    <input type="submit" name="save_document" class="button button-primary" value="Save Document">
                    <a href="legal-documents.php" class="button">Cancel</a>
                </p>
            </form>
            
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
            <a href="?action=add" class="page-title-action">Add New Document</a>
            
            <div style="margin-top: 20px;">
                <?php if (empty($documents)): ?>
                    <div style="text-align: center; padding: 40px; color: #646970;">
                        No legal documents found. <a href="?action=add">Create your first document</a>.
                    </div>
                <?php else: ?>
                    <?php 
                    $grouped = [];
                    foreach ($documents as $doc) {
                        $type = $doc['document_type'];
                        if (!isset($grouped[$type])) {
                            $grouped[$type] = [];
                        }
                        $grouped[$type][] = $doc;
                    }
                    ?>
                    
                    <?php foreach ($grouped as $type => $docs): ?>
                        <h2 style="margin-top: 30px; margin-bottom: 15px;"><?php echo htmlspecialchars($documentTypes[$type] ?? ucfirst($type)); ?></h2>
                        
                        <?php foreach ($docs as $doc): ?>
                            <div class="document-card">
                                <h3><?php echo htmlspecialchars($doc['title']); ?></h3>
                                <div class="document-meta">
                                    <strong>Slug:</strong> <?php echo htmlspecialchars($doc['slug']); ?> | 
                                    <strong>Version:</strong> <?php echo htmlspecialchars($doc['version'] ?? '1.0'); ?> | 
                                    <strong>Status:</strong> <span style="color: <?php echo $doc['is_active'] ? '#00a32a' : '#d63638'; ?>;">
                                        <?php echo $doc['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                    <?php if ($doc['effective_date']): ?>
                                        | <strong>Effective:</strong> <?php echo date('M j, Y', strtotime($doc['effective_date'])); ?>
                                    <?php endif; ?>
                                </div>
                                <div class="document-actions">
                                    <a href="?action=edit&id=<?php echo $doc['id']; ?>" class="button button-primary">Edit</a>
                                    <a href="<?php echo $baseUrl; ?>/cms/legal/<?php echo htmlspecialchars($doc['slug']); ?>" target="_blank" class="button">View</a>
                                    <a href="<?php echo $baseUrl; ?>/cms/legal/<?php echo htmlspecialchars($doc['slug']); ?>/print" target="_blank" class="button">Print</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>

