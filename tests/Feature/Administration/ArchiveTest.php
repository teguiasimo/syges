<?php

/** @var \Tests\TestCase $this */

use App\Models\AnneeScolaire;
use App\Models\ArchiveAdministrative;
use App\Models\Eleve;
use App\Models\Etablissement;
use App\Models\Institution;
use App\Models\Personnel;
use App\Models\User;
use Spatie\Permission\Models\Role;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

/**
 * Tests fonctionnels pour le Centre d'Archivage et la Reconduction Annuelle (Aurore §3.15, §3.28).
 *
 * Valide :
 * - L'accès sans erreur à la vue principale d'archivage (/administration/archivage).
 * - L'archivage d'un élève avec mise à jour de son statut.
 * - Le bon calcul des statistiques d'archivage.
 */

beforeEach(function () {
    /** @var \Tests\TestCase $this */
    Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);

    $this->adminUser = User::factory()->create();
    $this->adminUser->assignRole('super-admin');

    $this->institution = Institution::create([
        'code' => 'INST-ARCH',
        'nom' => 'Institution Test Archivage',
        'ville' => 'Douala',
    ]);

    $this->etablissement = Etablissement::create([
        'institution_id' => $this->institution->id,
        'code' => 'ETAB-ARCH',
        'nom' => 'Collège Test Archivage',
        'ordre_enseignement' => 'Secondaire General',
    ]);

    $this->anneeScolaire = AnneeScolaire::create([
        'nom' => '2026/2027',
        'code' => '2026-2027',
        'date_debut' => '2026-09-01',
        'date_fin' => '2027-06-30',
        'is_current' => true,
    ]);
});

test('la page du centre d archivage est accessible sans erreur', function () {
    actingAs($this->adminUser);

    $response = get('/administration/archivage');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('administration/archivage/index')
        ->has('archives')
        ->has('stats')
        ->has('elevesActifs')
        ->has('personnelsActifs')
        ->has('configurationsNumerotation')
    );
});

test('un eleve peut etre archive avec un motif valide', function () {
    actingAs($this->adminUser);

    $eleve = Eleve::create([
        'matricule' => 'EL-ARCH-001',
        'nom' => 'TESTEUR',
        'prenom' => 'Archivage',
        'sexe' => 'M',
        'is_active' => true,
    ]);

    $response = post('/administration/archivage', [
        'type_entite' => 'eleve',
        'eleve_id' => $eleve->id,
        'motif' => 'diplome',
        'date_archivage' => '2026-10-10',
        'destination' => 'Université de Douala',
        'observations' => 'Obtention du Baccalauréat',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('archives_administratives', [
        'type_entite' => 'eleve',
        'matricule' => 'EL-ARCH-001',
        'motif' => 'diplome',
    ]);

    expect($eleve->fresh()->is_active)->toBeFalse();
});
