<?php

/** @var TestCase $this */

use App\Models\AnneeScolaire;
use App\Models\Attribution;
use App\Models\BourseRemise;
use App\Models\Caisse;
use App\Models\Classe;
use App\Models\CompteFinancierEleve;
use App\Models\Cycle;
use App\Models\DeliberationConseil;
use App\Models\Eleve;
use App\Models\Etablissement;
use App\Models\Evaluation;
use App\Models\FraisScolaire;
use App\Models\Inscription;
use App\Models\Institution;
use App\Models\Matiere;
use App\Models\Niveau;
use App\Models\Note;
use App\Models\Periode;
use App\Models\Personnel;
use App\Models\Section;
use App\Models\TypeFrais;
use App\Models\User;
use App\Models\VentilationVersement;
use App\Models\VersementCaisse;
use App\Services\FinanceService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
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

test('la validation échoue si les champs obligatoires sont absents tandis que sexe et telephone_parent restent optionnels', function () {
    /** @var TestCase $this */
    actingAs($this->adminUser)
        ->post(route('administration.eleves.store'), [
            'nom' => '',
            'sexe' => '',
            'telephone_parent' => '',
            'classe_id' => $this->classe->id,
            'annee_scolaire_id' => $this->annee->id,
        ])
        ->assertSessionHasErrors(['nom', 'statut', 'is_redoublant'])
        ->assertSessionDoesntHaveErrors(['sexe', 'telephone_parent']);
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
        'matricule' => 'TENTATIVE-MODIF-MATRICULE',
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
    // Le matricule interne doit rester strictement en lecture seule (immuable)
    expect($eleve->matricule)->toBe('GS26-9999');
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

test('le sélecteur global d en-tête filtre la page des élèves par campus et permet le retour à tous les établissements', function () {
    /** @var TestCase $this */
    $inst = Institution::first();
    $etabA = Etablissement::firstOrCreate(
        ['code' => 'ETAB-SW-A'],
        ['institution_id' => $inst->id, 'nom' => 'Campus Maternelle Switch']
    );
    $etabB = Etablissement::firstOrCreate(
        ['code' => 'ETAB-SW-B'],
        ['institution_id' => $inst->id, 'nom' => 'Campus Primaire Switch']
    );

    $secA = Section::create(['etablissement_id' => $etabA->id, 'code' => 'SEC-SWA', 'nom' => 'Section A']);
    $cycA = Cycle::create(['section_id' => $secA->id, 'code' => 'CYC-SWA', 'nom' => 'Cycle A', 'ordre' => 1]);
    $nivA = Niveau::create(['cycle_id' => $cycA->id, 'code' => 'NIV-SWA', 'nom' => 'Niveau A', 'ordre' => 1]);
    $clsA = Classe::create(['etablissement_id' => $etabA->id, 'niveau_id' => $nivA->id, 'code' => 'CLS-SWA', 'nom' => 'Classe A', 'capacite_max' => 40, 'is_active' => true]);

    $secB = Section::create(['etablissement_id' => $etabB->id, 'code' => 'SEC-SWB', 'nom' => 'Section B']);
    $cycB = Cycle::create(['section_id' => $secB->id, 'code' => 'CYC-SWB', 'nom' => 'Cycle B', 'ordre' => 1]);
    $nivB = Niveau::create(['cycle_id' => $cycB->id, 'code' => 'NIV-SWB', 'nom' => 'Niveau B', 'ordre' => 1]);
    $clsB = Classe::create(['etablissement_id' => $etabB->id, 'niveau_id' => $nivB->id, 'code' => 'CLS-SWB', 'nom' => 'Classe B', 'capacite_max' => 40, 'is_active' => true]);

    $elA = Eleve::create(['matricule' => 'SW-A-01', 'nom' => 'ELEVE_SWITCH_A', 'sexe' => 'F']);
    Inscription::create([
        'eleve_id' => $elA->id,
        'classe_id' => $clsA->id,
        'etablissement_id' => $etabA->id,
        'annee_scolaire_id' => $this->annee->id,
        'statut' => 'validee',
        'date_inscription' => '2026-09-01',
    ]);

    $elB = Eleve::create(['matricule' => 'SW-B-01', 'nom' => 'ELEVE_SWITCH_B', 'sexe' => 'M']);
    Inscription::create([
        'eleve_id' => $elB->id,
        'classe_id' => $clsB->id,
        'etablissement_id' => $etabB->id,
        'annee_scolaire_id' => $this->annee->id,
        'statut' => 'validee',
        'date_inscription' => '2026-09-01',
    ]);

    // 1. Bascule via le sélecteur global d'en-tête vers Campus B
    $this->actingAs($this->adminUser)
        ->from('/administration/eleves')
        ->post(route('context.switch-etablissement'), [
            'etablissement_id' => $etabB->id,
        ])
        ->assertRedirect('/administration/eleves');

    $this->actingAs($this->adminUser)
        ->get(route('administration.eleves.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('supportsAllEtablissements', true)
            ->where('isAllEtablissements', false)
            ->where('filters.etablissement_id', (string) $etabB->id)
            ->where('stats.totalInscrits', 1)
            ->has('inscriptions', 1)
            ->where('inscriptions.0.eleve.nom', 'ELEVE_SWITCH_B')
        );

    // 2. Bascule via le sélecteur global d'en-tête vers « Tous les établissements » ('all')
    $this->actingAs($this->adminUser)
        ->from('/administration/eleves')
        ->post(route('context.switch-etablissement'), [
            'etablissement_id' => 'all',
        ])
        ->assertRedirect('/administration/eleves');

    $this->actingAs($this->adminUser)
        ->get(route('administration.eleves.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('supportsAllEtablissements', true)
            ->where('isAllEtablissements', true)
            ->where('filters.etablissement_id', '')
            ->where('stats.totalInscrits', 2)
            ->has('inscriptions', 2)
        );
});

/**
 * Teste la génération du nouveau modèle de Fiche d'Appel Journalière (Lundi–Vendredi sans créneaux horaires)
 * ainsi que le maintien du modèle horaire classique à 8 séances.
 */
test('la fiche d appel journaliere genere un tableau hebdomadaire neutre sans creneaux horaires tandis que le mode horaire reste intact', function () {
    /** @var TestCase $this */
    $eleve = Eleve::create([
        'matricule' => 'ESP-PRI-2026-0001',
        'nom' => 'MBAPPE',
        'prenom' => 'Junior',
        'sexe' => 'M',
    ]);
    Inscription::create([
        'eleve_id' => $eleve->id,
        'classe_id' => $this->classe->id,
        'etablissement_id' => $this->classe->etablissement_id,
        'annee_scolaire_id' => $this->annee->id,
        'statut' => 'validee',
        'date_inscription' => '2026-09-07',
    ]);

    $capturedJournaliere = null;
    view()->composer('documents.fiche_appel_journaliere', function ($view) use (&$capturedJournaliere): void {
        $capturedJournaliere = $view->getData();
    });

    $respJournaliere = actingAs($this->adminUser)->get(
        route('administration.documents.fiche-appel', $this->classe).'?mode=journaliere&annee_id='.$this->annee->id
    );

    $respJournaliere->assertOk();
    expect($respJournaliere->headers->get('Content-Type'))->toBe('application/pdf');
    expect($capturedJournaliere)->toBeArray();

    $htmlJournaliere = view('documents.fiche_appel_journaliere', $capturedJournaliere)->render();
    expect($htmlJournaliere)->toContain("Fiche d'Appel Journalière (Lundi – Vendredi)")
        ->toContain('Lundi')
        ->toContain('Mardi')
        ->toContain('Mercredi')
        ->toContain('Jeudi')
        ->toContain('Vendredi')
        ->toContain('Observations')
        ->toContain('TOTAL PRÉSENTS')
        ->toContain('TOTAL ABSENTS')
        ->toContain('Le / La Responsable de Classe')
        ->toContain('La Direction')
        ->toContain('MBAPPE')
        ->not->toContain('Primaire')
        ->not->toContain('Secondaire')
        ->not->toContain('Délégué de Classe')
        ->not->toContain('Surveillant Général')
        ->not->toContain('th-hour');

    // Le mode horaire historique (par défaut ou ?mode=horaire) reste inchangé
    $capturedHoraire = null;
    view()->composer('documents.fiche_appel', function ($view) use (&$capturedHoraire): void {
        $capturedHoraire = $view->getData();
    });

    $respHoraire = actingAs($this->adminUser)->get(
        route('administration.documents.fiche-appel', $this->classe).'?mode=horaire&annee_id='.$this->annee->id
    );

    $respHoraire->assertOk();
    expect($respHoraire->headers->get('Content-Type'))->toBe('application/pdf');
    expect($capturedHoraire)->toBeArray();

    $htmlHoraire = view('documents.fiche_appel', $capturedHoraire)->render();
    expect($htmlHoraire)->toContain('th-hour')
        ->toContain('LUNDI');
});

/**
 * Teste l'harmonisation de la nomenclature automatique des matricules sur {CODE_ETAB}-{YYYY}-{0001}
 * lors d'une création manuelle ou d'une importation CSV sans écraser les matricules existants.
 */
test('la creation manuelle et l import CSV generent un matricule au format CODE_ETAB-YYYY-0001 en continuite avec l existant', function () {
    /** @var TestCase $this */
    $inst = Institution::firstOrCreate(
        ['code' => 'INST-ESP-NOM'],
        ['nom' => 'GSB ESPOIR', 'sigle' => 'ESP']
    );
    $etabPri = Etablissement::create([
        'institution_id' => $inst->id,
        'code' => 'ESP-PRI',
        'nom' => 'École Primaire Espoir',
        'statut_juridique' => 'Prive_Laic',
        'ordre_enseignement' => 'Primaire',
    ]);
    $sec = Section::create(['etablissement_id' => $etabPri->id, 'code' => 'SEC-PRI-NOM', 'nom' => 'Section FR']);
    $cyc = Cycle::create(['section_id' => $sec->id, 'code' => 'CYC-PRI-NOM', 'nom' => 'Cycle Primaire', 'ordre' => 1]);
    $niv = Niveau::create(['cycle_id' => $cyc->id, 'code' => 'NIV-CM2-NOM', 'nom' => 'CM2', 'ordre' => 6]);
    $classePri = Classe::create([
        'etablissement_id' => $etabPri->id,
        'niveau_id' => $niv->id,
        'code' => 'CM2-A',
        'nom' => 'CM2 A',
        'capacite_max' => 45,
        'is_active' => true,
    ]);

    // Élève déjà présent dans la nomenclature structurée du campus Primaire
    Eleve::create([
        'matricule' => 'ESP-PRI-2026-0282',
        'nom' => 'EXISTANT',
        'prenom' => 'Éric',
    ]);

    // 1. Création manuelle d'un élève dans la classe du Primaire -> doit produire ESP-PRI-2026-0283
    actingAs($this->adminUser)
        ->post(route('administration.eleves.store'), [
            'nom' => 'NOUVEAU_PRIMAIRE',
            'prenom' => 'Claire',
            'classe_id' => $classePri->id,
            'annee_scolaire_id' => $this->annee->id,
            'statut' => 'validee',
            'is_redoublant' => false,
        ])
        ->assertRedirect();

    $eleveManuel = Eleve::where('nom', 'NOUVEAU_PRIMAIRE')->first();
    expect($eleveManuel)->not->toBeNull();
    expect($eleveManuel->matricule)->toBe('ESP-PRI-2026-0283');

    // 2. Importation CSV sans matricule dans le même campus -> doit produire ESP-PRI-2026-0284
    $csvContent = "Matricule\tMatrNat\tNom\tPrenom\tSexe\tDateNaiss\tLieu\tRedoub\tTel\tAdresse\tTuteur\tNat\n"
        ."\t\tIMPORT_PRIMAIRE\tPaul\tM\t10/05/2015\tDouala\tN\t+237 699000011\tBonaberi\tParent\tCAMEROUNAISE\n";
    $file = UploadedFile::fake()->createWithContent('eleves_pri.csv', $csvContent);

    actingAs($this->adminUser)
        ->post(route('administration.eleves.import-csv'), [
            'fichier' => $file,
            'classe_id' => $classePri->id,
            'annee_scolaire_id' => $this->annee->id,
            'ignorer_doublons' => true,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $eleveCsv = Eleve::where('nom', 'IMPORT_PRIMAIRE')->first();
    expect($eleveCsv)->not->toBeNull();
    expect($eleveCsv->matricule)->toBe('ESP-PRI-2026-0284');
});

/**
 * Teste que la suppression d'un élève bloqué par un versement financier valide
 * devient possible dès que le versement erroné a été annulé en Caisse, tout en journalisant l'audit.
 */
test('un eleve dont le versement errone a ete annule peut etre supprime proprement avec audit', function () {
    /** @var TestCase $this */
    $etabId = $this->classe->etablissement_id ?? Etablissement::firstOrFail()->id;

    $eleve = Eleve::create([
        'matricule' => '26-0001',
        'nom' => 'FOTSO FOGUE',
        'prenom' => 'Gauklin',
        'sexe' => 'M',
    ]);
    $insc = Inscription::create([
        'eleve_id' => $eleve->id,
        'classe_id' => $this->classe->id,
        'etablissement_id' => $etabId,
        'annee_scolaire_id' => $this->annee->id,
        'statut' => 'validee',
        'date_inscription' => '2026-09-07',
    ]);
    $caisse = Caisse::firstOrCreate(
        ['etablissement_id' => $etabId, 'code' => 'CAISSE-ERR'],
        ['nom' => 'Caisse Test', 'is_principale' => true, 'is_active' => true]
    );
    $compte = CompteFinancierEleve::create([
        'inscription_id' => $insc->id,
        'eleve_id' => $eleve->id,
        'etablissement_id' => $etabId,
        'annee_scolaire_id' => $this->annee->id,
        'debit_initial' => 50000,
        'montant_net_exigible' => 50000,
        'total_paye' => 15000,
        'solde_restant' => 35000,
        'statut_financier' => 'acompte',
    ]);
    $versement = VersementCaisse::create([
        'reference_recu' => 'REC-ERR-0001',
        'etablissement_id' => $etabId,
        'caisse_id' => $caisse->id,
        'compte_financier_id' => $compte->id,
        'inscription_id' => $insc->id,
        'caissier_user_id' => $this->adminUser->id,
        'montant_total' => 15000,
        'mode_paiement' => 'especes',
        'date_versement' => now(),
        'statut' => 'valide',
    ]);
    VentilationVersement::create([
        'versement_id' => $versement->id,
        'libelle' => 'Inscription',
        'montant' => 15000,
    ]);

    // 1. Tant que le versement est valide, la suppression est strictement refusée
    actingAs($this->adminUser)
        ->delete(route('administration.eleves.destroy', $eleve))
        ->assertRedirect()
        ->assertSessionHasErrors('error');
    expect(Eleve::find($eleve->id))->not->toBeNull();

    // 2. Après annulation administrative du versement erroné, la suppression doit réussir
    $versement->update([
        'statut' => 'annule',
        'motif_annulation' => 'Élève inscrit par erreur',
        'annule_par' => $this->adminUser->id,
        'annule_le' => now(),
    ]);

    Log::shouldReceive('warning')
        ->once()
        ->withArgs(fn (string $msg, array $ctx) => $msg === 'Suppression définitive élève exécutée'
            && $ctx['matricule'] === '26-0001'
            && count($ctx['recus_annules_purges']) === 1
            && $ctx['recus_annules_purges'][0]['reference_recu'] === 'REC-ERR-0001');

    actingAs($this->adminUser)
        ->delete(route('administration.eleves.destroy', $eleve))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->assertDatabaseMissing('eleves', ['id' => $eleve->id]);
    $this->assertDatabaseMissing('inscriptions', ['id' => $insc->id]);
    $this->assertDatabaseMissing('comptes_financiers_eleves', ['id' => $compte->id]);
    $this->assertDatabaseMissing('versements_caisse', ['id' => $versement->id]);
});

test('un administrateur peut televerser une photo lors de la creation et l afficher dans l index et via la route de secours', function () {
    /** @var TestCase $this */
    Storage::fake('public');

    $photo = UploadedFile::fake()->image('portrait_eleve.jpg', 1200, 1200);

    actingAs($this->adminUser)
        ->post(route('administration.eleves.store'), [
            'nom' => 'MBARGA',
            'prenom' => 'Paul',
            'sexe' => 'M',
            'classe_id' => $this->classe->id,
            'annee_scolaire_id' => $this->annee->id,
            'statut' => 'validee',
            'is_redoublant' => false,
            'photo' => $photo,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $eleve = Eleve::where('nom', 'MBARGA')->firstOrFail();
    expect($eleve->photo_url)->toStartWith('/storage/photos_eleves/');

    $relativePath = ltrim(str_replace('/storage/', '', $eleve->photo_url), '/');
    Storage::disk('public')->assertExists($relativePath);

    // La route de secours publique doit servir la photo stockée sur le disque public
    get($eleve->photo_url)->assertOk();

    // L'index des élèves doit bien exposer photo_url dans l'inscription chargée
    actingAs($this->adminUser)
        ->get(route('administration.eleves.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('administration/eleves/index')
            ->has('inscriptions', 1, fn ($insc) => $insc
                ->where('eleve.nom', 'MBARGA')
                ->where('eleve.photo_url', $eleve->photo_url)
                ->etc()
            )
        );
});

test('un administrateur peut modifier puis supprimer la photo d un eleve via POST _method PUT', function () {
    /** @var TestCase $this */
    Storage::fake('public');

    $initialPhoto = UploadedFile::fake()->image('initiale.png', 900, 900);
    $initialPath = $initialPhoto->store('photos_eleves', 'public');

    $eleve = Eleve::create([
        'matricule' => 'GS26-8801',
        'nom' => 'NDJOCK',
        'prenom' => 'Clarisse',
        'sexe' => 'F',
        'photo_url' => '/storage/'.ltrim($initialPath, '/'),
    ]);

    $etabId = $this->classe->niveau?->cycle?->section?->etablissement_id ?? Etablissement::value('id');

    Inscription::create([
        'eleve_id' => $eleve->id,
        'classe_id' => $this->classe->id,
        'etablissement_id' => $etabId,
        'annee_scolaire_id' => $this->annee->id,
        'type_inscription' => 'nouveau',
        'is_redoublant' => false,
        'statut' => 'validee',
        'date_inscription' => now()->toDateString(),
    ]);

    // 1. Remplacement de la photo existante par un nouveau portrait via _method=PUT
    $newPhoto = UploadedFile::fake()->image('nouvelle_photo.jpg', 1000, 1000);
    actingAs($this->adminUser)
        ->post(route('administration.eleves.update', $eleve), [
            '_method' => 'PUT',
            'nom' => 'NDJOCK',
            'prenom' => 'Clarisse',
            'photo' => $newPhoto,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $eleve->refresh();
    expect($eleve->photo_url)->toStartWith('/storage/photos_eleves/')
        ->and($eleve->photo_url)->not->toBe('/storage/'.ltrim($initialPath, '/'));

    $newRelativePath = ltrim(str_replace('/storage/', '', $eleve->photo_url), '/');
    Storage::disk('public')->assertMissing($initialPath);
    Storage::disk('public')->assertExists($newRelativePath);

    // 2. Suppression explicite de la photo via remove_photo=1
    actingAs($this->adminUser)
        ->post(route('administration.eleves.update', $eleve), [
            '_method' => 'PUT',
            'nom' => 'NDJOCK',
            'prenom' => 'Clarisse',
            'remove_photo' => '1',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $eleve->refresh();
    expect($eleve->photo_url)->toBeNull();
    Storage::disk('public')->assertMissing($newRelativePath);
});

test('une photo depassant 5 Mo est rejetee par la validation lors de l enregistrement ou de la mise a jour', function () {
    /** @var TestCase $this */
    Storage::fake('public');

    $tooBig = UploadedFile::fake()->create('grosse_photo.jpg', 6000, 'image/jpeg');

    actingAs($this->adminUser)
        ->post(route('administration.eleves.store'), [
            'nom' => 'TROP_GROS',
            'classe_id' => $this->classe->id,
            'annee_scolaire_id' => $this->annee->id,
            'statut' => 'validee',
            'is_redoublant' => false,
            'photo' => $tooBig,
        ])
        ->assertSessionHasErrors('photo');
});

test('un transfert dans le meme etablissement vers une classe au tarif different recalcule la pension tout en conservant paiements et remise', function () {
    /** @var TestCase $this */
    $etabId = $this->classe->niveau?->cycle?->section?->etablissement_id ?? Etablissement::value('id');
    $cycleId = $this->classe->niveau?->cycle_id;

    $typeFrais = TypeFrais::create([
        'etablissement_id' => $etabId,
        'code' => 'SCOL-TR-INTERNE',
        'libelle' => 'Scolarité Transfert Test',
        'obligatoire' => true,
        'periodicite' => 'annuel',
        'montant_defaut' => 80000,
    ]);

    // Tarif source : 80 000 FCFA sur le niveau d'origine
    FraisScolaire::create([
        'annee_scolaire_id' => $this->annee->id,
        'etablissement_id' => $etabId,
        'niveau_id' => $this->classe->niveau_id,
        'type_frais_id' => $typeFrais->id,
        'tranche_numero' => 1,
        'ordre_priorite' => 1,
        'libelle_tranche' => 'Tranche Unique Source',
        'montant' => 80000,
        'obligatoire' => true,
        'date_limite' => now()->addMonths(3)->toDateString(),
    ]);

    // Niveau & classe cibles dans le même établissement avec tarif différent : 110 000 FCFA
    $niveauCible = Niveau::create([
        'cycle_id' => $cycleId,
        'code' => 'NIV-CIBLE-TR',
        'nom' => 'Niveau Cible Transfert',
        'ordre' => 99,
    ]);

    $classeCible = Classe::create([
        'etablissement_id' => $etabId,
        'niveau_id' => $niveauCible->id,
        'code' => 'CLS-CIBLE-TR',
        'nom' => '5ème Moderne Cible',
        'capacite_max' => 50,
        'is_active' => true,
    ]);

    FraisScolaire::create([
        'annee_scolaire_id' => $this->annee->id,
        'etablissement_id' => $etabId,
        'niveau_id' => $niveauCible->id,
        'type_frais_id' => $typeFrais->id,
        'tranche_numero' => 1,
        'ordre_priorite' => 1,
        'libelle_tranche' => 'Tranche Unique Cible',
        'montant' => 110000,
        'obligatoire' => true,
        'date_limite' => now()->addMonths(3)->toDateString(),
    ]);

    $eleve = Eleve::create([
        'matricule' => 'GS26-TR-001',
        'nom' => 'ATEBA',
        'prenom' => 'Marc',
        'sexe' => 'M',
    ]);

    $inscription = Inscription::create([
        'eleve_id' => $eleve->id,
        'classe_id' => $this->classe->id,
        'etablissement_id' => $etabId,
        'annee_scolaire_id' => $this->annee->id,
        'type_inscription' => 'nouveau',
        'statut' => 'validee',
        'date_inscription' => now()->toDateString(),
    ]);

    $financeService = app(FinanceService::class);
    $compte = $financeService->initialiserCompteEleve($inscription);
    expect($compte->debit_initial)->toBe(80000.0);

    // Enregistrement d'un versement valide de 30 000 FCFA et d'une remise de 10 %
    $caisse = Caisse::create([
        'etablissement_id' => $etabId,
        'code' => 'CAISSE-TR',
        'nom' => 'Caisse Test Transfert',
        'solde_initial' => 0,
        'is_principale' => true,
        'is_active' => true,
    ]);

    VersementCaisse::create([
        'reference_recu' => 'REC-TR-0001',
        'compte_financier_id' => $compte->id,
        'inscription_id' => $inscription->id,
        'etablissement_id' => $etabId,
        'caisse_id' => $caisse->id,
        'date_versement' => now()->toDateString(),
        'montant_total' => 30000,
        'mode_paiement' => 'especes',
        'statut' => 'valide',
    ]);

    $bourse = BourseRemise::create([
        'etablissement_id' => $etabId,
        'annee_scolaire_id' => $this->annee->id,
        'code' => 'REM-10PCT',
        'nom' => 'Remise 10%',
        'type' => 'remise',
        'mode_calcul' => 'pourcentage',
        'valeur' => 10,
        'base_calcul' => 'total_scolarite',
        'is_active' => true,
    ]);

    $financeService->attribuerBourseRemise($compte, $bourse, 'Fratrie', $this->adminUser);
    $compte->refresh();
    expect($compte->remise_bourse)->toBe(8000.0)
        ->and($compte->montant_net_exigible)->toBe(72000.0)
        ->and($compte->total_paye)->toBe(30000.0)
        ->and($compte->solde_restant)->toBe(42000.0);

    // Exécution du transfert vers la classe cible à 110 000 FCFA
    actingAs($this->adminUser)
        ->post(route('administration.inscriptions.transferer'), [
            'inscription_ids' => [$inscription->id],
            'target_classe_id' => $classeCible->id,
            'motif_transfert' => 'Changement de niveau',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $inscription->refresh();
    $compte->refresh();

    expect($inscription->classe_id)->toBe($classeCible->id)
        ->and($compte->debit_initial)->toBe(110000.0)
        ->and($compte->remise_bourse)->toBe(11000.0)
        ->and($compte->montant_net_exigible)->toBe(99000.0)
        ->and($compte->total_paye)->toBe(30000.0)
        ->and($compte->solde_restant)->toBe(69000.0);
});

test('un transfert inter-campus archive le compte source et cree une nouvelle inscription avec compte financier separe', function () {
    /** @var TestCase $this */
    $etab1Id = $this->classe->niveau?->cycle?->section?->etablissement_id ?? Etablissement::value('id');
    $institutionId = Etablissement::where('id', $etab1Id)->value('institution_id') ?? Institution::value('id');

    $typeFrais1 = TypeFrais::create([
        'etablissement_id' => $etab1Id,
        'code' => 'SCOL-CAMP1',
        'libelle' => 'Scolarité Campus 1',
        'obligatoire' => true,
        'periodicite' => 'annuel',
        'montant_defaut' => 70000,
    ]);

    FraisScolaire::create([
        'annee_scolaire_id' => $this->annee->id,
        'etablissement_id' => $etab1Id,
        'niveau_id' => $this->classe->niveau_id,
        'type_frais_id' => $typeFrais1->id,
        'tranche_numero' => 1,
        'ordre_priorite' => 1,
        'libelle_tranche' => 'Tranche Campus 1',
        'montant' => 70000,
        'obligatoire' => true,
        'date_limite' => now()->addMonths(3)->toDateString(),
    ]);

    // Création du 2ème campus + classe + tarif distinct (120 000 FCFA)
    $etab2 = Etablissement::create([
        'institution_id' => $institutionId,
        'code' => 'CAMP2-TR',
        'nom' => 'Campus Secondaire Transfert',
        'statut' => 'actif',
    ]);
    $sec2 = Section::create(['etablissement_id' => $etab2->id, 'code' => 'SEC-C2', 'nom' => 'Section C2']);
    $cyc2 = Cycle::create(['section_id' => $sec2->id, 'code' => 'CYC-C2', 'nom' => 'Cycle C2']);
    $niv2 = Niveau::create(['cycle_id' => $cyc2->id, 'code' => 'NIV-C2', 'nom' => 'Niveau C2', 'ordre' => 1]);
    $classe2 = Classe::create([
        'etablissement_id' => $etab2->id,
        'niveau_id' => $niv2->id,
        'code' => 'CLS-C2',
        'nom' => '6ème Campus 2',
        'capacite_max' => 45,
        'is_active' => true,
    ]);

    $typeFrais2 = TypeFrais::create([
        'etablissement_id' => $etab2->id,
        'code' => 'SCOL-CAMP2',
        'libelle' => 'Scolarité Campus 2',
        'obligatoire' => true,
        'periodicite' => 'annuel',
        'montant_defaut' => 120000,
    ]);

    FraisScolaire::create([
        'annee_scolaire_id' => $this->annee->id,
        'etablissement_id' => $etab2->id,
        'niveau_id' => $niv2->id,
        'type_frais_id' => $typeFrais2->id,
        'tranche_numero' => 1,
        'ordre_priorite' => 1,
        'libelle_tranche' => 'Tranche Campus 2',
        'montant' => 120000,
        'obligatoire' => true,
        'date_limite' => now()->addMonths(3)->toDateString(),
    ]);

    $eleve = Eleve::create([
        'matricule' => 'GS26-TR-002',
        'nom' => 'NGOSSO',
        'prenom' => 'Hélène',
        'sexe' => 'F',
    ]);

    $oldInscription = Inscription::create([
        'eleve_id' => $eleve->id,
        'classe_id' => $this->classe->id,
        'etablissement_id' => $etab1Id,
        'annee_scolaire_id' => $this->annee->id,
        'type_inscription' => 'nouveau',
        'statut' => 'validee',
        'date_inscription' => now()->toDateString(),
    ]);

    $financeService = app(FinanceService::class);
    $oldCompte = $financeService->initialiserCompteEleve($oldInscription);

    $caisse1 = Caisse::create([
        'etablissement_id' => $etab1Id,
        'code' => 'CAISSE-C1',
        'nom' => 'Caisse Campus 1',
        'solde_initial' => 0,
        'is_principale' => true,
        'is_active' => true,
    ]);

    VersementCaisse::create([
        'reference_recu' => 'REC-C1-0001',
        'compte_financier_id' => $oldCompte->id,
        'inscription_id' => $oldInscription->id,
        'etablissement_id' => $etab1Id,
        'caisse_id' => $caisse1->id,
        'date_versement' => now()->toDateString(),
        'montant_total' => 25000,
        'mode_paiement' => 'especes',
        'statut' => 'valide',
    ]);
    $oldCompte->actualiserSolde();

    actingAs($this->adminUser)
        ->post(route('administration.inscriptions.transferer'), [
            'inscription_ids' => [$oldInscription->id],
            'target_classe_id' => $classe2->id,
            'motif_transfert' => 'Changement de campus',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $oldInscription->refresh();
    $oldCompte->refresh();

    // L'ancien dossier est archivé en statut 'transfere' avec ses paiements intacts
    expect($oldInscription->statut)->toBe('transfere')
        ->and($oldCompte->debit_initial)->toBe(70000.0)
        ->and($oldCompte->total_paye)->toBe(25000.0)
        ->and($oldCompte->solde_restant)->toBe(45000.0);

    // Une nouvelle inscription avec un compte financier séparé est créée immédiatement au Campus 2
    $newInscription = Inscription::withoutGlobalScopes()
        ->where('eleve_id', $eleve->id)
        ->where('etablissement_id', $etab2->id)
        ->first();

    expect($newInscription)->not->toBeNull()
        ->and($newInscription->statut)->toBe('validee')
        ->and($newInscription->classe_id)->toBe($classe2->id);

    $newCompte = CompteFinancierEleve::withoutGlobalScopes()
        ->where('inscription_id', $newInscription->id)
        ->first();

    expect($newCompte)->not->toBeNull()
        ->and($newCompte->id)->not->toBe($oldCompte->id)
        ->and((int) $newCompte->etablissement_id)->toBe($etab2->id)
        ->and($newCompte->debit_initial)->toBe(120000.0)
        ->and($newCompte->total_paye)->toBe(0.0)
        ->and($newCompte->solde_restant)->toBe(120000.0);
});
