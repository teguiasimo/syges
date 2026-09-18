<?php

/** @var \Tests\TestCase $this */

use App\Models\GroupeMatiere;
use App\Models\Matiere;
use App\Models\MatiereNiveau;
use App\Models\Niveau;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Tests de fonctionnalités pour le Module 4 : Gestion des Matières & Coefficients (Gesco).
 *
 * Vérifie :
 * - Le contrôle d'accès selon les permissions (matieres.view, matieres.manage).
 * - La consultation du catalogue, des blocs pédagogiques et de la grille des coefficients.
 * - Le CRUD complet des Groupes de Matières (Blocs Pédagogiques MINESEC/MINEDUB).
 * - L'intégrité référentielle interdisant la suppression d'un bloc rattaché à des coefficients.
 * - Le CRUD complet du Catalogue Général des Matières avec libellés bilingues.
 * - L'intégrité référentielle interdisant la suppression d'une matière configurée dans un niveau.
 * - La configuration des coefficients par niveau & série (note max, note éliminatoire V1.1, bonus facultatif).
 * - L'interdiction des doublons matière/niveau/série.
 * - L'initialisation automatique des standards officiels.
 */

beforeEach(function () {
    /** @var \Tests\TestCase $this */
    Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);

    $permissions = [
        'matieres.view',
        'matieres.manage',
    ];

    foreach ($permissions as $perm) {
        Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
    }

    $this->adminUser = User::factory()->create();
    $this->adminUser->assignRole('super-admin');

    // Récupération ou création d'une institution et d'un niveau de test
    $this->institution = \App\Models\Institution::firstOrCreate(
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

    $this->etablissement = \App\Models\Etablissement::first();
    if (! $this->etablissement) {
        $this->etablissement = \App\Models\Etablissement::firstOrCreate(
            ['code' => 'TEST-ETAB'],
            [
                'institution_id' => $this->institution->id,
                'nom' => 'Collège Test',
                'ordre_enseignement' => 'Secondaire_General',
            ]
        );
    }

    $this->niveau = Niveau::first();
    if (! $this->niveau) {
        $sec = \App\Models\Section::firstOrCreate(
            ['etablissement_id' => $this->etablissement->id, 'code' => 'SEC-FR'],
            ['nom' => 'Section FR', 'langue' => 'fr']
        );
        $cyc = \App\Models\Cycle::firstOrCreate(
            ['section_id' => $sec->id, 'code' => 'CYC-1'],
            ['nom' => 'Premier Cycle', 'ordre' => 1]
        );
        $this->niveau = Niveau::firstOrCreate(
            ['cycle_id' => $cyc->id, 'code' => '6EME-TEST'],
            ['nom' => 'Sixième Test', 'ordre' => 1, 'etablissement_id' => $this->etablissement->id]
        );
    }

    // Groupe et Matière de test
    $this->groupe = GroupeMatiere::firstOrCreate(
        ['code' => 'BLOC-TEST'],
        [
            'nom' => 'Bloc Scientifique Test',
            'nom_en' => 'Scientific Block Test',
            'couleur' => 'blue',
            'ordre' => 1,
        ]
    );

    $this->matiere = Matiere::firstOrCreate(
        ['code' => 'MATH-TEST'],
        [
            'nom' => 'Mathématiques Générales',
            'nom_en' => 'General Mathematics',
            'libelle_bulletin' => 'Mathématiques',
            'libelle_bulletin_en' => 'Mathematics',
            'is_active' => true,
        ]
    );
});

test('guests are redirected to login when visiting matieres page', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->get(route('administration.matieres.index'));
    $response->assertRedirect(route('login'));
});

test('unauthorized users without matieres.view permission cannot access matieres page', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('administration.matieres.index'));
    $response->assertForbidden();
});

test('authorized users can view matieres page with groups and subjects', function () {
    /** @var \Tests\TestCase $this */
    $this->actingAs($this->adminUser);

    $response = $this->get(route('administration.matieres.index'));
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('administration/matieres/index')
        ->has('etablissements')
        ->has('groupes')
        ->has('matieres')
        ->has('niveaux')
        ->has('coefficients')
        ->has('can')
        ->where('can.matieres_manage', true)
    );
});

/**
 * Teste le filtrage de la grille des matières et niveaux par établissement scolaire.
 */
test('can filter matieres and niveaux by etablissement_id', function () {
    /** @var \Tests\TestCase $this */
    $this->actingAs($this->adminUser);

    // Requête avec filtrage contextuel sur l'établissement actif
    $response = $this->get(route('administration.matieres.index', [
        'etablissement_id' => $this->etablissement->id,
    ]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('administration/matieres/index')
        ->where('selectedEtablissementId', $this->etablissement->id)
        ->has('niveaux')
    );
});

test('can create a new pedagogical block (groupe matiere)', function () {
    /** @var \Tests\TestCase $this */
    $this->actingAs($this->adminUser);

    $response = $this->post(route('administration.matieres.groupes.store'), [
        'code' => 'BLOC-NOUV',
        'nom' => 'Bloc Sciences Économiques',
        'nom_en' => 'Economic Sciences Block',
        'couleur' => 'amber',
        'ordre' => 4,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('groupes_matieres', [
        'code' => 'BLOC-NOUV',
        'nom' => 'Bloc Sciences Économiques',
        'ordre' => 4,
    ]);
});

test('cannot create a pedagogical block with duplicate code', function () {
    /** @var \Tests\TestCase $this */
    $this->actingAs($this->adminUser);

    $response = $this->post(route('administration.matieres.groupes.store'), [
        'code' => $this->groupe->code,
        'nom' => 'Nom dupliqué',
        'ordre' => 2,
    ]);

    $response->assertSessionHasErrors(['code']);
});

test('can update a pedagogical block', function () {
    /** @var \Tests\TestCase $this */
    $this->actingAs($this->adminUser);

    $response = $this->put(route('administration.matieres.groupes.update', $this->groupe->id), [
        'code' => $this->groupe->code,
        'nom' => 'Bloc Scientifique Modifié',
        'nom_en' => 'Updated Science Block',
        'couleur' => 'emerald',
        'ordre' => 1,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('groupes_matieres', [
        'id' => $this->groupe->id,
        'nom' => 'Bloc Scientifique Modifié',
        'couleur' => 'emerald',
    ]);
});

test('can delete an unattached pedagogical block', function () {
    /** @var \Tests\TestCase $this */
    $this->actingAs($this->adminUser);

    $groupeVide = GroupeMatiere::create([
        'code' => 'BLOC-SUPPR',
        'nom' => 'Bloc à supprimer',
        'ordre' => 10,
    ]);

    $response = $this->delete(route('administration.matieres.groupes.destroy', $groupeVide->id));
    $response->assertRedirect();
    $this->assertDatabaseMissing('groupes_matieres', ['id' => $groupeVide->id]);
});

test('cannot delete a pedagogical block with associated coefficients', function () {
    /** @var \Tests\TestCase $this */
    $this->actingAs($this->adminUser);

    MatiereNiveau::firstOrCreate(
        [
            'niveau_id' => $this->niveau->id,
            'matiere_id' => $this->matiere->id,
            'serie' => 'Toutes',
        ],
        [
            'groupe_matiere_id' => $this->groupe->id,
            'coefficient' => 4.0,
            'note_max' => 20.0,
            'is_comptabilisee' => true,
        ]
    );

    $response = $this->delete(route('administration.matieres.groupes.destroy', $this->groupe->id));
    $response->assertSessionHas('error');
    $this->assertDatabaseHas('groupes_matieres', ['id' => $this->groupe->id]);
});

test('can add a new subject to general catalogue', function () {
    /** @var \Tests\TestCase $this */
    $this->actingAs($this->adminUser);

    $response = $this->post(route('administration.matieres.catalogue.store'), [
        'code' => 'CHIM-TEST',
        'nom' => 'Chimie Organique',
        'nom_en' => 'Organic Chemistry',
        'libelle_bulletin' => 'Chimie Org.',
        'libelle_bulletin_en' => 'Org. Chemistry',
        'is_active' => true,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('matieres', [
        'code' => 'CHIM-TEST',
        'nom' => 'Chimie Organique',
    ]);
});

test('can update a subject in catalogue', function () {
    /** @var \Tests\TestCase $this */
    $this->actingAs($this->adminUser);

    $response = $this->put(route('administration.matieres.catalogue.update', $this->matiere->id), [
        'code' => $this->matiere->code,
        'nom' => 'Mathématiques Avancées',
        'nom_en' => 'Advanced Mathematics',
        'is_active' => true,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('matieres', [
        'id' => $this->matiere->id,
        'nom' => 'Mathématiques Avancées',
    ]);
});

test('can configure a subject coefficient for a level and serie with elimination mark', function () {
    /** @var \Tests\TestCase $this */
    $this->actingAs($this->adminUser);

    $nouvelleMatiere = Matiere::create([
        'code' => 'PHYS-TEST',
        'nom' => 'Physique Quantique',
        'is_active' => true,
    ]);

    $response = $this->post(route('administration.matieres.coefficients.store'), [
        'niveau_id' => $this->niveau->id,
        'matiere_id' => $nouvelleMatiere->id,
        'groupe_matiere_id' => $this->groupe->id,
        'serie' => 'C',
        'coefficient' => 5.0,
        'note_max' => 20.0,
        'note_eliminatoire' => 7.0,
        'is_facultative' => false,
        'is_comptabilisee' => true,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('matiere_niveaux', [
        'niveau_id' => $this->niveau->id,
        'matiere_id' => $nouvelleMatiere->id,
        'serie' => 'C',
        'coefficient' => 5.0,
        'note_eliminatoire' => 7.0,
    ]);
});

test('cannot configure duplicate subject on same level and serie', function () {
    /** @var \Tests\TestCase $this */
    $this->actingAs($this->adminUser);

    MatiereNiveau::firstOrCreate(
        [
            'niveau_id' => $this->niveau->id,
            'matiere_id' => $this->matiere->id,
            'serie' => 'D',
        ],
        [
            'groupe_matiere_id' => $this->groupe->id,
            'coefficient' => 3.0,
            'note_max' => 20.0,
            'is_comptabilisee' => true,
        ]
    );

    $response = $this->post(route('administration.matieres.coefficients.store'), [
        'niveau_id' => $this->niveau->id,
        'matiere_id' => $this->matiere->id,
        'groupe_matiere_id' => $this->groupe->id,
        'serie' => 'D',
        'coefficient' => 4.0,
        'note_max' => 20.0,
        'is_comptabilisee' => true,
    ]);

    $response->assertSessionHasErrors(['matiere_id']);
});

test('can update and delete a coefficient configuration', function () {
    /** @var \Tests\TestCase $this */
    $this->actingAs($this->adminUser);

    $coef = MatiereNiveau::firstOrCreate(
        [
            'niveau_id' => $this->niveau->id,
            'matiere_id' => $this->matiere->id,
            'serie' => 'TI',
        ],
        [
            'groupe_matiere_id' => $this->groupe->id,
            'coefficient' => 3.0,
            'note_max' => 20.0,
            'is_comptabilisee' => true,
        ]
    );

    // Mise à jour
    $response = $this->put(route('administration.matieres.coefficients.update', $coef->id), [
        'groupe_matiere_id' => $this->groupe->id,
        'serie' => 'TI',
        'coefficient' => 4.5,
        'note_max' => 20.0,
        'note_eliminatoire' => 6.0,
        'is_facultative' => true,
        'is_comptabilisee' => true,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('matiere_niveaux', [
        'id' => $coef->id,
        'coefficient' => 4.5,
        'is_facultative' => true,
    ]);

    // Suppression
    $delResponse = $this->delete(route('administration.matieres.coefficients.destroy', $coef->id));
    $delResponse->assertRedirect();
    $this->assertDatabaseMissing('matiere_niveaux', ['id' => $coef->id]);
});

test('can seed standard official subjects and pedagogical blocks', function () {
    /** @var \Tests\TestCase $this */
    $this->actingAs($this->adminUser);

    $response = $this->post(route('administration.matieres.seed-standards'));
    $response->assertRedirect();

    $this->assertDatabaseHas('groupes_matieres', ['code' => 'BLOC-SCI']);
    $this->assertDatabaseHas('groupes_matieres', ['code' => 'BLOC-LIT']);
    $this->assertDatabaseHas('matieres', ['code' => 'MATH']);
    $this->assertDatabaseHas('matieres', ['code' => 'FRAN']);
});

/**
 * Teste la coexistence de blocs pédagogiques portant le même code dans deux établissements distincts (isolation SaaS).
 */
test('can create pedagogical blocks with same code in different establishments without collision', function () {
    /** @var \Tests\TestCase $this */
    $this->actingAs($this->adminUser);

    $autreEtab = \App\Models\Etablissement::firstOrCreate(
        ['code' => 'TEST-ETAB-2'],
        [
            'institution_id' => $this->institution->id,
            'nom' => 'Lycée Technique Bilingue Test',
            'ordre_enseignement' => 'Secondaire_Technique',
        ]
    );

    // Création bloc pour l'établissement 1
    $response1 = $this->post(route('administration.matieres.groupes.store'), [
        'etablissement_id' => $this->etablissement->id,
        'code' => 'BLOC-ISO',
        'nom' => 'Bloc Isolation Etab 1',
        'ordre' => 5,
    ]);
    $response1->assertRedirect();

    // Création bloc avec le MÊME code pour l'établissement 2
    $response2 = $this->post(route('administration.matieres.groupes.store'), [
        'etablissement_id' => $autreEtab->id,
        'code' => 'BLOC-ISO',
        'nom' => 'Bloc Isolation Etab 2',
        'ordre' => 5,
    ]);
    $response2->assertRedirect();

    $this->assertDatabaseHas('groupes_matieres', [
        'etablissement_id' => $this->etablissement->id,
        'code' => 'BLOC-ISO',
        'nom' => 'Bloc Isolation Etab 1',
    ]);

    $this->assertDatabaseHas('groupes_matieres', [
        'etablissement_id' => $autreEtab->id,
        'code' => 'BLOC-ISO',
        'nom' => 'Bloc Isolation Etab 2',
    ]);
});

/**
 * Teste l'interdiction de doublons de code de bloc au sein d'un même établissement.
 */
test('cannot create pedagogical block with duplicate code in same establishment', function () {
    /** @var \Tests\TestCase $this */
    $this->actingAs($this->adminUser);

    $this->post(route('administration.matieres.groupes.store'), [
        'etablissement_id' => $this->etablissement->id,
        'code' => 'BLOC-UNIQUE',
        'nom' => 'Bloc Unique 1',
        'ordre' => 6,
    ])->assertRedirect();

    $response = $this->post(route('administration.matieres.groupes.store'), [
        'etablissement_id' => $this->etablissement->id,
        'code' => 'BLOC-UNIQUE',
        'nom' => 'Bloc Unique Dupliqué',
        'ordre' => 7,
    ]);

    $response->assertSessionHasErrors(['code']);
});

/**
 * Teste la création d'une discipline spécifique à un établissement vs une discipline du tronc commun.
 */
test('can manage subjects with establishment scope and common core scope', function () {
    /** @var \Tests\TestCase $this */
    $this->actingAs($this->adminUser);

    // Matière spécifique à l'établissement
    $responseSpec = $this->post(route('administration.matieres.catalogue.store'), [
        'etablissement_id' => $this->etablissement->id,
        'code' => 'ROBOT-SPEC',
        'nom' => 'Robotique Avancée Campus',
        'is_active' => true,
    ]);
    $responseSpec->assertRedirect();

    // Matière du tronc commun (etablissement_id = null)
    $responseComm = $this->post(route('administration.matieres.catalogue.store'), [
        'etablissement_id' => null,
        'code' => 'PHILO-COMM',
        'nom' => 'Philosophie Générale',
        'is_active' => true,
    ]);
    $responseComm->assertRedirect();

    $this->assertDatabaseHas('matieres', [
        'code' => 'ROBOT-SPEC',
        'etablissement_id' => $this->etablissement->id,
    ]);

    $this->assertDatabaseHas('matieres', [
        'code' => 'PHILO-COMM',
        'etablissement_id' => null,
    ]);
});

/**
 * Teste le seed des standards avec adaptation automatique selon l'ordre d'enseignement de l'établissement.
 */
test('seedStandards creates appropriate pedagogical blocks for specific establishment', function () {
    /** @var \Tests\TestCase $this */
    $this->actingAs($this->adminUser);

    $etabPrimaire = \App\Models\Etablissement::firstOrCreate(
        ['code' => 'TEST-PRIMAIRE'],
        [
            'institution_id' => $this->institution->id,
            'nom' => 'École Publique d\'Application Test',
            'ordre_enseignement' => 'Primaire',
        ]
    );

    $response = $this->post(route('administration.matieres.seed-standards'), [
        'etablissement_id' => $etabPrimaire->id,
    ]);
    $response->assertRedirect();

    // L'école primaire doit recevoir les 4 domaines MINEDUB
    $this->assertDatabaseHas('groupes_matieres', [
        'etablissement_id' => $etabPrimaire->id,
        'code' => 'DOM-LANG',
    ]);
    $this->assertDatabaseHas('groupes_matieres', [
        'etablissement_id' => $etabPrimaire->id,
        'code' => 'DOM-SCI',
    ]);
});
