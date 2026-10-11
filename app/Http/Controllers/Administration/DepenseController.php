<?php

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use App\Models\AnneeScolaire;
use App\Models\Caisse;
use App\Models\CategorieDepense;
use App\Models\Depense;
use App\Models\Etablissement;
use App\Models\ReglementDivers;
use App\Models\VersementCaisse;
use Barryvdh\DomPDF\Facade\Pdf;
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
            new Middleware('can:depenses.bilan|depenses.view|finances.view', only: ['imprimerBilan']),
        ];
    }
    /**
     * Affiche l'écran de gestion des dépenses et du bilan entrées/sorties.
     *
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        $user = Auth::user();
        $isSuperAdmin = $user ? ($user->hasRole('super-admin') || $user->email === 'admin@gmail.com') : false;

        $etablissementsQuery = Etablissement::with('institution');

        if (! $isSuperAdmin && $user?->personnel?->etablissement_id) {
            $etablissementsQuery->where('id', $user->personnel->etablissement_id);
        }

        $etablissements = $etablissementsQuery->get();
        $selectedEtabId = (int) $request->input('etablissement_id', $etablissements->first()?->id);

        $anneeActive = AnneeScolaire::where('is_current', true)->first() ?? AnneeScolaire::latest()->first();
        $selectedAnneeId = (int) $request->input('annee_scolaire_id', $anneeActive?->id);

        $caisseId = $request->input('caisse_id');
        $categorieId = $request->input('categorie_depense_id');
        $statut = $request->input('statut');
        $search = $request->input('search');

        // Récupération des dépenses
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

        $totalVersements = (float) VersementCaisse::where('etablissement_id', $selectedEtabId)
            ->where('statut', 'valide')
            ->sum('montant');

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
                'bilan' => $isSuperAdmin || $user?->can('depenses.bilan') || $user?->can('finances.view'),
                'categories' => $isSuperAdmin || $user?->can('categories_depenses.manage') || $user?->can('finances.manage'),
            ],
        ]);
    }

    /**
     * Enregistre une nouvelle dépense de caisse.
     *
     * @param Request $request
     * @return RedirectResponse
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

        $validated['numero_piece'] = Depense::genererNumeroPiece($validated['etablissement_id']);
        $validated['statut'] = $validated['statut'] ?? 'validee';
        $validated['created_by'] = Auth::id();
        $validated['valide_par'] = Auth::id();

        Depense::create($validated);

        return back()->with('success', "Dépense {$validated['numero_piece']} enregistrée avec succès.");
    }

    /**
     * Met à jour une dépense existante.
     *
     * @param Request $request
     * @param Depense $depense
     * @return RedirectResponse
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

        $depense->update($validated);

        return back()->with('success', 'Dépense mise à jour avec succès.');
    }

    /**
     * Supprime une dépense.
     *
     * @param Depense $depense
     * @return RedirectResponse
     */
    public function destroy(Depense $depense): RedirectResponse
    {
        $depense->delete();

        return back()->with('success', 'Dépense supprimée.');
    }

    /**
     * Crée une nouvelle catégorie de dépense.
     *
     * @param Request $request
     * @return RedirectResponse
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

        CategorieDepense::create($validated);

        return back()->with('success', 'Catégorie budgétaire créée avec succès.');
    }

    /**
     * Génère la Fiche de Décharge / Bon de Décaissement de Caisse en PDF.
     *
     * @param Depense $depense
     * @return HttpResponse
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
        ])->setPaper('a5', 'landscape');

        $cleanRef = preg_replace('/[^A-Za-z0-9]/', '_', $depense->numero_piece);
        $filename = "DECHARGE_{$cleanRef}.pdf";

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Génère le Bilan Synthétique Entrées / Sorties au format PDF.
     *
     * @param Request $request
     * @return HttpResponse
     */
    public function imprimerBilan(Request $request): HttpResponse
    {
        $etablissementId = (int) $request->input('etablissement_id');
        $anneeId = (int) $request->input('annee_scolaire_id');

        $etablissement = Etablissement::with('institution')->findOrFail($etablissementId);
        $annee = AnneeScolaire::findOrFail($anneeId);

        // Recettes
        $versements = VersementCaisse::where('etablissement_id', $etablissementId)
            ->where('statut', 'valide')
            ->sum('montant');

        $divers = ReglementDivers::where('etablissement_id', $etablissementId)
            ->where('statut', 'valide')
            ->sum('montant');

        $totalRecettes = $versements + $divers;

        // Dépenses groupées par catégorie
        $categoriesDepenses = CategorieDepense::with(['depenses' => function ($q) use ($etablissementId, $anneeId) {
            $q->where('etablissement_id', $etablissementId)
              ->where('annee_scolaire_id', $anneeId)
              ->where('statut', 'validee');
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
            'institution' => $etablissement->institution,
            'annee' => $annee,
            'versements' => $versements,
            'divers' => $divers,
            'totalRecettes' => $totalRecettes,
            'lignesCategories' => $lignesCategories,
            'totalDepenses' => $totalDepenses,
            'soldeNet' => $soldeNet,
            'date_generation' => now()->format('d/m/Y'),
        ])->setPaper('a4', 'portrait');

        $filename = "BILAN_FINANCIER_{$annee->code}.pdf";

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$filename}\"",
        ]);
    }
}
