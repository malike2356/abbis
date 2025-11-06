<?php
/**
 * ABBIS Legal Documents Viewer
 * Access legal documents from ABBIS system
 */
session_start();
require_once '../config/app.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

$pdo = getDBConnection();

// Ensure legal documents table exists
try {
    $pdo->query("SELECT * FROM cms_legal_documents LIMIT 1");
} catch (PDOException $e) {
    // Try to create table
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
    } catch (PDOException $e2) {}
}

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;

// Get all legal documents
try {
    $documents = $pdo->query("SELECT * FROM cms_legal_documents WHERE is_active=1 ORDER BY document_type, title")->fetchAll();
} catch (PDOException $e) {
    $documents = [];
}

// Get specific document
$document = null;
if ($id && $action === 'view') {
    try {
        $stmt = $pdo->prepare("SELECT * FROM cms_legal_documents WHERE id=? AND is_active=1 LIMIT 1");
        $stmt->execute([$id]);
        $document = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $document = null;
    }
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

$page_title = 'Legal Documents';
require_once '../includes/header.php';
?>
<div class="container-fluid">
    <div class="page-header">
        <h1>Legal Documents</h1>
        <p>View and access system-wide legal documents including drilling agreements, terms of service, and privacy policies.</p>
    </div>
    
    <?php if ($action === 'view' && $document): ?>
        <div class="card">
            <div class="card-header">
                <h2><?php echo htmlspecialchars($document['title']); ?></h2>
                <div style="color: #646970; font-size: 14px; margin-top: 10px;">
                    <?php if ($document['version']): ?>
                        <strong>Version:</strong> <?php echo htmlspecialchars($document['version']); ?> | 
                    <?php endif; ?>
                    <?php if ($document['effective_date']): ?>
                        <strong>Effective:</strong> <?php echo date('M j, Y', strtotime($document['effective_date'])); ?> | 
                    <?php endif; ?>
                    <strong>Last Updated:</strong> <?php echo date('M j, Y', strtotime($document['updated_at'])); ?>
                </div>
            </div>
            <div class="card-body" style="line-height: 1.8; max-width: 900px; margin: 0 auto;">
                <?php echo $document['content']; ?>
            </div>
            <div class="card-footer">
                <a href="legal-documents.php" class="btn btn-outline">← Back to Documents</a>
                <a href="../cms/legal/<?php echo htmlspecialchars($document['slug']); ?>/print" target="_blank" class="btn btn-primary">Print Document</a>
            </div>
        </div>
    <?php else: ?>
        <div class="row">
            <?php if (empty($documents)): ?>
                <div class="col-12">
                    <div class="card">
                        <div class="card-body text-center" style="padding: 40px;">
                            <p style="color: #646970;">No legal documents available.</p>
                            <p><a href="../cms/admin/legal-documents.php" class="btn btn-primary">Manage Documents in CMS</a></p>
                        </div>
                    </div>
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
                    <div class="col-12">
                        <h3 style="margin-top: 30px; margin-bottom: 15px;"><?php echo htmlspecialchars($documentTypes[$type] ?? ucfirst($type)); ?></h3>
                    </div>
                    
                    <?php foreach ($docs as $doc): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="card" style="height: 100%;">
                                <div class="card-body">
                                    <h4><?php echo htmlspecialchars($doc['title']); ?></h4>
                                    <p style="color: #646970; font-size: 13px; margin: 10px 0;">
                                        Version <?php echo htmlspecialchars($doc['version'] ?? '1.0'); ?>
                                        <?php if ($doc['effective_date']): ?>
                                            | Effective: <?php echo date('M j, Y', strtotime($doc['effective_date'])); ?>
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <div class="card-footer">
                                    <a href="?action=view&id=<?php echo $doc['id']; ?>" class="btn btn-primary btn-sm">View Document</a>
                                    <a href="../cms/legal/<?php echo htmlspecialchars($doc['slug']); ?>" target="_blank" class="btn btn-outline btn-sm">Open in CMS</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <div style="margin-top: 30px; padding: 20px; background: #f0f9ff; border-left: 4px solid #2271b1; border-radius: 4px;">
            <p style="margin: 0;"><strong>Note:</strong> To edit legal documents, go to <a href="../cms/admin/legal-documents.php">CMS Admin → Legal Documents</a>.</p>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>

