<?php

/** @var \Tests\TestCase $this */

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\CompetencePeriodique;
use App\Models\CoteConfiguration;
use App\Models\Cycle;
use App\Models\Etablissement;
use App\Models\Institution;
use App\Models\Matiere;
use App\Models\Niveau;
use App\Models\Periode;
use App\Models\Section;
use App\Models\User;
use Spatie\Permission\Models\Role;
use function Pest\Laravel\actingAs;

/**
 * Tests de fonctionnalités pour la Pédagogie APC (Phase 3).
 *
 * Vérifie :
 * - L'affichage de la page de gestion des compétences et des cotes d'acquisition.
 * - L'ajout, la modification et la suppression de compétences périodiques.
 * - Le moteur de transfert et duplication en masse vers des classes parallèles.
 * - La configuration des cotes personnalisées et le calcul conforme APC.
 */

beforeEach(function () {
    /** @var \Tests\TestCase $this */
    $this->withoutVite();

    Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);

    $this->adminUser = User::factory()->create();
    $this->adminUser->assignRole('super-admin');

    $this->institution = Institution::create([
        'code' => 'INST-TEST-APC',
        'nom' => 'Institution Test APC',
        'ville' => 'Yaoundé',
    ]);

    $this->etablissement = Etablissement::create([
        'institution_id' => $this->institution->id,
        'code' => 'ETAB-APC',
        'nom' => 'Lycée Bilingue Test APC',
        'statut_juridique' => 'Public',
        'ordre_enseignement' => 'Secondaire General',
    ]);

    $this->annee = AnneeScolaire::create([
        'nom' => 'Année Scolaire 2026-2027',
        'code' => '2026-2027',
        'date_debut' => '2026-09-01',
        'date_fin' => '2027-06-30',
        'is_current' => true,
    ]);

    $this->section = Section::create([
        'etablissement_id' => $this->etablissement->id,
        'nom' => 'Francophone',
        'code' => 'FR',
    ]);

    $this->cycle = Cycle::create([
        'section_id' => $this->section->id,
        'nom' => 'Premier Cycle',
        'code' => 'C1',
        'ordre' => 1,
    ]);

    $this->niveau = Niveau::create([
        'cycle_id' => $this->cycle->id,
        'nom' => 'Sixième',
        'code' => '6EME',
        'ordre' => 1,
    ]);

    $this->classeA = Classe::create([
        'niveau_id' => $this->niveau->id,
        'nom' => '6e A',
        'code' => '6A',
    ]);

    $this->classeB = Classe::create([
        'niveau_id' => $this->niveau->id,
        'nom' => '6e B',
        'code' => '6B',
    ]);

    $this->matiere = Matiere::create([
        'nom' => 'Mathématiques',
        'code' => 'MATH',
        'is_active' => true,
    ]);

    $this->periode = Periode::create([
        'annee_scolaire_id' => $this->annee->id,
        'etablissement_id' => $this->etablissement->id,
        'nom' => '1er Trimestre',
        'code' => 'T1',
        'ordre' => 1,
        'date_debut' => '2026-09-01',
        'date_fin' => '2026-11-30',
    ]);
});

test('affiche la page de gestion des compétences périodiques APC', function () {
    /** @var \Tests\TestCase $this */
    actingAs($this->adminUser);

    $response = $this->get(route('administration.pedagogie.competences.index', [
        'etablissement_id' => $this->etablissement->id,
    ]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('administration/pedagogie/competences/index')
        ->has('competences')
        ->has('cotes')
        ->has('matieres')
        ->has('periodes')
    );
});

test('permet d enregistrer, modifier et supprimer une compétence périodique', function () {
    /** @var \Tests\TestCase $this */
    actingAs($this->adminUser);

    // 1. Création
    $postResponse = $this->post(route('administration.pedagogie.competences.store'), [
        'etablissement_id' => $this->etablissement->id,
        'classe_id' => $this->classeA->id,
        'matiere_id' => $this->matiere->id,
        'periode_id' => $this->periode->id,
        'libelle_competence' => 'Résoudre des situations problèmes avec les nombres entiers',
        'description' => 'Maîtrise des 4 opérations fondamentales et priorités opératoires',
    ]);

    $postResponse->assertSessionHasNoErrors();
    $this->assertDatabaseHas('competences_periodiques', [
        'classe_id' => $this->classeA->id,
        'matiere_id' => $this->matiere->id,
        'libelle_competence' => 'Résoudre des situations problèmes avec les nombres entiers',
    ]);

    $competence = CompetencePeriodique::first();

    // 2. Modification
    $putResponse = $this->put(route('administration.pedagogie.competences.update', $competence), [
        'libelle_competence' => 'Résoudre des situations problèmes avec les nombres décimaux et entiers',
        'description' => 'Mise à jour pour inclure les nombres décimaux',
        'is_active' => true,
    ]);

    $putResponse->assertSessionHasNoErrors();
    $this->assertDatabaseHas('competences_periodiques', [
        'id' => $competence->id,
        'libelle_competence' => 'Résoudre des situations problèmes avec les nombres décimaux et entiers',
    ]);

    // 3. Suppression
    $deleteResponse = $this->delete(route('administration.pedagogie.competences.destroy', $competence));
    $deleteResponse->assertSessionHasNoErrors();
    $this->assertDatabaseMissing('competences_periodiques', [
        'id' => $competence->id,
    ]);
});

test('permet de dupliquer et transférer des compétences vers des classes cibles', function () {
    /** @var \Tests\TestCase $this */
    actingAs($this->adminUser);

    // Création de 2 compétences dans la classe source 6e A
    CompetencePeriodique::create([
        'etablissement_id' => $this->etablissement->id,
        'classe_id' => $this->classeA->id,
        'matiere_id' => $this->matiere->id,
        'periode_id' => $this->periode->id,
        'libelle_competence' => 'Compétence Source 1',
    ]);

    CompetencePeriodique::create([
        'etablissement_id' => $this->etablissement->id,
        'classe_id' => $this->classeA->id,
        'matiere_id' => $this->matiere->id,
        'periode_id' => $this->periode->id,
        'libelle_competence' => 'Compétence Source 2',
    ]);

    // Transfert vers 6e B
    $response = $this->post(route('administration.pedagogie.competences.transferer'), [
        'classe_source_id' => $this->classeA->id,
        'classes_cibles_ids' => [$this->classeB->id],
        'periode_id' => $this->periode->id,
        'ecraser_existant' => false,
    ]);

    $response->assertSessionHasNoErrors();

    // Vérification que les 2 compétences existent maintenant dans la classe B
    $this->assertEquals(2, CompetencePeriodique::where('classe_id', $this->classeB->id)->count());
    $this->assertDatabaseHas('competences_periodiques', [
        'classe_id' => $this->classeB->id,
        'libelle_competence' => 'Compétence Source 1',
    ]);
    $this->assertDatabaseHas('competences_periodiques', [
        'classe_id' => $this->classeB->id,
        'libelle_competence' => 'Compétence Source 2',
    ]);
});

test('permet de configurer les cotes d acquisition et vérifie le barème MINESEC', function () {
    /** @var \Tests\TestCase $this */
    actingAs($this->adminUser);

    // Barème MINESEC par défaut quand aucune config n'existe
    $this->assertEquals('CTBA', CoteConfiguration::determinerCote(19.0, $this->etablissement->id));
    $this->assertEquals('CBA', CoteConfiguration::determinerCote(15.5, $this->etablissement->id));
    $this->assertEquals('CMA', CoteConfiguration::determinerCote(11.0, $this->etablissement->id));
    $this->assertEquals('CNA', CoteConfiguration::determinerCote(7.5, $this->etablissement->id));

    // Ajout d'une cote personnalisée (ex: système alphabétique A+)
    $response = $this->post(route('administration.pedagogie.competences.cotes.store'), [
        'etablissement_id' => $this->etablissement->id,
        'code_cote' => 'A+',
        'libelle' => 'Excellent - Maîtrise Exceptionnelle',
        'borne_minimale' => 18.00,
        'borne_maximale' => 20.00,
        'couleur' => '#059669',
        'ordre' => 1,
    ]);

    $response->assertSessionHasNoErrors();
    $this->assertDatabaseHas('cotes_configurations', [
        'etablissement_id' => $this->etablissement->id,
        'code_cote' => 'A+',
    ]);

    // La note de 19.0 donne désormais la cote personnalisée A+
    $this->assertEquals('A+', CoteConfiguration::determinerCote(19.0, $this->etablissement->id));
});
