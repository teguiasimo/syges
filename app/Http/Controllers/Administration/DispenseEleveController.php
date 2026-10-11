<?php

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\DispenseEleve;
use App\Models\Etablissement;
use App\Models\Inscription;
use App\Models\Matiere;
use App\Models\Periode;
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
 * Contrôleur pour la gestion des Dérogations & Dispenses Officielles (Phase 4).
 *
 * Conforme aux directives MINESEC camerounaises :
 * - Suivi et validation des dispenses médicales (EPS / inaptitudes physiques) et dérogations ministérielles.
 * - Neutralisation automatique des coefficients lors du calcul des bulletins trimestriels et séquentiels.
 * - Production du Procès-Verbal (PV) officiel et du registre des dispenses pour les inspections.
 */
class DispenseEleveController extends Controller implements HasMiddleware
{
    /**
     * Définition de la matrice des middlewares d'autorisation pour les Dispenses & Dérogations.
     *
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:dispenses.view|evaluations.view', only: ['index']),
            new Middleware('can:dispenses.create|evaluations.manage', only: ['store']),
            new Middleware('can:dispenses.edit|evaluations.manage', only: ['update']),
            new Middleware('can:dispenses.delete|evaluations.manage', only: ['destroy']),
            new Middleware('can:dispenses.homologuer|evaluations.manage', only: ['changerStatut']),
            new Middleware('can:dispenses.print_pv|dispenses.view|evaluations.view', only: ['exporterPv']),
        ];
    }
    /**
     * Affiche l'écran de gestion et le registre des dispenses officielles.
     *
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        $user = Auth::user();
        $isSuperAdmin = $user ? ($user->hasRole('super-admin') || $user->email === 'admin@gmail.com') : false;

        $etablissementsQuery = Etablissement::with([
            'institution',
            'sections.cycles.niveaux.classes',
        ]);

        if (! $isSuperAdmin && $user?->personnel?->etablissement_id) {
            $etablissementsQuery->where('id', $user->personnel->etablissement_id);
        }

        $etablissements = $etablissementsQuery->get();
        $selectedEtabId = (int) $request->input('etablissement_id', $etablissements->first()?->id);

        $anneeActive = AnneeScolaire::where('is_current', true)->first() ?? AnneeScolaire::latest()->first();
        $selectedAnneeId = (int) $request->input('annee_scolaire_id', $anneeActive?->id);

        // Filtres
        $classeId = $request->input('classe_id');
        $matiereId = $request->input('matiere_id');
        $typeDispense = $request->input('type_dispense');
        $statut = $request->input('statut');
        $search = $request->input('search');

        // Requête principale des dispenses
        $dispensesQuery = DispenseEleve::with([
            'eleve',
            'inscription.classe.niveau',
            'matiere',
            'periode',
            'auteur',
        ])
        ->where('etablissement_id', $selectedEtabId)
        ->where('annee_scolaire_id', $selectedAnneeId);

        if ($classeId) {
            $dispensesQuery->whereHas('inscription', function ($q) use ($classeId) {
                $q->where('classe_id', $classeId);
            });
        }

        if ($matiereId) {
            $dispensesQuery->where('matiere_id', $matiereId);
        }

        if ($typeDispense) {
            $dispensesQuery->where('type_dispense', $typeDispense);
        }

        if ($statut) {
            $dispensesQuery->where('statut', $statut);
        }

        if ($search) {
            $dispensesQuery->where(function ($q) use ($search) {
                $q->where('motif', 'like', "%{$search}%")
                  ->orWhere('reference_certificat', 'like', "%{$search}%")
                  ->orWhere('medecin_ou_autorite', 'like', "%{$search}%")
                  ->orWhereHas('eleve', function ($sq) use ($search) {
                      $sq->where('nom', 'like', "%{$search}%")
                         ->orWhere('prenom', 'like', "%{$search}%")
                         ->orWhere('matricule', 'like', "%{$search}%");
                  });
            });
        }

        $dispenses = $dispensesQuery->latest()->get();

        // Statistiques globales de l'établissement
        $stats = [
            'total_dispenses' => DispenseEleve::where('etablissement_id', $selectedEtabId)->where('annee_scolaire_id', $selectedAnneeId)->count(),
            'total_validees' => DispenseEleve::where('etablissement_id', $selectedEtabId)->where('annee_scolaire_id', $selectedAnneeId)->where('statut', 'validee')->count(),
            'total_eps' => DispenseEleve::where('etablissement_id', $selectedEtabId)->where('annee_scolaire_id', $selectedAnneeId)->whereHas('matiere', function ($q) {
                $q->where('code', 'like', '%EPS%')->orWhere('nom', 'like', '%Sport%')->orWhere('nom', 'like', '%Physique%');
            })->count(),
            'total_en_attente' => DispenseEleve::where('etablissement_id', $selectedEtabId)->where('annee_scolaire_id', $selectedAnneeId)->where('statut', 'en_attente')->count(),
        ];

        // Référentiels pour filtres et formulaires
        $matieres = Matiere::where(function ($q) use ($selectedEtabId) {
            $q->whereNull('etablissement_id')->orWhere('etablissement_id', $selectedEtabId);
        })->where('is_active', true)->orderBy('nom')->get();

        $periodes = Periode::where('annee_scolaire_id', $selectedAnneeId)
            ->where(function ($q) use ($selectedEtabId) {
                $q->whereNull('etablissement_id')->orWhere('etablissement_id', $selectedEtabId);
            })
            ->orderBy('ordre')
            ->get();

        // Récupération des inscriptions actives pour la sélection des élèves
        $inscriptions = Inscription::with(['eleve', 'classe.niveau'])
            ->where('etablissement_id', $selectedEtabId)
            ->where('annee_scolaire_id', $selectedAnneeId)
            ->where('statut', 'validee')
            ->get()
            ->sortBy(fn ($i) => $i->eleve->nom . ' ' . $i->eleve->prenom)
            ->values();

        return Inertia::render('administration/pedagogie/dispenses/index', [
            'dispenses' => $dispenses,
            'stats' => $stats,
            'inscriptions' => $inscriptions,
            'matieres' => $matieres,
            'periodes' => $periodes,
            'etablissements' => $etablissements,
            'selectedEtabId' => $selectedEtabId,
            'selectedAnneeId' => $selectedAnneeId,
            'anneesScolaires' => AnneeScolaire::orderByDesc('is_current')->orderByDesc('date_debut')->get(),
            'filters' => [
                'etablissement_id' => (string) $selectedEtabId,
                'annee_scolaire_id' => (string) $selectedAnneeId,
                'classe_id' => (string) $classeId,
                'matiere_id' => (string) $matiereId,
                'type_dispense' => (string) $typeDispense,
                'statut' => (string) $statut,
                'search' => $search ?: '',
            ],
            'can' => [
                'view' => $isSuperAdmin || $user?->can('dispenses.view') || $user?->can('evaluations.view'),
                'create' => $isSuperAdmin || $user?->can('dispenses.create') || $user?->can('evaluations.manage'),
                'edit' => $isSuperAdmin || $user?->can('dispenses.edit') || $user?->can('evaluations.manage'),
                'delete' => $isSuperAdmin || $user?->can('dispenses.delete'),
                'homologuer' => $isSuperAdmin || $user?->can('dispenses.homologuer') || $user?->can('evaluations.manage'),
                'print_pv' => $isSuperAdmin || $user?->can('dispenses.print_pv') || $user?->can('dispenses.view') || $user?->can('evaluations.view'),
            ],
        ]);
    }

    /**
     * Enregistre une nouvelle dérogation ou dispense officielle.
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'etablissement_id' => ['required', 'exists:etablissements,id'],
            'annee_scolaire_id' => ['required', 'exists:annees_scolaires,id'],
            'inscription_id' => ['required', 'exists:inscriptions,id'],
            'matiere_id' => ['required', 'exists:matieres,id'],
            'periode_id' => ['nullable', 'exists:periodes,id'],
            'type_dispense' => ['required', 'string', 'in:medicale,administrative,handicap,derogation_special'],
            'motif' => ['required', 'string', 'max:1000'],
            'reference_certificat' => ['nullable', 'string', 'max:100'],
            'medecin_ou_autorite' => ['nullable', 'string', 'max:150'],
            'date_debut' => ['nullable', 'date'],
            'date_fin' => ['nullable', 'date', 'after_or_equal:date_debut'],
            'neutraliser_coefficient' => ['boolean'],
            'statut' => ['nullable', 'string', 'in:validee,en_attente,rejetee,expiree'],
        ]);

        $inscription = Inscription::findOrFail($validated['inscription_id']);
        $validated['eleve_id'] = $inscription->eleve_id;
        $validated['created_by'] = Auth::id();
        $validated['statut'] = $validated['statut'] ?? 'validee';
        $validated['neutraliser_coefficient'] = $request->boolean('neutraliser_coefficient', true);

        DispenseEleve::create($validated);

        return back()->with('success', 'Dispense officielle enregistrée. Le coefficient sera automatiquement neutralisé sur le bulletin.');
    }

    /**
     * Met à jour une dispense existante.
     *
     * @param Request $request
     * @param DispenseEleve $dispense
     * @return RedirectResponse
     */
    public function update(Request $request, DispenseEleve $dispense): RedirectResponse
    {
        $validated = $request->validate([
            'matiere_id' => ['required', 'exists:matieres,id'],
            'periode_id' => ['nullable', 'exists:periodes,id'],
            'type_dispense' => ['required', 'string', 'in:medicale,administrative,handicap,derogation_special'],
            'motif' => ['required', 'string', 'max:1000'],
            'reference_certificat' => ['nullable', 'string', 'max:100'],
            'medecin_ou_autorite' => ['nullable', 'string', 'max:150'],
            'date_debut' => ['nullable', 'date'],
            'date_fin' => ['nullable', 'date', 'after_or_equal:date_debut'],
            'neutraliser_coefficient' => ['boolean'],
            'statut' => ['required', 'string', 'in:validee,en_attente,rejetee,expiree'],
        ]);

        $validated['neutraliser_coefficient'] = $request->boolean('neutraliser_coefficient', true);

        $dispense->update($validated);

        return back()->with('success', 'Dispense officielle mise à jour avec succès.');
    }

    /**
     * Supprime une dispense.
     *
     * @param DispenseEleve $dispense
     * @return RedirectResponse
     */
    public function destroy(DispenseEleve $dispense): RedirectResponse
    {
        $dispense->delete();

        return back()->with('success', 'Dispense supprimée. La matière sera réintégrée dans le calcul de notation.');
    }

    /**
     * Modifie rapidement le statut d'une dispense (validation, rejet, expiration).
     *
     * @param Request $request
     * @param DispenseEleve $dispense
     * @return RedirectResponse
     */
    public function changerStatut(Request $request, DispenseEleve $dispense): RedirectResponse
    {
        $validated = $request->validate([
            'statut' => ['required', 'string', 'in:validee,en_attente,rejetee,expiree'],
        ]);

        $dispense->update(['statut' => $validated['statut']]);

        return back()->with('success', "Statut de la dispense mis à jour ({$validated['statut']}).");
    }

    /**
     * Génère le Procès-Verbal officiel et le Registre d'Inaptitude / Dispenses au format PDF.
     *
     * @param Request $request
     * @return HttpResponse
     */
    public function exporterPv(Request $request): HttpResponse
    {
        $etablissementId = (int) $request->input('etablissement_id');
        $anneeId = (int) $request->input('annee_scolaire_id');
        $classeId = $request->input('classe_id');
        $matiereId = $request->input('matiere_id');

        $etablissement = Etablissement::with('institution')->findOrFail($etablissementId);
        $annee = AnneeScolaire::findOrFail($anneeId);

        $query = DispenseEleve::with([
            'eleve',
            'inscription.classe.niveau',
            'matiere',
            'periode',
        ])
        ->where('etablissement_id', $etablissementId)
        ->where('annee_scolaire_id', $anneeId)
        ->where('statut', 'validee');

        if ($classeId) {
            $query->whereHas('inscription', fn ($q) => $q->where('classe_id', $classeId));
        }

        if ($matiereId) {
            $query->where('matiere_id', $matiereId);
        }

        $dispenses = $query->orderBy('matiere_id')->get();

        $pdf = Pdf::loadView('pedagogie.pv_dispenses', [
            'etablissement' => $etablissement,
            'institution' => $etablissement->institution,
            'annee' => $annee,
            'dispenses' => $dispenses,
            'classe' => $classeId ? Classe::find($classeId) : null,
            'date_generation' => now()->format('d/m/Y'),
        ])->setPaper('a4', 'landscape');

        $filename = "PV_DISPENSES_" . preg_replace('/[^A-Za-z0-9]/', '_', $etablissement->code) . "_{$annee->code}.pdf";

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$filename}\"",
        ]);
    }
}
