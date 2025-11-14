document.addEventListener('DOMContentLoaded', function () {
    const header = document.querySelector('.cms-global-header');
    const toggle = document.getElementById('cmsNavToggle');
    const nav = document.getElementById('cmsPrimaryNav');
    const backdrop = document.getElementById('cmsNavBackdrop');
    const headerBase = header ? header.getAttribute('data-base') || '' : '';

    document.body.style.setProperty('padding-top', '0px', 'important');

    if (!header || !toggle || !nav || !backdrop) {
        return;
    }

    const ensureAdminLinkTarget = () => {
        const adminLink = header.querySelector('[aria-label="Admin login"]');
        if (!adminLink) {
            return;
        }
        const origin = window.location.origin.replace(/\/+$/, '');
        const basePath = headerBase ? headerBase.replace(/\/+$/, '') : '';
        const target = (/^https?:/i.test(basePath) ? basePath : (origin + basePath)).replace(/\/+$/, '') + '/cms/admin/';
        adminLink.href = target;
    };
    ensureAdminLinkTarget();

    const body = document.body;
    const mediaQuery = window.matchMedia('(max-width: 992px)');

    const closeNav = () => {
        nav.classList.remove('is-open');
        toggle.classList.remove('is-active');
        toggle.setAttribute('aria-expanded', 'false');
        backdrop.classList.remove('is-visible');
        backdrop.setAttribute('aria-hidden', 'true');
        body.classList.remove('cms-nav-open');
    };

    const openNav = () => {
        nav.classList.add('is-open');
        toggle.classList.add('is-active');
        toggle.setAttribute('aria-expanded', 'true');
        backdrop.classList.add('is-visible');
        backdrop.setAttribute('aria-hidden', 'false');
        body.classList.add('cms-nav-open');
        nav.focus({ preventScroll: true });
    };

    const toggleNav = () => {
        if (nav.classList.contains('is-open')) {
            closeNav();
        } else {
            openNav();
        }
    };

    const enhanceSubmenus = () => {
        const items = nav.querySelectorAll('.cms-nav-item');
        items.forEach((item) => {
            const submenu = item.querySelector(':scope > .cms-submenu');
            const link = item.querySelector(':scope > .cms-nav-link');

            if (!submenu || !link) {
                return;
            }

            item.classList.add('has-submenu');
            link.setAttribute('aria-haspopup', 'true');
            link.setAttribute('aria-expanded', 'false');

            link.addEventListener('click', (event) => {
                if (!mediaQuery.matches) {
                    return;
                }

                event.preventDefault();
                const isOpen = item.classList.toggle('submenu-open');
                link.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            });
        });
    };

    const collapseMobileSubmenus = () => {
        nav.querySelectorAll('.cms-nav-item.submenu-open').forEach((item) => {
            item.classList.remove('submenu-open');
            const link = item.querySelector(':scope > .cms-nav-link');
            if (link) {
                link.setAttribute('aria-expanded', 'false');
            }
        });
    };

    toggle.addEventListener('click', toggleNav);
    backdrop.addEventListener('click', closeNav);

    window.addEventListener('resize', () => {
        if (!mediaQuery.matches) {
            closeNav();
            collapseMobileSubmenus();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && nav.classList.contains('is-open')) {
            closeNav();
        }
    });

    enhanceSubmenus();
});

