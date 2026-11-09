<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->put('/password', [
                'current_password' => 'password',
                'password' => 'NewPassword123!',
                'password_confirmation' => 'NewPassword123!',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('NewPassword123!', $user->refresh()->password));
    }

    public function test_password_change_revokes_existing_sessions_and_remember_tokens(): void
    {
        $user = User::factory()->create([
            'active_session_id' => 'previous-owner-token',
            'active_session_at' => now(),
            'security_session_version' => 4,
        ]);
        $previousRememberToken = $user->getRememberToken();

        $this->actingAs($user)->from('/profile')->put('/password', [
            'current_password' => 'password',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertRedirect(route('login'));

        $user->refresh();
        $this->assertNull($user->active_session_id);
        $this->assertNull($user->active_session_at);
        $this->assertSame(5, (int) $user->security_session_version);
        $this->assertNotSame($previousRememberToken, $user->getRememberToken());
        $this->assertDatabaseHas('security_audit_logs', [
            'event' => 'password.updated',
            'actor_id' => $user->id,
            'subject_id' => $user->id,
        ]);
    }

    public function test_correct_password_must_be_provided_to_update_password(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->put('/password', [
                'current_password' => 'wrong-password',
                'password' => 'NewPassword123!',
                'password_confirmation' => 'NewPassword123!',
            ]);

        $previousPasswordHash = $user->password;
        $previousRememberToken = $user->getRememberToken();
        $response
            ->assertSessionHasErrorsIn('updatePassword', 'current_password')
            ->assertRedirect('/profile');

        $this->assertSame($previousPasswordHash, $user->fresh()->password);
        $this->assertSame($previousRememberToken, $user->fresh()->getRememberToken());
        $this->assertSame(0, (int) $user->fresh()->security_session_version);
        $this->assertDatabaseMissing('security_audit_logs', ['event' => 'password.updated']);
    }
}
