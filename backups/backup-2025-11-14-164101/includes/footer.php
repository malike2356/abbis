        </div>
    </main>

    <!-- Footer -->
    <footer class="footer">
        <div class="container-fluid">
            <div class="footer-content" style="display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;">
                <div style="display:flex; gap:12px; align-items:center;">
                    <p>&copy; <?php echo date('Y'); ?> ABBIS. All rights reserved.</p>
                    <p>Version <?php echo APP_VERSION; ?></p>
                </div>
                <nav style="display:flex; gap:12px; font-size:14px;">
                    <a href="<?php echo (isset($_SESSION['is_module']) && $_SESSION['is_module']) ? '' : 'modules/'; ?>terms.php">Terms</a>
                    <span>•</span>
                    <a href="<?php echo (isset($_SESSION['is_module']) && $_SESSION['is_module']) ? '' : 'modules/'; ?>privacy-policy.php">Privacy</a>
                    <span>•</span>
                    <a href="<?php echo (isset($_SESSION['is_module']) && $_SESSION['is_module']) ? '' : 'modules/'; ?>cookie-policy.php">Cookies</a>
                    <span>•</span>
                    <a href="<?php echo (isset($_SESSION['is_module']) && $_SESSION['is_module']) ? '' : 'modules/'; ?>policies.php">Policies</a>
                </nav>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="<?php echo (isset($_SESSION['is_module']) && $_SESSION['is_module']) ? '../' : ''; ?>assets/js/main.js"></script>
    <script src="<?php echo (isset($_SESSION['is_module']) && $_SESSION['is_module']) ? '../' : ''; ?>assets/js/keyboard-shortcuts.js"></script>
    <script src="<?php echo (isset($_SESSION['is_module']) && $_SESSION['is_module']) ? '../' : ''; ?>assets/js/realtime-updates.js"></script>
    <script src="<?php echo (isset($_SESSION['is_module']) && $_SESSION['is_module']) ? '../' : ''; ?>assets/js/advanced-search.js"></script>
    <script src="<?php echo (isset($_SESSION['is_module']) && $_SESSION['is_module']) ? '../' : ''; ?>assets/js/ai-assistant.js"></script>
    <?php if (isset($additional_js)): ?>
        <?php foreach ($additional_js as $js): ?>
            <script src="<?php echo e($js); ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
<!-- Advanced Search Modal -->
<div id="advancedSearchModal" style="
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
    z-index: 10000;
    align-items: center;
    justify-content: center;
">
    <div style="
        background: var(--card);
        border-radius: 12px;
        padding: 30px;
        max-width: 800px;
        width: 90%;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    ">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2 style="margin: 0; color: var(--text);">🔍 Advanced Search</h2>
            <button onclick="closeAdvancedSearch()" style="
                background: none;
                border: none;
                font-size: 24px;
                color: var(--secondary);
                cursor: pointer;
                padding: 0;
                width: 32px;
                height: 32px;
                display: flex;
                align-items: center;
                justify-content: center;
                border-radius: 6px;
            " onmouseover="this.style.background='var(--bg)';" onmouseout="this.style.background='none';">
                ×
            </button>
        </div>
        
        <form method="GET" action="<?php echo $is_module ? '' : 'modules/'; ?>search.php">
            <div style="display: grid; gap: 20px;">
                <div>
                    <label class="form-label">Search Query *</label>
                    <input type="text" name="q" class="form-control" placeholder="Enter search term..." required autofocus>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div>
                        <label class="form-label">Search Type</label>
                        <select name="type" class="form-control">
                            <option value="all">All Types</option>
                            <option value="field_report">Field Reports</option>
                            <option value="client">Clients</option>
                            <option value="worker">Workers</option>
                            <option value="maintenance">Maintenance</option>
                            <option value="asset">Assets</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="form-label">Status</label>
                        <select name="status" class="form-control">
                            <option value="">All Statuses</option>
                            <option value="active">Active</option>
                            <option value="completed">Completed</option>
                            <option value="pending">Pending</option>
                        </select>
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div>
                        <label class="form-label">Date From</label>
                        <input type="date" name="date_from" class="form-control">
                    </div>
                    
                    <div>
                        <label class="form-label">Date To</label>
                        <input type="date" name="date_to" class="form-control">
                    </div>
                </div>
                
                <div>
                    <label class="form-label">Client</label>
                    <select name="client_id" class="form-control">
                        <option value="">All Clients</option>
                        <?php
                        try {
                            $pdo = getDBConnection();
                            $stmt = $pdo->query("SELECT id, client_name FROM clients ORDER BY client_name");
                            while ($client = $stmt->fetch()): ?>
                                <option value="<?php echo $client['id']; ?>">
                                    <?php echo e($client['client_name']); ?>
                                </option>
                            <?php endwhile;
                        } catch (PDOException $e) {}
                        ?>
                    </select>
                </div>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 10px;">
                    <button type="button" onclick="closeAdvancedSearch()" class="btn btn-outline">Cancel</button>
                    <button type="submit" class="btn btn-primary">🔍 Search</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function handleGlobalSearch(query) {
    if (query.trim() === '') return;
    window.location.href = '<?php echo $is_module ? '' : 'modules/'; ?>search.php?q=' + encodeURIComponent(query);
}

function openAdvancedSearch() {
    document.getElementById('advancedSearchModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeAdvancedSearch() {
    document.getElementById('advancedSearchModal').style.display = 'none';
    document.body.style.overflow = '';
}

// Close modal on outside click
document.getElementById('advancedSearchModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeAdvancedSearch();
    }
});

// Close modal on ESC key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeAdvancedSearch();
    }
});
</script>

</body>
</html>

