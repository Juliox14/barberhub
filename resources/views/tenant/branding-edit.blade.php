<x-tenant-layout :barbershop="$barbershop" role-label="Propietario" active-page="branding" :can-manage-members="true">
    <div class="mx-auto max-w-4xl space-y-6">
        <header>
            <h1 class="text-2xl font-bold tracking-[-0.03em] text-white sm:text-3xl">Identidad de {{ $barbershop->name }}</h1>
            <p class="mt-1 max-w-2xl text-sm leading-6 text-slate-400">Configura los colores, imágenes y presentación de tu barbería.</p>
        </header>

        @if (session('status'))
            <p role="status" class="rounded-lg border border-emerald-800 bg-emerald-950/40 px-4 py-3 text-sm text-emerald-200">{{ session('status') }}</p>
        @endif

        <form method="POST" action="{{ route('tenant.branding.update', $barbershop) }}" enctype="multipart/form-data" class="space-y-6">
            @csrf
            @method('PUT')

            <section class="space-y-5 rounded-xl border border-slate-800 bg-[#111820] p-5 sm:p-6" aria-labelledby="branding-colors-heading">
                <div>
                    <h2 id="branding-colors-heading" class="font-semibold text-white">Colores de marca</h2>
                    <p class="mt-1 text-sm text-slate-400">Usa un color hexadecimal de seis dígitos, como #496C35.</p>
                </div>
                <div class="grid gap-5 sm:grid-cols-2">
                    @foreach ([
                        'brand_primary_color' => ['Color principal', $branding['primary']],
                        'brand_accent_color' => ['Color de acento', $branding['accent']],
                    ] as $field => [$label, $default])
                        <div>
                            <label for="{{ $field }}" class="mb-2 block text-sm font-semibold text-slate-200">{{ $label }}</label>
                            <input id="{{ $field }}" name="{{ $field }}" type="text" inputmode="text" pattern="#[A-Fa-f0-9]{6}" maxlength="7" required value="{{ old($field, $barbershop->{$field} ?? $default) }}" class="block min-h-11 w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2.5 text-sm text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300" aria-describedby="{{ $field }}-help">
                            <p id="{{ $field }}-help" class="mt-1 text-xs text-slate-400">Formato #RRGGBB</p>
                            @error($field)<p class="mt-1 text-sm text-rose-300">{{ $message }}</p>@enderror
                        </div>
                    @endforeach
                </div>
                <div class="rounded-lg border border-slate-700 p-4" style="background-color: {{ $branding['surface'] }}; color: #1F2937">
                    <p class="font-semibold">Vista previa clara</p>
                    <p class="mt-1 text-sm">La combinación calcula texto legible para ambos colores de marca.</p>
                    <div class="mt-3 flex gap-3">
                        <span class="rounded px-3 py-1" style="background-color: {{ $branding['primary'] }}; color: {{ $branding['primary_foreground'] }}">Principal</span>
                        <span class="rounded px-3 py-1" style="background-color: {{ $branding['accent'] }}; color: {{ $branding['accent_foreground'] }}">Acento</span>
                    </div>
                </div>
            </section>

            <section class="space-y-5 rounded-xl border border-slate-800 bg-[#111820] p-5 sm:p-6" aria-labelledby="branding-presentation-heading">
                <div>
                    <h2 id="branding-presentation-heading" class="font-semibold text-white">Presentación</h2>
                    <p class="mt-1 text-sm text-slate-400">Describe brevemente tu barbería. Máximo 1000 caracteres.</p>
                </div>
                <div>
                    <label for="brand_description" class="mb-2 block text-sm font-semibold text-slate-200">Descripción</label>
                    <textarea id="brand_description" name="brand_description" rows="4" maxlength="1000" class="block w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2.5 text-sm text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">{{ old('brand_description', $barbershop->brand_description) }}</textarea>
                    @error('brand_description')<p class="mt-1 text-sm text-rose-300">{{ $message }}</p>@enderror
                </div>
            </section>

            <section class="space-y-5 rounded-xl border border-slate-800 bg-[#111820] p-5 sm:p-6" aria-labelledby="branding-assets-heading">
                <div>
                    <h2 id="branding-assets-heading" class="font-semibold text-white">Imágenes</h2>
                    <p class="mt-1 text-sm text-slate-400">PNG, JPEG o WebP reales. Logo: máximo 2 MB. Portada: máximo 5 MB. Dimensiones máximas: 4096 × 4096 px.</p>
                </div>
                <div class="grid gap-5 sm:grid-cols-2">
                    @foreach ([
                        'logo' => ['Logo', $barbershop->brand_logo_path],
                        'cover' => ['Portada', $barbershop->brand_cover_path],
                    ] as $field => [$label, $path])
                        <div>
                            <label for="{{ $field }}" class="mb-2 block text-sm font-semibold text-slate-200">{{ $label }}</label>
                            <input id="{{ $field }}" name="{{ $field }}" type="file" accept=".png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp" class="block w-full text-sm text-slate-300 file:mr-3 file:rounded-md file:border-0 file:bg-slate-700 file:px-3 file:py-2 file:font-semibold file:text-white">
                            @if ($path)
                                <img src="{{ route('tenant.branding.asset', [$barbershop, $field]) }}" alt="{{ $label }} actual" class="mt-3 max-h-40 max-w-full rounded-lg object-contain">
                                <p class="mt-1 text-xs text-slate-400">Se conserva la imagen actual si no seleccionas otra.</p>
                            @endif
                            @error($field)<p class="mt-1 text-sm text-rose-300">{{ $message }}</p>@enderror
                        </div>
                    @endforeach
                </div>
            </section>

            <div class="flex justify-end">
                <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-amber-300 px-5 py-2.5 text-sm font-semibold text-[#17130b] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">Guardar identidad</button>
            </div>
        </form>
    </div>
</x-tenant-layout>
