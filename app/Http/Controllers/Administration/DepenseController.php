<?php

namespace App\Http\Controllers\Administration;

use App\Concerns\ScopesEtablissement;
use App\Http\Controllers\Controller;
use App\Models\AnneeScolaire;
use App\Models\Caisse;
use App\Models\CategorieDepense;
use App\Models\Depense;
use App\Models\Etablissement;
use App\Models\Institution;
use App\Models\ReglementDivers;
use App\Models\User;
use App\Models\VersementCaisse;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Contrôleur de gestion des Dépenses, Charges & Fiches de Décharge (Phase 1).
 *
 * Gère le volet décaissements et équilibre financier (Aurore & SAGEES) :
 * - Enregistrement et catégorisation budgétaire des dépenses d'établissement.
 * - Génération certifiée des Fiches de Décharge / Bons de décaissement de caisse.
 * - Bilan synthétique Recettes vs Dépenses (Solde net d'exploitation).
 */
class DepenseController extends Controller implements HasMiddleware
{
    use ScopesEtablissement;

    /**
     * Définition de la matrice des middlewares d'autorisation pour les Dépenses & Décaissements.
     *
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:depenses.view|finances.view', only: ['index', 'imprimerDecharge']),
            new Middleware('can:depenses.create|finances.manage', only: ['store']),
            new Middleware('can:depenses.edit|finances.manage', only: ['update']),
            new Middleware('can:depenses.delete|finances.manage', only: ['destroy']),
            new Middleware('can:categories_depenses.manage|finances.manage', only: ['storeCategorie']),
            new Middleware('can:depenses.bilan|finances.etats|depenses.view|finances.view', only: ['imprimerBilan']),
        ];
    }

    /**
     * Affiche l'écran de gestion des dépenses et du bilan entrées/sorties.
     *
     * @param  Request  $request  Requête HTTP avec filtres optionnels.
     * @return Response Rendu Inertia avec statistiques et liste des dépenses.
     */
    public function index(Request $request): Response
    {
        /** @var User|null $user */
        $user = Auth::user();

        // Résolution hermétique du contexte établissement (Multi-Tenant)
        $context = $this->resolveEtablissementContext($request, ['institution']);
        $etablissements = $context['etablissements'];
        $selectedEtablissement = $context['selectedEtablissement'];
        $selectedEtabId = (int) ($selectedEtablissement?->id ?? 0);
        $isSuperAdmin = $context['isSuperAdmin'];

        $anneeActive = AnneeScolaire::where('is_current', true)->first() ?? AnneeScolaire::latest()->first();
        $selectedAnneeId = (int) $request->input('annee_scolaire_id', $anneeActive?->id);

        $caisseId = $request->input('caisse_id');
        $categorieId = $request->input('categorie_depense_id');
        $statut = $request->input('statut');
        $search = $request->input('search');

        // Récupération des dépenses filtrées
        $depensesQuery = Depense::with([
            'caisse',
            'categorieDepense',
            'auteur',
        ])
            ->where('etablissement_id', $selectedEtabId)
            ->where('annee_scolaire_id', $selectedAnneeId);

        if ($caisseId) {
            $depensesQuery->where('caisse_id', $caisseId);
        }

        if ($categorieId) {
            $depensesQuery->where('categorie_depense_id', $categorieId);
        }

        if ($statut) {
            $depensesQuery->where('statut', $statut);
        }

        if ($search) {
            $depensesQuery->where(function ($q) use ($search) {
                $q->where('numero_piece', 'like', "%{$search}%")
                    ->orWhere('beneficiaire', 'like', "%{$search}%")
                    ->orWhere('motif', 'like', "%{$search}%");
            });
        }

        $depenses = $depensesQuery->latest('date_depense')->get();

        // Calcul des totaux Recettes vs Dépenses
        $totalDepenses = (float) Depense::where('etablissement_id', $selectedEtabId)
            ->where('annee_scolaire_id', $selectedAnneeId)
            ->where('statut', 'validee')
            ->sum('montant');

        // Somme des versements de caisse scolaires (colonne 'montant_total')
        $versementsQuery = VersementCaisse::where('etablissement_id', $selectedEtabId)
            ->where('statut', 'valide');

        if ($selectedAnneeId) {
            $versementsQuery->whereHas('inscription', function ($q) use ($selectedAnneeId) {
                $q->where('annee_scolaire_id', $selectedAnneeId);
            });
        }

        $totalVersements = (float) $versementsQuery->sum('montant_total');

        // Somme des règlements divers hors scolarité (colonne 'montant')
        $totalDivers = (float) ReglementDivers::where('etablissement_id', $selectedEtabId)
            ->where('statut', 'valide')
            ->sum('montant');

        $totalRecettes = $totalVersements + $totalDivers;
        $soldeNet = $totalRecettes - $totalDepenses;

        $stats = [
            'total_depenses' => $totalDepenses,
            'nb_depenses' => $depenses->count(),
            'total_recettes' => $totalRecettes,
            'total_versements' => $totalVersements,
            'total_divers' => $totalDivers,
            'solde_net' => $soldeNet,
        ];

        // Référentiels
        $categories = CategorieDepense::where(function ($q) use ($selectedEtabId) {
            $q->whereNull('etablissement_id')->orWhere('etablissement_id', $selectedEtabId);
        })->where('is_active', true)->orderBy('nom')->get();

        $caisses = Caisse::where('etablissement_id', $selectedEtabId)->where('is_active', true)->get();

        return Inertia::render('administration/finances/depenses/index', [
            'depenses' => $depenses,
            'categories' => $categories,
            'caisses' => $caisses,
            'stats' => $stats,
            'etablissements' => $etablissements,
            'selectedEtabId' => $selectedEtabId,
            'selectedAnneeId' => $selectedAnneeId,
            // Transmettre canAccessAll afin d'exposer le sélecteur/bilan consolidé multi-campus aux profils globaux (ex: Secrétaire)
            'isSuperAdmin' => (bool) ($context['canAccessAll'] ?? $isSuperAdmin),
            'anneesScolaires' => AnneeScolaire::orderByDesc('is_current')->orderByDesc('date_debut')->get(),
            'filters' => [
                'etablissement_id' => (string) $selectedEtabId,
                'annee_scolaire_id' => (string) $selectedAnneeId,
                'caisse_id' => (string) $caisseId,
                'categorie_depense_id' => (string) $categorieId,
                'statut' => (string) $statut,
                'search' => $search ?: '',
            ],
            'can' => [
                'view' => $isSuperAdmin || $user?->can('depenses.view') || $user?->can('finances.view'),
                'create' => $isSuperAdmin || $user?->can('depenses.create') || $user?->can('finances.manage'),
                'edit' => $isSuperAdmin || $user?->can('depenses.edit') || $user?->can('finances.manage'),
                'delete' => $isSuperAdmin || $user?->can('depenses.delete'),
                'bilan' => $isSuperAdmin || $user?->can('depenses.bilan') || $user?->can('finances.etats') || $user?->can('finances.view'),
                'categories' => $isSuperAdmin || $user?->can('categories_depenses.manage') || $user?->can('finances.manage'),
            ],
        ]);
    }

    /**
     * Enregistre une nouvelle dépense de caisse.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'etablissement_id' => ['required', 'exists:etablissements,id'],
            'annee_scolaire_id' => ['required', 'exists:annees_scolaires,id'],
            'caisse_id' => ['required', 'exists:caisses,id'],
            'categorie_depense_id' => ['required', 'exists:categories_depenses,id'],
            'beneficiaire' => ['required', 'string', 'max:150'],
            'montant' => ['required', 'numeric', 'min:1'],
            'date_depense' => ['required', 'date'],
            'motif' => ['required', 'string', 'max:1000'],
            'mode_reglement' => ['required', 'string', 'in:especes,virement,cheque,orange_money,mtn_momo'],
            'reference_paiement' => ['nullable', 'string', 'max:100'],
            'statut' => ['nullable', 'string', 'in:validee,en_attente,annulee'],
        ]);

        $user = Auth::user();
        if ($user && ! $user->canAccessAllEtablissements() && ($effectiveEtabId = $user->getEffectiveEtablissementId())) {
            if ((int) $validated['etablissement_id'] !== (int) $effectiveEtabId) {
                abort(403, 'Accès non autorisé : vous ne pouvez pas créer une dépense sur un autre établissement.');
            }
        }

        // Vérification d'intégrité multi-campus : la caisse et la catégorie doivent appartenir au même établissement
        $caisse = Caisse::withoutGlobalScopes()->findOrFail($validated['caisse_id']);
        if ((int) $caisse->etablissement_id !== (int) $validated['etablissement_id']) {
            abort(403, 'Accès non autorisé : la caisse sélectionnée n\'appartient pas à cet établissement.');
        }

        $categorie = CategorieDepense::withoutGlobalScopes()->findOrFail($validated['categorie_depense_id']);
        if ($categorie->etablissement_id !== null && (int) $categorie->etablissement_id !== (int) $validated['etablissement_id']) {
            abort(403, 'Accès non autorisé : la catégorie budgétaire n\'appartient pas à cet établissement.');
        }

        $validated['numero_piece'] = Depense::genererNumeroPiece($validated['etablissement_id']);
        $validated['statut'] = $validated['statut'] ?? 'validee';
        $validated['created_by'] = Auth::id();
        $validated['valide_par'] = Auth::id();

        Depense::create($validated);

        return back()->with('success', "Dépense {$validated['numero_piece']} enregistrée avec succès.");
    }

    /**
     * Met à jour une dépense existante.
     */
    public function update(Request $request, Depense $depense): RedirectResponse
    {
        $validated = $request->validate([
            'caisse_id' => ['required', 'exists:caisses,id'],
            'categorie_depense_id' => ['required', 'exists:categories_depenses,id'],
            'beneficiaire' => ['required', 'string', 'max:150'],
            'montant' => ['required', 'numeric', 'min:1'],
            'date_depense' => ['required', 'date'],
            'motif' => ['required', 'string', 'max:1000'],
            'mode_reglement' => ['required', 'string', 'in:especes,virement,cheque,orange_money,mtn_momo'],
            'reference_paiement' => ['nullable', 'string', 'max:100'],
            'statut' => ['required', 'string', 'in:validee,en_attente,annulee'],
        ]);

        // Vérification que la caisse et la catégorie rattachées appartiennent bien à l'établissement de la dépense
        $caisse = Caisse::withoutGlobalScopes()->findOrFail($validated['caisse_id']);
        if ((int) $caisse->etablissement_id !== (int) $depense->etablissement_id) {
            abort(403, 'Accès non autorisé : la caisse sélectionnée n\'appartient pas à l\'établissement de cette dépense.');
        }

        $categorie = CategorieDepense::withoutGlobalScopes()->findOrFail($validated['categorie_depense_id']);
        if ($categorie->etablissement_id !== null && (int) $categorie->etablissement_id !== (int) $depense->etablissement_id) {
            abort(403, 'Accès non autorisé : la catégorie budgétaire n\'appartient pas à l\'établissement de cette dépense.');
        }

        $depense->update($validated);

        return back()->with('success', 'Dépense mise à jour avec succès.');
    }

    /**
     * Supprime une dépense.
     */
    public function destroy(Depense $depense): RedirectResponse
    {
        $depense->delete();

        return back()->with('success', 'Dépense supprimée.');
    }

    /**
     * Crée une nouvelle catégorie de dépense.
     */
    public function storeCategorie(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'etablissement_id' => ['nullable', 'exists:etablissements,id'],
            'code' => ['required', 'string', 'max:50'],
            'nom' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
            'couleur' => ['required', 'string', 'max:25'],
        ]);

        $user = Auth::user();
        if ($user && ! $user->canAccessAllEtablissements() && ($effectiveEtabId = $user->getEffectiveEtablissementId())) {
            if (! empty($validated['etablissement_id']) && (int) $validated['etablissement_id'] !== (int) $effectiveEtabId) {
                abort(403, 'Accès non autorisé : vous ne pouvez pas créer une catégorie sur un autre établissement.');
            }
            $validated['etablissement_id'] = (int) $effectiveEtabId;
        }

        CategorieDepense::create($validated);

        return back()->with('success', 'Catégorie budgétaire créée avec succès.');
    }

    /**
     * Génère la Fiche de Décharge / Bon de Décaissement de Caisse en PDF au format A4 portrait.
     *
     * @param  Depense  $depense  Dépense concernée par la décharge
     * @return HttpResponse Réponse PDF en ligne
     */
    public function imprimerDecharge(Depense $depense): HttpResponse
    {
        $depense->load([
            'etablissement.institution',
            'anneeScolaire',
            'caisse',
            'categorieDepense',
            'auteur',
        ]);

        $etablissement = $depense->etablissement;

        $pdf = Pdf::loadView('finances.fiche_decharge', [
            'etablissement' => $etablissement,
            'institution' => $etablissement->institution,
            'depense' => $depense,
            'date_impression' => now()->format('d/m/Y'),
        ])->setPaper('a4', 'portrait');

        $cleanRef = preg_replace('/[^A-Za-z0-9]/', '_', $depense->numero_piece);
        $filename = "DECHARGE_{$cleanRef}.pdf";

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Génère le Bilan Synthétique Entrées / Sorties au format PDF, filtrable par période (date_debut / date_fin).
     *
     * @param  Request  $request  Requête HTTP contenant l'établissement, l'année scolaire et la période optionnelle.
     * @return HttpResponse Fichier PDF du bilan financier.
     */
    public function imprimerBilan(Request $request): HttpResponse
    {
        // Validation assouplie : support du filtre global 'all' pour les super-administrateurs / profils transversaux
        $rawEtabId = $request->input('etablissement_id');
        $context = $this->resolveEtablissementContext($request);
        $canAccessAll = (bool) ($context['canAccessAll'] ?? $context['isSuperAdmin']);
        $isGlobal = ($rawEtabId === 'all' || (empty($rawEtabId) && $canAccessAll)) && $canAccessAll;

        if (! $isGlobal) {
            $request->validate([
                'etablissement_id' => 'nullable|integer|exists:etablissements,id',
            ]);
        }

        $request->validate([
            'annee_scolaire_id' => 'nullable|integer|exists:annees_scolaires,id',
            'date_debut' => 'nullable|date',
            'date_fin' => 'nullable|date',
        ]);

        // Cloisonnement SaaS Multi-Tenant ou Agrégation Globale Complexe Scolaire
        if ($isGlobal) {
            $etablissementId = null;
            $etablissement = null;
            $institution = Institution::first();
        } else {
            $requestedEtabId = $request->filled('etablissement_id') && $request->input('etablissement_id') !== 'all'
                ? (int) $request->input('etablissement_id')
                : null;
            if ($requestedEtabId !== null && ! $canAccessAll && $requestedEtabId !== (int) $context['etablissementId']) {
                abort(403, 'Accès non autorisé au bilan de cet établissement.');
            }

            $etablissementId = (int) ($requestedEtabId ?? $context['etablissementId'] ?? 0);
            $etablissement = Etablissement::with('institution')->findOrFail($etablissementId);
            $institution = $etablissement->institution;
        }

        $annee = $request->filled('annee_scolaire_id')
            ? AnneeScolaire::findOrFail((int) $request->input('annee_scolaire_id'))
            : (AnneeScolaire::where('is_current', true)->first() ?? AnneeScolaire::latest()->firstOrFail());
        $anneeId = (int) $annee->id;

        $debut = $request->filled('date_debut') ? Carbon::parse((string) $request->input('date_debut'))->startOfDay() : null;
        $fin = $request->filled('date_fin') ? Carbon::parse((string) $request->input('date_fin'))->endOfDay() : null;
        if ($debut && $fin && $debut->gt($fin)) {
            [$debut, $fin] = [$fin->copy()->startOfDay(), $debut->copy()->endOfDay()];
        }

        // Recettes scolaires (VersementCaisse montant_total)
        $versementsQuery = VersementCaisse::where('statut', 'valide');
        if (! $isGlobal && $etablissementId !== null) {
            $versementsQuery->where('etablissement_id', $etablissementId);
        }

        if ($anneeId) {
            $versementsQuery->whereHas('inscription', function ($q) use ($anneeId) {
                $q->where('annee_scolaire_id', $anneeId);
            });
        }
        if ($debut) {
            $versementsQuery->where('date_versement', '>=', $debut);
        }
        if ($fin) {
            $versementsQuery->where('date_versement', '<=', $fin);
        }

        $versements = (float) $versementsQuery->sum('montant_total');

        // Recettes diverses hors scolarité (ReglementDivers montant)
        $diversQuery = ReglementDivers::where('statut', 'valide');
        if (! $isGlobal && $etablissementId !== null) {
            $diversQuery->where('etablissement_id', $etablissementId);
        }
        if ($debut) {
            $diversQuery->where('date_reglement', '>=', $debut);
        }
        if ($fin) {
            $diversQuery->where('date_reglement', '<=', $fin);
        }
        $divers = (float) $diversQuery->sum('montant');

        $totalRecettes = $versements + $divers;

        // Dépenses groupées par catégorie
        $categoriesDepenses = CategorieDepense::with(['depenses' => function ($q) use ($isGlobal, $etablissementId, $anneeId, $debut, $fin) {
            if (! $isGlobal && $etablissementId !== null) {
                $q->where('etablissement_id', $etablissementId);
            }
            $q->where('annee_scolaire_id', $anneeId)
                ->where('statut', 'validee');
            if ($debut) {
                $q->whereDate('date_depense', '>=', $debut->toDateString());
            }
            if ($fin) {
                $q->whereDate('date_depense', '<=', $fin->toDateString());
            }
        }])->get();

        $totalDepenses = 0;
        $lignesCategories = [];

        foreach ($categoriesDepenses as $cat) {
            $somme = $cat->depenses->sum('montant');
            $totalDepenses += $somme;
            if ($somme > 0) {
                $lignesCategories[] = [
                    'nom' => $cat->nom,
                    'code' => $cat->code,
                    'montant' => $somme,
                ];
            }
        }

        $soldeNet = $totalRecettes - $totalDepenses;

        $pdf = Pdf::loadView('finances.bilan_entrees_sorties', [
            'etablissement' => $etablissement,
            'institution' => $institution,
            'is_global' => $isGlobal,
            'annee' => $annee,
            'date_debut' => $debut?->format('d-m-Y'),
            'date_fin' => $fin?->format('d-m-Y'),
            'versements' => $versements,
            'divers' => $divers,
            'totalRecettes' => $totalRecettes,
            'lignesCategories' => $lignesCategories,
            'totalDepenses' => $totalDepenses,
            'soldeNet' => $soldeNet,
            'date_generation' => now()->format('d-m-Y'),
        ])->setPaper('a4', 'portrait');

        $filename = "BILAN_FINANCIER_{$annee->code}.pdf";

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$filename}\"",
        ]);
    }
}
