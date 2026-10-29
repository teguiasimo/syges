<?php

/** @var \Tests\TestCase $this */

use App\Models\Etablissement;
use App\Models\Institution;
use App\Models\Personnel;
use App\Models\Salle;
use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

/**
 * Tests de sécurité hermétique Multi-Tenant pour le rattachement d'établissement des utilisateurs.
 *
 * Conforme au Cahier des Charges Gesco V1.1 :
 * - Garantit qu'aucun compte utilisateur créé via Personnel ou Gestion des utilisateurs
 *   ne peut conserver `etablissement_id = null` s'il n'a pas le rôle `super-admin`.
 * - Garantit qu'un utilisateur non super-admin avec `etablissement_id = null` ne peut jamais
 *   accéder aux données d'autres établissements (EtablissementScope neutralise la requête).
 * - Garantit la synchronisation bidirectionnelle automatique entre Personnel et User.
 */

beforeEach(function () {
    /** @var \Tests\TestCase $this */
    Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'enseignant', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $this->institution = Institution::firstOrCreate(
        ['code' => 'INST-SEC-TEST'],
        [
            'sigle' => 'INST-SEC',
            'nom' => 'Institution Test Sécurité',
            'email' => 'sec@test.org',
            'telephone_1' => '+237 600 000 001',
            'pays' => 'Cameroun',
            'statut' => 'Actif',
        ]
    );

    $this->etablissementA = Etablissement::firstOrCreate(
        ['code' => 'ETAB-SEC-A'],
        [
            'institution_id' => $this->institution->id,
            'nom' => 'Campus Sécurité A',
            'type_etablissement' => 'Secondaire Général',
            'statut' => 'Actif',
        ]
    );

    $this->etablissementB = Etablissement::firstOrCreate(
        ['code' => 'ETAB-SEC-B'],
        [
            'institution_id' => $this->institution->id,
            'nom' => 'Campus Sécurité B',
            'type_etablissement' => 'Secondaire Général',
            'statut' => 'Actif',
        ]
    );
});

test('la création d un personnel avec compte utilisateur renseigne obligatoirement etablissement_id sur le user', function () {
    /** @var \Tests\TestCase $this */
    $admin = User::factory()->create(['etablissement_id' => $this->etablissementA->id]);
    $admin->assignRole('super-admin');
    $this->actingAs($admin);

    $response = $this->post(route('administration.personnels.store'), [
        'nom' => 'Nyangnda',
        'prenom' => 'Luc',
        'sexe' => 'M',
        'telephone' => '690000001',
        'email' => 'luc.nyangnda@syges.cm',
        'etablissement_id' => $this->etablissementA->id,
        'fonction_principale' => 'Enseignant',
        'type_contrat' => 'CDI (Contrat à Durée Indéterminée)',
        'date_embauche' => '2026-09-01',
        'statut' => 'Actif',
        'is_enseignant' => true,
        'create_user_account' => true,
        'user_login' => '26pcesmegueu0009',
        'user_email' => 'luc.nyangnda@syges.cm',
        'user_password' => 'motdepasse2026',
        'user_role' => 'enseignant',
    ]);

    $response->assertSessionHasNoErrors();

    // L'utilisateur DOIT impérativement avoir etablissement_id = etablissementA->id
    $createdUser = User::where('login', '26pcesmegueu0009')->first();
    expect($createdUser)->not->toBeNull();
    expect($createdUser->etablissement_id)->toBe($this->etablissementA->id);

    // La fiche personnel doit pointer vers cet utilisateur
    $personnel = Personnel::where('user_id', $createdUser->id)->first();
    expect($personnel)->not->toBeNull();
    expect($personnel->etablissement_id)->toBe($this->etablissementA->id);
});

test('la modification d une fiche personnel synchronise automatiquement le compte utilisateur associé', function () {
    /** @var \Tests\TestCase $this */
    $admin = User::factory()->create();
    $admin->assignRole('super-admin');
    $this->actingAs($admin);

    // Utilisateur existant sans établissement
    $teacherUser = User::factory()->create([
        'login' => 'prof.test',
        'etablissement_id' => null,
    ]);
    $teacherUser->assignRole('enseignant');

    $personnel = Personnel::create([
        'matricule' => Personnel::generateMatricule($this->etablissementA->id),
        'user_id' => $teacherUser->id,
        'etablissement_id' => $this->etablissementA->id,
        'nom' => 'Testeur',
        'prenom' => 'Prof',
        'sexe' => 'M',
        'telephone' => '690000002',
        'fonction_principale' => 'Enseignant',
        'type_contrat' => 'CDI (Contrat à Durée Indéterminée)',
        'date_embauche' => '2026-09-01',
        'statut' => 'Actif',
        'is_enseignant' => true,
    ]);

    // Grâce au hook booted de Personnel, le user doit être automatiquement aligné
    $teacherUser->refresh();
    expect($teacherUser->etablissement_id)->toBe($this->etablissementA->id);
});

test('un utilisateur non super-admin avec etablissement_id null ne peut voir aucune donnée grâce au EtablissementScope', function () {
    /** @var \Tests\TestCase $this */
    // Création de données sur campus A et campus B
    $salleA = Salle::withoutGlobalScopes()->create([
        'etablissement_id' => $this->etablissementA->id,
        'nom' => 'Salle 101 - Campus A',
        'code' => 'S101-CA',
        'capacite_places' => 40,
        'type_salle' => 'standard',
        'statut' => 'disponible',
    ]);

    $salleB = Salle::withoutGlobalScopes()->create([
        'etablissement_id' => $this->etablissementB->id,
        'nom' => 'Salle 201 - Campus B',
        'code' => 'S201-CB',
        'capacite_places' => 40,
        'type_salle' => 'standard',
        'statut' => 'disponible',
    ]);

    // Utilisateur anormal sans établissement et sans fiche personnel
    $rogueUser = User::factory()->create([
        'login' => 'rogue.user',
        'etablissement_id' => null,
    ]);
    $rogueUser->assignRole('enseignant');

    // Authentification en tant que cet utilisateur
    $this->actingAs($rogueUser);

    // La requête de salles avec le Global Scope actif NE DOIT RETOURNER AUCUNE SALLE (1 = 0)
    $accessibleSalles = Salle::all();
    expect($accessibleSalles)->toHaveCount(0);
});

test('un enseignant assigné au campus A ne peut voir que les données du campus A', function () {
    /** @var \Tests\TestCase $this */
    $teacherUser = User::factory()->create([
        'login' => 'teacher.campus.a',
        'etablissement_id' => $this->etablissementA->id,
    ]);
    $teacherUser->assignRole('enseignant');

    $salleA = Salle::withoutGlobalScopes()->create([
        'etablissement_id' => $this->etablissementA->id,
        'nom' => 'Salle 301 - Campus A',
        'code' => 'S301-CA',
        'capacite_places' => 35,
        'type_salle' => 'standard',
        'statut' => 'disponible',
    ]);

    $salleB = Salle::withoutGlobalScopes()->create([
        'etablissement_id' => $this->etablissementB->id,
        'nom' => 'Salle 401 - Campus B',
        'code' => 'S401-CB',
        'capacite_places' => 35,
        'type_salle' => 'standard',
        'statut' => 'disponible',
    ]);

    $this->actingAs($teacherUser);

    $accessibleSalles = Salle::all();
    expect($accessibleSalles->pluck('id')->all())->toContain($salleA->id);
    expect($accessibleSalles->pluck('id')->all())->not->toContain($salleB->id);
});
