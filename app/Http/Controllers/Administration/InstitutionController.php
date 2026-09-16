<?php

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use App\Models\Classe;
use App\Models\Cycle;
use App\Models\Etablissement;
use App\Models\Institution;
use App\Models\Niveau;
use App\Models\Section;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Contrôleur d'administration de l'Institution et de la Structure Pédagogique.
 *
 * Gère le parcours Drill-Down complet (Approche A) :
 * Institution -> Établissements -> Sections -> Cycles -> Niveaux -> Classes
 * ainsi que les opérations CRUD sécurisées à chaque palier.
 */
class InstitutionController extends Controller
{
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

        // Résolution du Drill-Down en fonction des paramètres d'exploration
        if ($etablissementId && $etablissement = Etablissement::find($etablissementId)) {
            $selectedEtablissement = $etablissement;
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
            'code' => ['required', 'string', 'max:30', Rule::unique('institutions', 'code')->ignore($institutionId)],
            'sigle' => ['nullable', 'string', 'max:50'],
            'nom' => ['required', 'string', 'max:150'],
            'nom_en' => ['nullable', 'string', 'max:150'],
            'devise' => ['nullable', 'string', 'max:150'],
            'devise_en' => ['nullable', 'string', 'max:150'],
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
     */
    public function storeSection(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('structures.manage'), 403, 'Permission requise : structures.manage');

        $validated = $request->validate([
            'etablissement_id' => ['required', 'exists:etablissements,id'],
            'code' => ['required', 'string', 'max:20'],
            'nom' => ['required', 'string', 'max:100'],
            'nom_en' => ['nullable', 'string', 'max:100'],
            'langue' => ['required', 'string', 'in:fr,en'],
        ]);

        // Vérification de l'unicité du code dans cet établissement
        $exists = Section::where('etablissement_id', $validated['etablissement_id'])
            ->where('code', Str::upper($validated['code']))
            ->exists();

        if ($exists) {
            return back()->with('error', 'Une section portant ce code existe déjà dans cet établissement.');
        }

        $validated['code'] = Str::upper($validated['code']);
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
            'code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('sections', 'code')
                    ->where('etablissement_id', $section->etablissement_id)
                    ->ignore($section->id),
            ],
            'nom' => ['required', 'string', 'max:100'],
            'nom_en' => ['nullable', 'string', 'max:100'],
            'langue' => ['required', 'string', 'in:fr,en'],
        ]);

        $validated['code'] = Str::upper($validated['code']);
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
     */
    public function storeCycle(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('structures.manage'), 403, 'Permission requise : structures.manage');

        $validated = $request->validate([
            'section_id' => ['required', 'exists:sections,id'],
            'code' => ['required', 'string', 'max:20'],
            'nom' => ['required', 'string', 'max:100'],
            'nom_en' => ['nullable', 'string', 'max:100'],
            'diplome_fin_cycle' => ['nullable', 'string', 'max:50'],
            'ordre' => ['required', 'integer', 'min:1'],
        ]);

        $exists = Cycle::where('section_id', $validated['section_id'])
            ->where('code', Str::upper($validated['code']))
            ->exists();

        if ($exists) {
            return back()->with('error', 'Un cycle avec ce code existe déjà dans cette section.');
        }

        $validated['code'] = Str::upper($validated['code']);
        Cycle::create($validated);

        return back()->with('success', 'Le cycle « ' . $validated['nom'] . ' » a été créé avec succès.');
    }

    /**
     * Modifie un cycle pédagogique.
     */
    public function updateCycle(Request $request, Cycle $cycle): RedirectResponse
    {
        abort_unless($request->user()->can('structures.manage'), 403, 'Permission requise : structures.manage');

        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('cycles', 'code')
                    ->where('section_id', $cycle->section_id)
                    ->ignore($cycle->id),
            ],
            'nom' => ['required', 'string', 'max:100'],
            'nom_en' => ['nullable', 'string', 'max:100'],
            'diplome_fin_cycle' => ['nullable', 'string', 'max:50'],
            'ordre' => ['required', 'integer', 'min:1'],
        ]);

        $validated['code'] = Str::upper($validated['code']);
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
     */
    public function storeNiveau(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('structures.manage'), 403, 'Permission requise : structures.manage');

        $validated = $request->validate([
            'cycle_id' => ['required', 'exists:cycles,id'],
            'code' => ['required', 'string', 'max:20'],
            'nom' => ['required', 'string', 'max:100'],
            'nom_en' => ['nullable', 'string', 'max:100'],
            'ordre' => ['required', 'integer', 'min:1'],
        ]);

        $exists = Niveau::where('cycle_id', $validated['cycle_id'])
            ->where('code', Str::upper($validated['code']))
            ->exists();

        if ($exists) {
            return back()->with('error', 'Un niveau avec ce code existe déjà dans ce cycle.');
        }

        $validated['code'] = Str::upper($validated['code']);
        Niveau::create($validated);

        return back()->with('success', 'Le niveau « ' . $validated['nom'] . ' » a été créé avec succès.');
    }

    /**
     * Modifie un niveau d'étude existant.
     */
    public function updateNiveau(Request $request, Niveau $niveau): RedirectResponse
    {
        abort_unless($request->user()->can('structures.manage'), 403, 'Permission requise : structures.manage');

        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('niveaux', 'code')
                    ->where('cycle_id', $niveau->cycle_id)
                    ->ignore($niveau->id),
            ],
            'nom' => ['required', 'string', 'max:100'],
            'nom_en' => ['nullable', 'string', 'max:100'],
            'ordre' => ['required', 'integer', 'min:1'],
        ]);

        $validated['code'] = Str::upper($validated['code']);
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
        $classe->update($validated);

        return back()->with('success', 'La classe « ' . $classe->nom . ' » a été mise à jour avec succès.');
    }

    /**
     * Supprime une classe.
     */
    public function destroyClasse(Request $request, Classe $classe): RedirectResponse
    {
        abort_unless($request->user()->can('classes.manage'), 403, 'Permission requise : classes.manage');

        $nom = $classe->nom;
        $niveau = $classe->niveau;
        $cycle = $niveau->cycle;
        $section = $cycle->section;

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
