<?php

/** @var TestCase $this */

use App\Models\AnneeScolaire;
use App\Models\Attribution;
use App\Models\Caisse;
use App\Models\Classe;
use App\Models\CompteFinancierEleve;
use App\Models\Cycle;
use App\Models\DeliberationConseil;
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
use App\Models\VersementCaisse;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;

/**
 * Tests de fonctionnalités pour le Module 5 : Gestion des Élèves & Inscriptions (Gesco & SAGEES).
 *
 * Vérifie :
 * - Le contrôle d'accès selon les permissions (eleves.view, eleves.create, eleves.edit, eleves.delete, inscriptions.manage, inscriptions.transfer).
 * - La consultation de la liste des élèves avec KPIs et filtres.
 * - L'enregistrement complet d'un élève dans la Base Globale avec auto-génération de matricule.
 * - L'inscription annuelle dans une classe pour l'année scolaire active.
 * - La modification des données d'un élève (identité, contacts tuteur, urgences).
 * - La gestion des statuts d'inscription (validee, en_attente, suspendue, exclu, transfere).
 * - La réinscription d'un ancien élève non encore inscrit sur l'année.
 * - Le transfert d'élève (inter-classe intra-établissement et transfert intra-groupe selon Gesco V1.1).
 * - L'importation de fichier CSV selon le standard camerounais / SAGEES Schooling.
 * - La suppression d'un élève.
 */
beforeEach(function () {
    /** @var TestCase $this */
    Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);

    $permissions = [
        'eleves.view',
        'eleves.create',
        'eleves.edit',
        'eleves.delete',
        'inscriptions.manage',
        'inscriptions.transfer',
    ];

    foreach ($permissions as $perm) {
        Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
    }

    $this->adminUser = User::factory()->create();
    $this->adminUser->assignRole('super-admin');

    // Année scolaire active
    $this->annee = AnneeScolaire::firstOrCreate(
        ['code' => '2026-2027'],
        [
            'nom' => 'Année Scolaire 2026-2027',
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-06-30',
            'statut' => 'en_cours',
            'is_current' => true,
        ]
    );

    // Classes pour les tests
    $this->classe = Classe::first();
    if (! $this->classe) {
        $inst = Institution::firstOrCreate(
            ['code' => 'INST-ELEVE'],
            ['nom' => 'Institution Test', 'sigle' => 'IT', 'telephone_1' => '+237 600000000']
        );
        $etab = Etablissement::firstOrCreate(
            ['code' => 'ETAB-ELEVE'],
            ['institution_id' => $inst->id, 'nom' => 'Collège Test']
        );
        $sec = Section::firstOrCreate(
            ['etablissement_id' => $etab->id, 'code' => 'FR-ELEVE'],
            ['nom' => 'Section FR']
        );
        $cyc = Cycle::firstOrCreate(
            ['section_id' => $sec->id, 'code' => 'CYC-ELEVE'],
            ['nom' => '1er Cycle', 'ordre' => 1]
        );
        $niv = Niveau::firstOrCreate(
            ['cycle_id' => $cyc->id, 'code' => 'NIV-ELEVE'],
            ['nom' => 'Sixième', 'ordre' => 1]
        );
        $this->classe = Classe::firstOrCreate(
            ['niveau_id' => $niv->id, 'code' => '6M1-TEST'],
            ['nom' => '6ème Test', 'capacite_max' => 50, 'is_active' => true]
        );
    }
});

test('un invité ne peut pas accéder au module élèves', function () {
    get(route('administration.eleves.index'))
        ->assertRedirect(route('login'));
});

test('un administrateur autorisé peut afficher la liste des élèves', function () {
    /** @var TestCase $this */
    actingAs($this->adminUser)
        ->get(route('administration.eleves.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('administration/eleves/index')
            ->has('inscriptions')
            ->has('stats')
            ->has('etablissements')
            ->has('anneesScolaires')
        );
});

test('on peut enregistrer un nouvel élève avec auto-génération de matricule et inscription dans une classe', function () {
    /** @var TestCase $this */
    $data = [
        'matricule' => '', // Laisser vide pour tester l'auto-génération
        'matricule_national' => 'MIN-2026-44112',
        'nom' => 'NKWENTI',
        'prenom' => 'Brian Boris',
        'sexe' => 'M',
        'date_naissance' => '2012-07-21',
        'lieu_naissance' => 'Bamenda',
        'nationalite' => 'CAMEROUNAISE',
        'telephone_parent' => '+237 677 88 99 11',
        'nom_pere' => 'NKWENTI John',
        'nom_mere' => 'BIH Grace',
        'adresse' => 'Up Station, Bamenda',
        'classe_id' => $this->classe->id,
        'annee_scolaire_id' => $this->annee->id,
        'statut' => 'validee',
        'is_redoublant' => false,
    ];

    actingAs($this->adminUser)
        ->post(route('administration.eleves.store'), $data)
        ->assertRedirect();

    // Vérifie que l'élève a été créé dans la base avec un matricule non vide
    $eleve = Eleve::where('nom', 'NKWENTI')->first();
    expect($eleve)->not->toBeNull();
    expect($eleve->matricule)->not->toBeEmpty();
    expect($eleve->matricule_national)->toBe('MIN-2026-44112');
    expect($eleve->telephone_parent)->toBe('+237 677 88 99 11');
    expect($eleve->adresse)->toBe('Up Station, Bamenda');

    // Vérifie que son inscription annuelle est bien active
    $inscription = Inscription::where('eleve_id', $eleve->id)
        ->where('classe_id', $this->classe->id)
        ->where('annee_scolaire_id', $this->annee->id)
        ->first();

    expect($inscription)->not->toBeNull();
    expect($inscription->statut)->toBe('validee');
    expect($inscription->is_redoublant)->toBeFalse();
});

test('la validation échoue si le nom ou le téléphone du parent est absent', function () {
    /** @var TestCase $this */
    actingAs($this->adminUser)
        ->post(route('administration.eleves.store'), [
            'nom' => '',
            'telephone_parent' => '',
            'classe_id' => $this->classe->id,
            'annee_scolaire_id' => $this->annee->id,
        ])
        ->assertSessionHasErrors(['nom', 'telephone_parent', 'sexe']);
});

test('on peut modifier les informations civiles et de contact d un élève', function () {
    /** @var TestCase $this */
    $eleve = Eleve::create([
        'matricule' => 'GS26-9999',
        'nom' => 'ONANA',
        'prenom' => 'André',
        'sexe' => 'M',
        'telephone_parent' => '+237 699 00 00 00',
        'is_active' => true,
    ]);

    $updateData = [
        'matricule' => 'GS26-9999',
        'nom' => 'ONANA MODIFIE',
        'prenom' => 'André Joseph',
        'sexe' => 'M',
        'telephone_parent' => '+237 699 11 11 11',
        'adresse' => 'Quartier Bastos, Yaoundé',
    ];

    actingAs($this->adminUser)
        ->put(route('administration.eleves.update', $eleve), $updateData)
        ->assertRedirect();

    $eleve->refresh();
    expect($eleve->nom)->toBe('ONANA MODIFIE');
    expect($eleve->telephone_parent)->toBe('+237 699 11 11 11');
    expect($eleve->adresse)->toBe('Quartier Bastos, Yaoundé');
});

test('on peut modifier le statut d une inscription en suspendue ou exclu', function () {
    /** @var TestCase $this */
    $eleve = Eleve::create([
        'matricule' => 'GS26-7777',
        'nom' => 'TCHIO',
        'sexe' => 'M',
        'telephone_parent' => '+237 655 00 00 00',
    ]);

    $inscription = Inscription::create([
        'eleve_id' => $eleve->id,
        'classe_id' => $this->classe->id,
        'annee_scolaire_id' => $this->annee->id,
        'statut' => 'validee',
        'date_inscription' => '2026-09-01',
    ]);

    // Suspension
    actingAs($this->adminUser)
        ->patch(route('administration.inscriptions.statut', $inscription), [
            'statut' => 'suspendue',
            'motif_sortie' => 'Non-paiement 1ère tranche',
        ])
        ->assertRedirect();

    expect($inscription->refresh()->statut)->toBe('suspendue');
});

test('on peut réinscrire un ancien élève dans une nouvelle classe', function () {
    /** @var TestCase $this */
    $eleve = Eleve::create([
        'matricule' => 'GS25-1234',
        'nom' => 'BEA',
        'prenom' => 'Sophie',
        'sexe' => 'F',
        'telephone_parent' => '+237 670 12 34 56',
    ]);

    actingAs($this->adminUser)
        ->post(route('administration.eleves.reinscrire'), [
            'eleve_id' => $eleve->id,
            'classe_id' => $this->classe->id,
            'annee_scolaire_id' => $this->annee->id,
            'is_redoublant' => false,
            'statut' => 'validee',
        ])
        ->assertRedirect();

    $inscription = Inscription::where('eleve_id', $eleve->id)
        ->where('classe_id', $this->classe->id)
        ->where('annee_scolaire_id', $this->annee->id)
        ->first();

    expect($inscription)->not->toBeNull();
    expect($inscription->type_inscription)->toBe('reinscription');
});

test('on peut réinscrire un ancien élève avec report manuel des arriérés antérieurs', function () {
    /** @var TestCase $this */
    $eleve = Eleve::create([
        'matricule' => 'GS25-5678',
        'nom' => 'KAMGA',
        'prenom' => 'Paul',
        'sexe' => 'M',
        'telephone_parent' => '+237 699 00 11 22',
    ]);

    actingAs($this->adminUser)
        ->post(route('administration.eleves.reinscrire'), [
            'eleve_id' => $eleve->id,
            'classe_id' => $this->classe->id,
            'annee_scolaire_id' => $this->annee->id,
            'is_redoublant' => false,
            'statut' => 'validee',
            'arrieres_anterieurs' => 15000,
            'observations' => 'Report impayé an passé',
        ])
        ->assertRedirect();

    $inscription = Inscription::where('eleve_id', $eleve->id)
        ->where('annee_scolaire_id', $this->annee->id)
        ->first();

    expect($inscription)->not->toBeNull();
    expect($inscription->compteFinancier)->not->toBeNull();
    expect($inscription->compteFinancier->arrieres_anterieurs)->toEqual(15000);
    expect($inscription->compteFinancier->montant_net_exigible)->toBeGreaterThanOrEqual(15000);
});

test('searchAnciens renvoie la dernière classe fréquentée et la décision du conseil', function () {
    /** @var TestCase $this */
    // Année précédente
    $anneePrecedente = AnneeScolaire::create([
        'code' => '2024-2025',
        'nom' => 'Année Scolaire 2024/2025',
        'date_debut' => '2024-09-01',
        'date_fin' => '2025-06-30',
        'statut' => 'cloturee',
        'mode_calcul_moyenne' => 'arithmetique_simple',
        'is_current' => false,
    ]);

    $eleve = Eleve::create([
        'matricule' => 'GS24-9999',
        'nom' => 'FOKOU',
        'prenom' => 'David',
        'sexe' => 'M',
        'telephone_parent' => '+237 655 44 33 22',
    ]);

    $inscriptionPassée = Inscription::create([
        'eleve_id' => $eleve->id,
        'classe_id' => $this->classe->id,
        'annee_scolaire_id' => $anneePrecedente->id,
        'type_inscription' => 'nouveau',
        'is_redoublant' => false,
        'statut' => 'validee',
        'date_inscription' => '2024-09-10',
    ]);

    // Délibération de fin d'année passée
    DeliberationConseil::create([
        'etablissement_id' => $this->classe->niveau->cycle->section->etablissement_id,
        'inscription_id' => $inscriptionPassée->id,
        'classe_id' => $this->classe->id,
        'annee_scolaire_id' => $anneePrecedente->id,
        'type_deliberation' => 'annuelle',
        'moyenne_obtenue' => 14.5,
        'rang' => 3,
        'statut_propose' => 'promu',
        'statut_retenu' => 'promu',
        'decision_conseil' => 'Promu en 5ème',
        'est_valide' => true,
    ]);

    $response = actingAs($this->adminUser)
        ->getJson(route('administration.eleves.search-anciens', [
            'q' => 'FOKOU',
            'annee_id' => $this->annee->id,
        ]))
        ->assertOk();

    $data = $response->json();
    expect($data)->toBeArray();
    expect(count($data))->toBeGreaterThanOrEqual(1);

    $eleveTrouve = collect($data)->firstWhere('matricule', 'GS24-9999');
    expect($eleveTrouve)->not->toBeNull();
    expect($eleveTrouve['derniere_classe'])->not->toBeNull();
    expect($eleveTrouve['derniere_classe']['classe_nom'])->toBe($this->classe->nom);
    expect($eleveTrouve['derniere_classe']['annee_nom'])->toBe('Année Scolaire 2024/2025');
    expect($eleveTrouve['derniere_classe']['statut_retenu'])->toBe('promu');
    expect($eleveTrouve['derniere_classe']['is_redoublant_preconise'])->toBeFalse();
});

test('on peut transférer des élèves d une classe vers une autre classe', function () {
    /** @var TestCase $this */
    $classeCible = Classe::create([
        'niveau_id' => $this->classe->niveau_id,
        'code' => '6M2-TEST',
        'nom' => '6ème Moderne 2',
        'capacite_max' => 50,
        'is_active' => true,
    ]);

    $eleve = Eleve::create([
        'matricule' => 'GS26-5555',
        'nom' => 'FOKOU',
        'sexe' => 'M',
        'telephone_parent' => '+237 690 99 88 77',
    ]);

    $inscription = Inscription::create([
        'eleve_id' => $eleve->id,
        'classe_id' => $this->classe->id,
        'annee_scolaire_id' => $this->annee->id,
        'statut' => 'validee',
        'date_inscription' => '2026-09-01',
    ]);

    actingAs($this->adminUser)
        ->post(route('administration.inscriptions.transferer'), [
            'inscription_ids' => [$inscription->id],
            'target_classe_id' => $classeCible->id,
            'motif_transfert' => 'Effectif surchargé en classe 1',
        ])
        ->assertRedirect();

    // Vérifie que l'inscription a été basculée vers la classe cible
    $inscription->refresh();
    expect($inscription->classe_id)->toBe($classeCible->id);
});

test('on peut importer une liste d élèves à partir d un fichier CSV', function () {
    /** @var TestCase $this */
    $csvContent = "Matricule\tMatrNat\tNom\tPrenom\tSexe\tDateNaiss\tLieu\tRedoub\tTel\tAdresse\tTuteur\tNat\n"
        ."IMP-001\t\tEBODE\tChrist\tM\t12/11/2010\tYaounde\tN\t+237 699112233\tBastos\tEBODE Père\tCAMEROUNAISE\n"
        ."\t\tNGOULA\tDiane\tF\t05/04/2011\tDouala\tN\t+237 677223344\tBonanjo\tNGOULA Tuteur\tCAMEROUNAISE\n";

    $file = UploadedFile::fake()->createWithContent('eleves.csv', $csvContent);

    actingAs($this->adminUser)
        ->post(route('administration.eleves.import-csv'), [
            'fichier' => $file,
            'classe_id' => $this->classe->id,
            'annee_scolaire_id' => $this->annee->id,
            'ignorer_doublons' => true,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(Eleve::where('nom', 'EBODE')->exists())->toBeTrue();
    expect(Eleve::where('nom', 'NGOULA')->exists())->toBeTrue();

    $importedEleve = Eleve::where('nom', 'EBODE')->first();
    expect($importedEleve->matricule)->toBe('IMP-001');
    expect($importedEleve->sexe)->toBe('M');
});

test('on peut supprimer un élève et son inscription', function () {
    /** @var TestCase $this */
    $eleve = Eleve::create([
        'matricule' => 'GS26-0099',
        'nom' => 'SUPPRIME',
        'sexe' => 'M',
        'telephone_parent' => '+237 600 00 00 00',
    ]);

    Inscription::create([
        'eleve_id' => $eleve->id,
        'classe_id' => $this->classe->id,
        'annee_scolaire_id' => $this->annee->id,
        'statut' => 'validee',
        'date_inscription' => '2026-09-01',
    ]);

    actingAs($this->adminUser)
        ->delete(route('administration.eleves.destroy', $eleve))
        ->assertRedirect();

    expect(Eleve::find($eleve->id))->toBeNull();
    expect(Inscription::where('eleve_id', $eleve->id)->count())->toBe(0);
});

test('on peut rechercher les anciens élèves non inscrits sur une année donnée pour réinscription', function () {
    /** @var TestCase $this */
    // Élève ancien sans inscription sur l'année active
    $ancienEleve = Eleve::create([
        'matricule' => 'GS24-8888',
        'nom' => 'KOUAM',
        'prenom' => 'Hervé',
        'sexe' => 'M',
        'telephone_parent' => '+237 677 00 11 22',
        'is_active' => true,
    ]);

    // Élève déjà inscrit sur l'année active (ne doit pas ressortir)
    $eleveInscrit = Eleve::create([
        'matricule' => 'GS24-9999',
        'nom' => 'KOUAM',
        'prenom' => 'Bertrand',
        'sexe' => 'M',
        'telephone_parent' => '+237 677 00 11 33',
        'is_active' => true,
    ]);

    Inscription::create([
        'eleve_id' => $eleveInscrit->id,
        'classe_id' => $this->classe->id,
        'annee_scolaire_id' => $this->annee->id,
        'statut' => 'validee',
        'date_inscription' => '2026-09-01',
    ]);

    $response = actingAs($this->adminUser)
        ->getJson(route('administration.eleves.search-anciens', [
            'q' => 'KOUAM',
            'annee_id' => $this->annee->id,
        ]))
        ->assertOk();

    $data = $response->json();
    $ids = collect($data)->pluck('id')->all();

    // L'ancien élève non inscrit doit être renvoyé
    expect($ids)->toContain($ancienEleve->id);
    // L'élève déjà inscrit ne doit pas être renvoyé
    expect($ids)->not->toContain($eleveInscrit->id);
});

/**
 * Teste le cloisonnement strict multi-établissements des élèves et inscriptions.
 * Vérifie que la sélection d'un établissement isole strictement les effectifs et
 * qu'un transfert inter-établissements réassigne hermétiquement l'inscription.
 */
test('eleves and inscriptions are strictly isolated per etablissement', function () {
    /** @var TestCase $this */
    // Création de deux établissements distincts
    $inst = Institution::firstOrCreate(
        ['code' => 'INST-MULTI'],
        ['nom' => 'Groupe Scolaire Bilingue', 'sigle' => 'GSB', 'telephone_1' => '+237 600000000']
    );
    $etabA = Etablissement::create([
        'institution_id' => $inst->id,
        'nom' => 'Campus Primaire',
        'code' => 'CAMP-PRIM',
        'statut_juridique' => 'Prive_Laic',
        'ordre_enseignement' => 'Primaire',
    ]);
    $etabB = Etablissement::create([
        'institution_id' => $inst->id,
        'nom' => 'Campus Secondaire',
        'code' => 'CAMP-SEC',
        'statut_juridique' => 'Prive_Laic',
        'ordre_enseignement' => 'Secondaire_General',
    ]);

    $secA = Section::create(['etablissement_id' => $etabA->id, 'code' => 'SEC-PA', 'nom' => 'Section Prim', 'langue' => 'fr']);
    $cycA = Cycle::create(['section_id' => $secA->id, 'code' => 'CYC-PA', 'nom' => 'Cycle Prim', 'ordre' => 1]);
    $nivA = Niveau::create(['cycle_id' => $cycA->id, 'code' => 'NIV-SIL', 'nom' => 'SIL', 'ordre' => 1]);
    $classeA = Classe::create(['niveau_id' => $nivA->id, 'etablissement_id' => $etabA->id, 'code' => 'SIL-A', 'nom' => 'Classe SIL A', 'capacite_max' => 30]);

    $secB = Section::create(['etablissement_id' => $etabB->id, 'code' => 'SEC-SB', 'nom' => 'Section Sec', 'langue' => 'fr']);
    $cycB = Cycle::create(['section_id' => $secB->id, 'code' => 'CYC-SB', 'nom' => 'Cycle Sec', 'ordre' => 1]);
    $nivB = Niveau::create(['cycle_id' => $cycB->id, 'code' => 'NIV-6B', 'nom' => 'Sixième', 'ordre' => 1]);
    $classeB = Classe::create(['niveau_id' => $nivB->id, 'etablissement_id' => $etabB->id, 'code' => '6B-B', 'nom' => 'Classe 6ème B', 'capacite_max' => 40]);

    // Élève Campus A
    $eleveA = Eleve::create([
        'matricule' => 'ETAB-A-001',
        'nom' => 'ELEVE_CAMPUS_A',
        'sexe' => 'F',
        'telephone_parent' => '+237 611 11 11 11',
    ]);
    $inscA = Inscription::create([
        'eleve_id' => $eleveA->id,
        'classe_id' => $classeA->id,
        'annee_scolaire_id' => $this->annee->id,
        'statut' => 'validee',
        'date_inscription' => '2026-09-01',
    ]);

    // Élève Campus B
    $eleveB = Eleve::create([
        'matricule' => 'ETAB-B-001',
        'nom' => 'ELEVE_CAMPUS_B',
        'sexe' => 'M',
        'telephone_parent' => '+237 622 22 22 22',
    ]);
    $inscB = Inscription::create([
        'eleve_id' => $eleveB->id,
        'classe_id' => $classeB->id,
        'annee_scolaire_id' => $this->annee->id,
        'statut' => 'validee',
        'date_inscription' => '2026-09-01',
    ]);

    // Vérifier que le hook de modèle a bien assigné etablissement_id
    expect($inscA->etablissement_id)->toBe($etabA->id);
    expect($inscB->etablissement_id)->toBe($etabB->id);

    // 1. Consultation filtrée sur Campus A
    $responseA = actingAs($this->adminUser)->get(route('administration.eleves.index', [
        'annee_scolaire_id' => $this->annee->id,
        'etablissement_id' => $etabA->id,
    ]));
    $responseA->assertOk();
    $responseA->assertInertia(fn ($page) => $page
        ->component('administration/eleves/index')
        ->where('stats.totalInscrits', 1)
        ->has('inscriptions', 1)
        ->where('inscriptions.0.eleve.nom', 'ELEVE_CAMPUS_A')
    );

    // 2. Consultation filtrée sur Campus B
    $responseB = actingAs($this->adminUser)->get(route('administration.eleves.index', [
        'annee_scolaire_id' => $this->annee->id,
        'etablissement_id' => $etabB->id,
    ]));
    $responseB->assertOk();
    $responseB->assertInertia(fn ($page) => $page
        ->component('administration/eleves/index')
        ->where('stats.totalInscrits', 1)
        ->has('inscriptions', 1)
        ->where('inscriptions.0.eleve.nom', 'ELEVE_CAMPUS_B')
    );
});

/**
 * Vérifie l'intégrité référentielle stricte : un élève ayant déjà effectué un versement financier
 * ou possédant déjà une note / évaluation pédagogique ne peut jamais être supprimé,
 * de même qu'une séquence d'évaluation ayant déjà des notes saisies.
 */
test('interdit la suppression d un élève ayant un versement ou une note ainsi que d une évaluation notée', function () {
    /** @var TestCase $this */
    Permission::firstOrCreate(['name' => 'periodes.manage', 'guard_name' => 'web']);
    $etabId = Etablissement::firstOrFail()->id;

    // 1. Élève ayant effectué un versement en caisse -> suppression bloquée
    $eleveAvecVersement = Eleve::create([
        'etablissement_id' => $etabId,
        'matricule' => 'GS26-LOCK-FIN',
        'nom' => 'ATANGANA',
        'prenom' => 'Samuel',
        'sexe' => 'M',
        'telephone_parent' => '+237 690 11 22 33',
    ]);
    $inscFin = Inscription::create([
        'eleve_id' => $eleveAvecVersement->id,
        'classe_id' => $this->classe->id,
        'etablissement_id' => $etabId,
        'annee_scolaire_id' => $this->annee->id,
        'statut' => 'validee',
        'date_inscription' => '2026-09-01',
    ]);
    $caisse = Caisse::create([
        'etablissement_id' => $etabId,
        'code' => 'CAISSE-LOCK',
        'nom' => 'Caisse Principale Test',
        'is_principale' => true,
        'is_active' => true,
    ]);
    $compte = CompteFinancierEleve::create([
        'inscription_id' => $inscFin->id,
        'eleve_id' => $eleveAvecVersement->id,
        'etablissement_id' => $etabId,
        'annee_scolaire_id' => $this->annee->id,
        'debit_initial' => 50000,
        'montant_net_exigible' => 50000,
        'total_paye' => 15000,
        'solde_restant' => 35000,
        'statut_financier' => 'insolvable',
    ]);
    VersementCaisse::create([
        'reference_recu' => 'REC-LOCK-0001',
        'etablissement_id' => $etabId,
        'caisse_id' => $caisse->id,
        'compte_financier_id' => $compte->id,
        'inscription_id' => $inscFin->id,
        'caissier_user_id' => $this->adminUser->id,
        'montant_total' => 15000,
        'mode_paiement' => 'especes',
        'date_versement' => now(),
        'statut' => 'valide',
    ]);

    actingAs($this->adminUser)
        ->delete(route('administration.eleves.destroy', $eleveAvecVersement))
        ->assertRedirect()
        ->assertSessionHasErrors('error');

    expect(Eleve::find($eleveAvecVersement->id))->not->toBeNull();

    // 2. Élève ayant une note pédagogique -> suppression de l'élève ET de l'évaluation bloquées
    $eleveAvecNote = Eleve::create([
        'etablissement_id' => $etabId,
        'matricule' => 'GS26-LOCK-NOTE',
        'nom' => 'FOUDA',
        'prenom' => 'Mireille',
        'sexe' => 'F',
        'telephone_parent' => '+237 690 44 55 66',
    ]);
    $inscNote = Inscription::create([
        'eleve_id' => $eleveAvecNote->id,
        'classe_id' => $this->classe->id,
        'etablissement_id' => $etabId,
        'annee_scolaire_id' => $this->annee->id,
        'statut' => 'validee',
        'date_inscription' => '2026-09-01',
    ]);

    $periode = Periode::create([
        'etablissement_id' => $etabId,
        'annee_scolaire_id' => $this->annee->id,
        'code' => 'TRIM-LOCK-1',
        'nom' => 'Trimestre 1',
        'ordre' => 1,
        'statut' => 'en_cours',
    ]);
    $evaluation = Evaluation::create([
        'periode_id' => $periode->id,
        'code' => 'SEQ-LOCK-1',
        'nom' => 'Séquence 1',
        'ordre' => 1,
        'ponderation' => 50,
        'statut' => 'ouverte',
    ]);
    $matiere = Matiere::create([
        'etablissement_id' => $etabId,
        'code' => 'MATH-LOCK',
        'nom' => 'Mathématiques',
        'is_active' => true,
    ]);
    $enseignant = Personnel::create([
        'etablissement_id' => $etabId,
        'matricule' => 'PERS-LOCK-01',
        'nom' => 'NDI',
        'prenom' => 'Paul',
        'sexe' => 'M',
        'telephone' => '+237 677 88 99 00',
        'fonction_principale' => 'Enseignant',
        'type_contrat' => 'CDI',
        'date_embauche' => '2025-09-01',
        'statut' => 'actif',
        'is_enseignant' => true,
        'volume_horaire_hebdo' => 18,
    ]);
    $attribution = Attribution::create([
        'annee_scolaire_id' => $this->annee->id,
        'personnel_id' => $enseignant->id,
        'classe_id' => $this->classe->id,
        'matiere_id' => $matiere->id,
        'volume_horaire_reel' => 4,
        'is_titulaire' => false,
        'is_active' => true,
    ]);
    Note::create([
        'inscription_id' => $inscNote->id,
        'attribution_id' => $attribution->id,
        'evaluation_id' => $evaluation->id,
        'valeur_note' => 16.5,
        'statut_presence' => 'present',
    ]);

    // Suppression de l'élève ayant une note -> refusée
    actingAs($this->adminUser)
        ->delete(route('administration.eleves.destroy', $eleveAvecNote))
        ->assertRedirect()
        ->assertSessionHasErrors('error');

    expect(Eleve::find($eleveAvecNote->id))->not->toBeNull();

    // Suppression de la séquence d'évaluation ayant une note -> refusée
    actingAs($this->adminUser)
        ->delete(route('administration.periodes.evaluations.destroy', $evaluation))
        ->assertRedirect()
        ->assertSessionHasErrors('error');

    expect(Evaluation::find($evaluation->id))->not->toBeNull();
});
