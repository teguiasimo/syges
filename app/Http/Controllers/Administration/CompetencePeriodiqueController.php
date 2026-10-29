<?php

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use App\Models\AnneeScolaire;
use App\Models\Attribution;
use App\Models\Classe;
use App\Models\CompetencePeriodique;
use App\Models\CoteConfiguration;
use App\Models\Etablissement;
use App\Models\Matiere;
use App\Models\MatiereNiveau;
use App\Models\Periode;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Contrôleur de gestion de la Pédagogie APC (Phase 3).
 *
 * Gère :
 * 1. La banque de compétences périodiques du programme officiel par classe, matière et période.
 * 2. L'outil de transfert et duplication en masse des compétences vers les classes parallèles.
 * 3. La configuration des cotes d'acquisition personnalisées (A/B/C ou CTBA/CBA).
 */
class CompetencePeriodiqueController extends Controller implements HasMiddleware
{
    /**
     * Définition de la matrice des middlewares d'autorisation pour la Pédagogie APC.
     *
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:competences_apc.view|evaluations.view', only: ['index']),
            new Middleware('can:competences_apc.create|evaluations.manage', only: ['store']),
            new Middleware('can:competences_apc.edit|evaluations.manage', only: ['update']),
            new Middleware('can:competences_apc.delete|evaluations.manage', only: ['destroy']),
            new Middleware('can:competences_apc.duplicate|evaluations.manage', only: ['transferer']),
            new Middleware('can:cotes_apc.manage|evaluations.manage', only: ['storeCote', 'destroyCote']),
        ];
    }
    /**
     * Affiche l'écran de paramétrage des compétences périodiques et des cotes d'acquisition.
     *
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        /** @var User|null $user */
        $user = Auth::user();
        $isSuperAdmin = $user ? ($user->hasRole('super-admin') || $user->email === 'admin@gmail.com') : false;

        $etablissementsQuery = Etablissement::with([
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
        $periodeId = $request->input('periode_id');
        $evaluationId = $request->input('evaluation_id');
        $search = $request->input('search');

        // Récupération des compétences enregistrées
        $competencesQuery = CompetencePeriodique::with([
            'classe.niveau',
            'matiere',
            'periode',
            'evaluation',
        ])
        ->where('etablissement_id', $selectedEtabId);

        if ($classeId) {
            $competencesQuery->where('classe_id', $classeId);
        }

        if ($matiereId) {
            $competencesQuery->where('matiere_id', $matiereId);
        }

        if ($periodeId) {
            $competencesQuery->where('periode_id', $periodeId);
        }

        if ($evaluationId) {
            $competencesQuery->where('evaluation_id', $evaluationId);
        }

        if ($search) {
            $competencesQuery->where(function ($q) use ($search) {
                $q->where('libelle_competence', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $competences = $competencesQuery->latest()->get();

        // Référentiels pour les formulaires et filtres
        $matieres = Matiere::where(function ($q) use ($selectedEtabId) {
            $q->whereNull('etablissement_id')->orWhere('etablissement_id', $selectedEtabId);
        })->where('is_active', true)->orderBy('nom')->get();

        $periodes = Periode::where('annee_scolaire_id', $selectedAnneeId)
            ->where(function ($q) use ($selectedEtabId) {
                $q->whereNull('etablissement_id')->orWhere('etablissement_id', $selectedEtabId);
            })
            ->with('evaluations')
            ->orderBy('ordre')
            ->get();

        // Configuration des cotes de l'établissement
        $cotes = CoteConfiguration::where('etablissement_id', $selectedEtabId)
            ->orderByDesc('borne_minimale')
            ->get();

        // Cartographie des matières enseignées par classe (attributions actives + coefficients de niveau)
        $classesEtab = Classe::where('is_active', true)
            ->when($selectedEtabId, fn ($q) => $q->where('etablissement_id', $selectedEtabId))
            ->get();

        $attributionsParClasse = Attribution::whereIn('classe_id', $classesEtab->pluck('id'))
            ->where('annee_scolaire_id', $selectedAnneeId)
            ->where('is_active', true)
            ->get()
            ->groupBy('classe_id');

        $matieresNiveauParNiveau = MatiereNiveau::whereIn('niveau_id', $classesEtab->pluck('niveau_id')->unique())
            ->get()
            ->groupBy('niveau_id');

        $classeMatieresMap = [];
        foreach ($classesEtab as $cls) {
            $fromAttr = isset($attributionsParClasse[$cls->id])
                ? $attributionsParClasse[$cls->id]->pluck('matiere_id')->toArray()
                : [];

            $fromNiveau = isset($matieresNiveauParNiveau[$cls->niveau_id])
                ? $matieresNiveauParNiveau[$cls->niveau_id]
                    ->filter(fn ($mn) => empty($mn->serie) || $mn->serie === 'Toutes' || $mn->serie === $cls->serie)
                    ->pluck('matiere_id')
                    ->toArray()
                : [];

            $classeMatieresMap[$cls->id] = array_values(array_unique(array_merge($fromAttr, $fromNiveau)));
        }

        return Inertia::render('administration/pedagogie/competences/index', [
            'competences' => $competences,
            'cotes' => $cotes,
            'matieres' => $matieres,
            'periodes' => $periodes,
            'classeMatieresMap' => $classeMatieresMap,
            'etablissements' => $etablissements,
            'selectedEtabId' => $selectedEtabId,
            'selectedAnneeId' => $selectedAnneeId,
            'anneesScolaires' => AnneeScolaire::orderByDesc('is_current')->orderByDesc('date_debut')->get(),
            'filters' => [
                'etablissement_id' => (string) $selectedEtabId,
                'classe_id' => (string) $classeId,
                'matiere_id' => (string) $matiereId,
                'periode_id' => (string) $periodeId,
                'evaluation_id' => (string) $evaluationId,
                'search' => $search ?: '',
            ],
            'can' => [
                'view' => $isSuperAdmin || $user?->can('competences_apc.view') || $user?->can('evaluations.view'),
                'create' => $isSuperAdmin || $user?->can('competences_apc.create') || $user?->can('evaluations.manage'),
                'edit' => $isSuperAdmin || $user?->can('competences_apc.edit') || $user?->can('evaluations.manage'),
                'delete' => $isSuperAdmin || $user?->can('competences_apc.delete'),
                'duplicate' => $isSuperAdmin || $user?->can('competences_apc.duplicate') || $user?->can('evaluations.manage'),
                'cotes_manage' => $isSuperAdmin || $user?->can('cotes_apc.manage') || $user?->can('evaluations.manage'),
            ],
        ]);
    }

    /**
     * Enregistre une nouvelle compétence périodique officielle.
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'etablissement_id' => ['required', 'exists:etablissements,id'],
            'classe_id' => ['required', 'exists:classes,id'],
            'matiere_id' => ['required', 'exists:matieres,id'],
            'periode_id' => ['nullable', 'exists:periodes,id'],
            'evaluation_id' => ['nullable', 'exists:evaluations,id'],
            'libelle_competence' => ['required', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        CompetencePeriodique::create($validated);

        return back()->with('success', 'Compétence périodique enregistrée avec succès.');
    }

    /**
     * Met à jour une compétence périodique existante.
     *
     * @param Request $request
     * @param CompetencePeriodique $competence
     * @return RedirectResponse
     */
    public function update(Request $request, CompetencePeriodique $competence): RedirectResponse
    {
        $validated = $request->validate([
            'libelle_competence' => ['required', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
        ]);

        $competence->update($validated);

        return back()->with('success', 'Compétence périodique mise à jour.');
    }

    /**
     * Supprime une compétence périodique.
     *
     * @param CompetencePeriodique $competence
     * @return RedirectResponse
     */
    public function destroy(CompetencePeriodique $competence): RedirectResponse
    {
        $competence->delete();

        return back()->with('success', 'Compétence supprimée avec succès.');
    }

    /**
     * Outil exclusif : Transfère et duplique les compétences d'une classe source vers des classes parallèles.
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function transferer(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'classe_source_id' => ['required', 'exists:classes,id'],
            'classes_cibles_ids' => ['required', 'array', 'min:1'],
            'classes_cibles_ids.*' => ['integer', 'exists:classes,id'],
            'periode_id' => ['nullable', 'exists:periodes,id'],
            'ecraser_existant' => ['boolean'],
        ]);

        $classeSourceId = (int) $validated['classe_source_id'];
        $classesCibles = $validated['classes_cibles_ids'];
        $periodeId = $validated['periode_id'] ?? null;
        $ecraser = $validated['ecraser_existant'] ?? false;

        // Récupération des compétences sources
        $sourceQuery = CompetencePeriodique::where('classe_id', $classeSourceId);
        if ($periodeId) {
            $sourceQuery->where('periode_id', $periodeId);
        }
        $sources = $sourceQuery->get();

        if ($sources->isEmpty()) {
            return back()->with('error', 'Aucune compétence trouvée pour la classe source sélectionnée.');
        }

        $totalCopies = 0;

        foreach ($classesCibles as $targetClasseId) {
            if ($targetClasseId === $classeSourceId) {
                continue;
            }

            if ($ecraser) {
                $deleteQuery = CompetencePeriodique::where('classe_id', $targetClasseId);
                if ($periodeId) {
                    $deleteQuery->where('periode_id', $periodeId);
                }
                $deleteQuery->delete();
            }

            foreach ($sources as $source) {
                CompetencePeriodique::updateOrCreate(
                    [
                        'etablissement_id' => $source->etablissement_id,
                        'classe_id' => $targetClasseId,
                        'matiere_id' => $source->matiere_id,
                        'periode_id' => $source->periode_id,
                        'evaluation_id' => $source->evaluation_id,
                        'libelle_competence' => $source->libelle_competence,
                    ],
                    [
                        'description' => $source->description,
                        'is_active' => $source->is_active,
                    ]
                );
                $totalCopies++;
            }
        }

        return back()->with('success', "Transfert réussi : {$totalCopies} compétence(s) dupliquée(s) vers " . count($classesCibles) . " classe(s).");
    }

    /**
     * Enregistre ou modifie une cote d'acquisition personnalisée.
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function storeCote(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'etablissement_id' => ['required', 'exists:etablissements,id'],
            'code_cote' => ['required', 'string', 'max:10'],
            'libelle' => ['required', 'string', 'max:100'],
            'libelle_en' => ['nullable', 'string', 'max:100'],
            'borne_minimale' => ['required', 'numeric', 'min:0', 'max:20'],
            'borne_maximale' => ['required', 'numeric', 'min:0', 'max:20', 'gte:borne_minimale'],
            'couleur' => ['required', 'string', 'max:25'],
            'ordre' => ['integer'],
        ]);

        CoteConfiguration::updateOrCreate(
            [
                'etablissement_id' => $validated['etablissement_id'],
                'code_cote' => $validated['code_cote'],
            ],
            $validated
        );

        return back()->with('success', "Cote d'acquisition enregistrée avec succès.");
    }

    /**
     * Supprime une cote d'acquisition personnalisée.
     *
     * @param CoteConfiguration $cote
     * @return RedirectResponse
     */
    public function destroyCote(CoteConfiguration $cote): RedirectResponse
    {
        $cote->delete();

        return back()->with('success', "Cote personnalisée supprimée. Le barème officiel MINESEC s'applique.");
    }
}
