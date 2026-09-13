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
        // Module Utilisateurs
        'users.view' => [
            'module' => 'users',
            'label' => 'Consulter les utilisateurs',
            'description' => 'Visualiser les listes et les fiches des personnels et élèves.',
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
        'users.delete' => [
            'module' => 'users',
            'label' => 'Supprimer des utilisateurs',
            'description' => 'Désactiver ou supprimer un compte utilisateur.',
        ],
        'users.roles' => [
            'module' => 'users',
            'label' => 'Assigner les rôles',
            'description' => 'Modifier les attributions de rôles pour les utilisateurs.',
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

        // Module Pédagogie & Classes
        'classes.view' => [
            'module' => 'classes',
            'label' => 'Consulter les classes',
            'description' => 'Visualiser la structure des classes, niveaux et séries.',
        ],
        'classes.manage' => [
            'module' => 'classes',
            'label' => 'Gérer les classes',
            'description' => 'Créer, modifier et organiser les classes et promotions.',
        ],
        'subjects.manage' => [
            'module' => 'classes',
            'label' => 'Gérer les matières',
            'description' => 'Paramétrer les disciplines, matières et coefficients.',
        ],

        // Module Évaluations & Notes
        'grades.view' => [
            'module' => 'grades',
            'label' => 'Consulter les notes',
            'description' => 'Voir les notes et moyennes des évaluations scolaires.',
        ],
        'grades.edit' => [
            'module' => 'grades',
            'label' => 'Saisir & modifier les notes',
            'description' => 'Enregistrer ou modifier les évaluations des élèves.',
        ],
        'report-cards.generate' => [
            'module' => 'grades',
            'label' => 'Générer les bulletins',
            'description' => 'Éditer et exporter les bulletins scolaires officiels.',
        ],

        // Module Présences & Discipline
        'attendance.view' => [
            'module' => 'attendance',
            'label' => 'Consulter les présences',
            'description' => 'Visualiser l\'historique des absences et retards.',
        ],
        'attendance.record' => [
            'module' => 'attendance',
            'label' => 'Saisir les présences',
            'description' => 'Enregistrer les appels, retards et motifs disciplinaires.',
        ],

        // Module Configuration Système
        'settings.view' => [
            'module' => 'settings',
            'label' => 'Consulter la configuration',
            'description' => 'Accéder aux paramètres généraux de l\'établissement.',
        ],
        'settings.edit' => [
            'module' => 'settings',
            'label' => 'Modifier la configuration',
            'description' => 'Changer les réglages système, années et identité visuelle.',
        ],
        'backups.manage' => [
            'module' => 'settings',
            'label' => 'Gérer les sauvegardes',
            'description' => 'Lancer des sauvegardes et exporter la base de données.',
        ],
    ];

    /**
     * Structure des modules thématiques regroupant les permissions.
     *
     * @var array<string, array{id: string, name: string, description: string, icon: string}>
     */
    protected array $modules = [
        'users' => [
            'id' => 'users',
            'name' => 'Utilisateurs & Profils',
            'description' => 'Gestion des comptes, des enseignants et des élèves.',
            'icon' => 'Users',
        ],
        'roles' => [
            'id' => 'roles',
            'name' => 'Rôles & Permissions',
            'description' => 'Contrôle d\'accès granulaire et gestion des profils de sécurité.',
            'icon' => 'ShieldCheck',
        ],
        'classes' => [
            'id' => 'classes',
            'name' => 'Classes & Pédagogie',
            'description' => 'Structure des classes, filières et coefficients des matières.',
            'icon' => 'GraduationCap',
        ],
        'grades' => [
            'id' => 'grades',
            'name' => 'Évaluations & Bulletins',
            'description' => 'Saisie des notes, calculs de moyennes et bulletins scolaires.',
            'icon' => 'FileSpreadsheet',
        ],
        'attendance' => [
            'id' => 'attendance',
            'name' => 'Présences & Vie Scolaire',
            'description' => 'Suivi des retards, des absences et de la discipline.',
            'icon' => 'CalendarRange',
        ],
        'settings' => [
            'id' => 'settings',
            'name' => 'Configuration Système',
            'description' => 'Paramètres de l\'établissement et maintenance des données.',
            'icon' => 'Settings',
        ],
    ];

    /**
     * Affiche la liste des rôles avec leurs statistiques et l'ensemble des permissions structurées.
     *
     * @return Response La vue Inertia avec la liste des rôles et la structure des modules.
     */
    public function index(): Response
    {
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
        $isSuperAdmin = $role->name === 'super-admin';

        $validated = $request->validate([
            'name' => [
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

        // Protection : Le rôle super-admin ne peut jamais être renommé
        if ($isSuperAdmin && Str::slug($validated['name']) !== 'super-admin') {
            return back()->withErrors(['name' => 'Le rôle Super Administrateur est protégé et ne peut pas être renommé.']);
        }

        // Mise à jour du nom pour les autres rôles
        if (! $isSuperAdmin) {
            $role->name = Str::slug($validated['name']);
            $role->save();
        }

        // Synchronisation des permissions (le super-admin conserve toujours toutes les permissions)
        if ($isSuperAdmin) {
            $role->syncPermissions(Permission::all());
        } else {
            $role->syncPermissions($validated['permissions'] ?? []);
        }

        return redirect()->route('administration.roles.index')
            ->with('success', "Le rôle « {$role->name} » a été mis à jour avec succès.");
    }

    /**
     * Supprime un rôle personnalisé.
     * Empêche la suppression des rôles système et des rôles actuellement assignés à des utilisateurs.
     *
     * @param Role $role Le rôle à supprimer.
     * @return RedirectResponse Redirection avec message de succès ou d'erreur.
     */
    public function destroy(Role $role): RedirectResponse
    {
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
