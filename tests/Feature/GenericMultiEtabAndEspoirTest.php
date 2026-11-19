<?php

/** @var TestCase $this */

use App\Models\AnneeScolaire;
use App\Models\BourseRemise;
use App\Models\Classe;
use App\Models\CompteFinancierEleve;
use App\Models\Eleve;
use App\Models\Etablissement;
use App\Models\Institution;
use App\Models\Matiere;
use App\Models\Personnel;
use App\Models\Section;
use App\Models\User;
use App\Services\FinanceService;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\EspoirComplexeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\patch;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

uses(RefreshDatabase::class);

/**
 * Suite de tests de validation de l'intégration du client ESPOIR et des évolutions
 * génériques de la plateforme SYGES :
 * - Catalogue national enrichi des 181 matières (145 conservées + 36 MINEDUB Maternelle/Primaire)
 * - Données complètes du Groupe Scolaire ESPOIR (2 campus, 5 sections, 19 niveaux, 20 classes, 503 élèves)
 * - Gestion générique du sexe nullable (« Non défini / À compléter ») et téléphone parent optionnel
 * - Titres configurables de direction & titulaire (`Le Maître / La Maîtresse`) et fonctions RH MINEDUB
 * - Logo filigrane avec fallback hiérarchique Établissement -> Institution dans les états PDF
 * - Remise anticipée de 4 % imputée sur la dernière tranche (85 000 -> 3 400 / 75 000 -> 3 000 FCFA)
 * - Cloisonnement des directions locales et vision globale transversale du profil Secrétaire.
 */
beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);
    $this->seed(EspoirComplexeSeeder::class);
});

test('le seeder national préserve les 145 matières et ajoute les 36 matières MINEDUB Maternelle/Primaire', function (): void {
    // 145 matières historiques + 36 matières MINEDUB (15 Maternelle + 21 Primaire) = 181 nationales
    expect(Matiere::whereNull('etablissement_id')->count())->toBe(181)
        ->and(Matiere::where('code', 'MAT-LANG-COMM')->exists())->toBeTrue()
        ->and(Matiere::where('code', 'NUR-LANG-READ')->exists())->toBeTrue()
        ->and(Matiere::where('code', 'PRIM-LECTURE')->exists())->toBeTrue()
        ->and(Matiere::where('code', 'ENG-PRIM-NUM')->exists())->toBeTrue();
});

test('EspoirComplexeSeeder déploie fidèlement les 2 campus, 20 classes, 22 personnels et 503 élèves', function (): void {
    $institution = Institution::where('code', 'GSBL-ESPOIR')->firstOrFail();
    $mat = Etablissement::where('code', 'ESP-MAT')->firstOrFail();
    $pri = Etablissement::where('code', 'ESP-PRI')->firstOrFail();

    expect($institution->logo)->toBe('/storage/logos/espoir_logo.jpg')
        ->and($institution->logo_filigrane)->toBe('/storage/logos/espoir_filigrane.png')
        ->and($mat->titre_chef)->toBe('La Directrice')
        ->and($pri->titre_chef)->toBe('Le Directeur')
        ->and($mat->titre_responsable_classe)->toBe('Le Maître / La Maîtresse')
        ->and(Section::where('etablissement_id', $mat->id)->where('code', 'SPE-BIL')->exists())->toBeTrue()
        ->and(Classe::withoutGlobalScopes()->count())->toBe(20)
        ->and(Classe::withoutGlobalScopes()->whereNotNull('professeur_principal_id')->count())->toBe(20)
        ->and(Personnel::withoutGlobalScopes()->count())->toBe(22)
        ->and(Eleve::count())->toBe(503)
        ->and(Eleve::whereNull('sexe')->count())->toBe(503)
        ->and(Eleve::whereNotNull('date_naissance')->count())->toBe(19);

    // Vérification stricte des comptabilités élèves : 221 en Maternelle (85 000) + 282 en Primaire (75 000)
    expect(CompteFinancierEleve::withoutGlobalScopes()->where('etablissement_id', $mat->id)->count())->toBe(221)
        ->and(CompteFinancierEleve::withoutGlobalScopes()->where('etablissement_id', $mat->id)->where('debit_initial', 85000)->count())->toBe(221)
        ->and(CompteFinancierEleve::withoutGlobalScopes()->where('etablissement_id', $pri->id)->count())->toBe(282)
        ->and(CompteFinancierEleve::withoutGlobalScopes()->where('etablissement_id', $pri->id)->where('debit_initial', 75000)->count())->toBe(282);
});

test('un élève peut être créé, filtré et imprimé avec sexe null et téléphone parent optionnel', function (): void {
    $superAdmin = User::where('login', 'superadmin')->firstOrFail();
    $classeSil = Classe::withoutGlobalScopes()->where('code', 'SIL')->firstOrFail();
    $annee = AnneeScolaire::where('is_current', true)->firstOrFail();

    actingAs($superAdmin);

    $storeResponse = post(route('administration.eleves.store'), [
        'nom' => 'KAMDEM TEST',
        'prenom' => 'Junior',
        'sexe' => '',
        'telephone_parent' => '',
        'nationalite' => 'CAMEROUNAISE',
        'classe_id' => $classeSil->id,
        'annee_scolaire_id' => $annee->id,
        'is_redoublant' => false,
        'statut' => 'validee',
    ]);
    $storeResponse->assertRedirect();

    $nouvelEleve = Eleve::where('nom', 'KAMDEM TEST')->firstOrFail();
    expect($nouvelEleve->sexe)->toBeNull()
        ->and($nouvelEleve->telephone_parent)->toBeNull();

    // Filtrage sur la valeur sentinelle ND (Non défini) et vérification du KPI totalNonDefinis
    get(route('administration.eleves.index', ['sexe' => 'ND']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('administration/eleves/index')
            ->where('stats.totalNonDefinis', 504)
        );

    // Génération du PDF officiel liste des élèves sans erreur sur sexe=null
    $pdfResponse = get(route('administration.documents.liste-eleves', $classeSil));
    $pdfResponse->assertOk();
    expect($pdfResponse->headers->get('content-type'))->toContain('application/pdf');
});

test('les titres personnalisés, le filigrane hiérarchique et les fonctions RH MINEDUB fonctionnent sur le système générique', function (): void {
    Storage::fake('public');

    $superAdmin = User::where('login', 'superadmin')->firstOrFail();
    $mat = Etablissement::where('code', 'ESP-MAT')->firstOrFail();
    $classeNur1A = Classe::withoutGlobalScopes()->where('code', 'NUR-1A')->firstOrFail();

    actingAs($superAdmin);

    // 1. Téléversement d'un filigrane sur l'établissement + personnalisation du titre responsable de classe
    $fakeWatermark = UploadedFile::fake()->image('watermark.png', 400, 400);

    put(route('administration.etablissements.settings.update', $mat), [
        'code' => $mat->code,
        'nom' => $mat->nom,
        'nom_en' => $mat->nom_en,
        'statut_juridique' => $mat->statut_juridique,
        'ordre_enseignement' => $mat->ordre_enseignement,
        'titre_chef' => 'La Directrice',
        'titre_chef_en' => 'The Headmistress',
        'titre_responsable_classe' => 'Maîtresse Titulaire',
        'titre_responsable_classe_en' => 'Lead Teacher',
        'logo_filigrane_file' => $fakeWatermark,
        'mode_bulletin_defaut' => 'avec_competences',
        'type_calcul_trimestre' => 'arithmetique',
        'seuil_absences_decheance_mentions' => 15,
        'verrou_solvabilite_actif' => true,
        'bonus_paiement_anticipe_actif' => true,
    ])->assertRedirect();

    $mat->refresh();
    expect($mat->titre_responsable_classe)->toBe('Maîtresse Titulaire')
        ->and($mat->filigrane_url)->toStartWith('/storage/logos/');

    // Vérification du fallback filigrane lorsque l'établissement n'en a pas de spécifique
    $mat->logo_filigrane = null;
    $mat->save();
    $mat->refresh();
    expect($mat->filigrane_url)->toBe('/storage/logos/espoir_filigrane.png');

    // 2. Création d'un membre du personnel avec fonction Maître (détection enseignant) et attribution titulaire
    post(route('administration.personnels.store'), [
        'etablissement_id' => $mat->id,
        'nom' => 'FOTSO',
        'prenom' => 'Marc',
        'sexe' => 'M',
        'telephone' => '670 00 11 22',
        'fonction_principale' => 'Maître',
        'type_contrat' => 'Permanent',
        'date_embauche' => '2026-09-01',
        'statut' => 'Actif',
    ])->assertRedirect();

    $maitre = Personnel::withoutGlobalScopes()->where('nom', 'FOTSO')->firstOrFail();
    expect($maitre->is_enseignant)->toBeTrue();

    patch(route('administration.attributions.classes.professeur-principal', $classeNur1A), [
        'professeur_principal_id' => $maitre->id,
    ])->assertRedirect();

    expect((int) $classeNur1A->fresh()->professeur_principal_id)->toBe($maitre->id);
});

test('la remise anticipée de 4 pourcent calcule exactement 3 400 FCFA en Maternelle et 3 000 FCFA en Primaire sur la dernière tranche', function (): void {
    $superAdmin = User::where('login', 'superadmin')->firstOrFail();
    $mat = Etablissement::where('code', 'ESP-MAT')->firstOrFail();
    $pri = Etablissement::where('code', 'ESP-PRI')->firstOrFail();

    $compteMat = CompteFinancierEleve::withoutGlobalScopes()->where('etablissement_id', $mat->id)->firstOrFail();
    $comptePri = CompteFinancierEleve::withoutGlobalScopes()->where('etablissement_id', $pri->id)->firstOrFail();

    $remiseMat = BourseRemise::withoutGlobalScopes()
        ->where('etablissement_id', $mat->id)
        ->where('code', 'REM-ANTICIPE-4')
        ->firstOrFail();

    $remisePri = BourseRemise::withoutGlobalScopes()
        ->where('etablissement_id', $pri->id)
        ->where('code', 'REM-ANTICIPE-4')
        ->firstOrFail();

    $financeService = app(FinanceService::class);

    $attributionMat = $financeService->attribuerBourseRemise($compteMat, $remiseMat, 'Paiement anticipé avant rentrée', $superAdmin);
    $attributionPri = $financeService->attribuerBourseRemise($comptePri, $remisePri, 'Paiement anticipé avant rentrée', $superAdmin);

    $syntheseMat = $financeService->obtenirEcheancierEleve($compteMat->fresh());
    $synthesePri = $financeService->obtenirEcheancierEleve($comptePri->fresh());

    expect((float) $attributionMat->montant_calcule)->toBe(3400.0)
        ->and($syntheseMat['bourses_attribuees'][0]['cible_imputation'])->toBe('derniere_tranche')
        ->and((float) $compteMat->fresh()->solde_restant)->toBe(81600.0)
        ->and((float) $attributionPri->montant_calcule)->toBe(3000.0)
        ->and($synthesePri['bourses_attribuees'][0]['cible_imputation'])->toBe('derniere_tranche')
        ->and((float) $comptePri->fresh()->solde_restant)->toBe(72000.0);
});

test('le cloisonnement des directeurs locaux est strict tandis que la Secrétaire dispose d une vision globale multi-établissements', function (): void {
    $mat = Etablissement::where('code', 'ESP-MAT')->firstOrFail();
    $pri = Etablissement::where('code', 'ESP-PRI')->firstOrFail();

    $directriceMat = User::where('email', 'directrice.maternelle@espoir.cm')->firstOrFail();
    $directeurPri = User::where('email', 'directeur.primaire@espoir.cm')->firstOrFail();
    $secretaire = User::where('email', 'secretaire@espoir.cm')->firstOrFail();

    expect($directriceMat->canAccessAllEtablissements())->toBeFalse()
        ->and($directeurPri->canAccessAllEtablissements())->toBeFalse()
        ->and($secretaire->canAccessAllEtablissements())->toBeTrue();

    // 1. Directrice Maternelle : voit uniquement les 221 élèves de Maternelle et est bloquée sur le Primaire
    actingAs($directriceMat);
    get(route('administration.eleves.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('stats.totalInscrits', 221)
        );
    get(route('administration.finances.etats', ['etablissement_id' => $pri->id]))
        ->assertForbidden();

    // 2. Directeur Primaire : voit uniquement les 282 élèves du Primaire
    actingAs($directeurPri);
    get(route('administration.eleves.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('stats.totalInscrits', 282)
        );

    // 3. Secrétaire Générale : vision globale consolidée (503 élèves, états financiers consolidés, bilan dépenses, notes & bulletins)
    actingAs($secretaire);

    get(route('administration.eleves.index', ['etablissement_id' => 'all']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('stats.totalInscrits', 503)
        );

    get(route('administration.finances.etats', ['etablissement_id' => 'all']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('administration/finances/etats')
            ->where('isSuperAdmin', true)
            ->has('etablissements', 2)
        );

    $bilanFinancesPdf = get(route('administration.finances.etats.bilan-resume-pdf', ['etablissement_id' => 'all']));
    $bilanFinancesPdf->assertOk();
    expect($bilanFinancesPdf->headers->get('content-type'))->toContain('application/pdf');

    $bilanDepensesPdf = get(route('administration.finances.bilan-pdf', ['etablissement_id' => 'all']));
    $bilanDepensesPdf->assertOk();
    expect($bilanDepensesPdf->headers->get('content-type'))->toContain('application/pdf');

    get(route('administration.notes.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('etablissements', 2)
        );

    get(route('administration.bulletins.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('etablissements', 2)
        );
});

test('les deux sélecteurs fonctionnent de façon synchronisée et « Tous les établissements » consolide les 503 élèves sur Tableau de bord et Statistiques', function (): void {
    $mat = Etablissement::where('code', 'ESP-MAT')->firstOrFail();
    $pri = Etablissement::where('code', 'ESP-PRI')->firstOrFail();

    $superAdmin = User::where('login', 'superadmin')->firstOrFail();
    $secretaire = User::where('email', 'secretaire@espoir.cm')->firstOrFail();
    $directriceMat = User::where('email', 'directrice.maternelle@espoir.cm')->firstOrFail();

    // 1. Par défaut sur /dashboard, le Super-Admin voit Maternelle (221) avec les deux options disponibles
    actingAs($superAdmin);
    get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->where('supportsAllEtablissements', true)
            ->where('isAllEtablissements', false)
            ->where('demographie.effectif_total', 221)
        );

    // 2. Sélection directe via le sélecteur de page (<Select> -> ?etablissement_id=all) : consolide les 503 élèves et 39 935 000 FCFA
    get(route('dashboard', ['etablissement_id' => 'all']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->where('supportsAllEtablissements', true)
            ->where('isAllEtablissements', true)
            ->where('demographie.effectif_total', 503)
            ->where('demographie.densite.nombre_classes', 20)
            ->where('finances.total_facture', 39935000)
        );

    // 3. Bascule depuis le sélecteur d'en-tête (EtablissementSwitcher) vers Primaire tout en ayant ?etablissement_id=all dans l'URL précédente
    $this->from('/dashboard?etablissement_id=all')
        ->post(route('context.switch-etablissement'), [
            'etablissement_id' => $pri->id,
        ])
        ->assertRedirect('/dashboard');

    get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('isAllEtablissements', false)
            ->where('currentEtablissement.id', $pri->id)
            ->where('demographie.effectif_total', 282)
        );

    // 4. Bascule inverse via le sélecteur d'en-tête vers « Tous les établissements » puis accès à Statistiques + Bordereau PDF
    actingAs($secretaire);
    $this->from("/dashboard?etablissement_id={$mat->id}")
        ->post(route('context.switch-etablissement'), [
            'etablissement_id' => 'all',
        ])
        ->assertRedirect('/dashboard');

    get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('supportsAllEtablissements', true)
            ->where('isAllEtablissements', true)
            ->where('demographie.effectif_total', 503)
        );

    get(route('administration.statistiques.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('administration/statistiques/index')
            ->where('supportsAllEtablissements', true)
            ->where('isAllEtablissements', true)
            ->where('demographie.effectif_total', 503)
            ->has('classes', 20)
            ->has('listesOfficielles', 503)
            ->where('listesOfficielles.0.rang_alphabetique', 1)
            ->where('listesOfficielles.502.rang_alphabetique', 503)
        );

    // L'accès à un module mono-campus reste fonctionnel grâce au maintien de l'ID numérique en session ($pri->id de l'étape 3)
    get(route('administration.notes.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('currentEtablissement.id', $pri->id)
        );

    $bordereauPdf = get(route('administration.statistiques.bordereau', ['etablissement_id' => 'all']));
    $bordereauPdf->assertOk();
    expect($bordereauPdf->headers->get('content-type'))->toContain('application/pdf');

    // 5. Cloisonnement strict : Directrice Maternelle ne peut ni activer 'all' ni consulter les 503 élèves
    actingAs($directriceMat);
    get(route('dashboard', ['etablissement_id' => 'all']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('supportsAllEtablissements', false)
            ->where('isAllEtablissements', false)
            ->where('demographie.effectif_total', 221)
        );

    get(route('administration.statistiques.index', ['etablissement_id' => 'all']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('supportsAllEtablissements', false)
            ->where('isAllEtablissements', false)
            ->where('demographie.effectif_total', 221)
            ->has('listesOfficielles', 221)
        );
});

