<?php
// Standard module template - include this in all modules
function renderModuleHeader($title, $description = "") {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo htmlspecialchars($title); ?> - ABBIS</title>
        <link rel="stylesheet" href="../assets/css/styles.css">
    </head>
    <body>
        <header>
            <div class="container">
                <div class="header-content">
                    <div class="logo">
                        <span class="logo-mark">⛏️</span>
                        <h1>ABBIS</h1>
                    </div>
                    <div class="header-actions">
                        <span class="user-info">Welcome, <?php echo $_SESSION['full_name']; ?> (<?php echo $_SESSION['role']; ?>)</span>
                        <a href="../modules/config.php" class="btn btn-outline">Config</a>
                        <a href="../index.php" class="btn btn-outline">Dashboard</a>
                        <a href="../modules/field-reports-list.php" class="btn btn-outline">Search</a>
                        <button class="theme-toggle" id="themeToggle" title="Toggle theme">🌙</button>
                        <a href="../logout.php" class="btn btn-outline">Logout</a>
                    </div>
                </div>
            </div>
        </header>

        <main class="container">
            <div class="main-content">
                <div class="page-header">
                    <h1><?php echo htmlspecialchars($title); ?></h1>
                    <?php if (!empty($description)): ?>
                        <p><?php echo htmlspecialchars($description); ?></p>
                    <?php endif; ?>
                </div>
    <?php
}

function renderModuleFooter() {
    ?>
            </div>
        </main>

        <footer class="footer">
            <div class="container">
                <p>ABBIS - Advanced Borehole Business Intelligence System v2.0 | Developed by Velox PSI Limited. Copyright &copy; 2024.</p>
                <div style="font-size:12px; margin-top:5px;">
                    <span id="storageStatus">System: Active</span> |
                    <span id="syncStatus">Database: Connected</span>
                </div>
            </div>
        </footer>

        <script src="../assets/js/main.js"></script>
        <script>
            // Theme toggle functionality
            document.getElementById('themeToggle')?.addEventListener('click', function() {
                document.body.classList.toggle('dark');
                localStorage.setItem('theme', document.body.classList.contains('dark') ? 'dark' : 'light');
            });

            // Load saved theme
            if (localStorage.getItem('theme') === 'dark') {
                document.body.classList.add('dark');
            }
        </script>
    </body>
    </html>
    <?php
}
?>