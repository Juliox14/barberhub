<x-tenant-layout :barbershop="$barbershop" role-label="Propietario" active-page="branding" :can-manage-members="true">
    @php
        $initialBranding = [
            'primary' => $branding['primary'],
            'accent' => $branding['accent'],
            'primaryForeground' => $branding['primary_foreground'],
            'accentForeground' => $branding['accent_foreground'],
            'surface' => $branding['surface'],
            'description' => (string) ($barbershop->brand_description ?? ''),
            'shopName' => (string) $barbershop->name,
            'logoUrl' => $barbershop->brand_logo_path ? route('tenant.branding.asset', [$barbershop, 'logo']) : null,
            'coverUrl' => $barbershop->brand_cover_path ? route('tenant.branding.asset', [$barbershop, 'cover']) : null,
        ];
        $draftValues = [
            'primary' => old('brand_primary_color', $initialBranding['primary']),
            'accent' => old('brand_accent_color', $initialBranding['accent']),
            'description' => old('brand_description', $initialBranding['description']),
        ];
        $encodeForAttribute = fn (array $value): string => json_encode($value, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
    @endphp

    <div class="bh-branding-editor mx-auto max-w-7xl space-y-6">
        <header>
            <h1 class="text-2xl font-bold tracking-[-0.03em] text-white sm:text-3xl">Identidad de {{ $barbershop->name }}</h1>
            <p class="mt-1 max-w-2xl text-sm leading-6 text-slate-400">Configura los colores, imágenes y presentación de tu barbería.</p>
        </header>

        @if (session('status'))
            <p role="status" aria-live="polite" class="rounded-lg border border-emerald-800 bg-emerald-950/40 px-4 py-3 text-sm text-emerald-200">{{ session('status') }}</p>
        @endif

        <form
            method="POST"
            action="{{ route('tenant.branding.update', $barbershop) }}"
            enctype="multipart/form-data"
            class="bh-branding-editor__columns"
            x-data="tenantBrandingEditor(JSON.parse($el.dataset.initial), JSON.parse($el.dataset.drafts))"
            data-initial="{{ $encodeForAttribute($initialBranding) }}"
            data-drafts="{{ $encodeForAttribute($draftValues) }}"
        >
            @csrf
            @method('PUT')

            <div class="space-y-5">
                <section class="bh-branding-editor__panel space-y-5" aria-labelledby="branding-colors-heading">
                    <div>
                        <h2 id="branding-colors-heading" class="font-semibold text-white">Colores de marca</h2>
                        <p class="mt-1 text-sm leading-6 text-slate-300">Elige dos colores. El texto sobre cada color se ajusta automáticamente para mantener el contraste.</p>
                    </div>

                    <div class="grid gap-5 sm:grid-cols-2">
                        @foreach ([
                            'primary' => ['Color principal', 'brand_primary_color'],
                            'accent' => ['Color de acento', 'brand_accent_color'],
                        ] as $color => [$label, $field])
                            <div class="min-w-0">
                                <label for="{{ $field }}" class="mb-2 block text-sm font-semibold text-slate-200">{{ $label }}</label>
                                <div class="flex min-w-0 items-center gap-3">
                                    <input type="color" :value="values.{{ $color }}" x-on:input="setColor('{{ $color }}', $event.target.value)" aria-label="Selector de {{ mb_strtolower($label) }}" class="h-11 w-14 shrink-0 cursor-pointer rounded-lg border border-slate-700 bg-[#0b1117] p-1">
                                    <input
                                        id="{{ $field }}"
                                        name="{{ $field }}"
                                        type="text"
                                        inputmode="text"
                                        pattern="#[A-Fa-f0-9]{6}"
                                        maxlength="7"
                                        required
                                        x-ref="{{ $color }}"
                                        x-model="colorDrafts.{{ $color }}"
                                        x-on:input="setColor('{{ $color }}', $event.target.value)"
                                        value="{{ $draftValues[$color] }}"
                                        class="block min-h-11 min-w-0 w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2.5 text-sm text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300"
                                        aria-describedby="{{ $field }}-help @error($field){{ $field }}-error @enderror"
                                    >
                                </div>
                                <p id="{{ $field }}-help" class="mt-1 text-xs text-slate-400">Formato hexadecimal #RRGGBB</p>
                                @error($field)<p id="{{ $field }}-error" class="mt-1 text-sm text-rose-300">{{ $message }}</p>@enderror
                            </div>
                        @endforeach
                    </div>

                    <div class="rounded-lg border border-slate-700 bg-[#0b1117] p-4">
                        <p class="text-sm font-semibold text-white">Restablecer colores</p>
                        <p class="mt-1 text-sm leading-6 text-slate-300">Aplica #496C35 y #B8885A al borrador. No elimina imágenes ni guarda cambios hasta que pulses Guardar identidad.</p>
                        <button type="button" x-on:click="resetColors()" class="mt-3 inline-flex min-h-10 items-center rounded-lg border border-slate-600 px-3 py-2 text-sm font-semibold text-slate-100 hover:bg-slate-800">Restablecer colores</button>
                    </div>
                </section>

                <section class="bh-branding-editor__panel space-y-5" aria-labelledby="branding-presentation-heading">
                    <div>
                        <h2 id="branding-presentation-heading" class="font-semibold text-white">Presentación</h2>
                        <p class="mt-1 text-sm leading-6 text-slate-300">Describe brevemente tu barbería. Máximo 1000 caracteres.</p>
                    </div>
                    <div>
                        <label for="brand_description" class="mb-2 block text-sm font-semibold text-slate-200">Descripción</label>
                        <textarea id="brand_description" name="brand_description" rows="5" maxlength="1000" x-ref="description" x-model="values.description" class="block w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2.5 text-sm text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">{{ $draftValues['description'] }}</textarea>
                        @error('brand_description')<p class="mt-1 text-sm text-rose-300">{{ $message }}</p>@enderror
                    </div>
                </section>

                <section class="bh-branding-editor__panel space-y-5" aria-labelledby="branding-assets-heading">
                    <div>
                        <h2 id="branding-assets-heading" class="font-semibold text-white">Imágenes</h2>
                        <p class="mt-1 text-sm leading-6 text-slate-300">PNG, JPEG o WebP. Logo: máximo 2 MB. Portada: máximo 5 MB. Dimensiones máximas: 4096 × 4096 px. Las selecciones se previsualizan antes de guardar.</p>
                    </div>
                    <div class="grid min-w-0 gap-5 sm:grid-cols-2">
                        @foreach ([
                            'logo' => ['Logo', $barbershop->brand_logo_path],
                            'cover' => ['Portada', $barbershop->brand_cover_path],
                        ] as $field => [$label, $path])
                            <div class="min-w-0">
                                <label for="{{ $field }}" class="mb-2 block text-sm font-semibold text-slate-200">{{ $label }}</label>
                                <input id="{{ $field }}" name="{{ $field }}" type="file" accept=".png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp" x-ref="{{ $field }}" x-on:change="selectFile('{{ $field }}', $event.target.files[0])" class="block min-h-11 w-full min-w-0 text-sm text-slate-300 file:mr-3 file:max-w-[calc(100%-1rem)] file:rounded-md file:border-0 file:bg-slate-700 file:px-3 file:py-2 file:font-semibold file:text-white">
                                @if ($path)
                                    <p class="mt-2 text-xs text-slate-400">Se conserva la imagen actual si no eliges un reemplazo.</p>
                                @endif
                                @error($field)<p class="mt-1 text-sm text-rose-300">{{ $message }}</p>@enderror
                            </div>
                        @endforeach
                    </div>
                </section>

                @if ($errors->any())
                    <div role="alert" aria-live="assertive" class="rounded-lg border border-rose-900 bg-rose-950/40 px-4 py-3 text-sm text-rose-200">
                        Revisa los campos marcados antes de guardar. Tus cambios no se aplicaron.
                    </div>
                @endif

                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <button type="button" x-on:click="discard()" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-600 px-5 py-2.5 text-sm font-semibold text-slate-100 hover:bg-slate-800">Descartar cambios</button>
                    <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-amber-300 px-5 py-2.5 text-sm font-semibold text-[#17130b] hover:bg-amber-200">Guardar identidad</button>
                </div>
                <p class="text-xs leading-5 text-slate-400">Descartar restaura los últimos valores guardados y limpia archivos seleccionados. Los cambios se guardan únicamente al enviar este formulario.</p>
            </div>

            <aside class="bh-branding-editor__panel min-w-0 space-y-4 xl:sticky xl:top-24" aria-labelledby="branding-preview-heading">
                <div>
                    <h2 id="branding-preview-heading" class="font-semibold text-white">Vista previa para clientes</h2>
                    <p class="mt-1 text-sm leading-6 text-slate-300">Una superficie clara que muestra cómo se aplicará la identidad de tu negocio.</p>
                </div>
                <div class="bh-branding-preview__controls" role="group" aria-label="Tamaño de vista previa">
                    <button type="button" class="bh-branding-preview__control" x-on:click="setPreviewMode('mobile')" :aria-pressed="previewMode === 'mobile'">Vista móvil</button>
                    <button type="button" class="bh-branding-preview__control" x-on:click="setPreviewMode('desktop')" :aria-pressed="previewMode === 'desktop'">Vista de escritorio</button>
                </div>
                <div class="bh-branding-preview__viewport" style="background-color: {{ $branding['surface'] }}; color: #1F2937" :style="{ maxWidth: previewWidth }">
                    <x-customer-brand :barbershop="$barbershop" :branding="$branding" preview="true" />
                </div>
                <p class="text-xs leading-5 text-slate-400">La vista previa no es todavía una página pública activa. Los controles de ejemplo no permiten reservar ni navegan a servicios.</p>
                <p x-show="statusMessage" x-cloak x-bind:role="statusType" aria-live="polite" x-text="statusMessage" class="rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2 text-sm text-slate-200"></p>
            </aside>
        </form>
    </div>
</x-tenant-layout>
