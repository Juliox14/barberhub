import test from 'node:test';
import assert from 'node:assert/strict';
import { createPlatformSidebar } from '../../resources/js/platform-sidebar.js';

function harness({ collapsed = true, desktop = true, open = false } = {}) {
    const values = new Map([['bh-sidebar-collapsed', String(collapsed)]]);
    const storage = { getItem: (key) => values.get(key), setItem: (key, value) => values.set(key, value) };
    const sidebar = createPlatformSidebar({
        storage,
        matchMedia: (query) => {
            assert.equal(query, '(min-width: 1024px)');
            return { matches: desktop };
        },
    });
    const details = { open };
    const event = {
        currentTarget: { parentElement: details },
        defaultPrevented: false,
        preventDefault() { this.defaultPrevented = true; },
    };

    return { sidebar, values, details, event };
}

for (const open of [false, true]) {
    test(`collapsed desktop security click expands the sidebar and leaves the group open (initially ${open})`, () => {
        const app = harness({ open });
        assert.equal(app.sidebar.sidebarCollapsed, true);
        app.sidebar.openSecurityGroup(app.event);
        assert.equal(app.sidebar.sidebarCollapsed, false);
        assert.equal(app.details.open, true);
        assert.equal(app.values.get('bh-sidebar-collapsed'), 'false');
        assert.equal(app.event.defaultPrevented, true);
        assert.equal(app.sidebar.menuOpen, false);
    });
}

test('expanded desktop keeps native disclosure behavior and the saved preference', () => {
    const app = harness({ collapsed: false, open: true });
    app.sidebar.openSecurityGroup(app.event);
    assert.equal(app.event.defaultPrevented, false);
    assert.equal(app.details.open, true);
    assert.equal(app.sidebar.sidebarCollapsed, false);
    assert.equal(app.values.get('bh-sidebar-collapsed'), 'false');
});

test('mobile keeps native disclosure behavior despite a stored desktop collapse preference', () => {
    const app = harness({ desktop: false });
    app.sidebar.menuOpen = true;
    app.sidebar.openSecurityGroup(app.event);
    assert.equal(app.event.defaultPrevented, false);
    assert.equal(app.details.open, false);
    assert.equal(app.sidebar.sidebarCollapsed, true);
    assert.equal(app.values.get('bh-sidebar-collapsed'), 'true');
    assert.equal(app.sidebar.menuOpen, true);
});
