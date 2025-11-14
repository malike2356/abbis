<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
$auth->requireAuth();

// Module-specific PHP code here
$module_title = "Module Title";
$module_description = "Module description";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $module_title; ?> - ABBIS</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container-fluid">
        <main class="main-content">
            <div class="page-header">
                <h1><?php echo $module_title; ?></h1>
                <p><?php echo $module_description; ?></p>
            </div>

            <!-- Module content goes here -->
            <div class="dashboard-card">
                <h2><?php echo $module_title; ?> Content</h2>
                <p>This module is functioning correctly.</p>
                
                <div class="action-buttons">
                    <a href="../index.php" class="action-btn">
                        <span class="action-icon">📊</span>
                        <span>Dashboard</span>
                    </a>
                    <a href="field-reports.php" class="action-btn">
                        <span class="action-icon">📝</span>
                        <span>Field Reports</span>
                    </a>
                </div>
            </div>
        </main>
    </div>

    <script src="../assets/js/main.js"></script>
</body>
</html>
