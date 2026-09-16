<?php

/** @var TestCase $this */

use App\Models\AnneeScolaire;
use App\Models\Attribution;
use App\Models\Classe;
use App\Models\Cycle;
use App\Models\DisciplineBulletin;
use App\Models\Eleve;
use App\Models\Etablissement;
use App\Models\Evaluation;
use App\Models\GroupeMatiere;
use App\Models\Inscription;
use App\Models\Institution;
use App\Models\Matiere;
use App\Models\MatiereNiveau;
use App\Models\Niveau;
use App\Models\Note;
use App\Models\Periode;
use App\Models\Personnel;
use App\Models\Section;
use App\Models\User;
use App\Services\BulletinCalculationService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Tests de fonctionnalités pour le Module 10 : Saisie des Notes & Calcul des Bulletins (Gesco V1.1 Section 10).
 *
 * Vérifie :
 * - Le contrôle d'accès : les invités sont redirigés, les enseignants n'accèdent qu'à leurs cours.
 * - La saisie et l'enregistrement des notes avec calcul automatique de la cote APC (CTBA, CBA, CMA, CNA).
 * - Le verrouillage de session de notes et le déverrouillage réservé à la direction.
 * - Le moteur de calcul des moyennes (BulletinCalculationService) :
 *   * Moyennes séquentielles et trimestrielles.
 *   * Rangs par matière et rang général avec gestion des ex-aequo.
 *   * Statistiques de classe (Min, Max, Moyenne classe, Taux de réussite).
 * - L'invalidation automatique des distinctions honorifiques par note éliminatoire (Gesco 4.3).
 * - La configurabilité du bulletin : Avec compétences (APC) vs Sans compétences (Standard).
 * - La génération des exports PDF (individuel et classe complète).
 */
beforeEach(function () {
    Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'enseignant', 'guard_name' => 'web']);

    $permissions = [
        'notes.view',
        'notes.enter',
        'notes.lock',
        'bulletins.view',
        'bulletins.generate',
    ];

    foreach ($permissions as $perm) {
        Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
    }

    $this->adminUser = User::factory()->create();
    $this->adminUser->assignRole('super-admin');

    // 1. Structure académique
    $this->institution = Institution::firstOrCreate(
        ['code' => 'INST-MOD10'],
        [
            'sigle' => 'INST10',
            'nom' => 'Institution Module 10',
            'email' => 'mod10@syges.test',
            'telephone_1' => '+237690000010',
        ]
    );

    $this->etablissement = Etablissement::firstOrCreate(
        ['code' => 'ETAB-MOD10'],
        [
            'institution_id' => $this->institution->id,
            'nom' => 'Lycée Pilote de Test',
            'nom_en' => 'Test Pilot High School',
            'statut_juridique' => 'Public',
            'ordre_enseignement' => 'Secondaire_General',
            'mode_bulletin_defaut' => 'avec_competences',
            'type_calcul_trimestre' => 'arithmetique',
        ]
    );

    $this->section = Section::firstOrCreate(
        ['code' => 'SEC-FR-10'],
        [
            'etablissement_id' => $this->etablissement->id,
            'nom' => 'Section Francophone',
            'type_enseignement' => 'General',
        ]
    );

    $this->cycle = Cycle::firstOrCreate(
        ['code' => 'CYC-1-10'],
        [
            'section_id' => $this->section->id,
            'nom' => 'Premier Cycle',
            'ordre' => 1,
        ]
    );

    $this->niveau = Niveau::firstOrCreate(
        ['code' => 'NIV-5EME'],
        [
            'cycle_id' => $this->cycle->id,
            'nom' => '5ème',
            'ordre' => 2,
        ]
    );

    $this->classe = Classe::firstOrCreate(
        ['code' => '5EME-A'],
        [
            'niveau_id' => $this->niveau->id,
            'nom' => '5ème Ordinaire A',
            'capacite_max' => 60,
        ]
    );

    // 2. Année scolaire, Trimestre et Séquences
    $this->annee = AnneeScolaire::firstOrCreate(
        ['nom' => '2026-2027'],
        [
            'code' => '2026-2027',
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-06-30',
            'is_current' => true,
        ]
    );

    $this->periode = Periode::firstOrCreate(
        ['annee_scolaire_id' => $this->annee->id, 'code' => 'TRIM-1'],
        [
            'nom' => 'Premier Trimestre',
            'ordre' => 1,
            'date_debut' => '2026-09-01',
            'date_fin' => '2026-11-30',
            'is_cloturee' => false,
        ]
    );

    $this->seq1 = Evaluation::firstOrCreate(
        ['periode_id' => $this->periode->id, 'code' => 'SEQ-1'],
        [
            'nom' => 'Séquence 1',
            'ordre' => 1,
            'ponderation' => 50.00,
            'statut' => 'ouverte',
            'is_locked' => false,
        ]
    );

    $this->seq2 = Evaluation::firstOrCreate(
        ['periode_id' => $this->periode->id, 'code' => 'SEQ-2'],
        [
            'nom' => 'Séquence 2',
            'ordre' => 2,
            'ponderation' => 50.00,
            'statut' => 'ouverte',
            'is_locked' => false,
        ]
    );

    // 3. Blocs de Matières & Disciplines
    $this->groupeSci = GroupeMatiere::firstOrCreate(
        ['code' => 'GRP-SCI'],
        ['nom' => 'Enseignements Scientifiques', 'ordre' => 1]
    );

    $this->groupeLit = GroupeMatiere::firstOrCreate(
        ['code' => 'GRP-LIT'],
        ['nom' => 'Enseignements Littéraires', 'ordre' => 2]
    );

    $this->matiereMath = Matiere::firstOrCreate(
        ['code' => 'MATH-5'],
        ['nom' => 'Mathématiques', 'libelle_bulletin' => 'Mathématiques']
    );

    $this->matiereFrancais = Matiere::firstOrCreate(
        ['code' => 'FRAN-5'],
        ['nom' => 'Français', 'libelle_bulletin' => 'Langue Française']
    );

    // Grille de coefficients (avec note éliminatoire à 6.00 pour les maths)
    MatiereNiveau::firstOrCreate(
        ['niveau_id' => $this->niveau->id, 'matiere_id' => $this->matiereMath->id, 'serie' => 'Toutes'],
        [
            'groupe_matiere_id' => $this->groupeSci->id,
            'coefficient' => 4.0,
            'note_max' => 20.0,
            'note_eliminatoire' => 6.0, // Sanction Gesco 4.3
            'is_comptabilisee' => true,
        ]
    );

    MatiereNiveau::firstOrCreate(
        ['niveau_id' => $this->niveau->id, 'matiere_id' => $this->matiereFrancais->id, 'serie' => 'Toutes'],
        [
            'groupe_matiere_id' => $this->groupeLit->id,
            'coefficient' => 4.0,
            'note_max' => 20.0,
            'note_eliminatoire' => null,
            'is_comptabilisee' => true,
        ]
    );

    // 4. Élèves et Inscriptions
    $this->eleve1 = Eleve::firstOrCreate(
        ['matricule' => 'MAT-001'],
        [
            'nom' => 'KAMGA',
            'prenom' => 'Arthur',
            'sexe' => 'M',
            'date_naissance' => '2012-05-14',
            'lieu_naissance' => 'Yaoundé',
            'nom_tuteur' => 'M. Kamga',
            'telephone_parent' => '+237690111111',
        ]
    );

    $this->eleve2 = Eleve::firstOrCreate(
        ['matricule' => 'MAT-002'],
        [
            'nom' => 'NDONGO',
            'prenom' => 'Clarisse',
            'sexe' => 'F',
            'date_naissance' => '2012-08-20',
            'lieu_naissance' => 'Douala',
            'nom_tuteur' => 'Mme Ndongo',
            'telephone_parent' => '+237690222222',
        ]
    );

    $this->inscription1 = Inscription::firstOrCreate(
        ['eleve_id' => $this->eleve1->id, 'annee_scolaire_id' => $this->annee->id],
        ['classe_id' => $this->classe->id, 'statut' => 'validee']
    );

    $this->inscription2 = Inscription::firstOrCreate(
        ['eleve_id' => $this->eleve2->id, 'annee_scolaire_id' => $this->annee->id],
        ['classe_id' => $this->classe->id, 'statut' => 'validee']
    );

    // 5. Enseignant et Attributions
    $this->teacherUser = User::factory()->create(['email' => 'teacher.math@syges.test']);
    $this->teacherUser->assignRole('enseignant');

    $this->personnel = Personnel::firstOrCreate(
        ['matricule' => 'ENS-001'],
        [
            'user_id' => $this->teacherUser->id,
            'etablissement_id' => $this->etablissement->id,
            'nom' => 'TCHOUA',
            'prenom' => 'Jean-Paul',
            'sexe' => 'M',
            'telephone' => '0707070701',
            'fonction_principale' => 'Enseignant',
            'type_contrat' => 'CDI (Contrat à Durée Indéterminée)',
            'date_embauche' => '2026-09-01',
            'statut' => 'Actif',
            'email' => 'teacher.math@syges.test',
            'is_enseignant' => true,
            'specialite' => 'Mathématiques',
            'volume_horaire_hebdo' => 18.00,
        ]
    );

    $this->attributionMath = Attribution::firstOrCreate(
        [
            'annee_scolaire_id' => $this->annee->id,
            'classe_id' => $this->classe->id,
            'matiere_id' => $this->matiereMath->id,
        ],
        [
            'personnel_id' => $this->personnel->id,
            'volume_horaire_reel' => 4.0,
            'is_titulaire' => true,
            'is_active' => true,
        ]
    );

    $this->attributionFran = Attribution::firstOrCreate(
        [
            'annee_scolaire_id' => $this->annee->id,
            'classe_id' => $this->classe->id,
            'matiere_id' => $this->matiereFrancais->id,
        ],
        [
            'personnel_id' => $this->personnel->id,
            'volume_horaire_reel' => 4.0,
            'is_titulaire' => true,
            'is_active' => true,
        ]
    );
});

test('les invites sont rediriges vers la page de connexion', function () {
    $this->get('/administration/notes')->assertRedirect('/login');
    $this->get('/administration/bulletins')->assertRedirect('/login');
});

test('l administrateur peut acceder a la page de saisie des notes et aux bulletins', function () {
    $this->actingAs($this->adminUser)
        ->get('/administration/notes')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('administration/notes/index'));

    $this->actingAs($this->adminUser)
        ->get('/administration/bulletins')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('administration/bulletins/index'));
});

test('la grille de saisie charge les eleves de la classe avec getGrille', function () {
    $this->actingAs($this->adminUser)
        ->getJson("/administration/notes/grille?attribution_id={$this->attributionMath->id}&evaluation_id={$this->seq1->id}")
        ->assertOk()
        ->assertJsonStructure([
            'attribution',
            'evaluation',
            'is_locked',
            'grille' => [
                '*' => ['inscription_id', 'matricule', 'nom_complet', 'valeur_note', 'statut_presence'],
            ],
        ])
        ->assertJsonCount(2, 'grille');
});

test('un enseignant ne peut pas acceder a une attribution qui ne lui est pas affectee', function () {
    $otherTeacher = User::factory()->create();
    $otherTeacher->assignRole('enseignant');

    $this->actingAs($otherTeacher)
        ->getJson("/administration/notes/grille?attribution_id={$this->attributionMath->id}&evaluation_id={$this->seq1->id}")
        ->assertStatus(403);
});

test('enregistrement des notes avec calcul automatique des cotes APC', function () {
    $payload = [
        'attribution_id' => $this->attributionMath->id,
        'evaluation_id' => $this->seq1->id,
        'notes' => [
            [
                'inscription_id' => $this->inscription1->id,
                'valeur_note' => 17.5, // Doit devenir CTBA
                'statut_presence' => 'present',
                'appreciation' => 'Excellent travail',
                'competence_evaluee' => 'Résoudre des équations du 1er degré',
            ],
            [
                'inscription_id' => $this->inscription2->id,
                'valeur_note' => 14.5, // Doit devenir CBA
                'statut_presence' => 'present',
                'appreciation' => 'Bon travail',
                'competence_evaluee' => 'Résoudre des équations du 1er degré',
            ],
        ],
    ];

    $this->actingAs($this->adminUser)
        ->postJson('/administration/notes/sauvegarder', $payload)
        ->assertOk()
        ->assertJson(['success' => true]);

    $note1 = Note::where('inscription_id', $this->inscription1->id)
        ->where('attribution_id', $this->attributionMath->id)
        ->where('evaluation_id', $this->seq1->id)
        ->first();

    expect($note1)->not->toBeNull()
        ->and((float) $note1->valeur_note)->toBe(17.5)
        ->and($note1->cote)->toBe('CTBA')
        ->and($note1->statut_presence)->toBe('present');

    $note2 = Note::where('inscription_id', $this->inscription2->id)
        ->where('attribution_id', $this->attributionMath->id)
        ->where('evaluation_id', $this->seq1->id)
        ->first();

    expect($note2)->not->toBeNull()
        ->and((float) $note2->valeur_note)->toBe(14.5)
        ->and($note2->cote)->toBe('CBA');
});

test('verrouillage et deverrouillage de la session de notes', function () {
    // 1. Saisie d'une note
    Note::create([
        'inscription_id' => $this->inscription1->id,
        'attribution_id' => $this->attributionMath->id,
        'evaluation_id' => $this->seq1->id,
        'valeur_note' => 15.0,
        'statut_presence' => 'present',
    ]);

    // 2. Verrouillage
    $this->actingAs($this->adminUser)
        ->postJson('/administration/notes/verrouiller', [
            'attribution_id' => $this->attributionMath->id,
            'evaluation_id' => $this->seq1->id,
        ])
        ->assertOk();

    $note = Note::where('inscription_id', $this->inscription1->id)->first();
    expect($note->is_verrouillee)->toBeTrue();

    // 3. Déverrouillage par l'administrateur
    $this->actingAs($this->adminUser)
        ->postJson('/administration/notes/deverrouiller', [
            'attribution_id' => $this->attributionMath->id,
            'evaluation_id' => $this->seq1->id,
        ])
        ->assertOk();

    $note->refresh();
    expect($note->is_verrouillee)->toBeFalse();
});

test('le moteur de calcul calculTrimestre produit les moyennes, rangs et statistiques officielles', function () {
    // Évaluation 1 : KAMGA (Math: 18, Fran: 14), NDONGO (Math: 12, Fran: 16)
    Note::create([
        'inscription_id' => $this->inscription1->id,
        'attribution_id' => $this->attributionMath->id,
        'evaluation_id' => $this->seq1->id,
        'valeur_note' => 18.0,
        'statut_presence' => 'present',
    ]);
    Note::create([
        'inscription_id' => $this->inscription1->id,
        'attribution_id' => $this->attributionFran->id,
        'evaluation_id' => $this->seq1->id,
        'valeur_note' => 14.0,
        'statut_presence' => 'present',
    ]);

    Note::create([
        'inscription_id' => $this->inscription2->id,
        'attribution_id' => $this->attributionMath->id,
        'evaluation_id' => $this->seq1->id,
        'valeur_note' => 12.0,
        'statut_presence' => 'present',
    ]);
    Note::create([
        'inscription_id' => $this->inscription2->id,
        'attribution_id' => $this->attributionFran->id,
        'evaluation_id' => $this->seq1->id,
        'valeur_note' => 16.0,
        'statut_presence' => 'present',
    ]);

    // Évaluation 2 : KAMGA (Math: 16, Fran: 16), NDONGO (Math: 14, Fran: 14)
    Note::create([
        'inscription_id' => $this->inscription1->id,
        'attribution_id' => $this->attributionMath->id,
        'evaluation_id' => $this->seq2->id,
        'valeur_note' => 16.0,
        'statut_presence' => 'present',
    ]);
    Note::create([
        'inscription_id' => $this->inscription1->id,
        'attribution_id' => $this->attributionFran->id,
        'evaluation_id' => $this->seq2->id,
        'valeur_note' => 16.0,
        'statut_presence' => 'present',
    ]);

    Note::create([
        'inscription_id' => $this->inscription2->id,
        'attribution_id' => $this->attributionMath->id,
        'evaluation_id' => $this->seq2->id,
        'valeur_note' => 14.0,
        'statut_presence' => 'present',
    ]);
    Note::create([
        'inscription_id' => $this->inscription2->id,
        'attribution_id' => $this->attributionFran->id,
        'evaluation_id' => $this->seq2->id,
        'valeur_note' => 14.0,
        'statut_presence' => 'present',
    ]);

    $service = new BulletinCalculationService;
    $resultat = $service->calculerTrimestre($this->classe, $this->periode, 'arithmetique', 'avec_competences');

    // KAMGA : Math moy = (18+16)/2 = 17, Fran moy = (14+16)/2 = 15.
    // Points = (17*4) + (15*4) = 68 + 60 = 128. Coefs = 8.
    // Moyenne trimestrielle KAMGA = 128 / 8 = 16.00 / 20.
    // Rang KAMGA = 1er.

    // NDONGO : Math moy = (12+14)/2 = 13, Fran moy = (16+14)/2 = 15.
    // Points = (13*4) + (15*4) = 52 + 60 = 112. Coefs = 8.
    // Moyenne trimestrielle NDONGO = 112 / 8 = 14.00 / 20.
    // Rang NDONGO = 2e.

    $eleves = collect($resultat['eleves'])->keyBy('inscription_id');
    $kamga = $eleves->get($this->inscription1->id);
    $ndongo = $eleves->get($this->inscription2->id);

    expect($kamga['moyenne_generale'])->toBe(16.0)
        ->and($kamga['rang_general'])->toBe('1er')
        ->and($kamga['distinction'])->toContain('Tableau d\'Honneur avec Félicitations')
        ->and($ndongo['moyenne_generale'])->toBe(14.0)
        ->and($ndongo['rang_general'])->toBe('2e')
        ->and($ndongo['distinction'])->toContain('Tableau d\'Honneur avec Encouragements');

    // Statistiques de la classe
    expect($resultat['stats_classe']['effectif_total'])->toBe(2)
        ->and($resultat['stats_classe']['moyenne_generale'])->toBe(15.0)
        ->and($resultat['stats_classe']['taux_reussite'])->toBe(100.0)
        ->and($resultat['stats_classe']['min_generale'])->toBe(14.0)
        ->and($resultat['stats_classe']['max_generale'])->toBe(16.0);
});

test('une note eliminatoire disqualifie automatiquement l eleve des distinctions honorifiques (Gesco 4.3)', function () {
    // Math a un seuil éliminatoire de 6.00.
    // Donnons à l'élève 1 une note de 5.0 en Math (éliminatoire), mais 20.0 en Français.
    // Moyenne = (5*4 + 20*4) / 8 = (20 + 80) / 8 = 100 / 8 = 12.50 (normalement éligible au Tableau d'Honneur).

    Note::create([
        'inscription_id' => $this->inscription1->id,
        'attribution_id' => $this->attributionMath->id,
        'evaluation_id' => $this->seq1->id,
        'valeur_note' => 5.0, // < 6.00 => Note éliminatoire
        'statut_presence' => 'present',
    ]);
    Note::create([
        'inscription_id' => $this->inscription1->id,
        'attribution_id' => $this->attributionFran->id,
        'evaluation_id' => $this->seq1->id,
        'valeur_note' => 20.0,
        'statut_presence' => 'present',
    ]);

    $service = new BulletinCalculationService;
    $resultat = $service->calculerTrimestre($this->classe, $this->periode, 'arithmetique', 'avec_competences');

    $kamga = collect($resultat['eleves'])->firstWhere('inscription_id', $this->inscription1->id);

    expect($kamga['moyenne_generale'])->toBe(12.5)
        ->and($kamga['a_note_eliminatoire'])->toBeTrue()
        ->and($kamga['distinction'])->toBe('Aucune (Note éliminatoire)');
});

test('generation et telechargement du bulletin officiel en PDF individuel et cahier de classe', function () {
    // Renseigner des notes
    Note::create([
        'inscription_id' => $this->inscription1->id,
        'attribution_id' => $this->attributionMath->id,
        'evaluation_id' => $this->seq1->id,
        'valeur_note' => 15.0,
        'statut_presence' => 'present',
    ]);

    // 1. Prévisualisation HTML du bulletin en mode APC (présence de la colonne Compétences APC)
    $this->actingAs($this->adminUser)
        ->get("/administration/bulletins/previsualiser/{$this->inscription1->id}/{$this->periode->id}?mode=avec_competences")
        ->assertOk()
        ->assertSee('BULLETIN SCOLAIRE')
        ->assertSee('Compétences Évaluées (APC)')
        ->assertSee('KAMGA Arthur');

    // 2. Prévisualisation HTML du bulletin en mode Standard (absence de la colonne Compétences APC)
    $this->actingAs($this->adminUser)
        ->get("/administration/bulletins/previsualiser/{$this->inscription1->id}/{$this->periode->id}?mode=sans_competences")
        ->assertOk()
        ->assertSee('BULLETIN SCOLAIRE')
        ->assertDontSee('Compétences Évaluées (APC)');

    // 3. Téléchargement PDF individuel
    $pdfResponse = $this->actingAs($this->adminUser)
        ->get("/administration/bulletins/pdf/{$this->inscription1->id}/{$this->periode->id}?mode=avec_competences");

    $pdfResponse->assertOk();
    expect($pdfResponse->headers->get('content-type'))->toBe('application/pdf');

    // 4. Téléchargement PDF groupé de la classe
    $bundleResponse = $this->actingAs($this->adminUser)
        ->get("/administration/bulletins/classe-pdf/{$this->classe->id}/{$this->periode->id}?mode=avec_competences");

    $bundleResponse->assertOk();
    expect($bundleResponse->headers->get('content-type'))->toBe('application/pdf');
});

test('enregistrement des donnees disciplinaires pour le bulletin', function () {
    $this->actingAs($this->adminUser)
        ->postJson('/administration/bulletins/discipline', [
            'inscription_id' => $this->inscription1->id,
            'periode_id' => $this->periode->id,
            'absences_justifiees' => 2,
            'absences_non_justifiees' => 4,
            'retards' => 1,
            'consignes' => 0,
            'avertissements_conduite' => 1,
            'blames_conduite' => 0,
            'exclusions_jours' => 0,
            'decision_conseil' => 'Élève assidu mais doit surveiller sa ponctualité.',
        ])
        ->assertOk()
        ->assertJson(['success' => true]);

    $rec = DisciplineBulletin::where('inscription_id', $this->inscription1->id)
        ->where('periode_id', $this->periode->id)
        ->first();

    expect($rec)->not->toBeNull()
        ->and($rec->absences_non_justifiees)->toBe(4)
        ->and($rec->avertissements_conduite)->toBe(1)
        ->and($rec->decision_conseil)->toContain('Élève assidu');
});

test('calcul et generation du bulletin sequentiel avec troncature de competence longue', function () {
    // Note avec une compétence longue dépassant 75 caractères
    $competenceLongue = 'Capacité à résoudre des problèmes complexes d\'algèbre linéaire et de géométrie vectorielle dans l\'espace tridimensionnel';

    Note::create([
        'inscription_id' => $this->inscription1->id,
        'attribution_id' => $this->attributionMath->id,
        'evaluation_id' => $this->seq1->id,
        'valeur_note' => 17.5,
        'competence' => $competenceLongue,
        'statut_presence' => 'present',
    ]);

    $service = new BulletinCalculationService;
    $resultat = $service->calculerSequentiel($this->classe, $this->seq1, 'avec_competences');

    expect($resultat['type_bulletin'])->toBe('sequentiel')
        ->and($resultat['evaluation']['code'])->toBe('SEQ-1')
        ->and(count($resultat['eleves']))->toBe(2);

    $kamga = collect($resultat['eleves'])->firstWhere('inscription_id', $this->inscription1->id);
    expect($kamga['moyenne_generale'])->toBe(17.5);

    // Vérifier la troncature de la compétence à 75 caractères max
    $matiereMath = $kamga['lignes_matieres'][$this->attributionMath->id];
    expect(mb_strlen($matiereMath['competences']))->toBeLessThanOrEqual(78);
    expect(str_contains($matiereMath['competences'], '...'))->toBeTrue();

    // Prévisualisation HTML du bulletin séquentiel
    $this->actingAs($this->adminUser)
        ->get("/administration/bulletins/previsualiser/{$this->inscription1->id}/{$this->periode->id}?type_bulletin=sequentiel&evaluation_id={$this->seq1->id}&mode=avec_competences")
        ->assertOk()
        ->assertSee('ÉVALUATION SÉQUENTIELLE')
        ->assertSee('Séquence 1')
        ->assertSee('KAMGA Arthur');

    // Téléchargement PDF séquentiel
    $pdfResp = $this->actingAs($this->adminUser)
        ->get("/administration/bulletins/pdf/{$this->inscription1->id}/{$this->periode->id}?type_bulletin=sequentiel&evaluation_id={$this->seq1->id}&mode=avec_competences");

    $pdfResp->assertOk();
    expect($pdfResp->headers->get('content-type'))->toBe('application/pdf');
});

test('calcul et generation du bulletin annuel avec synthese et decision du conseil', function () {
    // Note pour Kamga (Moyenne >= 10 => Admis(e))
    Note::create([
        'inscription_id' => $this->inscription1->id,
        'attribution_id' => $this->attributionMath->id,
        'evaluation_id' => $this->seq1->id,
        'valeur_note' => 15.0,
        'statut_presence' => 'present',
    ]);
    Note::create([
        'inscription_id' => $this->inscription1->id,
        'attribution_id' => $this->attributionFran->id,
        'evaluation_id' => $this->seq1->id,
        'valeur_note' => 13.0,
        'statut_presence' => 'present',
    ]);

    // Note pour Ndongo (Moyenne entre 8.5 et 10.0 => Proposé(e) au redoublement)
    Note::create([
        'inscription_id' => $this->inscription2->id,
        'attribution_id' => $this->attributionMath->id,
        'evaluation_id' => $this->seq1->id,
        'valeur_note' => 9.0,
        'statut_presence' => 'present',
    ]);
    Note::create([
        'inscription_id' => $this->inscription2->id,
        'attribution_id' => $this->attributionFran->id,
        'evaluation_id' => $this->seq1->id,
        'valeur_note' => 8.0,
        'statut_presence' => 'present',
    ]);

    $service = new BulletinCalculationService;
    $resultat = $service->calculerAnnuel($this->classe, $this->annee, 'avec_competences');

    expect($resultat['type_bulletin'])->toBe('annuel')
        ->and(count($resultat['eleves']))->toBe(2);

    $kamga = collect($resultat['eleves'])->firstWhere('inscription_id', $this->inscription1->id);
    $ndongo = collect($resultat['eleves'])->firstWhere('inscription_id', $this->inscription2->id);

    expect($kamga['moyenne_generale'])->toBe(14.0)
        ->and($kamga['decision_finale'])->toContain('Admis(e)')
        ->and($ndongo['moyenne_generale'])->toBe(8.5)
        ->and($ndongo['decision_finale'])->toContain('redoublement');

    // Prévisualisation HTML du bulletin annuel
    $this->actingAs($this->adminUser)
        ->get("/administration/bulletins/previsualiser/{$this->inscription1->id}?type_bulletin=annuel&mode=avec_competences")
        ->assertOk()
        ->assertSee('BULLETIN SCOLAIRE ANNUEL')
        ->assertSee('DÉCISION DU CONSEIL')
        ->assertSee('Admis(e)');

    // Téléchargement PDF annuel
    $pdfResp = $this->actingAs($this->adminUser)
        ->get("/administration/bulletins/pdf/{$this->inscription1->id}?type_bulletin=annuel&mode=avec_competences");

    $pdfResp->assertOk();
    expect($pdfResp->headers->get('content-type'))->toBe('application/pdf');
});

test('les pages d administration notes et bulletins se chargent correctement sans double layout', function () {
    // Vérifie le rendu du composant Inertia pour /administration/notes
    $this->actingAs($this->adminUser)
        ->get('/administration/notes')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('administration/notes/index'));

    // Vérifie le rendu du composant Inertia pour /administration/bulletins
    $this->actingAs($this->adminUser)
        ->get('/administration/bulletins')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('administration/bulletins/index'));
});

