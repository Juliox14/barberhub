<?php

namespace App\Events;

final readonly class AccountLoginLocked
{
    public function __construct(public int $userId, public string $lockedUntil) {}
}
