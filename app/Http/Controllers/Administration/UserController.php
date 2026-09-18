<?php

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

/**
 * Contrôleur de gestion des utilisateurs de la plateforme SYGES.
 *
 * Fournit les opérations complètes de création, modification, suppression
 * et changement de statut (activation/blocage) des comptes d'accès,
 * ainsi que l'attribution des rôles de sécurité Spatie.
 */
class UserController extends Controller implements HasMiddleware
{
    /**
     * Définition de la matrice des middlewares d'autorisation pour le module Utilisateurs.
     *
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:users.view', only: ['index']),
            new Middleware('can:users.create', only: ['store']),
            new Middleware('can:users.edit', only: ['update']),
            new Middleware('can:users.block', only: ['toggleStatus']),
        ];
    }

    /**
     * Dictionnaire des libellés conviviaux pour les rôles prédéfinis.
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
     * Affiche la liste des utilisateurs avec leurs rôles dans le tableau TanStack.
     *
     * @param Request $request La requête HTTP entrante.
     * @return Response Vue Inertia avec les utilisateurs, les rôles disponibles et les droits d'action.
     */
    public function index(Request $request): Response
    {
        // Contrôle d'habilitation : consultation des utilisateurs
        abort_unless($request->user()->can('users.view'), 403, 'Accès non autorisé : vous ne disposez pas de la permission requise pour consulter les utilisateurs.');

        // 1. Récupération de tous les utilisateurs avec leurs rôles
        $users = User::with('roles')
            ->orderBy('name')
            ->get()
            ->map(function (User $user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'login' => $user->login ?? Str::slug($user->name),
                    'email' => $user->email,
                    'isActive' => (bool) $user->is_active,
                    'roles' => $user->roles->pluck('name')->toArray(),
                    'rolesDisplay' => $user->roles->map(fn (Role $r) => $this->roleLabels[$r->name] ?? $r->name)->toArray(),
                    'isSuperAdmin' => $user->hasRole('super-admin') || $user->email === 'admin@gmail.com',
                    'isCurrentAuth' => $user->id === Auth::id(),
                    'createdAt' => $user->created_at?->format('d/m/Y'),
                ];
            });

        // 2. Récupération des rôles disponibles (exclusion stricte du super-admin car unique dans l'application)
        $roles = Role::where('name', '!=', 'super-admin')
            ->orderBy('name')
            ->get()
            ->map(function (Role $role) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'displayName' => $this->roleLabels[$role->name] ?? Str::headline($role->name),
                ];
            });

        return Inertia::render('administration/users/index', [
            'users' => $users,
            'availableRoles' => $roles,
            'can' => [
                'create' => $request->user()->can('users.create'),
                'edit' => $request->user()->can('users.edit'),
                'block' => $request->user()->can('users.block'),
            ],
        ]);
    }

    /**
     * Enregistre un nouvel utilisateur avec son login, mot de passe et rôles.
     *
     * @param Request $request Requête HTTP contenant les informations de l'utilisateur.
     * @return RedirectResponse Redirection vers la liste des utilisateurs avec confirmation.
     */
    public function store(Request $request): RedirectResponse
    {
        // Contrôle d'habilitation : création d'un utilisateur
        abort_unless($request->user()->can('users.create'), 403, 'Accès non autorisé : vous ne disposez pas de la permission requise pour créer un utilisateur.');

        $validated = $request->validate([
            'login' => [
                'required',
                'string',
                'min:3',
                'max:40',
                'regex:/^[a-zA-Z0-9._-]+$/',
                'unique:users,login',
            ],
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:100', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', 'exists:roles,name', Rule::notIn(['super-admin'])],
        ], [
            'login.required' => 'Le login de connexion est obligatoire.',
            'login.unique' => 'Ce login est déjà attribué à un autre compte.',
            'login.regex' => 'Le login ne peut contenir que des lettres, chiffres, points, tirets et underscores.',
            'name.required' => 'Le nom complet est obligatoire.',
            'email.required' => 'L\'adresse email est obligatoire.',
            'email.unique' => 'Cette adresse email est déjà utilisée.',
            'password.required' => 'Le mot de passe est obligatoire.',
            'password.min' => 'Le mot de passe doit contenir au moins 6 caractères.',
            'password.confirmed' => 'La confirmation du mot de passe ne correspond pas.',
        ]);

        // Création du compte utilisateur (toujours actif par défaut)
        $user = User::create([
            'login' => Str::lower($validated['login']),
            'name' => $validated['name'],
            'email' => Str::lower($validated['email']),
            'password' => Hash::make($validated['password']),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        // Attribution des rôles Spatie
        if (! empty($validated['roles'])) {
            $user->syncRoles($validated['roles']);
        }

        return redirect()->route('administration.users.index')
            ->with('success', "L'utilisateur « {$user->name} » a été créé avec succès.");
    }

    /**
     * Met à jour les informations de profil, identifiants et rôles d'un utilisateur existant.
     * Le statut actif/bloqué est géré exclusivement via le blocage/déblocage (toggleStatus).
     *
     * @param Request $request Requête HTTP de mise à jour.
     * @param User $user L'utilisateur à modifier.
     * @return RedirectResponse Redirection avec message de succès.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        // Contrôle d'habilitation : modification des utilisateurs
        abort_unless($request->user()->can('users.edit'), 403, 'Accès non autorisé : vous ne disposez pas de la permission requise pour modifier un compte utilisateur.');

        $isPrimarySuperAdmin = $user->email === 'admin@gmail.com';

        $validated = $request->validate([
            'login' => [
                'required',
                'string',
                'min:3',
                'max:40',
                'regex:/^[a-zA-Z0-9._-]+$/',
                Rule::unique('users', 'login')->ignore($user->id),
            ],
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'email' => [
                'required',
                'string',
                'email',
                'max:100',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'password' => ['nullable', 'string', 'min:6', 'confirmed'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', 'exists:roles,name'],
        ], [
            'login.required' => 'Le login est obligatoire.',
            'login.unique' => 'Ce login est déjà utilisé.',
            'login.regex' => 'Le login contient des caractères non autorisés.',
            'name.required' => 'Le nom est obligatoire.',
            'email.required' => 'L\'adresse email est obligatoire.',
            'email.unique' => 'Cette adresse email est déjà attribuée.',
            'password.min' => 'Le mot de passe doit comporter au moins 6 caractères.',
            'password.confirmed' => 'La confirmation du mot de passe ne correspond pas.',
        ]);

        $roles = $validated['roles'] ?? [];
        if ($isPrimarySuperAdmin && ! in_array('super-admin', $roles)) {
            $roles[] = 'super-admin';
        }

        // Mise à jour des attributs (le statut d'activation est géré exclusivement via l'action Bloquer/Débloquer)
        $user->name = $validated['name'];
        $user->login = Str::lower($validated['login']);
        $user->email = Str::lower($validated['email']);

        // Mise à jour du mot de passe uniquement si un nouveau est fourni
        if (! empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        // Synchronisation des rôles si autorisée
        if (isset($validated['roles'])) {
            $user->syncRoles($roles);
        }

        return redirect()->route('administration.users.index')
            ->with('success', "Les informations de « {$user->name} » ont été mises à jour avec succès.");
    }

    /**
     * Bascule rapidement le statut d'un utilisateur entre Actif et Bloqué/Suspendu.
     *
     * @param Request $request La requête HTTP entrante.
     * @param User $user L'utilisateur ciblé.
     * @return RedirectResponse Redirection avec message d'information.
     */
    public function toggleStatus(Request $request, User $user): RedirectResponse
    {
        // Contrôle d'habilitation : permission de bloquer ou débloquer un compte utilisateur
        abort_unless($request->user()->can('users.block'), 403, 'Accès non autorisé : vous ne disposez pas de la permission requise pour bloquer ou réactiver un utilisateur.');

        // Garde-fous de sécurité
        if ($user->email === 'admin@gmail.com') {
            return back()->with('error', 'Le compte Super Administrateur principal ne peut pas être bloqué.');
        }

        if ($user->id === Auth::id()) {
            return back()->with('error', 'Vous ne pouvez pas bloquer votre propre compte actuellement connecté.');
        }

        $user->is_active = ! $user->is_active;
        $user->save();

        $actionLabel = $user->is_active ? 'activé' : 'bloqué';

        return redirect()->route('administration.users.index')
            ->with('success', "Le compte de « {$user->name} » a été {$actionLabel} avec succès.");
    }
}
