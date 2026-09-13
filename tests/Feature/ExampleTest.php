<?php

use Tests\TestCase;

// Vérifie que l'accès à la page d'accueil (route 'home') redirige bien vers la page de login
test('redirects to login', function () {
    /** @var TestCase $this */
    $response = $this->get(route('home'));

    $response->assertRedirect(route('login'));
});