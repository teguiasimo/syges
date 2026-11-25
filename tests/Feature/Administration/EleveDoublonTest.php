<?php

use App\Models\AnneeScolaire;
use App\Models\Assiduite;
use App\Models\Attribution;
use App\Models\Caisse;
use App\Models\Classe;
use App\Models\Cycle;
use App\Models\Eleve;
use App\Models\EleveDoublonIgnore;
use App\Models\EleveFusionAudit;
use App\Models\Etablissement;
use App\Models\Evaluation;
use App\Models\FraisScolaire;
use App\Models\GroupeMatiere;
use App\Models\Inscription;
use App\Models\Institution;
use App\Models\Matiere;
use App\Models\Niveau;
use App\Models\Note;
use App\Models\Periode;
use App\Models\Personnel;
use App\Models\Section;
use App\Models\SouscriptionFraisOptionnel;
use App\Models\TypeFrais;
use App\Models\User;
use App\Models\VersementCaisse;
use App\Services\FinanceService;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

use function Pest\Laravel\actingAs;

/**
 * Tests de fonctionnalités pour la prévention, la détection et la fusion atomique des doublons élèves.
 */
beforeEach(function () {
    /** @var TestCase $this */
    Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);

    foreach (['eleves.view', 'eleves.create', 'eleves.edit', 'eleves.merge'] as $perm) {
        Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
    }

    $this->adminUser = User::factory()->create();
    $this->adminUser->assignRole('super-admin');

    $this->institution = Institution::firstOrCreate(
        ['code' => 'INST-DUP'],
        ['nom' => 'Institution Doublon Test', 'sigle' => 'IDT', 'telephone_1' => '677000000']
    );

    $this->etablissement = Etablissement::firstOrCreate(
        ['code' => 'ETAB-DUP'],
        [
            'institution_id' => $this->institution->id,
            'nom' => 'École Primaire Test Doublon',
            'statut_juridique' => 'Prive_Laic',
            'ordre_enseignement' => 'Primaire',
        ]
    );

    $this->section = Section::firstOrCreate(
        ['etablissement_id' => $this->etablissement->id, 'code' => 'SEC-DUP'],
        ['nom' => 'Section Francophone']
    );

    $this->cycle = Cycle::firstOrCreate(
        ['section_id' => $this->section->id, 'code' => 'CYC-DUP'],
        ['nom' => 'Cycle Primaire', 'ordre' => 1]
    );

    $this->niveau = Niveau::firstOrCreate(
        ['cycle_id' => $this->cycle->id, 'code' => 'NIV-DUP'],
        ['nom' => 'CM2', 'ordre' => 1]
    );

    $this->classe = Classe::firstOrCreate(
        ['niveau_id' => $this->niveau->id, 'code' => 'CM2-A-DUP'],
        [
            'etablissement_id' => $this->etablissement->id,
            'nom' => 'CM2 A',
            'capacite_max' => 50,
            'is_active' => true,
        ]
    );

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
});

test('un utilisateur sans permission eleves.merge ne peut pas acceder a la gestion des doublons', function () {
    /** @var TestCase $this */
    $standardUser = User::factory()->create();
    $standardUser->givePermissionTo(['eleves.view', 'eleves.edit']);

    actingAs($standardUser)
        ->get(route('administration.eleves.doublons.index'))
        ->assertForbidden();

    actingAs($standardUser)
        ->post(route('administration.eleves.doublons.merge'), [
            'master_id' => 1,
            'duplicate_id' => 2,
        ])
        ->assertForbidden();
});

test('la detection temps reel alerte en cas de nom proche dans la meme classe mais ignore les freres de noms differents', function () {
    /** @var TestCase $this */
    $eleve = Eleve::create([
        'matricule' => 'ETAB-DUP-2026-0001',
        'nom' => 'AFU WOUA',
        'prenom' => 'Emmanuel Messi',
        'sexe' => 'M',
        'date_naissance' => '2016-05-10',
        'telephone_parent' => '+237 677 11 22 33',
    ]);

    Inscription::create([
        'eleve_id' => $eleve->id,
        'classe_id' => $this->classe->id,
        'etablissement_id' => $this->etablissement->id,
        'annee_scolaire_id' => $this->annee->id,
        'type_inscription' => 'nouveau',
        'statut' => 'validee',
        'date_inscription' => now()->toDateString(),
    ]);

    // 1. Faute de frappe sur le prénom dans la même classe -> détecté
    $resSimilaire = actingAs($this->adminUser)
        ->postJson(route('administration.eleves.check-doublons'), [
            'nom' => 'AFU WOUA',
            'prenom' => 'Emmenuel Messi',
            'classe_id' => $this->classe->id,
            'annee_scolaire_id' => $this->annee->id,
        ])
        ->assertOk();

    expect($resSimilaire->json('matches'))->toHaveCount(1)
        ->and($resSimilaire->json('matches.0.matricule'))->toBe('ETAB-DUP-2026-0001')
        ->and($resSimilaire->json('matches.0.score'))->toBeGreaterThanOrEqual(80);

    // 2. Autre élève de nom complètement différent partageant uniquement le téléphone parent -> non signalé
    $resFrere = actingAs($this->adminUser)
        ->postJson(route('administration.eleves.check-doublons'), [
            'nom' => 'TALLA',
            'prenom' => 'Junior',
            'telephone_parent' => '677112233',
            'classe_id' => $this->classe->id,
            'annee_scolaire_id' => $this->annee->id,
        ])
        ->assertOk();

    expect($resFrere->json('matches'))->toHaveCount(0);
});

test('le scanner detecte une paire de doublons et permet de l ignorer comme jumeaux puis de la retablir', function () {
    /** @var TestCase $this */
    $el1 = Eleve::create([
        'matricule' => 'ETAB-DUP-2026-0010',
        'nom' => 'KAMDEM',
        'prenom' => 'Boris',
        'sexe' => 'M',
    ]);
    $el2 = Eleve::create([
        'matricule' => 'ETAB-DUP-2026-0011',
        'nom' => 'KAMDEM',
        'prenom' => 'Brice',
        'sexe' => 'M',
    ]);

    foreach ([$el1, $el2] as $el) {
        Inscription::create([
            'eleve_id' => $el->id,
            'classe_id' => $this->classe->id,
            'etablissement_id' => $this->etablissement->id,
            'annee_scolaire_id' => $this->annee->id,
            'type_inscription' => 'nouveau',
            'statut' => 'validee',
            'date_inscription' => now()->toDateString(),
        ]);
    }

    // 1. Paire détectée dans le scan différé (`Inertia::defer`)
    actingAs($this->adminUser)
        ->get(route('administration.eleves.doublons.index', ['annee_scolaire_id' => $this->annee->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('administration/eleves/doublons')
            ->missing('suspects')
            ->loadDeferredProps(fn ($reload) => $reload->has('suspects', 1))
        );

    // 2. Marquage comme jumeaux -> disparaît des suspects
    actingAs($this->adminUser)
        ->post(route('administration.eleves.doublons.ignore'), [
            'eleve_a_id' => $el1->id,
            'eleve_b_id' => $el2->id,
            'motif' => 'Jumeaux',
        ])
        ->assertRedirect();

    $ignore = EleveDoublonIgnore::firstOrFail();

    actingAs($this->adminUser)
        ->get(route('administration.eleves.doublons.index', ['annee_scolaire_id' => $this->annee->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('ignores', 1)
            ->loadDeferredProps(fn ($reload) => $reload->has('suspects', 0))
        );

    // 3. Rétablissement -> réapparaît dans le scan différé
    actingAs($this->adminUser)
        ->delete(route('administration.eleves.doublons.unignore', $ignore))
        ->assertRedirect();

    actingAs($this->adminUser)
        ->get(route('administration.eleves.doublons.index', ['annee_scolaire_id' => $this->annee->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->loadDeferredProps(fn ($reload) => $reload->has('suspects', 1))
        );
});

test('la fusion atomique transfere versements recus options dedupliquees notes avec arbitrage de conflit discipline audit et recherche par ancien matricule', function () {
    /** @var TestCase $this */
    // Configuration financière (Scolarité 85 000 F + Option Bus 35 000 F)
    $typeScol = TypeFrais::create([
        'etablissement_id' => $this->etablissement->id,
        'code' => 'SCOL-DUP',
        'libelle' => 'Scolarité Annuelle',
        'obligatoire' => true,
        'ordre_priorite' => 1,
        'periodicite' => 'annuel',
        'montant_defaut' => 85000,
    ]);
    FraisScolaire::create([
        'annee_scolaire_id' => $this->annee->id,
        'etablissement_id' => $this->etablissement->id,
        'niveau_id' => $this->niveau->id,
        'type_frais_id' => $typeScol->id,
        'tranche_numero' => 1,
        'ordre_priorite' => 1,
        'libelle_tranche' => 'Scolarité',
        'montant' => 85000,
        'obligatoire' => true,
        'date_limite' => Carbon::today()->addMonth()->toDateString(),
    ]);

    $typeBus = TypeFrais::create([
        'etablissement_id' => $this->etablissement->id,
        'code' => 'BUS-DUP',
        'libelle' => 'Frais Bus',
        'obligatoire' => false,
        'ordre_priorite' => 5,
        'periodicite' => 'annuel',
        'montant_defaut' => 35000,
    ]);
    $fraisBus = FraisScolaire::create([
        'annee_scolaire_id' => $this->annee->id,
        'etablissement_id' => $this->etablissement->id,
        'niveau_id' => $this->niveau->id,
        'type_frais_id' => $typeBus->id,
        'tranche_numero' => 1,
        'ordre_priorite' => 5,
        'libelle_tranche' => 'Frais Bus',
        'montant' => 35000,
        'obligatoire' => false,
        'date_limite' => Carbon::today()->addMonth()->toDateString(),
    ]);

    $caisse = Caisse::create([
        'etablissement_id' => $this->etablissement->id,
        'code' => 'CAISSE-DUP',
        'nom' => 'Caisse Principale',
        'solde_initial' => 0,
        'is_principale' => true,
        'is_active' => true,
    ]);

    // Création des deux fiches en doublon
    $master = Eleve::create([
        'matricule' => 'ESP-DUP-0001',
        'nom' => 'NGUEMEZI',
        'prenom' => 'Kiara',
        'sexe' => 'F',
        'telephone_parent' => null, // Vide sur le Maître -> sera auto-complété depuis le Doublon
    ]);
    $duplicate = Eleve::create([
        'matricule' => 'ESP-DUP-0099',
        'nom' => 'NGUEMEZI',
        'prenom' => 'Kiarra',
        'sexe' => 'F',
        'telephone_parent' => '699887766',
    ]);

    $masterInsc = Inscription::create([
        'eleve_id' => $master->id,
        'classe_id' => $this->classe->id,
        'etablissement_id' => $this->etablissement->id,
        'annee_scolaire_id' => $this->annee->id,
        'type_inscription' => 'nouveau',
        'statut' => 'validee',
        'date_inscription' => now()->toDateString(),
    ]);
    $dupInsc = Inscription::create([
        'eleve_id' => $duplicate->id,
        'classe_id' => $this->classe->id,
        'etablissement_id' => $this->etablissement->id,
        'annee_scolaire_id' => $this->annee->id,
        'type_inscription' => 'nouveau',
        'statut' => 'validee',
        'date_inscription' => now()->toDateString(),
    ]);

    $financeService = app(FinanceService::class);
    $masterCompte = $financeService->initialiserCompteEleve($masterInsc);
    $dupCompte = $financeService->initialiserCompteEleve($dupInsc);

    // Même option Bus souscrite sur les deux comptes -> doit être dédupliquée lors de la fusion
    SouscriptionFraisOptionnel::create([
        'compte_financier_id' => $masterCompte->id,
        'frais_scolaire_id' => $fraisBus->id,
        'montant' => 35000,
        'date_souscription' => now()->toDateString(),
        'statut' => 'actif',
    ]);
    SouscriptionFraisOptionnel::create([
        'compte_financier_id' => $dupCompte->id,
        'frais_scolaire_id' => $fraisBus->id,
        'montant' => 35000,
        'date_souscription' => now()->toDateString(),
        'statut' => 'actif',
    ]);

    // Versements distincts sur les deux fiches (30 000 F sur Maître + 25 000 F sur Doublon)
    VersementCaisse::create([
        'reference_recu' => 'REC-MASTER-01',
        'compte_financier_id' => $masterCompte->id,
        'inscription_id' => $masterInsc->id,
        'etablissement_id' => $this->etablissement->id,
        'caisse_id' => $caisse->id,
        'date_versement' => now()->toDateString(),
        'montant_total' => 30000,
        'mode_paiement' => 'especes',
        'statut' => 'valide',
    ]);
    VersementCaisse::create([
        'reference_recu' => 'REC-DUP-01',
        'compte_financier_id' => $dupCompte->id,
        'inscription_id' => $dupInsc->id,
        'etablissement_id' => $this->etablissement->id,
        'caisse_id' => $caisse->id,
        'date_versement' => now()->toDateString(),
        'montant_total' => 25000,
        'mode_paiement' => 'especes',
        'statut' => 'valide',
    ]);
    $masterCompte->actualiserSolde();
    $dupCompte->actualiserSolde();

    // Notes : 1 conflit sur Maths (14 vs 17) + 1 note sans conflit sur Anglais (15)
    $periode = Periode::create([
        'etablissement_id' => $this->etablissement->id,
        'annee_scolaire_id' => $this->annee->id,
        'nom' => 'Trimestre 1',
        'code' => 'T1-DUP',
        'ordre' => 1,
        'date_debut' => '2026-09-01',
        'date_fin' => '2026-12-15',
    ]);
    $evaluation = Evaluation::create([
        'periode_id' => $periode->id,
        'nom' => 'Évaluation 1',
        'code' => 'EV1-DUP',
        'ordre' => 1,
        'date_evaluation' => '2026-10-15',
    ]);
    $groupe = GroupeMatiere::create([
        'etablissement_id' => $this->etablissement->id,
        'code' => 'GRP-DUP',
        'nom' => 'Scientifiques',
        'ordre' => 1,
    ]);
    $matMaths = Matiere::create(['code' => 'MATH-DUP', 'nom' => 'Mathématiques']);
    $matAnglais = Matiere::create(['code' => 'ANG-DUP', 'nom' => 'Anglais']);
    $prof = Personnel::create([
        'etablissement_id' => $this->etablissement->id,
        'matricule' => 'PROF-DUP-01',
        'nom' => 'ENSEIGNANT',
        'prenom' => 'Test',
        'sexe' => 'M',
        'telephone' => '670000001',
        'fonction_principale' => 'Enseignant',
        'type_contrat' => 'Permanent',
        'date_embauche' => '2025-09-01',
        'statut' => 'Actif',
        'is_enseignant' => true,
        'volume_horaire_hebdo' => 18,
    ]);
    $attrMaths = Attribution::create([
        'etablissement_id' => $this->etablissement->id,
        'annee_scolaire_id' => $this->annee->id,
        'classe_id' => $this->classe->id,
        'matiere_id' => $matMaths->id,
        'personnel_id' => $prof->id,
        'groupe_matiere_id' => $groupe->id,
    ]);
    $attrAnglais = Attribution::create([
        'etablissement_id' => $this->etablissement->id,
        'annee_scolaire_id' => $this->annee->id,
        'classe_id' => $this->classe->id,
        'matiere_id' => $matAnglais->id,
        'personnel_id' => $prof->id,
        'groupe_matiere_id' => $groupe->id,
    ]);

    Note::create([
        'inscription_id' => $masterInsc->id,
        'attribution_id' => $attrMaths->id,
        'evaluation_id' => $evaluation->id,
        'valeur_note' => 14.0,
        'statut_presence' => 'present',
    ]);
    $dupMathsNote = Note::create([
        'inscription_id' => $dupInsc->id,
        'attribution_id' => $attrMaths->id,
        'evaluation_id' => $evaluation->id,
        'valeur_note' => 17.5,
        'statut_presence' => 'present',
    ]);
    Note::create([
        'inscription_id' => $dupInsc->id,
        'attribution_id' => $attrAnglais->id,
        'evaluation_id' => $evaluation->id,
        'valeur_note' => 15.0,
        'statut_presence' => 'present',
    ]);

    // Assiduité sur le doublon -> doit être transférée sur le Maître
    Assiduite::create([
        'inscription_id' => $dupInsc->id,
        'periode_id' => $periode->id,
        'date_evenement' => '2026-10-10',
        'type_evenement' => 'absence',
        'nombre_heures' => 2,
        'statut_justification' => 'non_justifiee',
    ]);

    // 1. Prévisualisation doit signaler exactement 1 conflit de note (Maths : 14 vs 17.5)
    $previewRes = actingAs($this->adminUser)
        ->getJson(route('administration.eleves.doublons.preview', [
            'master_id' => $master->id,
            'duplicate_id' => $duplicate->id,
        ]))
        ->assertOk();

    expect($previewRes->json('conflits_notes'))->toHaveCount(1)
        ->and($previewRes->json('conflits_notes.0.duplicate_note_id'))->toBe($dupMathsNote->id)
        ->and($previewRes->json('resume_transfert.nb_versements'))->toBe(1)
        ->and((float) $previewRes->json('resume_transfert.montant_versements'))->toBe(25000.0)
        ->and($previewRes->json('resume_transfert.nb_notes_sans_conflit'))->toBe(1);

    // 2. Exécution de la fusion en choisissant la note du Doublon (17.5) pour Maths
    actingAs($this->adminUser)
        ->post(route('administration.eleves.doublons.merge'), [
            'master_id' => $master->id,
            'duplicate_id' => $duplicate->id,
            'identity_choices' => [
                'prenom' => 'master',
                'telephone_parent' => 'duplicate',
            ],
            'note_resolutions' => [
                (string) $dupMathsNote->id => 'duplicate',
            ],
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    // 3. Vérifications post-fusion complètes
    expect(Eleve::find($duplicate->id))->toBeNull();

    $master->refresh();
    $masterCompte->refresh();
    $masterInsc->refresh();

    // Identité complétée
    expect($master->prenom)->toBe('Kiara')
        ->and($master->telephone_parent)->toBe('699887766');

    // Finances unifiées : 2 reçus intacts, 55 000 F versés, 1 seule option Bus (85k + 35k = 120k -> reste 65 000 F)
    expect(VersementCaisse::withoutGlobalScopes()->where('compte_financier_id', $masterCompte->id)->count())->toBe(2)
        ->and(VersementCaisse::withoutGlobalScopes()->where('reference_recu', 'REC-DUP-01')->first()->compte_financier_id)->toBe($masterCompte->id)
        ->and(SouscriptionFraisOptionnel::where('compte_financier_id', $masterCompte->id)->where('statut', 'actif')->count())->toBe(1)
        ->and((float) $masterCompte->montant_net_exigible)->toBe(120000.0)
        ->and((float) $masterCompte->total_paye)->toBe(55000.0)
        ->and((float) $masterCompte->solde_restant)->toBe(65000.0);

    // Notes : Maths arbitrée à 17.5 + Anglais rapatriée à 15.0
    $noteMathsFinale = Note::where('inscription_id', $masterInsc->id)->where('attribution_id', $attrMaths->id)->first();
    $noteAnglaisFinale = Note::where('inscription_id', $masterInsc->id)->where('attribution_id', $attrAnglais->id)->first();
    expect((float) $noteMathsFinale->valeur_note)->toBe(17.5)
        ->and((float) $noteAnglaisFinale->valeur_note)->toBe(15.0);

    // Assiduité transférée
    expect(Assiduite::where('inscription_id', $masterInsc->id)->count())->toBe(1);

    // Audit enregistré + observations renseignées + recherche par ancien matricule retrouve le Maître
    $audit = EleveFusionAudit::where('absorbed_matricule', 'ESP-DUP-0099')->first();
    expect($audit)->not->toBeNull()
        ->and($audit->master_eleve_id)->toBe($master->id)
        ->and((float) $audit->montant_versements_transferes)->toBe(25000.0)
        ->and($audit->nb_versements_transferes)->toBe(1)
        ->and($audit->nb_notes_transferees)->toBe(1)
        ->and($audit->nb_conflits_notes_resolus)->toBe(1)
        ->and($masterInsc->observations)->toContain('ESP-DUP-0099');

    $foundByOldMatricule = Eleve::search('ESP-DUP-0099')->first();
    expect($foundByOldMatricule)->not->toBeNull()
        ->and($foundByOldMatricule->id)->toBe($master->id);
});
