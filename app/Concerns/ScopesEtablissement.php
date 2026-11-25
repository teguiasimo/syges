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
     * @param  Request  $request  Requête HTTP entrante.
     * @param  array<int|string, mixed>|null  $relations  Relations Eloquent optionnelles à charger (ex: `['sections']`).
     * @param  array<int|string, mixed>|null  $withCount  Relations de comptage Eloquent optionnelles (ex: `['sections']`).
     * @return array{
     *     isSuperAdmin: bool,
     *     canAccessAll: bool,
     *     personnel: Personnel|null,
     *     etablissementId: int|null,
     *     selectedEtablissement: Etablissement|null,
     *     etablissements: \Illuminate\Support\Collection|Collection
     * }
     */
    protected function resolveEtablissementContext(Request $request, ?array $relations = null, ?array $withCount = null): array
    {
        $user = $request->user();
        $isSuperAdmin = $user ? $user->isSuperAdmin() : false;
        $canAccessAll = $isSuperAdmin || ($user ? $user->canAccessAllEtablissements() : false);
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

        if (! $canAccessAll) {
            // Verrouillage strict : l'utilisateur local ne peut voir QUE son campus
            if ($userEtablissementId) {
                $etablissementId = (int) $userEtablissementId;
                $selectedEtablissement = $allEtablissements->firstWhere('id', $etablissementId)
                    ?: Etablissement::find($etablissementId);
                $etablissements = $selectedEtablissement ? collect([$selectedEtablissement]) : collect();
            } else {
                // Compte local sans aucun campus assigné : aucun campus accessible (cloisonnement de sécurité)
                $etablissementId = null;
                $selectedEtablissement = null;
                $etablissements = collect();
            }
        } else {
            // Navigation transversale (Super-Admin et Secrétaire Générale multi-établissements)
            $requestedId = $request->query('etablissement_id') ?? $request->input('etablissement_id');

            // Ignorer la valeur « all » (mode consolidé multi-campus) afin de ne jamais corrompre l'ID numérique actif en session
            if ($requestedId !== null && $requestedId !== '' && $requestedId !== 'all' && is_numeric($requestedId)) {
                $etablissementId = (int) $requestedId;
                if ($request->hasSession()) {
                    $request->session()->put('current_etablissement_id', $etablissementId);
                }
            } else {
                $sessionEtabId = $request->hasSession() ? $request->session()->get('current_etablissement_id') : null;
                $etablissementId = ($sessionEtabId && is_numeric($sessionEtabId))
                    ? (int) $sessionEtabId
                    : $allEtablissements->first()?->id;
            }

            $selectedEtablissement = $etablissementId
                ? $allEtablissements->firstWhere('id', $etablissementId)
                : $allEtablissements->first();
            $etablissements = $allEtablissements;
        }

        return [
            'isSuperAdmin' => $isSuperAdmin,
            'canAccessAll' => $canAccessAll,
            'personnel' => $personnel,
            'etablissementId' => $etablissementId,
            'selectedEtablissement' => $selectedEtablissement,
            'etablissements' => $etablissements,
        ];
    }

    /**
     * Vérifie et résout l'identifiant d'établissement autorisé pour une opération d'écriture.
     * Si l'utilisateur connecté est restreint à un seul campus, toute tentative de cibler un autre
     * établissement déclenche un rejet HTTP 403.
     *
     * @param  Request  $request  Requête HTTP entrante.
     * @param  int|string|null  $requestedEtablissementId  Identifiant d'établissement transmis dans le payload ou l'URL.
     * @return int|null Identifiant d'établissement validé et autorisé.
     */
    protected function authorizeEtablissementWrite(Request $request, int|string|null $requestedEtablissementId = null): ?int
    {
        $context = $this->resolveEtablissementContext($request);
        $canAccessAll = (bool) ($context['canAccessAll'] ?? $context['isSuperAdmin']);
        $scopedEtabId = $context['etablissementId'] ? (int) $context['etablissementId'] : null;
        $targetId = ($requestedEtablissementId !== null && $requestedEtablissementId !== '' && is_numeric($requestedEtablissementId))
            ? (int) $requestedEtablissementId
            : null;

        if (! $canAccessAll && $scopedEtabId !== null) {
            if ($targetId !== null && $targetId !== $scopedEtabId) {
                abort(403, 'Accès non autorisé : vous ne pouvez pas effectuer cette opération sur un autre établissement.');
            }

            return $scopedEtabId;
        }

        return $targetId ?? $scopedEtabId;
    }
}
