<?php

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use App\Models\Etablissement;
use App\Models\GroupeMatiere;
use App\Models\Matiere;
use App\Models\MatiereNiveau;
use App\Models\Niveau;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Contrôleur d'administration du Catalogue des Matières et de la Grille des Coefficients.
 *
 * Implémente le Module 4 du cahier des charges Gesco :
 * - 4.1 Groupes de Matières (Blocs Pédagogiques ordonnés pour la maquette officielle du bulletin MINESEC/MINEDUB).
 * - 4.2 Catalogue Général des Matières (Discipline, codes officiels, bilinguisme FR/EN).
 * - 4.3 Configuration des Coefficients (Poids académique par Niveau + Série, note max, note éliminatoire, facultatif/bonus).
 */
class MatiereController extends Controller implements HasMiddleware
{
    /**
     * Définition de la matrice des middlewares d'autorisation pour le module Matières & Coefficients.
     *
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:matieres.view', only: ['index']),
            new Middleware('can:matieres.manage', only: [
                'storeGroupe', 'updateGroupe', 'destroyGroupe',
                'storeMatiere', 'updateMatiere', 'destroyMatiere',
                'storeCoefficient', 'updateCoefficient', 'destroyCoefficient',
                'seedStandards',
            ]),
        ];
    }
    /**
     * Affiche l'interface de gestion des matières, groupes et coefficients.
     * Permet le filtrage contextuel par Établissement scolaire maître et Niveau d'étude.
     *
     * @param Request $request Requête HTTP entrante avec filtres optionnels (etablissement_id, niveau_id, serie).
     * @return Response Vue Inertia avec les données du catalogue et de la grille.
     */
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('matieres.view'), 403, 'Accès non autorisé au catalogue des matières.');

        // 1. Récupération de tous les campus / établissements de l'institution
        $etablissements = Etablissement::orderBy('nom')->get();
        $etablissementId = $request->query('etablissement_id');
        $selectedEtablissement = $etablissementId ? Etablissement::find($etablissementId) : $etablissements->first();

        if (! $selectedEtablissement && $etablissements->isNotEmpty()) {
            $selectedEtablissement = $etablissements->first();
        }

        $selectedEtablissementId = $selectedEtablissement?->id;

        // 2. Groupes de matières (blocs pédagogiques) rattachés à l'établissement ou standards/partagés
        $groupesQuery = GroupeMatiere::withCount('matiereNiveaux')
            ->with('etablissement:id,nom,code')
            ->orderBy('ordre');

        if ($selectedEtablissementId) {
            $groupesQuery->where(function ($q) use ($selectedEtablissementId) {
                $q->where('etablissement_id', $selectedEtablissementId)
                    ->orWhereNull('etablissement_id');
            });
        }

        $groupes = $groupesQuery->get();

        // 3. Catalogue des disciplines disponibles pour cet établissement (tronc commun + spécifiques)
        $matieresQuery = Matiere::withCount('matiereNiveaux')
            ->with('etablissement:id,nom,code')
            ->orderBy('code');

        if ($selectedEtablissementId) {
            $matieresQuery->where(function ($q) use ($selectedEtablissementId) {
                $q->where('etablissement_id', $selectedEtablissementId)
                    ->orWhereNull('etablissement_id');
            });
        }

        $matieres = $matieresQuery->get();

        // 4. Niveaux scolaires appartenant à cet établissement pour le sélecteur
        $niveauxQuery = Niveau::with(['cycle.section.etablissement', 'etablissement'])
            ->orderBy('ordre');

        if ($selectedEtablissementId) {
            $niveauxQuery->where(function ($q) use ($selectedEtablissementId) {
                $q->where('etablissement_id', $selectedEtablissementId)
                    ->orWhereHas('cycle.section', function ($sq) use ($selectedEtablissementId) {
                        $sq->where('etablissement_id', $selectedEtablissementId);
                    });
            });
        }

        $niveaux = $niveauxQuery->get();

        // Niveau sélectionné (par défaut le premier niveau disponible dans l'établissement actif)
        $selectedNiveauId = $request->query('niveau_id');
        $selectedNiveau = $selectedNiveauId ? $niveaux->firstWhere('id', (int) $selectedNiveauId) : $niveaux->first();

        if (! $selectedNiveau && $niveaux->isNotEmpty()) {
            $selectedNiveau = $niveaux->first();
        }

        // 5. Séries disponibles pour ce niveau (extraites des classes du niveau ou des coefficients déjà paramétrés)
        $seriesDisponibles = collect(['Toutes']);
        if ($selectedNiveau) {
            $seriesFromClasses = $selectedNiveau->classes()->whereNotNull('serie')->pluck('serie')->unique();
            $seriesFromCoefficients = MatiereNiveau::where('niveau_id', $selectedNiveau->id)->pluck('serie')->unique();
            $seriesDisponibles = $seriesDisponibles
                ->merge($seriesFromClasses)
                ->merge($seriesFromCoefficients)
                ->filter()
                ->unique()
                ->values();
        }

        $selectedSerie = $request->query('serie', 'Toutes');

        // 6. Coefficients configurés pour le niveau sélectionné
        $coefficients = collect();
        if ($selectedNiveau) {
            $query = MatiereNiveau::with(['matiere', 'groupeMatiere'])
                ->where('niveau_id', $selectedNiveau->id);

            if ($selectedSerie && $selectedSerie !== 'Toutes') {
                $query->where(function ($q) use ($selectedSerie) {
                    $q->where('serie', $selectedSerie)->orWhere('serie', 'Toutes');
                });
            }

            $coefficients = $query->get()->sortBy([
                ['groupeMatiere.ordre', 'asc'],
                ['matiere.code', 'asc'],
            ])->values();
        }

        $user = $request->user();
        $canManage = $user ? ($user->hasRole('super-admin') || $user->hasRole('admin') || $user->email === 'admin@gmail.com' || $user->can('matieres.manage')) : false;

        return Inertia::render('administration/matieres/index', [
            'etablissements' => $etablissements,
            'selectedEtablissement' => $selectedEtablissement,
            'selectedEtablissementId' => $selectedEtablissementId,
            'groupes' => $groupes,
            'matieres' => $matieres,
            'niveaux' => $niveaux,
            'selectedNiveau' => $selectedNiveau,
            'seriesDisponibles' => $seriesDisponibles,
            'selectedSerie' => $selectedSerie,
            'coefficients' => $coefficients,
            'can' => [
                'matieres_manage' => $canManage,
            ],
        ]);
    }

    // =========================================================================
    // SECTION 1 : CRUD GROUPES DE MATIÈRES (BLOCS PÉDAGOGIQUES)
    // =========================================================================

    /**
     * Enregistre un nouveau groupe de matières (bloc pédagogique).
     * Associe le bloc à l'établissement actif ou en paramètre avec unicité locale.
     *
     * @param Request $request Requête HTTP avec données validées.
     * @return RedirectResponse Redirection avec message de confirmation.
     */
    public function storeGroupe(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('matieres.manage'), 403, 'Permission requise : matieres.manage');

        $etablissementId = $request->input('etablissement_id');

        $validated = $request->validate([
            'etablissement_id' => ['nullable', 'exists:etablissements,id'],
            'code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('groupes_matieres', 'code')->where('etablissement_id', $etablissementId),
            ],
            'nom' => ['required', 'string', 'max:120'],
            'nom_en' => ['nullable', 'string', 'max:120'],
            'couleur' => ['nullable', 'string', 'max:30'],
            'ordre' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        GroupeMatiere::create($validated);

        return back()->with('success', 'Bloc pédagogique créé avec succès.');
    }

    /**
     * Met à jour un groupe de matières existant.
     *
     * @param Request $request Requête HTTP avec données validées.
     * @param GroupeMatiere $groupe Instance du groupe à modifier.
     * @return RedirectResponse Redirection avec message de confirmation.
     */
    public function updateGroupe(Request $request, GroupeMatiere $groupe): RedirectResponse
    {
        abort_unless($request->user()->can('matieres.manage'), 403, 'Permission requise : matieres.manage');

        $etablissementId = $request->has('etablissement_id') ? $request->input('etablissement_id') : $groupe->etablissement_id;

        $validated = $request->validate([
            'etablissement_id' => ['nullable', 'exists:etablissements,id'],
            'code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('groupes_matieres', 'code')
                    ->where('etablissement_id', $etablissementId)
                    ->ignore($groupe->id),
            ],
            'nom' => ['required', 'string', 'max:120'],
            'nom_en' => ['nullable', 'string', 'max:120'],
            'couleur' => ['nullable', 'string', 'max:30'],
            'ordre' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $groupe->update($validated);

        return back()->with('success', 'Bloc pédagogique mis à jour avec succès.');
    }

    /**
     * Supprime un groupe de matières s'il n'est rattaché à aucun coefficient.
     *
     * @param Request $request Requête HTTP.
     * @param GroupeMatiere $groupe Instance du groupe à supprimer.
     * @return RedirectResponse Redirection avec statut de l'opération.
     */
    public function destroyGroupe(Request $request, GroupeMatiere $groupe): RedirectResponse
    {
        abort_unless($request->user()->can('matieres.manage'), 403, 'Permission requise : matieres.manage');

        if ($groupe->matiereNiveaux()->exists()) {
            return back()->with('error', 'Impossible de supprimer ce bloc car des coefficients de matières lui sont actuellement associés.');
        }

        $groupe->delete();

        return back()->with('success', 'Bloc pédagogique supprimé avec succès.');
    }

    // =========================================================================
    // SECTION 2 : CRUD CATALOGUE GÉNÉRAL DES MATIÈRES
    // =========================================================================

    /**
     * Enregistre une nouvelle discipline dans le catalogue général (spécifique ou tronc commun).
     *
     * @param Request $request Requête HTTP avec données validées.
     * @return RedirectResponse Redirection avec message de confirmation.
     */
    public function storeMatiere(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('matieres.manage'), 403, 'Permission requise : matieres.manage');

        $etablissementId = $request->input('etablissement_id');

        $validated = $request->validate([
            'etablissement_id' => ['nullable', 'exists:etablissements,id'],
            'code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('matieres', 'code')->where('etablissement_id', $etablissementId),
            ],
            'nom' => ['required', 'string', 'max:120'],
            'nom_en' => ['nullable', 'string', 'max:120'],
            'libelle_bulletin' => ['nullable', 'string', 'max:100'],
            'libelle_bulletin_en' => ['nullable', 'string', 'max:100'],
            'is_active' => ['boolean'],
        ]);

        if (empty($validated['libelle_bulletin'])) {
            $validated['libelle_bulletin'] = $validated['nom'];
        }

        if (empty($validated['libelle_bulletin_en']) && ! empty($validated['nom_en'])) {
            $validated['libelle_bulletin_en'] = $validated['nom_en'];
        }

        Matiere::create($validated);

        return back()->with('success', 'Matière ajoutée au catalogue avec succès.');
    }

    /**
     * Met à jour les informations d'une discipline du catalogue.
     *
     * @param Request $request Requête HTTP avec données validées.
     * @param Matiere $matiere Instance de la matière à modifier.
     * @return RedirectResponse Redirection avec message de confirmation.
     */
    public function updateMatiere(Request $request, Matiere $matiere): RedirectResponse
    {
        abort_unless($request->user()->can('matieres.manage'), 403, 'Permission requise : matieres.manage');

        $etablissementId = $request->has('etablissement_id') ? $request->input('etablissement_id') : $matiere->etablissement_id;

        $validated = $request->validate([
            'etablissement_id' => ['nullable', 'exists:etablissements,id'],
            'code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('matieres', 'code')
                    ->where('etablissement_id', $etablissementId)
                    ->ignore($matiere->id),
            ],
            'nom' => ['required', 'string', 'max:120'],
            'nom_en' => ['nullable', 'string', 'max:120'],
            'libelle_bulletin' => ['nullable', 'string', 'max:100'],
            'libelle_bulletin_en' => ['nullable', 'string', 'max:100'],
            'is_active' => ['boolean'],
        ]);

        if (empty($validated['libelle_bulletin'])) {
            $validated['libelle_bulletin'] = $validated['nom'];
        }

        if (empty($validated['libelle_bulletin_en']) && ! empty($validated['nom_en'])) {
            $validated['libelle_bulletin_en'] = $validated['nom_en'];
        }

        $matiere->update($validated);

        return back()->with('success', 'Matière mise à jour avec succès.');
    }

    /**
     * Supprime une matière du catalogue si elle n'est configurée dans aucun niveau.
     *
     * @param Request $request Requête HTTP.
     * @param Matiere $matiere Instance de la matière à supprimer.
     * @return RedirectResponse Redirection avec statut de l'opération.
     */
    public function destroyMatiere(Request $request, Matiere $matiere): RedirectResponse
    {
        abort_unless($request->user()->can('matieres.manage'), 403, 'Permission requise : matieres.manage');

        if ($matiere->matiereNiveaux()->exists()) {
            return back()->with('error', 'Impossible de supprimer cette matière car des coefficients de niveaux lui sont actuellement associés.');
        }

        $matiere->delete();

        return back()->with('success', 'Matière supprimée du catalogue avec succès.');
    }

    // =========================================================================
    // SECTION 3 : CRUD CONFIGURATION DES COEFFICIENTS PAR NIVEAU & SÉRIE
    // =========================================================================

    /**
     * Associe une matière à un niveau avec son coefficient et ses paramètres d'évaluation.
     *
     * @param Request $request Requête HTTP avec données validées.
     * @return RedirectResponse Redirection avec message de confirmation.
     */
    public function storeCoefficient(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('matieres.manage'), 403, 'Permission requise : matieres.manage');

        $validated = $request->validate([
            'niveau_id' => ['required', 'exists:niveaux,id'],
            'matiere_id' => ['required', 'exists:matieres,id'],
            'groupe_matiere_id' => ['required', 'exists:groupes_matieres,id'],
            'serie' => ['nullable', 'string', 'max:50'],
            'coefficient' => ['required', 'numeric', 'min:0.25', 'max:20'],
            'note_max' => ['required', 'numeric', 'min:1', 'max:100'],
            'note_eliminatoire' => ['nullable', 'numeric', 'min:0', 'max:20'],
            'is_facultative' => ['boolean'],
            'is_comptabilisee' => ['boolean'],
        ]);

        $validated['serie'] = $validated['serie'] ?: 'Toutes';

        // Unicité de la matière pour ce niveau et cette série
        $exists = MatiereNiveau::where('niveau_id', $validated['niveau_id'])
            ->where('matiere_id', $validated['matiere_id'])
            ->where('serie', $validated['serie'])
            ->exists();

        if ($exists) {
            return back()->withErrors(['matiere_id' => 'Cette matière est déjà configurée pour ce niveau et cette série.']);
        }

        MatiereNiveau::create($validated);

        return back()->with('success', 'Discipline ajoutée à la grille du niveau avec succès.');
    }

    /**
     * Met à jour les coefficients et règles d'évaluation d'une matière dans un niveau.
     *
     * @param Request $request Requête HTTP avec données validées.
     * @param MatiereNiveau $coefficient Instance de l'association à modifier.
     * @return RedirectResponse Redirection avec message de confirmation.
     */
    public function updateCoefficient(Request $request, MatiereNiveau $coefficient): RedirectResponse
    {
        abort_unless($request->user()->can('matieres.manage'), 403, 'Permission requise : matieres.manage');

        $validated = $request->validate([
            'groupe_matiere_id' => ['required', 'exists:groupes_matieres,id'],
            'serie' => ['nullable', 'string', 'max:50'],
            'coefficient' => ['required', 'numeric', 'min:0.25', 'max:20'],
            'note_max' => ['required', 'numeric', 'min:1', 'max:100'],
            'note_eliminatoire' => ['nullable', 'numeric', 'min:0', 'max:20'],
            'is_facultative' => ['boolean'],
            'is_comptabilisee' => ['boolean'],
        ]);

        $validated['serie'] = $validated['serie'] ?: 'Toutes';

        $coefficient->update($validated);

        return back()->with('success', 'Coefficient mis à jour avec succès.');
    }

    /**
     * Supprime une matière de la grille d'évaluation d'un niveau.
     *
     * @param Request $request Requête HTTP.
     * @param MatiereNiveau $coefficient Instance de l'association à supprimer.
     * @return RedirectResponse Redirection avec message de confirmation.
     */
    public function destroyCoefficient(Request $request, MatiereNiveau $coefficient): RedirectResponse
    {
        abort_unless($request->user()->can('matieres.manage'), 403, 'Permission requise : matieres.manage');

        $coefficient->delete();

        return back()->with('success', 'Matière retirée de la grille d\'évaluation.');
    }

    /**
     * Initialise les matières et blocs standards officiels adaptés à l'établissement.
     *
     * @param Request $request Requête HTTP.
     * @return RedirectResponse Redirection avec message de succès.
     */
    public function seedStandards(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('matieres.manage'), 403, 'Permission requise : matieres.manage');

        $etablissementId = $request->input('etablissement_id') ?? $request->query('etablissement_id');
        $etablissement = $etablissementId ? Etablissement::find($etablissementId) : Etablissement::first();
        $targetEtabId = $etablissement?->id;

        // 1. Blocs pédagogiques adaptés selon l'ordre d'enseignement et la langue de l'établissement
        $isPrimary = $etablissement && in_array($etablissement->ordre_enseignement, ['Maternelle', 'Primaire']);
        $isTechnical = $etablissement && $etablissement->ordre_enseignement === 'Secondaire_Technique';

        if ($isPrimary) {
            $blocs = [
                ['code' => 'DOM-LANG', 'nom' => 'Domaine 1 : Langues et Communication', 'nom_en' => 'Domain 1: Languages and Communication', 'couleur' => 'blue', 'ordre' => 1],
                ['code' => 'DOM-SCI', 'nom' => 'Domaine 2 : Sciences et Technologies', 'nom_en' => 'Domain 2: Sciences and Technology', 'couleur' => 'emerald', 'ordre' => 2],
                ['code' => 'DOM-HUM', 'nom' => 'Domaine 3 : Sciences Humaines et Sociales', 'nom_en' => 'Domain 3: Social Sciences', 'couleur' => 'amber', 'ordre' => 3],
                ['code' => 'DOM-DEV', 'nom' => 'Domaine 4 : Développement Personnel & Pratique', 'nom_en' => 'Domain 4: Personal Development', 'couleur' => 'purple', 'ordre' => 4],
            ];
        } elseif ($isTechnical) {
            $blocs = [
                ['code' => 'BLOC-GEN', 'nom' => 'Pôle Enseignements Généraux', 'nom_en' => 'General Education Block', 'couleur' => 'blue', 'ordre' => 1],
                ['code' => 'BLOC-SCI', 'nom' => 'Pôle Enseignements Scientifiques & Technologiques', 'nom_en' => 'Scientific & Technology Block', 'couleur' => 'emerald', 'ordre' => 2],
                ['code' => 'BLOC-PRO', 'nom' => 'Pôle Professionnel & Pratiques d\'Atelier', 'nom_en' => 'Vocational & Workshop Practices Block', 'couleur' => 'amber', 'ordre' => 3],
            ];
        } else {
            // Secondaire Général (MINESEC officiel)
            $blocs = [
                ['code' => 'BLOC-SCI', 'nom' => 'Enseignements Scientifiques & Technologiques', 'nom_en' => 'Scientific & Technological Subjects', 'couleur' => 'blue', 'ordre' => 1],
                ['code' => 'BLOC-LIT', 'nom' => 'Enseignements Littéraires & Sciences Humaines', 'nom_en' => 'Literary & Human Sciences Subjects', 'couleur' => 'emerald', 'ordre' => 2],
                ['code' => 'BLOC-DEV', 'nom' => 'Développement Personnel, Arts & Éducation Physique', 'nom_en' => 'Personal Development, Arts & Physical Education', 'couleur' => 'amber', 'ordre' => 3],
            ];
        }

        foreach ($blocs as $b) {
            GroupeMatiere::firstOrCreate(
                [
                    'etablissement_id' => $targetEtabId,
                    'code' => $b['code'],
                ],
                [
                    'nom' => $b['nom'],
                    'nom_en' => $b['nom_en'],
                    'couleur' => $b['couleur'],
                    'ordre' => $b['ordre'],
                ]
            );
        }

        // 2. Matières standards pour le tronc commun institutionnel (etablissement_id = null)
        $standards = [
            ['code' => 'MATH', 'nom' => 'Mathématiques', 'nom_en' => 'Mathematics'],
            ['code' => 'PCT', 'nom' => 'Physique - Chimie - Technologie', 'nom_en' => 'Physics - Chemistry - Technology'],
            ['code' => 'SVT', 'nom' => 'Sciences de la Vie et de la Terre', 'nom_en' => 'Life and Earth Sciences'],
            ['code' => 'INFO', 'nom' => 'Informatique', 'nom_en' => 'Computer Science'],
            ['code' => 'FRAN', 'nom' => 'Langue Française', 'nom_en' => 'French Language'],
            ['code' => 'LIT', 'nom' => 'Littérature & Culture', 'nom_en' => 'Literature & Culture'],
            ['code' => 'ANG', 'nom' => 'Anglais', 'nom_en' => 'English Language'],
            ['code' => 'HIST-GEO', 'nom' => 'Histoire - Géographie - ECM', 'nom_en' => 'History - Geography - Civics'],
            ['code' => 'EPS', 'nom' => 'Éducation Physique et Sportive', 'nom_en' => 'Physical & Sports Education'],
            ['code' => 'ARTS', 'nom' => 'Éducation Artistique & Dessin', 'nom_en' => 'Artistic Education & Drawing'],
            ['code' => 'TM', 'nom' => 'Travail Manuel & Économie Sociale', 'nom_en' => 'Manual Labor & Home Economics'],
        ];

        foreach ($standards as $s) {
            Matiere::firstOrCreate(
                [
                    'code' => $s['code'],
                    'etablissement_id' => null,
                ],
                [
                    'nom' => $s['nom'],
                    'nom_en' => $s['nom_en'],
                    'libelle_bulletin' => $s['nom'],
                    'libelle_bulletin_en' => $s['nom_en'],
                    'is_active' => true,
                ]
            );
        }

        return back()->with('success', 'Catalogue officiel et blocs standards de l\'établissement initialisés avec succès.');
    }
}
