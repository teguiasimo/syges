<?php

/** @var \Tests\TestCase $this */

use App\Models\AnneeScolaire;
use App\Models\Attribution;
use App\Models\Classe;
use App\Models\Cycle;
use App\Models\DispenseEleve;
use App\Models\Eleve;
use App\Models\Etablissement;
use App\Models\Inscription;
use App\Models\Institution;
use App\Models\Matiere;
use App\Models\MatiereNiveau;
use App\Models\Niveau;
use App\Models\Periode;
use App\Models\Section;
use App\Models\User;
use App\Services\BulletinCalculationService;
use Spatie\Permission\Models\Role;
use function Pest\Laravel\actingAs;

/**
 * Tests de fonctionnalités pour les Dérogations & Dispenses Officielles (Phase 4).
 *
 * Vérifie :
 * - L'affichage de la page de gestion des dispenses et des compteurs statistiques.
 * - La création, la mise à jour et la modification de statut d'une dispense.
 * - La génération du Procès-Verbal officiel (PV) au format PDF.
 * - La neutralisation automatique du coefficient dans le calcul du bulletin trimestriel.
 */

beforeEach(function () {
    /** @var \Tests\TestCase $this */
    $this->withoutVite();

    Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);

    $this->adminUser = User::factory()->create();
    $this->adminUser->assignRole('super-admin');

    $this->institution = Institution::create([
        'code' => 'INST-DISP',
        'nom' => 'Délégation Régionale Centre',
        'ville' => 'Yaoundé',
    ]);

    $this->etablissement = Etablissement::create([
        'institution_id' => $this->institution->id,
        'code' => 'LYCEE-DISP',
        'nom' => 'Lycée Classique de Test',
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
        'nom' => 'Section Francophone',
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
        'nom' => 'Première',
        'code' => '1ERE',
        'ordre' => 6,
    ]);

    $this->classe = Classe::create([
        'niveau_id' => $this->niveau->id,
        'nom' => 'Première C',
        'code' => '1C',
    ]);

    $this->matiereEps = Matiere::create([
        'nom' => 'Éducation Physique et Sportive',
        'code' => 'EPS',
        'is_active' => true,
    ]);

    $this->matiereMath = Matiere::create([
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

    $this->eleve = Eleve::create([
        'matricule' => '26DISP001',
        'nom' => 'MBARGA',
        'prenom' => 'Alain',
        'date_naissance' => '2010-05-14',
        'lieu_naissance' => 'Yaoundé',
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

test('affiche la page de gestion des dispenses et dérogations officielles', function () {
    /** @var \Tests\TestCase $this */
    actingAs($this->adminUser);

    $response = $this->get(route('administration.pedagogie.dispenses.index', [
        'etablissement_id' => $this->etablissement->id,
        'annee_scolaire_id' => $this->annee->id,
    ]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('administration/pedagogie/dispenses/index')
        ->has('dispenses')
        ->has('stats')
        ->has('inscriptions')
        ->has('matieres')
    );
});

test('permet d enregistrer, modifier et supprimer une dispense officielle', function () {
    /** @var \Tests\TestCase $this */
    actingAs($this->adminUser);

    // 1. Enregistrement
    $postResponse = $this->post(route('administration.pedagogie.dispenses.store'), [
        'etablissement_id' => $this->etablissement->id,
        'annee_scolaire_id' => $this->annee->id,
        'inscription_id' => $this->inscription->id,
        'matiere_id' => $this->matiereEps->id,
        'type_dispense' => 'medicale',
        'motif' => 'Entorse sévère cheville droite avec certificat d inaptitude',
        'reference_certificat' => 'MED-2026-789',
        'medecin_ou_autorite' => 'Dr. ATANGANA (Hôpital Central)',
        'date_debut' => '2026-09-05',
        'neutraliser_coefficient' => true,
        'statut' => 'validee',
    ]);

    $postResponse->assertSessionHasNoErrors();
    $this->assertDatabaseHas('dispenses_eleves', [
        'inscription_id' => $this->inscription->id,
        'matiere_id' => $this->matiereEps->id,
        'reference_certificat' => 'MED-2026-789',
        'neutraliser_coefficient' => 1,
    ]);

    $dispense = DispenseEleve::first();

    // 2. Modification
    $putResponse = $this->put(route('administration.pedagogie.dispenses.update', $dispense), [
        'matiere_id' => $this->matiereEps->id,
        'type_dispense' => 'medicale',
        'motif' => 'Mise à jour motif médical : rééducation prolongée',
        'reference_certificat' => 'MED-2026-789-B',
        'medecin_ou_autorite' => 'Dr. ATANGANA',
        'neutraliser_coefficient' => true,
        'statut' => 'validee',
    ]);

    $putResponse->assertSessionHasNoErrors();
    $this->assertDatabaseHas('dispenses_eleves', [
        'id' => $dispense->id,
        'reference_certificat' => 'MED-2026-789-B',
    ]);

    // 3. Changement de statut rapide
    $patchResponse = $this->patch(route('administration.pedagogie.dispenses.statut', $dispense), [
        'statut' => 'rejetee',
    ]);
    $patchResponse->assertSessionHasNoErrors();
    $this->assertDatabaseHas('dispenses_eleves', [
        'id' => $dispense->id,
        'statut' => 'rejetee',
    ]);

    // 4. Suppression
    $deleteResponse = $this->delete(route('administration.pedagogie.dispenses.destroy', $dispense));
    $deleteResponse->assertSessionHasNoErrors();
    $this->assertDatabaseMissing('dispenses_eleves', [
        'id' => $dispense->id,
    ]);
});

test('permet d exporter le Procès-Verbal officiel des dispenses en PDF', function () {
    /** @var \Tests\TestCase $this */
    actingAs($this->adminUser);

    DispenseEleve::create([
        'etablissement_id' => $this->etablissement->id,
        'annee_scolaire_id' => $this->annee->id,
        'inscription_id' => $this->inscription->id,
        'eleve_id' => $this->eleve->id,
        'matiere_id' => $this->matiereEps->id,
        'type_dispense' => 'medicale',
        'motif' => 'Inaptitude physique',
        'reference_certificat' => 'PV-001',
        'neutraliser_coefficient' => true,
        'statut' => 'validee',
    ]);

    $response = $this->get(route('administration.pedagogie.dispenses.pv-pdf', [
        'etablissement_id' => $this->etablissement->id,
        'annee_scolaire_id' => $this->annee->id,
    ]));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');
});

test('neutralise le coefficient de la matière dispensée dans le moteur de calcul de bulletin', function () {
    /** @var \Tests\TestCase $this */
    actingAs($this->adminUser);

    $groupe = \App\Models\GroupeMatiere::create([
        'code' => 'GRP-TEST',
        'nom' => 'Matières Principales',
        'ordre' => 1,
    ]);

    // Configuration des coefficients : Math (coef 4), EPS (coef 2)
    MatiereNiveau::create([
        'niveau_id' => $this->niveau->id,
        'matiere_id' => $this->matiereMath->id,
        'groupe_matiere_id' => $groupe->id,
        'coefficient' => 4.0,
        'is_comptabilisee' => true,
    ]);

    MatiereNiveau::create([
        'niveau_id' => $this->niveau->id,
        'matiere_id' => $this->matiereEps->id,
        'groupe_matiere_id' => $groupe->id,
        'coefficient' => 2.0,
        'is_comptabilisee' => true,
    ]);

    $enseignant = \App\Models\Personnel::create([
        'matricule' => 'ENS001',
        'etablissement_id' => $this->etablissement->id,
        'nom' => 'KAMGA',
        'prenom' => 'Jean',
        'sexe' => 'M',
        'telephone' => '670000000',
        'fonction_principale' => 'Enseignant',
        'type_contrat' => 'permanent',
        'date_embauche' => '2020-09-01',
        'statut' => 'actif',
        'is_enseignant' => true,
    ]);

    // Attributions pour la classe
    $attrMath = Attribution::create([
        'classe_id' => $this->classe->id,
        'matiere_id' => $this->matiereMath->id,
        'personnel_id' => $enseignant->id,
        'annee_scolaire_id' => $this->annee->id,
        'is_active' => true,
    ]);

    $attrEps = Attribution::create([
        'classe_id' => $this->classe->id,
        'matiere_id' => $this->matiereEps->id,
        'personnel_id' => $enseignant->id,
        'annee_scolaire_id' => $this->annee->id,
        'is_active' => true,
    ]);

    // Dispense validée en EPS pour l'élève
    DispenseEleve::create([
        'etablissement_id' => $this->etablissement->id,
        'annee_scolaire_id' => $this->annee->id,
        'inscription_id' => $this->inscription->id,
        'eleve_id' => $this->eleve->id,
        'matiere_id' => $this->matiereEps->id,
        'periode_id' => $this->periode->id,
        'type_dispense' => 'medicale',
        'motif' => 'Certificat médical inaptitude EPS',
        'neutraliser_coefficient' => true,
        'statut' => 'validee',
    ]);

    $service = new BulletinCalculationService();
    $resultat = $service->calculerTrimestre($this->classe, $this->periode);

    $eleveResultat = collect($resultat['eleves'])->firstWhere('inscription_id', $this->inscription->id);
    $ligneEps = $eleveResultat['lignes_matieres'][$attrEps->id];

    // Vérifications :
    // 1. La matière EPS est bien marquée dispensée
    $this->assertTrue($ligneEps['is_dispense']);
    // 2. Le coefficient sur le bulletin est neutralisé à 0
    $this->assertEquals(0.0, $ligneEps['coefficient']);
    // 3. Les points de la matière sont 0
    $this->assertEquals(0.0, $ligneEps['points']);
    // 4. L'appréciation mentionne la dispense
    $this->assertStringContainsString('Dispensé', $ligneEps['appreciation']);
});
