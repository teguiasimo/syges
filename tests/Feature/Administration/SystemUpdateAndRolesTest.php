<?php

/** @var TestCase $this */

use App\Mail\DatabaseBackupMail;
use App\Models\Eleve;
use App\Models\User;
use App\Services\DatabaseBackupService;
use App\Services\SystemUpdateService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Process;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Suite de tests fonctionnels et d'intégration couvrant :
 * 1. Les nouveaux identifiants du Super Administrateur (`superadmin` / `5idfasuper`).
 * 2. La séparation stricte des privilèges entre `super-admin` et `admin` (interdiction de supprimer un élève
 *    et interdiction d'accéder aux mises à jour système pour le compte `admin`).
 * 3. Le service autonome de sauvegarde SQL (`DatabaseBackupService`) et l'envoi d'email au développeur.
 * 4. Le gestionnaire de versions SemVer (`SystemUpdateService`) sur un véritable dépôt Git isolé :
 *    ignorance des pushs non tagués, classification Majeure/Mineure/Patch et pipeline complet de mise à jour.
 */
test('database seeder configures superadmin credentials and restricts admin permissions', function () {
    /** @var TestCase $this */
    $this->seed(DatabaseSeeder::class);

    $superAdmin = User::where('login', 'superadmin')->first();
    expect($superAdmin)->not->toBeNull()
        ->and($superAdmin->isSuperAdmin())->toBeTrue()
        ->and(Hash::check('5idfasuper', $superAdmin->password))->toBeTrue();

    $superAdminRole = Role::findByName('super-admin');
    $adminRole = Role::findByName('admin');

    expect($adminRole->permissions->count())->toBeLessThan($superAdminRole->permissions->count())
        ->and($superAdminRole->hasPermissionTo('system.update'))->toBeTrue()
        ->and($superAdminRole->hasPermissionTo('eleves.delete'))->toBeTrue()
        ->and($adminRole->hasPermissionTo('system.update'))->toBeFalse()
        ->and($adminRole->hasPermissionTo('eleves.delete'))->toBeFalse()
        ->and($adminRole->hasPermissionTo('roles.edit'))->toBeFalse();
});

test('superadmin can sign in using superadmin login and 5idfasuper password', function () {
    /** @var TestCase $this */
    $this->seed(DatabaseSeeder::class);

    $response = $this->post(route('login.store'), [
        'email' => 'superadmin',
        'password' => '5idfasuper',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('local admin cannot delete students or access system update endpoints', function () {
    /** @var TestCase $this */
    $this->seed(DatabaseSeeder::class);

    $localAdmin = User::factory()->create([
        'name' => 'Administrateur Local',
        'login' => 'administrateur',
        'email' => 'admin.local@syges.cm',
        'is_active' => true,
    ]);
    $localAdmin->assignRole('admin');

    $eleve = Eleve::create([
        'matricule' => 'SYG2026999',
        'nom' => 'NDJOCK',
        'prenom' => 'Samuel',
        'sexe' => 'M',
        'nationalite' => 'CAMEROUNAISE',
        'is_active' => true,
    ]);

    $this->actingAs($localAdmin);

    // 1. Interdiction stricte de supprimer un élève
    $this->delete(route('administration.eleves.destroy', $eleve))
        ->assertForbidden();
    expect(Eleve::find($eleve->id))->not->toBeNull();

    // 2. Interdiction stricte d'accéder à tous les endpoints de mise à jour système
    $this->get(route('administration.system-update.index'))->assertForbidden();
    $this->postJson(route('administration.system-update.check'))->assertForbidden();
    $this->postJson(route('administration.system-update.run'))->assertForbidden();
    $this->postJson(route('administration.system-update.backup'))->assertForbidden();
});

test('superadmin can access system update dashboard and roles controller blocks system.update assignment to non super-admin', function () {
    /** @var TestCase $this */
    $this->seed(DatabaseSeeder::class);

    $superAdmin = User::where('login', 'superadmin')->firstOrFail();
    $this->actingAs($superAdmin);

    $this->get(route('administration.system-update.index'))
        ->assertOk();

    // Tenter d'attribuer `system.update` au rôle `admin` : doit être filtré automatiquement
    $adminRole = Role::findByName('admin');
    $this->put(route('administration.roles.update', $adminRole), [
        'display_name' => 'Administrateur',
        'permissions' => ['eleves.view', 'system.update'],
    ])->assertRedirect();

    $adminRole->refresh();
    expect($adminRole->hasPermissionTo('eleves.view'))->toBeTrue()
        ->and($adminRole->hasPermissionTo('system.update'))->toBeFalse();
});

test('database backup service exports valid sql, quotes edge-case strings, sends developer email and prevents path traversal', function () {
    /** @var TestCase $this */
    Mail::fake();

    $tempBackupDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'syges_test_backups_'.uniqid();
    File::makeDirectory($tempBackupDir, 0755, true);

    try {
        // Insérer un élève avec téléphone à zéro initial et matricule type notation scientifique (6E1)
        Eleve::create([
            'matricule' => '6E1',
            'nom' => 'FOUDA',
            'prenom' => 'Clarisse',
            'sexe' => 'F',
            'telephone_parent' => '0699112233',
            'nationalite' => 'CAMEROUNAISE',
            'is_active' => true,
        ]);

        $service = new DatabaseBackupService($tempBackupDir);
        $backup = $service->createBackup('v1.0.0');

        expect($backup['success'])->toBeTrue()
            ->and($backup['path'])->not->toBeNull()
            ->and(File::exists((string) $backup['path']))->toBeTrue();

        $sqlContent = File::get((string) $backup['path']);
        expect($sqlContent)->toContain("'6E1'")
            ->and($sqlContent)->toContain("'0699112233'");

        // Test d'envoi d'email au développeur
        config(['app.developer_email' => 'teguiasimo@gmail.com']);
        $mailRes = $service->sendBackupToDeveloper(
            filepath: (string) $backup['path'],
            filename: (string) $backup['filename'],
            currentVersion: 'v1.0.0',
            targetVersion: 'v1.1.0',
        );

        expect($mailRes['sent'])->toBeTrue();
        Mail::assertSent(DatabaseBackupMail::class, function (DatabaseBackupMail $mail): bool {
            return $mail->hasTo('teguiasimo@gmail.com');
        });

        // Contrôle anti-Path Traversal
        expect($service->resolveValidBackupPath('../../.env'))->toBeNull()
            ->and($service->resolveValidBackupPath((string) $backup['filename']))->not->toBeNull();
    } finally {
        File::deleteDirectory($tempBackupDir);
    }
});

test('system update service ignores untagged commits and updates cleanly only to validated semver tags', function () {
    /** @var TestCase $this */
    Mail::fake();

    $baseTemp = sys_get_temp_dir().DIRECTORY_SEPARATOR.'syges_git_test_'.uniqid();
    $remoteBareDir = $baseTemp.DIRECTORY_SEPARATOR.'remote.git';
    $devRepoDir = $baseTemp.DIRECTORY_SEPARATOR.'dev';
    $clientRepoDir = $baseTemp.DIRECTORY_SEPARATOR.'client';
    $backupDir = $baseTemp.DIRECTORY_SEPARATOR.'backups';
    $historyFile = $baseTemp.DIRECTORY_SEPARATOR.'update_history.json';

    File::makeDirectory($remoteBareDir, 0755, true);
    File::makeDirectory($devRepoDir, 0755, true);
    File::makeDirectory($backupDir, 0755, true);

    try {
        // 1. Initialiser un dépôt distant Git nu (simulant GitHub) et un dépôt développeur
        Process::path($remoteBareDir)->run(['git', 'init', '--bare', '--initial-branch=main'])->throw();

        Process::path($devRepoDir)->run(['git', 'init', '--initial-branch=main'])->throw();
        Process::path($devRepoDir)->run(['git', 'config', 'user.email', 'teguiasimo@gmail.com'])->throw();
        Process::path($devRepoDir)->run(['git', 'config', 'user.name', 'SYGES Dev'])->throw();
        Process::path($devRepoDir)->run(['git', 'remote', 'add', 'origin', $remoteBareDir])->throw();

        File::put($devRepoDir.'/release.txt', 'Version initiale 1.0.0');
        Process::path($devRepoDir)->run(['git', 'add', '.'])->throw();
        Process::path($devRepoDir)->run(['git', 'commit', '-m', 'Initial release v1.0.0'])->throw();
        Process::path($devRepoDir)->run(['git', 'tag', '-a', 'v1.0.0', '-m', 'Livraison initiale SYGES'])->throw();
        Process::path($devRepoDir)->run(['git', 'push', 'origin', 'main', '--tags'])->throw();

        // 2. Cloner sur le serveur client à la version v1.0.0
        Process::run(['git', 'clone', '--branch', 'main', $remoteBareDir, $clientRepoDir])->throw();
        Process::path($clientRepoDir)->run(['git', 'config', 'user.email', 'client@syges.cm'])->throw();
        Process::path($clientRepoDir)->run(['git', 'config', 'user.name', 'SYGES Client'])->throw();

        $backupService = new DatabaseBackupService($backupDir);
        $updateService = new SystemUpdateService($backupService, $clientRepoDir, $historyFile);

        // Vérifications unitaires de classification SemVer
        expect($updateService->isValidSemverTag('v1.0.0'))->toBeTrue()
            ->and($updateService->isValidSemverTag('v1.2.0-beta.1'))->toBeFalse()
            ->and($updateService->isValidSemverTag('wip-feature'))->toBeFalse()
            ->and($updateService->classifyReleaseType('v1.0.0', 'v1.0.1'))->toBe('patch')
            ->and($updateService->classifyReleaseType('v1.0.1', 'v1.1.0'))->toBe('minor')
            ->and($updateService->classifyReleaseType('v1.1.0', 'v2.0.0'))->toBe('major');

        // 3. Le développeur pousse un commit NON TAGUÉ et un tag invalide (beta) sur GitHub
        File::put($devRepoDir.'/release.txt', 'Travail en cours non validé');
        Process::path($devRepoDir)->run(['git', 'add', '.'])->throw();
        Process::path($devRepoDir)->run(['git', 'commit', '-m', 'WIP commit sans tag valide'])->throw();
        Process::path($devRepoDir)->run(['git', 'tag', 'v1.1.0-beta.1'])->throw();
        Process::path($devRepoDir)->run(['git', 'push', 'origin', 'main', '--tags'])->throw();

        // Le serveur client vérifie : AUCUNE mise à jour ne doit être proposée !
        $checkUntagged = $updateService->checkForUpdates(skipInternetCheck: true);
        expect($checkUntagged['success'])->toBeTrue()
            ->and($checkUntagged['has_update'])->toBeFalse()
            ->and($checkUntagged['is_up_to_date'])->toBeTrue()
            ->and($checkUntagged['current_version'])->toBe('v1.0.0');

        // 4. Le développeur valide et publie un tag SemVer mineur officiel `v1.1.0`, puis continue un autre commit non tagué
        File::put($devRepoDir.'/release.txt', 'Nouvelle fonctionnalité validée 1.1.0');
        Process::path($devRepoDir)->run(['git', 'add', '.'])->throw();
        Process::path($devRepoDir)->run(['git', 'commit', '-m', 'feat: module statistique v1.1.0'])->throw();
        Process::path($devRepoDir)->run(['git', 'tag', '-a', 'v1.1.0', '-m', 'Ajout des statistiques trimestrielles'])->throw();

        File::put($devRepoDir.'/release.txt', 'Brouillon futur non tagué après 1.1.0');
        Process::path($devRepoDir)->run(['git', 'add', '.'])->throw();
        Process::path($devRepoDir)->run(['git', 'commit', '-m', 'chore: brouillon futur'])->throw();
        Process::path($devRepoDir)->run(['git', 'push', 'origin', 'main', '--tags'])->throw();

        // Le serveur client détecte désormais exactement `v1.1.0` (Mineure)
        $checkValidTag = $updateService->checkForUpdates(skipInternetCheck: true);
        expect($checkValidTag['has_update'])->toBeTrue()
            ->and($checkValidTag['latest_version'])->toBe('v1.1.0')
            ->and($checkValidTag['release_type'])->toBe('minor');

        // 5. Exécuter le pipeline complet de mise à jour sur le client
        $pipelineResult = $updateService->runUpdatePipeline(
            requestedTag: null,
            triggeredBy: 'superadmin',
            skipInternetCheck: true,
        );

        expect($pipelineResult['success'])->toBeTrue()
            ->and($pipelineResult['new_version'])->toBe('v1.1.0')
            ->and(File::get($clientRepoDir.'/release.txt'))->toBe('Nouvelle fonctionnalité validée 1.1.0')
            ->and(count($backupService->getBackupsList()))->toBe(1)
            ->and(count($updateService->getUpdateHistory()))->toBe(1)
            ->and(File::exists(storage_path('framework/down')))->toBeFalse();

        Mail::assertSent(DatabaseBackupMail::class);
    } finally {
        File::deleteDirectory($baseTemp);
    }
});
