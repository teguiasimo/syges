<?php

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use App\Models\GroupeMatiere;
use App\Models\Matiere;
use App\Models\MatiereNiveau;
use App\Models\Niveau;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
class MatiereController extends Controller
{
    /**
     * Affiche l'interface de gestion des matières, groupes et coefficients.
     *
     * @param Request $request Requête HTTP entrante.
     * @return Response Vue Inertia avec les données du catalogue et de la grille.
     */
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('matieres.view'), 403, 'Accès non autorisé au catalogue des matières.');

        // 1. Groupes de matières (blocs pédagogiques) ordonnés avec décompte des disciplines associées
        $groupes = GroupeMatiere::withCount('matiereNiveaux')
            ->orderBy('ordre')
            ->get();

        // 2. Catalogue complet des disciplines
        $matieres = Matiere::withCount('matiereNiveaux')
            ->orderBy('code')
            ->get();

        // 3. Niveaux scolaires avec arborescence cycle et établissement pour le sélecteur
        $niveaux = Niveau::with(['cycle.section.etablissement'])
            ->orderBy('ordre')
            ->get();

        // Niveau sélectionné (par défaut le premier niveau disponible)
        $selectedNiveauId = $request->query('niveau_id');
        $selectedNiveau = $selectedNiveauId ? $niveaux->firstWhere('id', (int) $selectedNiveauId) : $niveaux->first();

        if (! $selectedNiveau && $niveaux->isNotEmpty()) {
            $selectedNiveau = $niveaux->first();
        }

        // 4. Séries disponibles pour ce niveau (extraites des classes du niveau ou des coefficients déjà paramétrés)
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

        // 5. Coefficients configurés pour le niveau sélectionné
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
     *
     * @param Request $request Requête HTTP avec données validées.
     * @return RedirectResponse Redirection avec message de confirmation.
     */
    public function storeGroupe(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('matieres.manage'), 403, 'Permission requise : matieres.manage');

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:30', 'unique:groupes_matieres,code'],
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

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:30', Rule::unique('groupes_matieres', 'code')->ignore($groupe->id)],
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
     * Enregistre une nouvelle discipline dans le catalogue général.
     *
     * @param Request $request Requête HTTP avec données validées.
     * @return RedirectResponse Redirection avec message de confirmation.
     */
    public function storeMatiere(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('matieres.manage'), 403, 'Permission requise : matieres.manage');

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:30', 'unique:matieres,code'],
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

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:30', Rule::unique('matieres', 'code')->ignore($matiere->id)],
            'nom' => ['required', 'string', 'max:120'],
            'nom_en' => ['nullable', 'string', 'max:120'],
            'libelle_bulletin' => ['nullable', 'string', 'max:100'],
            'libelle_bulletin_en' => ['nullable', 'string', 'max:100'],
            'is_active' => ['boolean'],
        ]);

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
            return back()->with('error', 'Impossible de supprimer cette discipline car elle est configurée dans des grilles de coefficients.');
        }

        $matiere->delete();

        return back()->with('success', 'Matière retirée du catalogue avec succès.');
    }

    // =========================================================================
    // SECTION 3 : CRUD CONFIGURATION DES COEFFICIENTS (MOTEUR D'ÉVALUATION)
    // =========================================================================

    /**
     * Associe une matière à un niveau/série avec ses coefficients et règles de calcul.
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
            'coefficient' => ['required', 'numeric', 'min:0.25', 'max:50'],
            'note_max' => ['required', 'numeric', 'min:1', 'max:100'],
            'note_eliminatoire' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_facultative' => ['boolean'],
            'is_comptabilisee' => ['boolean'],
        ]);

        $serie = empty($validated['serie']) ? 'Toutes' : $validated['serie'];
        $validated['serie'] = $serie;

        // Vérification de l'unicité de la matière pour ce niveau et cette série
        $exists = MatiereNiveau::where('niveau_id', $validated['niveau_id'])
            ->where('matiere_id', $validated['matiere_id'])
            ->where('serie', $serie)
            ->exists();

        if ($exists) {
            return back()->with('error', 'Cette matière est déjà configurée pour ce niveau et cette série.');
        }

        MatiereNiveau::create($validated);

        return back()->with('success', 'Coefficient configuré avec succès pour ce niveau.');
    }

    /**
     * Met à jour la configuration d'un coefficient.
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
            'coefficient' => ['required', 'numeric', 'min:0.25', 'max:50'],
            'note_max' => ['required', 'numeric', 'min:1', 'max:100'],
            'note_eliminatoire' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_facultative' => ['boolean'],
            'is_comptabilisee' => ['boolean'],
        ]);

        $serie = empty($validated['serie']) ? 'Toutes' : $validated['serie'];
        $validated['serie'] = $serie;

        // Vérification de l'unicité si la série est modifiée
        $exists = MatiereNiveau::where('niveau_id', $coefficient->niveau_id)
            ->where('matiere_id', $coefficient->matiere_id)
            ->where('serie', $serie)
            ->where('id', '!=', $coefficient->id)
            ->exists();

        if ($exists) {
            return back()->with('error', 'Une configuration existe déjà pour cette discipline sur cette série.');
        }

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
     * Initialise les matières et blocs standards officiels camerounais si absents.
     *
     * @param Request $request Requête HTTP.
     * @return RedirectResponse Redirection avec message de succès.
     */
    public function seedStandards(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('matieres.manage'), 403, 'Permission requise : matieres.manage');

        // 1. Blocs pédagogiques standard
        $groupeSci = GroupeMatiere::firstOrCreate(
            ['code' => 'BLOC-SCI'],
            ['nom' => 'Enseignements Scientifiques & Technologiques', 'nom_en' => 'Scientific & Technological Subjects', 'couleur' => 'blue', 'ordre' => 1]
        );

        $groupeLit = GroupeMatiere::firstOrCreate(
            ['code' => 'BLOC-LIT'],
            ['nom' => 'Enseignements Littéraires & Sciences Humaines', 'nom_en' => 'Literary & Human Sciences Subjects', 'couleur' => 'emerald', 'ordre' => 2]
        );

        $groupeDev = GroupeMatiere::firstOrCreate(
            ['code' => 'BLOC-DEV'],
            ['nom' => 'Développement Personnel, Arts & Éducation Physique', 'nom_en' => 'Personal Development, Arts & Physical Education', 'couleur' => 'amber', 'ordre' => 3]
        );

        // 2. Matières standards
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
                ['code' => $s['code']],
                [
                    'nom' => $s['nom'],
                    'nom_en' => $s['nom_en'],
                    'libelle_bulletin' => $s['nom'],
                    'libelle_bulletin_en' => $s['nom_en'],
                    'is_active' => true,
                ]
            );
        }

        return back()->with('success', 'Catalogue officiel et blocs standards initialisés avec succès.');
    }
}
