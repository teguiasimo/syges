<?php

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use App\Models\Departement;
use App\Models\Etablissement;
use App\Models\Institution;
use App\Models\Personnel;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

/**
 * Contrôleur de gestion du Module 8 : Personnel & Ressources Humaines (RH).
 * Conforme au Cahier des Charges Gesco V1.1 (Sections 8.1, 8.2, 8.3).
 * 
 * Assure le cycle de vie complet des employés, la gestion des départements pédagogiques,
 * la qualification des enseignants (charge horaire, vacation) et la création / liaison fluide
 * avec les comptes d'accès utilisateurs (`users`).
 */
class PersonnelController extends Controller
{
    /**
     * Affiche la liste du personnel et des départements pédagogiques avec filtres avancés.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Inertia\Response
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $isSuperAdmin = $user?->is_super_admin ?? false;

        // Contrôle d'habilitation : consultation du registre du personnel
        abort_unless(
            $isSuperAdmin || $user?->can('personnels.view'),
            403,
            'Accès non autorisé : vous ne disposez pas de la permission de consulter le personnel.'
        );

        // 1. Récupération des filtres
        $etablissementId = $request->input('etablissement_id');
        $departementId = $request->input('departement_id');
        $fonction = $request->input('fonction');
        $contrat = $request->input('contrat');
        $statut = $request->input('statut');
        $search = $request->input('search');

        // 2. Requête du personnel avec eager-loading
        $query = Personnel::query()
            ->with(['etablissement:id,nom,code', 'departement:id,nom,code', 'user:id,name,email,login,is_active'])
            ->latest('id');

        if ($etablissementId) {
            $query->where('etablissement_id', $etablissementId);
        }

        if ($departementId) {
            $query->where('departement_id', $departementId);
        }

        if ($fonction) {
            $query->where('fonction_principale', $fonction);
        }

        if ($contrat) {
            $query->where('type_contrat', $contrat);
        }

        if ($statut) {
            $query->where('statut', $statut);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nom', 'LIKE', "%{$search}%")
                    ->orWhere('prenom', 'LIKE', "%{$search}%")
                    ->orWhere('matricule', 'LIKE', "%{$search}%")
                    ->orWhere('telephone', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%");
            });
        }

        $personnels = $query->get();

        // 3. Départements avec chef et effectifs
        $institution = Institution::first();
        $departements = Departement::with(['chefDepartement:id,nom,prenom,matricule'])
            ->withCount('personnels')
            ->orderBy('nom')
            ->get();

        // 4. Établissements actifs
        $etablissements = Etablissement::select('id', 'nom', 'code')->orderBy('nom')->get();

        // 5. Utilisateurs existants sans fiche de personnel (pour liaison optionnelle)
        $assignedUserIds = Personnel::whereNotNull('user_id')->pluck('user_id')->toArray();
        $availableUsers = User::whereNotIn('id', $assignedUserIds)
            ->where('is_active', true)
            ->select('id', 'name', 'email', 'login')
            ->orderBy('name')
            ->get();

        // 6. Rôles Spatie disponibles pour attribution
        $roles = Role::pluck('name')->toArray();

        // 7. Statistiques récapitulatives
        $stats = [
            'total' => Personnel::count(),
            'actifs' => Personnel::where('statut', 'Actif')->count(),
            'enseignants' => Personnel::where('is_enseignant', true)->count(),
            'departementsCount' => $departements->count(),
            'avecCompte' => Personnel::whereNotNull('user_id')->count(),
        ];

        // 8. Droits d'habilitation
        $can = [
            'manage' => $isSuperAdmin || $user?->can('personnels.manage'),
            'delete' => $isSuperAdmin || $user?->can('personnels.delete'),
            'departements' => $isSuperAdmin || $user?->can('departements.manage'),
        ];

        return Inertia::render('administration/personnels/index', [
            'personnels' => $personnels,
            'departements' => $departements,
            'etablissements' => $etablissements,
            'availableUsers' => $availableUsers,
            'roles' => $roles,
            'stats' => $stats,
            'can' => $can,
            'filters' => [
                'etablissement_id' => $etablissementId ?? '',
                'departement_id' => $departementId ?? '',
                'fonction' => $fonction ?? '',
                'contrat' => $contrat ?? '',
                'statut' => $statut ?? '',
                'search' => $search ?? '',
            ],
        ]);
    }

    /**
     * Enregistre un nouveau membre du personnel, avec création optionnelle de son compte utilisateur.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless(
            $user?->is_super_admin || $user?->can('personnels.manage'),
            403,
            'Accès non autorisé : vous ne disposez pas de la permission pour enregistrer un membre du personnel.'
        );

        $validated = $request->validate([
            // Informations Civiles
            'nom' => ['required', 'string', 'max:120'],
            'prenom' => ['nullable', 'string', 'max:120'],
            'sexe' => ['required', 'in:M,F'],
            'date_naissance' => ['nullable', 'date'],
            'lieu_naissance' => ['nullable', 'string', 'max:150'],
            'telephone' => ['required', 'string', 'max:50'],
            'telephone_secondaire' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:150'],
            'adresse' => ['nullable', 'string', 'max:255'],

            // Affectation & Statut Professionnel
            'etablissement_id' => ['required', 'exists:etablissements,id'],
            'departement_id' => ['nullable', 'exists:departements,id'],
            'fonction_principale' => ['required', 'string', 'max:80'],
            'type_contrat' => ['required', 'string', 'max:60'],
            'date_embauche' => ['required', 'date'],
            'statut' => ['required', 'string', 'max:40'],

            // Données RH
            'numero_cnps' => ['nullable', 'string', 'max:60'],
            'diplome_plus_eleve' => ['nullable', 'string', 'max:120'],
            'observations' => ['nullable', 'string'],

            // Spécificités Pédagogiques Enseignant
            'is_enseignant' => ['boolean'],
            'specialite' => ['nullable', 'string', 'max:120'],
            'volume_horaire_hebdo' => ['numeric', 'min:0', 'max:100'],
            'taux_horaire_vacation' => ['nullable', 'numeric', 'min:0'],

            // Gestion du compte d'accès utilisateur
            'create_user_account' => ['boolean'],
            'user_id' => ['nullable', 'exists:users,id', 'unique:personnels,user_id'],
            'user_login' => ['nullable', 'string', 'max:80', 'unique:users,login'],
            'user_email' => ['nullable', 'email', 'max:150', 'unique:users,email'],
            'user_password' => ['nullable', 'string', 'min:6'],
            'user_role' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($validated, $request) {
            $userId = $validated['user_id'] ?? null;

            // 1. Création automatique du compte utilisateur si demandée (Gesco 7 & 8 unifiés)
            if (! empty($validated['create_user_account'])) {
                $fullName = trim($validated['nom'] . ' ' . ($validated['prenom'] ?? ''));
                $login = ! empty($validated['user_login'])
                    ? $validated['user_login']
                    : strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $validated['nom'])) . rand(10, 99);

                // Si aucun email spécifié, utilise l'email de contact ou en génère un interne
                $userEmail = ! empty($validated['user_email'])
                    ? $validated['user_email']
                    : (! empty($validated['email']) ? $validated['email'] : "{$login}@syges.local");

                $password = ! empty($validated['user_password'])
                    ? $validated['user_password']
                    : 'syges2026';

                $newUser = User::create([
                    'name' => $fullName,
                    'login' => $login,
                    'email' => $userEmail,
                    'password' => Hash::make($password),
                    'is_active' => true,
                ]);

                // Attribution du rôle Spatie
                $roleName = $validated['user_role'] ?? 'enseignant';
                if (Role::where('name', $roleName)->exists()) {
                    $newUser->assignRole($roleName);
                }

                $userId = $newUser->id;
            }

            // 2. Génération du matricule séquentiel officiel
            $matricule = Personnel::generateMatricule();

            // 3. Déduction de is_enseignant si fonction = Enseignant
            $isEnseignant = isset($validated['is_enseignant'])
                ? (bool) $validated['is_enseignant']
                : ($validated['fonction_principale'] === 'Enseignant');

            // 4. Création de la fiche administrative Personnel
            Personnel::create([
                'matricule' => $matricule,
                'user_id' => $userId,
                'etablissement_id' => $validated['etablissement_id'],
                'departement_id' => $validated['departement_id'] ?? null,
                'nom' => $validated['nom'],
                'prenom' => $validated['prenom'] ?? null,
                'sexe' => $validated['sexe'],
                'date_naissance' => $validated['date_naissance'] ?? null,
                'lieu_naissance' => $validated['lieu_naissance'] ?? null,
                'telephone' => $validated['telephone'],
                'telephone_secondaire' => $validated['telephone_secondaire'] ?? null,
                'email' => $validated['email'] ?? null,
                'adresse' => $validated['adresse'] ?? null,
                'fonction_principale' => $validated['fonction_principale'],
                'type_contrat' => $validated['type_contrat'],
                'date_embauche' => $validated['date_embauche'],
                'statut' => $validated['statut'],
                'numero_cnps' => $validated['numero_cnps'] ?? null,
                'diplome_plus_eleve' => $validated['diplome_plus_eleve'] ?? null,
                'observations' => $validated['observations'] ?? null,
                'is_enseignant' => $isEnseignant,
                'specialite' => $validated['specialite'] ?? null,
                'volume_horaire_hebdo' => $validated['volume_horaire_hebdo'] ?? 18.00,
                'taux_horaire_vacation' => $validated['taux_horaire_vacation'] ?? null,
            ]);
        });

        return back()->with('success', 'Nouveau membre du personnel enregistré avec succès.');
    }

    /**
     * Met à jour la fiche administrative d'un membre du personnel.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Personnel  $personnel
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, Personnel $personnel): RedirectResponse
    {
        $user = $request->user();
        abort_unless(
            $user?->is_super_admin || $user?->can('personnels.manage'),
            403,
            'Accès non autorisé : vous ne disposez pas de la permission pour modifier ce personnel.'
        );

        $validated = $request->validate([
            'nom' => ['required', 'string', 'max:120'],
            'prenom' => ['nullable', 'string', 'max:120'],
            'sexe' => ['required', 'in:M,F'],
            'date_naissance' => ['nullable', 'date'],
            'lieu_naissance' => ['nullable', 'string', 'max:150'],
            'telephone' => ['required', 'string', 'max:50'],
            'telephone_secondaire' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:150'],
            'adresse' => ['nullable', 'string', 'max:255'],
            'etablissement_id' => ['required', 'exists:etablissements,id'],
            'departement_id' => ['nullable', 'exists:departements,id'],
            'fonction_principale' => ['required', 'string', 'max:80'],
            'type_contrat' => ['required', 'string', 'max:60'],
            'date_embauche' => ['required', 'date'],
            'statut' => ['required', 'string', 'max:40'],
            'numero_cnps' => ['nullable', 'string', 'max:60'],
            'diplome_plus_eleve' => ['nullable', 'string', 'max:120'],
            'observations' => ['nullable', 'string'],
            'is_enseignant' => ['boolean'],
            'specialite' => ['nullable', 'string', 'max:120'],
            'volume_horaire_hebdo' => ['numeric', 'min:0', 'max:100'],
            'taux_horaire_vacation' => ['nullable', 'numeric', 'min:0'],
            'user_id' => [
                'nullable',
                'exists:users,id',
                Rule::unique('personnels', 'user_id')->ignore($personnel->id),
            ],
        ]);

        $personnel->update($validated);

        return back()->with('success', 'Fiche du personnel mise à jour avec succès.');
    }

    /**
     * Supprime un membre du personnel (Soft Delete) avec conservation de l'intégrité référentielle.
     *
     * @param  \App\Models\Personnel  $personnel
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Personnel $personnel): RedirectResponse
    {
        $user = request()->user();
        abort_unless(
            $user?->is_super_admin || $user?->can('personnels.delete'),
            403,
            'Accès non autorisé : vous ne disposez pas de la permission pour archiver ce personnel.'
        );

        // Si cet employé était chef de département, on libère le poste
        Departement::where('chef_departement_id', $personnel->id)
            ->update(['chef_departement_id' => null]);

        $personnel->delete();

        return back()->with('success', 'Membre du personnel archivé avec succès.');
    }

    /**
     * Bascule rapidement le statut d'un personnel (ex: Actif, En congé, Suspendu).
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Personnel  $personnel
     * @return \Illuminate\Http\RedirectResponse
     */
    public function toggleStatus(Request $request, Personnel $personnel): RedirectResponse
    {
        $user = $request->user();
        abort_unless(
            $user?->is_super_admin || $user?->can('personnels.manage'),
            403,
            'Accès non autorisé : vous ne disposez pas de la permission pour modifier le statut du personnel.'
        );

        $validated = $request->validate([
            'statut' => ['required', 'string', 'in:Actif,En congé,Démissionnaire,Retraité,Suspendu,Licencié'],
        ]);

        $personnel->update(['statut' => $validated['statut']]);

        return back()->with('success', "Statut de {$personnel->nom_complet} mis à jour : {$validated['statut']}.");
    }

    /**
     * Enregistre un nouveau département pédagogique.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function storeDepartement(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless(
            $user?->is_super_admin || $user?->can('departements.manage'),
            403,
            'Accès non autorisé : vous ne disposez pas de la permission pour créer un département.'
        );

        $institution = Institution::first();
        if (! $institution) {
            return back()->with('error', 'Aucune institution configurée.');
        }

        $validated = $request->validate([
            'nom' => ['required', 'string', 'max:120'],
            'code' => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string'],
            'chef_departement_id' => ['nullable', 'exists:personnels,id'],
            'is_active' => ['boolean'],
        ]);

        Departement::create([
            'institution_id' => $institution->id,
            'nom' => $validated['nom'],
            'code' => $validated['code'] ?? null,
            'description' => $validated['description'] ?? null,
            'chef_departement_id' => $validated['chef_departement_id'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return back()->with('success', 'Département pédagogique créé avec succès.');
    }

    /**
     * Met à jour un département pédagogique.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Departement  $departement
     * @return \Illuminate\Http\RedirectResponse
     */
    public function updateDepartement(Request $request, Departement $departement): RedirectResponse
    {
        $user = $request->user();
        abort_unless(
            $user?->is_super_admin || $user?->can('departements.manage'),
            403,
            'Accès non autorisé : vous ne disposez pas de la permission pour modifier un département.'
        );

        $validated = $request->validate([
            'nom' => ['required', 'string', 'max:120'],
            'code' => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string'],
            'chef_departement_id' => ['nullable', 'exists:personnels,id'],
            'is_active' => ['boolean'],
        ]);

        $departement->update($validated);

        return back()->with('success', 'Département pédagogique mis à jour avec succès.');
    }

    /**
     * Supprime un département pédagogique.
     *
     * @param  \App\Models\Departement  $departement
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroyDepartement(Departement $departement): RedirectResponse
    {
        $user = request()->user();
        abort_unless(
            $user?->is_super_admin || $user?->can('departements.manage'),
            403,
            'Accès non autorisé : vous ne disposez pas de la permission pour supprimer un département.'
        );

        // Dissocie les membres du personnel rattachés avant suppression
        Personnel::where('departement_id', $departement->id)->update(['departement_id' => null]);
        $departement->delete();

        return back()->with('success', 'Département pédagogique supprimé.');
    }
}
