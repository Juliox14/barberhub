export function createPlatformSidebar({
    storage = window.localStorage,
    matchMedia = (query) => window.matchMedia(query),
} = {}) {
    return {
        menuOpen: false,
        sidebarCollapsed: storage.getItem('bh-sidebar-collapsed') === 'true',

        openSecurityGroup(event) {
            if (!this.sidebarCollapsed || !matchMedia('(min-width: 1024px)').matches) {
                return;
            }

            event.preventDefault();
            this.sidebarCollapsed = false;
            storage.setItem('bh-sidebar-collapsed', 'false');
            event.currentTarget.parentElement.open = true;
        },
    };
}
