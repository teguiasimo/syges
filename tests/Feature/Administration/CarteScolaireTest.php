<?php

/** @var \Tests\TestCase $this */

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\Cycle;
use App\Models\Eleve;
use App\Models\Etablissement;
use App\Models\Inscription;
use App\Models\Institution;
use App\Models\Niveau;
use App\Models\Section;
use App\Models\User;
use Spatie\Permission\Models\Role;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

/**
 * Tests de fonctionnalités pour les Cartes Scolaires & Badges d'Identité (Phase 2).
 *
 * Vérifie :
 * - L'affichage de la page de gestion et des KPIs de couverture photo.
 * - La génération du flux PDF de la planche A4 recto (8 cartes par page avec traits de coupe).
 * - La génération du flux PDF de la planche A4 verso avec QR code de contrôle.
 * - La génération du badge individuel au format CR-80.
 * - Le contrôle d'authenticité public par scan du QR code.
 */

beforeEach(function () {
    /** @var \Tests\TestCase $this */
    Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);

    $this->adminUser = User::factory()->create();
    $this->adminUser->assignRole('super-admin');

    $this->institution = Institution::create([
        'code' => 'INST-FOUMBAN',
        'nom' => 'Secrétariat à l’Éducation - Diocèse de Bafoussam',
        'nom_en' => 'Education Secretariat - Bafoussam Diocese',
        'devise' => 'Respect - Travail - Réussite',
        'delegation_regionale' => 'DÉLÉGATION RÉGIONALE DE L’OUEST',
        'delegation_regionale_en' => 'REGIONAL DELEGATION FOR THE WEST',
        'delegation_departementale' => 'DÉLÉGATION DÉPARTEMENTALE DU NOUN',
        'delegation_departementale_en' => 'DIVISIONAL DELEGATION FOR NOUN',
        'ville' => 'Foumban',
        'telephone_1' => '673980799',
    ]);

    $this->etablissement = Etablissement::create([
        'institution_id' => $this->institution->id,
        'code' => 'CBSAF',
        'nom' => 'Collège Bilingue St Abraham de Foumban',
        'nom_en' => 'St Abraham Bilingual College Foumban',
        'statut_juridique' => 'Prive Catholique',
        'ordre_enseignement' => 'Secondaire General',
        'nom_chef_etablissement' => 'Abbé Armel FONGUE KAMGA',
        'telephone_1' => '673980799',
        'adresse' => 'B.P: 60 Foumban',
    ]);

    $this->anneeScolaire = AnneeScolaire::create([
        'nom' => '2025/2026',
        'code' => '2025-2026',
        'date_debut' => '2025-09-01',
        'date_fin' => '2026-06-30',
        'is_current' => true,
    ]);

    $this->section = Section::create([
        'etablissement_id' => $this->etablissement->id,
        'nom' => 'Section Francophone',
        'code' => 'FR',
    ]);

    $this->cycle = Cycle::create([
        'section_id' => $this->section->id,
        'nom' => 'Premier Cycle',
        'code' => 'C1',
    ]);

    $this->niveau = Niveau::create([
        'cycle_id' => $this->cycle->id,
        'nom' => 'Sixième',
        'code' => '6E',
    ]);

    $this->classe = Classe::create([
        'niveau_id' => $this->niveau->id,
        'nom' => '6 EME',
        'code' => '6E-1',
        'capacite' => 60,
    ]);

    $this->eleve = Eleve::create([
        'matricule' => '250111417',
        'nom' => 'NIEN NTIENGEM',
        'prenom' => 'OUTJA JASMIN',
        'sexe' => 'F',
        'date_naissance' => '2014-05-16',
        'lieu_naissance' => 'FOUMBAN',
        'nationalite' => 'CAMEROUNAISE',
        'telephone_parent' => '673980799',
        'is_active' => true,
    ]);

    $this->inscription = Inscription::create([
        'eleve_id' => $this->eleve->id,
        'classe_id' => $this->classe->id,
        'annee_scolaire_id' => $this->anneeScolaire->id,
        'etablissement_id' => $this->etablissement->id,
        'date_inscription' => '2025-09-02',
        'statut' => 'validee',
    ]);
});

it('permet à un administrateur d’accéder à la page de gestion des cartes scolaires', function () {
    $this->withoutVite();
    actingAs($this->adminUser);

    $response = get(route('administration.eleves.cartes-scolaires'));

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('administration/eleves/cartes-scolaires')
        ->has('inscriptions')
        ->has('stats')
        ->where('stats.total_inscrits', 1)
    );
});

it('génère avec succès le flux PDF d’une planche A4 recto pour les cartes scolaires', function () {
    actingAs($this->adminUser);

    $response = post(route('administration.eleves.cartes-scolaires.imprimer-planche'), [
        'inscription_ids' => [$this->inscription->id],
        'face' => 'recto',
    ]);

    $response->assertStatus(200);
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});

it('génère avec succès le flux PDF d’une planche A4 verso avec QR code', function () {
    actingAs($this->adminUser);

    $response = post(route('administration.eleves.cartes-scolaires.imprimer-planche'), [
        'inscription_ids' => [$this->inscription->id],
        'face' => 'verso',
    ]);

    $response->assertStatus(200);
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});

it('génère avec succès le badge individuel unitaire CR80 pour un élève', function () {
    actingAs($this->adminUser);

    $response = get(route('administration.eleves.carte-scolaire-pdf', ['inscription' => $this->inscription->id]));

    $response->assertStatus(200);
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});

it('affiche la page publique de vérification QR code pour un matricule valide', function () {
    $response = get(route('cartes-scolaires.verifier', ['matricule' => '250111417']));

    $response->assertStatus(200);
    $response->assertSee('NIEN NTIENGEM');
    $response->assertSee('OUTJA JASMIN');
    $response->assertSee('250111417');
    $response->assertSeeText('Carte Valide & Authentique');
});

it('affiche un état non reconnu pour un matricule inexistant lors du contrôle QR code', function () {
    $response = get(route('cartes-scolaires.verifier', ['matricule' => 'INEXISTANT_9999']));

    $response->assertStatus(200);
    $response->assertSee('Carte Non Reconnue');
});
