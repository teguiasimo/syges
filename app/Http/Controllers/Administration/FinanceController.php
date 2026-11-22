<?php

namespace App\Http\Controllers\Administration;

use App\Concerns\ScopesEtablissement;
use App\Http\Controllers\Controller;
use App\Models\AnneeScolaire;
use App\Models\BourseRemise;
use App\Models\Caisse;
use App\Models\CategorieBourseRemise;
use App\Models\CategorieFrais;
use App\Models\CategorieRecetteDiverse;
use App\Models\Classe;
use App\Models\ClotureCaisse;
use App\Models\CompteFinancierEleve;
use App\Models\DispenseFinanciere;
use App\Models\EcheanceTranche;
use App\Models\Eleve;
use App\Models\EleveBourseRemise;
use App\Models\Etablissement;
use App\Models\FraisScolaire;
use App\Models\Inscription;
use App\Models\ModeleEcheance;
use App\Models\Niveau;
use App\Models\PenaliteConfiguration;
use App\Models\PenaliteEleve;
use App\Models\Personnel;
use App\Models\ReglementDivers;
use App\Models\SouscriptionFraisOptionnel;
use App\Models\TypeFrais;
use App\Models\VentilationVersement;
use App\Models\VersementCaisse;
use App\Scopes\EtablissementScope;
use App\Services\FinanceService;
use App\Services\QrCodeService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use InvalidArgumentException;

/**
 * Contrôleur principal pour le Module 11 : Gestion de la Caisse & Paiements (Gesco V1.1 & Aurore).
 *
 * Coordonne les opérations financières de l'établissement :
 * - Guichet d'encaissement et émission des reçus officiels infalsifiables.
 * - Suivi des comptes courants scolaires des élèves et ardoises financières.
 * - Paramétrage de la nomenclature des frais et de la matrice tarifaire.
 * - Journal quotidien de caisse et clôture journalière des comptes.
 * - Activation/désactivation du verrou de solvabilité sur les bulletins.
 * - Caisses multiples et encaissements hors scolarité (Aurore §3.20, §4.2).
 * - Bourses d'études et remises automatiques (Aurore §3.19, §3.21, §3.25).
 * - Échéanciers calendaires et pénalités de retard (Aurore §3.17, §3.24).
 */
class FinanceController extends Controller implements HasMiddleware
{
    use ScopesEtablissement;

    /**
     * Définition de la matrice des middlewares d'autorisation pour le module Finances & Caisse.
     *
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:finances.view|finances.manage', only: [
                'index',
                'scolarites',
                'imprimerRecu',
                'imprimerRecuDivers',
                'dossierFinancierEleve',
                'imprimerFicheCompte',
            ]),
            new Middleware('can:finances.etats|finances.manage', only: [
                'etats',
                'imprimerJournalDetaille',
                'imprimerJournalSynthetique',
                'imprimerListeInsolvables',
                'imprimerListeEnRegle',
            ]),
            new Middleware('can:versements.create|finances.manage', only: [
                'storeVersement',
            ]),
            new Middleware('can:versements.edit|finances.manage', only: [
                'updateVersement',
            ]),
            new Middleware('can:recettes_diverses.create|finances.manage', only: [
                'storeReglementDivers',
                'storeCategorieRecette',
                'destroyCategorieRecette',
            ]),
            new Middleware('can:versements.cancel|finances.annuler|finances.manage', only: [
                'annulerVersement',
                'annulerVersementsLot',
                'annulerReglementDivers',
            ]),
            new Middleware('can:caisses.cloture|finances.cloture|finances.manage', only: ['cloturerJournee']),
            new Middleware('can:caisses.create|caisses.edit|finances.manage', only: ['storeCaisse', 'updateCaisse', 'destroyCaisse']),
            new Middleware('can:frais.configure|finances.manage', only: [
                'storeCategorieFrais',
                'updateCategorieFrais',
                'destroyCategorieFrais',
                'storeTypeFrais',
                'updateTypeFrais',
                'destroyTypeFrais',
                'storeTarif',
                'affecterFraisAuxNiveaux',
                'updateTarif',
                'destroyTarif',
            ]),
            new Middleware('can:echeances.manage|finances.manage', only: [
                'storeModeleEcheance',
                'destroyModeleEcheance',
            ]),
            new Middleware('can:bourses.manage|finances.manage', only: [
                'storeBourseRemise',
                'updateBourseRemise',
                'destroyBourseRemise',
                'storeCategorieBourse',
                'destroyCategorieBourse',
            ]),
            new Middleware('can:bourses.attribuer|bourses.manage|finances.manage', only: [
                'attribuerBourseRemise',
                'retirerBourseRemise',
            ]),
            new Middleware('can:dispenses_financieres.manage|finances.manage', only: [
                'storeDispenseFinanciere',
                'destroyDispenseFinanciere',
            ]),
            new Middleware('can:options_financieres.manage|finances.manage', only: [
                'storeSouscriptionOptionnelle',
                'storeSouscriptionOptionnelleClasse',
                'destroySouscriptionOptionnelle',
                'ajusterCompte',
            ]),
            new Middleware('can:penalites.manage|finances.manage', only: [
                'storePenaliteConfiguration',
                'appliquerPenalitesRetard',
                'annulerPenaliteEleve',
            ]),
            new Middleware('can:finances.verrou_solvabilite|finances.manage', only: [
                'toggleVerrouSolvabilite',
            ]),
        ];
    }

    /**
     * Charge les catégories personnalisables de recettes diverses et de bourses/remises
     * applicables à l'établissement (catégories globales + catégories propres à l'établissement).
     *
     * @param  int  $etablissementId  Identifiant de l'établissement actif
     * @return array{
     *     recettes: Collection<int, CategorieRecetteDiverse>,
     *     bourses: Collection<int, CategorieBourseRemise>
     * }
     */
    private function chargerCategoriesPersonnalisables(int $etablissementId): array
    {
        $recettes = CategorieRecetteDiverse::where('is_active', true)
            ->where(function ($q) use ($etablissementId) {
                $q->whereNull('etablissement_id')
                    ->orWhere('etablissement_id', $etablissementId);
            })
            ->orderByRaw('etablissement_id IS NULL DESC')
            ->orderBy('nom')
            ->get();

        $bourses = CategorieBourseRemise::where('is_active', true)
            ->where(function ($q) use ($etablissementId) {
                $q->whereNull('etablissement_id')
                    ->orWhere('etablissement_id', $etablissementId);
            })
            ->orderByRaw("CASE WHEN nom LIKE 'Paiement en avance%' THEN 0 ELSE 1 END")
            ->orderByRaw('etablissement_id IS NULL DESC')
            ->orderBy('nom')
            ->get();

        return [
            'recettes' => $recettes,
            'bourses' => $bourses,
        ];
    }

    /**
     * Construit le tableau standardisé des permissions financières de l'utilisateur courant.
     *
     * @param  Request  $request  Requête HTTP entrante
     * @return array<string, bool>
     */
    private function buildPermissionsPayload(Request $request): array
    {
        $user = $request->user();
        $isSuper = (bool) $user?->hasRole('super-admin');
        $canManageAll = $isSuper || (bool) $user?->can('finances.manage');

        return [
            'view' => $canManageAll || (bool) $user?->can('finances.view'),
            'manage' => $canManageAll,
            'etats' => $canManageAll || (bool) $user?->can('finances.etats'),
            'frais_configure' => $canManageAll || (bool) $user?->can('frais.configure'),
            'versements_create' => $canManageAll || (bool) $user?->can('versements.create'),
            'versements_edit' => $canManageAll || (bool) $user?->can('versements.edit'),
            'recettes_diverses_create' => $canManageAll || (bool) $user?->can('recettes_diverses.create'),
            'versements_cancel' => $canManageAll || (bool) $user?->can('versements.cancel') || (bool) $user?->can('finances.annuler'),
            'caisses_manage' => $canManageAll || (bool) $user?->can('caisses.create') || (bool) $user?->can('caisses.edit'),
            'caisses_cloture' => $canManageAll || (bool) $user?->can('caisses.cloture') || (bool) $user?->can('finances.cloture'),
            'bourses_manage' => $canManageAll || (bool) $user?->can('bourses.manage'),
            'bourses_attribuer' => $canManageAll || (bool) $user?->can('bourses.attribuer') || (bool) $user?->can('bourses.manage'),
            'dispenses_manage' => $canManageAll || (bool) $user?->can('dispenses_financieres.manage'),
            'options_manage' => $canManageAll || (bool) $user?->can('options_financieres.manage'),
            'verrou_solvabilite' => $canManageAll || (bool) $user?->can('finances.verrou_solvabilite'),
            'echeances_manage' => $canManageAll || (bool) $user?->can('echeances.manage'),
            'penalites_manage' => $canManageAll || (bool) $user?->can('penalites.manage'),
        ];
    }

    /**
     * Affiche l'espace de configuration financière, journal et caisse.
     */
    public function index(Request $request, FinanceService $financeService): InertiaResponse
    {
        abort_if(! $request->user()?->can('finances.view|finances.manage') && ! $request->user()?->hasRole('super-admin'), 403);

        $currentAnnee = AnneeScolaire::where('is_current', true)->first()
            ?? AnneeScolaire::latest()->first();
        $anneeId = $currentAnnee?->id;

        // Chargement de l'arborescence complète (Section > Cycle > Niveau > Classe) pour le générateur d'états filtrables
        $context = $this->resolveEtablissementContext($request, [
            'institution',
            'sections' => fn ($q) => $q->orderBy('nom'),
            'sections.cycles' => fn ($q) => $q->orderBy('ordre')->orderBy('nom'),
            'sections.cycles.niveaux' => fn ($q) => $q->orderBy('ordre')->orderBy('nom'),
            'sections.cycles.niveaux.classes' => fn ($q) => $q->where('is_active', true)->orderBy('nom'),
        ]);
        $etablissements = $context['etablissements'];
        $currentEtablissement = $context['selectedEtablissement'];
        $etablissementId = $currentEtablissement?->id ?? 0;

        // Synchronisation automatique des comptes financiers des élèves inscrits non encore initialisés
        if ($etablissementId && $anneeId) {
            $financeService->synchroniserComptesEtablissement((int) $etablissementId, (int) $anneeId);
        }

        // 1. Liste des versements avec pagination et filtres
        $versementsQuery = VersementCaisse::with([
            'compteFinancier.eleve',
            'inscription.classe',
            'ventilations',
            'caissier',
            'caisse',
        ])
            ->where('etablissement_id', $etablissementId)
            ->latest('date_versement');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $versementsQuery->where(function ($q) use ($search) {
                $q->where('reference_recu', 'LIKE', "%{$search}%")
                    ->orWhere('payeur_nom', 'LIKE', "%{$search}%")
                    ->orWhereHas('compteFinancier.eleve', function ($eq) use ($search) {
                        $eq->where('nom', 'LIKE', "%{$search}%")
                            ->orWhere('prenom', 'LIKE', "%{$search}%")
                            ->orWhere('matricule', 'LIKE', "%{$search}%");
                    });
            });
        }

        if ($request->filled('mode_paiement')) {
            $versementsQuery->where('mode_paiement', $request->input('mode_paiement'));
        }

        if ($request->filled('statut')) {
            $versementsQuery->where('statut', $request->input('statut'));
        }

        $versements = $versementsQuery->take(100)->get();

        // 2. Liste des comptes financiers des élèves
        $comptesQuery = CompteFinancierEleve::with([
            'eleve',
            'inscription.classe.niveau',
            'boursesRemises.bourseRemise',
            'souscriptionsOptionnelles.fraisScolaire.typeFrais',
            'penalites',
            'versements',
        ])
            ->where('etablissement_id', $etablissementId)
            ->where('annee_scolaire_id', $anneeId);

        if ($request->filled('classe_id')) {
            $classeId = $request->integer('classe_id');
            $comptesQuery->whereHas('inscription', fn ($q) => $q->where('classe_id', $classeId));
        }

        if ($request->filled('statut_financier')) {
            $comptesQuery->where('statut_financier', $request->input('statut_financier'));
        }

        if ($request->filled('search_compte')) {
            $sc = $request->input('search_compte');
            $comptesQuery->whereHas('eleve', function ($q) use ($sc) {
                $q->where('nom', 'LIKE', "%{$sc}%")
                    ->orWhere('prenom', 'LIKE', "%{$sc}%")
                    ->orWhere('matricule', 'LIKE', "%{$sc}%");
            });
        }

        $comptes = $comptesQuery->get();

        // 3. Catégories de frais et Catalogue des frais configurés pour l'établissement
        $categoriesFrais = CategorieFrais::obtenirOuInitialiserPourEtablissement((int) $etablissementId);

        $typesFrais = TypeFrais::with([
            'categorieFrais',
            'fraisScolaires' => fn ($q) => $q
                ->where('annee_scolaire_id', $anneeId)
                ->with('niveau:id,nom,ordre'),
        ])
            ->where('etablissement_id', $etablissementId)
            ->orderBy('ordre_priorite')
            ->orderByDesc('obligatoire')
            ->orderBy('libelle')
            ->get();

        // 4. Matrice tarifaire par niveau (triée par niveau puis par priorité effective et numéro de tranche)
        $tarifs = FraisScolaire::with(['niveau.cycle.section', 'typeFrais.categorieFrais'])
            ->where('etablissement_id', $etablissementId)
            ->where('annee_scolaire_id', $anneeId)
            ->get()
            ->sortBy([
                ['niveau_id', 'asc'],
                fn (FraisScolaire $a, FraisScolaire $b) => (int) ($a->ordre_priorite ?? ($a->typeFrais?->ordre_priorite ?? 10))
                    <=> (int) ($b->ordre_priorite ?? ($b->typeFrais?->ordre_priorite ?? 10)),
                ['tranche_numero', 'asc'],
                ['id', 'asc'],
            ])
            ->values();

        // 5. Caisses de l'établissement (Aurore §3.20)
        $caisses = Caisse::with('responsable')
            ->where('etablissement_id', $etablissementId)
            ->orderByDesc('is_principale')
            ->orderBy('nom')
            ->get();

        // 6. Règlements divers hors scolarité (Aurore §4.2)
        $reglementsDivers = ReglementDivers::with(['caisse', 'caissier'])
            ->where('etablissement_id', $etablissementId)
            ->latest('date_reglement')
            ->take(100)
            ->get();

        // 7. Référentiel des bourses et remises (Aurore §3.19, §3.21, §3.25)
        $boursesRemises = BourseRemise::with(['niveau', 'typeFrais'])
            ->where('etablissement_id', $etablissementId)
            ->orderBy('type')
            ->orderBy('nom')
            ->get();

        // 8. Modèles d'échéances calendaires (Aurore §3.24)
        $modelesEcheances = ModeleEcheance::with('tranches')
            ->where('etablissement_id', $etablissementId)
            ->where('annee_scolaire_id', $anneeId)
            ->orderByDesc('is_default')
            ->get();

        // 9. Configurations des pénalités de retard (Aurore §3.17)
        $penalitesConfigurations = PenaliteConfiguration::where('etablissement_id', $etablissementId)->get();

        // 10. Journal du jour et état de la caisse
        $today = Carbon::today()->toDateString();
        $versementsDuJour = VersementCaisse::where('etablissement_id', $etablissementId)
            ->whereDate('date_versement', $today)
            ->where('statut', 'valide')
            ->get();

        $reglementsDiversDuJour = ReglementDivers::where('etablissement_id', $etablissementId)
            ->whereDate('date_reglement', $today)
            ->where('statut', 'valide')
            ->get();

        $totalGeneralDuJour = (float) $versementsDuJour->sum('montant_total') + (float) $reglementsDiversDuJour->sum('montant');

        $journalDuJour = [
            'date' => $today,
            'total_especes' => (float) $versementsDuJour->where('mode_paiement', 'especes')->sum('montant_total')
                + (float) $reglementsDiversDuJour->where('mode_paiement', 'especes')->sum('montant'),
            'total_cheques' => (float) $versementsDuJour->where('mode_paiement', 'cheque')->sum('montant_total')
                + (float) $reglementsDiversDuJour->where('mode_paiement', 'cheque')->sum('montant'),
            'total_virements' => (float) $versementsDuJour->where('mode_paiement', 'virement')->sum('montant_total')
                + (float) $reglementsDiversDuJour->where('mode_paiement', 'virement')->sum('montant'),
            'total_mobile_money' => (float) $versementsDuJour->where('mode_paiement', 'mobile_money_manuel')->sum('montant_total')
                + (float) $reglementsDiversDuJour->where('mode_paiement', 'mobile_money_manuel')->sum('montant'),
            'total_general' => $totalGeneralDuJour,
            'nombre_transactions' => $versementsDuJour->count() + $reglementsDiversDuJour->count(),
            'est_cloture' => ClotureCaisse::where('etablissement_id', $etablissementId)
                ->where('date_cloture', $today)
                ->where('statut', 'cloturee')
                ->exists(),
        ];

        // 11. Historique des clôtures passées
        $historiqueClotures = ClotureCaisse::with(['caissier', 'verificateur'])
            ->where('etablissement_id', $etablissementId)
            ->latest('date_cloture')
            ->take(30)
            ->get();

        // 12. Données d'appui pour les formulaires
        $classes = Classe::where('etablissement_id', $etablissementId)
            ->where('is_active', true)
            ->orderBy('nom')
            ->get();

        $niveaux = Niveau::with('cycle.section')
            ->where('etablissement_id', $etablissementId)
            ->orderBy('ordre')
            ->get();

        $caissiers = Personnel::where('etablissement_id', $etablissementId)
            ->actif()
            ->orderBy('nom')
            ->get();

        // 13. Frais scolaires optionnels paramétrés pour l'année en cours (bus, cantine, activités, frais spécifiques par série/classe...)
        $fraisOptionnels = FraisScolaire::with(['typeFrais.categorieFrais', 'niveau'])
            ->where('etablissement_id', $etablissementId)
            ->where('annee_scolaire_id', $anneeId)
            ->where(function ($q) {
                $q->where('frais_scolaires.obligatoire', false)
                    ->orWhere(function ($q2) {
                        $q2->whereNull('frais_scolaires.obligatoire')
                            ->whereHas('typeFrais', fn ($q3) => $q3->where('obligatoire', false));
                    });
            })
            ->get();

        // 14. Catégories personnalisables (Recettes diverses & Bourses/Remises)
        $categories = $this->chargerCategoriesPersonnalisables((int) $etablissementId);

        return Inertia::render('administration/finances/index', [
            'versements' => $versements,
            'comptes' => $comptes,
            'categoriesFrais' => $categoriesFrais,
            'typesFrais' => $typesFrais,
            'tarifs' => $tarifs,
            'fraisOptionnels' => $fraisOptionnels,
            'caisses' => $caisses,
            'reglementsDivers' => $reglementsDivers,
            'boursesRemises' => $boursesRemises,
            'categoriesRecettes' => $categories['recettes'],
            'categoriesBourses' => $categories['bourses'],
            'modelesEcheances' => $modelesEcheances,
            'penalitesConfigurations' => $penalitesConfigurations,
            'journalDuJour' => $journalDuJour,
            'historiqueClotures' => $historiqueClotures,
            'classes' => $classes,
            'niveaux' => $niveaux,
            'caissiers' => $caissiers,
            'etablissements' => $etablissements,
            'currentEtablissement' => $currentEtablissement,
            // Exposer le sélecteur d'établissement aux profils autorisés en navigation globale (Super-Admin & Secrétaire)
            'isSuperAdmin' => (bool) ($context['canAccessAll'] ?? $context['isSuperAdmin']),
            'anneesScolaires' => AnneeScolaire::orderBy('code', 'desc')->get(),
            'currentAnnee' => $currentAnnee,
            'filters' => $request->only(['search', 'mode_paiement', 'statut', 'classe_id', 'statut_financier', 'tab']),
            'can' => $this->buildPermissionsPayload($request),
        ]);
    }

    /**
     * Affiche la page dédiée « Guichet Scolarité & Dossiers Élèves ».
     *
     * Permet à l'économe ou au dirigeant de filtrer en entonnoir :
     * Établissement → Section → Cycle → Niveau → Série (optionnel) → Classe,
     * puis d'afficher tous les élèves de la sélection et d'ouvrir le Dossier Financier Tout-en-Un
     * (encaissement rapide, échéancier, bourses/remises, options Bus/Cantine et reçus).
     *
     * @param  Request  $request  Requête HTTP entrante
     * @param  FinanceService  $financeService  Service métier financier
     */
    public function scolarites(Request $request, FinanceService $financeService): InertiaResponse
    {
        abort_if(! $request->user()?->can('finances.view|finances.manage') && ! $request->user()?->hasRole('super-admin'), 403);

        $defaultAnnee = AnneeScolaire::where('is_current', true)->first()
            ?? AnneeScolaire::latest()->first();
        $anneeId = $request->integer('annee_scolaire_id') ?: ($defaultAnnee?->id ?? 0);
        $currentAnnee = $anneeId ? AnneeScolaire::find($anneeId) : $defaultAnnee;

        // Chargement de l'arborescence complète (Section > Cycle > Niveau > Classe) selon les droits multi-établissement
        $context = $this->resolveEtablissementContext($request, [
            'institution',
            'sections' => fn ($q) => $q->orderBy('nom'),
            'sections.cycles' => fn ($q) => $q->orderBy('ordre')->orderBy('nom'),
            'sections.cycles.niveaux' => fn ($q) => $q->orderBy('ordre')->orderBy('nom'),
            'sections.cycles.niveaux.classes' => fn ($q) => $q->where('is_active', true)->orderBy('nom'),
        ]);

        $etablissements = $context['etablissements'];
        $currentEtablissement = $context['selectedEtablissement'];
        $etablissementId = $currentEtablissement?->id ?? 0;

        // Synchronisation automatique pour garantir que 100 % des élèves inscrits apparaissent au guichet
        if ($etablissementId && $anneeId) {
            $financeService->synchroniserComptesEtablissement((int) $etablissementId, (int) $anneeId);
        }

        $categoriesFrais = CategorieFrais::obtenirOuInitialiserPourEtablissement((int) $etablissementId);

        $comptes = CompteFinancierEleve::with([
            'eleve',
            'inscription.classe.niveau.cycle.section',
            'boursesRemises.bourseRemise',
            'dispensesFinancieres.fraisScolaire.typeFrais',
            'souscriptionsOptionnelles.fraisScolaire.typeFrais.categorieFrais',
            'penalites',
            'versements' => fn ($q) => $q->where('statut', 'valide')->with('ventilations'),
        ])
            ->where('etablissement_id', $etablissementId)
            ->where('annee_scolaire_id', $anneeId)
            ->orderBy(
                Eleve::select('nom')->whereColumn('eleves.id', 'comptes_financiers_eleves.eleve_id')
            )
            ->get();

        $typesFrais = TypeFrais::with('categorieFrais')
            ->where('etablissement_id', $etablissementId)
            ->orderBy('ordre_priorite')
            ->orderByDesc('obligatoire')
            ->orderBy('libelle')
            ->get();

        $tarifs = FraisScolaire::with(['niveau', 'typeFrais.categorieFrais'])
            ->where('etablissement_id', $etablissementId)
            ->where('annee_scolaire_id', $anneeId)
            ->get()
            ->sortBy([
                ['niveau_id', 'asc'],
                fn (FraisScolaire $a, FraisScolaire $b) => (int) ($a->ordre_priorite ?? ($a->typeFrais?->ordre_priorite ?? 10))
                    <=> (int) ($b->ordre_priorite ?? ($b->typeFrais?->ordre_priorite ?? 10)),
                ['tranche_numero', 'asc'],
                ['id', 'asc'],
            ])
            ->values();

        $fraisOptionnels = FraisScolaire::with(['typeFrais.categorieFrais', 'niveau'])
            ->where('etablissement_id', $etablissementId)
            ->where('annee_scolaire_id', $anneeId)
            ->where(function ($q) {
                $q->where('frais_scolaires.obligatoire', false)
                    ->orWhere(function ($q2) {
                        $q2->whereNull('frais_scolaires.obligatoire')
                            ->whereHas('typeFrais', fn ($q3) => $q3->where('obligatoire', false));
                    });
            })
            ->get();

        $boursesRemises = BourseRemise::with(['niveau', 'typeFrais'])
            ->where('etablissement_id', $etablissementId)
            ->where('is_active', true)
            ->orderBy('type')
            ->orderBy('nom')
            ->get();

        $categories = $this->chargerCategoriesPersonnalisables((int) $etablissementId);

        $today = Carbon::today()->toDateString();
        $estCloture = ClotureCaisse::where('etablissement_id', $etablissementId)
            ->whereDate('date_cloture', $today)
            ->where('statut', 'cloturee')
            ->exists();

        return Inertia::render('administration/finances/scolarites', [
            'comptes' => $comptes,
            'categoriesFrais' => $categoriesFrais,
            'typesFrais' => $typesFrais,
            'tarifs' => $tarifs,
            'fraisOptionnels' => $fraisOptionnels,
            'boursesRemises' => $boursesRemises,
            'categoriesBourses' => $categories['bourses'],
            'estCaisseCloturee' => $estCloture,
            'etablissements' => $etablissements,
            'currentEtablissement' => $currentEtablissement,
            // Exposer le sélecteur d'établissement aux profils autorisés en navigation globale (Super-Admin & Secrétaire)
            'isSuperAdmin' => (bool) ($context['canAccessAll'] ?? $context['isSuperAdmin']),
            'anneesScolaires' => AnneeScolaire::orderBy('code', 'desc')->get(),
            'currentAnnee' => $currentAnnee,
            'filters' => $request->only(['etablissement_id', 'annee_scolaire_id', 'classe_id', 'compte_id']),
            'can' => $this->buildPermissionsPayload($request),
        ]);
    }

    /**
     * Affiche la page dédiée du Centre des États & Rapports Financiers (Aurore §3.22, §4.3).
     *
     * Regroupe l'ensemble des états et documents financiers officiels (Journaux de caisse,
     * liste des élèves insolvables, liste des élèves en règle, balance de trésorerie) avec filtres
     * multicritères par période (date_debut / date_fin) et hiérarchie scolaire complète (Établissement,
     * Section, Cycle, Niveau, Classe) dans un espace spacieux, ergonomique et aéré.
     *
     * @param  Request  $request  Requête HTTP entrante
     * @return InertiaResponse Vue Inertia du centre des états financiers
     */
    public function etats(Request $request): InertiaResponse
    {
        abort_if(! $request->user()?->can('finances.etats') && ! $request->user()?->can('finances.view') && ! $request->user()?->can('finances.manage') && ! $request->user()?->hasRole('super-admin'), 403);

        $context = $this->resolveEtablissementContext($request, ['sections.cycles.niveaux.classes']);
        $rawEtabId = $request->input('etablissement_id');
        $isSuperAdmin = (bool) $context['isSuperAdmin'];
        $canAccessAll = (bool) ($context['canAccessAll'] ?? $isSuperAdmin);
        $isGlobal = ($rawEtabId === 'all' || empty($rawEtabId) && $canAccessAll) && $canAccessAll;

        // Cloisonnement multi-tenant : rejet strict si un utilisateur local tente de consulter un autre établissement
        if (
            $request->filled('etablissement_id')
            && $rawEtabId !== 'all'
            && ! $canAccessAll
            && $request->integer('etablissement_id') !== (int) $context['etablissementId']
        ) {
            abort(403, "Accès refusé : vous n'êtes pas autorisé à consulter les états financiers d'un autre établissement.");
        }

        $etablissementId = $isGlobal ? null : (int) $context['etablissementId'];
        $currentEtablissement = $isGlobal ? null : $context['selectedEtablissement'];
        $etablissements = $context['etablissements'];

        $anneeId = $request->filled('annee_scolaire_id')
            ? $request->integer('annee_scolaire_id')
            : (AnneeScolaire::where('is_current', true)->value('id') ?? AnneeScolaire::latest()->value('id'));

        $currentAnnee = AnneeScolaire::find($anneeId);

        $accessibleEtabIds = $etablissements->pluck('id')->map(fn ($id) => (int) $id)->all();

        // Caisses disponibles pour filtrer les encaissements
        $caissesQuery = Caisse::orderByDesc('is_principale')->orderBy('nom');
        if ($etablissementId) {
            $caissesQuery->where('etablissement_id', $etablissementId);
        } elseif (! empty($accessibleEtabIds)) {
            $caissesQuery->whereIn('etablissement_id', $accessibleEtabIds);
        }
        $caisses = $caissesQuery->get();

        // Élèves pour la sélection rapide du Relevé Individuel
        $elevesQuery = Inscription::with(['eleve', 'classe'])
            ->where('annee_scolaire_id', $anneeId);
        if ($etablissementId) {
            $elevesQuery->where('etablissement_id', $etablissementId);
        } elseif (! empty($accessibleEtabIds)) {
            $elevesQuery->whereIn('etablissement_id', $accessibleEtabIds);
        }
        $elevesList = $elevesQuery->get()->map(function ($insc) {
            return [
                'id' => $insc->eleve_id,
                'matricule' => $insc->eleve?->matricule ?? 'N/A',
                'nom' => $insc->eleve?->nom ?? '',
                'prenom' => $insc->eleve?->prenom ?? '',
                'nom_complet' => trim(($insc->eleve?->nom ?? '').' '.($insc->eleve?->prenom ?? '')),
                'classe_id' => $insc->classe_id,
                'classe' => $insc->classe?->nom ?? 'N/A',
                'etablissement_id' => $insc->etablissement_id,
            ];
        })->sortBy('nom_complet')->values();

        // Catégories et types de frais disponibles pour les filtres avancés des états
        $categoriesQuery = CategorieFrais::withoutGlobalScopes()->where('is_active', true)->orderBy('nom')->orderBy('id');
        if ($etablissementId) {
            $categoriesQuery->where('etablissement_id', $etablissementId);
        } elseif (! empty($accessibleEtabIds)) {
            $categoriesQuery->whereIn('etablissement_id', $accessibleEtabIds);
        }
        $categoriesFrais = $categoriesQuery->get(['id', 'etablissement_id', 'nom', 'description']);

        $typesFraisQuery = TypeFrais::withoutGlobalScopes()
            ->with('categorieFrais:id,nom')
            ->where('is_active', true)
            ->orderBy('ordre_priorite')
            ->orderBy('libelle')
            ->orderBy('id');
        if ($etablissementId) {
            $typesFraisQuery->where('etablissement_id', $etablissementId);
        } elseif (! empty($accessibleEtabIds)) {
            $typesFraisQuery->whereIn('etablissement_id', $accessibleEtabIds);
        }
        $typesFrais = $typesFraisQuery->get(['id', 'etablissement_id', 'categorie_frais_id', 'code', 'libelle', 'obligatoire', 'ordre_priorite']);

        // En mode multi-campus global, chaque établissement possède ses propres lignes homonymes
        // (« Scolarité », « Frais d'Examen », etc.) : on dédoublonne par libellé normalisé côté UI,
        // puis FinanceService résout automatiquement les homonymes sur tous les campus lors du tirage PDF.
        if ($isGlobal) {
            $categoriesFrais = $categoriesFrais
                ->unique(fn (CategorieFrais $cat): string => mb_strtolower(trim($cat->nom)))
                ->values();

            $typesFrais = $typesFrais
                ->unique(function (TypeFrais $tf): string {
                    $catNorm = mb_strtolower(trim((string) ($tf->categorieFrais?->nom ?? '')));

                    return $catNorm.'|'.mb_strtolower(trim($tf->libelle));
                })
                ->values();
        }

        return Inertia::render('administration/finances/etats', [
            'etablissements' => $etablissements,
            'currentEtablissement' => $currentEtablissement,
            'isSuperAdmin' => $canAccessAll,
            'isGlobal' => $isGlobal,
            'anneesScolaires' => AnneeScolaire::orderBy('code', 'desc')->get(),
            'currentAnnee' => $currentAnnee,
            'caisses' => $caisses,
            'eleves' => $elevesList,
            'categoriesFrais' => $categoriesFrais,
            'typesFrais' => $typesFrais,
            'filters' => $request->only([
                'etablissement_id',
                'annee_scolaire_id',
                'section_id',
                'cycle_id',
                'niveau_id',
                'classe_id',
                'caisse_id',
                'mode_paiement',
                'date_debut',
                'date_fin',
                'eleve_id',
                'categorie_frais_id',
                'type_frais_id',
                'mode_filtre_frais',
                'type',
                'preset',
            ]),
            'can' => $this->buildPermissionsPayload($request),
        ]);
    }

    /**
     * Enregistre un versement physique effectué à la caisse.
     */
    public function storeVersement(Request $request, FinanceService $financeService): RedirectResponse
    {
        abort_if(! $request->user()?->can('versements.create') && ! $request->user()?->can('finances.manage') && ! $request->user()?->hasRole('super-admin'), 403);

        $validated = $request->validate([
            'compte_financier_id' => 'required|exists:comptes_financiers_eleves,id',
            'montant_total' => 'required|numeric|min:1',
            'date_versement' => 'nullable|date|after_or_equal:2020-01-01|before_or_equal:today',
            'appliquer_remise_anticipee' => 'nullable|boolean',
            'bourse_remise_anticipee_id' => 'nullable|exists:bourses_remises,id',
            'mode_paiement' => 'required|in:especes,cheque,virement,mobile_money_manuel',
            'reference_paiement' => 'nullable|string|max:100',
            'payeur_nom' => 'nullable|string|max:150',
            'payeur_telephone' => 'nullable|string|max:50',
            'personnel_id' => 'nullable|exists:personnels,id',
            'observations' => 'nullable|string|max:500',
            'ventilations' => 'required|array|min:1',
            'ventilations.*.libelle' => 'required|string|max:150',
            'ventilations.*.montant' => 'required|numeric|min:1',
            'ventilations.*.type_frais_id' => 'nullable|exists:types_frais,id',
            'ventilations.*.frais_scolaire_id' => 'nullable|exists:frais_scolaires,id',
        ], [
            'date_versement.before_or_equal' => "La date de versement ne peut pas être postérieure à aujourd'hui.",
            'date_versement.after_or_equal' => 'La date de versement doit être postérieure au 01-01-2020.',
            'date_versement.date' => 'Veuillez saisir une date de versement valide.',
        ]);

        $caissier = null;
        if (! empty($validated['personnel_id'])) {
            $caissier = Personnel::find($validated['personnel_id']);
        }

        try {
            $versement = $financeService->enregistrerVersement($validated, $caissier, $request->user());

            return back()->with([
                'success' => 'Versement de '.number_format($versement->montant_total, 0)." FCFA enregistré avec succès. Reçu N° {$versement->reference_recu}.",
                'dernier_recu_id' => $versement->id,
            ]);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Modifie un versement de scolarité valide existant (montant, date, mode de paiement, payeur, référence)
     * et met à jour atomiquement le dossier financier de l'élève ainsi que tous les états financiers.
     *
     * @param  Request  $request  Requête HTTP contenant les champs modifiés du versement
     * @param  VersementCaisse  $versement  Versement ciblé
     * @param  FinanceService  $financeService  Service métier financier
     * @return RedirectResponse Redirection avec notification de succès ou message d'erreur métier
     */
    public function updateVersement(Request $request, VersementCaisse $versement, FinanceService $financeService): RedirectResponse
    {
        abort_if(
            ! $request->user()?->can('versements.edit')
            && ! $request->user()?->can('finances.manage')
            && ! $request->user()?->hasRole('super-admin'),
            403
        );

        // Vérification du périmètre multi-établissement
        $context = $this->resolveEtablissementContext($request);
        $canAccessAll = (bool) ($context['canAccessAll'] ?? $context['isSuperAdmin']);
        if (! $canAccessAll && (int) $versement->etablissement_id !== (int) $context['etablissementId']) {
            abort(403, "Vous n'êtes pas autorisé à modifier un versement appartenant à un autre établissement.");
        }

        $validated = $request->validate([
            'montant_total' => 'required|numeric|min:1',
            'date_versement' => 'nullable|date|after_or_equal:2020-01-01|before_or_equal:today',
            'mode_paiement' => 'required|in:especes,cheque,virement,mobile_money_manuel',
            'reference_paiement' => 'nullable|string|max:100',
            'payeur_nom' => 'nullable|string|max:150',
            'payeur_telephone' => 'nullable|string|max:50',
            'observations' => 'nullable|string|max:500',
            'mode_allocation' => 'nullable|in:auto,manuel',
            'ventilations' => 'nullable|array',
            'ventilations.*.libelle' => 'required_with:ventilations|string|max:150',
            'ventilations.*.montant' => 'required_with:ventilations|numeric|min:1',
            'ventilations.*.type_frais_id' => 'nullable|exists:types_frais,id',
            'ventilations.*.frais_scolaire_id' => 'nullable|exists:frais_scolaires,id',
        ], [
            'montant_total.required' => 'Veuillez saisir le nouveau montant du versement.',
            'montant_total.min' => 'Le montant du versement doit être supérieur à 0 FCFA.',
            'date_versement.before_or_equal' => "La date de versement ne peut pas être postérieure à aujourd'hui.",
            'date_versement.after_or_equal' => 'La date de versement doit être postérieure au 01-01-2020.',
            'date_versement.date' => 'Veuillez saisir une date de versement valide.',
        ]);

        try {
            $versementMisAJour = $financeService->modifierVersement($versement, $validated, $request->user());
            $montantFormate = number_format((float) $versementMisAJour->montant_total, 0, ',', ' ');

            return back()->with(
                'success',
                "Le versement {$versementMisAJour->reference_recu} a été mis à jour à {$montantFormate} FCFA. Le dossier de l'élève et les états financiers ont été recalculés."
            );
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage())->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Génère et télécharge le reçu officiel PDF d'un versement avec son QR code de sécurité
     * (format A4 portrait par défaut avec double exemplaire parent/souche, ou ticket thermique 80mm avec `?format=ticket`).
     *
     * @param  Request  $request  Requête HTTP portant le paramètre optionnel `format`
     * @param  VersementCaisse  $versement  Versement concerné
     * @param  QrCodeService  $qrCodeService  Service de génération du QR code
     * @return Response Document PDF généré
     */
    public function imprimerRecu(Request $request, VersementCaisse $versement, QrCodeService $qrCodeService): Response
    {
        abort_if(! $request->user()?->can('finances.view|finances.manage') && ! $request->user()?->hasRole('super-admin'), 403);

        $versement->load([
            'ventilations',
            'compteFinancier.eleve',
            'compteFinancier.anneeScolaire',
            'inscription.classe',
            'etablissement',
            'caisse',
            'caissier',
        ]);

        $compte = $versement->compteFinancier;
        $eleve = $compte->eleve;
        $inscription = $versement->inscription;
        $etablissement = $versement->etablissement;

        // Données d'intégrité encodées dans le QR code
        $dateVersement = $versement->date_versement instanceof Carbon
            ? $versement->date_versement->format('d/m/Y H:i')
            : Carbon::parse($versement->date_versement)->format('d/m/Y H:i');

        $qrData = sprintf(
            'SYGES-REC:%s|DATE:%s|ELEVE:%s (MAT:%s)|MONTANT:%s FCFA|MODE:%s|SOLDE:%s FCFA',
            $versement->reference_recu,
            $dateVersement,
            $eleve->nom.' '.($eleve->prenom ?? ''),
            $eleve->matricule,
            number_format($versement->montant_total, 0, ',', ' '),
            strtoupper($versement->mode_paiement),
            number_format($compte->solde_restant, 0, ',', ' ')
        );

        // Choix du gabarit d'impression : Ticket thermique 80mm (format=ticket) ou Reçu A4 portrait (défaut)
        $formatImpression = mb_strtolower($request->string('format', 'a4')->toString());
        if ($formatImpression === 'ticket') {
            $qrCodeBase64 = $qrCodeService->generateBase64Svg($qrData, 80);
            $nbLignes = max(1, $versement->ventilations->count());
            // Hauteur dynamique en points (80mm = 226.77pt de large) pour éviter les sauts de page thermiques
            $hauteurTicketPt = 435
                + ($nbLignes * 18)
                + ($versement->statut === 'annule' ? 48 : 0)
                + ($versement->payeur_nom ? 16 : 0)
                + ($versement->reference_paiement ? 14 : 0);

            $pdf = Pdf::loadView('finances.recu_caisse_ticket', compact('versement', 'compte', 'eleve', 'inscription', 'etablissement', 'qrCodeBase64'))
                ->setPaper([0, 0, 226.77, $hauteurTicketPt]);

            return $pdf->stream("Ticket_{$versement->reference_recu}.pdf");
        }

        $qrCodeBase64 = $qrCodeService->generateBase64Svg($qrData, 90);

        $pdf = Pdf::loadView('finances.recu_caisse', compact('versement', 'compte', 'eleve', 'inscription', 'etablissement', 'qrCodeBase64'))
            ->setPaper('a4', 'portrait');

        return $pdf->stream("Recu_{$versement->reference_recu}.pdf");
    }

    /**
     * Annule un versement avec motif obligatoire et réajustement du compte élève.
     */
    public function annulerVersement(Request $request, VersementCaisse $versement, FinanceService $financeService): RedirectResponse
    {
        abort_if(! $request->user()?->can('versements.cancel') && ! $request->user()?->can('finances.annuler') && ! $request->user()?->can('finances.manage') && ! $request->user()?->hasRole('super-admin'), 403);

        $request->validate([
            'motif_annulation' => 'required|string|min:5|max:500',
        ]);

        try {
            $financeService->annulerVersement($versement, $request->input('motif_annulation'), $request->user());

            return back()->with('success', "Le versement {$versement->reference_recu} a été annulé avec succès. Le solde de l'élève a été réajusté.");
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Annule en une seule opération atomique plusieurs versements sélectionnés (ex: 8 opérations d'un élève)
     * avec un seul motif obligatoire et réajuste le solde des comptes élèves impactés.
     */
    public function annulerVersementsLot(Request $request, FinanceService $financeService): RedirectResponse
    {
        abort_if(! $request->user()?->can('versements.cancel') && ! $request->user()?->can('finances.annuler') && ! $request->user()?->can('finances.manage') && ! $request->user()?->hasRole('super-admin'), 403);

        $validated = $request->validate([
            'versement_ids' => 'required|array|min:1',
            'versement_ids.*' => 'integer|exists:versements_caisse,id',
            'motif_annulation' => 'required|string|min:5|max:500',
        ]);

        $context = $this->resolveEtablissementContext($request);
        $etabRestriction = ($context['canAccessAll'] ?? $context['isSuperAdmin']) ? null : ($context['etablissementId'] ? (int) $context['etablissementId'] : null);

        try {
            $bilan = $financeService->annulerVersementsEnLot(
                $validated['versement_ids'],
                $validated['motif_annulation'],
                $request->user(),
                $etabRestriction
            );

            $montantFormate = number_format($bilan['montant_total_annule'], 0, ',', ' ');

            return back()->with(
                'success',
                "{$bilan['nb_annules']} versement(s) annulé(s) en une seule opération (Total : {$montantFormate} FCFA). Le solde a été recalculé."
            );
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage())->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Clôture la caisse journalière de l'établissement pour la date sélectionnée.
     */
    public function cloturerJournee(Request $request, FinanceService $financeService): RedirectResponse
    {
        abort_if(! $request->user()?->can('caisses.cloture') && ! $request->user()?->can('finances.cloture') && ! $request->user()?->can('finances.manage') && ! $request->user()?->hasRole('super-admin'), 403);

        $validated = $request->validate([
            'etablissement_id' => 'required|exists:etablissements,id',
            'personnel_id' => 'nullable|exists:personnels,id',
            'date_cloture' => 'required|date',
            'observations' => 'nullable|string|max:500',
        ]);

        $financeService->cloturerCaisse(
            $validated['etablissement_id'],
            $validated['personnel_id'] ?? null,
            $validated['date_cloture'],
            $validated['observations'] ?? null,
            $request->user()
        );

        return back()->with('success', "La caisse du {$validated['date_cloture']} a été clôturée avec succès. Les écritures sont désormais verrouillées.");
    }

    /**
     * Ajuste la remise/bourse ou les arriérés antérieurs reportés sur le compte financier d'un élève.
     *
     * Permet au service comptable d'enregistrer manuellement une dette antérieure,
     * d'accorder une réduction financière ou de modifier les observations de l'élève.
     */
    public function ajusterCompte(Request $request, CompteFinancierEleve $compte): RedirectResponse
    {
        $validated = $request->validate([
            'remise_bourse' => 'nullable|numeric|min:0',
            'arrieres_anterieurs' => 'nullable|numeric|min:0',
            'observations' => 'nullable|string|max:500',
        ]);

        if (isset($validated['remise_bourse'])) {
            $compte->remise_bourse = (float) $validated['remise_bourse'];
        }
        if (isset($validated['arrieres_anterieurs'])) {
            $compte->arrieres_anterieurs = (float) $validated['arrieres_anterieurs'];
        }
        if (isset($validated['observations'])) {
            $compte->observations = $validated['observations'];
        }
        $compte->actualiserSolde();

        return back()->with('success', "Le compte financier de l'élève a été ajusté avec succès (Arriérés: ".number_format($compte->arrieres_anterieurs, 0, ',', ' ').' FCFA, Remise: '.number_format($compte->remise_bourse, 0, ',', ' ').' FCFA).');
    }

    /**
     * Crée ou réactive une Catégorie de Frais (Libellé + Description) dans le sous-module du Catalogue.
     */
    public function storeCategorieFrais(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'etablissement_id' => 'required|exists:etablissements,id',
            'nom' => 'required|string|max:120',
            'description' => 'nullable|string|max:500',
        ]);

        $nom = trim($validated['nom']);

        CategorieFrais::withoutGlobalScopes()->updateOrCreate(
            [
                'etablissement_id' => (int) $validated['etablissement_id'],
                'nom' => $nom,
            ],
            [
                'description' => isset($validated['description']) && trim((string) $validated['description']) !== ''
                    ? trim((string) $validated['description'])
                    : null,
                'is_active' => true,
            ]
        );

        return back()->with('success', "Catégorie de frais « {$nom} » enregistrée avec succès.");
    }

    /**
     * Met à jour le libellé ou la description d'une Catégorie de Frais existante.
     */
    public function updateCategorieFrais(Request $request, CategorieFrais $categorieFrais): RedirectResponse
    {
        $validated = $request->validate([
            'nom' => 'required|string|max:120',
            'description' => 'nullable|string|max:500',
        ]);

        $nom = trim($validated['nom']);

        $doublon = CategorieFrais::withoutGlobalScopes()
            ->where('etablissement_id', $categorieFrais->etablissement_id)
            ->where('nom', $nom)
            ->where('id', '!=', $categorieFrais->id)
            ->exists();

        if ($doublon) {
            return back()->withErrors(['nom' => 'Une catégorie portant ce libellé existe déjà dans cet établissement.']);
        }

        $categorieFrais->update([
            'nom' => $nom,
            'description' => isset($validated['description']) && trim((string) $validated['description']) !== ''
                ? trim((string) $validated['description'])
                : null,
        ]);

        return back()->with('success', "Catégorie « {$nom} » mise à jour avec succès.");
    }

    /**
     * Supprime une Catégorie de Frais uniquement si aucun frais actif du catalogue ne lui est rattaché.
     *
     * Note : les frais précédemment mis à la corbeille (soft-deleted) ne doivent pas bloquer
     * la suppression d'une catégorie affichée avec un compteur à 0.
     *
     * @param  CategorieFrais  $categorieFrais  Catégorie ciblée
     * @return RedirectResponse Redirection avec message de succès ou d'erreur
     */
    public function destroyCategorieFrais(CategorieFrais $categorieFrais): RedirectResponse
    {
        $nom = $categorieFrais->nom;
        $nbFraisLies = TypeFrais::withoutGlobalScope(EtablissementScope::class)
            ->where('categorie_frais_id', $categorieFrais->id)
            ->count();

        if ($nbFraisLies > 0) {
            $message = "Suppression impossible : la catégorie « {$nom} » est déjà utilisée par {$nbFraisLies} frais dans le catalogue.";

            return back()->with('error', $message)->withErrors(['error' => $message]);
        }

        // Dissocie proprement les anciens frais de corbeille (soft-deleted) s'il y en avait
        TypeFrais::withoutGlobalScope(EtablissementScope::class)
            ->withTrashed()
            ->where('categorie_frais_id', $categorieFrais->id)
            ->update(['categorie_frais_id' => null]);

        $categorieFrais->delete();

        return back()->with('success', "Catégorie « {$nom} » supprimée.");
    }

    /**
     * Crée ou met à jour un Frais dans le Catalogue des Frais (catégorie, montant, priorité, date butoir et infos complémentaires).
     *
     * Pour alléger la saisie, `code` et `periodicite` sont générés automatiquement lorsqu'ils ne sont pas fournis,
     * tout en restant 100 % rétrocompatibles avec les appels explicites.
     */
    public function storeTypeFrais(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'etablissement_id' => 'required|exists:etablissements,id',
            'categorie_frais_id' => 'nullable|exists:categories_frais,id',
            'code' => 'nullable|string|max:50',
            'libelle' => 'required|string|max:150',
            'description' => 'nullable|string|max:1000',
            'obligatoire' => 'nullable|boolean',
            'periodicite' => 'nullable|in:annuel,trimestriel,mensuel,ponctuel',
            'ordre_priorite' => 'nullable|integer|min:1|max:99',
            'montant_defaut' => 'required|numeric|min:0',
            'date_limite_defaut' => 'nullable|date',
        ]);

        $etabId = (int) $validated['etablissement_id'];
        $codeExplicite = isset($validated['code']) && trim((string) $validated['code']) !== '';

        if ($codeExplicite) {
            $validated['code'] = Str::upper(trim((string) $validated['code']));
        } else {
            $baseSlug = Str::upper(Str::slug(Str::limit($validated['libelle'], 32, ''), '_'));
            $baseSlug = $baseSlug !== '' ? $baseSlug : 'FRAIS';
            $codeCandidat = $baseSlug;
            $compteur = 2;

            while (TypeFrais::withTrashed()
                ->where('etablissement_id', $etabId)
                ->where('code', $codeCandidat)
                ->exists()
            ) {
                $codeCandidat = Str::limit($baseSlug, 42, '').'_'.$compteur;
                $compteur++;
            }

            $validated['code'] = $codeCandidat;
        }

        if (empty($validated['periodicite'])) {
            $validated['periodicite'] = 'annuel';
        }

        $validated['obligatoire'] = isset($validated['obligatoire']) ? (bool) $validated['obligatoire'] : true;

        if (empty($validated['date_limite_defaut'])) {
            $validated['date_limite_defaut'] = null;
        }

        if (empty($validated['ordre_priorite'])) {
            $cle = mb_strtoupper($validated['code'].' '.$validated['libelle']);
            if (str_contains($cle, 'APE')) {
                $validated['ordre_priorite'] = 1;
            } elseif (str_contains($cle, 'INSCR')) {
                $validated['ordre_priorite'] = 2;
            } elseif (str_contains($cle, 'SCOL') || str_contains($cle, 'PENS') || str_contains($cle, 'TRANCHE')) {
                $validated['ordre_priorite'] = 3;
            } else {
                $validated['ordre_priorite'] = 10;
            }
        }

        TypeFrais::updateOrCreate(
            ['etablissement_id' => $etabId, 'code' => $validated['code']],
            $validated
        );

        return back()->with('success', 'Frais enregistré avec succès dans le catalogue.');
    }

    /**
     * Met à jour un Frais existant du Catalogue et synchronise automatiquement toutes ses lignes
     * rattachées aux niveaux pour l'année scolaire active, puis recalcule les comptes élèves impactés.
     */
    public function updateTypeFrais(Request $request, TypeFrais $typeFrais, FinanceService $financeService): RedirectResponse
    {
        $validated = $request->validate([
            'categorie_frais_id' => 'nullable|exists:categories_frais,id',
            'libelle' => 'required|string|max:150',
            'description' => 'nullable|string|max:1000',
            'obligatoire' => 'nullable|boolean',
            'periodicite' => 'nullable|in:annuel,trimestriel,mensuel,ponctuel',
            'ordre_priorite' => 'required|integer|min:1|max:99',
            'montant_defaut' => 'required|numeric|min:0',
            'date_limite_defaut' => 'nullable|date',
        ]);

        $typeFrais->update([
            'categorie_frais_id' => $validated['categorie_frais_id'] ?? null,
            'libelle' => trim($validated['libelle']),
            'description' => isset($validated['description']) && trim((string) $validated['description']) !== ''
                ? trim((string) $validated['description'])
                : null,
            'obligatoire' => isset($validated['obligatoire']) ? (bool) $validated['obligatoire'] : (bool) $typeFrais->obligatoire,
            'periodicite' => $validated['periodicite'] ?? $typeFrais->periodicite ?? 'annuel',
            'ordre_priorite' => (int) $validated['ordre_priorite'],
            'montant_defaut' => (float) $validated['montant_defaut'],
            'date_limite_defaut' => ! empty($validated['date_limite_defaut']) ? $validated['date_limite_defaut'] : null,
        ]);

        // Synchronisation automatique des niveaux déjà rattachés à ce frais sur l'année active
        $currentAnnee = AnneeScolaire::where('is_current', true)->first()
            ?? AnneeScolaire::latest()->first();

        if ($currentAnnee) {
            $lignesRattachees = FraisScolaire::where('etablissement_id', $typeFrais->etablissement_id)
                ->where('annee_scolaire_id', $currentAnnee->id)
                ->where('type_frais_id', $typeFrais->id)
                ->get();

            if ($lignesRattachees->isNotEmpty()) {
                $trancheNum = $financeService->infererNumeroTrancheDepuisLibelle($typeFrais->libelle);

                foreach ($lignesRattachees as $ligne) {
                    $ligne->update([
                        'tranche_numero' => $trancheNum,
                        'ordre_priorite' => (int) $typeFrais->ordre_priorite,
                        'libelle_tranche' => $typeFrais->libelle,
                        'montant' => (float) $typeFrais->montant_defaut,
                        'date_limite' => $typeFrais->date_limite_defaut,
                    ]);
                }

                $niveauIds = $lignesRattachees->pluck('niveau_id')->unique();
                foreach ($niveauIds as $nivId) {
                    $financeService->recalculerComptesNiveau(
                        (int) $typeFrais->etablissement_id,
                        (int) $currentAnnee->id,
                        (int) $nivId
                    );
                }
            }
        }

        return back()->with('success', 'Frais mis à jour et synchronisé sur tous les niveaux rattachés.');
    }

    /**
     * Supprime un frais du catalogue uniquement s'il n'est appliqué à aucun niveau et n'est lié à aucun versement ni remise.
     */
    public function destroyTypeFrais(TypeFrais $typeFrais, FinanceService $financeService): RedirectResponse
    {
        $affectations = FraisScolaire::with('niveau')
            ->where('type_frais_id', $typeFrais->id)
            ->get();

        if ($affectations->isNotEmpty()) {
            $nomsNiveaux = $affectations
                ->map(fn (FraisScolaire $fs) => $fs->niveau?->nom)
                ->filter()
                ->unique()
                ->values();

            $precisionNiveaux = $nomsNiveaux->isNotEmpty()
                ? ' ('.$nomsNiveaux->implode(', ').')'
                : '';

            $message = "Suppression impossible : le frais « {$typeFrais->libelle} » est actuellement appliqué à {$affectations->count()} ligne(s) de niveau{$precisionNiveaux}. Retirez-le d'abord de la grille par niveau.";

            return back()->with('error', $message)->withErrors(['error' => $message]);
        }

        if (VentilationVersement::where('type_frais_id', $typeFrais->id)
            ->whereHas('versement', fn ($q) => $q->where('statut', 'valide'))
            ->exists()) {
            $message = "Suppression impossible : le frais « {$typeFrais->libelle} » fait déjà l'objet de versements enregistrés en caisse.";

            return back()->with('error', $message)->withErrors(['error' => $message]);
        }

        if (BourseRemise::withoutGlobalScopes()->where('type_frais_id', $typeFrais->id)->exists()) {
            $message = "Suppression impossible : le frais « {$typeFrais->libelle} » est référencé par un modèle de bourse ou remise.";

            return back()->with('error', $message)->withErrors(['error' => $message]);
        }

        $typeFrais->delete();

        return back()->with('success', 'Frais retiré du catalogue.');
    }

    /**
     * Rattache en une seule opération un ou plusieurs Frais du Catalogue à un ou plusieurs Niveaux
     * (ex: appliquer « Inscription SIL-CM2 » et « Tranche 1 SIL-CM2 » simultanément à SIL, CP, CE1, CE2, CM1, CM2),
     * sans ressaisie du montant ni de la date butoir, puis recalcule tous les dossiers élèves concernés.
     */
    public function affecterFraisAuxNiveaux(Request $request, FinanceService $financeService): RedirectResponse
    {
        $validated = $request->validate([
            'annee_scolaire_id' => 'required|exists:annees_scolaires,id',
            'etablissement_id' => 'required|exists:etablissements,id',
            'type_frais_ids' => 'required|array|min:1',
            'type_frais_ids.*' => 'integer|exists:types_frais,id',
            'niveau_ids' => 'required|array|min:1',
            'niveau_ids.*' => 'integer|exists:niveaux,id',
            'serie' => 'nullable|string|max:50',
            'obligatoire' => 'nullable|boolean',
        ]);

        $anneeId = (int) $validated['annee_scolaire_id'];
        $etabId = (int) $validated['etablissement_id'];
        $serie = isset($validated['serie']) && trim((string) $validated['serie']) !== ''
            ? trim((string) $validated['serie'])
            : null;
        $isObligatoire = $request->has('obligatoire') ? $request->boolean('obligatoire') : true;

        $typesSelectionnes = TypeFrais::where('etablissement_id', $etabId)
            ->whereIn('id', $validated['type_frais_ids'])
            ->get();

        $niveauxValidesIds = Niveau::where('etablissement_id', $etabId)
            ->whereIn('id', $validated['niveau_ids'])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($typesSelectionnes->isEmpty() || $niveauxValidesIds->isEmpty()) {
            return back()->withErrors(['error' => 'Veuillez sélectionner au moins un frais et un niveau valides de cet établissement.']);
        }

        $nbAffectations = 0;

        DB::transaction(function () use ($typesSelectionnes, $niveauxValidesIds, $anneeId, $etabId, $serie, $isObligatoire, $financeService, &$nbAffectations) {
            foreach ($typesSelectionnes as $typeFrais) {
                $trancheNum = $financeService->infererNumeroTrancheDepuisLibelle($typeFrais->libelle);

                foreach ($niveauxValidesIds as $niveauId) {
                    $query = FraisScolaire::where('annee_scolaire_id', $anneeId)
                        ->where('etablissement_id', $etabId)
                        ->where('niveau_id', $niveauId)
                        ->where('type_frais_id', $typeFrais->id);

                    if ($serie === null) {
                        $query->whereNull('serie');
                    } else {
                        $query->where('serie', $serie);
                    }

                    $existant = $query->first();

                    $attributs = [
                        'annee_scolaire_id' => $anneeId,
                        'etablissement_id' => $etabId,
                        'niveau_id' => $niveauId,
                        'serie' => $serie,
                        'type_frais_id' => $typeFrais->id,
                        'tranche_numero' => $trancheNum,
                        'ordre_priorite' => (int) ($typeFrais->ordre_priorite ?? 10),
                        'libelle_tranche' => $typeFrais->libelle,
                        'montant' => (float) $typeFrais->montant_defaut,
                        'obligatoire' => $isObligatoire,
                        'date_limite' => $typeFrais->date_limite_defaut,
                    ];

                    if ($existant) {
                        $existant->update($attributs);
                    } else {
                        FraisScolaire::create($attributs);
                    }

                    $nbAffectations++;
                }
            }
        });

        foreach ($niveauxValidesIds as $niveauId) {
            $financeService->recalculerComptesNiveau($etabId, $anneeId, $niveauId);
        }

        return back()->with(
            'success',
            "{$nbAffectations} rattachement(s) enregistré(s) sur {$niveauxValidesIds->count()} niveau(x). Les comptes élèves ont été actualisés."
        );
    }

    /**
     * Enregistre un tarif dans la grille tarifaire et recalcule immédiatement les comptes élèves du niveau.
     */
    public function storeTarif(Request $request, FinanceService $financeService): RedirectResponse
    {
        $validated = $request->validate([
            'annee_scolaire_id' => 'required|exists:annees_scolaires,id',
            'etablissement_id' => 'required|exists:etablissements,id',
            'niveau_id' => 'required|exists:niveaux,id',
            'serie' => 'nullable|string|max:50',
            'type_frais_id' => 'required|exists:types_frais,id',
            'tranche_numero' => 'required|integer|min:1|max:10',
            'ordre_priorite' => 'nullable|integer|min:1|max:99',
            'libelle_tranche' => 'nullable|string|max:150',
            'montant' => 'required|numeric|min:0',
            'obligatoire' => 'nullable|boolean',
            'date_limite' => 'nullable|date',
        ]);

        if (isset($validated['serie']) && trim((string) $validated['serie']) === '') {
            $validated['serie'] = null;
        }
        if (array_key_exists('ordre_priorite', $validated) && ($validated['ordre_priorite'] === '' || $validated['ordre_priorite'] === null)) {
            $validated['ordre_priorite'] = null;
        }
        $validated['obligatoire'] = $request->has('obligatoire') ? $request->boolean('obligatoire') : true;

        FraisScolaire::create($validated);

        // Mise à jour immédiate de tous les élèves inscrits dans ce niveau
        $financeService->recalculerComptesNiveau(
            (int) $validated['etablissement_id'],
            (int) $validated['annee_scolaire_id'],
            (int) $validated['niveau_id']
        );

        return back()->with('success', 'Ligne de tarif enregistrée avec succès dans la grille tarifaire.');
    }

    /**
     * Met à jour une ligne existante de la grille tarifaire (montant, tranche, priorité, date limite...)
     * et recalcule automatiquement les soldes des élèves concernés.
     */
    public function updateTarif(Request $request, FraisScolaire $fraisScolaire, FinanceService $financeService): RedirectResponse
    {
        $validated = $request->validate([
            'niveau_id' => 'required|exists:niveaux,id',
            'serie' => 'nullable|string|max:50',
            'type_frais_id' => 'required|exists:types_frais,id',
            'tranche_numero' => 'required|integer|min:1|max:10',
            'ordre_priorite' => 'nullable|integer|min:1|max:99',
            'libelle_tranche' => 'nullable|string|max:150',
            'montant' => 'required|numeric|min:0',
            'obligatoire' => 'nullable|boolean',
            'date_limite' => 'nullable|date',
        ]);

        if (isset($validated['serie']) && trim((string) $validated['serie']) === '') {
            $validated['serie'] = null;
        }
        if (array_key_exists('ordre_priorite', $validated) && ($validated['ordre_priorite'] === '' || $validated['ordre_priorite'] === null)) {
            $validated['ordre_priorite'] = null;
        }
        if ($request->has('obligatoire')) {
            $validated['obligatoire'] = $request->boolean('obligatoire');
        }

        $ancienNiveauId = (int) $fraisScolaire->niveau_id;
        $fraisScolaire->update($validated);

        $financeService->recalculerComptesNiveau(
            (int) $fraisScolaire->etablissement_id,
            (int) $fraisScolaire->annee_scolaire_id,
            (int) $fraisScolaire->niveau_id
        );

        if ($ancienNiveauId !== (int) $fraisScolaire->niveau_id) {
            $financeService->recalculerComptesNiveau(
                (int) $fraisScolaire->etablissement_id,
                (int) $fraisScolaire->annee_scolaire_id,
                $ancienNiveauId
            );
        }

        return back()->with('success', 'Ligne de tarif mise à jour avec succès.');
    }

    /**
     * Retire une ligne de tarif d'un niveau uniquement si aucune opération élève (versement, dispense ou option)
     * ne s'appuie déjà dessus, puis réactualise les comptes des élèves du niveau concerné.
     */
    public function destroyTarif(FraisScolaire $fraisScolaire, FinanceService $financeService): RedirectResponse
    {
        $fraisScolaire->loadMissing(['typeFrais', 'niveau']);
        $intitule = ($fraisScolaire->libelle_tranche ?: $fraisScolaire->typeFrais?->libelle ?: 'Tarif')
            .($fraisScolaire->niveau ? " ({$fraisScolaire->niveau->nom})" : '');

        $blockers = [];
        $nbVentilations = VentilationVersement::where('frais_scolaire_id', $fraisScolaire->id)
            ->whereHas('versement', fn ($q) => $q->where('statut', 'valide'))
            ->count();
        if ($nbVentilations > 0) {
            $blockers[] = "{$nbVentilations} versement(s) actif(s)";
        }

        $nbDispenses = DispenseFinanciere::withoutGlobalScopes()
            ->where('frais_scolaire_id', $fraisScolaire->id)
            ->count();
        if ($nbDispenses > 0) {
            $blockers[] = "{$nbDispenses} dispense(s) accordée(s)";
        }

        $nbSouscriptions = SouscriptionFraisOptionnel::where('frais_scolaire_id', $fraisScolaire->id)
            ->where('statut', 'actif')
            ->count();
        if ($nbSouscriptions > 0) {
            $blockers[] = "{$nbSouscriptions} souscription(s) d'élève(s)";
        }

        $nbRemisesCiblees = EleveBourseRemise::where('frais_scolaire_id', $fraisScolaire->id)->count();
        if ($nbRemisesCiblees > 0) {
            $blockers[] = "{$nbRemisesCiblees} remise(s) ciblée(s)";
        }

        if (! empty($blockers)) {
            $message = "Suppression impossible : la ligne « {$intitule} » est déjà liée à des opérations élèves (".implode(', ', $blockers).').';

            return back()->with('error', $message)->withErrors(['error' => $message]);
        }

        $etabId = (int) $fraisScolaire->etablissement_id;
        $anneeId = (int) $fraisScolaire->annee_scolaire_id;
        $niveauId = (int) $fraisScolaire->niveau_id;

        $fraisScolaire->delete();

        $financeService->recalculerComptesNiveau($etabId, $anneeId, $niveauId);

        return back()->with('success', 'Tarif retiré de la grille tarifaire.');
    }

    /**
     * Active ou désactive le verrou de solvabilité sur un établissement.
     */
    public function toggleVerrouSolvabilite(Request $request, Etablissement $etablissement): RedirectResponse
    {
        $validated = $request->validate([
            'verrou_solvabilite_actif' => 'required|boolean',
        ]);

        $etablissement->update([
            'verrou_solvabilite_actif' => $validated['verrou_solvabilite_actif'],
        ]);

        $etat = $etablissement->verrou_solvabilite_actif ? 'activé' : 'désactivé';

        return back()->with('success', "Le verrou de solvabilité a été {$etat} pour cet établissement.");
    }

    /**
     * Crée une nouvelle caisse d'encaissement (Aurore §3.20).
     */
    public function storeCaisse(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'etablissement_id' => 'required|exists:etablissements,id',
            'code' => 'required|string|max:50',
            'nom' => 'required|string|max:150',
            'solde_initial' => 'nullable|numeric|min:0',
            'responsable_id' => 'nullable|exists:personnels,id',
            'is_principale' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'description' => 'nullable|string|max:500',
        ]);

        if (! empty($validated['is_principale'])) {
            Caisse::where('etablissement_id', $validated['etablissement_id'])
                ->update(['is_principale' => false]);
        }

        Caisse::create($validated);

        return back()->with('success', "Caisse « {$validated['nom']} » créée avec succès.");
    }

    /**
     * Met à jour les informations d'une caisse.
     */
    public function updateCaisse(Request $request, Caisse $caisse): RedirectResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50',
            'nom' => 'required|string|max:150',
            'solde_initial' => 'nullable|numeric|min:0',
            'responsable_id' => 'nullable|exists:personnels,id',
            'is_principale' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'description' => 'nullable|string|max:500',
        ]);

        if (! empty($validated['is_principale']) && ! $caisse->is_principale) {
            Caisse::where('etablissement_id', $caisse->etablissement_id)
                ->update(['is_principale' => false]);
        }

        $caisse->update($validated);

        return back()->with('success', "Caisse « {$caisse->nom} » mise à jour avec succès.");
    }

    /**
     * Supprime une caisse (si aucun versement rattaché).
     */
    public function destroyCaisse(Caisse $caisse): RedirectResponse
    {
        if ($caisse->is_principale) {
            return back()->withErrors(['error' => "La caisse principale de l'établissement ne peut pas être supprimée."]);
        }

        if ($caisse->versements()->exists() || $caisse->reglementsDivers()->exists()) {
            return back()->withErrors(['error' => "Impossible de supprimer cette caisse car elle contient déjà des opérations d'encaissement."]);
        }

        $caisse->delete();

        return back()->with('success', 'Caisse supprimée avec succès.');
    }

    /**
     * Enregistre un règlement divers hors scolarité (Aurore §4.2).
     *
     * Supporte les catégories personnalisées créées par l'établissement et résout automatiquement
     * la Caisse Principale si aucune caisse spécifique n'est fournie.
     */
    public function storeReglementDivers(Request $request, FinanceService $financeService): RedirectResponse
    {
        $validated = $request->validate([
            'caisse_id' => 'nullable|exists:caisses,id',
            'etablissement_id' => 'nullable|exists:etablissements,id',
            'payeur_nom' => 'required|string|max:150',
            'payeur_telephone' => 'nullable|string|max:50',
            'categorie' => 'required|string|max:100',
            'libelle' => 'nullable|string|max:255',
            'montant' => 'required|numeric|min:1',
            'date_paiement' => 'nullable|date',
            'mode_paiement' => 'required|in:especes,cheque,virement,mobile_money_manuel',
            'reference_paiement' => 'nullable|string|max:100',
            'personnel_id' => 'nullable|exists:personnels,id',
            'observations' => 'nullable|string|max:500',
        ]);

        if (empty($validated['libelle'])) {
            $validated['libelle'] = $validated['categorie'];
        }

        if (empty($validated['caisse_id']) && empty($validated['etablissement_id'])) {
            $context = $this->resolveEtablissementContext($request);
            $validated['etablissement_id'] = $context['etablissementId'];
        }

        $caissier = null;
        if (! empty($validated['personnel_id'])) {
            $caissier = Personnel::find($validated['personnel_id']);
        }

        try {
            $reglement = $financeService->enregistrerReglementDivers($validated, $caissier, $request->user());

            return back()->with([
                'success' => 'Recette diverse de '.number_format($reglement->montant, 0)." FCFA enregistrée (Reçu N° {$reglement->reference_recu}).",
                'dernier_recu_divers_id' => $reglement->id,
            ]);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Met à jour une recette diverse préenregistrée.
     */
    public function updateReglementDivers(Request $request, ReglementDivers $reglementDivers): RedirectResponse
    {
        $validated = $request->validate([
            'payeur_nom' => 'required|string|max:150',
            'payeur_telephone' => 'nullable|string|max:50',
            'categorie' => 'required|string|max:100',
            'libelle' => 'nullable|string|max:255',
            'montant' => 'required|numeric|min:1',
            'date_paiement' => 'nullable|date',
            'mode_paiement' => 'required|in:especes,cheque,virement,mobile_money_manuel',
            'reference_paiement' => 'nullable|string|max:100',
            'observations' => 'nullable|string|max:500',
        ]);

        if (empty($validated['libelle'])) {
            $validated['libelle'] = $validated['categorie'];
        }

        $reglementDivers->payeur_nom = $validated['payeur_nom'];
        $reglementDivers->payeur_telephone = $validated['payeur_telephone'] ?? null;
        $reglementDivers->categorie = $validated['categorie'];
        $reglementDivers->libelle = $validated['libelle'];
        $reglementDivers->montant = $validated['montant'];
        $reglementDivers->mode_paiement = $validated['mode_paiement'];
        $reglementDivers->reference_paiement = $validated['reference_paiement'] ?? null;
        if (! empty($validated['observations'])) {
            $reglementDivers->observations = $validated['observations'];
        }

        if (! empty($validated['date_paiement'])) {
            $reglementDivers->date_reglement = Carbon::parse($validated['date_paiement'])->setTime(Carbon::now()->hour, Carbon::now()->minute, Carbon::now()->second);
        }

        $reglementDivers->save();

        return back()->with('success', "Recette diverse N° {$reglementDivers->reference_recu} modifiée avec succès.");
    }

    /**
     * Crée une nouvelle catégorie personnalisée de recette diverse pour l'établissement.
     */
    public function storeCategorieRecette(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'etablissement_id' => 'nullable|exists:etablissements,id',
            'nom' => 'required|string|max:100',
        ]);

        $etabId = $validated['etablissement_id'] ?? $this->resolveEtablissementContext($request)['etablissementId'];
        $nom = trim($validated['nom']);

        CategorieRecetteDiverse::updateOrCreate(
            ['etablissement_id' => $etabId, 'nom' => $nom],
            ['is_active' => true]
        );

        return back()->with('success', "Catégorie de recette « {$nom} » créée avec succès.");
    }

    /**
     * Supprime une catégorie personnalisée de recette diverse si aucune recette ne l'utilise.
     */
    public function destroyCategorieRecette(CategorieRecetteDiverse $categorie): RedirectResponse
    {
        $utilisee = ReglementDivers::withoutGlobalScopes()
            ->where('etablissement_id', $categorie->etablissement_id)
            ->where('categorie', $categorie->nom)
            ->exists();

        if ($utilisee) {
            $message = "Suppression impossible : la catégorie de recette « {$categorie->nom} » est déjà utilisée par des encaissements divers.";

            return back()->with('error', $message)->withErrors(['error' => $message]);
        }

        $categorie->delete();

        return back()->with('success', 'Catégorie de recette supprimée.');
    }

    /**
     * Annule un règlement divers avec justification.
     */
    public function annulerReglementDivers(Request $request, ReglementDivers $reglement, FinanceService $financeService): RedirectResponse
    {
        $request->validate([
            'motif_annulation' => 'required|string|min:5|max:500',
        ]);

        try {
            $financeService->annulerReglementDivers($reglement, $request->input('motif_annulation'), $request->user());

            return back()->with('success', "Le règlement divers {$reglement->reference_recu} a été annulé avec succès.");
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Imprime le reçu A4 portrait d'un règlement divers (double exemplaire versant / souche) avec QR code.
     *
     * @param  ReglementDivers  $reglement  Règlement divers concerné
     * @param  QrCodeService  $qrCodeService  Service de génération du QR code
     * @return Response Document PDF généré
     */
    public function imprimerRecuDivers(ReglementDivers $reglement, QrCodeService $qrCodeService): Response
    {
        $reglement->load(['caisse', 'caissier', 'etablissement']);
        $etablissement = $reglement->etablissement;

        $dateReglement = $reglement->date_reglement instanceof Carbon
            ? $reglement->date_reglement->format('d/m/Y H:i')
            : Carbon::parse($reglement->date_reglement)->format('d/m/Y H:i');

        $qrData = sprintf(
            'SYGES-REC-DIV:%s|DATE:%s|PAYEUR:%s|MONTANT:%s FCFA|MOTIF:%s|MODE:%s',
            $reglement->reference_recu,
            $dateReglement,
            $reglement->payeur_nom,
            number_format($reglement->montant, 0, ',', ' '),
            $reglement->libelle,
            strtoupper($reglement->mode_paiement)
        );

        $qrCodeBase64 = $qrCodeService->generateBase64Svg($qrData, 90);

        $pdf = Pdf::loadView('finances.recu_divers', compact('reglement', 'etablissement', 'qrCodeBase64'))
            ->setPaper('a4', 'portrait');

        return $pdf->stream("Recu_Divers_{$reglement->reference_recu}.pdf");
    }

    /**
     * Valide et normalise les données communes d'un modèle de bourse ou remise.
     *
     * @param  Request  $request  Requête HTTP entrante
     * @param  BourseRemise|null  $existant  Modèle existant en cas de mise à jour (null lors d'une création)
     * @return array<string, mixed> Données prêtes pour la création ou mise à jour Eloquent
     */
    private function validerDonneesBourseRemise(Request $request, ?BourseRemise $existant = null): array
    {
        $rules = [
            'code' => 'nullable|string|max:50',
            'nom' => 'nullable|string|max:150',
            'type' => 'required|in:bourse,remise',
            'categorie' => 'required|string|max:100',
            'mode_calcul' => 'required|in:pourcentage,montant_fixe',
            'valeur' => 'required|numeric|min:0.01',
            'base_calcul' => 'nullable|in:total_scolarite,tranche_ciblee',
            'cible_imputation' => 'nullable|in:derniere_tranche,tranche_1,tranche_2,tranche_3,tranche_4,proportionnelle',
            'niveau_id' => 'nullable|exists:niveaux,id',
            'type_frais_id' => 'nullable|exists:types_frais,id',
            'description' => 'nullable|string|max:500',
            'is_active' => 'nullable|boolean',
        ];

        if ($existant === null) {
            $rules['etablissement_id'] = 'required|exists:etablissements,id';
            $rules['annee_scolaire_id'] = 'nullable|exists:annees_scolaires,id';
        }

        $validated = $request->validate($rules);

        $nomNettoye = trim((string) ($validated['nom'] ?? ''));
        if ($nomNettoye === '') {
            $catLabel = strtoupper($validated['categorie']) === 'BOURSE' ? 'Bourse' : 'Remise';
            $valeurLabel = $validated['mode_calcul'] === 'pourcentage'
                ? "{$validated['valeur']}%"
                : number_format((float) $validated['valeur'], 0, '', ' ').' FCFA';
            $nomNettoye = "{$catLabel} {$valeurLabel}";
        }
        $validated['nom'] = $nomNettoye;

        if (empty($validated['code'])) {
            if ($existant !== null) {
                $validated['code'] = $existant->code;
            } else {
                $prefix = $validated['type'] === 'bourse' ? 'BRS' : 'REM';
                $slug = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', Str::ascii($validated['nom'])), 0, 6) ?: 'STD');
                $validated['code'] = "{$prefix}-{$slug}-".random_int(100, 999);
            }
        }

        if (empty($validated['base_calcul'])) {
            $validated['base_calcul'] = $existant?->base_calcul ?: 'total_scolarite';
        }

        if (empty($validated['cible_imputation'])) {
            $validated['cible_imputation'] = $existant?->cible_imputation ?: 'derniere_tranche';
        }

        return $validated;
    }

    /**
     * Crée un modèle de bourse ou de remise avec sa base de calcul et sa tranche cible par défaut.
     *
     * Génère automatiquement le libellé ou le code interne si non renseigné et accepte les
     * catégories personnalisées telles que « Paiement en avance (avant rentrée) ».
     *
     * @param  Request  $request  Requête contenant les paramètres du modèle
     * @return RedirectResponse Redirection avec message de confirmation
     */
    public function storeBourseRemise(Request $request): RedirectResponse
    {
        $validated = $this->validerDonneesBourseRemise($request);

        BourseRemise::create($validated);

        return back()->with('success', "Modèle de {$validated['type']} « {$validated['nom']} » créé avec succès.");
    }

    /**
     * Met à jour une bourse ou remise ainsi que sa base de calcul et sa règle d'imputation.
     *
     * @param  Request  $request  Requête contenant les modifications
     * @param  BourseRemise  $bourseRemise  Modèle ciblé
     * @return RedirectResponse Redirection avec message de confirmation
     */
    public function updateBourseRemise(Request $request, BourseRemise $bourseRemise): RedirectResponse
    {
        $validated = $this->validerDonneesBourseRemise($request, $bourseRemise);

        $bourseRemise->update($validated);

        return back()->with('success', 'Modèle mis à jour avec succès.');
    }

    /**
     * Crée une nouvelle catégorie personnalisée de bourse ou remise pour l'établissement.
     */
    public function storeCategorieBourse(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'etablissement_id' => 'nullable|exists:etablissements,id',
            'nom' => 'required|string|max:100',
        ]);

        $etabId = $validated['etablissement_id'] ?? $this->resolveEtablissementContext($request)['etablissementId'];
        $nom = trim($validated['nom']);

        CategorieBourseRemise::updateOrCreate(
            ['etablissement_id' => $etabId, 'nom' => $nom],
            ['is_active' => true]
        );

        return back()->with('success', "Catégorie « {$nom} » ajoutée avec succès.");
    }

    /**
     * Supprime une catégorie personnalisée de bourse ou remise si aucun modèle ne l'utilise.
     */
    public function destroyCategorieBourse(CategorieBourseRemise $categorie): RedirectResponse
    {
        $utilisee = BourseRemise::withoutGlobalScopes()
            ->where('etablissement_id', $categorie->etablissement_id)
            ->where('categorie', $categorie->nom)
            ->exists();

        if ($utilisee) {
            $message = "Suppression impossible : la catégorie « {$categorie->nom} » est déjà rattachée à un ou plusieurs modèles de bourse/remise.";

            return back()->with('error', $message)->withErrors(['error' => $message]);
        }

        $categorie->delete();

        return back()->with('success', 'Catégorie supprimée.');
    }

    /**
     * Supprime un modèle de bourse ou remise uniquement s'il n'est attribué à aucun élève.
     */
    public function destroyBourseRemise(BourseRemise $bourseRemise): RedirectResponse
    {
        $nbAttributions = EleveBourseRemise::where('bourse_remise_id', $bourseRemise->id)->count();

        if ($nbAttributions > 0) {
            $message = "Suppression impossible : le modèle « {$bourseRemise->nom} » est actuellement accordé à {$nbAttributions} élève(s).";

            return back()->with('error', $message)->withErrors(['error' => $message]);
        }

        $bourseRemise->delete();

        return back()->with('success', 'Modèle supprimé avec succès.');
    }

    /**
     * Attribue une bourse ou remise au compte d'un élève, avec choix optionnel de la tranche cible d'imputation.
     */
    public function attribuerBourseRemise(Request $request, FinanceService $financeService): RedirectResponse
    {
        $validated = $request->validate([
            'compte_financier_id' => 'required|exists:comptes_financiers_eleves,id',
            'bourse_remise_id' => 'required|exists:bourses_remises,id',
            'cible_imputation' => 'nullable|in:derniere_tranche,tranche_1,tranche_2,tranche_3,tranche_4,proportionnelle',
            'frais_scolaire_id' => 'nullable|exists:frais_scolaires,id',
            'motif' => 'nullable|string|max:255',
        ]);

        $compte = CompteFinancierEleve::findOrFail($validated['compte_financier_id']);
        $bourseRemise = BourseRemise::findOrFail($validated['bourse_remise_id']);

        $attribution = $financeService->attribuerBourseRemise(
            $compte,
            $bourseRemise,
            $validated['motif'] ?? null,
            $request->user(),
            $validated['cible_imputation'] ?? null,
            ! empty($validated['frais_scolaire_id']) ? (int) $validated['frais_scolaire_id'] : null
        );

        return back()->with('success', "{$bourseRemise->nom} de ".number_format($attribution->montant_calcule, 0)." FCFA attribuée avec succès. L'ardoise de l'élève a été recalculée.");
    }

    /**
     * Résout de manière idempotente un élément rattaché à un compte financier élève tout en appliquant
     * le cloisonnement multi-campus (`EtablissementScope`).
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  class-string<TModel>  $modelClass  Classe Eloquent cible (`EleveBourseRemise`, `DispenseFinanciere`, etc.).
     * @param  int  $id  Identifiant de la ressource demandée.
     * @return TModel|null Instance trouvée et autorisée, ou `null` si l'élément a déjà été retiré.
     */
    protected function resolveDossierItemForRemoval(string $modelClass, int $id): mixed
    {
        $item = $modelClass::query()->find($id);
        if (! $item) {
            return null;
        }

        // Vérifie que le compte financier parent reste accessible dans le périmètre d'établissement de l'utilisateur
        $compteAutorise = CompteFinancierEleve::query()
            ->whereKey($item->compte_financier_id)
            ->exists();

        abort_unless($compteAutorise, 403, 'Accès non autorisé : ce dossier financier appartient à un autre établissement.');

        return $item;
    }

    /**
     * Retire une bourse ou remise accordée à un élève (idempotent contre les doubles clics).
     *
     * @param  int  $attribution  Identifiant de l'attribution de bourse/remise à retirer.
     * @param  FinanceService  $financeService  Service financier métier.
     * @return RedirectResponse Redirection avec notification de succès.
     */
    public function retirerBourseRemise(int $attribution, FinanceService $financeService): RedirectResponse
    {
        $attributionModel = $this->resolveDossierItemForRemoval(EleveBourseRemise::class, $attribution);
        if (! $attributionModel) {
            return back()->with('success', 'Cette remise a déjà été retirée du dossier.');
        }

        $financeService->retirerBourseRemise($attributionModel);

        return back()->with('success', 'Attribution de bourse/remise retirée avec succès.');
    }

    /**
     * Accorde une dispense financière (totale 100 % ou partielle) sur une tranche obligatoire d'un élève
     * (ex: élève arrivé au 2ème ou 3ème trimestre, exonération cotisation fratrie...).
     */
    public function storeDispenseFinanciere(Request $request, FinanceService $financeService): RedirectResponse
    {
        $validated = $request->validate([
            'compte_financier_id' => 'required|exists:comptes_financiers_eleves,id',
            'frais_scolaire_id' => 'required|exists:frais_scolaires,id',
            'type_dispense' => 'required|in:totale,partielle',
            'montant_dispense' => 'nullable|numeric|min:1',
            'motif' => 'required|string|min:3|max:255',
        ]);

        $compte = CompteFinancierEleve::findOrFail($validated['compte_financier_id']);
        $frais = FraisScolaire::with('typeFrais')->findOrFail($validated['frais_scolaire_id']);

        try {
            $dispense = $financeService->accorderDispenseFinanciere(
                $compte,
                $frais,
                $validated['type_dispense'],
                isset($validated['montant_dispense']) && $validated['montant_dispense'] !== '' ? (float) $validated['montant_dispense'] : null,
                $validated['motif'],
                $request->user()
            );

            $intitule = $frais->libelle_tranche ?: ($frais->typeFrais?->libelle.' - Tranche '.$frais->tranche_numero);

            return back()->with('success', 'Dispense de '.number_format($dispense->montant_dispense, 0, ',', ' ')." FCFA enregistrée sur « {$intitule} ».");
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Retire une dispense financière accordée sur une tranche et réactualise immédiatement le compte élève
     * (idempotent contre les doubles clics).
     *
     * @param  int  $dispense  Identifiant de la dispense à retirer.
     * @param  FinanceService  $financeService  Service financier métier.
     * @return RedirectResponse Redirection avec notification de succès.
     */
    public function destroyDispenseFinanciere(int $dispense, FinanceService $financeService): RedirectResponse
    {
        $dispenseModel = $this->resolveDossierItemForRemoval(DispenseFinanciere::class, $dispense);
        if (! $dispenseModel) {
            return back()->with('success', 'Cette dispense financière a déjà été retirée du dossier.');
        }

        $financeService->retirerDispenseFinanciere($dispenseModel);

        return back()->with('success', 'Dispense financière retirée avec succès. La tranche est à nouveau exigible.');
    }

    /**
     * Crée un modèle d'échéancier calendaire avec ses tranches (Aurore §3.24).
     */
    public function storeModeleEcheance(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'etablissement_id' => 'required|exists:etablissements,id',
            'annee_scolaire_id' => 'required|exists:annees_scolaires,id',
            'nom' => 'required|string|max:150',
            'description' => 'nullable|string|max:500',
            'is_default' => 'nullable|boolean',
            'tranches' => 'required|array|min:1',
            'tranches.*.tranche_numero' => 'required|integer|min:1',
            'tranches.*.libelle' => 'required|string|max:150',
            'tranches.*.date_limite' => 'required|date',
            'tranches.*.pourcentage_exigible' => 'required|numeric|min:0|max:100',
        ]);

        if (! empty($validated['is_default'])) {
            ModeleEcheance::where('etablissement_id', $validated['etablissement_id'])
                ->where('annee_scolaire_id', $validated['annee_scolaire_id'])
                ->update(['is_default' => false]);
        }

        $modele = ModeleEcheance::create([
            'etablissement_id' => $validated['etablissement_id'],
            'annee_scolaire_id' => $validated['annee_scolaire_id'],
            'nom' => $validated['nom'],
            'description' => $validated['description'] ?? null,
            'is_default' => $validated['is_default'] ?? false,
        ]);

        foreach ($validated['tranches'] as $tr) {
            EcheanceTranche::create([
                'modele_echeance_id' => $modele->id,
                'tranche_numero' => $tr['tranche_numero'],
                'libelle' => $tr['libelle'],
                'date_limite' => $tr['date_limite'],
                'pourcentage_exigible' => $tr['pourcentage_exigible'],
            ]);
        }

        return back()->with('success', "Modèle d'échéancier « {$modele->nom} » enregistré avec succès.");
    }

    /**
     * Supprime un modèle d'échéancier uniquement s'il n'est rattaché à aucune ligne de frais scolaire.
     */
    public function destroyModeleEcheance(ModeleEcheance $modeleEcheance): RedirectResponse
    {
        if (FraisScolaire::where('modele_echeance_id', $modeleEcheance->id)->exists()) {
            $message = "Suppression impossible : le modèle d'échéancier « {$modeleEcheance->nom} » est déjà utilisé dans la grille tarifaire.";

            return back()->with('error', $message)->withErrors(['error' => $message]);
        }

        $modeleEcheance->delete();

        return back()->with('success', 'Échéancier supprimé avec succès.');
    }

    /**
     * Enregistre ou met à jour la configuration des pénalités de retard (Aurore §3.17).
     */
    public function storePenaliteConfiguration(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'etablissement_id' => 'required|exists:etablissements,id',
            'nom' => 'required|string|max:150',
            'mode_calcul' => 'required|in:montant_fixe,pourcentage',
            'valeur' => 'required|numeric|min:1',
            'jours_grace' => 'required|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        PenaliteConfiguration::updateOrCreate(
            ['etablissement_id' => $validated['etablissement_id']],
            $validated
        );

        return back()->with('success', 'Paramétrage des pénalités de retard enregistré avec succès.');
    }

    /**
     * Déclenche l'analyse des retards d'échéances et applique les pénalités.
     */
    public function appliquerPenalitesRetard(Request $request, FinanceService $financeService): RedirectResponse
    {
        $validated = $request->validate([
            'etablissement_id' => 'required|exists:etablissements,id',
            'annee_scolaire_id' => 'required|exists:annees_scolaires,id',
        ]);

        $res = $financeService->appliquerPenalitesAutomatiquesEcheances(
            $validated['etablissement_id'],
            $validated['annee_scolaire_id'],
            $request->user()
        );

        if ($res['penalites_appliquees'] > 0) {
            return back()->with('success', "{$res['penalites_appliquees']} pénalité(s) de retard appliquée(s) pour un montant total de ".number_format($res['total_montant'], 0).' FCFA.');
        }

        return back()->with('info', 'Aucun nouveau retard de paiement à pénaliser à cette date.');
    }

    /**
     * Annule une pénalité appliquée sur le compte d'un élève.
     */
    public function annulerPenaliteEleve(PenaliteEleve $penalite, FinanceService $financeService): RedirectResponse
    {
        $financeService->annulerPenalite($penalite);

        return back()->with('success', 'Pénalité de retard annulée avec succès.');
    }

    /**
     * Enregistre une souscription à un frais optionnel (bus, cantine, internat) pour un élève.
     */
    public function storeSouscriptionOptionnelle(Request $request, FinanceService $financeService): RedirectResponse
    {
        abort_if(! $request->user()?->can('options_financieres.manage|finances.manage') && ! $request->user()?->hasRole('super-admin'), 403);

        $validated = $request->validate([
            'compte_financier_id' => 'required|exists:comptes_financiers_eleves,id',
            'frais_scolaire_id' => 'required|exists:frais_scolaires,id',
            'montant' => 'nullable|numeric|min:0',
            'observations' => 'nullable|string|max:255',
        ]);

        try {
            $financeService->souscrireFraisOptionnel(
                $validated['compte_financier_id'],
                $validated['frais_scolaire_id'],
                isset($validated['montant']) && $validated['montant'] !== '' ? (float) $validated['montant'] : null,
                $validated['observations'] ?? null
            );

            return back()->with('success', 'Souscription au service optionnel enregistrée avec succès. Le compte élève a été réactualisé.');
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Annule une souscription à un frais optionnel (idempotent contre les doubles clics).
     *
     * @param  int  $souscription  Identifiant de la souscription optionnelle.
     * @param  FinanceService  $financeService  Service financier métier.
     * @return RedirectResponse Redirection avec notification de succès ou d'erreur.
     */
    public function destroySouscriptionOptionnelle(int $souscription, FinanceService $financeService): RedirectResponse
    {
        abort_if(! request()->user()?->can('options_financieres.manage|finances.manage') && ! request()->user()?->hasRole('super-admin'), 403);

        $souscriptionModel = $this->resolveDossierItemForRemoval(SouscriptionFraisOptionnel::class, $souscription);
        if (! $souscriptionModel) {
            return back()->with('success', 'Cette souscription optionnelle a déjà été retirée du dossier.');
        }

        try {
            $financeService->annulerSouscriptionOptionnelle($souscriptionModel);

            return back()->with('success', 'Souscription au service optionnel annulée avec succès. Le compte élève a été réactualisé.');
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage())->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Applique en lot un frais optionnel (ex: Inscription Première CDE ou Première TI)
     * à tous les élèves d'une classe donnée afin de calculer immédiatement leur total à verser avant l'encaissement.
     */
    public function storeSouscriptionOptionnelleClasse(Request $request, FinanceService $financeService): RedirectResponse
    {
        abort_if(! $request->user()?->can('options_financieres.manage|finances.manage') && ! $request->user()?->hasRole('super-admin'), 403);

        $validated = $request->validate([
            'classe_id' => 'required|exists:classes,id',
            'frais_scolaire_id' => 'required|exists:frais_scolaires,id',
            'annee_scolaire_id' => 'nullable|exists:annees_scolaires,id',
        ]);

        $classe = Classe::findOrFail($validated['classe_id']);
        $etablissementId = (int) $classe->etablissement_id;
        $anneeId = ! empty($validated['annee_scolaire_id'])
            ? (int) $validated['annee_scolaire_id']
            : (int) (AnneeScolaire::where('is_current', true)->value('id') ?? AnneeScolaire::latest()->value('id'));

        try {
            $nbAppliques = $financeService->souscrireFraisOptionnelPourClasse(
                (int) $validated['classe_id'],
                (int) $validated['frais_scolaire_id'],
                $anneeId,
                $etablissementId
            );

            return back()->with(
                'success',
                "Frais optionnel appliqué à {$nbAppliques} élève(s) de la classe « {$classe->nom} ». Les montants exigibles ont été recalculés."
            );
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage())->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Retourne l'échéancier prévisionnel complet et le dossier financier d'un élève (API JSON).
     */
    public function dossierFinancierEleve(CompteFinancierEleve $compte, FinanceService $financeService): JsonResponse
    {
        $user = request()->user();
        abort_if(! $user?->can('finances.view|finances.manage') && ! $user?->hasRole('super-admin'), 403);

        $donnees = $financeService->obtenirEcheancierEleve($compte);
        $donnees['can_edit_versements'] = (bool) (
            $user?->hasRole('super-admin')
            || $user?->can('finances.manage')
            || $user?->can('versements.edit')
        );

        return response()->json($donnees);
    }

    /**
     * Génère et télécharge la Fiche Individuelle de Situation Financière de l'Élève (PDF A4 Portrait).
     */
    public function imprimerFicheCompte(CompteFinancierEleve $compte, FinanceService $financeService, QrCodeService $qrCodeService): Response
    {
        abort_if(! request()->user()?->can('finances.view|finances.manage') && ! request()->user()?->hasRole('super-admin'), 403);

        $echeancier = $financeService->obtenirEcheancierEleve($compte);
        $compte->loadMissing(['eleve', 'inscription.classe.niveau', 'anneeScolaire', 'etablissement', 'versements.ventilations', 'boursesRemises.bourseRemise']);

        $eleve = $compte->eleve;
        $etablissement = $compte->etablissement;
        $annee = $compte->anneeScolaire;

        $qrData = sprintf(
            'SYGES-COMPTE-ELEVE|MAT:%s|NOM:%s|CLASSE:%s|EXIGIBLE:%s|PAYE:%s|SOLDE:%s|STATUT:%s',
            $eleve->matricule,
            $eleve->nom.' '.($eleve->prenom ?? ''),
            $compte->inscription?->classe?->nom ?? 'N/A',
            number_format($compte->montant_net_exigible, 0, ',', ' '),
            number_format($compte->total_paye, 0, ',', ' '),
            number_format($compte->solde_restant, 0, ',', ' '),
            strtoupper($compte->statut_financier)
        );

        $qrCodeBase64 = $qrCodeService->generateBase64Svg($qrData, 95);

        $pdf = Pdf::loadView('finances.fiche_compte_eleve', compact('compte', 'echeancier', 'eleve', 'etablissement', 'annee', 'qrCodeBase64'))
            ->setPaper('a4', 'portrait');

        return $pdf->stream("Situation_Financiere_{$eleve->matricule}.pdf");
    }

    /**
     * Vérifie le cloisonnement multi-établissement et extrait les filtres hiérarchiques & temporels
     * communs à l'ensemble des éditions PDF financières.
     *
     * @param  Request  $request  Requête HTTP entrante
     * @return array{
     *     etablissement: ?Etablissement,
     *     etablissement_id: int,
     *     annee: ?AnneeScolaire,
     *     date_debut: ?string,
     *     date_fin: ?string,
     *     section_id: ?int,
     *     cycle_id: ?int,
     *     niveau_id: ?int,
     *     classe_id: ?int,
     *     caisse_id: ?int,
     *     mode_paiement: ?string
     * }
     */
    /**
     * Valide et résout les filtres d'état PDF communs (période, établissement ou multi-campus global, structure pédagogique).
     *
     * @return array{
     *     etablissement: ?Etablissement,
     *     etablissement_id: ?int,
     *     is_global: bool,
     *     annee: ?AnneeScolaire,
     *     date_debut: ?string,
     *     date_fin: ?string,
     *     section_id: ?int,
     *     cycle_id: ?int,
     *     niveau_id: ?int,
     *     classe_id: ?int,
     *     caisse_id: ?int,
     *     mode_paiement: ?string,
     *     categorie: ?string,
     *     eleve_id: ?int
     * }
     */
    private function resoudreFiltresEtatsPdf(Request $request): array
    {
        $request->validate([
            'etablissement_id' => 'nullable',
            'annee_scolaire_id' => 'nullable|integer|exists:annees_scolaires,id',
            'date_debut' => 'nullable|date',
            'date_fin' => 'nullable|date',
            'section_id' => 'nullable|integer|exists:sections,id',
            'cycle_id' => 'nullable|integer|exists:cycles,id',
            'niveau_id' => 'nullable|integer|exists:niveaux,id',
            'classe_id' => 'nullable|integer|exists:classes,id',
            'caisse_id' => 'nullable|integer|exists:caisses,id',
            'mode_paiement' => 'nullable|in:especes,cheque,virement,mobile_money_manuel',
            'categorie' => 'nullable|string|max:100',
            'eleve_id' => 'nullable|integer|exists:eleves,id',
            'categorie_frais_id' => 'nullable|integer|exists:categories_frais,id',
            'type_frais_id' => 'nullable|integer|exists:types_frais,id',
            'mode_filtre_frais' => 'nullable|in:tous,solde,uniquement_palier,partiel,non_paye',
        ]);

        $context = $this->resolveEtablissementContext($request);
        $rawEtabId = $request->input('etablissement_id');
        $isSuperAdmin = (bool) $context['isSuperAdmin'];
        $canAccessAll = (bool) ($context['canAccessAll'] ?? $isSuperAdmin);
        $isGlobal = ($rawEtabId === 'all' || empty($rawEtabId) && $canAccessAll) && $canAccessAll;

        if (
            $request->filled('etablissement_id')
            && $rawEtabId !== 'all'
            && ! $canAccessAll
            && $request->integer('etablissement_id') !== (int) $context['etablissementId']
        ) {
            abort(403, "Accès refusé : vous n'êtes pas autorisé à consulter les états financiers d'un autre établissement.");
        }

        $etablissement = $isGlobal ? null : ($request->filled('etablissement_id') && $rawEtabId !== 'all' ? Etablissement::find((int) $rawEtabId) : $context['selectedEtablissement']);
        $etablissementId = $isGlobal ? null : (int) ($etablissement?->id ?? 0);

        $annee = $request->filled('annee_scolaire_id')
            ? AnneeScolaire::find($request->integer('annee_scolaire_id'))
            : (AnneeScolaire::where('is_current', true)->first() ?? AnneeScolaire::latest()->first());

        return [
            'etablissement' => $etablissement,
            'etablissement_id' => $etablissementId,
            'is_global' => $isGlobal,
            'annee' => $annee,
            'date_debut' => $request->filled('date_debut') ? (string) $request->input('date_debut') : null,
            'date_fin' => $request->filled('date_fin') ? (string) $request->input('date_fin') : null,
            'section_id' => $request->filled('section_id') ? $request->integer('section_id') : null,
            'cycle_id' => $request->filled('cycle_id') ? $request->integer('cycle_id') : null,
            'niveau_id' => $request->filled('niveau_id') ? $request->integer('niveau_id') : null,
            'classe_id' => $request->filled('classe_id') ? $request->integer('classe_id') : null,
            'caisse_id' => $request->filled('caisse_id') ? $request->integer('caisse_id') : null,
            'mode_paiement' => $request->filled('mode_paiement') ? (string) $request->input('mode_paiement') : null,
            'categorie' => $request->filled('categorie') ? (string) $request->input('categorie') : null,
            'eleve_id' => $request->filled('eleve_id') ? $request->integer('eleve_id') : null,
            'categorie_frais_id' => $request->filled('categorie_frais_id') ? $request->integer('categorie_frais_id') : null,
            'type_frais_id' => $request->filled('type_frais_id') ? $request->integer('type_frais_id') : null,
            'mode_filtre_frais' => $request->filled('mode_filtre_frais') ? (string) $request->input('mode_filtre_frais') : 'tous',
        ];
    }

    /**
     * 01. Génère le Journal des Versements (PDF A4 Paysage), conforme au document officiel du concurrent.
     */
    public function imprimerJournalVersements(Request $request, FinanceService $financeService): Response
    {
        abort_if(! $request->user()?->can('finances.etats|finances.manage') && ! $request->user()?->hasRole('super-admin'), 403);

        $f = $this->resoudreFiltresEtatsPdf($request);

        $data = $financeService->obtenirJournalDetaille(
            $f['etablissement_id'],
            $f['date_debut'],
            $f['date_fin'],
            $f['caisse_id'],
            $f['mode_paiement'],
            $f['section_id'],
            $f['cycle_id'],
            $f['niveau_id'],
            $f['classe_id'],
            $f['categorie_frais_id'],
            $f['type_frais_id']
        );

        $pdf = Pdf::loadView('finances.journal_versements', array_merge($data, [
            'etablissement' => $f['etablissement'],
            'annee' => $f['annee'],
        ]))->setPaper('a4', 'landscape');

        return $pdf->stream('Journal_Versements.pdf');
    }

    /**
     * Génère le Journal Détaillé des Encaissements (alias vers le Journal des Versements).
     */
    public function imprimerJournalDetaille(Request $request, FinanceService $financeService): Response
    {
        return $this->imprimerJournalVersements($request, $financeService);
    }

    /**
     * 02. Génère le Journal Résumé des Versements (PDF A4 Portrait), ventilé par Section / Niveau / Classe.
     */
    public function imprimerJournalResume(Request $request, FinanceService $financeService): Response
    {
        abort_if(! $request->user()?->can('finances.etats|finances.manage') && ! $request->user()?->hasRole('super-admin'), 403);

        $f = $this->resoudreFiltresEtatsPdf($request);

        $data = $financeService->obtenirJournalResume(
            $f['etablissement_id'],
            $f['date_debut'],
            $f['date_fin'],
            $f['section_id'],
            $f['cycle_id'],
            $f['niveau_id'],
            $f['classe_id']
        );

        $pdf = Pdf::loadView('finances.journal_resume', array_merge($data, [
            'etablissement' => $f['etablissement'],
            'annee' => $f['annee'],
        ]))->setPaper('a4', 'portrait');

        return $pdf->stream('Journal_Resume_Versements.pdf');
    }

    /**
     * 03. Génère le Journal des Versements par Catégories (PDF A4 Paysage), avec sous-totaux et total en toutes lettres.
     */
    public function imprimerJournalParCategories(Request $request, FinanceService $financeService): Response
    {
        abort_if(! $request->user()?->can('finances.etats|finances.manage') && ! $request->user()?->hasRole('super-admin'), 403);

        $f = $this->resoudreFiltresEtatsPdf($request);

        $data = $financeService->obtenirJournalParCategories(
            $f['etablissement_id'],
            $f['date_debut'],
            $f['date_fin'],
            $f['caisse_id'],
            $f['mode_paiement'],
            $f['section_id'],
            $f['cycle_id'],
            $f['niveau_id'],
            $f['classe_id'],
            $f['categorie_frais_id'],
            $f['type_frais_id']
        );

        $pdf = Pdf::loadView('finances.journal_categories', array_merge($data, [
            'etablissement' => $f['etablissement'],
            'annee' => $f['annee'],
        ]))->setPaper('a4', 'landscape');

        return $pdf->stream('Journal_Versements_Par_Categories.pdf');
    }

    /**
     * 04. Génère le Bilan Résumé des Recouvrements (PDF A4 Paysage), avec effectifs et taux de recouvrement.
     */
    public function imprimerBilanResumeRecouvrement(Request $request, FinanceService $financeService): Response
    {
        abort_if(! $request->user()?->can('finances.etats|finances.manage') && ! $request->user()?->hasRole('super-admin'), 403);

        $f = $this->resoudreFiltresEtatsPdf($request);

        $data = $financeService->obtenirBilanResumeRecouvrement(
            $f['etablissement_id'],
            $f['annee']?->id ?? 0,
            $f['section_id'],
            $f['cycle_id'],
            $f['niveau_id'],
            $f['classe_id']
        );

        $pdf = Pdf::loadView('finances.bilan_resume', array_merge($data, [
            'etablissement' => $f['etablissement'],
            'annee' => $f['annee'],
        ]))->setPaper('a4', 'landscape');

        return $pdf->stream('Bilan_Resume_Recouvrement.pdf');
    }

    /**
     * 05. Génère le Bilan Détaillé des Versements (PDF A4 Paysage), nominatif par élève et par classe.
     *
     * Transmet les filtres de périmètre (établissement, année scolaire, section, cycle, niveau, classe)
     * dans l'ordre attendu par `FinanceService::obtenirBilanDetailleVersements()`.
     *
     * @param  Request  $request  Requête HTTP portant les paramètres de filtrage de l'état
     * @param  FinanceService  $financeService  Service de calcul et d'extraction financière
     * @return Response Document PDF en flux HTTP
     */
    public function imprimerBilanDetailleVersements(Request $request, FinanceService $financeService): Response
    {
        abort_if(! $request->user()?->can('finances.etats|finances.manage') && ! $request->user()?->hasRole('super-admin'), 403);

        $f = $this->resoudreFiltresEtatsPdf($request);

        // L'état cumulatif annuel filtre par périmètre hiérarchique (section -> cycle -> niveau -> classe).
        $data = $financeService->obtenirBilanDetailleVersements(
            $f['etablissement_id'],
            $f['annee']?->id ?? 0,
            $f['section_id'],
            $f['cycle_id'],
            $f['niveau_id'],
            $f['classe_id']
        );

        $pdf = Pdf::loadView('finances.bilan_detaille', array_merge($data, [
            'etablissement' => $f['etablissement'],
            'annee' => $f['annee'],
        ]))->setPaper('a4', 'landscape');

        return $pdf->stream('Bilan_Detaille_Versements.pdf');
    }

    /**
     * 06. Génère le Bilan des Réductions et Bourses Scolaires (PDF A4 Portrait), nominatif avec motifs.
     */
    public function imprimerBilanReductionsBourses(Request $request, FinanceService $financeService): Response
    {
        abort_if(! $request->user()?->can('finances.etats|finances.manage') && ! $request->user()?->hasRole('super-admin'), 403);

        $f = $this->resoudreFiltresEtatsPdf($request);

        $data = $financeService->obtenirBilanReductionsBourses(
            $f['etablissement_id'],
            $f['annee']?->id ?? 0,
            $f['section_id'],
            $f['cycle_id'],
            $f['niveau_id'],
            $f['classe_id']
        );

        $pdf = Pdf::loadView('finances.bilan_reductions', array_merge($data, [
            'etablissement' => $f['etablissement'],
            'annee' => $f['annee'],
        ]))->setPaper('a4', 'portrait');

        return $pdf->stream('Bilan_Reductions_Bourses.pdf');
    }

    /**
     * 07. Génère la Liste des Élèves Insolvables (PDF A4 Paysage), enrichie pour les relances officielles.
     */
    public function imprimerListeInsolvables(Request $request, FinanceService $financeService): Response
    {
        abort_if(! $request->user()?->can('finances.etats|finances.manage') && ! $request->user()?->hasRole('super-admin'), 403);

        $f = $this->resoudreFiltresEtatsPdf($request);

        if ($f['etablissement_id'] && $f['annee']) {
            $financeService->synchroniserComptesEtablissement($f['etablissement_id'], (int) $f['annee']->id);
        }

        $data = $financeService->obtenirListeInsolvables(
            $f['etablissement_id'],
            $f['annee']?->id ?? 0,
            $f['classe_id'],
            $f['niveau_id'],
            null,
            $f['section_id'],
            $f['cycle_id'],
            $f['date_debut'],
            $f['date_fin']
        );

        $pdf = Pdf::loadView('finances.liste_insolvables', array_merge($data, [
            'etablissement' => $f['etablissement'],
            'annee' => $f['annee'],
        ]))->setPaper('a4', 'landscape');

        return $pdf->stream('Liste_Eleves_Insolvables.pdf');
    }

    /**
     * 08. Génère le Relevé Individuel des Versements d'un Élève (PDF A4 Portrait), conforme au relevé individuel du concurrent.
     */
    public function imprimerReleveIndividuelEleve(Request $request, FinanceService $financeService): Response
    {
        abort_if(! $request->user()?->can('finances.etats|finances.manage') && ! $request->user()?->hasRole('super-admin'), 403);

        $f = $this->resoudreFiltresEtatsPdf($request);

        $eleveId = $f['eleve_id'];
        if (! $eleveId) {
            abort(400, 'Veuillez sélectionner un élève pour imprimer son relevé individuel de versements.');
        }

        $data = $financeService->obtenirReleveIndividuelEleve($eleveId, $f['annee']?->id ?? 0);

        $pdf = Pdf::loadView('finances.releve_eleve', array_merge($data, [
            'etablissement' => $f['etablissement'] ?? $data['etablissement'],
            'annee' => $f['annee'],
        ]))->setPaper('a4', 'portrait');

        return $pdf->stream('Releve_Versements_Eleve_'.$data['eleve']->matricule.'.pdf');
    }

    /**
     * Génère le Journal Synthétique de Caisse (PDF A4 Portrait), filtrable par période et hiérarchie scolaire.
     */
    public function imprimerJournalSynthetique(Request $request, FinanceService $financeService): Response
    {
        abort_if(! $request->user()?->can('finances.etats|finances.manage') && ! $request->user()?->hasRole('super-admin'), 403);

        $f = $this->resoudreFiltresEtatsPdf($request);

        $data = $financeService->obtenirJournalSynthetique(
            $f['etablissement_id'],
            $f['date_debut'],
            $f['date_fin'],
            $f['caisse_id'],
            $f['mode_paiement'],
            $f['section_id'],
            $f['cycle_id'],
            $f['niveau_id'],
            $f['classe_id']
        );

        $pdf = Pdf::loadView('finances.journal_synthetique', array_merge($data, [
            'etablissement' => $f['etablissement'],
            'annee' => $f['annee'],
        ]))->setPaper('a4', 'portrait');

        return $pdf->stream('Journal_Synthetique_Caisse.pdf');
    }

    /**
     * Génère la Liste des Élèves en Règle (PDF A4 Portrait), filtrable par période et hiérarchie scolaire.
     */
    public function imprimerListeEnRegle(Request $request, FinanceService $financeService): Response
    {
        abort_if(! $request->user()?->can('finances.etats|finances.manage') && ! $request->user()?->hasRole('super-admin'), 403);

        $f = $this->resoudreFiltresEtatsPdf($request);

        if ($f['etablissement_id'] && $f['annee']) {
            $financeService->synchroniserComptesEtablissement($f['etablissement_id'], (int) $f['annee']->id);
        }

        $data = $financeService->obtenirListeEnRegle(
            $f['etablissement_id'],
            $f['annee']?->id ?? 0,
            $f['classe_id'],
            $f['niveau_id'],
            $f['section_id'],
            $f['cycle_id'],
            $f['date_debut'],
            $f['date_fin']
        );

        $pdf = Pdf::loadView('finances.liste_en_regle', array_merge($data, [
            'etablissement' => $f['etablissement'],
            'annee' => $f['annee'],
        ]))->setPaper('a4', 'portrait');

        return $pdf->stream('Liste_Eleves_En_Regle.pdf');
    }

    /**
     * Génère l'État officiel des Recettes Diverses et Encaissements Libres (PDF A4 Paysage),
     * filtrable par période, caisse, mode de règlement et catégorie.
     */
    public function imprimerEtatRecettesDiverses(Request $request, FinanceService $financeService): Response
    {
        abort_if(! $request->user()?->can('finances.etats|finances.manage') && ! $request->user()?->hasRole('super-admin'), 403);

        $f = $this->resoudreFiltresEtatsPdf($request);

        $data = $financeService->obtenirEtatRecettesDiverses(
            $f['etablissement_id'],
            $f['date_debut'],
            $f['date_fin'],
            $f['caisse_id'],
            $f['mode_paiement'],
            $f['categorie']
        );

        $pdf = Pdf::loadView('finances.recettes_diverses', array_merge($data, [
            'etablissement' => $f['etablissement'],
            'annee' => $f['annee'],
        ]))->setPaper('a4', 'landscape');

        return $pdf->stream('Etat_Recettes_Diverses.pdf');
    }

    /**
     * 09. Génère l'État Nominatif de Suivi par Catégorie, Frais précis & Palier atteint (PDF A4 Paysage).
     *
     * Permet d'isoler tous les élèves concernés par une catégorie (ex: Scolarité, Frais d'Examen)
     * ou ayant payé un frais / palier précis (ex: uniquement l'Inscription, uniquement la 1ère Tranche).
     *
     * @param  Request  $request  Requête HTTP portant les filtres hiérarchiques et cibles de frais
     * @param  FinanceService  $financeService  Service de calcul financier
     * @return Response Document PDF en flux HTTP
     */
    public function imprimerSuiviParFrais(Request $request, FinanceService $financeService): Response
    {
        abort_if(! $request->user()?->can('finances.etats|finances.manage') && ! $request->user()?->hasRole('super-admin'), 403);

        $f = $this->resoudreFiltresEtatsPdf($request);

        if ($f['etablissement_id'] && $f['annee']) {
            $financeService->synchroniserComptesEtablissement($f['etablissement_id'], (int) $f['annee']->id);
        }

        $data = $financeService->obtenirSuiviParFraisOuCategorie(
            $f['etablissement_id'],
            $f['annee']?->id ?? 0,
            $f['categorie_frais_id'],
            $f['type_frais_id'],
            $f['mode_filtre_frais'],
            $f['section_id'],
            $f['cycle_id'],
            $f['niveau_id'],
            $f['classe_id']
        );

        $pdf = Pdf::loadView('finances.suivi_frais', array_merge($data, [
            'etablissement' => $f['etablissement'],
            'annee' => $f['annee'],
        ]))->setPaper('a4', 'landscape');

        return $pdf->stream('Suivi_Frais_Palier.pdf');
    }
}
