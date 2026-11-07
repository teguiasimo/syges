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
                'imprimerJournalDetaille',
                'imprimerJournalSynthetique',
                'imprimerListeInsolvables',
                'imprimerListeEnRegle',
            ]),
            new Middleware('can:versements.create|finances.manage', only: [
                'storeVersement',
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
            new Middleware('can:frais.configure|echeances.manage|finances.manage', only: [
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
            'frais_configure' => $canManageAll || (bool) $user?->can('frais.configure') || (bool) $user?->can('echeances.manage'),
            'versements_create' => $canManageAll || (bool) $user?->can('versements.create'),
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

        // 13. Frais scolaires optionnels paramétrés pour l'année en cours (bus, cantine, activités...)
        $fraisOptionnels = FraisScolaire::with(['typeFrais.categorieFrais', 'niveau'])
            ->where('etablissement_id', $etablissementId)
            ->where('annee_scolaire_id', $anneeId)
            ->whereHas('typeFrais', fn ($q) => $q->where('obligatoire', false))
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
            'isSuperAdmin' => $context['isSuperAdmin'],
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
            ->whereHas('typeFrais', fn ($q) => $q->where('obligatoire', false))
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
            'isSuperAdmin' => $context['isSuperAdmin'],
            'anneesScolaires' => AnneeScolaire::orderBy('code', 'desc')->get(),
            'currentAnnee' => $currentAnnee,
            'filters' => $request->only(['etablissement_id', 'annee_scolaire_id', 'classe_id', 'compte_id']),
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
     * Génère et télécharge le reçu officiel PDF d'un versement avec son QR code de sécurité.
     */
    public function imprimerRecu(VersementCaisse $versement, QrCodeService $qrCodeService): Response
    {
        abort_if(! request()->user()?->can('finances.view|finances.manage') && ! request()->user()?->hasRole('super-admin'), 403);

        $versement->load([
            'ventilations',
            'compteFinancier.eleve',
            'compteFinancier.anneeScolaire',
            'inscription.classe',
            'etablissement',
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

        $qrCodeBase64 = $qrCodeService->generateBase64Svg($qrData, 90);

        $pdf = Pdf::loadView('finances.recu_caisse', compact('versement', 'compte', 'eleve', 'inscription', 'etablissement', 'qrCodeBase64'))
            ->setPaper('a5', 'landscape');

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
        $etabRestriction = $context['isSuperAdmin'] ? null : ($context['etablissementId'] ? (int) $context['etablissementId'] : null);

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
     * Supprime une Catégorie de Frais uniquement si aucun frais du catalogue ne lui est rattaché.
     */
    public function destroyCategorieFrais(CategorieFrais $categorieFrais): RedirectResponse
    {
        $nom = $categorieFrais->nom;
        $nbFraisLies = TypeFrais::withoutGlobalScopes()
            ->where('categorie_frais_id', $categorieFrais->id)
            ->count();

        if ($nbFraisLies > 0) {
            $message = "Suppression impossible : la catégorie « {$nom} » est déjà utilisée par {$nbFraisLies} frais dans le catalogue.";

            return back()->with('error', $message)->withErrors(['error' => $message]);
        }

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
            'obligatoire' => 'required|boolean',
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
            'obligatoire' => 'required|boolean',
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
            'obligatoire' => (bool) $validated['obligatoire'],
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

        if (VentilationVersement::where('type_frais_id', $typeFrais->id)->exists()) {
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
        ]);

        $anneeId = (int) $validated['annee_scolaire_id'];
        $etabId = (int) $validated['etablissement_id'];
        $serie = isset($validated['serie']) && trim((string) $validated['serie']) !== ''
            ? trim((string) $validated['serie'])
            : null;

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

        DB::transaction(function () use ($typesSelectionnes, $niveauxValidesIds, $anneeId, $etabId, $serie, $financeService, &$nbAffectations) {
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
            'date_limite' => 'nullable|date',
        ]);

        if (isset($validated['serie']) && trim((string) $validated['serie']) === '') {
            $validated['serie'] = null;
        }
        if (array_key_exists('ordre_priorite', $validated) && ($validated['ordre_priorite'] === '' || $validated['ordre_priorite'] === null)) {
            $validated['ordre_priorite'] = null;
        }

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
            'date_limite' => 'nullable|date',
        ]);

        if (isset($validated['serie']) && trim((string) $validated['serie']) === '') {
            $validated['serie'] = null;
        }
        if (array_key_exists('ordre_priorite', $validated) && ($validated['ordre_priorite'] === '' || $validated['ordre_priorite'] === null)) {
            $validated['ordre_priorite'] = null;
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
        $nbVentilations = VentilationVersement::where('frais_scolaire_id', $fraisScolaire->id)->count();
        if ($nbVentilations > 0) {
            $blockers[] = "{$nbVentilations} versement(s) ventilé(s)";
        }

        $nbDispenses = DispenseFinanciere::withoutGlobalScopes()
            ->where('frais_scolaire_id', $fraisScolaire->id)
            ->count();
        if ($nbDispenses > 0) {
            $blockers[] = "{$nbDispenses} dispense(s) accordée(s)";
        }

        $nbSouscriptions = SouscriptionFraisOptionnel::where('frais_scolaire_id', $fraisScolaire->id)->count();
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
            'libelle' => 'required|string|max:255',
            'montant' => 'required|numeric|min:1',
            'mode_paiement' => 'required|in:especes,cheque,virement,mobile_money_manuel',
            'reference_paiement' => 'nullable|string|max:100',
            'personnel_id' => 'nullable|exists:personnels,id',
            'observations' => 'nullable|string|max:500',
        ]);

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
     * Imprime le reçu d'un règlement divers avec QR code.
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
            ->setPaper('a5', 'landscape');

        return $pdf->stream("Recu_Divers_{$reglement->reference_recu}.pdf");
    }

    /**
     * Crée un modèle de bourse ou de remise avec sa tranche cible d'imputation par défaut.
     *
     * Génère automatiquement le code interne si non renseigné et accepte les catégories personnalisées
     * telles que « Paiement en avance (avant rentrée) ».
     */
    public function storeBourseRemise(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'etablissement_id' => 'required|exists:etablissements,id',
            'annee_scolaire_id' => 'nullable|exists:annees_scolaires,id',
            'code' => 'nullable|string|max:50',
            'nom' => 'required|string|max:150',
            'type' => 'required|in:bourse,remise',
            'categorie' => 'required|string|max:100',
            'mode_calcul' => 'required|in:pourcentage,montant_fixe',
            'valeur' => 'required|numeric|min:0.01',
            'cible_imputation' => 'nullable|in:derniere_tranche,tranche_1,tranche_2,tranche_3,tranche_4,proportionnelle',
            'niveau_id' => 'nullable|exists:niveaux,id',
            'type_frais_id' => 'nullable|exists:types_frais,id',
            'description' => 'nullable|string|max:500',
            'is_active' => 'nullable|boolean',
        ]);

        if (empty($validated['code'])) {
            $prefix = $validated['type'] === 'bourse' ? 'BRS' : 'REM';
            $slug = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', Str::ascii($validated['nom'])), 0, 6) ?: 'STD');
            $validated['code'] = "{$prefix}-{$slug}-".random_int(100, 999);
        }

        if (empty($validated['cible_imputation'])) {
            $validated['cible_imputation'] = 'derniere_tranche';
        }

        BourseRemise::create($validated);

        return back()->with('success', "Modèle de {$validated['type']} « {$validated['nom']} » créé avec succès.");
    }

    /**
     * Met à jour une bourse ou remise et sa règle d'imputation.
     */
    public function updateBourseRemise(Request $request, BourseRemise $bourseRemise): RedirectResponse
    {
        $validated = $request->validate([
            'code' => 'nullable|string|max:50',
            'nom' => 'required|string|max:150',
            'type' => 'required|in:bourse,remise',
            'categorie' => 'required|string|max:100',
            'mode_calcul' => 'required|in:pourcentage,montant_fixe',
            'valeur' => 'required|numeric|min:0.01',
            'cible_imputation' => 'nullable|in:derniere_tranche,tranche_1,tranche_2,tranche_3,tranche_4,proportionnelle',
            'niveau_id' => 'nullable|exists:niveaux,id',
            'type_frais_id' => 'nullable|exists:types_frais,id',
            'description' => 'nullable|string|max:500',
            'is_active' => 'nullable|boolean',
        ]);

        if (empty($validated['code'])) {
            $validated['code'] = $bourseRemise->code;
        }
        if (empty($validated['cible_imputation'])) {
            $validated['cible_imputation'] = $bourseRemise->cible_imputation ?: 'derniere_tranche';
        }

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
     * Retire une bourse ou remise accordée à un élève.
     */
    public function retirerBourseRemise(EleveBourseRemise $attribution, FinanceService $financeService): RedirectResponse
    {
        $financeService->retirerBourseRemise($attribution);

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
     * Retire une dispense financière accordée sur une tranche et réactualise immédiatement le compte élève.
     */
    public function destroyDispenseFinanciere(DispenseFinanciere $dispense, FinanceService $financeService): RedirectResponse
    {
        $financeService->retirerDispenseFinanciere($dispense);

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
     * Annule une souscription à un frais optionnel.
     */
    public function destroySouscriptionOptionnelle(SouscriptionFraisOptionnel $souscription, FinanceService $financeService): RedirectResponse
    {
        abort_if(! request()->user()?->can('options_financieres.manage|finances.manage') && ! request()->user()?->hasRole('super-admin'), 403);

        $financeService->annulerSouscriptionOptionnelle($souscription);

        return back()->with('success', 'Souscription au service optionnel annulée avec succès. Le compte élève a été réactualisé.');
    }

    /**
     * Retourne l'échéancier prévisionnel complet et le dossier financier d'un élève (API JSON).
     */
    public function dossierFinancierEleve(CompteFinancierEleve $compte, FinanceService $financeService): JsonResponse
    {
        abort_if(! request()->user()?->can('finances.view|finances.manage') && ! request()->user()?->hasRole('super-admin'), 403);

        $donnees = $financeService->obtenirEcheancierEleve($compte);

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
    private function resoudreFiltresEtatsPdf(Request $request): array
    {
        $request->validate([
            'etablissement_id' => 'nullable|integer|exists:etablissements,id',
            'annee_scolaire_id' => 'nullable|integer|exists:annees_scolaires,id',
            'date_debut' => 'nullable|date',
            'date_fin' => 'nullable|date',
            'section_id' => 'nullable|integer|exists:sections,id',
            'cycle_id' => 'nullable|integer|exists:cycles,id',
            'niveau_id' => 'nullable|integer|exists:niveaux,id',
            'classe_id' => 'nullable|integer|exists:classes,id',
            'caisse_id' => 'nullable|integer|exists:caisses,id',
            'mode_paiement' => 'nullable|in:especes,cheque,virement,mobile_money_manuel',
        ]);

        $context = $this->resolveEtablissementContext($request);
        if (
            $request->filled('etablissement_id')
            && ! $context['isSuperAdmin']
            && $request->integer('etablissement_id') !== (int) $context['etablissementId']
        ) {
            abort(403, "Accès refusé : vous n'êtes pas autorisé à consulter les états financiers d'un autre établissement.");
        }

        $etablissement = $context['selectedEtablissement'];
        $etablissementId = (int) ($etablissement?->id ?? 0);

        $annee = $request->filled('annee_scolaire_id')
            ? AnneeScolaire::find($request->integer('annee_scolaire_id'))
            : (AnneeScolaire::where('is_current', true)->first() ?? AnneeScolaire::latest()->first());

        return [
            'etablissement' => $etablissement,
            'etablissement_id' => $etablissementId,
            'annee' => $annee,
            'date_debut' => $request->filled('date_debut') ? (string) $request->input('date_debut') : null,
            'date_fin' => $request->filled('date_fin') ? (string) $request->input('date_fin') : null,
            'section_id' => $request->filled('section_id') ? $request->integer('section_id') : null,
            'cycle_id' => $request->filled('cycle_id') ? $request->integer('cycle_id') : null,
            'niveau_id' => $request->filled('niveau_id') ? $request->integer('niveau_id') : null,
            'classe_id' => $request->filled('classe_id') ? $request->integer('classe_id') : null,
            'caisse_id' => $request->filled('caisse_id') ? $request->integer('caisse_id') : null,
            'mode_paiement' => $request->filled('mode_paiement') ? (string) $request->input('mode_paiement') : null,
        ];
    }

    /**
     * Génère le Journal Détaillé des Encaissements (PDF A4 Paysage), filtrable par période et hiérarchie scolaire.
     */
    public function imprimerJournalDetaille(Request $request, FinanceService $financeService): Response
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
            $f['classe_id']
        );

        $pdf = Pdf::loadView('finances.journal_detaille', array_merge($data, [
            'etablissement' => $f['etablissement'],
            'annee' => $f['annee'],
        ]))->setPaper('a4', 'landscape');

        return $pdf->stream('Journal_Detaille_Encaissements.pdf');
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
     * Génère la Liste des Élèves Insolvables (PDF A4 Paysage), filtrable par période et hiérarchie scolaire.
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
}
