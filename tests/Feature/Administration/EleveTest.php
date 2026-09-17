<?php

/** @var \Tests\TestCase $this */

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\Etablissement;
use App\Models\Inscription;
use App\Models\Institution;
use App\Models\Niveau;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patch;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

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
    /** @var \Tests\TestCase $this */
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
        $sec = \App\Models\Section::firstOrCreate(
            ['etablissement_id' => $etab->id, 'code' => 'FR-ELEVE'],
            ['nom' => 'Section FR']
        );
        $cyc = \App\Models\Cycle::firstOrCreate(
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
    /** @var \Tests\TestCase $this */
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
    /** @var \Tests\TestCase $this */
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
        'groupe_sanguin' => 'O+',
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
    /** @var \Tests\TestCase $this */
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
    /** @var \Tests\TestCase $this */
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
        'groupe_sanguin' => 'B+',
    ];

    actingAs($this->adminUser)
        ->put(route('administration.eleves.update', $eleve), $updateData)
        ->assertRedirect();

    $eleve->refresh();
    expect($eleve->nom)->toBe('ONANA MODIFIE');
    expect($eleve->telephone_parent)->toBe('+237 699 11 11 11');
    expect($eleve->groupe_sanguin)->toBe('B+');
});

test('on peut modifier le statut d une inscription en suspendue ou exclu', function () {
    /** @var \Tests\TestCase $this */
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
    /** @var \Tests\TestCase $this */
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
    /** @var \Tests\TestCase $this */
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
    /** @var \Tests\TestCase $this */
    // Année précédente
    $anneePrecedente = \App\Models\AnneeScolaire::create([
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
    \App\Models\DeliberationConseil::create([
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
    /** @var \Tests\TestCase $this */
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
    /** @var \Tests\TestCase $this */
    $csvContent = "Matricule\tMatrNat\tNom\tPrenom\tSexe\tDateNaiss\tLieu\tRedoub\tTel\tAdresse\tTuteur\tNat\n"
        . "IMP-001\t\tEBODE\tChrist\tM\t12/11/2010\tYaounde\tN\t+237 699112233\tBastos\tEBODE Père\tCAMEROUNAISE\n"
        . "\t\tNGOULA\tDiane\tF\t05/04/2011\tDouala\tN\t+237 677223344\tBonanjo\tNGOULA Tuteur\tCAMEROUNAISE\n";

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
    /** @var \Tests\TestCase $this */
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
    /** @var \Tests\TestCase $this */
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

