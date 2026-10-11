<?php

namespace App\Models;

use App\Concerns\BelongsToEtablissement;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modèle représentant une Dépense / Décaissement de Caisse (Phase 1).
 *
 * Enregistre chaque sortie de trésorerie avec son imputation budgétaire,
 * son bénéficiaire, son mode de paiement et sa fiche de décharge officielle.
 *
 * @property int $id
 * @property int $etablissement_id
 * @property int $annee_scolaire_id
 * @property int $caisse_id
 * @property int $categorie_depense_id
 * @property string $numero_piece
 * @property string $beneficiaire
 * @property float $montant
 * @property \Illuminate\Support\Carbon $date_depense
 * @property string $motif
 * @property string $mode_reglement
 * @property string|null $reference_paiement
 * @property string|null $justificatif_path
 * @property string $statut
 * @property int|null $valide_par
 * @property int|null $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Etablissement $etablissement
 * @property-read \App\Models\AnneeScolaire $anneeScolaire
 * @property-read \App\Models\Caisse $caisse
 * @property-read \App\Models\CategorieDepense $categorieDepense
 * @property-read \App\Models\User|null $auteur
 * @property-read \App\Models\User|null $validateur
 */
class Depense extends Model
{
    use HasFactory, BelongsToEtablissement;

    /**
     * Nom de la table associée.
     *
     * @var string
     */
    protected $table = 'depenses';

    /**
     * Attributs assignables en masse.
     *
     * @var list<string>
     */
    protected $fillable = [
        'etablissement_id',
        'annee_scolaire_id',
        'caisse_id',
        'categorie_depense_id',
        'numero_piece',
        'beneficiaire',
        'montant',
        'date_depense',
        'motif',
        'mode_reglement',
        'reference_paiement',
        'justificatif_path',
        'statut',
        'valide_par',
        'created_by',
    ];

    /**
     * Casts des attributs.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'date_depense' => 'date',
        'montant' => 'decimal:2',
    ];

    /**
     * Relation vers l'établissement.
     *
     * @return BelongsTo
     */
    public function etablissement(): BelongsTo
    {
        return $this->belongsTo(Etablissement::class);
    }

    /**
     * Relation vers l'année scolaire.
     *
     * @return BelongsTo
     */
    public function anneeScolaire(): BelongsTo
    {
        return $this->belongsTo(AnneeScolaire::class);
    }

    /**
     * Relation vers la caisse débitée.
     *
     * @return BelongsTo
     */
    public function caisse(): BelongsTo
    {
        return $this->belongsTo(Caisse::class);
    }

    /**
     * Relation vers la catégorie budgétaire.
     *
     * @return BelongsTo
     */
    public function categorieDepense(): BelongsTo
    {
        return $this->belongsTo(CategorieDepense::class, 'categorie_depense_id');
    }

    /**
     * Relation vers l'utilisateur ayant saisi la dépense.
     *
     * @return BelongsTo
     */
    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relation vers l'ordonnateur ayant validé la dépense.
     *
     * @return BelongsTo
     */
    public function validateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'valide_par');
    }

    /**
     * Génère automatiquement le numéro de pièce de dépense suivant pour l'établissement.
     *
     * @param int $etablissementId
     * @return string
     */
    public static function genererNumeroPiece(int $etablissementId): string
    {
        $annee = date('Y');
        $dernier = self::where('etablissement_id', $etablissementId)
            ->whereYear('date_depense', $annee)
            ->count();

        $numero = str_pad($dernier + 1, 4, '0', STR_PAD_LEFT);
        return "DEP-{$annee}/{$numero}";
    }
}
