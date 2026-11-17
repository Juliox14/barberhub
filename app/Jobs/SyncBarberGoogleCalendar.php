<?php

namespace App\Jobs;

use App\Models\BarberGoogleCalendarConnection;
use App\Support\GoogleCalendarDispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class SyncBarberGoogleCalendar implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    /** @var array<int, int> */
    public array $backoff = [10, 30, 60];

    public function __construct(public int $connectionId) {}

    public function handle(GoogleCalendarDispatcher $dispatcher): void
    {
        $connection = BarberGoogleCalendarConnection::query()->find($this->connectionId);
        if ($connection !== null && $connection->connected_at !== null) {
            $dispatcher->backfill($connection);
        }
    }
}
