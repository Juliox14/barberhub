const COLOR_PATTERN = /^#[0-9A-Fa-f]{6}$/;
const DEFAULT_COLORS = { primary: '#496C35', accent: '#B8885A' };

function canonicalColor(color) {
    return typeof color === 'string' && COLOR_PATTERN.test(color) ? color.toUpperCase() : null;
}

export function readableForeground(color) {
    const canonical = canonicalColor(color);
    if (!canonical) {
        return '#000000';
    }

    const channels = [1, 3, 5].map((offset) => Number.parseInt(canonical.slice(offset, offset + 2), 16) / 255);
    const [red, green, blue] = channels.map((channel) => channel <= 0.04045
        ? channel / 12.92
        : ((channel + 0.055) / 1.055) ** 2.4);
    const luminance = 0.2126 * red + 0.7152 * green + 0.0722 * blue;
    const blackContrast = (luminance + 0.05) / 0.05;
    const whiteContrast = 1.05 / (luminance + 0.05);

    return blackContrast >= whiteContrast ? '#000000' : '#FFFFFF';
}

export function createTenantBrandingEditor({
    initial,
    drafts = {},
    createObjectURL = (file) => URL.createObjectURL(file),
    revokeObjectURL = (url) => URL.revokeObjectURL(url),
}) {
    const saved = { ...initial };
    const values = { ...initial, description: drafts.description ?? initial.description };
    const colorDrafts = {
        primary: drafts.primary ?? initial.primary,
        accent: drafts.accent ?? initial.accent,
    };
    for (const colorName of Object.keys(DEFAULT_COLORS)) {
        const canonical = canonicalColor(colorDrafts[colorName]);
        if (canonical) {
            values[colorName] = canonical;
            values[`${colorName}Foreground`] = readableForeground(canonical);
        }
    }
    const selectedFiles = { logo: null, cover: null };
    const objectUrls = { logo: null, cover: null };

    function releasePreview(field) {
        if (objectUrls[field]) {
            revokeObjectURL(objectUrls[field]);
            objectUrls[field] = null;
        }
    }

    return {
        saved,
        values,
        colorDrafts,
        selectedFiles,
        previewMode: 'desktop',
        statusMessage: '',
        statusType: 'status',

        get preview() {
            return this.values;
        },

        get previewWidth() {
            return this.previewMode === 'mobile' ? '375px' : '100%';
        },

        updateForeground(colorName) {
            const foregroundName = `${colorName}Foreground`;
            this.values[foregroundName] = readableForeground(this.values[colorName]);
        },

        setColor(colorName, value) {
            if (!Object.hasOwn(DEFAULT_COLORS, colorName)) {
                return;
            }

            this.colorDrafts[colorName] = value;
            const canonical = canonicalColor(value);
            if (!canonical) {
                this.statusMessage = 'Completa el color con el formato #RRGGBB antes de guardar.';
                this.statusType = 'alert';
                return;
            }

            this.values[colorName] = canonical;
            this.updateForeground(colorName);
            this.statusMessage = '';
        },

        setPresentation(description) {
            this.values.description = description;
        },

        setPreviewMode(mode) {
            if (mode === 'mobile' || mode === 'desktop') {
                this.previewMode = mode;
            }
        },

        selectFile(field, file) {
            if (!Object.hasOwn(this.selectedFiles, field)) {
                return;
            }

            releasePreview(field);
            this.selectedFiles[field] = file ?? null;
            objectUrls[field] = file ? createObjectURL(file) : null;
            this.values[`${field}Url`] = objectUrls[field] ?? this.saved[`${field}Url`];
        },

        resetColors() {
            for (const colorName of Object.keys(DEFAULT_COLORS)) {
                this.values[colorName] = DEFAULT_COLORS[colorName];
                this.colorDrafts[colorName] = DEFAULT_COLORS[colorName];
                this.updateForeground(colorName);
            }
            this.statusMessage = 'Colores predeterminados aplicados al borrador. Guarda para conservarlos.';
            this.statusType = 'status';
        },

        discard() {
            for (const field of Object.keys(this.selectedFiles)) {
                releasePreview(field);
                this.selectedFiles[field] = null;
                this.values[`${field}Url`] = this.saved[`${field}Url`];
                const input = this.$refs?.[field];
                if (input) {
                    input.value = '';
                }
            }

            Object.assign(this.values, this.saved);
            this.colorDrafts.primary = this.saved.primary;
            this.colorDrafts.accent = this.saved.accent;
            const descriptionInput = this.$refs?.description;
            if (descriptionInput) {
                descriptionInput.value = this.saved.description;
            }
            for (const colorName of Object.keys(DEFAULT_COLORS)) {
                const input = this.$refs?.[colorName];
                if (input) {
                    input.value = this.saved[colorName];
                }
            }
            this.statusMessage = 'Se descartaron los cambios sin guardar.';
            this.statusType = 'status';
        },

        dispose() {
            for (const field of Object.keys(this.selectedFiles)) {
                releasePreview(field);
                this.selectedFiles[field] = null;
            }
        },

        destroy() {
            this.dispose();
        },
    };
}
