<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Définit les données partagées par défaut avec toutes les vues Inertia.
     * Intègre le contexte multi-établissements (SaaS Multi-Tenant) en résolvant l'établissement
     * actif en session pour le Super Admin ou l'établissement assigné pour le personnel.
     *
     * @param Request $request Requête HTTP entrante.
     * @return array<string, mixed> Tableau des variables globales partagées.
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $isSuperAdmin = $user ? ($user->hasRole('super-admin') || $user->email === 'admin@gmail.com') : false;

        $currentEtablissement = null;
        $availableEtablissements = [];

        if ($user) {
            $allEtablissements = \App\Models\Etablissement::select('id', 'nom', 'code', 'ordre_enseignement')->orderBy('nom')->get();

            if ($isSuperAdmin) {
                // Pour le Super Admin : lecture depuis la session ou défaut sur le premier établissement
                $sessionEtabId = $request->session()->get('current_etablissement_id');
                $currentEtablissement = $sessionEtabId
                    ? $allEtablissements->firstWhere('id', $sessionEtabId)
                    : $allEtablissements->first();
                $availableEtablissements = $allEtablissements->toArray();
            } else {
                // Pour le personnel d'établissement : verrouillage sur le campus assigné
                $userPersonnel = $user->personnel ?? ($user->email ? \App\Models\Personnel::where('email', $user->email)->first() : null);
                if ($userPersonnel && $userPersonnel->etablissement_id) {
                    $currentEtablissement = $allEtablissements->firstWhere('id', $userPersonnel->etablissement_id);
                    $availableEtablissements = $currentEtablissement ? [$currentEtablissement->toArray()] : [];
                } else {
                    $currentEtablissement = $allEtablissements->first();
                    $availableEtablissements = $allEtablissements->toArray();
                }
            }
        }

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'csrf_token' => csrf_token(),
            'locale' => app()->getLocale(),
            'currentEtablissement' => $currentEtablissement ? [
                'id' => $currentEtablissement->id,
                'nom' => $currentEtablissement->nom,
                'code' => $currentEtablissement->code,
                'ordre_enseignement' => $currentEtablissement->ordre_enseignement,
            ] : null,
            'availableEtablissements' => $availableEtablissements,
            'auth' => [
                'user' => $user ? [
                    ...$user->toArray(),
                    'is_super_admin' => $isSuperAdmin,
                    'roles' => $user->roles->pluck('name')->toArray(),
                    'permissions' => $isSuperAdmin
                        ? \Spatie\Permission\Models\Permission::pluck('name')->toArray()
                        : $user->getAllPermissions()->pluck('name')->toArray(),
                ] : null,
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
