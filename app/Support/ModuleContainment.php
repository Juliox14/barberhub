<?php

namespace App\Support;

use App\Models\Barbershop;
use App\Models\ModuleContainment as ModuleContainmentRecord;
use Illuminate\Support\Facades\DB;

final class ModuleContainment
{
    public function suspend(Barbershop $barbershop, string $module, string $reason): ?ModuleContainmentRecord
    {
        return DB::transaction(function () use ($barbershop, $module, $reason): ?ModuleContainmentRecord {
            $lockedShop = Barbershop::query()->lockForUpdate()->findOrFail($barbershop->getKey());
            $containment = ModuleContainmentRecord::query()
                ->where('barbershop_id', $lockedShop->getKey())
                ->where('module', $module)
                ->lockForUpdate()
                ->first();

            if ($containment !== null && $containment->released_at === null && $containment->expires_at->isFuture()) {
                return null;
            }

            if ($containment === null) {
                $containment = new ModuleContainmentRecord([
                    'barbershop_id' => $lockedShop->getKey(),
                    'module' => $module,
                ]);
            }

            $containment->fill([
                'reason' => $reason,
                'expires_at' => now()->addMinutes(15),
                'released_at' => null,
                'release_reason' => null,
            ])->save();

            return $containment;
        });
    }

    public function release(Barbershop $barbershop, string $module, string $reason): ?ModuleContainmentRecord
    {
        return DB::transaction(function () use ($barbershop, $module, $reason): ?ModuleContainmentRecord {
            $lockedShop = Barbershop::query()->lockForUpdate()->findOrFail($barbershop->getKey());
            $containment = ModuleContainmentRecord::query()
                ->where('barbershop_id', $lockedShop->getKey())
                ->where('module', $module)
                ->lockForUpdate()
                ->first();

            if ($containment === null || $containment->released_at !== null || ! $containment->expires_at->isFuture()) {
                return null;
            }

            $containment->fill([
                'released_at' => now(),
                'release_reason' => $reason,
            ])->save();

            return $containment;
        });
    }

    public function isSuspended(Barbershop $barbershop, string $module): bool
    {
        return ModuleContainmentRecord::query()
            ->where('barbershop_id', $barbershop->getKey())
            ->where('module', $module)
            ->whereNull('released_at')
            ->where('expires_at', '>', now())
            ->exists();
    }
}
