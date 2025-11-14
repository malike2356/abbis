<?php
// This ensures the sidebar persists on all pages
if (!defined('FOOTER_OUTPUT')) {
    define('FOOTER_OUTPUT', true);
?>
<script>
function toggleMobileMenu() {
    const sidebar = document.getElementById('admin-sidebar');
    const overlay = document.getElementById('mobile-sidebar-overlay');

    if (!sidebar) {
        return;
    }

    const isOpen = sidebar.classList.toggle('is-open');

    if (overlay) {
        overlay.classList.toggle('active', isOpen);
    }

    document.body.classList.toggle('sidebar-open', isOpen);
}

window.addEventListener('resize', function() {
    if (window.innerWidth > 1024) {
        const sidebar = document.getElementById('admin-sidebar');
        const overlay = document.getElementById('mobile-sidebar-overlay');
        if (sidebar) {
            sidebar.classList.remove('is-open');
        }
        if (overlay) {
            overlay.classList.remove('active');
        }
        document.body.classList.remove('sidebar-open');
    }
});

document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('admin-sidebar');
    if (!sidebar) {
        return;
    }

    sidebar.querySelectorAll('a').forEach(link => {
        link.addEventListener('click', () => {
            if (window.innerWidth <= 1024) {
                setTimeout(() => {
                    const overlay = document.getElementById('mobile-sidebar-overlay');
                    sidebar.classList.remove('is-open');
                    if (overlay) {
                        overlay.classList.remove('active');
                    }
                    document.body.classList.remove('sidebar-open');
                }, 150);
            }
        });
    });
});
</script>
<?php } ?>

