<?php

/** @var \Tests\TestCase $this */

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\ConseilDiscipline;
use App\Models\Cycle;
use App\Models\DossierConseilDiscipline;
use App\Models\Eleve;
use App\Models\Etablissement;
use App\Models\HeureConsigne;
use App\Models\Inscription;
use App\Models\Institution;
use App\Models\Niveau;
use App\Models\Section;
use App\Models\User;
use Spatie\Permission\Models\Role;
use function Pest\Laravel\actingAs;

/**
 * Tests de fonctionnalités pour le Conseil de Discipline & Heures de Consigne (Phase 5).
 *
 * Vérifie :
 * - L'affichage de l'espace de gestion et des compteurs de vie scolaire.
 * - La création de sessions formelles de Conseil de Discipline.
 * - La comparution d'élèves, les sanctions prononcées et les répercussions automatiques.
 * - La génération des lettres de convocation officielles et des Procès-Verbaux de délibération en PDF.
 * - Le registre des heures de consigne et retenues du samedi.
 */

beforeEach(function () {
    /** @var \Tests\TestCase $this */
    $this->withoutVite();

    Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);

    $this->adminUser = User::factory()->create();
    $this->adminUser->assignRole('super-admin');

    $this->institution = Institution::create([
        'code' => 'INST-DISCIPLINE',
        'nom' => 'Délégation Régionale Littoral',
        'ville' => 'Douala',
    ]);

    $this->etablissement = Etablissement::create([
        'institution_id' => $this->institution->id,
        'code' => 'LYCEE-DISCIPLINE',
        'nom' => 'Lycée de Douala Test',
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
        'nom' => 'Second Cycle',
        'code' => 'C2',
        'ordre' => 2,
    ]);

    $this->niveau = Niveau::create([
        'cycle_id' => $this->cycle->id,
        'nom' => 'Terminale',
        'code' => 'TLE',
        'ordre' => 7,
    ]);

    $this->classe = Classe::create([
        'niveau_id' => $this->niveau->id,
        'nom' => 'Terminale C',
        'code' => 'TC',
    ]);

    $this->eleve = Eleve::create([
        'matricule' => '26CD001',
        'nom' => 'EKOMBO',
        'prenom' => 'Samuel',
        'date_naissance' => '2008-03-12',
        'lieu_naissance' => 'Douala',
        'sexe' => 'M',
        'statut' => 'actif',
    ]);

    $this->inscription = Inscription::create([
        'etablissement_id' => $this->etablissement->id,
        'annee_scolaire_id' => $this->annee->id,
        'classe_id' => $this->classe->id,
        'eleve_id' => $this->eleve->id,
        'statut' => 'validee',
        'date_inscription' => '2026-09-01',
    ]);
});

test('affiche l espace de gestion du Conseil de Discipline et des Heures de Consigne', function () {
    /** @var \Tests\TestCase $this */
    actingAs($this->adminUser);

    $response = $this->get(route('administration.discipline.conseil.index', [
        'etablissement_id' => $this->etablissement->id,
        'annee_scolaire_id' => $this->annee->id,
    ]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('administration/discipline/conseil/index')
        ->has('sessions')
        ->has('consignes')
        ->has('stats')
        ->has('inscriptions')
    );
});

test('permet de planifier et gerer une session de Conseil de Discipline', function () {
    /** @var \Tests\TestCase $this */
    actingAs($this->adminUser);

    // 1. Création de session
    $postResponse = $this->post(route('administration.discipline.conseil.sessions.store'), [
        'etablissement_id' => $this->etablissement->id,
        'annee_scolaire_id' => $this->annee->id,
        'numero_session' => 'CD-2026/S1',
        'date_conseil' => '2026-10-15 09:00:00',
        'lieu' => 'Salle des Actes',
        'president_seance' => 'M. Le Proviseur',
        'rapporteur' => 'M. Le Censeur Principal',
        'membres_presents' => 'Proviseur, Censeur, SG, Représentant Enseignants, Président APEE',
        'statut' => 'planifie',
    ]);

    $postResponse->assertSessionHasNoErrors();
    $this->assertDatabaseHas('conseils_discipline', [
        'numero_session' => 'CD-2026/S1',
        'president_seance' => 'M. Le Proviseur',
    ]);

    $session = ConseilDiscipline::first();

    // 2. Traduction d'un élève (Ajout d'un dossier)
    $dossierResponse = $this->post(route('administration.discipline.conseil.dossiers.store', $session), [
        'inscription_id' => $this->inscription->id,
        'motifs_renvoi' => 'Bagarre rangée dans l enceinte du lycée et insultes envers surveillant',
        'plaidoirie_eleve' => 'Reconnaissance partielle des faits et excuses formulées',
        'decision_finale' => 'exclusion_temporaire',
        'nombre_jours_exclusion' => 8,
        'parent_present' => true,
        'is_convocation_envoyee' => true,
    ]);

    $dossierResponse->assertSessionHasNoErrors();
    $this->assertDatabaseHas('dossiers_conseil_discipline', [
        'conseil_discipline_id' => $session->id,
        'inscription_id' => $this->inscription->id,
        'decision_finale' => 'exclusion_temporaire',
        'nombre_jours_exclusion' => 8,
    ]);

    $dossier = DossierConseilDiscipline::first();

    // 3. Impression de la convocation PDF
    $convocResponse = $this->get(route('administration.discipline.conseil.dossiers.convocation-pdf', $dossier));
    $convocResponse->assertOk();
    $convocResponse->assertHeader('Content-Type', 'application/pdf');

    // 4. Impression du PV officiel de session en PDF
    $pvResponse = $this->get(route('administration.discipline.conseil.sessions.pv-pdf', $session));
    $pvResponse->assertOk();
    $pvResponse->assertHeader('Content-Type', 'application/pdf');
});

test('enregistre et modifie une séance d heure de consigne', function () {
    /** @var \Tests\TestCase $this */
    actingAs($this->adminUser);

    // 1. Enregistrement
    $postResponse = $this->post(route('administration.discipline.heures-consigne.store'), [
        'etablissement_id' => $this->etablissement->id,
        'annee_scolaire_id' => $this->annee->id,
        'inscription_id' => $this->inscription->id,
        'date_consigne' => '2026-10-17',
        'heure_debut' => '08:00',
        'heure_fin' => '12:00',
        'nombre_heures' => 4,
        'motif' => 'Retards répétitifs et insolence en classe',
        'travail_assigne' => 'Corvée de nettoyage et devoirs de sciences sous surveillance',
        'surveillant_responsable' => 'M. MBA (Surveillant Général)',
        'statut' => 'programmee',
    ]);

    $postResponse->assertSessionHasNoErrors();
    $this->assertDatabaseHas('heures_consigne', [
        'inscription_id' => $this->inscription->id,
        'nombre_heures' => 4,
        'statut' => 'programmee',
    ]);

    $consigne = HeureConsigne::first();

    // 2. Mise à jour de statut (Effectuée)
    $putResponse = $this->put(route('administration.discipline.heures-consigne.update', $consigne), [
        'statut' => 'effectuee',
        'observations' => 'Consigne purgée avec assiduité',
    ]);

    $putResponse->assertSessionHasNoErrors();
    $this->assertDatabaseHas('heures_consigne', [
        'id' => $consigne->id,
        'statut' => 'effectuee',
    ]);
});
