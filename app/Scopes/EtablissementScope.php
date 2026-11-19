<?php

namespace App\Scopes;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Global Scope Eloquent assurant le cloisonnement hermétique par établissement (Multi-Tenant).
 *
 * Règles d'isolation :
 * 1. Utilisateurs Locaux de Campus (Directeur, Censeur, Enseignant, Secrétaire, etc.) :
 *    Filtre automatiquement toutes les requêtes de lecture (SELECT) par leur `etablissement_id`.
 *    Empêche toute fuite ou visualisation de données appartenant à un autre campus.
 *
 * 2. Super-Administrateur (Fondateur / Promoteur / Siège) :
 *    Dispose d'un accès global transversal sans restriction automatique au niveau du Scope,
 *    lui permettant d'administrer l'ensemble des campus, de changer d'établissement actif
 *    et de générer des états statistiques consolidés.
 *
 * 3. Environnements non authentifiés (CLI, Migrations, Seeders, CRONs non authentifiés) :
 *    Aucune restriction n'est appliquée, garantissant la bonne exécution de la maintenance.
 */
class EtablissementScope implements Scope
{
    /**
     * Contexte d'établissement temporaire autorisé par un service métier vérifié
     * (ex. : consultation par un parent d'un enfant déjà authentifié sur un campus précis).
     *
     * @var int|null
     */
    protected static ?int $overrideEtablissementId = null;

    /**
     * Exécute un bloc de code en contextualisant temporairement le Global Scope sur un établissement donné.
     * Restaure systématiquement l'état précédent via `finally` pour préserver l'isolation Multi-Tenant.
     *
     * @template TResult
     * @param int|null $etablissementId Identifiant du campus cible (si null, exécution standard sans forçage)
     * @param callable(): TResult $callback Traitement à exécuter dans ce contexte borné
     * @return TResult
     */
    public static function withEtablissementOverride(?int $etablissementId, callable $callback): mixed
    {
        if ($etablissementId === null) {
            return $callback();
        }

        $previous = self::$overrideEtablissementId;
        self::$overrideEtablissementId = $etablissementId;

        try {
            return $callback();
        } finally {
            self::$overrideEtablissementId = $previous;
        }
    }

    /**
     * Applique la restriction d'établissement sur la requête Eloquent.
     *
     * @param Builder $builder Le constructeur de requête Eloquent.
     * @param Model $model L'instance du modèle Eloquent concerné.
     * @return void
     */
    public function apply(Builder $builder, Model $model): void
    {
        // Si un contexte borné vérifié est actif (ex. : Portail Parents après contrôle d'appartenance), on l'applique en priorité
        if (self::$overrideEtablissementId !== null) {
            $builder->where($model->qualifyColumn('etablissement_id'), self::$overrideEtablissementId);

            return;
        }

        // Si aucun utilisateur n'est authentifié (ex: commandes CLI de migration/seed), pas de restriction
        if (! Auth::check()) {
            return;
        }

        /** @var User $user */
        $user = Auth::user();

        // Le Super-Administrateur et les profils transversaux (ex: Secrétaire Générale globale) ont une vue multi-campus
        if ($user->canAccessAllEtablissements()) {
            return;
        }

        // Récupération de l'établissement effectif du compte local
        $etablissementId = $user->getEffectiveEtablissementId();

        if ($etablissementId !== null) {
            // Utilise qualifyColumn pour éviter toute ambiguïté SQL lors des jointures ('classes.etablissement_id')
            $builder->where($model->qualifyColumn('etablissement_id'), $etablissementId);
        } else {
            // Isolation hermétique : un utilisateur local sans affectation campus valide
            // ne doit sous aucun prétexte accéder aux données globales. On neutralise la requête.
            $builder->whereRaw('1 = 0');
        }
    }
}
