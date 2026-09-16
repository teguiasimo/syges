<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Tests de fonctionnalité pour la gestion de l'internationalisation et le basculement de langue.
 */
class LocaleTest extends TestCase
{
    /**
     * Vérifie que le basculement de langue vers l'anglais réussit et stocke la locale en session et cookie.
     */
    public function test_can_switch_locale_to_english(): void
    {
        $response = $this->from('/login')->post('/locale', [
            'locale' => 'en',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHas('locale', 'en');
        $response->assertPlainCookie('locale', 'en');
    }

    /**
     * Vérifie que le basculement de langue vers le français réussit.
     */
    public function test_can_switch_locale_to_french(): void
    {
        $response = $this->from('/login')->post('/locale', [
            'locale' => 'fr',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHas('locale', 'fr');
        $response->assertPlainCookie('locale', 'fr');
    }

    /**
     * Vérifie qu'une langue non supportée est rejetée par la validation.
     */
    public function test_cannot_switch_to_unsupported_locale(): void
    {
        $response = $this->from('/login')->post('/locale', [
            'locale' => 'es',
        ]);

        $response->assertSessionHasErrors('locale');
    }
}
