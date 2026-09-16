<?php

namespace App\Services;

use App\Models\AnneeScolaire;
use App\Models\Eleve;
use App\Models\Inscription;
use App\Models\Periode;
use App\Models\SeanceEmploiTemps;
use App\Models\User;
use App\Models\VersementCaisse;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

/**
 * Service Métier pour le Module 16 : Portail Parents & Élèves (Gesco V1.1).
 *
 * Coordonne :
 * - 16.1 : Détection multi-enfants par numéro de téléphone principal ou compte rattaché.
 * - 16.2 : Agrégation du profil complet de l'élève (Académique, Emploi du temps, Discipline, Finances).
 * - 16.3 : Application stricte du verrou visuel de solvabilité financière (Module 11.5 & 16.3).
 * - Téléchargement sécurisé des bulletins scolaires et des reçus de caisse certifiés.
 */
class PortailParentService
{
    /**
     * Service de calcul des résultats pédagogiques.
     */
    protected BulletinCalculationService $bulletinService;

    /**
     * Constructeur injectant le calculateur de bulletins.
     *
     * @param BulletinCalculationService $bulletinService
     */
    public function __construct(BulletinCalculationService $bulletinService)
    {
        $this->bulletinService = $bulletinService;
    }

    /**
     * Récupère la liste des enfants rattachés au compte utilisateur connecté (Gesco 16.1).
     *
     * Détecte les élèves dont le numéro `telephone_parent` ou `email_parent` correspond à l'utilisateur,
     * ou donne accès au répertoire pour les profils de direction/administration.
     *
     * @param User $user
     * @return Collection<int, Eleve>
     */
    public function getEnfantsRattaches(User $user): Collection
    {
        // 1. Profil Administrateur ou Direction (permet d'inspecter ou prévisualiser n'importe quel élève)
        if ($user->hasRole('super-admin') || $user->hasRole('admin') || $user->hasRole('directeur-etudes') || $user->can('eleves.view')) {
            return Eleve::where('is_active', true)
                ->with(['inscriptions' => fn ($q) => $q->where('statut', 'validee')->latest('date_inscription')->with('classe.niveau')])
                ->take(30)
                ->get();
        }

        // 2. Profil Parent : correspondance par numéro de téléphone ou email
        $identifiants = array_filter([
            $user->email,
            $user->login,
            preg_replace('/[^0-9]/', '', (string) $user->login),
            preg_replace('/[^0-9]/', '', (string) $user->email),
        ]);

        return Eleve::where('is_active', true)
            ->where(function ($query) use ($identifiants, $user) {
                foreach ($identifiants as $id) {
                    if (! empty($id)) {
                        $query->orWhere('telephone_parent', 'like', "%{$id}%")
                            ->orWhere('email_parent', $id);
                    }
                }
            })
            ->with(['inscriptions' => fn ($q) => $q->where('statut', 'validee')->latest('date_inscription')->with('classe.niveau')])
            ->get();
    }

    /**
     * Récupère le profil complet d'un élève pour le portail parent (Gesco 16.2).
     *
     * @param Eleve $eleve
     * @param int|null $anneeId
     * @param int|null $periodeId
     * @return array<string, mixed>
     */
    public function getProfilCompletEnfant(Eleve $eleve, ?int $anneeId = null, ?int $periodeId = null): array
    {
        $annee = $anneeId ? AnneeScolaire::find($anneeId) : (AnneeScolaire::current() ?? AnneeScolaire::latest()->first());

        // Inscription active de l'élève
        $inscription = Inscription::with(['classe.niveau.cycle.section.etablissement', 'compteFinancier.versements'])
            ->where('eleve_id', $eleve->id)
            ->where('statut', 'validee')
            ->where('annee_scolaire_id', $annee?->id)
            ->first();

        if (! $inscription) {
            return [
                'eleve' => $eleve,
                'inscription' => null,
                'classe' => null,
                'academique' => null,
                'emploi_du_temps' => [],
                'discipline' => ['absences_justifiees' => 0, 'absences_non_justifiees' => 0, 'sanctions' => []],
                'finances' => null,
                'verrou_solvabilite' => ['est_bloque' => false, 'message' => null],
            ];
        }

        $classe = $inscription->classe;
        $etablissement = $classe->niveau->cycle->section->etablissement;

        // 1. Périodes et Évaluations
        $periodes = Periode::where('annee_scolaire_id', $annee->id)
            ->orderBy('ordre')
            ->get();

        $activePeriode = $periodeId ? Periode::find($periodeId) : $periodes->first();

        // 2. Résultats Académiques (Notes, moyennes, rangs via BulletinCalculationService)
        $donneesClasse = $activePeriode
            ? $this->bulletinService->calculerTrimestre($classe, $activePeriode)
            : $this->bulletinService->calculerAnnuel($classe, $annee);

        $donneesEleve = collect($donneesClasse['eleves'] ?? [])
            ->firstWhere('inscription_id', $inscription->id);

        // 3. Emploi du Temps Dynamique de la classe (Gesco 13 & 16.2)
        $emploiDuTemps = SeanceEmploiTemps::with(['attribution.matiere', 'attribution.personnel', 'salle'])
            ->where('classe_id', $classe->id)
            ->where('annee_scolaire_id', $annee->id)
            ->where('is_active', true)
            ->orderBy('jour_semaine')
            ->orderBy('heure_debut')
            ->get()
            ->map(function ($seance) {
                return [
                    'id' => $seance->id,
                    'jour_semaine' => $seance->jour_semaine,
                    'jour_libelle' => $seance->jour_libelle,
                    'heure_debut' => substr((string) $seance->heure_debut, 0, 5),
                    'heure_fin' => substr((string) $seance->heure_fin, 0, 5),
                    'plage_horaire' => $seance->plage_horaire,
                    'matiere_nom' => $seance->attribution?->matiere?->nom ?? 'Cours',
                    'matiere_code' => $seance->attribution?->matiere?->code ?? 'N/A',
                    'enseignant_nom' => $seance->enseignantActuel?->nom_complet ?? 'Non assigné',
                    'salle_nom' => $seance->salle?->nom ?? 'Salle classe',
                    'statut' => $seance->statut,
                    'is_annule' => $seance->isAnnule(),
                ];
            });

        // 4. Assiduité & Discipline (Gesco 12 & 16.2)
        $disciplineData = [
            'absences_justifiees' => (int) ($donneesEleve['discipline']['absences_justifiees'] ?? 0),
            'absences_non_justifiees' => (int) ($donneesEleve['discipline']['absences_non_justifiees'] ?? 0),
            'consignes' => (int) ($donneesEleve['discipline']['consignes'] ?? 0),
            'sanctions' => $inscription->sanctionsDisciplinaires ?? [],
        ];

        // 5. Volet Financier (Gesco 11 & 16.2)
        $compte = $inscription->compteFinancier;
        $versements = $compte ? $compte->versements()->where('statut', 'valide')->latest('date_versement')->get() : collect();

        $financesData = [
            'compte_id' => $compte?->id,
            'debit_initial' => (float) ($compte?->debit_initial ?? 0),
            'remise_bourse' => (float) ($compte?->remise_bourse ?? 0),
            'montant_net_exigible' => (float) ($compte?->montant_net_exigible ?? 0),
            'total_paye' => (float) ($compte?->total_paye ?? 0),
            'solde_restant' => (float) ($compte?->solde_restant ?? 0),
            'statut_financier' => $compte?->statut_financier ?? 'en_attente',
            'versements' => $versements->map(function ($v) {
                return [
                    'id' => $v->id,
                    'numero_recu' => $v->reference_recu,
                    'date_versement' => $v->date_versement?->format('d/m/Y') ?? 'N/A',
                    'montant' => (float) $v->montant_total,
                    'mode_paiement' => ucfirst(str_replace('_', ' ', $v->mode_paiement ?? 'Espèces')),
                    'motif' => $v->observations ?? 'Paiement scolarité',
                ];
            }),
        ];

        // 6. Application du Verrou Visuel de Solvabilité (Gesco 11.5 & 16.3)
        $verrouActif = (bool) ($etablissement->verrou_solvabilite_actif ?? false);
        $soldeRestant = (float) ($compte?->solde_restant ?? 0);
        $isBloque = $verrouActif && ($soldeRestant > 0);

        $nomEnfant = trim("{$eleve->nom} {$eleve->prenom}");
        $messageVerrou = $isBloque
            ? "Accès restreint. Veuillez régulariser le solde financier de {$nomEnfant} auprès de l'intendance pour débloquer le bulletin."
            : null;

        return [
            'eleve' => [
                'id' => $eleve->id,
                'matricule' => $eleve->matricule,
                'matricule_national' => $eleve->matricule_national,
                'nom' => $eleve->nom,
                'prenom' => $eleve->prenom,
                'nom_complet' => $nomEnfant,
                'sexe' => $eleve->sexe,
                'date_naissance' => $eleve->date_naissance?->format('d/m/Y'),
                'lieu_naissance' => $eleve->lieu_naissance,
                'photo_url' => $eleve->photo_url,
            ],
            'inscription' => [
                'id' => $inscription->id,
                'statut' => $inscription->statut,
                'is_redoublant' => (bool) $inscription->is_redoublant,
            ],
            'classe' => [
                'id' => $classe->id,
                'nom' => $classe->nom,
                'professeur_principal' => $classe->professeurPrincipal?->nom_complet ?? 'Non assigné',
            ],
            'annee_scolaire' => [
                'id' => $annee->id,
                'nom' => $annee->nom,
            ],
            'periodes' => $periodes,
            'active_periode_id' => $activePeriode?->id,
            'academique' => $donneesEleve,
            'statistiques_classe' => $donneesClasse['statistiques_classe'] ?? [],
            'emploi_du_temps' => $emploiDuTemps,
            'discipline' => $disciplineData,
            'finances' => $financesData,
            'verrou_solvabilite' => [
                'est_bloque' => $isBloque,
                'verrou_actif' => $verrouActif,
                'solde_restant' => $soldeRestant,
                'message' => $messageVerrou,
            ],
        ];
    }

    /**
     * Télécharge le bulletin officiel en PDF en appliquant le verrou de solvabilité (Gesco 11.5 & 16.3).
     *
     * @param Inscription $inscription
     * @param int|null $periodeId
     * @param User $user
     * @return Response
     * @throws ValidationException
     */
    public function telechargerBulletin(Inscription $inscription, ?int $periodeId, User $user): Response
    {
        $classe = $inscription->classe()->with(['niveau.cycle.section.etablissement'])->firstOrFail();
        $etablissement = $classe->niveau->cycle->section->etablissement;

        // Contrôle du Verrou de Solvabilité
        if ($etablissement->verrou_solvabilite_actif) {
            $compte = $inscription->compteFinancier;
            if ($compte && $compte->solde_restant > 0) {
                $eleve = $inscription->eleve;
                abort(403, "Accès restreint. Veuillez régulariser le solde financier de {$eleve->nom} {$eleve->prenom} auprès de l'intendance pour débloquer le bulletin.");
            }
        }

        $periode = $periodeId ? Periode::find($periodeId) : null;
        $annee = $inscription->anneeScolaire;

        $modeBulletin = $etablissement->mode_bulletin_defaut ?? 'sans_competences';

        $donneesClasse = $periode
            ? $this->bulletinService->calculerTrimestre($classe, $periode, null, $modeBulletin)
            : $this->bulletinService->calculerAnnuel($classe, $annee, $modeBulletin);

        $donneesEleve = collect($donneesClasse['eleves'])->firstWhere('inscription_id', $inscription->id);

        if (! $donneesEleve) {
            abort(404, "Données de bulletin introuvables.");
        }

        $vueBlade = $periode ? 'bulletins.bulletin_trimestriel' : 'bulletins.bulletin_annuel';

        $viewData = [
            'classe' => $classe,
            'annee_scolaire' => $annee,
            'periode' => $periode,
            'eleve' => $donneesEleve,
            'stats_classe' => $donneesClasse['statistiques_classe'] ?? ($donneesClasse['stats_classe'] ?? []),
            'statistiques_classe' => $donneesClasse['statistiques_classe'] ?? ($donneesClasse['stats_classe'] ?? []),
            'mode_bulletin' => $modeBulletin,
            'dateGeneration' => now()->format('d/m/Y H:i'),
        ];

        $pdf = Pdf::loadView($vueBlade, $viewData)->setPaper('a4', 'portrait');

        $nomFichier = 'Bulletin_' . $inscription->eleve->matricule . '_' . date('Ymd') . '.pdf';

        return $pdf->stream($nomFichier);
    }
}
