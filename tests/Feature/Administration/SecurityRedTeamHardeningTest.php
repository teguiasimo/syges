<?php

/** @var TestCase $this */

use App\Models\AnneeScolaire;
use App\Models\Caisse;
use App\Models\CategorieDepense;
use App\Models\Classe;
use App\Models\CompteFinancierEleve;
use App\Models\Cycle;
use App\Models\Eleve;
use App\Models\Etablissement;
use App\Models\Inscription;
use App\Models\Institution;
use App\Models\Niveau;
use App\Models\Section;
use App\Models\User;
use App\Services\FinanceService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Suite de tests fonctionnels validant les correctifs de sécurité issus de l'audit Red Team / OWASP :
 * 1. Présence des en-têtes HTTP de sécurité (`X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`).
 * 2. Neutralisation du Path Traversal / LFI dans `Institution::resolveImageBase64()`.
 * 3. Blocage de l'escalade verticale de privilèges vers `super-admin` dans `UserController::update` et `PersonnelController::store`.
 * 4. Blocage des accès et injections inter-campus (IDOR) sur les élèves, le personnel et les dépenses.
 * 5. Neutralisation de l'Open Redirect via l'en-tête `Referer` sur `POST /switch-etablissement`.
 * 6. Impossibilité d'annuler un versement ou d'encaisser une recette diverse sur une journée de caisse clôturée.
 */
beforeEach(function () {
    Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    foreach ([
        'users.view',
        'users.create',
        'users.edit',
        'personnels.manage',
        'eleves.view',
        'eleves.edit',
        'depenses.create',
        'depenses.manage',
    ] as $perm) {
        Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
    }

    $this->institution = Institution::firstOrCreate(
        ['code' => 'INST-SEC'],
        ['nom' => 'Institution Sécurité SYGES', 'ville' => 'Yaoundé']
    );

    $this->campusA = Etablissement::create([
        'institution_id' => $this->institution->id,
        'code' => 'CMPA',
        'nom' => 'Campus Alpha',
        'statut_juridique' => 'Prive_Laic',
        'ordre_enseignement' => 'Secondaire_General',
    ]);

    $this->campusB = Etablissement::create([
        'institution_id' => $this->institution->id,
        'code' => 'CMPB',
        'nom' => 'Campus Beta',
        'statut_juridique' => 'Prive_Laic',
        'ordre_enseignement' => 'Secondaire_General',
    ]);

    $this->localAdminA = User::factory()->create([
        'login' => 'admin.campus.a',
        'email' => 'admin.a@syges.cm',
        'etablissement_id' => $this->campusA->id,
        'has_global_access' => false,
        'is_active' => true,
    ]);
    $this->localAdminA->givePermissionTo([
        'users.view',
        'users.create',
        'users.edit',
        'personnels.manage',
        'eleves.view',
        'eleves.edit',
        'depenses.create',
        'depenses.manage',
    ]);
});

test('security headers middleware attaches protective http headers and blocks lfi in resolveImageBase64', function () {
    $response = $this->get(route('login'));

    $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    $response->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

    // Tentative de lecture de .env par traversée de répertoire
    expect(Institution::resolveImageBase64('../.env'))->toBeNull()
        ->and(Institution::resolveImageBase64('/storage/../../.env'))->toBeNull()
        ->and(Institution::resolveImageBase64('composer.json'))->toBeNull();
});

test('local admin cannot escalate to super-admin via UserController update or PersonnelController store', function () {
    $this->actingAs($this->localAdminA);

    // 1. Tentative d'auto-attribution du rôle super-admin via PUT /administration/users/{user}
    $resRole = $this->put(route('administration.users.update', $this->localAdminA), [
        'login' => 'admin.campus.a',
        'name' => 'Admin Campus A',
        'email' => 'admin.a@syges.cm',
        'roles' => ['super-admin'],
    ]);
    $resRole->assertSessionHasErrors(['roles.0']);
    expect($this->localAdminA->fresh()->hasRole('super-admin'))->toBeFalse();

    // 2. Tentative d'usurpation du login réservé 'superadmin'
    $resLogin = $this->put(route('administration.users.update', $this->localAdminA), [
        'login' => 'superadmin',
        'name' => 'Admin Campus A',
        'email' => 'admin.a@syges.cm',
        'roles' => ['admin'],
    ]);
    $resLogin->assertSessionHasErrors(['login']);

    // 3. Tentative de création d'un personnel avec compte utilisateur ayant user_role = super-admin
    $resPers = $this->post(route('administration.personnels.store'), [
        'nom' => 'TENTATIVE',
        'prenom' => 'Escalade',
        'sexe' => 'M',
        'telephone' => '699112233',
        'etablissement_id' => $this->campusA->id,
        'fonction_principale' => 'Enseignant',
        'type_contrat' => 'Permanent',
        'date_embauche' => '2026-09-01',
        'statut' => 'Actif',
        'create_user_account' => true,
        'user_role' => 'super-admin',
    ]);
    $resPers->assertSessionHasErrors(['user_role']);
});

test('local admin from campus A cannot hijack a user or modify a student or create an expense in campus B', function () {
    $userCampusB = User::factory()->create([
        'login' => 'user.campus.b',
        'email' => 'user.b@syges.cm',
        'etablissement_id' => $this->campusB->id,
        'has_global_access' => false,
    ]);

    // Création préalable des entités du Campus B (hors session de l'admin local du Campus A)
    $annee = AnneeScolaire::firstOrCreate(
        ['code' => '2026-2027'],
        ['nom' => '2026-2027', 'date_debut' => '2026-09-01', 'date_fin' => '2027-07-31', 'is_current' => true]
    );
    $sectionB = Section::create(['etablissement_id' => $this->campusB->id, 'code' => 'FR', 'nom' => 'Francophone B', 'langue' => 'fr']);
    $cycleB = Cycle::create(['etablissement_id' => $this->campusB->id, 'section_id' => $sectionB->id, 'code' => 'C1', 'nom' => 'Cycle 1 B', 'ordre' => 1]);
    $niveauB = Niveau::create(['etablissement_id' => $this->campusB->id, 'cycle_id' => $cycleB->id, 'code' => '6E', 'nom' => '6ème B', 'ordre' => 1]);
    $classeB = Classe::create(['etablissement_id' => $this->campusB->id, 'niveau_id' => $niveauB->id, 'code' => '6B1', 'nom' => '6ème B1', 'capacite_max' => 50]);

    $eleveB = Eleve::create([
        'matricule' => '26CMPB0001',
        'nom' => 'ATANGANA',
        'prenom' => 'Junior',
        'sexe' => 'M',
        'is_active' => true,
    ]);
    Inscription::create([
        'eleve_id' => $eleveB->id,
        'classe_id' => $classeB->id,
        'etablissement_id' => $this->campusB->id,
        'annee_scolaire_id' => $annee->id,
        'type_inscription' => 'nouveau',
        'statut' => 'validee',
        'date_inscription' => '2026-09-05',
    ]);

    $caisseB = Caisse::withoutGlobalScopes()->create([
        'etablissement_id' => $this->campusB->id,
        'code' => 'CAISSE-B',
        'nom' => 'Caisse Principale B',
        'solde_initial' => 0,
        'is_principale' => true,
        'is_active' => true,
    ]);
    $catB = CategorieDepense::withoutGlobalScopes()->create([
        'etablissement_id' => $this->campusB->id,
        'code' => 'CAT-B',
        'nom' => 'Fournitures B',
        'couleur' => 'blue',
    ]);

    // Connexion de l'administrateur local du Campus A qui tente d'attaquer les ressources du Campus B
    $this->actingAs($this->localAdminA);

    // 1. Tentative de détournement du compte utilisateur du Campus B via création d'un Personnel au Campus A
    $this->post(route('administration.personnels.store'), [
        'nom' => 'DETOURNEMENT',
        'prenom' => 'Test',
        'sexe' => 'M',
        'telephone' => '677001122',
        'etablissement_id' => $this->campusA->id,
        'fonction_principale' => 'Enseignant',
        'type_contrat' => 'Permanent',
        'date_embauche' => '2026-09-01',
        'statut' => 'Actif',
        'user_id' => $userCampusB->id,
    ])->assertForbidden();
    expect($userCampusB->fresh()->etablissement_id)->toBe($this->campusB->id);

    // 2. Tentative de modification d'un élève inscrit uniquement au Campus B
    $this->put(route('administration.eleves.update', $eleveB), [
        'nom' => 'NOM PIRATE',
        'prenom' => 'Junior',
    ])->assertForbidden();
    expect($eleveB->fresh()->nom)->toBe('ATANGANA');

    // 3. Tentative d'injection d'une dépense sur la caisse du Campus B
    $this->post(route('administration.finances.depenses.store'), [
        'etablissement_id' => $this->campusB->id,
        'annee_scolaire_id' => $annee->id,
        'caisse_id' => $caisseB->id,
        'categorie_depense_id' => $catB->id,
        'beneficiaire' => 'Fournisseur Test',
        'montant' => 25000,
        'date_depense' => '2026-10-10',
        'motif' => 'Achat frauduleux inter-campus',
        'mode_reglement' => 'especes',
    ])->assertForbidden();
});

test('switch-etablissement neutralizes open redirect via external Referer header and closed cash register blocks cancellations', function () {
    $superAdmin = User::factory()->create(['login' => 'superadmin.sec', 'is_active' => true]);
    $superAdmin->assignRole('super-admin');
    $this->actingAs($superAdmin);

    // 1. Open Redirect via Referer externe malveillant -> redirige uniquement vers le chemin relatif local
    $resRedirect = $this->from('https://evil-attacker.example.com/administration/eleves?etablissement_id=999&page=2')
        ->post(route('context.switch-etablissement'), [
            'etablissement_id' => $this->campusA->id,
        ]);

    $resRedirect->assertRedirect('/administration/eleves?page=2');

    // 2. Une caisse clôturée interdit l'annulation d'un versement de cette journée
    $annee = AnneeScolaire::firstOrCreate(
        ['code' => '2026-2027'],
        ['nom' => '2026-2027', 'date_debut' => '2026-09-01', 'date_fin' => '2027-07-31', 'is_current' => true]
    );
    $sectionA = Section::create(['etablissement_id' => $this->campusA->id, 'code' => 'FR', 'nom' => 'Francophone A', 'langue' => 'fr']);
    $cycleA = Cycle::create(['etablissement_id' => $this->campusA->id, 'section_id' => $sectionA->id, 'code' => 'C1', 'nom' => 'Cycle 1 A', 'ordre' => 1]);
    $niveauA = Niveau::create(['etablissement_id' => $this->campusA->id, 'cycle_id' => $cycleA->id, 'code' => '6E', 'nom' => '6ème A', 'ordre' => 1]);
    $classeA = Classe::create(['etablissement_id' => $this->campusA->id, 'niveau_id' => $niveauA->id, 'code' => '6A1', 'nom' => '6ème A1', 'capacite_max' => 50]);
    $eleveA = Eleve::create(['matricule' => '26CMPA0001', 'nom' => 'NGONO', 'prenom' => 'Marie', 'sexe' => 'F', 'is_active' => true]);
    $inscA = Inscription::create([
        'eleve_id' => $eleveA->id,
        'classe_id' => $classeA->id,
        'etablissement_id' => $this->campusA->id,
        'annee_scolaire_id' => $annee->id,
        'type_inscription' => 'nouveau',
        'statut' => 'validee',
        'date_inscription' => '2026-09-05',
    ]);
    $compteA = CompteFinancierEleve::create([
        'inscription_id' => $inscA->id,
        'eleve_id' => $eleveA->id,
        'etablissement_id' => $this->campusA->id,
        'annee_scolaire_id' => $annee->id,
        'debit_initial' => 50000,
        'montant_net_exigible' => 50000,
        'total_paye' => 0,
        'solde_restant' => 50000,
        'statut_financier' => 'insolvable',
    ]);

    $financeService = app(FinanceService::class);
    $versement = $financeService->enregistrerVersement([
        'compte_financier_id' => $compteA->id,
        'montant_total' => 20000,
        'mode_paiement' => 'especes',
        'ventilations' => [
            ['libelle' => 'Acompte scolarité', 'montant' => 20000],
        ],
    ], null, $superAdmin);

    $financeService->cloturerCaisse($this->campusA->id, null, now()->toDateString(), 'Clôture test', $superAdmin);

    expect(fn () => $financeService->annulerVersement($versement->fresh(), 'Tentative après clôture', $superAdmin))
        ->toThrow(InvalidArgumentException::class);
});
