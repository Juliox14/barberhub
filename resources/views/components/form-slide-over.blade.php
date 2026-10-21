@props(['id', 'title', 'description' => null, 'open' => false])

<div
    x-data="{
        isOpen: @js($open),
        opener: null,
        showPanel(event) {
            this.opener = event.target instanceof HTMLElement
                ? event.target.closest('[data-slide-over-trigger]')
                : null;
            window.bhActiveSlideOverId = @js($id);
            this.isOpen = true;
            document.body.classList.add('overflow-hidden');
            this.$nextTick(() => this.$refs.panel.querySelector('[autofocus], input:not([type=hidden]), select, textarea, button')?.focus());
        },
        hidePanel() {
            this.isOpen = false;

            if (window.bhActiveSlideOverId === @js($id)) {
                window.bhActiveSlideOverId = null;
                document.body.classList.remove('overflow-hidden');
            }

            this.$nextTick(() => this.opener?.focus());
        },
        trapFocus(event) {
            if (event.key !== 'Tab') {
                return;
            }

            const focusable = Array.from(this.$refs.panel.querySelectorAll('a[href], button:not([disabled]), input:not([type=hidden]):not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]'))
                .filter((element) => element.getAttribute('tabindex') !== '-1');
            const first = focusable[0];
            const last = focusable[focusable.length - 1];

            if (!first) {
                event.preventDefault();
                this.$refs.panel.focus();
            } else if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        },
    }"
    x-on:form-slide-over-open.window="if ($event.detail.id === @js($id)) { showPanel($event); } else if (isOpen) { hidePanel(); }"
    x-on:form-slide-over-close.window="if ($event.detail.id === @js($id) && isOpen) { hidePanel(); }"
    x-on:keydown.escape.window="if (isOpen) { hidePanel(); }"
    x-init="if (isOpen) { window.bhActiveSlideOverId = @js($id); document.body.classList.add('overflow-hidden'); $nextTick(() => $refs.panel.querySelector('[autofocus], input:not([type=hidden]), select, textarea, button')?.focus()); }"
>
    <div
        x-cloak
        x-show="isOpen"
        x-transition:enter="transition-opacity ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition-opacity ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-50 bg-black/65"
        aria-hidden="true"
        x-on:click="hidePanel()"
    ></div>

    <aside
        x-cloak
        x-ref="panel"
        x-show="isOpen"
        x-transition:enter="transform transition ease-[cubic-bezier(0.16,1,0.3,1)] duration-300"
        x-transition:enter-start="translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transform transition ease-in duration-150"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="translate-x-full"
        class="fixed inset-y-0 right-0 z-[51] flex h-screen h-dvh w-full flex-col border-l border-slate-700 bg-[#0d1217] shadow-2xl shadow-black/30 sm:max-w-xl"
        role="dialog"
        aria-modal="true"
        aria-labelledby="{{ $id }}-title"
        @if ($description) aria-describedby="{{ $id }}-description" @endif
        tabindex="-1"
        x-on:keydown="trapFocus($event)"
    >
        <header class="flex shrink-0 items-start justify-between gap-4 border-b border-slate-800 px-5 py-5 sm:px-7">
            <div class="min-w-0">
                <h2 id="{{ $id }}-title" class="text-xl font-bold text-white">{{ $title }}</h2>
                @if ($description)
                    <p id="{{ $id }}-description" class="mt-1 text-sm leading-6 text-slate-400">{{ $description }}</p>
                @endif
            </div>
            <button
                type="button"
                class="shrink-0 rounded-md p-2 text-slate-400 transition hover:bg-slate-800 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300"
                aria-label="Cerrar panel"
                x-on:click="hidePanel()"
            >
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" d="m6 6 12 12M18 6 6 18"/></svg>
            </button>
        </header>

        <div class="min-h-0 flex-1 overflow-y-auto px-5 py-6 sm:px-7">
            {{ $slot }}
        </div>

        @isset($footer)
            <footer class="flex shrink-0 flex-col-reverse gap-3 border-t border-slate-800 bg-[#0d1217] px-5 py-4 sm:flex-row sm:justify-end sm:px-7">
                {{ $footer }}
            </footer>
        @endisset
    </aside>
</div>
