<?php

namespace App\Models;

use App\Concerns\BelongsToEtablissement;
use App\Services\FinanceService;
use Carbon\Carbon;
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
 * @property float $total_dispense Cumul des dispenses financières (ex: arrivée au 2ème trimestre)
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
    use BelongsToEtablissement, HasFactory;

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
        'total_dispense',
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
        'total_dispense' => 'float',
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
     * Relation vers les souscriptions aux prestations et frais optionnels (bus, cantine, internat).
     *
     * @return HasMany<SouscriptionFraisOptionnel>
     */
    public function souscriptionsOptionnelles(): HasMany
    {
        return $this->hasMany(SouscriptionFraisOptionnel::class, 'compte_financier_id');
    }

    /**
     * Relation vers les dispenses financières accordées par tranche (ex: arrivée au 2ème trimestre).
     *
     * @return HasMany<DispenseFinanciere>
     */
    public function dispensesFinancieres(): HasMany
    {
        return $this->hasMany(DispenseFinanciere::class, 'compte_financier_id');
    }

    /**
     * Calcule le montant des frais obligatoires et souscriptions arrivés à échéance mais non encore réglés.
     *
     * Délègue au moteur central de FinanceService afin de prendre en compte exactement :
     * - Les dispenses financières par tranche (totales ou partielles),
     * - La cible d'imputation configurée pour chaque bourse/remise,
     * - Les ventilations ciblées et l'ordre de priorité propre à l'établissement.
     *
     * @param  Carbon|null  $dateReference  Date d'évaluation (par défaut aujourd'hui)
     * @return float Montant en FCFA de la dette échue (0 si à jour ou en avance)
     */
    public function montantEchuEnRetard(?Carbon $dateReference = null): float
    {
        return app(FinanceService::class)->calculerMontantEchuEnRetard($this, $dateReference);
    }

    /**
     * Détermine le statut financier global de l'élève selon le cumul versé et les tranches (Gesco V1.1).
     *
     * @return string 'solde', 'insolvable', 'en_regle_tranche_1', ou 'en_regle_tranche_2'
     */
    public function determinerStatutFinancier(): string
    {
        if ($this->solde_restant <= 0 && ($this->montant_net_exigible > 0 || $this->debit_initial > 0)) {
            return 'solde';
        }

        if ($this->total_paye <= 0) {
            return 'insolvable';
        }

        $taux = $this->montant_net_exigible > 0 ? ($this->total_paye / $this->montant_net_exigible) : 1;
        if ($taux >= 0.6) {
            return 'en_regle_tranche_2';
        }

        return 'en_regle_tranche_1';
    }

    /**
     * Recalcule et persiste instantanément les soldes et statuts du compte élève,
     * en intégrant les dispenses par tranche, les bourses/remises, les options et les pénalités.
     */
    public function actualiserSolde(): void
    {
        // 1. Calcul des dispenses financières par tranche (ex: arrivée au 2ème trimestre)
        $dispenses = $this->dispensesFinancieres()->with('fraisScolaire')->get();
        $sommeDispenses = 0.0;
        foreach ($dispenses as $dispense) {
            $plafondFrais = $dispense->fraisScolaire
                ? (float) $dispense->fraisScolaire->montant
                : (float) $dispense->montant_dispense;
            $sommeDispenses += min((float) $dispense->montant_dispense, $plafondFrais);
        }
        $this->total_dispense = min((float) $this->debit_initial, $sommeDispenses);
        $baseObligatoireApresDispense = max(0.0, (float) $this->debit_initial - $this->total_dispense);

        // 2. Prise en compte et réajustement automatique des bourses et remises attribuées
        $attributions = $this->boursesRemises()->with('bourseRemise')->get();
        if ($attributions->isNotEmpty()) {
            $cumulBourses = 0.0;
            foreach ($attributions as $attr) {
                $modele = $attr->bourseRemise;
                if ($modele) {
                    $plafondCible = $baseObligatoireApresDispense;
                    if ($attr->frais_scolaire_id) {
                        $fraisCible = FraisScolaire::find($attr->frais_scolaire_id);
                        if ($fraisCible) {
                            $dispenseSurCible = (float) ($dispenses->firstWhere('frais_scolaire_id', $fraisCible->id)?->montant_dispense ?? 0);
                            $plafondCible = max(0.0, (float) $fraisCible->montant - $dispenseSurCible);
                        }
                    }

                    $nouveauMontant = $modele->mode_calcul === 'pourcentage'
                        ? round(($plafondCible * (float) $modele->valeur) / 100)
                        : min($baseObligatoireApresDispense, (float) $modele->valeur);

                    if (abs((float) $attr->montant_calcule - $nouveauMontant) > 0.01) {
                        $attr->update(['montant_calcule' => $nouveauMontant]);
                    }
                    $cumulBourses += $nouveauMontant;
                } else {
                    $cumulBourses += (float) $attr->montant_calcule;
                }
            }
            $this->remise_bourse = min($baseObligatoireApresDispense, $cumulBourses);
        }

        // 3. Cumul des prestations optionnelles actives souscrites par l'élève (Bus, Cantine, Activités...)
        $totalOptions = (float) $this->souscriptionsOptionnelles()
            ->where('statut', 'actif')
            ->sum('montant');

        // 4. Cumul des pénalités actives (non annulées)
        $totalPenalites = (float) $this->penalites()
            ->where('statut', '!=', 'annule')
            ->sum('montant');

        // 5. Calcul du montant net exigible (obligatoire après dispense + options + arriérés + pénalités - remises/bourses)
        $debitTotal = $baseObligatoireApresDispense + $totalOptions + (float) ($this->arrieres_anterieurs ?? 0) + $totalPenalites;
        $this->montant_net_exigible = max(0, $debitTotal - (float) $this->remise_bourse);

        // 6. Cumul des versements au statut 'valide'
        $this->total_paye = (float) $this->versements()
            ->where('statut', 'valide')
            ->sum('montant_total');

        // 7. Calcul du solde restant dû
        $this->solde_restant = max(0, $this->montant_net_exigible - $this->total_paye);

        // 8. Détermination du statut financier
        $this->statut_financier = $this->determinerStatutFinancier();

        $this->save();
    }
}
