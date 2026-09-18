<?php

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use App\Models\Classe;
use App\Models\Cycle;
use App\Models\Etablissement;
use App\Models\Institution;
use App\Models\Niveau;
use App\Models\Section;
use App\Models\Serie;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Contrôleur d'administration de l'Institution et de la Structure Pédagogique.
 *
 * Gère le parcours Drill-Down complet (Approche A) :
 * Institution -> Établissements -> Sections -> Cycles -> Niveaux -> Classes
 * ainsi que les opérations CRUD sécurisées à chaque palier et pour les Séries personnalisées.
 */
class InstitutionController extends Controller implements HasMiddleware
{
    /**
     * Définition de la matrice des middlewares d'autorisation pour le contrôleur Institution.
     *
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:institutions.view', only: ['index']),
            new Middleware('can:institutions.edit', only: ['editInstitution', 'updateInstitution']),
            new Middleware('can:etablissements.manage', only: [
                'storeEtablissement',
                'updateEtablissement',
                'destroyEtablissement',
                'editEtablissementSettings',
                'updateEtablissementSettings',
            ]),
            new Middleware('can:structures.manage', only: [
                'storeSection', 'updateSection', 'destroySection',
                'storeCycle', 'updateCycle', 'destroyCycle',
                'storeNiveau', 'updateNiveau', 'destroyNiveau',
                'storeSerie', 'updateSerie', 'destroySerie',
            ]),
            new Middleware('can:classes.manage', only: ['storeClasse', 'updateClasse', 'destroyClasse', 'toggleClasseStatus']),
        ];
    }
    /**
     * Affiche la vue principale du module avec le palier actif et son fil d'Ariane.
     *
     * @param Request $request Requête HTTP contenant les paramètres de navigation (Drill-Down).
     * @return Response Vue Inertia avec les données du niveau sélectionné.
     */
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('institutions.view'), 403, 'Accès non autorisé au module Institution.');

        // Récupération de l'institution unique (création d'un enregistrement par défaut si absent)
        $institution = Institution::first() ?? Institution::create([
            'code' => 'INST-01',
            'nom' => 'Institution Scolaire Principale',
            'nom_en' => 'Main Educational Institution',
            'devise' => 'Discipline - Travail - Succès',
            'devise_en' => 'Discipline - Work - Success',
            'ville' => 'Yaoundé',
        ]);

        $etablissementId = $request->query('etablissement_id');
        $sectionId = $request->query('section_id');
        $cycleId = $request->query('cycle_id');
        $niveauId = $request->query('niveau_id');

        // Initialisation de la sélection active et du fil d'Ariane
        $selectedEtablissement = null;
        $selectedSection = null;
        $selectedCycle = null;
        $selectedNiveau = null;

        $etablissements = Etablissement::where('institution_id', $institution->id)
            ->withCount(['sections'])
            ->get();

        $sections = collect();
        $cycles = collect();
        $niveaux = collect();
        $classes = collect();
        $series = collect();

        // Résolution du Drill-Down en fonction des paramètres d'exploration
        if ($etablissementId && $etablissement = Etablissement::find($etablissementId)) {
            $selectedEtablissement = $etablissement;
            // Assure la présence des séries par défaut pour cet établissement
            Serie::seedDefaultsForEtablissement($etablissement->id);
            $series = Serie::where('etablissement_id', $etablissement->id)
                ->orderBy('id')
                ->get();
            $sections = Section::where('etablissement_id', $etablissement->id)
                ->withCount(['cycles'])
                ->get();

            if ($sectionId && $section = Section::where('etablissement_id', $etablissement->id)->find($sectionId)) {
                $selectedSection = $section;
                $cycles = Cycle::where('section_id', $section->id)
                    ->withCount(['niveaux'])
                    ->orderBy('ordre')
                    ->get();

                if ($cycleId && $cycle = Cycle::where('section_id', $section->id)->find($cycleId)) {
                    $selectedCycle = $cycle;
                    $niveaux = Niveau::where('cycle_id', $cycle->id)
                        ->withCount(['classes'])
                        ->orderBy('ordre')
                        ->get();

                    if ($niveauId && $niveau = Niveau::where('cycle_id', $cycle->id)->find($niveauId)) {
                        $selectedNiveau = $niveau;
                        $classes = Classe::where('niveau_id', $niveau->id)
                            ->withCount(['attributions', 'inscriptions', 'seancesEmploiTemps'])
                            ->get();
                    }
                }
            }
        }

        // Construction du fil d'Ariane dynamique
        $breadcrumbs = [
            [
                'label' => $institution->nom,
                'level' => 'institution',
                'params' => [],
            ],
        ];

        if ($selectedEtablissement) {
            $breadcrumbs[] = [
                'label' => $selectedEtablissement->nom,
                'level' => 'etablissement',
                'params' => ['etablissement_id' => $selectedEtablissement->id],
            ];
        }

        if ($selectedSection) {
            $breadcrumbs[] = [
                'label' => $selectedSection->nom,
                'level' => 'section',
                'params' => [
                    'etablissement_id' => $selectedEtablissement->id,
                    'section_id' => $selectedSection->id,
                ],
            ];
        }

        if ($selectedCycle) {
            $breadcrumbs[] = [
                'label' => $selectedCycle->nom,
                'level' => 'cycle',
                'params' => [
                    'etablissement_id' => $selectedEtablissement->id,
                    'section_id' => $selectedSection->id,
                    'cycle_id' => $selectedCycle->id,
                ],
            ];
        }

        if ($selectedNiveau) {
            $breadcrumbs[] = [
                'label' => $selectedNiveau->nom,
                'level' => 'niveau',
                'params' => [
                    'etablissement_id' => $selectedEtablissement->id,
                    'section_id' => $selectedSection->id,
                    'cycle_id' => $selectedCycle->id,
                    'niveau_id' => $selectedNiveau->id,
                ],
            ];
        }

        return Inertia::render('administration/institution/index', [
            'institution' => $institution,
            'etablissements' => $etablissements,
            'sections' => $sections,
            'cycles' => $cycles,
            'niveaux' => $niveaux,
            'classes' => $classes,
            'series' => $series,
            'selectedEtablissement' => $selectedEtablissement,
            'selectedSection' => $selectedSection,
            'selectedCycle' => $selectedCycle,
            'selectedNiveau' => $selectedNiveau,
            'breadcrumbs' => $breadcrumbs,
            'can' => [
                'institutions_edit' => $request->user()->can('institutions.edit'),
                'etablissements_manage' => $request->user()->can('etablissements.manage'),
                'structures_manage' => $request->user()->can('structures.manage'),
                'classes_manage' => $request->user()->can('classes.manage'),
            ],
        ]);
    }

    /**
     * Affiche la page dédiée de création ou de modification de l'institution faîtière.
     *
     * Offre un environnement spacieux et ergonomique pour renseigner la multitude de champs
     * (identité bilingue, tutelles ministérielles, découpages géographiques, contacts officiels).
     *
     * @param Request $request Requête HTTP entrante.
     * @return Response Vue Inertia dédiée du formulaire institution.
     */
    public function editInstitution(Request $request): Response
    {
        abort_unless($request->user()->can('institutions.edit'), 403, 'Permission requise : institutions.edit');

        // Récupère l'institution existante ou initialise une nouvelle instance avec des valeurs par défaut
        $institution = Institution::first() ?? new Institution([
            'code' => 'INST-01',
            'nom' => 'Institution Scolaire Principale',
            'nom_en' => 'Main Educational Institution',
            'devise' => 'Discipline - Travail - Succès',
            'devise_en' => 'Discipline - Work - Success',
            'ville' => 'Yaoundé',
        ]);

        return Inertia::render('administration/institution/edit', [
            'institution' => $institution,
        ]);
    }

    /**
     * Enregistre ou met à jour les informations globales de l'institution.
     *
     * @param Request $request Requête HTTP avec données validées.
     * @return RedirectResponse Redirection vers la vue d'ensemble avec message de confirmation.
     */
    public function updateInstitution(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('institutions.edit'), 403, 'Permission requise : institutions.edit');

        $institution = Institution::first();
        $institutionId = $institution?->id;

        $validated = $request->validate([
            'code' => ['nullable', 'string', 'max:30', Rule::unique('institutions', 'code')->ignore($institutionId)],
            'sigle' => ['nullable', 'string', 'max:50'],
            'nom' => ['required', 'string', 'max:150'],
            'nom_en' => ['nullable', 'string', 'max:150'],
            'devise' => ['nullable', 'string', 'max:150'],
            'devise_en' => ['nullable', 'string', 'max:150'],
            'logo' => ['nullable', 'string', 'max:255'],
            'logo_file' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,svg,webp', 'max:2048'],
            'email' => ['nullable', 'email', 'max:100'],
            'telephone_1' => ['nullable', 'string', 'max:30'],
            'telephone_2' => ['nullable', 'string', 'max:30'],
            'boite_postale' => ['nullable', 'string', 'max:50'],
            'site_web' => ['nullable', 'string', 'max:150'],
            'region' => ['nullable', 'string', 'max:100'],
            'region_en' => ['nullable', 'string', 'max:100'],
            'departement' => ['nullable', 'string', 'max:100'],
            'departement_en' => ['nullable', 'string', 'max:100'],
            'arrondissement' => ['nullable', 'string', 'max:100'],
            'arrondissement_en' => ['nullable', 'string', 'max:100'],
            'ville' => ['nullable', 'string', 'max:100'],
            'delegation_regionale' => ['nullable', 'string', 'max:150'],
            'delegation_regionale_en' => ['nullable', 'string', 'max:150'],
            'delegation_departementale' => ['nullable', 'string', 'max:150'],
            'delegation_departementale_en' => ['nullable', 'string', 'max:150'],
        ]);

        // Conservation du code technique existant ou auto-génération intelligente
        if (empty($validated['code'])) {
            if ($institution && !empty($institution->code)) {
                $validated['code'] = $institution->code;
            } else {
                $base = !empty($validated['sigle']) ? $validated['sigle'] : substr($validated['nom'], 0, 6);
                $cleanBase = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $base));
                $validated['code'] = !empty($cleanBase) ? $cleanBase : 'INST-' . rand(100, 999);
            }
        }

        // Traitement de l'upload de logo avec stockage public
        if ($request->hasFile('logo_file')) {
            $path = $request->file('logo_file')->store('logos', 'public');
            $validated['logo'] = Storage::url($path);
        }
        unset($validated['logo_file']);

        if ($institution) {
            $institution->update($validated);
        } else {
            Institution::create($validated);
        }

        return redirect()->route('administration.institution.index')
            ->with('success', 'Les informations de l\'institution ont été enregistrées avec succès.');
    }

    /**
     * Enregistre un nouvel établissement scolaire.
     */
    public function storeEtablissement(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('etablissements.manage'), 403, 'Permission requise : etablissements.manage');

        $institution = Institution::firstOrFail();

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:30', 'unique:etablissements,code'],
            'nom' => ['required', 'string', 'max:150'],
            'nom_en' => ['nullable', 'string', 'max:150'],
            'statut_juridique' => ['required', 'string', 'in:Public,Prive_Laic,Prive_Confessionnel'],
            'ordre_enseignement' => ['required', 'string', 'in:Maternelle,Primaire,Secondaire_General,Secondaire_Technique,Polyvalent'],
            'decret_creation' => ['nullable', 'string', 'max:150'],
            'nom_chef_etablissement' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:100'],
            'telephone_1' => ['nullable', 'string', 'max:30'],
            'telephone_2' => ['nullable', 'string', 'max:30'],
            'adresse' => ['nullable', 'string', 'max:150'],
        ]);

        $validated['institution_id'] = $institution->id;
        $validated['mode_bulletin_defaut'] = 'sans_competences';
        $validated['type_calcul_trimestre'] = 'arithmetique';
        $validated['seuil_absences_decheance_mentions'] = 40;
        $validated['verrou_solvabilite_actif'] = false;
        $validated['bonus_paiement_anticipe_actif'] = false;
        $validated['montant_bonus_anticipe'] = 3000;
        Etablissement::create($validated);

        return back()->with('success', 'L\'établissement « ' . $validated['nom'] . ' » a été créé avec succès.');
    }

    /**
     * Modifie un établissement scolaire existant.
     */
    public function updateEtablissement(Request $request, Etablissement $etablissement): RedirectResponse
    {
        abort_unless($request->user()->can('etablissements.manage'), 403, 'Permission requise : etablissements.manage');

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:30', Rule::unique('etablissements', 'code')->ignore($etablissement->id)],
            'nom' => ['required', 'string', 'max:150'],
            'nom_en' => ['nullable', 'string', 'max:150'],
            'statut_juridique' => ['required', 'string', 'in:Public,Prive_Laic,Prive_Confessionnel'],
            'ordre_enseignement' => ['required', 'string', 'in:Maternelle,Primaire,Secondaire_General,Secondaire_Technique,Polyvalent'],
            'decret_creation' => ['nullable', 'string', 'max:150'],
            'nom_chef_etablissement' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:100'],
            'telephone_1' => ['nullable', 'string', 'max:30'],
            'telephone_2' => ['nullable', 'string', 'max:30'],
            'adresse' => ['nullable', 'string', 'max:150'],
        ]);

        $etablissement->update($validated);

        return back()->with('success', 'L\'établissement « ' . $etablissement->nom . ' » a été mis à jour avec succès.');
    }

    /**
     * Affiche la page dédiée de configuration et règles avancées d'un établissement scolaire.
     *
     * Offre une interface ergonomique pour régler le format des bulletins (standard sans compétences par défaut / APC),
     * les règles de calcul des moyennes, les seuils d'absences disciplinaires et les verrous de solvabilité financière.
     *
     * @param Request $request Requête HTTP entrante.
     * @param Etablissement $etablissement Établissement scolaire ciblé.
     * @return Response Vue Inertia des paramètres avancés.
     */
    public function editEtablissementSettings(Request $request, Etablissement $etablissement): Response
    {
        abort_unless($request->user()->can('etablissements.manage'), 403, 'Permission requise : etablissements.manage');

        $etablissement->load('institution');

        return Inertia::render('administration/institution/etablissement-settings', [
            'etablissement' => $etablissement,
        ]);
    }

    /**
     * Enregistre l'ensemble des paramètres pédagogiques, disciplinaires et financiers de l'établissement.
     *
     * @param Request $request Requête HTTP avec données validées.
     * @param Etablissement $etablissement Établissement scolaire à mettre à jour.
     * @return RedirectResponse Redirection vers la même vue avec confirmation visuelle.
     */
    public function updateEtablissementSettings(Request $request, Etablissement $etablissement): RedirectResponse
    {
        abort_unless($request->user()->can('etablissements.manage'), 403, 'Permission requise : etablissements.manage');

        $validated = $request->validate([
            // Identité & Coordonnées
            'code' => ['required', 'string', 'max:30', Rule::unique('etablissements', 'code')->ignore($etablissement->id)],
            'nom' => ['required', 'string', 'max:150'],
            'nom_en' => ['nullable', 'string', 'max:150'],
            'statut_juridique' => ['required', 'string', 'in:Public,Prive_Laic,Prive_Confessionnel'],
            'ordre_enseignement' => ['required', 'string', 'in:Maternelle,Primaire,Secondaire_General,Secondaire_Technique,Polyvalent'],
            'decret_creation' => ['nullable', 'string', 'max:150'],
            'nom_chef_etablissement' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:100'],
            'telephone_1' => ['nullable', 'string', 'max:30'],
            'telephone_2' => ['nullable', 'string', 'max:30'],
            'adresse' => ['nullable', 'string', 'max:150'],

            // Paramètres Pédagogiques & Bulletins (Module 10)
            'mode_bulletin_defaut' => ['required', 'string', 'in:sans_competences,avec_competences'],
            'type_calcul_trimestre' => ['required', 'string', 'in:arithmetique,pondere'],

            // Discipline & Assiduité (Module 12)
            'seuil_absences_decheance_mentions' => ['required', 'integer', 'min:0', 'max:200'],

            // Caisse & Solvabilité Financière (Module 11)
            'verrou_solvabilite_actif' => ['required', 'boolean'],
            'bonus_paiement_anticipe_actif' => ['required', 'boolean'],
            'montant_bonus_anticipe' => ['nullable', 'numeric', 'min:0'],
            'date_limite_bonus' => ['nullable', 'date'],
        ]);

        $etablissement->update($validated);

        return back()->with('success', 'Les paramètres et règles de l\'établissement « ' . $etablissement->nom . ' » ont été enregistrés avec succès.');
    }

    /**
     * Supprime un établissement s'il ne contient aucune section.
     */
    public function destroyEtablissement(Request $request, Etablissement $etablissement): RedirectResponse
    {
        abort_unless($request->user()->can('etablissements.manage'), 403, 'Permission requise : etablissements.manage');

        if ($etablissement->sections()->exists()) {
            return back()->with('error', 'Suppression impossible : cet établissement contient encore des sections pédagogiques.');
        }

        $nom = $etablissement->nom;
        $etablissement->delete();

        return redirect()->route('administration.institution.index')
            ->with('success', "L'établissement « {$nom} » a été supprimé avec succès.");
    }

    /**
     * Enregistre une nouvelle section pédagogique.
     * Le code technique est généré automatiquement (FR, EN ou FR-2) s'il n'est pas fourni.
     */
    public function storeSection(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('structures.manage'), 403, 'Permission requise : structures.manage');

        $validated = $request->validate([
            'etablissement_id' => ['required', 'exists:etablissements,id'],
            'nom' => [
                'required',
                'string',
                'max:100',
                Rule::unique('sections', 'nom')->where('etablissement_id', $request->input('etablissement_id')),
            ],
            'langue' => ['required', 'string', 'in:fr,en'],
            'code' => ['nullable', 'string', 'max:20'],
        ], [
            'nom.required' => 'Le nom de la section est obligatoire.',
            'nom.unique' => 'Une section portant ce nom existe déjà dans cet établissement.',
            'langue.required' => 'Veuillez sélectionner la langue d’enseignement.',
        ]);

        // Vérification insensible à la casse pour interdire les doublons typographiques
        $normalizedNom = mb_strtolower(trim($validated['nom']));
        $duplicate = Section::where('etablissement_id', $validated['etablissement_id'])
            ->whereRaw('LOWER(nom) = ?', [$normalizedNom])
            ->exists();

        if ($duplicate) {
            return back()->withErrors([
                'nom' => 'Une section portant ce nom existe déjà dans cet établissement.',
            ]);
        }

        // Auto-génération intelligente du code court si non fourni
        if (empty($validated['code'])) {
            $nomLower = mb_strtolower($validated['nom']);
            if (Str::contains($nomLower, ['francophone', 'français'])) {
                $baseCode = 'FR';
            } elseif (Str::contains($nomLower, ['anglophone', 'anglais', 'english'])) {
                $baseCode = 'EN';
            } elseif (Str::contains($nomLower, 'technique')) {
                $baseCode = 'TECH';
            } elseif (Str::contains($nomLower, 'bilingue')) {
                $baseCode = 'BIL';
            } elseif (Str::contains($nomLower, 'commercial')) {
                $baseCode = 'COM';
            } else {
                $words = array_filter(explode(' ', $validated['nom']));
                $nonCommon = array_filter($words, fn($w) => !in_array(mb_strtolower($w), ['section', 'de', 'la', 'le', 'l', 'd', 'et', 'en']));
                $first = !empty($nonCommon) ? reset($nonCommon) : reset($words);
                $clean = Str::upper(substr(preg_replace('/[^A-Za-z0-9]/', '', (string)$first), 0, 4));
                $baseCode = !empty($clean) ? $clean : Str::upper($validated['langue']);
            }

            $codeCandidate = $baseCode;
            $counter = 1;

            while (Section::where('etablissement_id', $validated['etablissement_id'])->where('code', $codeCandidate)->exists()) {
                $counter++;
                $codeCandidate = $baseCode . '-' . $counter;
            }
            $validated['code'] = $codeCandidate;
        } else {
            $validated['code'] = Str::upper($validated['code']);
            $exists = Section::where('etablissement_id', $validated['etablissement_id'])
                ->where('code', $validated['code'])
                ->exists();

            if ($exists) {
                return back()->with('error', 'Une section portant ce code existe déjà dans cet établissement.');
            }
        }

        Section::create($validated);

        return back()->with('success', 'La section « ' . $validated['nom'] . ' » a été ajoutée avec succès.');
    }

    /**
     * Modifie une section pédagogique.
     */
    public function updateSection(Request $request, Section $section): RedirectResponse
    {
        abort_unless($request->user()->can('structures.manage'), 403, 'Permission requise : structures.manage');

        $validated = $request->validate([
            'nom' => [
                'required',
                'string',
                'max:100',
                Rule::unique('sections', 'nom')
                    ->where('etablissement_id', $section->etablissement_id)
                    ->ignore($section->id),
            ],
            'langue' => ['required', 'string', 'in:fr,en'],
            'code' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('sections', 'code')
                    ->where('etablissement_id', $section->etablissement_id)
                    ->ignore($section->id),
            ],
        ], [
            'nom.required' => 'Le nom de la section est obligatoire.',
            'nom.unique' => 'Une section portant ce nom existe déjà dans cet établissement.',
            'langue.required' => 'Veuillez sélectionner la langue d’enseignement.',
        ]);

        // Vérification insensible à la casse pour les modifications
        $normalizedNom = mb_strtolower(trim($validated['nom']));
        $duplicate = Section::where('etablissement_id', $section->etablissement_id)
            ->where('id', '!=', $section->id)
            ->whereRaw('LOWER(nom) = ?', [$normalizedNom])
            ->exists();

        if ($duplicate) {
            return back()->withErrors([
                'nom' => 'Une section portant ce nom existe déjà dans cet établissement.',
            ]);
        }

        if (empty($validated['code'])) {
            $validated['code'] = $section->code;
        } else {
            $validated['code'] = Str::upper($validated['code']);
        }

        $section->update($validated);

        return back()->with('success', 'La section « ' . $section->nom . ' » a été modifiée avec succès.');
    }

    /**
     * Supprime une section pédagogique si elle est vide.
     */
    public function destroySection(Request $request, Section $section): RedirectResponse
    {
        abort_unless($request->user()->can('structures.manage'), 403, 'Permission requise : structures.manage');

        if ($section->cycles()->exists()) {
            return back()->with('error', 'Suppression impossible : cette section contient encore des cycles pédagogiques.');
        }

        $nom = $section->nom;
        $etablissementId = $section->etablissement_id;
        $section->delete();

        return redirect()->route('administration.institution.index', ['etablissement_id' => $etablissementId])
            ->with('success', "La section « {$nom} » a été supprimée avec succès.");
    }

    /**
     * Enregistre un nouveau cycle pédagogique.
     * Le code technique est auto-généré si non spécifié, et le nom du cycle doit être unique par établissement.
     *
     * @param Request $request Requête HTTP avec données validées.
     * @return RedirectResponse Redirection avec notification toast de confirmation.
     */
    public function storeCycle(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('structures.manage'), 403, 'Permission requise : structures.manage');

        $section = Section::findOrFail($request->input('section_id'));
        $etablissementId = $section->etablissement_id;

        $validated = $request->validate([
            'section_id' => ['required', 'exists:sections,id'],
            'nom' => [
                'required',
                'string',
                'max:100',
                Rule::unique('cycles', 'nom')->where('etablissement_id', $etablissementId),
            ],
            'nom_en' => ['nullable', 'string', 'max:100'],
            'diplome_fin_cycle' => ['nullable', 'string', 'max:50'],
            'ordre' => ['required', 'integer', 'min:1'],
            'code' => ['nullable', 'string', 'max:20'],
        ], [
            'nom.required' => 'Le nom du cycle est obligatoire.',
            'nom.unique' => 'Un cycle portant ce nom existe déjà dans cet établissement.',
            'ordre.required' => 'L’ordre chronologique est obligatoire.',
            'ordre.min' => 'L’ordre chronologique doit être supérieur ou égal à 1.',
        ]);

        // Vérification insensible à la casse au niveau de l'établissement
        $normalizedNom = mb_strtolower(trim($validated['nom']));
        $duplicate = Cycle::where('etablissement_id', $etablissementId)
            ->whereRaw('LOWER(nom) = ?', [$normalizedNom])
            ->exists();

        if ($duplicate) {
            return back()->withErrors([
                'nom' => 'Un cycle portant ce nom existe déjà dans cet établissement.',
            ]);
        }

        $validated['etablissement_id'] = $etablissementId;

        // Auto-génération transparente du code technique
        if (empty($validated['code'])) {
            $baseCode = 'CYC' . $validated['ordre'];
            $codeCandidate = $baseCode;
            $counter = 1;
            while (Cycle::where('section_id', $validated['section_id'])->where('code', $codeCandidate)->exists()) {
                $counter++;
                $codeCandidate = $baseCode . '-' . $counter;
            }
            $validated['code'] = $codeCandidate;
        } else {
            $validated['code'] = Str::upper($validated['code']);
        }

        // Si "AUCUN" est sélectionné ou si la valeur est vide, on stocke null
        if (empty($validated['diplome_fin_cycle']) || Str::upper($validated['diplome_fin_cycle']) === 'AUCUN') {
            $validated['diplome_fin_cycle'] = null;
        }

        Cycle::create($validated);

        return back()->with('success', 'Le cycle « ' . $validated['nom'] . ' » a été créé avec succès.');
    }

    /**
     * Modifie un cycle pédagogique existant.
     *
     * @param Request $request Requête HTTP avec données validées.
     * @param Cycle $cycle Cycle d'enseignement à modifier.
     * @return RedirectResponse Redirection avec notification toast.
     */
    public function updateCycle(Request $request, Cycle $cycle): RedirectResponse
    {
        abort_unless($request->user()->can('structures.manage'), 403, 'Permission requise : structures.manage');

        $etablissementId = $cycle->etablissement_id ?? $cycle->section->etablissement_id;

        $validated = $request->validate([
            'nom' => [
                'required',
                'string',
                'max:100',
                Rule::unique('cycles', 'nom')
                    ->where('etablissement_id', $etablissementId)
                    ->ignore($cycle->id),
            ],
            'nom_en' => ['nullable', 'string', 'max:100'],
            'diplome_fin_cycle' => ['nullable', 'string', 'max:50'],
            'ordre' => ['required', 'integer', 'min:1'],
            'code' => ['nullable', 'string', 'max:20'],
        ], [
            'nom.required' => 'Le nom du cycle est obligatoire.',
            'nom.unique' => 'Un cycle portant ce nom existe déjà dans cet établissement.',
            'ordre.required' => 'L’ordre chronologique est obligatoire.',
            'ordre.min' => 'L’ordre chronologique doit être supérieur ou égal à 1.',
        ]);

        // Vérification insensible à la casse au niveau de l'établissement
        $normalizedNom = mb_strtolower(trim($validated['nom']));
        $duplicate = Cycle::where('etablissement_id', $etablissementId)
            ->where('id', '!=', $cycle->id)
            ->whereRaw('LOWER(nom) = ?', [$normalizedNom])
            ->exists();

        if ($duplicate) {
            return back()->withErrors([
                'nom' => 'Un cycle portant ce nom existe déjà dans cet établissement.',
            ]);
        }

        $validated['etablissement_id'] = $etablissementId;

        if (empty($validated['code'])) {
            $validated['code'] = $cycle->code;
        } else {
            $validated['code'] = Str::upper($validated['code']);
        }

        // Si "AUCUN" est sélectionné ou si la valeur est vide, on stocke null
        if (empty($validated['diplome_fin_cycle']) || Str::upper($validated['diplome_fin_cycle']) === 'AUCUN') {
            $validated['diplome_fin_cycle'] = null;
        }

        $cycle->update($validated);

        return back()->with('success', 'Le cycle « ' . $cycle->nom . ' » a été mis à jour avec succès.');
    }

    /**
     * Supprime un cycle s'il ne contient pas de niveaux.
     */
    public function destroyCycle(Request $request, Cycle $cycle): RedirectResponse
    {
        abort_unless($request->user()->can('structures.manage'), 403, 'Permission requise : structures.manage');

        if ($cycle->niveaux()->exists()) {
            return back()->with('error', 'Suppression impossible : ce cycle contient encore des niveaux d\'étude.');
        }

        $nom = $cycle->nom;
        $section = $cycle->section;
        $cycle->delete();

        return redirect()->route('administration.institution.index', [
            'etablissement_id' => $section->etablissement_id,
            'section_id' => $section->id,
        ])->with('success', "Le cycle « {$nom} » a été supprimé avec succès.");
    }

    /**
     * Enregistre un nouveau niveau d'étude.
     * Le code technique est auto-généré si omis, et le nom du niveau doit être unique par établissement.
     *
     * @param Request $request Requête HTTP avec données validées.
     * @return RedirectResponse Redirection avec notification toast de confirmation.
     */
    public function storeNiveau(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('structures.manage'), 403, 'Permission requise : structures.manage');

        $cycle = Cycle::findOrFail($request->input('cycle_id'));
        $etablissementId = $cycle->etablissement_id ?? $cycle->section->etablissement_id;

        $validated = $request->validate([
            'cycle_id' => ['required', 'exists:cycles,id'],
            'nom' => [
                'required',
                'string',
                'max:100',
                Rule::unique('niveaux', 'nom')->where('etablissement_id', $etablissementId),
            ],
            'nom_en' => ['nullable', 'string', 'max:100'],
            'ordre' => ['required', 'integer', 'min:1'],
            'code' => ['nullable', 'string', 'max:20'],
        ], [
            'nom.required' => 'Le nom du niveau est obligatoire.',
            'nom.unique' => 'Un niveau portant ce nom existe déjà dans cet établissement.',
            'ordre.required' => 'L’ordre de progression est obligatoire.',
            'ordre.min' => 'L’ordre de progression doit être supérieur ou égal à 1.',
        ]);

        // Vérification insensible à la casse au niveau de l'établissement
        $normalizedNom = mb_strtolower(trim($validated['nom']));
        $duplicate = Niveau::where('etablissement_id', $etablissementId)
            ->whereRaw('LOWER(nom) = ?', [$normalizedNom])
            ->exists();

        if ($duplicate) {
            return back()->withErrors([
                'nom' => 'Un niveau portant ce nom existe déjà dans cet établissement.',
            ]);
        }

        $validated['etablissement_id'] = $etablissementId;

        // Auto-génération transparente du code technique si non fourni
        if (empty($validated['code'])) {
            $nomLower = mb_strtolower($validated['nom']);
            if (Str::contains($nomLower, 'sixi') || Str::contains($nomLower, '6')) {
                $baseCode = '6EME';
            } elseif (Str::contains($nomLower, 'cinqui') || Str::contains($nomLower, '5')) {
                $baseCode = '5EME';
            } elseif (Str::contains($nomLower, 'quatri') || Str::contains($nomLower, '4')) {
                $baseCode = '4EME';
            } elseif (Str::contains($nomLower, 'troisi') || Str::contains($nomLower, '3')) {
                $baseCode = '3EME';
            } elseif (Str::contains($nomLower, 'second') || Str::contains($nomLower, '2nde')) {
                $baseCode = '2NDE';
            } elseif (Str::contains($nomLower, 'premi') || Str::contains($nomLower, '1ere')) {
                $baseCode = '1ERE';
            } elseif (Str::contains($nomLower, 'termin') || Str::contains($nomLower, 'tle')) {
                $baseCode = 'TLE';
            } elseif (preg_match('/form\s*(\d+)/i', $validated['nom'], $matches)) {
                $baseCode = 'F' . $matches[1];
            } elseif (preg_match('/class\s*(\d+)/i', $validated['nom'], $matches)) {
                $baseCode = 'CL' . $matches[1];
            } elseif (in_array(Str::upper($validated['nom']), ['SIL', 'CP', 'CE1', 'CE2', 'CM1', 'CM2'])) {
                $baseCode = Str::upper($validated['nom']);
            } else {
                $clean = Str::upper(substr(preg_replace('/[^A-Za-z0-9]/', '', (string)$validated['nom']), 0, 4));
                $baseCode = !empty($clean) ? $clean : 'NIV' . $validated['ordre'];
            }

            $codeCandidate = $baseCode;
            $counter = 1;
            while (Niveau::where('cycle_id', $validated['cycle_id'])->where('code', $codeCandidate)->exists()) {
                $counter++;
                $codeCandidate = $baseCode . '-' . $counter;
            }
            $validated['code'] = $codeCandidate;
        } else {
            $validated['code'] = Str::upper($validated['code']);
        }

        Niveau::create($validated);

        return back()->with('success', 'Le niveau « ' . $validated['nom'] . ' » a été créé avec succès.');
    }

    /**
     * Modifie un niveau d'étude existant.
     *
     * @param Request $request Requête HTTP avec données validées.
     * @param Niveau $niveau Niveau d'étude à modifier.
     * @return RedirectResponse Redirection avec notification toast.
     */
    public function updateNiveau(Request $request, Niveau $niveau): RedirectResponse
    {
        abort_unless($request->user()->can('structures.manage'), 403, 'Permission requise : structures.manage');

        $etablissementId = $niveau->etablissement_id ?? $niveau->cycle->etablissement_id ?? $niveau->cycle->section->etablissement_id;

        $validated = $request->validate([
            'nom' => [
                'required',
                'string',
                'max:100',
                Rule::unique('niveaux', 'nom')
                    ->where('etablissement_id', $etablissementId)
                    ->ignore($niveau->id),
            ],
            'nom_en' => ['nullable', 'string', 'max:100'],
            'ordre' => ['required', 'integer', 'min:1'],
            'code' => ['nullable', 'string', 'max:20'],
        ], [
            'nom.required' => 'Le nom du niveau est obligatoire.',
            'nom.unique' => 'Un niveau portant ce nom existe déjà dans cet établissement.',
            'ordre.required' => 'L’ordre de progression est obligatoire.',
            'ordre.min' => 'L’ordre de progression doit être supérieur ou égal à 1.',
        ]);

        // Vérification insensible à la casse au niveau de l'établissement
        $normalizedNom = mb_strtolower(trim($validated['nom']));
        $duplicate = Niveau::where('etablissement_id', $etablissementId)
            ->where('id', '!=', $niveau->id)
            ->whereRaw('LOWER(nom) = ?', [$normalizedNom])
            ->exists();

        if ($duplicate) {
            return back()->withErrors([
                'nom' => 'Un niveau portant ce nom existe déjà dans cet établissement.',
            ]);
        }

        $validated['etablissement_id'] = $etablissementId;

        if (empty($validated['code'])) {
            $validated['code'] = $niveau->code;
        } else {
            $validated['code'] = Str::upper($validated['code']);
        }

        $niveau->update($validated);

        return back()->with('success', 'Le niveau « ' . $niveau->nom . ' » a été mis à jour avec succès.');
    }

    /**
     * Supprime un niveau d'étude s'il ne contient pas de classes.
     */
    public function destroyNiveau(Request $request, Niveau $niveau): RedirectResponse
    {
        abort_unless($request->user()->can('structures.manage'), 403, 'Permission requise : structures.manage');

        if ($niveau->classes()->exists()) {
            return back()->with('error', 'Suppression impossible : ce niveau contient encore des classes actives.');
        }

        $nom = $niveau->nom;
        $cycle = $niveau->cycle;
        $section = $cycle->section;
        $niveau->delete();

        return redirect()->route('administration.institution.index', [
            'etablissement_id' => $section->etablissement_id,
            'section_id' => $section->id,
            'cycle_id' => $cycle->id,
        ])->with('success', "Le niveau « {$nom} » a été supprimé avec succès.");
    }

    /**
     * Enregistre une nouvelle série pédagogique pour l'établissement.
     *
     * @param Request $request Requête HTTP contenant les données de la série.
     * @return RedirectResponse Redirection avec message toast.
     */
    public function storeSerie(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('structures.manage'), 403, 'Permission requise : structures.manage');

        if ($request->has('nom')) {
            $request->merge(['nom' => Str::upper(trim($request->input('nom')))]);
        }

        $validated = $request->validate([
            'etablissement_id' => ['required', 'exists:etablissements,id'],
            'nom' => [
                'required',
                'string',
                'max:50',
                Rule::unique('series', 'nom')->where('etablissement_id', $request->input('etablissement_id')),
            ],
        ], [
            'nom.required' => 'Le nom de la série est obligatoire (ex: A4 ALL, C, D).',
            'nom.unique' => 'Cette série existe déjà pour cet établissement.',
        ]);

        $serie = Serie::create($validated);

        return back()->with('success', "La série « {$serie->nom} » a été ajoutée avec succès.");
    }

    /**
     * Met à jour une série pédagogique existante.
     *
     * @param Request $request Requête HTTP contenant les modifications.
     * @param Serie $serie Série ciblée.
     * @return RedirectResponse Redirection avec message toast.
     */
    public function updateSerie(Request $request, Serie $serie): RedirectResponse
    {
        abort_unless($request->user()->can('structures.manage'), 403, 'Permission requise : structures.manage');

        if ($request->has('nom')) {
            $request->merge(['nom' => Str::upper(trim($request->input('nom')))]);
        }

        $validated = $request->validate([
            'nom' => [
                'required',
                'string',
                'max:50',
                Rule::unique('series', 'nom')
                    ->where('etablissement_id', $serie->etablissement_id)
                    ->ignore($serie->id),
            ],
        ], [
            'nom.required' => 'Le nom de la série est obligatoire.',
            'nom.unique' => 'Cette série existe déjà pour cet établissement.',
        ]);

        $serie->update($validated);

        return back()->with('success', "La série « {$serie->nom} » a été mise à jour avec succès.");
    }

    /**
     * Supprime une série pédagogique si elle n'est pas utilisée par des classes.
     *
     * @param Request $request Requête HTTP.
     * @param Serie $serie Série ciblée.
     * @return RedirectResponse Redirection avec message toast explicite.
     */
    public function destroySerie(Request $request, Serie $serie): RedirectResponse
    {
        abort_unless($request->user()->can('structures.manage'), 403, 'Permission requise : structures.manage');

        // Vérification si des classes de cet établissement utilisent cette série
        $usedInClasses = Classe::where('serie', $serie->nom)
            ->whereHas('niveau.cycle.section', function ($q) use ($serie) {
                $q->where('etablissement_id', $serie->etablissement_id);
            })
            ->exists();

        if ($usedInClasses) {
            return back()->with('error', "Suppression impossible : la série « {$serie->nom} » est actuellement assignée à une ou plusieurs classes.");
        }

        $nom = $serie->nom;
        $serie->delete();

        return back()->with('success', "La série « {$nom} » a été supprimée avec succès.");
    }

    /**
     * Enregistre une nouvelle classe.
     */
    public function storeClasse(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('classes.manage'), 403, 'Permission requise : classes.manage');

        $validated = $request->validate([
            'niveau_id' => ['required', 'exists:niveaux,id'],
            'code' => ['required', 'string', 'max:30'],
            'nom' => ['required', 'string', 'max:100'],
            'serie' => ['nullable', 'string', 'max:50'],
            'capacite_max' => ['required', 'integer', 'min:1', 'max:150'],
        ]);

        $exists = Classe::where('niveau_id', $validated['niveau_id'])
            ->where('code', Str::upper($validated['code']))
            ->exists();

        if ($exists) {
            return back()->with('error', 'Une classe portant ce code existe déjà dans ce niveau.');
        }

        $validated['code'] = Str::upper($validated['code']);
        $validated['serie'] = empty($validated['serie']) ? 'GENERAL' : Str::upper(trim($validated['serie']));
        $validated['is_active'] = true;
        Classe::create($validated);

        return back()->with('success', 'La classe « ' . $validated['nom'] . ' » a été créée avec succès.');
    }

    /**
     * Modifie une classe existante.
     */
    public function updateClasse(Request $request, Classe $classe): RedirectResponse
    {
        abort_unless($request->user()->can('classes.manage'), 403, 'Permission requise : classes.manage');

        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('classes', 'code')
                    ->where('niveau_id', $classe->niveau_id)
                    ->ignore($classe->id),
            ],
            'nom' => ['required', 'string', 'max:100'],
            'serie' => ['nullable', 'string', 'max:50'],
            'capacite_max' => ['required', 'integer', 'min:1', 'max:150'],
        ]);

        $validated['code'] = Str::upper($validated['code']);
        $validated['serie'] = empty($validated['serie']) ? 'GENERAL' : Str::upper(trim($validated['serie']));
        $classe->update($validated);

        return back()->with('success', 'La classe « ' . $classe->nom . ' » a été mise à jour avec succès.');
    }

    /**
     * Supprime une classe d'élèves.
     *
     * Règle de gestion stricte d'intégrité pédagogique :
     * Une classe ne peut être supprimée QUE si elle ne contient AUCUNE attribution de matière/professeur,
     * aucune inscription d'élève, aucun cours programmé ni délibération de conseil.
     * Si des dépendances actives existent, la suppression est bloquée et l'utilisateur est invité à la désactiver.
     *
     * @param Request $request Requête HTTP validée.
     * @param Classe $classe Classe ciblée.
     * @return RedirectResponse Redirection avec message toast explicite.
     */
    public function destroyClasse(Request $request, Classe $classe): RedirectResponse
    {
        abort_unless($request->user()->can('classes.manage'), 403, 'Permission requise : classes.manage');

        $nom = $classe->nom;
        $niveau = $classe->niveau;
        $cycle = $niveau->cycle;
        $section = $cycle->section;

        $blockers = $classe->getDeletionBlockers();

        if (! empty($blockers)) {
            $details = [];
            if (isset($blockers['attributions'])) {
                $details[] = $blockers['attributions'] . ' attribution(s) de matière(s)/enseignant(s)';
            }
            if (isset($blockers['inscriptions'])) {
                $details[] = $blockers['inscriptions'] . ' élève(s) inscrit(s)';
            }
            if (isset($blockers['seances'])) {
                $details[] = $blockers['seances'] . ' cours à l’emploi du temps';
            }
            if (isset($blockers['deliberations'])) {
                $details[] = $blockers['deliberations'] . ' délibération(s) de conseil';
            }

            return back()->with('error', "Suppression impossible : la classe « {$nom} » possède des données pédagogiques actives (" . implode(', ', $details) . "). Pour préserver l'intégrité du cursus et l'historique scolaire, veuillez la désactiver plutôt que de la supprimer.");
        }

        $classe->delete();

        return redirect()->route('administration.institution.index', [
            'etablissement_id' => $section->etablissement_id,
            'section_id' => $section->id,
            'cycle_id' => $cycle->id,
            'niveau_id' => $niveau->id,
        ])->with('success', "La classe « {$nom} » a été supprimée avec succès.");
    }

    /**
     * Bascule le statut d'activation d'une classe (Actif / Inactif).
     */
    public function toggleClasseStatus(Request $request, Classe $classe): RedirectResponse
    {
        abort_unless($request->user()->can('classes.manage'), 403, 'Permission requise : classes.manage');

        $classe->is_active = ! $classe->is_active;
        $classe->save();

        $action = $classe->is_active ? 'réactivée' : 'désactivée';

        return back()->with('success', "La classe « {$classe->nom} » a été {$action} avec succès.");
    }
}
