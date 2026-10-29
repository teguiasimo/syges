<?php

/** @var \Tests\TestCase $this */

use App\Models\AnneeScolaire;
use App\Models\Attribution;
use App\Models\Classe;
use App\Models\Cycle;
use App\Models\Eleve;
use App\Models\Etablissement;
use App\Models\Evaluation;
use App\Models\Inscription;
use App\Models\Institution;
use App\Models\Matiere;
use App\Models\Niveau;
use App\Models\Note;
use App\Models\Periode;
use App\Models\Personnel;
use App\Models\Section;
use App\Models\User;
use Carbon\Carbon;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Suite de tests de sécurité et d'intégrité pour le Verrouillage des Évaluations (Module Gesco 3 & 10).
 *
 * Vérifie :
 * 1. Le verrouillage administratif depuis le module Périodes (/administration/periodes/evaluations/{evaluation}/toggle-lock)
 *    accessible au Proviseur ('periodes.lock'), au Censeur ('evaluations.lock') et au Super-Admin.
 * 2. Le refus catégorique de saisie de notes (HTTP 403) pour un enseignant lorsque l'évaluation est verrouillée (is_locked = true).
 * 3. Le refus catégorique de saisie de notes (HTTP 403) lorsque la date limite de saisie est dépassée (date_limite_saisie < today).
 * 4. Le refus de modification (HTTP 403) lorsque la grille spécifique a été verrouillée (is_verrouillee = true).
 * 5. L'autorisation accordée aux gestionnaires (Censeur, Super-Admin) de déverrouiller ou de corriger exceptionnellement.
 * 6. Le bon état de lecture seule renvoyé par l'API getGrille.
 */
beforeEach(function () {
    /** @var \Tests\TestCase $this */
    Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'Censeur', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'enseignant', 'guard_name' => 'web']);

    $permissions = [
        'periodes.view',
        'periodes.manage',
        'periodes.lock',
        'evaluations.view',
        'evaluations.lock',
        'notes.view',
        'notes.create',
        'notes.lock',
        'notes.unlock',
    ];

    foreach ($permissions as $perm) {
        Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
    }

    $censeurRole = Role::findByName('Censeur', 'web');
    $censeurRole->syncPermissions(['periodes.view', 'evaluations.lock', 'notes.view', 'notes.lock', 'notes.unlock']);

    $teacherRole = Role::findByName('enseignant', 'web');
    $teacherRole->syncPermissions(['notes.view', 'notes.create']);

    $this->adminUser = User::factory()->create();
    $this->adminUser->assignRole('super-admin');

    // Structure académique
    $this->institution = Institution::firstOrCreate(
        ['code' => 'INST-LOCK-TEST'],
        [
            'sigle' => 'INSTLOCK',
            'nom' => 'Institution Test Verrouillage',
            'email' => 'contact@locktest.org',
            'telephone_1' => '+237690000000',
        ]
    );

    $this->etablissement = Etablissement::firstOrCreate(
        ['code' => 'ETAB-LOCK-TEST'],
        [
            'institution_id' => $this->institution->id,
            'nom' => 'Lycée Test Verrouillage',
            'nom_en' => 'Lock Test High School',
            'statut_juridique' => 'Public',
            'ordre_enseignement' => 'Secondaire_General',
        ]
    );

    $this->section = Section::firstOrCreate(
        ['code' => 'SEC-LOCK-FR'],
        [
            'etablissement_id' => $this->etablissement->id,
            'nom' => 'Section Francophone',
            'type_enseignement' => 'General',
        ]
    );

    $this->cycle = Cycle::firstOrCreate(
        ['code' => 'CYC-LOCK-1'],
        [
            'section_id' => $this->section->id,
            'nom' => 'Premier Cycle',
            'ordre' => 1,
        ]
    );

    $this->niveau = Niveau::firstOrCreate(
        ['code' => 'NIV-LOCK-6EME'],
        [
            'cycle_id' => $this->cycle->id,
            'nom' => '6ème',
            'ordre' => 1,
        ]
    );

    $this->classe = Classe::firstOrCreate(
        ['code' => '6EME-LOCK-A'],
        [
            'etablissement_id' => $this->etablissement->id,
            'niveau_id' => $this->niveau->id,
            'nom' => '6ème A',
            'capacite_max' => 60,
        ]
    );
    $this->classe->update(['etablissement_id' => $this->etablissement->id]);

    $this->annee = AnneeScolaire::firstOrCreate(
        ['code' => '2026-2027-LOCK'],
        [
            'nom' => 'Année Scolaire 2026-2027',
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-06-30',
            'is_current' => true,
        ]
    );

    $this->periode = Periode::firstOrCreate(
        ['annee_scolaire_id' => $this->annee->id, 'code' => 'TRIM-1-LOCK'],
        [
            'etablissement_id' => $this->etablissement->id,
            'nom' => '1er Trimestre',
            'ordre' => 1,
            'date_debut' => '2026-09-01',
            'date_fin' => '2026-11-30',
            'statut' => 'en_cours',
        ]
    );

    $this->eval1 = Evaluation::firstOrCreate(
        ['periode_id' => $this->periode->id, 'code' => 'EVA-1-LOCK'],
        [
            'nom' => 'Évaluation 1',
            'ordre' => 1,
            'ponderation' => 50.0,
            'date_debut' => '2026-09-01',
            'date_fin' => '2026-10-15',
            'date_limite_saisie' => Carbon::now()->addDays(7)->toDateString(),
            'is_locked' => false,
            'statut' => 'ouverte',
        ]
    );

    $this->matiere = Matiere::firstOrCreate(
        ['code' => 'MAT-LOCK-1'],
        ['nom' => 'Informatique', 'libelle_bulletin' => 'Informatique']
    );

    // Enseignant
    $this->teacherUser = User::factory()->create([
        'email' => 'prof.lock@syges.test',
        'etablissement_id' => $this->etablissement->id,
    ]);
    $this->teacherUser->assignRole('enseignant');

    $this->personnel = Personnel::firstOrCreate(
        ['matricule' => 'ENS-LOCK-01'],
        [
            'user_id' => $this->teacherUser->id,
            'etablissement_id' => $this->etablissement->id,
            'nom' => 'KAMENI',
            'prenom' => 'Arnaud',
            'sexe' => 'M',
            'telephone' => '0600000001',
            'fonction_principale' => 'Enseignant',
            'type_contrat' => 'CDI (Contrat à Durée Indéterminée)',
            'date_embauche' => '2026-09-01',
            'statut' => 'Actif',
            'email' => 'prof.lock@syges.test',
            'is_enseignant' => true,
        ]
    );

    // Censeur
    $this->censeurUser = User::factory()->create([
        'email' => 'censeur.lock@syges.test',
        'etablissement_id' => $this->etablissement->id,
    ]);
    $this->censeurUser->assignRole('Censeur');

    $this->attribution = Attribution::firstOrCreate(
        [
            'annee_scolaire_id' => $this->annee->id,
            'classe_id' => $this->classe->id,
            'matiere_id' => $this->matiere->id,
        ],
        [
            'personnel_id' => $this->personnel->id,
        ]
    );

    // Élève et inscription
    $this->eleve = Eleve::firstOrCreate(
        ['matricule' => 'MAT-LOCK-001'],
        [
            'nom' => 'FOTSO',
            'prenom' => 'David',
            'sexe' => 'M',
            'date_naissance' => '2014-03-10',
            'lieu_naissance' => 'Bafoussam',
        ]
    );

    $this->inscription = Inscription::firstOrCreate(
        ['eleve_id' => $this->eleve->id, 'annee_scolaire_id' => $this->annee->id],
        ['classe_id' => $this->classe->id, 'statut' => 'validee']
    );
});

test('un gestionnaire peut verrouiller et deverrouiller une evaluation depuis periodes', function () {
    /** @var \Tests\TestCase $this */
    // 1. Verrouillage par le Super-Admin
    $this->actingAs($this->adminUser)
        ->patch(route('administration.periodes.evaluations.toggle-lock', $this->eval1))
        ->assertRedirect();

    $this->eval1->refresh();
    expect($this->eval1->is_locked)->toBeTrue();
    expect($this->eval1->statut)->toBe('verrouillee');
    expect($this->eval1->isLockedForGrading())->toBeTrue();

    // 2. Déverrouillage par le Censeur (qui a la permission evaluations.lock)
    $this->actingAs($this->censeurUser)
        ->patch(route('administration.periodes.evaluations.toggle-lock', $this->eval1))
        ->assertRedirect();

    $this->eval1->refresh();
    expect($this->eval1->is_locked)->toBeFalse();
    expect($this->eval1->statut)->toBe('ouverte');
    expect($this->eval1->isLockedForGrading())->toBeFalse();
});

test('un enseignant sans permission ne peut pas verrouiller ou deverrouiller une evaluation', function () {
    /** @var \Tests\TestCase $this */
    $this->actingAs($this->teacherUser)
        ->patch(route('administration.periodes.evaluations.toggle-lock', $this->eval1))
        ->assertForbidden();

    expect($this->eval1->fresh()->is_locked)->toBeFalse();
});

test('la grille de saisie getGrille signale correctement l etat is_locked quand l evaluation est verrouillee', function () {
    /** @var \Tests\TestCase $this */
    $this->eval1->update(['is_locked' => true, 'statut' => 'verrouillee']);

    $response = $this->actingAs($this->teacherUser)
        ->getJson(route('administration.notes.grille', [
            'attribution_id' => $this->attribution->id,
            'evaluation_id' => $this->eval1->id,
        ]));

    $response->assertOk()
        ->assertJson([
            'is_locked' => true,
        ]);
});

test('un enseignant peut enregistrer les notes quand l evaluation est ouverte', function () {
    /** @var \Tests\TestCase $this */
    $this->eval1->update(['is_locked' => false, 'date_limite_saisie' => Carbon::now()->addDays(5)->toDateString()]);

    $payload = [
        'attribution_id' => $this->attribution->id,
        'evaluation_id' => $this->eval1->id,
        'notes' => [
            [
                'inscription_id' => $this->inscription->id,
                'valeur_note' => 15.5,
                'statut_presence' => 'present',
                'appreciation' => 'Bon travail',
                'competence_evaluee' => 'Programmation',
                'cote' => 'CBA',
            ],
        ],
    ];

    $response = $this->actingAs($this->teacherUser)
        ->postJson(route('administration.notes.sauvegarder'), $payload);

    $response->assertOk()
        ->assertJson(['success' => true]);

    $this->assertDatabaseHas('notes', [
        'attribution_id' => $this->attribution->id,
        'evaluation_id' => $this->eval1->id,
        'inscription_id' => $this->inscription->id,
        'valeur_note' => 15.5,
    ]);
});

test('un enseignant ne peut pas enregistrer de notes si l evaluation est verrouillee administrativement', function () {
    /** @var \Tests\TestCase $this */
    // Évaluation verrouillée par la direction
    $this->eval1->update(['is_locked' => true, 'statut' => 'verrouillee']);

    $payload = [
        'attribution_id' => $this->attribution->id,
        'evaluation_id' => $this->eval1->id,
        'notes' => [
            [
                'inscription_id' => $this->inscription->id,
                'valeur_note' => 18.0,
                'statut_presence' => 'present',
            ],
        ],
    ];

    // La tentative de sauvegarde par l'enseignant doit être bloquée avec HTTP 403
    $response = $this->actingAs($this->teacherUser)
        ->postJson(route('administration.notes.sauvegarder'), $payload);

    $response->assertForbidden();

    // Aucune note n'a été insérée
    $this->assertDatabaseMissing('notes', [
        'attribution_id' => $this->attribution->id,
        'evaluation_id' => $this->eval1->id,
        'valeur_note' => 18.0,
    ]);
});

test('un enseignant ne peut pas enregistrer de notes si la date limite de saisie est depassee', function () {
    /** @var \Tests\TestCase $this */
    // Évaluation non verrouillée explicitement mais dont la date limite est expirée
    $this->eval1->update([
        'is_locked' => false,
        'date_limite_saisie' => Carbon::now()->subDays(2)->toDateString(),
    ]);

    expect($this->eval1->isLockedForGrading())->toBeTrue();

    $payload = [
        'attribution_id' => $this->attribution->id,
        'evaluation_id' => $this->eval1->id,
        'notes' => [
            [
                'inscription_id' => $this->inscription->id,
                'valeur_note' => 14.0,
                'statut_presence' => 'present',
            ],
        ],
    ];

    // Bloqué avec HTTP 403
    $response = $this->actingAs($this->teacherUser)
        ->postJson(route('administration.notes.sauvegarder'), $payload);

    $response->assertForbidden();
});

test('un enseignant ne peut pas ecraser des notes si la grille a ete individuellement verrouillee', function () {
    /** @var \Tests\TestCase $this */
    $this->eval1->update(['is_locked' => false, 'date_limite_saisie' => Carbon::now()->addDays(5)->toDateString()]);

    // Note existante déjà verrouillée
    Note::create([
        'attribution_id' => $this->attribution->id,
        'evaluation_id' => $this->eval1->id,
        'inscription_id' => $this->inscription->id,
        'valeur_note' => 12.0,
        'statut_presence' => 'present',
        'is_verrouillee' => true,
        'saisie_par_user_id' => $this->teacherUser->id,
    ]);

    $payload = [
        'attribution_id' => $this->attribution->id,
        'evaluation_id' => $this->eval1->id,
        'notes' => [
            [
                'inscription_id' => $this->inscription->id,
                'valeur_note' => 19.0, // Tentative de modification pirate
                'statut_presence' => 'present',
            ],
        ],
    ];

    $response = $this->actingAs($this->teacherUser)
        ->postJson(route('administration.notes.sauvegarder'), $payload);

    $response->assertForbidden();

    // La note originale reste intacte
    expect(Note::where('inscription_id', $this->inscription->id)->first()->valeur_note)->toEqual(12.0);
});

test('un gestionnaire peut enregistrer des notes meme si l evaluation est verrouillee', function () {
    /** @var \Tests\TestCase $this */
    $this->eval1->update(['is_locked' => true, 'statut' => 'verrouillee']);

    $payload = [
        'attribution_id' => $this->attribution->id,
        'evaluation_id' => $this->eval1->id,
        'notes' => [
            [
                'inscription_id' => $this->inscription->id,
                'valeur_note' => 16.0,
                'statut_presence' => 'present',
                'appreciation' => 'Correction autorisée par la direction',
            ],
        ],
    ];

    // Le censeur ou super-admin doit pouvoir corriger
    $response = $this->actingAs($this->censeurUser)
        ->postJson(route('administration.notes.sauvegarder'), $payload);

    $response->assertOk()
        ->assertJson(['success' => true]);

    $this->assertDatabaseHas('notes', [
        'attribution_id' => $this->attribution->id,
        'evaluation_id' => $this->eval1->id,
        'valeur_note' => 16.0,
    ]);
});
