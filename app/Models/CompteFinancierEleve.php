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
     * Relation vers les souscriptions aux prestations et frais optionnels (bus, cantine, internat).
     *
     * @return HasMany<SouscriptionFraisOptionnel>
     */
    public function souscriptionsOptionnelles(): HasMany
    {
        return $this->hasMany(SouscriptionFraisOptionnel::class, 'compte_financier_id');
    }

    /**
     * Calcule le montant des frais obligatoires et souscriptions arrivés à échéance mais non encore réglés.
     *
     * Règle de gestion :
     * Compare le cumul des tranches échues (date_limite <= date de référence) au total déjà versé par l'élève.
     *
     * @param \Carbon\Carbon|null $dateReference Date d'évaluation (par défaut aujourd'hui)
     * @return float Montant en FCFA de la dette échue (0 si à jour ou en avance)
     */
    public function montantEchuEnRetard(?\Carbon\Carbon $dateReference = null): float
    {
        $dateRef = $dateReference ?? \Carbon\Carbon::today();
        $classe = $this->inscription?->classe;
        $niveauId = $classe?->niveau_id;
        $serie = $classe?->serie;

        if (! $niveauId) {
            return 0.0;
        }

        // 1. Recherche des frais obligatoires échus pour ce niveau
        $fraisObligatoiresEchus = FraisScolaire::where('annee_scolaire_id', $this->annee_scolaire_id)
            ->where('etablissement_id', $this->etablissement_id)
            ->where('niveau_id', $niveauId)
            ->where(function ($q) use ($serie) {
                if ($serie) {
                    $q->where('serie', $serie)->orWhereNull('serie');
                } else {
                    $q->whereNull('serie');
                }
            })
            ->whereHas('typeFrais', fn ($q) => $q->where('obligatoire', true))
            ->whereNotNull('date_limite')
            ->whereDate('date_limite', '<=', $dateRef)
            ->sum('montant');

        // 2. Frais optionnels souscrits actifs échus
        $optionsEchues = $this->souscriptionsOptionnelles()
            ->where('statut', 'actif')
            ->whereHas('fraisScolaire', function ($q) use ($dateRef) {
                $q->whereNotNull('date_limite')->whereDate('date_limite', '<=', $dateRef);
            })
            ->sum('montant');

        // Arriérés antérieurs toujours considérés comme échus dès la rentrée
        $arrieres = (float) ($this->arrieres_anterieurs ?? 0);
        $totalExigibleEchu = (float) $fraisObligatoiresEchus + (float) $optionsEchues + $arrieres;

        // Déduction de la remise/bourse au prorata
        $netExigibleEchu = max(0, $totalExigibleEchu - (float) ($this->remise_bourse ?? 0));

        // Comparaison avec les versements effectifs
        $paye = (float) ($this->total_paye ?? 0);

        return max(0, $netExigibleEchu - $paye);
    }

    /**
     * Détermine le statut financier global de l'élève selon le cumul versé et les tranches (Gesco V1.1).
     *
     * @return string 'solde', 'insolvable', 'en_regle_tranche_1', ou 'en_regle_tranche_2'
     */
    public function determinerStatutFinancier(): string
    {
        if ($this->solde_restant <= 0 && $this->montant_net_exigible > 0) {
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

        // 2. Cumul des prestations optionnelles actives souscrites par l'élève (Bus, Cantine, Activités...)
        $totalOptions = (float) $this->souscriptionsOptionnelles()
            ->where('statut', 'actif')
            ->sum('montant');

        // 3. Cumul des pénalités actives (non annulées)
        $totalPenalites = (float) $this->penalites()
            ->where('statut', '!=', 'annule')
            ->sum('montant');

        // 4. Calcul du montant net exigible (débit initial obligatoire + options + arriérés + pénalités - remises/bourses)
        $debitTotal = (float) $this->debit_initial + $totalOptions + (float) ($this->arrieres_anterieurs ?? 0) + $totalPenalites;
        $this->montant_net_exigible = max(0, $debitTotal - (float) $this->remise_bourse);

        // 5. Cumul des versements au statut 'valide'
        $this->total_paye = (float) $this->versements()
            ->where('statut', 'valide')
            ->sum('montant_total');

        // 6. Calcul du solde restant dû
        $this->solde_restant = max(0, $this->montant_net_exigible - $this->total_paye);

        // 7. Détermination du statut financier (calcul temporel sur dates d'échéances)
        $this->statut_financier = $this->determinerStatutFinancier();

        $this->save();
    }
}
