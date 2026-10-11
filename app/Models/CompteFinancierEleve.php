<?php

namespace App\Models;

use App\Concerns\BelongsToEtablissement;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modèle représentant le Compte Financier d'un Élève pour une Année Scolaire (Gesco V1.1 - Module 11.1).
 *
 * Tient à jour en temps réel l'ardoise financière de l'élève :
 * - Débit initial (Scolarité + APEE + options)
 * - Remise / Bourse d'études
 * - Montant net exigible
 * - Total payé
 * - Solde restant dû
 * - Statut financier global ('insolvable', 'en_regle_tranche_1', 'en_regle_tranche_2', 'solde')
 *
 * @property int $id Identifiant unique du compte financier
 * @property int $inscription_id Inscription scolaire liée
 * @property int $eleve_id Profil élève
 * @property int $etablissement_id Établissement
 * @property int $annee_scolaire_id Année scolaire
 * @property float $debit_initial Montant total initial dû
 * @property float $arrieres_anterieurs Arriérés ou dettes reportées de l'année précédente
 * @property float $remise_bourse Réduction financière ou bourse accordée
 * @property float $montant_net_exigible Montant net dû après remise
 * @property float $total_paye Cumul des versements validés
 * @property float $solde_restant Reste à payer
 * @property string $statut_financier État financier global
 * @property string|null $observations Remarques éventuelles de l'intendance
 */
class CompteFinancierEleve extends Model
{
    use HasFactory, BelongsToEtablissement;

    /**
     * Nom de la table associée dans la base de données.
     *
     * @var string
     */
    protected $table = 'comptes_financiers_eleves';

    /**
     * Les attributs assignables en masse.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'inscription_id',
        'eleve_id',
        'etablissement_id',
        'annee_scolaire_id',
        'debit_initial',
        'arrieres_anterieurs',
        'remise_bourse',
        'montant_net_exigible',
        'total_paye',
        'solde_restant',
        'statut_financier',
        'observations',
    ];

    /**
     * Les attributs convertis dans des types natifs.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'debit_initial' => 'float',
        'arrieres_anterieurs' => 'float',
        'remise_bourse' => 'float',
        'montant_net_exigible' => 'float',
        'total_paye' => 'float',
        'solde_restant' => 'float',
    ];

    /**
     * Relation vers l'inscription de l'élève.
     *
     * @return BelongsTo<Inscription, CompteFinancierEleve>
     */
    public function inscription(): BelongsTo
    {
        return $this->belongsTo(Inscription::class);
    }

    /**
     * Relation vers le profil élève.
     *
     * @return BelongsTo<Eleve, CompteFinancierEleve>
     */
    public function eleve(): BelongsTo
    {
        return $this->belongsTo(Eleve::class);
    }


    /**
     * Relation vers l'année scolaire.
     *
     * @return BelongsTo<AnneeScolaire, CompteFinancierEleve>
     */
    public function anneeScolaire(): BelongsTo
    {
        return $this->belongsTo(AnneeScolaire::class);
    }

    /**
     * Relation vers l'ensemble des versements effectués sur ce compte.
     *
     * @return HasMany<VersementCaisse>
     */
    public function versements(): HasMany
    {
        return $this->hasMany(VersementCaisse::class, 'compte_financier_id');
    }

    /**
     * Relation vers les bourses et remises accordées à cet élève.
     *
     * @return HasMany<EleveBourseRemise>
     */
    public function boursesRemises(): HasMany
    {
        return $this->hasMany(EleveBourseRemise::class, 'compte_financier_id');
    }

    /**
     * Relation vers les pénalités de retard appliquées à ce compte.
     *
     * @return HasMany<PenaliteEleve>
     */
    public function penalites(): HasMany
    {
        return $this->hasMany(PenaliteEleve::class, 'compte_financier_id');
    }

    /**
     * Recalcule et persiste instantanément les soldes et statuts du compte élève.
     *
     * @return void
     */
    public function actualiserSolde(): void
    {
        // 1. Prise en compte automatique des bourses et remises attribuées
        $totalBoursesRemises = (float) $this->boursesRemises()->sum('montant_calcule');
        if ($totalBoursesRemises > 0) {
            $this->remise_bourse = $totalBoursesRemises;
        }

        // 2. Cumul des pénalités actives (non annulées)
        $totalPenalites = (float) $this->penalites()
            ->where('statut', '!=', 'annule')
            ->sum('montant');

        // 3. Calcul du montant net exigible (débit initial + arriérés + pénalités - remises/bourses)
        $debitTotal = (float) $this->debit_initial + (float) ($this->arrieres_anterieurs ?? 0) + $totalPenalites;
        $this->montant_net_exigible = max(0, $debitTotal - (float) $this->remise_bourse);

        // 4. Cumul des versements au statut 'valide'
        $this->total_paye = (float) $this->versements()
            ->where('statut', 'valide')
            ->sum('montant_total');

        // 5. Calcul du solde restant dû
        $this->solde_restant = max(0, $this->montant_net_exigible - $this->total_paye);

        // 6. Détermination du statut financier
        if ($this->solde_restant <= 0 && $this->montant_net_exigible > 0) {
            $this->statut_financier = 'solde';
        } elseif ($this->total_paye <= 0) {
            $this->statut_financier = 'insolvable';
        } else {
            // Statut intermédiaire selon le prorata payé
            $tauxPaiement = $this->montant_net_exigible > 0 ? ($this->total_paye / $this->montant_net_exigible) : 1;
            if ($tauxPaiement >= 0.6) {
                $this->statut_financier = 'en_regle_tranche_2';
            } else {
                $this->statut_financier = 'en_regle_tranche_1';
            }
        }

        $this->save();
    }
}
