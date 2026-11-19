<?php

/**
 * Tests de fonctionnalités et de sécurité pour l'Accès Global Multi-Campus (`has_global_access`)
 * et le profil Administrateur Opérationnel Multi-Campus (`admin` + `has_global_access = true`).
 *
 * Vérifie notamment :
 * - L'attribution, la conservation et la révocation explicite de `has_global_access` par le Super-Admin via UserController.
 * - Le refus strict (403) si un non Super-Admin tente d'activer `has_global_access`.
 * - Le refus de basculer en Accès Global un compte rattaché à une fiche Personnel mono-campus.
 * - La protection contre la fuite de portée lors de `User::create()` quand des personnels non liés existent (`user_id = null`).
 * - La préservation du cloisonnement hermétique SaaS (`1 = 0`) pour un compte local orphelin (`etablissement_id = null` sans `has_global_access`).
 * - Les droits réels d'un compte `admin` avec Accès Global sur deux campus (basculement, encaissement, notes, dépenses)
 *   ainsi que les blocages stricts demandés (gestion utilisateurs, configuration/tarifs de frais, annulation de versements, suppressions, rôles).
 */

use App\Models\AnneeScolaire;
use App\Models\Attribution;
use App\Models\Caisse;
use App\Models\CategorieDepense;
use App\Models\Classe;
use App\Models\CompteFinancierEleve;
use App\Models\Cycle;
use App\Models\Depense;
use App\Models\Eleve;
use App\Models\Etablissement;
use App\Models\Evaluation;
use App\Models\FraisScolaire;
use App\Models\GroupeMatiere;
use App\Models\Inscription;
use App\Models\Institution;
use App\Models\Matiere;
use App\Models\Niveau;
use App\Models\Periode;
use App\Models\Personnel;
use App\Models\Section;
use App\Models\TypeFrais;
use App\Models\User;
use App\Models\VersementCaisse;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    // Exécution du seeder officiel afin de tester exactement la matrice réelle du rôle `admin`
    $this->seed(DatabaseSeeder::class);

    $this->superAdmin = User::where('login', 'superadmin')->firstOrFail();

    $this->institution = Institution::create([
        'code' => 'INST-GLOBAL',
        'sigle' => 'IG',
        'nom' => 'Complexe Scolaire Multi-Campus Test',
    ]);

    $this->etab1 = Etablissement::create([
        'institution_id' => $this->institution->id,
        'code' => 'CAMPUS-1',
        'nom' => 'Campus Maternelle Test',
        'statut_juridique' => 'Prive_Laic',
        'ordre_enseignement' => 'Maternelle',
    ]);

    $this->etab2 = Etablissement::create([
        'institution_id' => $this->institution->id,
        'code' => 'CAMPUS-2',
        'nom' => 'Campus Primaire Test',
        'statut_juridique' => 'Prive_Laic',
        'ordre_enseignement' => 'Primaire',
    ]);

    $this->annee = AnneeScolaire::create([
        'code' => '2026-2027',
        'nom' => 'Année 2026-2027',
        'date_debut' => '2026-09-01',
        'date_fin' => '2027-06-30',
        'is_current' => true,
    ]);

    // Structure académique Campus 1
    $section1 = Section::create(['etablissement_id' => $this->etab1->id, 'nom' => 'Francophone C1', 'code' => 'FR1', 'langue' => 'fr']);
    $cycle1 = Cycle::create(['etablissement_id' => $this->etab1->id, 'section_id' => $section1->id, 'nom' => 'Maternelle C1', 'code' => 'MAT1', 'ordre' => 1]);
    $niveau1 = Niveau::create(['etablissement_id' => $this->etab1->id, 'cycle_id' => $cycle1->id, 'nom' => 'Grande Section', 'code' => 'GS1', 'ordre' => 1]);
    $this->classe1 = Classe::create(['etablissement_id' => $this->etab1->id, 'niveau_id' => $niveau1->id, 'nom' => 'GS A', 'code' => 'GSA1']);

    // Structure académique Campus 2
    $section2 = Section::create(['etablissement_id' => $this->etab2->id, 'nom' => 'Francophone C2', 'code' => 'FR2', 'langue' => 'fr']);
    $cycle2 = Cycle::create(['etablissement_id' => $this->etab2->id, 'section_id' => $section2->id, 'nom' => 'Primaire C2', 'code' => 'PRI2', 'ordre' => 1]);
    $niveau2 = Niveau::create(['etablissement_id' => $this->etab2->id, 'cycle_id' => $cycle2->id, 'nom' => 'CM2', 'code' => 'CM2', 'ordre' => 6]);
    $this->classe2 = Classe::create(['etablissement_id' => $this->etab2->id, 'niveau_id' => $niveau2->id, 'nom' => 'CM2 A', 'code' => 'CM2A']);

    // Périodes & Évaluations pour chaque campus
    $periode1 = Periode::create([
        'etablissement_id' => $this->etab1->id,
        'annee_scolaire_id' => $this->annee->id,
        'code' => 'T1-C1',
        'nom' => 'Trimestre 1 C1',
        'ordre' => 1,
        'date_debut' => '2026-09-01',
        'date_fin' => '2026-12-15',
    ]);
    $this->eval1 = Evaluation::create([
        'periode_id' => $periode1->id,
        'code' => 'EV1-C1',
        'nom' => 'Éval 1 C1',
        'ordre' => 1,
        'ponderation' => 100,
        'statut' => 'ouverte',
        'is_locked' => false,
    ]);

    $periode2 = Periode::create([
        'etablissement_id' => $this->etab2->id,
        'annee_scolaire_id' => $this->annee->id,
        'code' => 'T1-C2',
        'nom' => 'Trimestre 1 C2',
        'ordre' => 1,
        'date_debut' => '2026-09-01',
        'date_fin' => '2026-12-15',
    ]);
    $this->eval2 = Evaluation::create([
        'periode_id' => $periode2->id,
        'code' => 'EV1-C2',
        'nom' => 'Éval 1 C2',
        'ordre' => 1,
        'ponderation' => 100,
        'statut' => 'ouverte',
        'is_locked' => false,
    ]);

    // Élèves, Inscriptions & Comptes Financiers
    $this->eleve1 = Eleve::create([
        'etablissement_id' => $this->etab1->id,
        'matricule' => 'ELV-C1-001',
        'nom' => 'ABENA',
        'prenom' => 'Léna',
        'sexe' => 'F',
    ]);
    $this->inscription1 = Inscription::create([
        'etablissement_id' => $this->etab1->id,
        'eleve_id' => $this->eleve1->id,
        'classe_id' => $this->classe1->id,
        'annee_scolaire_id' => $this->annee->id,
        'date_inscription' => '2026-09-01',
        'statut' => 'validee',
    ]);
    $this->compte1 = CompteFinancierEleve::create([
        'etablissement_id' => $this->etab1->id,
        'inscription_id' => $this->inscription1->id,
        'eleve_id' => $this->eleve1->id,
        'annee_scolaire_id' => $this->annee->id,
        'debit_initial' => 50000,
        'montant_net_exigible' => 50000,
        'total_paye' => 0,
        'solde_restant' => 50000,
        'statut_financier' => 'insolvable',
    ]);

    $this->eleve2 = Eleve::create([
        'etablissement_id' => $this->etab2->id,
        'matricule' => 'ELV-C2-001',
        'nom' => 'MBARGA',
        'prenom' => 'Paul',
        'sexe' => 'M',
    ]);
    $this->inscription2 = Inscription::create([
        'etablissement_id' => $this->etab2->id,
        'eleve_id' => $this->eleve2->id,
        'classe_id' => $this->classe2->id,
        'annee_scolaire_id' => $this->annee->id,
        'date_inscription' => '2026-09-01',
        'statut' => 'validee',
    ]);
    $this->compte2 = CompteFinancierEleve::create([
        'etablissement_id' => $this->etab2->id,
        'inscription_id' => $this->inscription2->id,
        'eleve_id' => $this->eleve2->id,
        'annee_scolaire_id' => $this->annee->id,
        'debit_initial' => 60000,
        'montant_net_exigible' => 60000,
        'total_paye' => 0,
        'solde_restant' => 60000,
        'statut_financier' => 'insolvable',
    ]);

    // Types de frais & Attributions pédagogiques (notes)
    $this->typeFrais1 = TypeFrais::create([
        'etablissement_id' => $this->etab1->id,
        'code' => 'SCOL-C1',
        'libelle' => 'Pension Maternelle',
        'obligatoire' => true,
        'periodicite' => 'annuel',
        'montant_defaut' => 50000,
    ]);
    $this->frais1 = FraisScolaire::create([
        'etablissement_id' => $this->etab1->id,
        'annee_scolaire_id' => $this->annee->id,
        'niveau_id' => $niveau1->id,
        'type_frais_id' => $this->typeFrais1->id,
        'tranche_numero' => 1,
        'libelle_tranche' => 'Tranche 1 C1',
        'montant' => 50000,
    ]);

    $this->typeFrais2 = TypeFrais::create([
        'etablissement_id' => $this->etab2->id,
        'code' => 'SCOL-C2',
        'libelle' => 'Pension Primaire',
        'obligatoire' => true,
        'periodicite' => 'annuel',
        'montant_defaut' => 60000,
    ]);

    // Personnel enseignant non lié à un user (user_id = null) pour tester également l'absence de capture dans User::booted()
    $this->enseignantC1 = Personnel::create([
        'etablissement_id' => $this->etab1->id,
        'matricule' => 'ENS-C1-01',
        'nom' => 'NGONO',
        'prenom' => 'Claire',
        'sexe' => 'F',
        'telephone' => '699000001',
        'fonction_principale' => 'Maîtresse',
        'type_contrat' => 'Permanent',
        'date_embauche' => '2026-09-01',
        'statut' => 'Actif',
        'is_enseignant' => true,
    ]);
    $this->enseignantC2 = Personnel::create([
        'etablissement_id' => $this->etab2->id,
        'matricule' => 'ENS-C2-01',
        'nom' => 'FOUDA',
        'prenom' => 'Marc',
        'sexe' => 'M',
        'telephone' => '699000002',
        'fonction_principale' => 'Maître',
        'type_contrat' => 'Permanent',
        'date_embauche' => '2026-09-01',
        'statut' => 'Actif',
        'is_enseignant' => true,
    ]);

    $mat1 = Matiere::create(['etablissement_id' => $this->etab1->id, 'code' => 'EVEIL', 'nom' => 'Éveil Sensoriel']);
    $mat2 = Matiere::create(['etablissement_id' => $this->etab2->id, 'code' => 'MATHP', 'nom' => 'Mathématiques Primaire']);

    $this->attribution1 = Attribution::create([
        'annee_scolaire_id' => $this->annee->id,
        'classe_id' => $this->classe1->id,
        'matiere_id' => $mat1->id,
        'personnel_id' => $this->enseignantC1->id,
        'volume_horaire_reel' => 4,
        'is_active' => true,
    ]);
    $this->attribution2 = Attribution::create([
        'annee_scolaire_id' => $this->annee->id,
        'classe_id' => $this->classe2->id,
        'matiere_id' => $mat2->id,
        'personnel_id' => $this->enseignantC2->id,
        'volume_horaire_reel' => 6,
        'is_active' => true,
    ]);
});

test('super admin can grant and revoke explicit global access when creating and updating an admin user', function () {
    // 1. Création d'un compte admin avec Accès Global explicite (même si un personnel avec user_id = null existe)
    $createResponse = $this->actingAs($this->superAdmin)->post(route('administration.users.store'), [
        'name' => 'Secrétaire Complexe',
        'login' => 'secretaire.global',
        'email' => 'secretaire.global@syges.test',
        'password' => 'Secret123!',
        'password_confirmation' => 'Secret123!',
        'etablissement_id' => null,
        'has_global_access' => true,
        'roles' => ['admin'],
    ]);

    $createResponse->assertRedirect(route('administration.users.index'));
    $user = User::where('login', 'secretaire.global')->firstOrFail();

    expect($user->etablissement_id)->toBeNull()
        ->and($user->has_global_access)->toBeTrue()
        ->and($user->canAccessAllEtablissements())->toBeTrue();

    // 2. Révocation vers un campus unique via update
    $this->actingAs($this->superAdmin)->put(route('administration.users.update', $user), [
        'name' => 'Secrétaire Campus 2',
        'login' => 'secretaire.global',
        'email' => 'secretaire.global@syges.test',
        'etablissement_id' => $this->etab2->id,
        'has_global_access' => false,
        'roles' => ['admin'],
    ])->assertRedirect(route('administration.users.index'));

    $user->refresh();
    expect($user->etablissement_id)->toBe($this->etab2->id)
        ->and($user->has_global_access)->toBeFalse()
        ->and($user->canAccessAllEtablissements())->toBeFalse();

    // 3. Ré-attribution de l'Accès Global (sans personnel rattaché)
    $this->actingAs($this->superAdmin)->put(route('administration.users.update', $user), [
        'name' => 'Secrétaire Multi-Campus',
        'login' => 'secretaire.global',
        'email' => 'secretaire.global@syges.test',
        'etablissement_id' => null,
        'has_global_access' => true,
        'roles' => ['admin'],
    ])->assertRedirect(route('administration.users.index'));

    $user->refresh();
    expect($user->etablissement_id)->toBeNull()
        ->and($user->has_global_access)->toBeTrue()
        ->and($user->canAccessAllEtablissements())->toBeTrue();
});

test('non super admin cannot grant global access and personnel bound user cannot become global', function () {
    $localManager = User::factory()->create([
        'etablissement_id' => $this->etab1->id,
        'has_global_access' => false,
    ]);
    $localManager->givePermissionTo(['users.view', 'users.create', 'users.edit']);

    $this->actingAs($localManager)->post(route('administration.users.store'), [
        'name' => 'Tentative Escapade',
        'login' => 'tentative.escapade',
        'email' => 'escapade@syges.test',
        'password' => 'Secret123!',
        'password_confirmation' => 'Secret123!',
        'etablissement_id' => null,
        'has_global_access' => true,
        'roles' => ['admin'],
    ])->assertForbidden();

    // Un compte rattaché à une fiche Personnel du campus 1 ne peut pas basculer en Accès Global
    $userWithPersonnel = User::factory()->create([
        'login' => 'ens.campus1',
        'etablissement_id' => $this->etab1->id,
        'has_global_access' => false,
    ]);
    $userWithPersonnel->assignRole('admin');
    $this->enseignantC1->update(['user_id' => $userWithPersonnel->id]);

    $response = $this->actingAs($this->superAdmin)->put(route('administration.users.update', $userWithPersonnel), [
        'name' => 'Enseignant Campus 1',
        'login' => 'ens.campus1',
        'email' => $userWithPersonnel->email,
        'etablissement_id' => null,
        'has_global_access' => true,
        'roles' => ['admin'],
    ]);

    $response->assertSessionHasErrors('etablissement_id');
    expect($userWithPersonnel->fresh()->has_global_access)->toBeFalse()
        ->and($userWithPersonnel->fresh()->etablissement_id)->toBe($this->etab1->id);
});

test('broken admin without etablissement_id and without has_global_access remains isolated', function () {
    $brokenUser = User::factory()->create([
        'etablissement_id' => null,
        'has_global_access' => false,
    ]);
    $brokenUser->assignRole('admin');

    expect($brokenUser->canAccessAllEtablissements())->toBeFalse();

    $this->actingAs($brokenUser);
    expect(Classe::count())->toBe(0)
        ->and(Inscription::count())->toBe(0)
        ->and(CompteFinancierEleve::count())->toBe(0);
});

test('global admin can manage daily operations across both campuses while restricted super admin actions remain forbidden', function () {
    $secretaireAdmin = User::factory()->create([
        'name' => 'Secrétaire ESPOIR Test',
        'etablissement_id' => null,
        'has_global_access' => true,
        'password' => Hash::make('Secret123!'),
    ]);
    $secretaireAdmin->assignRole('admin');

    expect($secretaireAdmin->canAccessAllEtablissements())->toBeTrue();

    // 1. Basculement entre Campus 1, Campus 2 et Vue Consolidée "all"
    $this->actingAs($secretaireAdmin)
        ->post('/switch-etablissement', ['etablissement_id' => $this->etab1->id])
        ->assertSessionHas('current_etablissement_id', $this->etab1->id)
        ->assertSessionHas('current_etablissement_scope', 'single');

    $this->actingAs($secretaireAdmin)
        ->post('/switch-etablissement', ['etablissement_id' => $this->etab2->id])
        ->assertSessionHas('current_etablissement_id', $this->etab2->id)
        ->assertSessionHas('current_etablissement_scope', 'single');

    $this->actingAs($secretaireAdmin)
        ->post('/switch-etablissement', ['etablissement_id' => 'all'])
        ->assertSessionHas('current_etablissement_scope', 'all');

    // 2. Consultation des finances et encaissement de versements sur Campus 1 ET Campus 2
    $this->actingAs($secretaireAdmin)
        ->get(route('administration.finances.index'))
        ->assertOk();

    $this->actingAs($secretaireAdmin)
        ->post(route('administration.finances.versements.store'), [
            'compte_financier_id' => $this->compte1->id,
            'montant_total' => 25000,
            'mode_paiement' => 'especes',
            'ventilations' => [
                [
                    'libelle' => 'Avance Tranche 1 C1',
                    'montant' => 25000,
                    'type_frais_id' => $this->typeFrais1->id,
                    'frais_scolaire_id' => $this->frais1->id,
                ],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->actingAs($secretaireAdmin)
        ->post(route('administration.finances.versements.store'), [
            'compte_financier_id' => $this->compte2->id,
            'montant_total' => 30000,
            'mode_paiement' => 'especes',
            'ventilations' => [
                [
                    'libelle' => 'Avance Pension C2',
                    'montant' => 30000,
                    'type_frais_id' => $this->typeFrais2->id,
                ],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $versementC1 = VersementCaisse::withoutGlobalScopes()->where('compte_financier_id', $this->compte1->id)->firstOrFail();

    // 3. Saisie des notes sur les deux campus sans être rattachée à une fiche Personnel enseignant
    $this->actingAs($secretaireAdmin)
        ->getJson(route('administration.notes.grille', [
            'attribution_id' => $this->attribution1->id,
            'evaluation_id' => $this->eval1->id,
        ]))
        ->assertOk()
        ->assertJsonCount(1, 'grille');

    $this->actingAs($secretaireAdmin)
        ->postJson(route('administration.notes.sauvegarder'), [
            'attribution_id' => $this->attribution2->id,
            'evaluation_id' => $this->eval2->id,
            'notes' => [
                [
                    'inscription_id' => $this->inscription2->id,
                    'valeur_note' => 16.5,
                    'statut_presence' => 'present',
                ],
            ],
        ])
        ->assertOk();

    // 4. Restrictions strictes demandées :
    // 4a. Gestion des utilisateurs interdite (users.*)
    $this->actingAs($secretaireAdmin)->get(route('administration.users.index'))->assertForbidden();
    $this->actingAs($secretaireAdmin)->post(route('administration.users.store'), [])->assertForbidden();
    $this->actingAs($secretaireAdmin)->put(route('administration.users.update', $this->superAdmin), [])->assertForbidden();

    // 4b. Configuration / modification de frais et tarifs interdite (frais.configure & finances.manage exclus)
    $this->actingAs($secretaireAdmin)->post(route('administration.finances.types-frais.store'), [
        'etablissement_id' => $this->etab1->id,
        'code' => 'NEW-FRAIS',
        'libelle' => 'Nouveau Frais',
    ])->assertForbidden();

    $this->actingAs($secretaireAdmin)->post(route('administration.finances.tarifs.store'), [
        'etablissement_id' => $this->etab1->id,
        'type_frais_id' => $this->typeFrais1->id,
        'montant' => 90000,
    ])->assertForbidden();

    $this->actingAs($secretaireAdmin)->put(route('administration.finances.tarifs.update', $this->frais1), [
        'montant' => 90000,
    ])->assertForbidden();

    // 4c. Annulation de versement interdite (versements.cancel & finances.annuler exclus)
    $this->actingAs($secretaireAdmin)->post(route('administration.finances.versements.annuler', $versementC1), [
        'motif_annulation' => 'Tentative annulation non autorisée',
    ])->assertForbidden();

    // 4d. Suppressions destructrices & gouvernance système interdites
    $this->actingAs($secretaireAdmin)->get(route('administration.roles.index'))->assertForbidden();
    $this->actingAs($secretaireAdmin)->delete(route('administration.eleves.destroy', $this->eleve1))->assertForbidden();

    // Vérifie qu'une dépense existante ne peut pas être supprimée par le profil admin global
    $caisse = Caisse::create([
        'etablissement_id' => $this->etab1->id,
        'code' => 'CAISSE-C1',
        'nom' => 'Caisse Principale C1',
        'solde_initial' => 0,
        'is_principale' => true,
        'is_active' => true,
    ]);
    $categorieDepense = CategorieDepense::create([
        'etablissement_id' => $this->etab1->id,
        'code' => 'CAT-FOURN',
        'nom' => 'Fournitures de bureau',
        'is_active' => true,
    ]);
    $depense = Depense::create([
        'etablissement_id' => $this->etab1->id,
        'annee_scolaire_id' => $this->annee->id,
        'caisse_id' => $caisse->id,
        'categorie_depense_id' => $categorieDepense->id,
        'numero_piece' => 'DEP-TEST-01',
        'beneficiaire' => 'Librairie Centrale',
        'montant' => 10000,
        'date_depense' => '2026-09-10',
        'motif' => 'Achat fournitures bureau',
        'mode_reglement' => 'especes',
        'statut' => 'validee',
        'created_by' => $this->superAdmin->id,
    ]);
    $this->actingAs($secretaireAdmin)->delete(route('administration.finances.depenses.destroy', $depense))->assertForbidden();
});
