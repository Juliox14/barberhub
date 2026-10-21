<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ValidationTranslationsTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('invalidFields')]
    public function test_validation_messages_translate_rules_attributes_and_parameters(array $data, array $rules, string $field, string $message): void
    {
        $validator = Validator::make($data, $rules);

        $this->assertSame([$message], $validator->errors()->get($field));
    }

    public static function invalidFields(): array
    {
        return [
            'required' => [[], ['email' => 'required'], 'email', 'El campo correo electrónico es obligatorio.'],
            'email' => [['email' => 'invalid'], ['email' => 'email'], 'email', 'El campo correo electrónico debe ser una dirección de correo válida.'],
            'string' => [['name' => []], ['name' => 'string'], 'name', 'El campo nombre debe ser un texto.'],
            'lowercase' => [['email' => 'USER@example.com'], ['email' => 'lowercase'], 'email', 'El campo correo electrónico debe estar en minúsculas.'],
            'confirmed' => [['password' => 'password', 'password_confirmation' => 'different'], ['password' => 'confirmed'], 'password', 'La confirmación de contraseña no coincide.'],
            'same' => [['password' => 'password', 'password_confirmation' => 'different'], ['password_confirmation' => 'same:password'], 'password_confirmation', 'El campo confirmación de contraseña debe coincidir con contraseña.'],
            'numeric' => [['price' => 'invalid'], ['price' => 'numeric'], 'price', 'El campo precio debe ser un número.'],
            'integer' => [['duration_minutes' => 1.5], ['duration_minutes' => 'integer'], 'duration_minutes', 'El campo duración en minutos debe ser un número entero.'],
            'boolean' => [['active' => 'invalid'], ['active' => 'boolean'], 'active', 'El campo activo debe ser verdadero o falso.'],
            'min numeric' => [['price' => -1], ['price' => 'numeric|min:0'], 'price', 'El campo precio debe ser mayor o igual que 0.'],
            'max numeric' => [['duration_minutes' => 1441], ['duration_minutes' => 'integer|max:1440'], 'duration_minutes', 'El campo duración en minutos debe ser menor o igual que 1440.'],
            'min string' => [['password' => 'short'], ['password' => 'string|min:8'], 'password', 'El campo contraseña debe tener al menos 8 caracteres.'],
            'max string' => [['name' => 'long'], ['name' => 'string|max:3'], 'name', 'El campo nombre no debe tener más de 3 caracteres.'],
            'date format' => [['opens_at' => 'invalid'], ['opens_at' => 'date_format:H:i'], 'opens_at', 'El campo hora de apertura debe tener el formato H:i.'],
            'before' => [['opens_at' => '18:00', 'closes_at' => '09:00'], ['opens_at' => 'before:closes_at'], 'opens_at', 'El campo hora de apertura debe ser anterior a hora de cierre.'],
            'in' => [['role' => 'invalid'], ['role' => 'in:owner,admin'], 'role', 'El valor seleccionado para rol no es válido.'],
            'regex' => [['slug' => 'Invalid Slug'], ['slug' => 'regex:/^[a-z0-9-]+$/'], 'slug', 'El formato del campo identificador de la barbería no es válido.'],
        ];
    }

    public function test_duplicate_email_is_translated_without_updating_the_profile(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $response = $this->actingAs($user)->patch('/profile', [
            'name' => $user->name,
            'email' => $otherUser->email,
        ]);

        $response->assertRedirect()->assertSessionHasErrors([
            'email' => 'El valor del campo correo electrónico ya está en uso.',
        ]);
        $this->assertSame($user->email, $user->fresh()->email);
        $this->assertDatabaseCount('users', 2);
    }

    public function test_missing_owner_is_translated_without_creating_a_barbershop(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);

        $response = $this->actingAs($admin)->post(route('platform.barbershops.store', absolute: false), [
            'name' => 'Barbería de prueba',
            'slug' => 'barberia-de-prueba',
            'timezone' => 'America/Bogota',
            'owner_email' => 'missing@example.com',
        ]);

        $response->assertRedirect()->assertSessionHasErrors([
            'owner_email' => 'El correo de la persona propietaria debe pertenecer a una cuenta existente.',
        ]);

        $validator = Validator::make(['owner_email' => 'missing@example.com'], ['owner_email' => 'exists:users,email']);

        $this->assertSame('El valor seleccionado para correo de la persona propietaria no es válido.', $validator->errors()->first('owner_email'));
        $this->assertDatabaseCount('barbershops', 0);
        $this->assertDatabaseCount('memberships', 0);
        $this->assertDatabaseCount('users', 1);
    }
}
