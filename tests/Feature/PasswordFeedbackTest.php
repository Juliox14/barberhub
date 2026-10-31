<?php

namespace Tests\Feature;

use App\Models\Barber;
use App\Models\Barbershop;
use App\Models\Customer;
use App\Models\Membership;
use App\Models\User;
use App\Support\CustomerInvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Password;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PasswordFeedbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_password_feedback_is_rendered_inside_each_of_the_seven_password_forms(): void
    {
        $this->assertPasswordFeedbackForm($this->get('/register'));

        $resetUser = User::factory()->create();
        $resetToken = Password::broker()->createToken($resetUser);
        $this->assertPasswordFeedbackForm($this->get(route('password.reset', $resetToken).'?email='.$resetUser->email));

        $barbershop = Barbershop::factory()->create();
        $owner = User::factory()->unverified()->create();
        Membership::factory()->for($owner)->for($barbershop)->create([
            'role' => Membership::ROLE_OWNER,
            'status' => Membership::STATUS_ACTIVE,
        ]);
        $ownerToken = Password::broker()->createToken($owner);
        $this->assertPasswordFeedbackForm($this->get(route('owner-invitations.accept', $ownerToken).'?email='.$owner->email));

        $barber = User::factory()->unverified()->create();
        Membership::factory()->for($barber)->for($barbershop)->create([
            'role' => Membership::ROLE_BARBER,
            'status' => Membership::STATUS_ACTIVE,
        ]);
        Barber::factory()->for($barber)->for($barbershop)->create(['active' => true]);
        $barberToken = Password::broker()->createToken($barber);
        $this->assertPasswordFeedbackForm($this->get(route('barber-invitations.accept', $barberToken).'?email='.$barber->email));

        $customer = Customer::factory()->for($barbershop)->create(['email' => 'new-customer-feedback@example.com']);
        $issued = app(CustomerInvitationService::class)->issue($owner, $barbershop, $customer, $customer->email);
        $this->assertPasswordFeedbackForm($this->get(route('customer-invitations.show', $issued['token'])));

        $account = User::factory()->create();
        $this->actingAs($account);
        $this->assertPasswordFeedbackForm($this->get(route('password.renew')));
        $this->assertPasswordFeedbackForm($this->get('/profile'), 'light');
    }

    public function test_login_and_current_password_forms_do_not_show_new_password_feedback(): void
    {
        $this->get('/login')->assertOk()->assertDontSee('data-password-requirements', false);

        $user = User::factory()->create();
        $this->actingAs($user)->get('/confirm-password')
            ->assertOk()
            ->assertDontSee('data-password-requirements', false);
    }

    private function assertPasswordFeedbackForm(TestResponse $response, string $tone = 'dark'): void
    {
        $response->assertOk()
            ->assertSee('La contraseña debe tener al menos 12 caracteres', false)
            ->assertSee('data-password-requirements', false)
            ->assertSee('data-tone="'.$tone.'"', false)
            ->assertSee('<ul class="mt-2 hidden space-y-1"', false);

        preg_match_all('/<form\\b[^>]*>.*?<\\/form>/s', $response->getContent(), $forms);
        $feedbackForms = array_filter($forms[0], fn (string $form): bool => str_contains($form, 'data-password-requirements'));

        $this->assertCount(1, $feedbackForms);
        $this->assertStringContainsString('name="password"', reset($feedbackForms));
        $this->assertStringContainsString('name="password_confirmation"', reset($feedbackForms));
    }
}
