<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\BarberAvailability;
use App\Models\Barbershop;
use App\Models\BusinessHour;
use App\Models\Customer;
use App\Models\Service;
use App\Notifications\GuestBookingEmailCode;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;
use Throwable;

class GuestBookingConcurrencyTest extends TestCase
{
    public function test_concurrent_guest_confirmations_wait_and_share_one_customer(): void
    {
        if (config('database.default') !== 'pgsql') {
            $this->markTestSkipped('Run this integration test with DB_CONNECTION=pgsql and a database allowing scratch schemas.');
        }
        if (! function_exists('pcntl_fork') || ! function_exists('posix_kill')) {
            $this->markTestSkipped('This integration test requires the pcntl and posix extensions.');
        }

        $this->withoutVite();
        $this->travelTo('2026-10-05 08:00:00 UTC');
        Notification::fake();
        $schema = 'guest_booking_test_'.bin2hex(random_bytes(8));
        $child = null;
        $socket = null;
        $created = false;
        $configuration = config('database.connections.pgsql');
        config(['database.connections.guest_concurrency' => array_replace($configuration, ['search_path' => $schema])]);

        try {
            DB::connection('pgsql')->statement('CREATE SCHEMA "'.$schema.'"');
            $created = true;
            DB::setDefaultConnection('guest_concurrency');
            $this->assertSame($schema, DB::selectOne('SELECT current_schema() AS name')->name);
            $this->assertSame(1, (int) DB::selectOne('SELECT count(*) AS owned FROM pg_namespace WHERE nspname = ? AND nspowner = current_user::regrole', [$schema])->owned);
            $this->assertSame(0, Artisan::call('migrate', ['--database' => 'guest_concurrency', '--force' => true, '--no-interaction' => true]));

            $shop = Barbershop::factory()->create(['timezone' => 'UTC']);
            $service = Service::factory()->for($shop)->create(['duration_minutes' => 30]);
            $barber = Barber::factory()->for($shop)->create();
            BusinessHour::factory()->for($shop)->create(['weekday' => 1, 'opens_at' => '10:00', 'closes_at' => '12:00']);
            BarberAvailability::factory()->for($barber)->create(['weekday' => 1, 'starts_at' => '10:00', 'ends_at' => '12:00']);
            $issued = $this->postJson(route('public.booking.email-code.store', $shop), ['email' => 'guest@example.com'])->assertOk();
            $cookie = $issued->getCookie(config('session.cookie'));
            $this->withCredentials()->withCookie($cookie->getName(), $cookie->getValue());
            $code = Notification::sent(new AnonymousNotifiable, GuestBookingEmailCode::class)->sole()->code;
            $this->postJson(route('public.booking.email-code.verify', $shop), ['email' => 'guest@example.com', 'code' => $code])->assertOk();
            $drafts = [];
            foreach (['10:00', '10:30'] as $time) {
                $drafts[] = $this->postJson(route('public.booking.summary', $shop), [
                    'service_id' => $service->id, 'slot' => $barber->id.'|2026-10-05T'.$time,
                    'name' => 'Invitado', 'email' => 'guest@example.com',
                ])->assertOk()->json('draft_id');
            }

            foreach (DB::getConnections() as $connection) {
                $connection->disconnect();
            }
            $sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
            $this->assertNotFalse($sockets);
            $child = pcntl_fork();
            $this->assertNotSame(-1, $child);
            if ($child === 0) {
                fclose($sockets[0]);
                $this->confirmInChild($sockets[1], $shop, $drafts[1], $schema);
            }

            fclose($sockets[1]);
            $socket = $sockets[0];
            stream_set_timeout($socket, 15);
            $childPid = (int) trim(fgets($socket));
            $parentPid = (int) DB::selectOne('SELECT pg_backend_pid() AS pid')->pid;
            $this->assertGreaterThan(0, $childPid);
            $this->assertNotSame($parentPid, $childPid);
            DB::beginTransaction();
            $first = $this->postJson(route('public.booking.store', $shop), ['draft_id' => $drafts[0]])
                ->assertOk()->assertJsonPath('confirmed', true);
            fwrite($socket, "confirm\n");
            $deadline = microtime(true) + 5;
            do {
                $blocked = (int) DB::connection('pgsql')->selectOne(
                    'SELECT count(*) AS blocked FROM unnest(pg_blocking_pids(?)) AS blocker(pid) WHERE pid = ?',
                    [$childPid, $parentPid],
                )->blocked;
            } while ($blocked === 0 && microtime(true) < $deadline);
            $this->assertSame(1, $blocked, 'Second confirmation must wait for the first transaction.');
            DB::commit();
            $line = fgets($socket);
            $this->assertNotFalse($line, 'Child confirmation did not return within its deadline.');
            $second = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame(200, $second['status'], json_encode($second));
            $this->assertTrue($second['confirmed']);
            $this->assertNotSame($first->json('appointment_id'), $second['appointment_id']);
            pcntl_waitpid($child, $status);
            $child = null;
            $this->assertSame(0, pcntl_wexitstatus($status));
            $this->assertSame(1, Customer::query()->count());
            $this->assertSame(2, Appointment::query()->count());
            $this->assertSame(1, Appointment::query()->distinct()->count('customer_id'));
            $this->assertSame(2, Appointment::query()->distinct()->count('starts_at'));
        } finally {
            while (DB::connection('guest_concurrency')->transactionLevel() > 0) {
                DB::connection('guest_concurrency')->rollBack();
            }
            if ($child !== null && $child > 0) {
                if (pcntl_waitpid($child, $status, WNOHANG) === 0) {
                    posix_kill($child, SIGTERM);
                    pcntl_waitpid($child, $status);
                }
            }
            if (is_resource($socket)) {
                fclose($socket);
            }
            DB::purge('guest_concurrency');
            DB::setDefaultConnection('pgsql');
            if ($created) {
                DB::connection('pgsql')->statement('DROP SCHEMA "'.$schema.'" CASCADE');
                $this->assertSame(0, (int) DB::selectOne('SELECT count(*) AS remaining FROM pg_namespace WHERE nspname = ?', [$schema])->remaining);
            }
        }
    }

    /** @param resource $socket */
    private function confirmInChild(mixed $socket, Barbershop $shop, string $draftId, string $schema): never
    {
        pcntl_alarm(15);
        stream_set_timeout($socket, 10);
        try {
            if (DB::selectOne('SELECT current_schema() AS name')->name !== $schema) {
                throw new \RuntimeException('Child connection escaped scratch schema.');
            }
            DB::statement("SET statement_timeout = '10s'");
            fwrite($socket, DB::selectOne('SELECT pg_backend_pid() AS pid')->pid."\n");
            if (fgets($socket) !== "confirm\n") {
                throw new \RuntimeException('Missing parent confirmation signal.');
            }
            $response = $this->postJson(route('public.booking.store', $shop), ['draft_id' => $draftId]);
            fwrite($socket, json_encode([
                'status' => $response->status(), 'confirmed' => $response->json('confirmed'),
                'appointment_id' => $response->json('appointment_id'),
            ], JSON_THROW_ON_ERROR)."\n");
            fclose($socket);
            DB::disconnect();
            exit(0);
        } catch (Throwable $exception) {
            fwrite($socket, json_encode(['status' => 0, 'error' => $exception::class], JSON_THROW_ON_ERROR)."\n");
            fclose($socket);
            exit(1);
        }
    }
}
