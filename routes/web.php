<?php

use Illuminate\Support\Facades\Route;

// Redirection par défaut de la racine vers la page de connexion dès le lancement
Route::redirect('/', 'login')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    // Page du Tableau de bord utilisateur
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    // Page du module d'Administration (rôles, permissions et gestion système)
    Route::inertia('administration', 'administration')->name('administration');
});

require __DIR__.'/settings.php';
