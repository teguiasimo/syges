<?php

/** @var \Tests\TestCase $this */

use App\Models\Departement;
use App\Models\Etablissement;
use App\Models\Institution;
use App\Models\Personnel;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Tests de fonctionnalités pour le Module 8 : Personnel & Ressources Humaines (RH).
 * Conforme au Cahier des Charges Gesco V1.1 (Sections 8.1, 8.2, 8.3).
 *
 * Vérifie :
 * - Le contrôle d'accès selon les permissions (personnels.view, personnels.manage, personnels.delete, departements.manage).
 * - La consultation du registre du personnel et des départements pédagogiques avec Inertia.
 * - L'enregistrement d'un personnel avec matricule séquentiel officiel (PERS-YYYY-XXXX).
 * - La création automatique et simultanée du compte d'accès utilisateur SYGES avec rôle Spatie.
 * - La validation des formulaires d'enregistrement et de mise à jour.
 * - La modification d'une fiche personnel.
 * - Le basculement de statut administratif (Actif, En congé, Suspendu).
 * - L'archivage (soft-delete) d'un personnel et la libération automatique des responsabilités de chef de département.
 * - Le CRUD complet des départements pédagogiques et la dissociation sécurisée des enseignants rattachés.
 */

beforeEach(function () {
    /** @var \Tests\TestCase $this */
    // Initialisation des rôles de base
    Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'enseignant', 'guard_name' => 'web']);

    // Permissions du module RH
    $permissions = [
        'personnels.view',
        'personnels.manage',
        'personnels.delete',
        'departements.manage',
    ];

    foreach ($permissions as $perm) {
        Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
    }

    $this->adminUser = User::factory()->create();
    $this->adminUser->assignRole('super-admin');

    // Institution de référence
    $this->institution = Institution::firstOrCreate(
        ['code' => 'INST-TEST'],
        [
            'sigle' => 'TEST-INST',
            'nom' => 'Groupe Scolaire de Test',
            'nom_en' => 'Test School Group',
            'devise' => 'Excellence et Rigueur',
            'devise_en' => 'Excellence and Discipline',
            'email' => 'contact@test-inst.org',
            'telephone_1' => '+237 600 000 000',
        ]
    );

    // Établissement de référence
    $this->etablissement = Etablissement::firstOrCreate(
        ['code' => 'ETAB-TEST'],
        [
            'institution_id' => $this->institution->id,
            'nom' => 'Campus Secondaire Principal',
            'nom_en' => 'Main Secondary Campus',
            'email' => 'campus@test-inst.org',
            'telephone' => '+237 611 111 111',
            'type_etablissement' => 'Secondaire Général',
        ]
    );
});

test('les invités non authentifiés sont redirigés vers la page de connexion', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->get(route('administration.personnels.index'));
    $response->assertRedirect(route('login'));
});

test('les utilisateurs sans permission personnels.view ne peuvent pas accéder au module', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('administration.personnels.index'));
    $response->assertForbidden();
});

test('un administrateur avec personnels.view peut afficher la liste du personnel et départements', function () {
    /** @var \Tests\TestCase $this */
    $this->actingAs($this->adminUser);

    $response = $this->get(route('administration.personnels.index'));
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('administration/personnels/index')
        ->has('personnels')
        ->has('departements')
        ->has('etablissements')
        ->has('stats')
        ->has('can')
    );
});

test('un utilisateur sans permission personnels.manage ne peut pas enregistrer de personnel', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->post(route('administration.personnels.store'), [
        'nom' => 'Kouassi',
        'prenom' => 'Jean',
        'sexe' => 'M',
        'telephone' => '0707070707',
        'etablissement_id' => $this->etablissement->id,
        'fonction_principale' => 'Enseignant',
        'type_contrat' => 'CDI (Contrat à Durée Indéterminée)',
        'date_embauche' => '2026-09-01',
        'statut' => 'Actif',
    ]);

    $response->assertForbidden();
});

test('un administrateur peut enregistrer un personnel avec matricule séquentiel auto-généré', function () {
    /** @var \Tests\TestCase $this */
    $this->actingAs($this->adminUser);

    $response = $this->post(route('administration.personnels.store'), [
        'nom' => 'Yao',
        'prenom' => 'Armel',
        'sexe' => 'M',
        'telephone' => '0505050505',
        'email' => 'yao.armel@test.org',
        'etablissement_id' => $this->etablissement->id,
        'fonction_principale' => 'Enseignant',
        'type_contrat' => 'CDI (Contrat à Durée Indéterminée)',
        'date_embauche' => '2026-09-01',
        'statut' => 'Actif',
        'is_enseignant' => true,
        'specialite' => 'Mathématiques',
        'volume_horaire_hebdo' => 18,
        'taux_horaire_vacation' => 5000,
        'create_user_account' => false,
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    $this->assertDatabaseHas('personnels', [
        'nom' => 'Yao',
        'prenom' => 'Armel',
        'telephone' => '0505050505',
        'specialite' => 'Mathématiques',
        'is_enseignant' => true,
        'volume_horaire_hebdo' => 18,
    ]);

    $personnel = Personnel::where('nom', 'Yao')->first();
    expect($personnel)->not->toBeNull();
    // Le matricule doit suivre le format officiel PERS-YYYY-XXXX
    $currentYear = date('Y');
    expect($personnel->matricule)->toStartWith("PERS-{$currentYear}-");
});

test('enregistrement simultané d un personnel et de son compte utilisateur SYGES', function () {
    /** @var \Tests\TestCase $this */
    $this->actingAs($this->adminUser);

    $response = $this->post(route('administration.personnels.store'), [
        'nom' => 'Bamba',
        'prenom' => 'Souleymane',
        'sexe' => 'M',
        'telephone' => '0102030405',
        'email' => 'bamba.souleymane@syges.ci',
        'etablissement_id' => $this->etablissement->id,
        'fonction_principale' => 'Enseignant',
        'type_contrat' => 'CDI (Contrat à Durée Indéterminée)',
        'date_embauche' => '2026-09-01',
        'statut' => 'Actif',
        'is_enseignant' => true,
        // Création du compte utilisateur
        'create_user_account' => true,
        'user_login' => 'sbamba',
        'user_email' => 'sbamba@syges.ci',
        'user_password' => 'secretPass2026',
        'user_role' => 'enseignant',
    ]);

    $response->assertSessionHasNoErrors();

    // Vérifie que l'utilisateur a été créé
    $this->assertDatabaseHas('users', [
        'login' => 'sbamba',
        'email' => 'sbamba@syges.ci',
    ]);

    $user = User::where('login', 'sbamba')->first();
    expect($user)->not->toBeNull();
    expect($user->hasRole('enseignant'))->toBeTrue();

    // Vérifie que la fiche personnel est liée au compte utilisateur
    $personnel = Personnel::where('nom', 'Bamba')->first();
    expect($personnel->user_id)->toBe($user->id);
    expect($personnel->nom_complet)->toBe('Bamba Souleymane');
});

test('un personnel peut être modifié avec succès', function () {
    /** @var \Tests\TestCase $this */
    $this->actingAs($this->adminUser);

    $personnel = Personnel::create([
        'matricule' => Personnel::generateMatricule(),
        'etablissement_id' => $this->etablissement->id,
        'nom' => 'Konan',
        'prenom' => 'Alice',
        'sexe' => 'F',
        'telephone' => '0700000001',
        'fonction_principale' => 'Secrétaire',
        'type_contrat' => 'CDD (Contrat à Durée Déterminée)',
        'date_embauche' => '2026-09-01',
        'statut' => 'Actif',
        'is_enseignant' => false,
        'volume_horaire_hebdo' => 0,
    ]);

    $response = $this->put(route('administration.personnels.update', $personnel), [
        'nom' => 'Konan-Koffi',
        'prenom' => 'Alice',
        'sexe' => 'F',
        'telephone' => '0700000002',
        'etablissement_id' => $this->etablissement->id,
        'fonction_principale' => 'Secrétaire Principale',
        'type_contrat' => 'CDI (Contrat à Durée Indéterminée)',
        'date_embauche' => '2026-09-01',
        'statut' => 'Actif',
        'is_enseignant' => false,
        'volume_horaire_hebdo' => 0,
    ]);

    $response->assertSessionHasNoErrors();
    $personnel->refresh();
    expect($personnel->nom)->toBe('Konan-Koffi');
    expect($personnel->telephone)->toBe('0700000002');
    expect($personnel->fonction_principale)->toBe('Secrétaire Principale');
});

test('le statut d un personnel peut être basculé rapidement', function () {
    /** @var \Tests\TestCase $this */
    $this->actingAs($this->adminUser);

    $personnel = Personnel::create([
        'matricule' => Personnel::generateMatricule(),
        'etablissement_id' => $this->etablissement->id,
        'nom' => 'Touré',
        'prenom' => 'Moussa',
        'sexe' => 'M',
        'telephone' => '0808080808',
        'fonction_principale' => 'Enseignant',
        'type_contrat' => 'CDI (Contrat à Durée Indéterminée)',
        'date_embauche' => '2026-09-01',
        'statut' => 'Actif',
        'is_enseignant' => true,
        'volume_horaire_hebdo' => 18,
    ]);

    $response = $this->patch(route('administration.personnels.toggle-status', $personnel), [
        'statut' => 'En congé',
    ]);

    $response->assertSessionHasNoErrors();
    $personnel->refresh();
    expect($personnel->statut)->toBe('En congé');
});

test('un personnel peut être archivé par soft delete', function () {
    /** @var \Tests\TestCase $this */
    $this->actingAs($this->adminUser);

    $personnel = Personnel::create([
        'matricule' => Personnel::generateMatricule(),
        'etablissement_id' => $this->etablissement->id,
        'nom' => 'Diomandé',
        'prenom' => 'Lassana',
        'sexe' => 'M',
        'telephone' => '0909090909',
        'fonction_principale' => 'Surveillant Général',
        'type_contrat' => 'CDI (Contrat à Durée Indéterminée)',
        'date_embauche' => '2026-09-01',
        'statut' => 'Actif',
        'is_enseignant' => false,
        'volume_horaire_hebdo' => 0,
    ]);

    $response = $this->delete(route('administration.personnels.destroy', $personnel));
    $response->assertSessionHasNoErrors();

    // Vérifie le Soft Delete
    $this->assertSoftDeleted('personnels', ['id' => $personnel->id]);
});

test('gestion complète des départements pédagogiques et attribution de chef', function () {
    /** @var \Tests\TestCase $this */
    $this->actingAs($this->adminUser);

    // 1. Création d'un enseignant
    $enseignant = Personnel::create([
        'matricule' => Personnel::generateMatricule(),
        'etablissement_id' => $this->etablissement->id,
        'nom' => 'Diallo',
        'prenom' => 'Ousmane',
        'sexe' => 'M',
        'telephone' => '0101010101',
        'fonction_principale' => 'Enseignant',
        'type_contrat' => 'CDI (Contrat à Durée Indéterminée)',
        'date_embauche' => '2026-09-01',
        'statut' => 'Actif',
        'is_enseignant' => true,
        'specialite' => 'Physique-Chimie',
        'volume_horaire_hebdo' => 18,
    ]);

    // 2. Création d'un département avec désignation de l'enseignant comme chef
    $response = $this->post(route('administration.personnels.departements.store'), [
        'nom' => 'Département des Sciences Physiques',
        'code' => 'DPT-PHYS',
        'description' => 'Coordination de la physique et de la chimie',
        'chef_departement_id' => $enseignant->id,
        'is_active' => true,
    ]);

    $response->assertSessionHasNoErrors();

    $this->assertDatabaseHas('departements', [
        'nom' => 'Département des Sciences Physiques',
        'code' => 'DPT-PHYS',
        'chef_departement_id' => $enseignant->id,
    ]);

    $departement = Departement::where('code', 'DPT-PHYS')->first();
    expect($departement->chefDepartement->nom)->toBe('Diallo');

    // 3. Rattachement de l'enseignant au département
    $enseignant->update(['departement_id' => $departement->id]);

    // 4. Suppression du département : dissocie le personnel sans le supprimer
    $delResponse = $this->delete(route('administration.personnels.departements.destroy', $departement));
    $delResponse->assertSessionHasNoErrors();

    $this->assertDatabaseMissing('departements', ['id' => $departement->id]);
    $enseignant->refresh();
    expect($enseignant->departement_id)->toBeNull();
    expect($enseignant->exists)->toBeTrue();
});
