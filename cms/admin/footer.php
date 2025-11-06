<?php
// This ensures the sidebar persists on all pages
if (!defined('FOOTER_OUTPUT')) {
    define('FOOTER_OUTPUT', true);
?>
<style>
    /* Ensure body has proper spacing for fixed header and sidebar */
    body {
        margin-left: 160px !important;
        margin-top: 32px !important;
        padding-bottom: 20px !important;
    }
    
    /* Ensure wrap doesn't overlap with sidebar */
    .wrap {
        margin-left: 0 !important;
    }
    
    /* Ensure sidebar is always visible and positioned correctly - PERSISTS ON ALL PAGES */
    .admin-sidebar {
        display: block !important;
        position: fixed !important;
        left: 0 !important;
        top: 32px !important;
        bottom: 0 !important;
        width: 160px !important;
        z-index: 998 !important;
        visibility: visible !important;
        opacity: 1 !important;
    }
    
    /* Ensure header is fixed and PERSISTS ON ALL PAGES */
    .admin-header {
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        right: 0 !important;
        z-index: 999 !important;
        visibility: visible !important;
        opacity: 1 !important;
    }
    
    /* Mobile responsive - hide sidebar on small screens */
    @media (max-width: 782px) {
        body { margin-left: 0 !important; }
        .admin-sidebar { display: none !important; }
        .admin-header { padding-left: 20px !important; }
    }
</style>
<?php } ?>

