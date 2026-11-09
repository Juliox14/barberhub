<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
            $response = $this->get('/reset-password/'.$notification->token);

            $response->assertStatus(200);

            return true;
        });
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        $user->forceFill([
            'active_session_id' => 'previous-owner-token',
            'active_session_at' => now(),
            'security_session_version' => 8,
        ])->save();
        $previousRememberToken = $user->getRememberToken();

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user, $previousRememberToken) {
            $response = $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'ResetPassword123!',
                'password_confirmation' => 'ResetPassword123!',
            ]);

            $response
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('login'));

            $updatedUser = $user->fresh();
            $this->assertNull($updatedUser->active_session_id);
            $this->assertNull($updatedUser->active_session_at);
            $this->assertSame(9, (int) $updatedUser->security_session_version);
            $this->assertNotSame($previousRememberToken, $updatedUser->getRememberToken());

            return true;
        });
    }
}
