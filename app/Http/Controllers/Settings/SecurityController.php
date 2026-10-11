<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Contrôleur de gestion de la sécurité et du mot de passe utilisateur.
 */
class SecurityController extends Controller
{
    /**
     * Affiche la page de mise à jour du mot de passe de l'utilisateur connecté.
     *
     * @param Request $request
     * @return Response
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/security', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    /**
     * Enregistre le nouveau mot de passe après vérification du mot de passe actuel.
     *
     * @param PasswordUpdateRequest $request
     * @return RedirectResponse
     */
    public function update(PasswordUpdateRequest $request): RedirectResponse
    {
        $request->user()->update([
            'password' => $request->password,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Votre mot de passe a été modifié avec succès.')]);

        return back();
    }
}
