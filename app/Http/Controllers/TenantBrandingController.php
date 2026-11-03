<?php

namespace App\Http\Controllers;

use App\Models\Barbershop;
use App\Models\Membership;
use App\Support\TenantBranding;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

final class TenantBrandingController extends Controller
{
    public function edit(Request $request, Barbershop $barbershop): View
    {
        $this->authorizeOwner($request, $barbershop);

        return view('tenant.branding-edit', [
            'barbershop' => $barbershop,
            'branding' => TenantBranding::resolve($barbershop),
        ]);
    }

    public function update(Request $request, Barbershop $barbershop): RedirectResponse
    {
        $this->authorizeOwner($request, $barbershop);

        $validated = $request->validate([
            'brand_primary_color' => ['required', 'regex:/\A#[0-9A-Fa-f]{6}\z/'],
            'brand_accent_color' => ['required', 'regex:/\A#[0-9A-Fa-f]{6}\z/'],
            'brand_description' => ['nullable', 'string', 'max:1000'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=4096,max_height=4096'],
            'cover' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=4096,max_height=4096'],
        ], [
            'brand_primary_color.required' => 'El color principal es obligatorio.',
            'brand_primary_color.regex' => 'El color principal debe usar el formato #RRGGBB.',
            'brand_accent_color.required' => 'El color de acento es obligatorio.',
            'brand_accent_color.regex' => 'El color de acento debe usar el formato #RRGGBB.',
            'brand_description.max' => 'La descripción no puede superar los 1000 caracteres.',
            'logo' => 'El logo debe ser una imagen PNG, JPEG o WebP válida de máximo 2 MB y 4096 × 4096 px.',
            'cover' => 'La portada debe ser una imagen PNG, JPEG o WebP válida de máximo 5 MB y 4096 × 4096 px.',
        ]);

        $disk = Storage::disk('local');
        $newPaths = [];
        $oldPaths = [
            'logo' => $barbershop->brand_logo_path,
            'cover' => $barbershop->brand_cover_path,
        ];

        try {
            foreach (['logo', 'cover'] as $asset) {
                if (! isset($validated[$asset])) {
                    continue;
                }

                $storedPath = $validated[$asset]->store('tenant-branding/'.$barbershop->id, 'local');

                if (! is_string($storedPath)) {
                    throw new RuntimeException('No se pudo almacenar el archivo de marca.');
                }

                $newPaths[$asset] = $storedPath;
            }

            $barbershop->update([
                'brand_primary_color' => strtoupper($validated['brand_primary_color']),
                'brand_accent_color' => strtoupper($validated['brand_accent_color']),
                'brand_description' => $validated['brand_description'] ?? null,
                'brand_logo_path' => $newPaths['logo'] ?? $barbershop->brand_logo_path,
                'brand_cover_path' => $newPaths['cover'] ?? $barbershop->brand_cover_path,
            ]);
        } catch (Throwable $exception) {
            foreach ($newPaths as $newPath) {
                $disk->delete($newPath);
            }

            throw $exception;
        }

        $ownedPrefix = 'tenant-branding/'.$barbershop->id.'/';
        $retainedPaths = array_filter([
            $barbershop->brand_logo_path,
            $barbershop->brand_cover_path,
        ]);

        foreach ($oldPaths as $asset => $oldPath) {
            if (isset($newPaths[$asset])
                && is_string($oldPath)
                && str_starts_with($oldPath, $ownedPrefix)
                && ! in_array($oldPath, $retainedPaths, true)) {
                $disk->delete($oldPath);
            }
        }

        return redirect()
            ->route('tenant.branding.edit', $barbershop)
            ->with('status', 'La identidad de tu barbería se actualizó correctamente.');
    }

    public function asset(Barbershop $barbershop, string $kind): Response
    {
        abort_unless($barbershop->status === Barbershop::STATUS_ACTIVE, 404);

        $path = match ($kind) {
            'logo' => $barbershop->brand_logo_path,
            'cover' => $barbershop->brand_cover_path,
            default => null,
        };

        abort_unless(is_string($path) && str_starts_with($path, 'tenant-branding/'.$barbershop->id.'/'), 404);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($path), 404);

        $contents = $disk->get($path);
        $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->buffer($contents);
        abort_unless(in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'], true), 404);

        $extension = match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        };

        return response($contents, 200, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="brand-'.$kind.'.'.$extension.'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    private function authorizeOwner(Request $request, Barbershop $barbershop): void
    {
        $user = $request->user();
        abort_unless($user !== null && ! $user->is_platform_admin, 403);

        $isActiveOwner = $user->memberships()
            ->where('barbershop_id', $barbershop->id)
            ->where('status', Membership::STATUS_ACTIVE)
            ->where('role', Membership::ROLE_OWNER)
            ->exists();

        abort_unless($isActiveOwner, 403);
    }
}
