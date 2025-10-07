(function () {
    if (typeof window === 'undefined') {
        return;
    }

    const body = document.body;
    const sidebar = document.querySelector('.dashboard-sidebar');
    const backdrop = document.querySelector('[data-sidebar-backdrop]');
    const toggles = Array.from(document.querySelectorAll('[data-sidebar-toggle]'));

    if (!sidebar || toggles.length === 0) {
        return;
    }

    const closeSidebar = () => {
        body.classList.remove('sidebar-open');
    };

    const openSidebar = () => {
        body.classList.add('sidebar-open');
    };

    const handleToggle = (event) => {
        event.preventDefault();
        if (body.classList.contains('sidebar-open')) {
            closeSidebar();
        } else {
            openSidebar();
        }
    };

    toggles.forEach((button) => {
        button.addEventListener('click', handleToggle);
    });

    if (backdrop) {
        backdrop.addEventListener('click', closeSidebar);
    }

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeSidebar();
        }
    });

    const syncSidebar = () => {
        if (window.innerWidth >= 1024) {
            body.classList.remove('sidebar-open');
        }
    };

    window.addEventListener('resize', syncSidebar);
    syncSidebar();
})();
