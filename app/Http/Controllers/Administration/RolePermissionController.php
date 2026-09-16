<?php

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Contrôleur de gestion des rôles et des permissions de l'application SYGES.
 *
 * Fournit les opérations CRUD complètes sur les profils d'accès (rôles)
 * et permet la synchronisation granulaire des permissions organisées par module scolaire.
 */
class RolePermissionController extends Controller
{
    /**
     * Dictionnaire des libellés conviviaux pour les rôles prédéfinis du système.
     *
     * @var array<string, string>
     */
    protected array $roleLabels = [
        'super-admin' => 'Super Administrateur',
        'admin' => 'Administrateur',
        'directeur-etudes' => 'Directeur des Études',
        'enseignant' => 'Enseignant',
        'secretaire' => 'Secrétaire',
    ];

    /**
     * Dictionnaire de description des permissions avec leurs modules parents.
     *
     * @var array<string, array{module: string, label: string, description: string}>
     */
    protected array $permissionDefinitions = [
        // Module Utilisateurs & Profils
        'users.view' => [
            'module' => 'users',
            'label' => 'Consulter les utilisateurs',
            'description' => 'Visualiser les listes et les fiches des personnels et utilisateurs.',
        ],
        'users.create' => [
            'module' => 'users',
            'label' => 'Créer des utilisateurs',
            'description' => 'Ajouter de nouveaux utilisateurs dans la plateforme.',
        ],
        'users.edit' => [
            'module' => 'users',
            'label' => 'Modifier les utilisateurs',
            'description' => 'Mettre à jour les coordonnées et les informations des comptes.',
        ],
        'users.block' => [
            'module' => 'users',
            'label' => 'Bloquer des utilisateurs',
            'description' => 'Suspendre ou réactiver l\'accès d\'un utilisateur à la plateforme.',
        ],

        // Module Rôles & Permissions
        'roles.view' => [
            'module' => 'roles',
            'label' => 'Consulter les rôles',
            'description' => 'Voir la liste des rôles existants et leurs permissions.',
        ],
        'roles.create' => [
            'module' => 'roles',
            'label' => 'Créer des rôles',
            'description' => 'Créer de nouveaux profils d\'accès dans le système.',
        ],
        'roles.edit' => [
            'module' => 'roles',
            'label' => 'Modifier les rôles',
            'description' => 'Modifier le nom et les permissions assignées à un rôle.',
        ],
        'roles.delete' => [
            'module' => 'roles',
            'label' => 'Supprimer des rôles',
            'description' => 'Supprimer les rôles personnalisés non utilisés.',
        ],

        // Module Institution & Structure Pédagogique
        'institutions.view' => [
            'module' => 'institution',
            'label' => 'Consulter l\'institution',
            'description' => 'Visualiser les informations officielles de l\'institution et de ses campus.',
        ],
        'institutions.edit' => [
            'module' => 'institution',
            'label' => 'Modifier l\'institution',
            'description' => 'Mettre à jour les coordonnées globales, le logo et les tutelles administratives.',
        ],
        'etablissements.manage' => [
            'module' => 'institution',
            'label' => 'Gérer les établissements',
            'description' => 'Créer, modifier et paramétrer les campus scolaires.',
        ],
        'structures.manage' => [
            'module' => 'institution',
            'label' => 'Gérer l\'arborescence pédagogique',
            'description' => 'Créer et configurer les sections, cycles et niveaux d\'étude.',
        ],
        'classes.manage' => [
            'module' => 'institution',
            'label' => 'Gérer les classes',
            'description' => 'Créer, configurer et administrer les classes de l\'établissement.',
        ],

        // Module Périodes & Paramètres d'Évaluation (Gesco 3)
        'periodes.view' => [
            'module' => 'periodes',
            'label' => 'Consulter les années & périodes',
            'description' => 'Consulter les années scolaires, trimestres, séquences et modes de calcul.',
        ],
        'periodes.manage' => [
            'module' => 'periodes',
            'label' => 'Gérer les années & périodes',
            'description' => 'Créer, modifier, basculer l\'année courante et paramétrer les trimestres et séquences.',
        ],
        'periodes.lock' => [
            'module' => 'periodes',
            'label' => 'Verrouiller / Déverrouiller les notes',
            'description' => 'Ouvrir ou verrouiller manuellement la saisie des notes pour les enseignants.',
        ],

        // Module Matières & Coefficients (Gesco 4)
        'matieres.view' => [
            'module' => 'matieres',
            'label' => 'Consulter les matières & coefficients',
            'description' => 'Visualiser le catalogue des disciplines, les blocs pédagogiques et les coefficients.',
        ],
        'matieres.manage' => [
            'module' => 'matieres',
            'label' => 'Gérer les matières & coefficients',
            'description' => 'Créer et modifier les matières du catalogue, les blocs et configurer les coefficients par niveau/série.',
        ],

        // Module 8 : Personnel & Ressources Humaines (Gesco 8)
        'personnels.view' => [
            'module' => 'personnels',
            'label' => 'Consulter le personnel & RH',
            'description' => 'Visualiser le registre des employés, fiches administratives et départements.',
        ],
        'personnels.manage' => [
            'module' => 'personnels',
            'label' => 'Gérer le personnel & RH',
            'description' => 'Créer, modifier les membres du personnel et créer leurs comptes d\'accès.',
        ],
        'personnels.delete' => [
            'module' => 'personnels',
            'label' => 'Supprimer un membre du personnel',
            'description' => 'Archiver ou supprimer une fiche de personnel.',
        ],
        'departements.manage' => [
            'module' => 'personnels',
            'label' => 'Gérer les départements pédagogiques',
            'description' => 'Créer, modifier et désigner les animateurs des départements.',
        ],

        // Module 9 : Attribution des Classes & Matières (Gesco 9)
        'attributions.view' => [
            'module' => 'attributions',
            'label' => 'Consulter les attributions de cours',
            'description' => 'Visualiser la répartition des cours par classe et la charge de travail des enseignants.',
        ],
        'attributions.manage' => [
            'module' => 'attributions',
            'label' => 'Gérer les attributions & professeurs principaux',
            'description' => 'Affecter les enseignants aux classes/matières et désigner les professeurs principaux.',
        ],
        'attributions.delete' => [
            'module' => 'attributions',
            'label' => 'Révoquer une attribution de cours',
            'description' => 'Supprimer ou dissocier l\'affectation d\'un enseignant à une classe.',
        ],

        // Module 10 : Saisie des Notes & Calcul des Bulletins (Gesco 10)
        'notes.view' => [
            'module' => 'evaluations',
            'label' => 'Consulter les grilles de notes',
            'description' => 'Visualiser les notes d\'évaluation et bilans séquentiels.',
        ],
        'notes.enter' => [
            'module' => 'evaluations',
            'label' => 'Saisir et modifier les notes',
            'description' => 'Enregistrer les notes, présences, appréciations et compétences APC.',
        ],
        'notes.lock' => [
            'module' => 'evaluations',
            'label' => 'Verrouiller / Déverrouiller la saisie',
            'description' => 'Verrouiller définitivement ou autoriser la modification exceptionnelle des notes.',
        ],
        'bulletins.view' => [
            'module' => 'bulletins',
            'label' => 'Consulter les bulletins scolaires',
            'description' => 'Visualiser les moyennes générales, rangs et synthèses trimestrielles.',
        ],
        'bulletins.generate' => [
            'module' => 'bulletins',
            'label' => 'Générer et exporter les bulletins PDF',
            'description' => 'Télécharger les bulletins individuels scellés ou le cahier complet de la classe.',
        ],
    ];

    /**
     * Structure des modules thématiques regroupant les permissions disponibles.
     * Seuls les modules actuellement implémentés sont conservés.
     *
     * @var array<string, array{id: string, name: string, description: string, icon: string}>
     */
    protected array $modules = [
        'institution' => [
            'id' => 'institution',
            'name' => 'Institution & Structure Pédagogique',
            'description' => 'Organisation de l\'institution, campus, sections, cycles, niveaux et classes.',
            'icon' => 'School',
        ],
        'periodes' => [
            'id' => 'periodes',
            'name' => 'Périodes & Évaluation',
            'description' => 'Années scolaires, trimestres, séquences d\'évaluation et règles de calcul des moyennes.',
            'icon' => 'CalendarRange',
        ],
        'matieres' => [
            'id' => 'matieres',
            'name' => 'Matières & Coefficients',
            'description' => 'Catalogue des disciplines, blocs pédagogiques et grille des coefficients.',
            'icon' => 'BookOpen',
        ],
        'personnels' => [
            'id' => 'personnels',
            'name' => 'Personnel & Ressources Humaines (RH)',
            'description' => 'Registre des employés, fiches administratives, contrats et départements pédagogiques.',
            'icon' => 'Briefcase',
        ],
        'attributions' => [
            'id' => 'attributions',
            'name' => 'Attribution des Classes & Matières',
            'description' => 'Répartition pédagogique des enseignements, charges horaires et professeurs principaux.',
            'icon' => 'Layers',
        ],
        'users' => [
            'id' => 'users',
            'name' => 'Utilisateurs & Profils',
            'description' => 'Gestion des comptes, des personnels et des utilisateurs.',
            'icon' => 'Users',
        ],
        'roles' => [
            'id' => 'roles',
            'name' => 'Rôles & Permissions',
            'description' => 'Contrôle d\'accès granulaire et gestion des profils de sécurité.',
            'icon' => 'ShieldCheck',
        ],
    ];

    /**
     * Affiche la liste des rôles avec leurs statistiques et l'ensemble des permissions structurées.
     *
     * @param Request $request La requête HTTP entrante.
     * @return Response La vue Inertia avec la liste des rôles, la structure des modules et les droits d'action.
     */
    public function index(Request $request): Response
    {
        // Contrôle d'habilitation : consultation des rôles & permissions
        abort_unless($request->user()->can('roles.view'), 403, 'Accès non autorisé : vous ne disposez pas de la permission requise pour consulter les rôles.');

        // 1. Récupération des rôles existants avec le comptage des utilisateurs associés
        $roles = Role::with('permissions')
            ->withCount('users')
            ->orderBy('id')
            ->get()
            ->map(function (Role $role) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'displayName' => $this->roleLabels[$role->name] ?? Str::headline($role->name),
                    'isSystem' => in_array($role->name, ['super-admin', 'admin']),
                    'isSuperAdmin' => $role->name === 'super-admin',
                    'usersCount' => $role->users_count,
                    'permissionsCount' => $role->permissions->count(),
                    'permissions' => $role->permissions->pluck('name')->toArray(),
                    'createdAt' => $role->created_at?->format('d/m/Y'),
                ];
            });

        // 2. Récupération de l'ensemble des permissions et organisation par module
        $allPermissions = Permission::orderBy('name')->get();
        $groupedModules = [];

        foreach ($this->modules as $moduleId => $moduleData) {
            $modulePermissions = [];

            foreach ($allPermissions as $permission) {
                $definition = $this->permissionDefinitions[$permission->name] ?? null;

                if ($definition && $definition['module'] === $moduleId) {
                    $modulePermissions[] = [
                        'id' => $permission->id,
                        'name' => $permission->name,
                        'label' => $definition['label'],
                        'description' => $definition['description'],
                    ];
                }
            }

            $groupedModules[] = [
                ...$moduleData,
                'permissions' => $modulePermissions,
            ];
        }

        return Inertia::render('administration/roles/index', [
            'roles' => $roles,
            'modules' => $groupedModules,
            'totalPermissions' => $allPermissions->count(),
            'can' => [
                'create' => $request->user()->can('roles.create'),
                'edit' => $request->user()->can('roles.edit'),
                'delete' => $request->user()->can('roles.delete'),
            ],
        ]);
    }

    /**
     * Enregistre un nouveau rôle personnalisé et lui associe ses permissions.
     *
     * @param Request $request La requête HTTP contenant le nom et les permissions sélectionnées.
     * @return RedirectResponse Redirection vers la liste des rôles avec un message flash.
     */
    public function store(Request $request): RedirectResponse
    {
        // Contrôle d'habilitation : création de rôle
        abort_unless($request->user()->can('roles.create'), 403, 'Accès non autorisé : vous ne disposez pas de la permission requise pour créer un rôle.');

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'min:2',
                'max:50',
                'regex:/^[a-zA-Z0-9\-_ ]+$/',
                'unique:roles,name',
            ],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ], [
            'name.required' => 'Le nom du rôle est obligatoire.',
            'name.unique' => 'Ce nom de rôle existe déjà.',
            'name.regex' => 'Le nom contient des caractères non autorisés (lettres, chiffres, tirets et espaces uniquement).',
        ]);

        // Génération d'un nom de rôle normalisé (slug) ou conservation d'un format propre
        $roleSlug = Str::slug($validated['name']);

        // Vérification de la disponibilité du slug si différent du nom saisi
        if (Role::where('name', $roleSlug)->exists()) {
            return back()->withErrors(['name' => 'Un rôle avec cet identifiant existe déjà.'])->withInput();
        }

        // Création du rôle Spatie
        $role = Role::create([
            'name' => $roleSlug,
            'guard_name' => 'web',
        ]);

        // Synchronisation des permissions accordées
        if (! empty($validated['permissions'])) {
            $role->syncPermissions($validated['permissions']);
        }

        return redirect()->route('administration.roles.index')
            ->with('success', "Le rôle « {$validated['name']} » a été créé avec succès.");
    }

    /**
     * Met à jour le nom et les permissions d'un rôle existant.
     * Protège le rôle Super Administrateur contre les modifications critiques.
     *
     * @param Request $request La requête HTTP de mise à jour.
     * @param Role $role Le rôle à mettre à jour.
     * @return RedirectResponse Redirection vers la liste des rôles avec message de confirmation.
     */
    public function update(Request $request, Role $role): RedirectResponse
    {
        // Contrôle d'habilitation : modification de rôle
        abort_unless($request->user()->can('roles.edit'), 403, 'Accès non autorisé : vous ne disposez pas de la permission requise pour modifier les permissions de ce rôle.');

        $isSuperAdmin = $role->name === 'super-admin';

        $validated = $request->validate([
            'name' => [
                'sometimes',
                'required',
                'string',
                'min:2',
                'max:50',
                'regex:/^[a-zA-Z0-9\-_ ]+$/',
                Rule::unique('roles', 'name')->ignore($role->id),
            ],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ], [
            'name.required' => 'Le nom du rôle est obligatoire.',
            'name.unique' => 'Ce nom de rôle est déjà utilisé.',
            'name.regex' => 'Le nom contient des caractères non autorisés.',
        ]);

        // Mise à jour du nom du rôle si fourni
        if (isset($validated['name'])) {
            // Protection : Le rôle super-admin ne peut jamais être renommé
            if ($isSuperAdmin && Str::slug($validated['name']) !== 'super-admin') {
                return back()->withErrors(['name' => 'Le rôle Super Administrateur est protégé et ne peut pas être renommé.']);
            }

            if (! $isSuperAdmin) {
                $role->name = Str::slug($validated['name']);
                $role->save();
            }
        }

        // Synchronisation des permissions (le super-admin conserve toujours toutes les permissions)
        if ($isSuperAdmin) {
            $role->syncPermissions(Permission::all());
        } else {
            $role->syncPermissions($validated['permissions'] ?? []);
        }

        $displayName = $this->roleLabels[$role->name] ?? $role->name;

        return redirect()->route('administration.roles.index')
            ->with('success', "Les permissions du rôle « {$displayName} » ont été enregistrées avec succès.");
    }

    /**
     * Supprime un rôle personnalisé.
     * Empêche la suppression des rôles système et des rôles actuellement assignés à des utilisateurs.
     *
     * @param Request $request La requête HTTP entrante.
     * @param Role $role Le rôle à supprimer.
     * @return RedirectResponse Redirection avec message de succès ou d'erreur.
     */
    public function destroy(Request $request, Role $role): RedirectResponse
    {
        // Contrôle d'habilitation : suppression de rôle
        abort_unless($request->user()->can('roles.delete'), 403, 'Accès non autorisé : vous ne disposez pas de la permission requise pour supprimer un rôle.');

        // 1. Interdiction stricte de supprimer les rôles fondamentaux du système
        if (in_array($role->name, ['super-admin', 'admin'])) {
            return back()->with('error', "Le rôle système « {$role->name} » est protégé et ne peut pas être supprimé.");
        }

        // 2. Vérification qu'aucun utilisateur n'est actuellement rattaché à ce rôle
        $assignedUsersCount = $role->users()->count();
        if ($assignedUsersCount > 0) {
            return back()->with('error', "Impossible de supprimer ce rôle car il est actuellement attribué à {$assignedUsersCount} utilisateur(s).");
        }

        $roleName = $role->name;
        $role->delete();

        return redirect()->route('administration.roles.index')
            ->with('success', "Le rôle « {$roleName} » a été supprimé avec succès.");
    }
}
