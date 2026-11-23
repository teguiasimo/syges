<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Journal d'audit traçant chaque fusion de doublon élève exécutée dans SYGES.
 *
 * Conserve de façon pérenne l'ancien matricule absorbé, les montants financiers transférés,
 * le nombre de notes et conflits résolus ainsi que l'auteur de l'opération afin de garantir
 * une traçabilité comptable et administrative complète.
 *
 * @property int $id
 * @property int|null $etablissement_id
 * @property int $master_eleve_id
 * @property string $master_matricule
 * @property int $absorbed_eleve_id
 * @property string $absorbed_matricule
 * @property string $absorbed_nom_complet
 * @property int|null $merged_by_user_id
 * @property float $montant_versements_transferes
 * @property int $nb_versements_transferes
 * @property int $nb_notes_transferees
 * @property int $nb_conflits_notes_resolus
 * @property array<string, mixed>|null $resume_json
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class EleveFusionAudit extends Model
{
    /**
     * Nom de la table associée.
     *
     * @var string
     */
    protected $table = 'eleves_fusions_audit';

    /**
     * Attributs assignables en masse.
     *
     * @var list<string>
     */
    protected $fillable = [
        'etablissement_id',
        'master_eleve_id',
        'master_matricule',
        'absorbed_eleve_id',
        'absorbed_matricule',
        'absorbed_nom_complet',
        'merged_by_user_id',
        'montant_versements_transferes',
        'nb_versements_transferes',
        'nb_notes_transferees',
        'nb_conflits_notes_resolus',
        'resume_json',
    ];

    /**
     * Conversions de types natifs.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'montant_versements_transferes' => 'float',
        'nb_versements_transferes' => 'integer',
        'nb_notes_transferees' => 'integer',
        'nb_conflits_notes_resolus' => 'integer',
        'resume_json' => 'array',
    ];

    /**
     * Dossier élève Maître ayant absorbé le doublon.
     *
     * @return BelongsTo<Eleve, EleveFusionAudit>
     */
    public function masterEleve(): BelongsTo
    {
        return $this->belongsTo(Eleve::class, 'master_eleve_id');
    }

    /**
     * Utilisateur administrateur ayant validé la fusion.
     *
     * @return BelongsTo<User, EleveFusionAudit>
     */
    public function mergedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'merged_by_user_id');
    }

    /**
     * Établissement principal concerné par la fusion.
     *
     * @return BelongsTo<Etablissement, EleveFusionAudit>
     */
    public function etablissement(): BelongsTo
    {
        return $this->belongsTo(Etablissement::class, 'etablissement_id');
    }
}
