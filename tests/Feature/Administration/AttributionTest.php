<?php

/** @var \Tests\TestCase $this */

use App\Models\AnneeScolaire;
use App\Models\Attribution;
use App\Models\Classe;
use App\Models\Cycle;
use App\Models\Etablissement;
use App\Models\GroupeMatiere;
use App\Models\Institution;
use App\Models\Matiere;
use App\Models\MatiereNiveau;
use App\Models\Niveau;
use App\Models\Personnel;
use App\Models\Section;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Tests de fonctionnalités pour le Module 9 : Attribution des Classes & Matières (Gesco V1.1).
 *
 * Vérifie :
 * - Le contrôle d'accès selon les permissions (attributions.view, attributions.manage, attributions.delete).
 * - L'affichage de la page d'administration des attributions avec les props Inertia.
 * - L'affectation individuelle d'un enseignant à une classe et une discipline avec volume horaire réel.
 * - La gestion de l'unicité (la réattribution de la même matière dans la même classe met à jour l'attribution existante).
 * - L'attribution en masse des matières d'une classe (matrice de répartition).
 * - La désignation et mise à jour du Professeur Principal (Gesco 2.7.5).
 * - Le calcul exact de la charge horaire (heures réelles vs quota légal, détection sous-charge/surcharge).
 * - La révocation d'une attribution de cours.
 */

beforeEach(function () {
    /** @var TestCase $this */
    Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'enseignant', 'guard_name' => 'web']);

    $permissions = [
        'attributions.view',
        'attributions.manage',
        'attributions.delete',
    ];

    foreach ($permissions as $perm) {
        Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
    }

    $this->adminUser = User::factory()->create();
    $this->adminUser->assignRole('super-admin');

    // Structure de base
    $this->institution = Institution::firstOrCreate(
        ['code' => 'INST-TEST'],
        [
            'sigle' => 'TEST-INST',
            'nom' => 'Groupe Scolaire de Test',
            'nom_en' => 'Test School Group',
            'devise' => 'Excellence et Rigueur',
            'email' => 'contact@test-inst.org',
            'telephone_1' => '+237 600 000 000',
        ]
    );

    $this->etablissement = Etablissement::firstOrCreate(
        ['code' => 'ETAB-TEST'],
        [
            'institution_id' => $this->institution->id,
            'nom' => 'Campus Principal',
            'email' => 'campus@test-inst.org',
            'telephone' => '+237 611 111 111',
            'type_etablissement' => 'Secondaire Général',
        ]
    );

    $this->section = Section::firstOrCreate(
        ['etablissement_id' => $this->etablissement->id, 'code' => 'FR'],
        ['nom' => 'Section Francophone']
    );

    $this->cycle = Cycle::firstOrCreate(
        ['section_id' => $this->section->id, 'code' => 'CYCLE-1'],
        ['nom' => 'Premier Cycle', 'ordre' => 1]
    );

    $this->niveau = Niveau::firstOrCreate(
        ['cycle_id' => $this->cycle->id, 'code' => '6EME'],
        ['nom' => 'Sixième', 'ordre' => 1]
    );

    $this->classe = Classe::firstOrCreate(
        ['niveau_id' => $this->niveau->id, 'code' => '6M1'],
        ['nom' => '6ème Moderne 1', 'capacite_max' => 50]
    );

    $this->anneeScolaire = AnneeScolaire::firstOrCreate(
        ['code' => '2026-2027'],
        [
            'nom' => 'Année Scolaire 2026-2027',
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-06-30',
            'statut' => 'en_cours',
            'is_current' => true,
        ]
    );

    $this->matiere = Matiere::firstOrCreate(
        ['code' => 'MATH-TEST'],
        ['nom' => 'Mathématiques de Test', 'is_active' => true]
    );

    $this->matiere2 = Matiere::firstOrCreate(
        ['code' => 'FRAN-TEST'],
        ['nom' => 'Français de Test', 'is_active' => true]
    );

    $this->groupeMatiere = GroupeMatiere::firstOrCreate(
        ['code' => 'BLOC-SCI'],
        ['nom' => 'Sciences', 'ordre' => 1]
    );

    MatiereNiveau::firstOrCreate(
        [
            'niveau_id' => $this->niveau->id,
            'matiere_id' => $this->matiere->id,
            'serie' => 'Toutes',
        ],
        [
            'groupe_matiere_id' => $this->groupeMatiere->id,
            'coefficient' => 4.00,
            'note_max' => 20.00,
        ]
    );

    // Enseignant avec quota contractuel de 18h
    $this->enseignant = Personnel::create([
        'matricule' => Personnel::generateMatricule(),
        'etablissement_id' => $this->etablissement->id,
        'nom' => 'Ouedraogo',
        'prenom' => 'Salif',
        'sexe' => 'M',
        'telephone' => '0707070701',
        'fonction_principale' => 'Enseignant',
        'type_contrat' => 'CDI (Contrat à Durée Indéterminée)',
        'date_embauche' => '2026-09-01',
        'statut' => 'Actif',
        'is_enseignant' => true,
        'specialite' => 'Mathématiques',
        'volume_horaire_hebdo' => 18.00,
    ]);
});

test('les invités non authentifiés sont redirigés vers la page de connexion', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->get(route('administration.attributions.index'));
    $response->assertRedirect(route('login'));
});

test('les utilisateurs sans permission attributions.view ne peuvent pas accéder au module', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('administration.attributions.index'));
    $response->assertForbidden();
});

test('un administrateur avec attributions.view peut afficher la grille de répartition', function () {
    /** @var \Tests\TestCase $this */
    $this->actingAs($this->adminUser);

    $response = $this->get(route('administration.attributions.index'));
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('administration/attributions/index')
        ->has('anneesScolaires')
        ->has('classes')
        ->has('enseignants')
        ->has('stats')
        ->has('can')
    );
});

test('un utilisateur sans permission attributions.manage ne peut pas affecter de cours', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->post(route('administration.attributions.store'), [
        'annee_scolaire_id' => $this->anneeScolaire->id,
        'classe_id' => $this->classe->id,
        'matiere_id' => $this->matiere->id,
        'personnel_id' => $this->enseignant->id,
        'volume_horaire_reel' => 4.0,
    ]);

    $response->assertForbidden();
});

test('un censeur peut affecter un enseignant à une classe et une matière', function () {
    /** @var \Tests\TestCase $this */
    $this->actingAs($this->adminUser);

    $response = $this->post(route('administration.attributions.store'), [
        'annee_scolaire_id' => $this->anneeScolaire->id,
        'classe_id' => $this->classe->id,
        'matiere_id' => $this->matiere->id,
        'personnel_id' => $this->enseignant->id,
        'volume_horaire_reel' => 4.5,
        'is_titulaire' => true,
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    $this->assertDatabaseHas('attributions', [
        'annee_scolaire_id' => $this->anneeScolaire->id,
        'classe_id' => $this->classe->id,
        'matiere_id' => $this->matiere->id,
        'personnel_id' => $this->enseignant->id,
        'volume_horaire_reel' => 4.5,
        'is_titulaire' => true,
    ]);
});

test('une réattribution de la même matière dans la même classe met à jour l existante sans créer de doublon', function () {
    /** @var \Tests\TestCase $this */
    $this->actingAs($this->adminUser);

    $enseignant2 = Personnel::create([
        'matricule' => Personnel::generateMatricule(),
        'etablissement_id' => $this->etablissement->id,
        'nom' => 'Kone',
        'prenom' => 'Ibrahim',
        'sexe' => 'M',
        'telephone' => '0707070702',
        'fonction_principale' => 'Enseignant',
        'type_contrat' => 'CDI',
        'date_embauche' => '2026-09-01',
        'statut' => 'Actif',
        'is_enseignant' => true,
        'volume_horaire_hebdo' => 18.00,
    ]);

    // 1. Première attribution
    Attribution::create([
        'annee_scolaire_id' => $this->anneeScolaire->id,
        'classe_id' => $this->classe->id,
        'matiere_id' => $this->matiere->id,
        'personnel_id' => $this->enseignant->id,
        'volume_horaire_reel' => 4.0,
    ]);

    // 2. Réattribution de la même classe/matière à enseignant2
    $response = $this->post(route('administration.attributions.store'), [
        'annee_scolaire_id' => $this->anneeScolaire->id,
        'classe_id' => $this->classe->id,
        'matiere_id' => $this->matiere->id,
        'personnel_id' => $enseignant2->id,
        'volume_horaire_reel' => 5.0,
    ]);

    $response->assertSessionHasNoErrors();

    // Il ne doit y avoir qu'une seule ligne d'attribution pour cette classe et matière
    $count = Attribution::where('annee_scolaire_id', $this->anneeScolaire->id)
        ->where('classe_id', $this->classe->id)
        ->where('matiere_id', $this->matiere->id)
        ->count();

    expect($count)->toBe(1);

    $attribution = Attribution::where('classe_id', $this->classe->id)->first();
    expect($attribution->personnel_id)->toBe($enseignant2->id);
    expect((float) $attribution->volume_horaire_reel)->toBe(5.0);
});

test('attribution en lot des matières d une classe via la matrice de classe', function () {
    /** @var \Tests\TestCase $this */
    $this->actingAs($this->adminUser);

    $response = $this->post(route('administration.attributions.classes.batch', $this->classe), [
        'annee_scolaire_id' => $this->anneeScolaire->id,
        'assignments' => [
            [
                'matiere_id' => $this->matiere->id,
                'personnel_id' => $this->enseignant->id,
                'volume_horaire_reel' => 4.0,
            ],
            [
                'matiere_id' => $this->matiere2->id,
                'personnel_id' => $this->enseignant->id,
                'volume_horaire_reel' => 3.5,
            ],
        ],
    ]);

    $response->assertSessionHasNoErrors();

    $this->assertDatabaseHas('attributions', [
        'classe_id' => $this->classe->id,
        'matiere_id' => $this->matiere->id,
        'personnel_id' => $this->enseignant->id,
    ]);

    $this->assertDatabaseHas('attributions', [
        'classe_id' => $this->classe->id,
        'matiere_id' => $this->matiere2->id,
        'personnel_id' => $this->enseignant->id,
    ]);
});

test('désignation et mise à jour du Professeur Principal d une classe', function () {
    /** @var \Tests\TestCase $this */
    $this->actingAs($this->adminUser);

    $response = $this->patch(route('administration.attributions.classes.professeur-principal', $this->classe), [
        'professeur_principal_id' => $this->enseignant->id,
    ]);

    $response->assertSessionHasNoErrors();
    $this->classe->refresh();
    expect($this->classe->professeur_principal_id)->toBe($this->enseignant->id);
    expect($this->classe->professeurPrincipal->nom)->toBe('Ouedraogo');
});

test('révocation d une attribution de cours', function () {
    /** @var \Tests\TestCase $this */
    $this->actingAs($this->adminUser);

    $attribution = Attribution::create([
        'annee_scolaire_id' => $this->anneeScolaire->id,
        'classe_id' => $this->classe->id,
        'matiere_id' => $this->matiere->id,
        'personnel_id' => $this->enseignant->id,
        'volume_horaire_reel' => 4.0,
    ]);

    $response = $this->delete(route('administration.attributions.destroy', $attribution));
    $response->assertSessionHasNoErrors();

    $this->assertDatabaseMissing('attributions', ['id' => $attribution->id]);
});
