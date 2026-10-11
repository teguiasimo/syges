<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeder principal de l'application SYGES.
 *
 * Initialise exclusivement la matrice de sécurité granulaire du système :
 * 1. Déclaration exhaustive des permissions scolaires unitaires scindées par module fonctionnel.
 * 2. Création des rôles standards de l'application et synchronisation ciblée de leurs permissions.
 * 3. Création du compte Super Administrateur principal (admin@gmail.com) pour la première connexion.
 *
 * Conforme au principe de sécurité du moindre privilège (Least Privilege / Zero Trust).
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Exécute le peuplement de la base de données.
     *
     * @return void
     */
    public function run(): void
    {
        // 1. Réinitialiser le cache des permissions et des rôles Spatie
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 2. Définition exhaustive des permissions scolaires granulaires par module
        $permissions = [
            // ==========================================
            // 1. Module Utilisateurs & Profils
            // ==========================================
            'users.view' => 'web',
            'users.create' => 'web',
            'users.edit' => 'web',
            'users.block' => 'web',

            // ==========================================
            // 2. Module Rôles & Sécurité
            // ==========================================
            'roles.view' => 'web',
            'roles.create' => 'web',
            'roles.edit' => 'web',
            'roles.delete' => 'web',

            // ==========================================
            // 3. Module Institution & Établissements
            // ==========================================
            'institutions.view' => 'web',
            'institutions.edit' => 'web',
            'etablissements.view' => 'web',
            'etablissements.create' => 'web',
            'etablissements.edit' => 'web',
            'etablissements.delete' => 'web',
            'etablissements.settings' => 'web',
            // Compatibilité ascendante
            'etablissements.manage' => 'web',

            // ==========================================
            // 4. Module Structure Pédagogique
            // ==========================================
            'sections.view' => 'web',
            'sections.create' => 'web',
            'sections.edit' => 'web',
            'sections.delete' => 'web',
            'cycles.view' => 'web',
            'cycles.create' => 'web',
            'cycles.edit' => 'web',
            'cycles.delete' => 'web',
            'niveaux.view' => 'web',
            'niveaux.create' => 'web',
            'niveaux.edit' => 'web',
            'niveaux.delete' => 'web',
            'classes.view' => 'web',
            'classes.create' => 'web',
            'classes.edit' => 'web',
            'classes.delete' => 'web',
            'series.view' => 'web',
            'series.create' => 'web',
            'series.edit' => 'web',
            'series.delete' => 'web',
            // Compatibilité ascendante
            'structures.manage' => 'web',
            'classes.manage' => 'web',

            // ==========================================
            // 5. Module Périodes & Paramètres d'Évaluation
            // ==========================================
            'annees.view' => 'web',
            'annees.create' => 'web',
            'annees.edit' => 'web',
            'annees.set_current' => 'web',
            'periodes.view' => 'web',
            'periodes.create' => 'web',
            'periodes.edit' => 'web',
            'periodes.delete' => 'web',
            'periodes.lock' => 'web',
            'evaluations.view' => 'web',
            'evaluations.create' => 'web',
            'evaluations.edit' => 'web',
            'evaluations.delete' => 'web',
            'evaluations.lock' => 'web',
            // Compatibilité ascendante
            'periodes.manage' => 'web',

            // ==========================================
            // 6. Module Matières & Coefficients
            // ==========================================
            'matieres.view' => 'web',
            'matieres.create' => 'web',
            'matieres.edit' => 'web',
            'matieres.delete' => 'web',
            'groupes_matieres.view' => 'web',
            'groupes_matieres.create' => 'web',
            'groupes_matieres.edit' => 'web',
            'groupes_matieres.delete' => 'web',
            'coefficients.view' => 'web',
            'coefficients.edit' => 'web',
            'coefficients.duplicate' => 'web',
            // Compatibilité ascendante
            'matieres.manage' => 'web',

            // ==========================================
            // 7. Module Élèves, Inscriptions & Cartes
            // ==========================================
            'eleves.view' => 'web',
            'eleves.create' => 'web',
            'eleves.edit' => 'web',
            'eleves.delete' => 'web',
            'eleves.import' => 'web',
            'inscriptions.reinscrire' => 'web',
            'inscriptions.transfer' => 'web',
            'inscriptions.delete' => 'web',
            'cartes_scolaires.view' => 'web',
            'cartes_scolaires.generate' => 'web',
            // Compatibilité ascendante
            'inscriptions.manage' => 'web',

            // ==========================================
            // 8. Module Personnel & Départements RH
            // ==========================================
            'personnels.view' => 'web',
            'personnels.create' => 'web',
            'personnels.edit' => 'web',
            'personnels.delete' => 'web',
            'personnels.toggle_status' => 'web',
            'departements.view' => 'web',
            'departements.create' => 'web',
            'departements.edit' => 'web',
            'departements.delete' => 'web',
            // Compatibilité ascendante
            'personnels.manage' => 'web',
            'departements.manage' => 'web',

            // ==========================================
            // 9. Module Attributions Pédagogiques
            // ==========================================
            'attributions.view' => 'web',
            'attributions.create' => 'web',
            'attributions.edit' => 'web',
            'attributions.delete' => 'web',
            'attributions.prof_principal' => 'web',
            // Compatibilité ascendante
            'attributions.manage' => 'web',

            // ==========================================
            // 10. Module Saisie des Notes & Bulletins
            // ==========================================
            'notes.view' => 'web',
            'notes.create' => 'web',
            'notes.lock' => 'web',
            'notes.unlock' => 'web',
            'releves_notes.view' => 'web',
            'releves_notes.print' => 'web',
            'bulletins.view' => 'web',
            'bulletins.generate' => 'web',
            'bulletins.lock' => 'web',
            // Compatibilité ascendante
            'notes.manage' => 'web',

            // ==========================================
            // 11. Module Gestion Financière, Caisse & Dépenses
            // ==========================================
            'finances.view' => 'web',
            'versements.create' => 'web',
            'versements.cancel' => 'web',
            'caisses.view' => 'web',
            'caisses.create' => 'web',
            'caisses.edit' => 'web',
            'caisses.cloture' => 'web',
            'depenses.view' => 'web',
            'depenses.create' => 'web',
            'depenses.edit' => 'web',
            'depenses.delete' => 'web',
            'depenses.bilan' => 'web',
            'categories_depenses.manage' => 'web',
            'bourses.manage' => 'web',
            'echeances.manage' => 'web',
            'penalites.manage' => 'web',
            // Compatibilité ascendante
            'finances.manage' => 'web',
            'finances.cloture' => 'web',
            'finances.annuler' => 'web',

            // ==========================================
            // 12. Module Discipline & Conseil de Discipline
            // ==========================================
            'discipline.view' => 'web',
            'discipline.assiduite_create' => 'web',
            'discipline.assiduite_delete' => 'web',
            'discipline.sanction_create' => 'web',
            'discipline.sanction_delete' => 'web',
            'conseil_discipline.view' => 'web',
            'conseil_discipline.create' => 'web',
            'conseil_discipline.edit' => 'web',
            'conseil_discipline.delete' => 'web',
            'conseil_discipline.print' => 'web',
            'consignes.view' => 'web',
            'consignes.pointage' => 'web',
            // Compatibilité ascendante
            'discipline.manage' => 'web',
            'discipline.sanction' => 'web',

            // ==========================================
            // 13. Module Dérogations & Dispenses Médicales
            // ==========================================
            'dispenses.view' => 'web',
            'dispenses.create' => 'web',
            'dispenses.edit' => 'web',
            'dispenses.delete' => 'web',
            'dispenses.homologuer' => 'web',
            'dispenses.print_pv' => 'web',

            // ==========================================
            // 14. Module Pédagogie par Compétences (APC)
            // ==========================================
            'competences_apc.view' => 'web',
            'competences_apc.create' => 'web',
            'competences_apc.edit' => 'web',
            'competences_apc.delete' => 'web',
            'competences_apc.duplicate' => 'web',
            'cotes_apc.manage' => 'web',

            // ==========================================
            // 15. Module Cahier de Texte & Progression
            // ==========================================
            'progression.view' => 'web',
            'progression.create' => 'web',
            'progression.edit' => 'web',
            'progression.delete' => 'web',
            'progression.viser' => 'web',
            'progression.print' => 'web',

            // ==========================================
            // 16. Module Emplois du Temps & Salles
            // ==========================================
            'emplois_du_temps.view' => 'web',
            'emplois_du_temps.create' => 'web',
            'emplois_du_temps.edit' => 'web',
            'emplois_du_temps.delete' => 'web',
            'salles.view' => 'web',
            'salles.create' => 'web',
            'salles.edit' => 'web',
            'salles.delete' => 'web',
            // Compatibilité ascendante
            'emplois_du_temps.manage' => 'web',

            // ==========================================
            // 17. Module Communication & Passerelle SMS
            // ==========================================
            'communication.view' => 'web',
            'communication.send' => 'web',
            'communication.settings' => 'web',
            // Compatibilité ascendante
            'communication.manage' => 'web',

            // ==========================================
            // 18. Module Statistiques & Délibérations
            // ==========================================
            'statistiques.view' => 'web',
            'deliberations.view' => 'web',
            'deliberations.saisir' => 'web',
            'deliberations.valider' => 'web',
            // Compatibilité ascendante
            'deliberations.manage' => 'web',

            // ==========================================
            // 19. Module Archivage, Reconduction & Numérotation
            // ==========================================
            'archivage.view' => 'web',
            'archivage.archiver' => 'web',
            'archivage.dupliquer' => 'web',
            'archivage.numerotation' => 'web',
        ];

        // Création ou persistance des permissions
        foreach ($permissions as $name => $guardName) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => $guardName]);
        }

        // Nettoyage des permissions obsolètes
        Permission::whereNotIn('name', array_keys($permissions))->delete();

        // 3. Création des rôles standards du système SYGES
        $superAdminRole = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $directorRole = Role::firstOrCreate(['name' => 'directeur-etudes', 'guard_name' => 'web']);
        $censeurRole = Role::firstOrCreate(['name' => 'censeur', 'guard_name' => 'web']);
        $surveillantGeneralRole = Role::firstOrCreate(['name' => 'surveillant-general', 'guard_name' => 'web']);
        $intendantRole = Role::firstOrCreate(['name' => 'intendant', 'guard_name' => 'web']);
        $teacherRole = Role::firstOrCreate(['name' => 'enseignant', 'guard_name' => 'web']);
        $secretaryRole = Role::firstOrCreate(['name' => 'secretaire', 'guard_name' => 'web']);

        // Attribution des permissions par rôle
        $allPermissions = Permission::all();
        $superAdminRole->syncPermissions($allPermissions);
        $adminRole->syncPermissions($allPermissions);

        // Permissions du Directeur des Études / Proviseur
        $directorRole->syncPermissions([
            // Institution & structure
            'institutions.view', 'institutions.edit',
            'etablissements.view', 'etablissements.edit', 'etablissements.settings', 'etablissements.manage',
            'sections.view', 'cycles.view', 'niveaux.view',
            'classes.view', 'classes.create', 'classes.edit', 'classes.manage',
            'series.view', 'structures.manage',
            // Calendrier & évaluations
            'annees.view', 'periodes.view', 'periodes.manage', 'periodes.lock',
            'evaluations.view', 'evaluations.create', 'evaluations.edit', 'evaluations.lock',
            // Matières & coefficients
            'matieres.view', 'matieres.manage', 'groupes_matieres.view', 'coefficients.view', 'coefficients.edit', 'coefficients.duplicate',
            // Élèves & scolarité
            'eleves.view', 'eleves.create', 'eleves.edit', 'inscriptions.reinscrire', 'inscriptions.transfer', 'inscriptions.manage',
            'cartes_scolaires.view', 'cartes_scolaires.generate',
            // Personnel & Attributions
            'personnels.view', 'departements.view', 'attributions.view', 'attributions.create', 'attributions.edit', 'attributions.prof_principal', 'attributions.manage',
            // Notes & Bulletins
            'notes.view', 'notes.manage', 'notes.lock', 'notes.unlock', 'releves_notes.view', 'releves_notes.print', 'bulletins.view', 'bulletins.generate', 'bulletins.lock',
            // Discipline & Conseil de Discipline
            'discipline.view', 'discipline.assiduite_create', 'discipline.sanction_create', 'discipline.manage', 'discipline.sanction',
            'conseil_discipline.view', 'conseil_discipline.create', 'conseil_discipline.edit', 'conseil_discipline.print',
            'consignes.view', 'consignes.pointage',
            // Dispenses, Compétences & Progression
            'dispenses.view', 'dispenses.create', 'dispenses.edit', 'dispenses.homologuer', 'dispenses.print_pv',
            'competences_apc.view', 'competences_apc.create', 'competences_apc.edit', 'competences_apc.duplicate', 'cotes_apc.manage',
            'progression.view', 'progression.viser', 'progression.print',
            // Emplois du temps & Communication
            'emplois_du_temps.view', 'emplois_du_temps.create', 'emplois_du_temps.edit', 'emplois_du_temps.manage', 'salles.view',
            'communication.view', 'communication.send', 'communication.manage',
            // Délibérations & Statistiques
            'statistiques.view', 'deliberations.view', 'deliberations.saisir', 'deliberations.valider', 'deliberations.manage',
            // Finances (consultation)
            'finances.view',
        ]);

        // Permissions du Censeur
        $censeurRole->syncPermissions([
            'institutions.view', 'etablissements.view', 'classes.view', 'series.view',
            'annees.view', 'periodes.view', 'evaluations.view', 'evaluations.lock',
            'matieres.view', 'coefficients.view', 'coefficients.duplicate',
            'eleves.view', 'cartes_scolaires.view', 'cartes_scolaires.generate',
            'personnels.view', 'departements.view', 'attributions.view',
            'notes.view', 'notes.create', 'notes.lock', 'notes.unlock', 'notes.manage', 'releves_notes.view', 'releves_notes.print',
            'bulletins.view', 'bulletins.generate',
            'discipline.view', 'discipline.assiduite_create', 'discipline.sanction_create',
            'conseil_discipline.view', 'conseil_discipline.create', 'conseil_discipline.edit', 'conseil_discipline.print',
            'consignes.view', 'consignes.pointage',
            'dispenses.view', 'dispenses.create', 'dispenses.edit', 'dispenses.homologuer', 'dispenses.print_pv',
            'competences_apc.view', 'competences_apc.create', 'competences_apc.edit', 'competences_apc.duplicate',
            'progression.view', 'progression.viser', 'progression.print',
            'emplois_du_temps.view', 'emplois_du_temps.create', 'emplois_du_temps.edit', 'salles.view',
            'statistiques.view', 'deliberations.view', 'deliberations.saisir', 'deliberations.valider',
        ]);

        // Permissions du Surveillant Général
        $surveillantGeneralRole->syncPermissions([
            'institutions.view', 'etablissements.view', 'classes.view',
            'eleves.view', 'cartes_scolaires.view', 'cartes_scolaires.generate',
            'discipline.view', 'discipline.assiduite_create', 'discipline.assiduite_delete',
            'discipline.sanction_create', 'discipline.sanction_delete', 'discipline.manage', 'discipline.sanction',
            'conseil_discipline.view', 'conseil_discipline.create', 'conseil_discipline.edit', 'conseil_discipline.print',
            'consignes.view', 'consignes.pointage',
            'emplois_du_temps.view', 'salles.view',
            'communication.view', 'communication.send',
        ]);

        // Permissions de l'Intendant / Comptable
        $intendantRole->syncPermissions([
            'institutions.view', 'etablissements.view', 'classes.view',
            'eleves.view',
            'finances.view', 'finances.manage',
            'versements.create', 'versements.cancel',
            'caisses.view', 'caisses.create', 'caisses.edit', 'caisses.cloture', 'finances.cloture', 'finances.annuler',
            'depenses.view', 'depenses.create', 'depenses.edit', 'depenses.delete', 'depenses.bilan',
            'categories_depenses.manage', 'bourses.manage', 'echeances.manage', 'penalites.manage',
            'statistiques.view',
        ]);

        // Permissions de l'Enseignant (Principe du moindre privilège - Espace Pédagogique)
        $teacherRole->syncPermissions([
            'notes.view',
            'notes.create',
            'releves_notes.view',
            'releves_notes.print',
            'progression.view',
            'progression.create',
            'progression.edit',
            'progression.print',
            'emplois_du_temps.view',
        ]);

        // Permissions du Secrétaire
        $secretaryRole->syncPermissions([
            'users.view', 'users.create', 'users.edit',
            'institutions.view', 'etablissements.view',
            'sections.view', 'cycles.view', 'niveaux.view', 'classes.view',
            'periodes.view', 'matieres.view',
            'eleves.view', 'eleves.create', 'eleves.edit', 'eleves.import',
            'inscriptions.reinscrire', 'inscriptions.transfer', 'inscriptions.manage',
            'cartes_scolaires.view', 'cartes_scolaires.generate',
            'personnels.view',
            'finances.view',
            'communication.view', 'communication.send',
        ]);

        // 4. Création du compte Super Administrateur principal (accès global initial)
        $superAdmin = User::firstOrCreate(
            ['email' => 'admin@gmail.com'],
            [
                'name' => 'Super Admin',
                'login' => 'admin',
                'is_active' => true,
                'password' => Hash::make('admin@'),
                'email_verified_at' => now(),
            ]
        );

        if (empty($superAdmin->login) || ! isset($superAdmin->is_active)) {
            $superAdmin->login = 'admin';
            $superAdmin->is_active = true;
            $superAdmin->save();
        }

        // Attribution du rôle super-admin
        if (! $superAdmin->hasRole('super-admin')) {
            $superAdmin->assignRole($superAdminRole);
        }
    }
}
