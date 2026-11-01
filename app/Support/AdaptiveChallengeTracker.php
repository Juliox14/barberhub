<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

final class AdaptiveChallengeTracker
{
    private const FREE_SUBMISSIONS = 3;

    private const DECAY_SECONDS = 900;

    public function count(string $flow, string $ip): int
    {
        return (int) Cache::get($this->key($flow, $ip), 0);
    }

    public function challengeRequired(string $flow, string $ip): bool
    {
        return $this->count($flow, $ip) >= self::FREE_SUBMISSIONS;
    }

    public function admit(string $flow, string $ip): void
    {
        $key = $this->key($flow, $ip);

        if (! Cache::add($key, 1, now()->addSeconds(self::DECAY_SECONDS))) {
            Cache::increment($key);
        }
    }

    private function key(string $flow, string $ip): string
    {
        return 'adaptive-recaptcha:'.hash('sha256', $flow.'|'.$ip);
    }
}
