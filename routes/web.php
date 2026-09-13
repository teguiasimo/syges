<?php

use App\Http\Controllers\Administration\RolePermissionController;
use Illuminate\Support\Facades\Route;

// Redirection par défaut de la racine vers la page de connexion dès le lancement
Route::redirect('/', 'login')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    // Page du Tableau de bord utilisateur
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    // Page d'accueil du module d'Administration
    Route::inertia('administration', 'administration')->name('administration');

    // Gestion des Rôles & Permissions
    Route::prefix('administration')->name('administration.')->group(function () {
        Route::resource('roles', RolePermissionController::class)->only(['index', 'store', 'update', 'destroy']);
    });
});

require __DIR__.'/settings.php';
