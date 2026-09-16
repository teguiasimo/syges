<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modèle représentant les Délibérations du Conseil de Classe (Gesco V1.1 Module 15 & Module 2.6).
 *
 * Gère :
 * - Les délibérations trimestrielles et annuelles par classe.
 * - Le calcul automatique et le verrouillage des mentions d'honneur (Gesco 12.4 & 4.3).
 * - La proposition automatisée de passage de classe (Promu / Redouble / Exclu) basée sur la M.A.G. (Gesco 2.6 Étape A).
 * - La décision finale retenue par le conseil et la validation humaine définitive (Gesco 2.6 Étape B).
 *
 * @property int $id Identifiant unique de la délibération
 * @property int $etablissement_id Établissement de rattachement
 * @property int $inscription_id Inscription scolaire de l'élève
 * @property int $classe_id Classe de l'élève
 * @property int $annee_scolaire_id Année scolaire
 * @property int|null $periode_id Période trimestrielle (null pour bilan annuel)
 * @property string $type_deliberation 'trimestrielle' ou 'annuelle'
 * @property float $moyenne_obtenue Moyenne chiffrée (MGT ou MAG)
 * @property int|null $rang Rang de l'élève dans sa classe
 * @property string $mention_obtenue 'aucune', 'tableau_honneur', 'encouragements', 'felicitations'
 * @property bool $mention_bloquee Indique si la distinction a été refusée pour indiscipline ou note éliminatoire
 * @property string|null $motif_blocage_mention Raison du refus
 * @property string $statut_propose 'promu', 'redouble', 'exclu', 'en_attente'
 * @property string $statut_retenu 'promu', 'redouble', 'exclu', 'en_attente'
 * @property string|null $appreciation_globale Appréciation qualitative du conseil
 * @property string|null $decision_conseil Formulation textuelle de la décision
 * @property string|null $observations Notes complémentaires
 * @property bool $est_valide Validation définitive par la direction
 * @property int|null $valide_par_user_id Utilisateur ayant validé la promotion
 * @property \Illuminate\Support\Carbon|null $valide_at Date et heure de validation
 */
class DeliberationConseil extends Model
{
    use HasFactory;

    /**
     * Nom de la table associée dans la base de données.
     *
     * @var string
     */
    protected $table = 'deliberations_conseils';

    /**
     * Les attributs assignables en masse.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'etablissement_id',
        'inscription_id',
        'classe_id',
        'annee_scolaire_id',
        'periode_id',
        'type_deliberation',
        'moyenne_obtenue',
        'rang',
        'mention_obtenue',
        'mention_bloquee',
        'motif_blocage_mention',
        'statut_propose',
        'statut_retenu',
        'appreciation_globale',
        'decision_conseil',
        'observations',
        'est_valide',
        'valide_par_user_id',
        'valide_at',
    ];

    /**
     * Les attributs devant être convertis en types natifs.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'moyenne_obtenue' => 'float',
        'rang' => 'integer',
        'mention_bloquee' => 'boolean',
        'est_valide' => 'boolean',
        'valide_at' => 'datetime',
    ];

    /**
     * Relation vers l'établissement scolaire.
     *
     * @return BelongsTo<Etablissement, DeliberationConseil>
     */
    public function etablissement(): BelongsTo
    {
        return $this->belongsTo(Etablissement::class, 'etablissement_id');
    }

    /**
     * Relation vers l'inscription de l'élève.
     *
     * @return BelongsTo<Inscription, DeliberationConseil>
     */
    public function inscription(): BelongsTo
    {
        return $this->belongsTo(Inscription::class, 'inscription_id');
    }

    /**
     * Relation vers la classe de rattachement.
     *
     * @return BelongsTo<Classe, DeliberationConseil>
     */
    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class, 'classe_id');
    }

    /**
     * Relation vers l'année scolaire.
     *
     * @return BelongsTo<AnneeScolaire, DeliberationConseil>
     */
    public function anneeScolaire(): BelongsTo
    {
        return $this->belongsTo(AnneeScolaire::class, 'annee_scolaire_id');
    }

    /**
     * Relation vers la période trimestrielle (optionnelle si délibération annuelle).
     *
     * @return BelongsTo<Periode, DeliberationConseil>
     */
    public function periode(): BelongsTo
    {
        return $this->belongsTo(Periode::class, 'periode_id');
    }

    /**
     * Relation vers l'utilisateur ayant validé la délibération / promotion.
     *
     * @return BelongsTo<User, DeliberationConseil>
     */
    public function valideParUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'valide_par_user_id');
    }
}
