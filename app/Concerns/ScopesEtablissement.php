<?php

namespace App\Concerns;

use App\Models\Etablissement;
use App\Models\Personnel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

/**
 * Trait pour la gestion hermétique de l'étanchéité multi-établissements (Multi-Tenant).
 *
 * Conforme au Cahier des Charges Gesco V1.1 :
 * - Super-Administrateur (rôle `super-admin` ou email `admin@gmail.com`) :
 *   Accès global et transversal à l'ensemble des campus de l'institution, avec sélecteur de campus actif.
 * - Utilisateurs Locaux (Directeur des études, Censeur, Intendant, Enseignant, Secrétaire, etc.) :
 *   Strictement cloisonnés à l'établissement auquel leur fiche `Personnel` est rattachée (`etablissement_id`).
 *   La liste des établissements retournée ne contient que leur campus assigné (masquant tout sélecteur),
 *   et toute tentative d'injection d'un autre `etablissement_id` par URL ou formulaire est neutralisée.
 */
trait ScopesEtablissement
{
    /**
     * Résout l'établissement actif et la collection d'établissements autorisés pour l'utilisateur connecté.
     *
     * @param Request $request Requête HTTP entrante.
     * @param array<int|string, mixed>|null $relations Relations Eloquent optionnelles à charger (ex: `['sections']`).
     * @param array<int|string, mixed>|null $withCount Relations de comptage Eloquent optionnelles (ex: `['sections']`).
     * @return array{
     *     isSuperAdmin: bool,
     *     personnel: Personnel|null,
     *     etablissementId: int|null,
     *     selectedEtablissement: Etablissement|null,
     *     etablissements: \Illuminate\Support\Collection|Collection
     * }
     */
    protected function resolveEtablissementContext(Request $request, ?array $relations = null, ?array $withCount = null): array
    {
        $user = $request->user();
        $isSuperAdmin = $user ? ($user->hasRole('super-admin') || $user->email === 'admin@gmail.com') : false;
        $personnel = $user?->personnel ?? ($user?->email ? Personnel::where('email', $user->email)->first() : null);
        $userEtablissementId = $user?->getEffectiveEtablissementId() ?? $personnel?->etablissement_id;

        // Chargement systématique de l'institution parente pour le fallback du logo effectif
        $query = Etablissement::query()->with('institution')->orderBy('nom');
        if (! empty($relations)) {
            $query->with($relations);
        }
        if (! empty($withCount)) {
            $query->withCount($withCount);
        }
        $allEtablissements = $query->get();

        if (! $isSuperAdmin && $userEtablissementId) {
            // Verrouillage strict : l'utilisateur local ne peut voir QUE son campus
            $etablissementId = (int) $userEtablissementId;
            $selectedEtablissement = $allEtablissements->firstWhere('id', $etablissementId)
                ?: Etablissement::find($etablissementId);
            $etablissements = $selectedEtablissement ? collect([$selectedEtablissement]) : collect();
        } else {
            // Super-Admin ou utilisateur sans affectation campus spécifique
            $requestedId = $request->query('etablissement_id') ?? $request->input('etablissement_id');

            if ($requestedId) {
                $etablissementId = (int) $requestedId;
                if ($request->hasSession()) {
                    $request->session()->put('current_etablissement_id', $etablissementId);
                }
            } else {
                $sessionEtabId = $request->hasSession() ? $request->session()->get('current_etablissement_id') : null;
                $etablissementId = $sessionEtabId ? (int) $sessionEtabId : $allEtablissements->first()?->id;
            }

            $selectedEtablissement = $etablissementId
                ? $allEtablissements->firstWhere('id', $etablissementId)
                : $allEtablissements->first();
            $etablissements = $allEtablissements;
        }

        return [
            'isSuperAdmin' => $isSuperAdmin,
            'personnel' => $personnel,
            'etablissementId' => $etablissementId,
            'selectedEtablissement' => $selectedEtablissement,
            'etablissements' => $etablissements,
        ];
    }
}
