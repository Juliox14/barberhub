import test from 'node:test';
import assert from 'node:assert/strict';
import { effect, reactive, stop as stopEffect } from '@vue/reactivity';
import { createTenantBrandingEditor, readableForeground } from '../../resources/js/tenant-branding.js';

const defaults = {
    primary: '#496C35',
    accent: '#B8885A',
    primaryForeground: '#FFFFFF',
    accentForeground: '#000000',
    description: 'Una barbería cercana',
    shopName: 'Barbería Aurora',
    logoUrl: null,
    coverUrl: null,
};

function createEditor(options = {}) {
    const created = [];
    const revoked = [];
    const editor = createTenantBrandingEditor({
        initial: defaults,
        createObjectURL: (file) => {
            const url = `blob:${file.name}:${created.length}`;
            created.push(url);
            return url;
        },
        revokeObjectURL: (url) => revoked.push(url),
        ...options,
    });

    return { editor, created, revoked };
}

function observeSnapshots(editor, snapshot) {
    const snapshots = [];
    const runner = effect(() => snapshots.push(snapshot(editor)));

    return { snapshots, stop: () => stopEffect(runner) };
}

test('Alpine-reactive color and presentation snapshots update through editor methods', () => {
    const { editor } = createEditor();
    const state = reactive(editor);
    const { snapshots, stop } = observeSnapshots(state, (current) => ({
        primary: current.values.primary,
        draft: current.colorDrafts.primary,
        foreground: current.values.primaryForeground,
        description: current.values.description,
    }));

    state.setColor('primary', '#F5F5F5');
    state.setPresentation('Presentación reactiva');

    assert.ok(snapshots.length >= 3);
    assert.deepEqual(snapshots.at(-1), {
        primary: '#F5F5F5',
        draft: '#F5F5F5',
        foreground: '#000000',
        description: 'Presentación reactiva',
    });
    stop();
});

test('Alpine-reactive viewport snapshots update when preview mode changes', () => {
    const { editor } = createEditor();
    const state = reactive(editor);
    const { snapshots, stop } = observeSnapshots(state, (current) => current.previewWidth);

    state.setPreviewMode('mobile');

    assert.ok(snapshots.length >= 2);
    assert.equal(snapshots.at(-1), '375px');
    state.setPreviewMode('desktop');
    assert.equal(snapshots.at(-1), '100%');
    stop();
});

test('Alpine-reactive file snapshots update for selection and replacement', () => {
    const { editor } = createEditor();
    const state = reactive(editor);
    const { snapshots, stop } = observeSnapshots(state, (current) => ({
        url: current.preview.logoUrl,
        file: current.selectedFiles.logo?.name ?? null,
    }));

    state.selectFile('logo', { name: 'primero.png' });
    state.selectFile('logo', { name: 'segundo.png' });

    assert.ok(snapshots.some((snapshot) => snapshot.url === 'blob:primero.png:0' && snapshot.file === 'primero.png'));
    assert.deepEqual(snapshots.at(-1), { url: 'blob:segundo.png:1', file: 'segundo.png' });
    stop();
});

test('Alpine-reactive reset and discard snapshots reflect current draft and restored saved values', () => {
    const { editor } = createEditor();
    const state = reactive(editor);
    const { snapshots, stop } = observeSnapshots(state, (current) => ({
        primary: current.values.primary,
        accent: current.values.accent,
        foreground: current.values.accentForeground,
        description: current.values.description,
        logo: current.preview.logoUrl,
        selectedLogo: current.selectedFiles.logo?.name ?? null,
    }));

    state.setColor('primary', '#123456');
    state.setColor('accent', '#FFFFFF');
    state.setPresentation('Texto temporal');
    state.selectFile('logo', { name: 'temporal.png' });
    state.resetColors();

    assert.deepEqual(snapshots.at(-1), {
        primary: defaults.primary,
        accent: defaults.accent,
        foreground: '#000000',
        description: 'Texto temporal',
        logo: 'blob:temporal.png:0',
        selectedLogo: 'temporal.png',
    });

    state.setColor('primary', '#ABCDEF');
    state.setPresentation('Se descarta');
    state.setPreviewMode('mobile');
    state.discard();

    assert.ok(snapshots.some((snapshot) => snapshot.description === 'Texto temporal' && snapshot.selectedLogo === 'temporal.png'));
    assert.deepEqual(snapshots.at(-1), {
        primary: defaults.primary,
        accent: defaults.accent,
        foreground: defaults.accentForeground,
        description: defaults.description,
        logo: null,
        selectedLogo: null,
    });
    stop();
});

test('derives readable contrast for light and dark brand backgrounds', () => {
    assert.equal(readableForeground('#F5F5F5'), '#000000');
    assert.equal(readableForeground('#123456'), '#FFFFFF');
    assert.equal(readableForeground('not-a-color'), '#000000');
});

test('draft edits do not mutate saved values and only canonical colors reach the preview', () => {
    const { editor } = createEditor();
    editor.setColor('primary', '#F5F5F5');
    assert.equal(editor.values.primary, '#F5F5F5');
    assert.equal(editor.values.primaryForeground, '#000000');
    assert.equal(editor.saved.primary, '#496C35');

    editor.setColor('primary', '#12');
    assert.equal(editor.colorDrafts.primary, '#12');
    assert.equal(editor.values.primary, '#F5F5F5');
    assert.equal(editor.preview.primary, '#F5F5F5');
});

test('invalid returned form drafts stay out of CSS and discard restores persisted values', () => {
    const { editor } = createEditor({ drafts: {
        primary: 'red; background:url(javascript:alert(1))',
        accent: '#F5F5F5',
        description: '<script>alert(1)</script>',
    } });
    assert.equal(editor.colorDrafts.primary, 'red; background:url(javascript:alert(1))');
    assert.equal(editor.values.primary, '#496C35');
    assert.equal(editor.values.accent, '#F5F5F5');
    assert.equal(editor.values.accentForeground, '#000000');
    assert.equal(editor.preview.description, '<script>alert(1)</script>');

    editor.discard();
    assert.equal(editor.values.primary, defaults.primary);
    assert.equal(editor.values.accent, defaults.accent);
    assert.equal(editor.values.description, defaults.description);
});

test('preview viewport switches between bounded mobile and desktop widths', () => {
    const { editor } = createEditor();
    editor.setPreviewMode('mobile');
    assert.equal(editor.previewMode, 'mobile');
    assert.equal(editor.previewWidth, '375px');
    editor.setPreviewMode('desktop');
    assert.equal(editor.previewMode, 'desktop');
    assert.equal(editor.previewWidth, '100%');
});

test('file previews use object URLs and discard revokes URLs and restores saved identity', () => {
    const { editor, created, revoked } = createEditor();
    editor.selectFile('logo', { name: 'nuevo-logo.png' });
    editor.selectFile('cover', { name: 'portada.png' });
    assert.deepEqual(editor.selectedFiles, { logo: { name: 'nuevo-logo.png' }, cover: { name: 'portada.png' } });
    assert.equal(editor.preview.logoUrl, 'blob:nuevo-logo.png:0');
    assert.equal(created.join(','), 'blob:nuevo-logo.png:0,blob:portada.png:1');
    assert.equal(editor.preview.logoUrl, created[0]);
    assert.equal(editor.preview.coverUrl, created[1]);

    editor.setPresentation('Borrador sin guardar');
    editor.discard();
    assert.equal(revoked.join(','), created.join(','));
    assert.equal(editor.values.description, defaults.description);
    assert.equal(editor.preview.logoUrl, null);
    assert.equal(editor.preview.coverUrl, null);
    assert.equal(editor.selectedFiles.logo, null);
    assert.equal(editor.selectedFiles.cover, null);
});

test('replacing and disposing file previews clean up every object URL', () => {
    const { editor, created, revoked } = createEditor();
    editor.selectFile('logo', { name: 'primero.png' });
    editor.selectFile('logo', { name: 'segundo.png' });
    editor.dispose();
    assert.equal(revoked.join(','), created.join(','));
});

test('resetting colors changes only the draft and never sends a network request', () => {
    const { editor } = createEditor();
    editor.setColor('primary', '#123456');
    editor.setColor('accent', '#FFFFFF');
    editor.selectFile('logo', { name: 'nuevo-logo.png' });
    editor.resetColors();

    assert.equal(editor.values.primary, '#496C35');
    assert.equal(editor.values.accent, '#B8885A');
    assert.equal(editor.saved.primary, '#496C35');
    assert.equal(editor.selectedFiles.logo.name, 'nuevo-logo.png');
    assert.equal(editor.preview.logoUrl, 'blob:nuevo-logo.png:0');
});
