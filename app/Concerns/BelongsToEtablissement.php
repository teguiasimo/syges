<?php

namespace App\Concerns;

use App\Models\Etablissement;
use App\Models\User;
use App\Scopes\EtablissementScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Trait Eloquent pour les modèles rattachés à un établissement / campus.
 *
 * Fournit :
 * 1. L'application automatique du `EtablissementScope` pour filtrer les lectures (SELECT).
 * 2. L'auto-assignation transparente de `etablissement_id` lors de la création d'un enregistrement (INSERT).
 * 3. La relation Eloquent `etablissement(): BelongsTo`.
 * 4. Des scopes utilitaires pour forcer un campus spécifique (`pourEtablissement`) ou désactiver le filtre (`tousEtablissements`).
 *
 * @mixin Model
 *
 * @method static void addGlobalScope(\Illuminate\Database\Eloquent\Scope|\Closure|string $scope, \Illuminate\Database\Eloquent\Scope|\Closure|null $implementation = null)
 * @method static void creating(\Closure|string|array $callback)
 */
trait BelongsToEtablissement
{
    /**
     * Initialise le trait et enregistre le Global Scope ainsi que les hooks d'événements Eloquent.
     */
    public static function bootBelongsToEtablissement(): void
    {
        // 1. Enregistrement du Global Scope pour le cloisonnement en lecture
        static::addGlobalScope(new EtablissementScope);

        // 2. Auto-assignation et verrouillage de l'établissement lors de la création d'une entité
        static::creating(function ($model) {
            if (Auth::check()) {
                /** @var User $user */
                $user = Auth::user();

                if (! $user->canAccessAllEtablissements()) {
                    // Pour un utilisateur local rattaché à un campus, on force systématiquement son établissement
                    // afin de neutraliser toute tentative d'injection d'un etablissement_id externe dans le payload.
                    $effectiveEtabId = $user->getEffectiveEtablissementId();
                    if ($effectiveEtabId !== null) {
                        $model->etablissement_id = (int) $effectiveEtabId;
                    }
                } elseif (empty($model->etablissement_id) && request()->hasSession() && request()->session()->has('current_etablissement_id')) {
                    // Pour un profil transversal (Super-Admin / Secrétaire globale), on utilise l'établissement actif en session s'il existe
                    $model->etablissement_id = (int) request()->session()->get('current_etablissement_id');
                }
            }
        });
    }

    /**
     * Relation Eloquent avec l'établissement / campus propriétaire.
     */
    public function etablissement(): BelongsTo
    {
        return $this->belongsTo(Etablissement::class, 'etablissement_id');
    }

    /**
     * Scope Eloquent permettant de cibler explicitement un établissement spécifique
     * en désactivant le filtrage automatique contextuel.
     */
    public function scopePourEtablissement(Builder $query, int $etablissementId): Builder
    {
        return $query->withoutGlobalScope(EtablissementScope::class)
            ->where($this->qualifyColumn('etablissement_id'), $etablissementId);
    }

    /**
     * Scope Eloquent désactivant le Global Scope pour obtenir l'ensemble des données
     * tous établissements confondus (ex: rapports consolidés du groupe scolaire).
     */
    public function scopeTousEtablissements(Builder $query): Builder
    {
        return $query->withoutGlobalScope(EtablissementScope::class);
    }
}
