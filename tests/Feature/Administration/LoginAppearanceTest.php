<?php

/** @var TestCase $this */

use App\Models\Institution;
use App\Models\LoginSetting;
use App\Models\LoginSlide;
use App\Models\User;
use App\Services\LoginAppearanceService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Tests de fonctionnalités pour la personnalisation du carrousel et de l'apparence
 * de la page de connexion (`/login`) dans SYGES.
 *
 * Vérifie :
 * - Les contrôles d'accès RBAC (`institutions.edit`).
 * - Le comportement par défaut (10 scènes SYGES + branding d'origine).
 * - L'import de N photos (ex. 3 slides = boucle stricte sur 3 slides, jamais 10).
 * - La modification, le réordonnancement (`up`/`down`), la désactivation et la suppression
 *   avec repli automatique vers les 10 scènes par défaut lorsqu'aucun slide actif ne subsiste.
 * - L'édition et la réinitialisation du branding, de la cadence et des contacts support.
 */
beforeEach(function () {
    /** @var TestCase $this */
    app(LoginAppearanceService::class)->clearCache();
    Storage::fake('public');

    Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'institutions.view', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'institutions.edit', 'guard_name' => 'web']);

    $this->adminUser = User::factory()->create();
    $this->adminUser->givePermissionTo(['institutions.view', 'institutions.edit']);

    $this->unauthorizedUser = User::factory()->create();

    Institution::firstOrCreate(
        ['code' => 'INST-ESPOIR-TEST'],
        [
            'sigle' => 'ESPOIR',
            'nom' => 'École Primaire Espoir',
            'nom_en' => 'Espoir Primary School',
            'devise' => 'Travail - Discipline - Succès',
            'email' => 'contact@espoir.cm',
            'telephone_1' => '(+237) 699 00 11 22',
        ]
    );
});

test('guests are redirected to login when visiting login appearance settings', function () {
    /** @var TestCase $this */
    $this->get(route('administration.login-appearance.index'))
        ->assertRedirect(route('login'));
});

test('unauthorized users without institutions.edit receive 403 on login appearance endpoints', function () {
    /** @var TestCase $this */
    $slide = LoginSlide::create([
        'titre' => 'Slide existant',
        'message' => 'Message',
        'image_path' => '/storage/login-slides/demo.jpg',
        'ordre' => 1,
        'is_active' => true,
    ]);

    $this->actingAs($this->unauthorizedUser);

    $this->get(route('administration.login-appearance.index'))->assertForbidden();

    $this->post(route('administration.login-appearance.settings.update'), [
        'use_custom_slides' => false,
        'rotation_interval_seconds' => 5,
    ])->assertForbidden();

    $this->post(route('administration.login-appearance.settings.reset'))->assertForbidden();

    $this->post(route('administration.login-appearance.slides.store'), [
        'titre' => 'Tentative',
        'image_file' => UploadedFile::fake()->image('photo.jpg', 1200, 800),
    ])->assertForbidden();

    $this->post(route('administration.login-appearance.slides.update', $slide), [
        'titre' => 'Modif',
        'is_active' => true,
    ])->assertForbidden();

    $this->post(route('administration.login-appearance.slides.reorder', $slide), [
        'direction' => 'up',
    ])->assertForbidden();

    $this->delete(route('administration.login-appearance.slides.destroy', $slide))
        ->assertForbidden();
});

test('login page serves the 10 default SYGES scenes and default branding out of the box', function () {
    /** @var TestCase $this */
    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/login')
            ->where('loginAppearance.using_custom_slides', false)
            ->where('loginAppearance.rotation_interval_ms', 4000)
            ->has('loginAppearance.slides', 10)
            ->where('loginAppearance.slides.0.id', 'classe-pedagogie')
            ->where('loginAppearance.branding.brand_name', 'SYGES')
            ->where('loginAppearance.branding.support_email', 'teguiasimo@gmail.com')
        );
});

test('uploading 3 custom slides activates custom mode and displays strictly 3 slides on login', function () {
    /** @var TestCase $this */
    $this->actingAs($this->adminUser);

    $this->get(route('administration.login-appearance.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('administration/institution/login-appearance')
            ->has('defaultScenes', 10)
            ->where('institution.sigle', 'ESPOIR')
        );

    foreach (['Façade campus', 'Élèves en cour', 'Salle informatique'] as $idx => $titre) {
        $this->post(route('administration.login-appearance.slides.store'), [
            'titre' => $titre,
            'message' => 'Message slide '.($idx + 1),
            'image_file' => UploadedFile::fake()->image("photo-{$idx}.jpg", 1920, 1080),
            'is_active' => true,
        ])->assertRedirect();
    }

    expect(LoginSlide::count())->toBe(3);
    expect(LoginSetting::first()?->use_custom_slides)->toBeTrue();

    // Déconnexion pour vérifier le rendu exact reçu par un visiteur sur `/login`
    auth()->logout();

    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/login')
            ->where('loginAppearance.using_custom_slides', true)
            ->has('loginAppearance.slides', 3)
            ->where('loginAppearance.slides.0.title', 'Façade campus')
            ->where('loginAppearance.slides.1.title', 'Élèves en cour')
            ->where('loginAppearance.slides.2.title', 'Salle informatique')
        );
});

test('reordering updating deactivating and deleting slides stays synchronized and falls back to defaults cleanly', function () {
    /** @var TestCase $this */
    $this->actingAs($this->adminUser);

    $this->post(route('administration.login-appearance.slides.store'), [
        'titre' => 'Premier slide',
        'message' => 'Message initial 1',
        'image_file' => UploadedFile::fake()->image('one.jpg', 1400, 900),
        'is_active' => true,
    ])->assertRedirect();

    $this->post(route('administration.login-appearance.slides.store'), [
        'titre' => 'Deuxième slide',
        'message' => 'Message initial 2',
        'image_file' => UploadedFile::fake()->image('two.png', 1400, 900),
        'is_active' => true,
    ])->assertRedirect();

    [$slide1, $slide2] = LoginSlide::orderBy('ordre')->get()->all();
    $oldRelativePath = ltrim(str_replace('/storage/', '', $slide1->image_path), '/');
    Storage::disk('public')->assertExists($oldRelativePath);

    // Remonte le slide 2 en première position
    $this->post(route('administration.login-appearance.slides.reorder', $slide2), [
        'direction' => 'up',
    ])->assertRedirect();

    expect(LoginSlide::orderBy('ordre')->pluck('id')->all())
        ->toBe([$slide2->id, $slide1->id]);

    // Met à jour le texte et remplace l'image du slide 1, tout en le masquant
    $this->post(route('administration.login-appearance.slides.update', $slide1), [
        'titre' => 'Premier slide modifié',
        'message' => 'Nouveau texte',
        'image_file' => UploadedFile::fake()->image('one-updated.jpg', 1400, 900),
        'is_active' => false,
    ])->assertRedirect();

    $slide1->refresh();
    expect($slide1->titre)->toBe('Premier slide modifié');
    expect($slide1->is_active)->toBeFalse();
    Storage::disk('public')->assertMissing($oldRelativePath);

    // Un seul slide actif reste -> `/login` (visiteur non connecté) doit afficher exactement 1 slide
    auth()->logout();

    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('loginAppearance.using_custom_slides', true)
            ->has('loginAppearance.slides', 1)
            ->where('loginAppearance.slides.0.title', 'Deuxième slide')
        );

    // Reconnexion admin pour supprimer le dernier slide actif -> bascule automatique sur les 10 photos par défaut
    $this->actingAs($this->adminUser);
    $this->delete(route('administration.login-appearance.slides.destroy', $slide2))
        ->assertRedirect();

    expect(LoginSetting::first()?->use_custom_slides)->toBeFalse();

    auth()->logout();

    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('loginAppearance.using_custom_slides', false)
            ->has('loginAppearance.slides', 10)
        );
});

test('custom branding rotation interval and reset to defaults update cached login payload cleanly', function () {
    /** @var TestCase $this */
    $this->actingAs($this->adminUser);

    $this->post(route('administration.login-appearance.settings.update'), [
        'use_custom_slides' => false,
        'rotation_interval_seconds' => 8,
        'brand_name' => 'ESPOIR',
        'brand_subtitle' => 'École Primaire Espoir',
        'logo_file' => UploadedFile::fake()->image('logo-espoir.png', 400, 400),
        'footer_left' => 'Groupe Espoir • Douala',
        'footer_right' => '© 2026 ESPOIR',
        'support_label' => 'Secrétariat',
        'support_phone' => '',
        'support_email' => 'sec@espoir.cm',
    ])->assertRedirect();

    $setting = LoginSetting::firstOrFail();
    $savedLogoRelative = ltrim(str_replace('/storage/', '', (string) $setting->logo_path), '/');
    Storage::disk('public')->assertExists($savedLogoRelative);

    auth()->logout();

    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('loginAppearance.rotation_interval_ms', 8000)
            ->where('loginAppearance.branding.brand_name', 'ESPOIR')
            ->where('loginAppearance.branding.brand_subtitle', 'École Primaire Espoir')
            ->where('loginAppearance.branding.footer_left', 'Groupe Espoir • Douala')
            ->where('loginAppearance.branding.support_label', 'Secrétariat')
            ->where('loginAppearance.branding.support_phone', '')
            ->where('loginAppearance.branding.support_email', 'sec@espoir.cm')
        );

    $this->actingAs($this->adminUser);
    $this->post(route('administration.login-appearance.settings.reset'))
        ->assertRedirect();

    Storage::disk('public')->assertMissing($savedLogoRelative);

    auth()->logout();

    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('loginAppearance.rotation_interval_ms', 4000)
            ->where('loginAppearance.branding.brand_name', 'SYGES')
            ->where('loginAppearance.branding.logo_url', '/images/syges-icon.png')
            ->where('loginAppearance.branding.support_phone', '(+237) 697 44 90 03')
        );
});

test('sidebar app branding and browser favicon sync can share or override login branding and reset cleanly', function () {
    /** @var TestCase $this */
    $this->actingAs($this->adminUser);

    // 1. Par défaut : espace connecté reste sur SYGES et favicon null
    $this->get(route('administration.login-appearance.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('appBranding.use_custom_app_branding', false)
            ->where('appBranding.brand_subtitle', 'Gestion Scolaire')
            ->where('appBranding.logo_url', '/images/syges-icon.png')
            ->where('appBranding.sync_favicon', true)
            ->where('appBranding.favicon_url', null)
        );

    // 2. Active l'identité sur la barre latérale en réutilisant le logo et nom de connexion + favicon
    $this->post(route('administration.login-appearance.settings.update'), [
        'use_custom_slides' => false,
        'rotation_interval_seconds' => 4,
        'brand_name' => 'G.S.B.L ESPOIR',
        'brand_subtitle' => 'École Bilingue',
        'logo_file' => UploadedFile::fake()->image('logo-login.png', 256, 256),
        'apply_branding_to_app' => true,
        'app_brand_name' => '',
        'app_brand_subtitle' => '',
        'sync_favicon' => true,
    ])->assertRedirect();

    $setting = LoginSetting::firstOrFail();
    $sharedLogoUrl = (string) $setting->logo_path;

    $responseShared = $this->get(route('administration.login-appearance.index'));
    $responseShared->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('appBranding.use_custom_app_branding', true)
            ->where('appBranding.brand_name', 'G.S.B.L ESPOIR')
            ->where('appBranding.brand_subtitle', 'École Bilingue')
            ->where('appBranding.logo_url', $sharedLogoUrl)
            ->where('appBranding.favicon_url', $sharedLogoUrl)
        );
    $responseShared->assertSee('data-syges-favicon="custom"', false);
    $responseShared->assertSee($sharedLogoUrl, false);

    // 3. Surcharge spécifique barre latérale + désactivation du favicon
    $this->post(route('administration.login-appearance.settings.update'), [
        'use_custom_slides' => false,
        'rotation_interval_seconds' => 4,
        'brand_name' => 'Groupe Scolaire ESPOIR',
        'brand_subtitle' => 'Excellence',
        'apply_branding_to_app' => true,
        'app_brand_name' => 'ESPOIR',
        'app_brand_subtitle' => 'Maternelle & Primaire',
        'app_logo_file' => UploadedFile::fake()->image('logo-sidebar.png', 256, 256),
        'sync_favicon' => false,
    ])->assertRedirect();

    $setting->refresh();
    $sidebarLogoUrl = (string) $setting->app_logo_path;
    $sidebarLogoRelative = ltrim(str_replace('/storage/', '', $sidebarLogoUrl), '/');
    Storage::disk('public')->assertExists($sidebarLogoRelative);

    $responseOverride = $this->get(route('administration.login-appearance.index'));
    $responseOverride->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('appBranding.use_custom_app_branding', true)
            ->where('appBranding.brand_name', 'ESPOIR')
            ->where('appBranding.brand_subtitle', 'Maternelle & Primaire')
            ->where('appBranding.logo_url', $sidebarLogoUrl)
            ->where('appBranding.sync_favicon', false)
            ->where('appBranding.favicon_url', null)
        );
    $responseOverride->assertSee('data-syges-favicon="default"', false);

    // 4. Réinitialisation complète : nettoie aussi le logo barre latérale et restaure SYGES
    $this->post(route('administration.login-appearance.settings.reset'))
        ->assertRedirect();

    Storage::disk('public')->assertMissing($sidebarLogoRelative);

    $this->get(route('administration.login-appearance.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('appBranding.use_custom_app_branding', false)
            ->where('appBranding.logo_url', '/images/syges-icon.png')
            ->where('appBranding.brand_subtitle', 'Gestion Scolaire')
            ->where('appBranding.favicon_url', null)
        );
});
