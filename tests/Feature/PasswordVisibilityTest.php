<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class PasswordVisibilityTest extends TestCase
{
    public function test_password_fields_remain_masked_and_have_independent_non_submit_controls(): void
    {
        $html = Blade::render(<<<'BLADE'
            <form>
                <x-password-field id="password">
                    <input id="password" name="password" type="password" x-bind:type="passwordVisible ? 'text' : 'password'" required autocomplete="new-password">
                </x-password-field>
                <x-password-field id="password_confirmation">
                    <input id="password_confirmation" name="password_confirmation" type="password" x-bind:type="passwordVisible ? 'text' : 'password'" required autocomplete="new-password">
                </x-password-field>
            </form>
            BLADE);

        $document = new \DOMDocument;
        @$document->loadHTML('<meta charset="UTF-8">'.$html);
        $xpath = new \DOMXPath($document);

        foreach (['password', 'password_confirmation'] as $id) {
            $input = $document->getElementById($id);
            $this->assertSame('password', $input->getAttribute('type'));
            $this->assertSame($id, $input->getAttribute('name'));
            $this->assertSame('new-password', $input->getAttribute('autocomplete'));
            $this->assertTrue($input->hasAttribute('required'));
            $buttons = $xpath->query('.//button', $input->parentNode);
            $this->assertCount(1, $buttons);
            $button = $buttons->item(0);
            $this->assertSame('button', $button->getAttribute('type'));
            $this->assertSame($id, $button->getAttribute('aria-controls'));
            $this->assertSame('Mostrar contraseña', $button->getAttribute('aria-label'));
            $this->assertSame('false', $button->getAttribute('aria-pressed'));
            $this->assertSame('{ passwordVisible: false }', $input->parentNode->getAttribute('x-data'));
        }
    }
}
