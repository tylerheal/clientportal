(function () {
    if (typeof window === 'undefined') {
        return;
    }

    const doc = document;
    const mobileMenu = doc.querySelector('[data-mobile-menu]');
    const menuButtons = Array.from(doc.querySelectorAll('[data-mobile-menu-toggle]'));

    const closeMenu = () => {
        if (mobileMenu) {
            mobileMenu.classList.remove('open');
        }
    };

    const openMenu = () => {
        if (mobileMenu) {
            mobileMenu.classList.add('open');
        }
    };

    menuButtons.forEach((btn) => {
        btn.addEventListener('click', (event) => {
            event.preventDefault();
            if (!mobileMenu) {
                return;
            }
            if (mobileMenu.classList.contains('open')) {
                closeMenu();
            } else {
                openMenu();
            }
        });
    });

    if (mobileMenu) {
        mobileMenu.addEventListener('click', (event) => {
            if (event.target === mobileMenu) {
                closeMenu();
            }
        });
    }

    doc.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeMenu();
        }
    });

    // Simple dropdown toggles for elements marked with data-menu
    const menus = Array.from(doc.querySelectorAll('[data-menu]'));
    menus.forEach((menu) => {
        const toggle = menu.querySelector('[data-menu-toggle]');
        if (!toggle) {
            return;
        }
        toggle.addEventListener('click', (event) => {
            event.preventDefault();
            menu.classList.toggle('open');
        });
    });

    doc.addEventListener('click', (event) => {
        menus.forEach((menu) => {
            if (!menu.contains(event.target)) {
                menu.classList.remove('open');
            }
        });
    });
})();
