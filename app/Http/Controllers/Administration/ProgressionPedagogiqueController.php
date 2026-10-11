<?php

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use App\Models\AnneeScolaire;
use App\Models\Attribution;
use App\Models\Classe;
use App\Models\FicheProgression;
use App\Models\Matiere;
use App\Models\Periode;
use App\Models\Personnel;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Contrôleur de gestion du Cahier de Texte & Progression Pédagogique.
 * 
 * Conforme au Manuel Aurore (Section 5.1.8) et au Cahier des charges Gesco V1.1.
 * Gère la saisie des fiches de progression des cours par les enseignants,
 * le calcul des taux d'avancement du programme d'enseignement,
 * le visa de contrôle hiérarchique par les censeurs, et l'export PDF officiel.
 */
class ProgressionPedagogiqueController extends Controller implements HasMiddleware
{
    /**
     * Définition de la matrice des middlewares d'autorisation pour la Progression Pédagogique.
     *
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:progression.view|evaluations.view', only: ['index']),
            new Middleware('can:progression.create|evaluations.manage', only: ['store']),
            new Middleware('can:progression.edit|evaluations.manage', only: ['update']),
            new Middleware('can:progression.delete|evaluations.manage', only: ['destroy']),
            new Middleware('can:progression.viser|evaluations.manage', only: ['viser']),
            new Middleware('can:progression.print|progression.view|evaluations.view', only: ['imprimerFiche']),
        ];
    }
    /**
     * Affiche la vue principale du cahier de texte et de suivi de la progression.
     *
     * @param Request $request
     * @return InertiaResponse
     */
    public function index(Request $request): InertiaResponse
    {
        $user = Auth::user();
        $etablissementId = $user->getEffectiveEtablissementId();

        // Année scolaire active
        $anneeActive = AnneeScolaire::where('is_current', true)->first()
            ?? AnneeScolaire::orderByDesc('date_debut')->first();

        $anneeId = $anneeActive ? $anneeActive->id : null;

        // Filtres
        $classeId = $request->input('classe_id');
        $matiereId = $request->input('matiere_id');
        $personnelId = $request->input('personnel_id');
        $periodeId = $request->input('periode_id');
        $statut = $request->input('statut');
        $visaStatus = $request->input('visa'); // 'vise', 'en_attente', 'all'
        $search = $request->input('search');

        // Construction de la requête des fiches
        $query = FicheProgression::with([
            'attribution.classe',
            'attribution.matiere',
            'attribution.personnel',
            'periode',
            'visePar:id,name',
        ])
        ->where('etablissement_id', $etablissementId)
        ->when($anneeId, fn($q) => $q->where('annee_scolaire_id', $anneeId));

        if ($classeId) {
            $query->whereHas('attribution', fn($q) => $q->where('classe_id', $classeId));
        }

        if ($matiereId) {
            $query->whereHas('attribution', fn($q) => $q->where('matiere_id', $matiereId));
        }

        if ($personnelId) {
            $query->whereHas('attribution', fn($q) => $q->where('personnel_id', $personnelId));
        }

        if ($periodeId) {
            $query->where('periode_id', $periodeId);
        }

        if ($statut && in_array($statut, ['fait', 'en_cours', 'reporte'])) {
            $query->where('statut', $statut);
        }

        if ($visaStatus === 'vise') {
            $query->where('visa_censeur', true);
        } elseif ($visaStatus === 'en_attente') {
            $query->where('visa_censeur', false);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('chapitre', 'like', "%{$search}%")
                  ->orWhere('titre_lecon', 'like', "%{$search}%")
                  ->orWhere('resume_activite', 'like', "%{$search}%");
            });
        }

        // Statistiques globales pour la sélection courante
        $statsQuery = clone $query;
        $totalSeances = $statsQuery->count();
        $totalHeuresPrevues = (float) $statsQuery->sum('heures_prevues');
        $totalHeuresEffectuees = (float) $statsQuery->sum('heures_effectuees');
        $totalVisees = (int) (clone $statsQuery)->where('visa_censeur', true)->count();
        $totalEnAttenteVisa = $totalSeances - $totalVisees;
        
        $tauxRealisation = $totalHeuresPrevues > 0
            ? round(($totalHeuresEffectuees / $totalHeuresPrevues) * 100, 1)
            : 0;

        // Liste des séances triées par date décroissante
        $fiches = $query->orderByDesc('date_seance')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        // Référentiels pour les sélecteurs
        $classes = Classe::where('etablissement_id', $etablissementId)
            ->orderBy('nom')
            ->get(['id', 'nom', 'code']);

        $matieres = Matiere::orderBy('nom')->get(['id', 'nom', 'code']);

        $enseignants = Personnel::where('etablissement_id', $etablissementId)
            ->where('is_enseignant', true)
            ->where('statut', 'Actif')
            ->orderBy('nom')
            ->get(['id', 'nom', 'prenom', 'matricule']);

        $periodes = Periode::where('annee_scolaire_id', $anneeId)
            ->where(function ($q) use ($etablissementId) {
                $q->whereNull('etablissement_id')->orWhere('etablissement_id', $etablissementId);
            })
            ->orderBy('ordre')
            ->get(['id', 'nom', 'code']);

        // Attributions actives
        $attributions = Attribution::with(['classe:id,nom,code', 'matiere:id,nom,code', 'personnel:id,nom,prenom'])
            ->where('annee_scolaire_id', $anneeId)
            ->whereHas('classe', fn($q) => $q->where('etablissement_id', $etablissementId))
            ->where('is_active', true)
            ->get();

        return Inertia::render('administration/progression/index', [
            'fiches' => $fiches,
            'stats' => [
                'total_seances' => $totalSeances,
                'total_heures_prevues' => $totalHeuresPrevues,
                'total_heures_effectuees' => $totalHeuresEffectuees,
                'total_visees' => $totalVisees,
                'total_en_attente_visa' => $totalEnAttenteVisa,
                'taux_realisation' => $tauxRealisation,
            ],
            'filters' => [
                'classe_id' => $classeId,
                'matiere_id' => $matiereId,
                'personnel_id' => $personnelId,
                'periode_id' => $periodeId,
                'statut' => $statut,
                'visa' => $visaStatus,
                'search' => $search,
            ],
            'classes' => $classes,
            'matieres' => $matieres,
            'enseignants' => $enseignants,
            'periodes' => $periodes,
            'attributions' => $attributions,
            'can' => [
                'view' => $user?->hasRole('super-admin') || $user?->can('progression.view') || $user?->can('evaluations.view'),
                'create' => $user?->hasRole('super-admin') || $user?->can('progression.create') || $user?->can('evaluations.manage'),
                'edit' => $user?->hasRole('super-admin') || $user?->can('progression.edit') || $user?->can('evaluations.manage'),
                'delete' => $user?->hasRole('super-admin') || $user?->can('progression.delete'),
                'viser' => $user?->hasRole('super-admin') || $user?->can('progression.viser') || $user?->can('evaluations.manage'),
                'print' => $user?->hasRole('super-admin') || $user?->can('progression.print') || $user?->can('progression.view') || $user?->can('evaluations.view'),
            ],
        ]);
    }

    /**
     * Enregistre une nouvelle séance de cours dans le cahier de texte.
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $etablissementId = $user->getEffectiveEtablissementId();

        $validated = $request->validate([
            'attribution_id' => ['required', 'exists:attributions,id'],
            'periode_id' => ['nullable', 'exists:periodes,id'],
            'date_seance' => ['required', 'date'],
            'semaine_numero' => ['nullable', 'integer', 'min:1', 'max:52'],
            'chapitre' => ['required', 'string', 'max:255'],
            'titre_lecon' => ['required', 'string', 'max:255'],
            'resume_activite' => ['nullable', 'string'],
            'travail_a_faire' => ['nullable', 'string'],
            'heures_prevues' => ['required', 'numeric', 'min:0.5', 'max:20'],
            'heures_effectuees' => ['required', 'numeric', 'min:0', 'max:20'],
            'statut' => ['required', 'in:fait,en_cours,reporte'],
            'motif_report' => ['nullable', 'string', 'max:255'],
        ]);

        $attribution = Attribution::findOrFail($validated['attribution_id']);

        FicheProgression::create([
            'etablissement_id' => $etablissementId,
            'annee_scolaire_id' => $attribution->annee_scolaire_id,
            'attribution_id' => $validated['attribution_id'],
            'periode_id' => $validated['periode_id'] ?? null,
            'date_seance' => $validated['date_seance'],
            'semaine_numero' => $validated['semaine_numero'] ?? null,
            'chapitre' => $validated['chapitre'],
            'titre_lecon' => $validated['titre_lecon'],
            'resume_activite' => $validated['resume_activite'] ?? null,
            'travail_a_faire' => $validated['travail_a_faire'] ?? null,
            'heures_prevues' => $validated['heures_prevues'],
            'heures_effectuees' => $validated['heures_effectuees'],
            'statut' => $validated['statut'],
            'motif_report' => $validated['motif_report'] ?? null,
            'visa_censeur' => false,
        ]);

        return back()->with('success', 'Séance de cours enregistrée avec succès dans le cahier de texte.');
    }

    /**
     * Met à jour une séance de cours existante.
     *
     * @param Request $request
     * @param FicheProgression $progression
     * @return RedirectResponse
     */
    public function update(Request $request, FicheProgression $progression): RedirectResponse
    {
        $validated = $request->validate([
            'attribution_id' => ['required', 'exists:attributions,id'],
            'periode_id' => ['nullable', 'exists:periodes,id'],
            'date_seance' => ['required', 'date'],
            'semaine_numero' => ['nullable', 'integer', 'min:1', 'max:52'],
            'chapitre' => ['required', 'string', 'max:255'],
            'titre_lecon' => ['required', 'string', 'max:255'],
            'resume_activite' => ['nullable', 'string'],
            'travail_a_faire' => ['nullable', 'string'],
            'heures_prevues' => ['required', 'numeric', 'min:0.5', 'max:20'],
            'heures_effectuees' => ['required', 'numeric', 'min:0', 'max:20'],
            'statut' => ['required', 'in:fait,en_cours,reporte'],
            'motif_report' => ['nullable', 'string', 'max:255'],
        ]);

        $progression->update($validated);

        return back()->with('success', 'Séance mise à jour avec succès.');
    }

    /**
     * Supprime une séance du cahier de texte.
     *
     * @param FicheProgression $progression
     * @return RedirectResponse
     */
    public function destroy(FicheProgression $progression): RedirectResponse
    {
        $progression->delete();

        return back()->with('success', 'Séance supprimée du cahier de texte.');
    }

    /**
     * Appose ou révoque le visa hiérarchique du censeur sur une séance.
     *
     * @param Request $request
     * @param FicheProgression $progression
     * @return RedirectResponse
     */
    public function viser(Request $request, FicheProgression $progression): RedirectResponse
    {
        $validated = $request->validate([
            'visa' => ['required', 'boolean'],
            'observations_censeur' => ['nullable', 'string', 'max:500'],
        ]);

        if ($validated['visa']) {
            $progression->update([
                'visa_censeur' => true,
                'date_visa' => now(),
                'vise_par_id' => Auth::id(),
                'observations_censeur' => $validated['observations_censeur'] ?? $progression->observations_censeur,
            ]);
            $message = 'Séance validée et visée par le censeur avec succès.';
        } else {
            $progression->update([
                'visa_censeur' => false,
                'date_visa' => null,
                'vise_par_id' => null,
            ]);
            $message = 'Visa hiérarchique révoqué.';
        }

        return back()->with('success', $message);
    }

    /**
     * Génère et télécharge la fiche officielle de progression pédagogique en PDF.
     * Conforme aux normes d'inspection pédagogique nationale (Aurore 5.1.8).
     *
     * @param Request $request
     * @param int $attributionId
     * @return Response
     */
    public function imprimerFiche(Request $request, int $attributionId): Response
    {
        $attribution = Attribution::with([
            'classe',
            'matiere',
            'personnel',
            'anneeScolaire',
        ])->findOrFail($attributionId);

        $fiches = FicheProgression::with(['periode', 'visePar:id,name'])
            ->where('attribution_id', $attributionId)
            ->orderBy('date_seance', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $totalPrevues = (float) $fiches->sum('heures_prevues');
        $totalEffectuees = (float) $fiches->sum('heures_effectuees');
        $taux = $totalPrevues > 0 ? round(($totalEffectuees / $totalPrevues) * 100, 1) : 0;

        $etablissement = Auth::user()->etablissement;

        $pdf = Pdf::loadView('pedagogie.fiche_progression', [
            'attribution' => $attribution,
            'fiches' => $fiches,
            'etablissement' => $etablissement,
            'totalPrevues' => $totalPrevues,
            'totalEffectuees' => $totalEffectuees,
            'taux' => $taux,
            'dateImpression' => now()->format('d/m/Y H:i'),
        ])->setPaper('a4', 'portrait');

        $fileName = sprintf(
            'Progression_%s_%s_%s.pdf',
            $attribution->classe->code ?? $attribution->classe->nom,
            $attribution->matiere->code ?? $attribution->matiere->nom,
            now()->format('Ymd')
        );

        return $pdf->stream($fileName);
    }
}
