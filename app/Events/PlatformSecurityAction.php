<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

final class PlatformSecurityAction
{
    use Dispatchable;

    public function __construct(
        public string $action,
        public int $userId,
        public ?int $subjectUserId,
        public ?int $barbershopId,
        public ?string $module,
        public string $reason,
        public ?string $expiresAt,
    ) {}
}
