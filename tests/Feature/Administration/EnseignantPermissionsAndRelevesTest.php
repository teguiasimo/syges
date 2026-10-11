<?php

use App\Models\AnneeScolaire;
use App\Models\Attribution;
use App\Models\Classe;
use App\Models\Cycle;
use App\Models\Departement;
use App\Models\Etablissement;
use App\Models\Evaluation;
use App\Models\Inscription;
use App\Models\Institution;
use App\Models\Matiere;
use App\Models\Niveau;
use App\Models\Periode;
use App\Models\Personnel;
use App\Models\Section;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    // Initialisation de la matrice de permissions et des rôles
    $this->seed(DatabaseSeeder::class);

    // Création de l'infrastructure multi-tenant minimale
    $this->institution = Institution::create([
        'nom' => 'Groupe Scolaire Excellence',
        'code' => 'GSE',
    ]);

    $this->etablissement = Etablissement::create([
        'institution_id' => $this->institution->id,
        'nom' => 'Campus Principal',
        'code' => 'CAMP-PRI',
        'is_active' => true,
    ]);

    $this->annee = AnneeScolaire::create([
        'code' => '2026-2027',
        'nom' => '2026-2027',
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
        'nom' => 'Secondaire Général',
        'code' => 'CYC1',
        'numero' => 1,
    ]);

    $this->niveau = Niveau::create([
        'cycle_id' => $this->cycle->id,
        'nom' => '3ème',
        'code' => 'NIV3',
        'ordre' => 3,
    ]);

    $this->classe = Classe::create([
        'etablissement_id' => $this->etablissement->id,
        'niveau_id' => $this->niveau->id,
        'nom' => '3ème A',
        'code' => '3A',
        'capacite_max' => 50,
        'is_active' => true,
    ]);

    $this->departement = Departement::create([
        'institution_id' => $this->institution->id,
        'nom' => 'Informatique',
        'code' => 'INFO',
    ]);

    $this->matiere = Matiere::create([
        'nom' => 'Informatique Fondamentale',
        'code' => 'INF101',
    ]);

    // Création de l'enseignant et de son compte utilisateur
    $this->teacherUser = User::factory()->create([
        'name' => 'Professeur Informatique',
        'email' => 'prof.info@syges.local',
        'login' => 'profinfo',
        'etablissement_id' => $this->etablissement->id,
        'is_active' => true,
    ]);
    $this->teacherUser->assignRole('enseignant');

    $this->personnel = Personnel::create([
        'matricule' => 'ENS-2026-001',
        'user_id' => $this->teacherUser->id,
        'etablissement_id' => $this->etablissement->id,
        'departement_id' => $this->departement->id,
        'nom' => 'Professeur',
        'prenom' => 'Informatique',
        'sexe' => 'M',
        'telephone' => '+237690000001',
        'email' => 'prof.info@syges.local',
        'fonction_principale' => 'Enseignant',
        'type_contrat' => 'Permanent',
        'date_embauche' => '2026-09-01',
        'statut' => 'actif',
        'is_enseignant' => true,
    ]);

    // Attribution de cours à cet enseignant
    $this->attribution = Attribution::create([
        'annee_scolaire_id' => $this->annee->id,
        'personnel_id' => $this->personnel->id,
        'classe_id' => $this->classe->id,
        'matiere_id' => $this->matiere->id,
        'volume_horaire_reel' => 4,
        'is_active' => true,
    ]);

    // Période et évaluation
    $this->periode = Periode::create([
        'etablissement_id' => $this->etablissement->id,
        'annee_scolaire_id' => $this->annee->id,
        'nom' => 'Trimestre 1',
        'code' => 'T1',
        'numero' => 1,
        'date_debut' => '2026-09-01',
        'date_fin' => '2026-11-30',
        'is_active' => true,
    ]);

    $this->evaluation = Evaluation::create([
        'periode_id' => $this->periode->id,
        'nom' => 'Séquence 1',
        'code' => 'SEQ1',
        'ordre' => 1,
        'date_debut' => '2026-10-15',
        'date_fin' => '2026-10-20',
        'pourcentage' => 100,
        'is_active' => true,
    ]);
});

test('le rôle enseignant ne possède que des permissions pédagogiques strictes', function () {
    $teacherRole = Role::findByName('enseignant', 'web');
    $permissions = $teacherRole->permissions->pluck('name')->toArray();

    // Doit avoir :
    expect($permissions)->toContain('notes.view')
        ->toContain('notes.create')
        ->toContain('releves_notes.view')
        ->toContain('releves_notes.print')
        ->toContain('progression.view')
        ->toContain('emplois_du_temps.view');

    // Ne doit absolument PAS avoir :
    expect($permissions)->not->toContain('institutions.view')
        ->not->toContain('etablissements.view')
        ->not->toContain('matieres.view')
        ->not->toContain('eleves.view')
        ->not->toContain('attributions.view')
        ->not->toContain('bulletins.view')
        ->not->toContain('finances.view')
        ->not->toContain('discipline.view');
});

test('un enseignant accède au tableau de bord sans aucune fuite financière ni administrative', function () {
    $this->actingAs($this->teacherUser);

    $response = $this->get(route('dashboard'));
    $response->assertOk();

    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->where('hasAccess', true)
        ->where('isEnseignantDashboard', true)
        ->where('finances', null)
        ->where('demographie', null)
        ->where('pedagogie', null)
        ->has('enseignantStats', fn (Assert $stats) => $stats
            ->where('matricule', 'ENS-2026-001')
            ->where('totalClasses', 1)
            ->where('totalHeures', 4)
            ->has('attributions', 1)
            ->etc()
        )
    );
});

test('un enseignant se voit refuser l\'accès aux modules administratifs', function () {
    $this->actingAs($this->teacherUser);

    // Tentative d'accès à la structure et au campus
    $responseInst = $this->get(route('administration.institution.index'));
    $responseInst->assertForbidden();

    // Tentative d'accès au catalogue des matières
    $responseMat = $this->get(route('administration.matieres.index'));
    $responseMat->assertForbidden();

    // Tentative d'accès aux inscriptions élèves
    $responseEleves = $this->get(route('administration.eleves.index'));
    $responseEleves->assertForbidden();

    // Tentative d'accès aux bulletins scolaires
    $responseBulletins = $this->get(route('administration.bulletins.index'));
    $responseBulletins->assertForbidden();
});

test('un enseignant peut accéder à la consultation des relevés de notes pour ses cours', function () {
    $this->actingAs($this->teacherUser);

    $response = $this->get(route('administration.notes.releves.index', [
        'attribution_id' => $this->attribution->id,
        'annee_id' => $this->annee->id,
    ]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('administration/notes/releves')
        ->has('attributions', 1)
        ->where('selectedAttribution.id', $this->attribution->id)
    );
});

test('un enseignant peut exporter sa fiche de relevé de notes au format PDF officiel', function () {
    $this->actingAs($this->teacherUser);

    $response = $this->get(route('administration.notes.releves.pdf', [
        'attribution_id' => $this->attribution->id,
        'annee_id' => $this->annee->id,
        'mode' => 'vierge',
    ]));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toBe('application/pdf');
});

test('un enseignant ne peut pas exporter le relevé de notes d\'un autre enseignant', function () {
    // Création d'un second enseignant et d'une autre attribution
    $otherTeacher = User::factory()->create([
        'etablissement_id' => $this->etablissement->id,
    ]);
    $otherTeacher->assignRole('enseignant');

    $otherPersonnel = Personnel::create([
        'matricule' => 'ENS-2026-002',
        'user_id' => $otherTeacher->id,
        'etablissement_id' => $this->etablissement->id,
        'nom' => 'Autre',
        'prenom' => 'Prof',
        'sexe' => 'M',
        'telephone' => '+237690000002',
        'fonction_principale' => 'Enseignant',
        'type_contrat' => 'Permanent',
        'date_embauche' => '2026-09-01',
        'statut' => 'actif',
        'is_enseignant' => true,
    ]);

    $otherMatiere = Matiere::create([
        'nom' => 'Mathématiques',
        'code' => 'MATH101',
    ]);

    $otherAttribution = Attribution::create([
        'annee_scolaire_id' => $this->annee->id,
        'personnel_id' => $otherPersonnel->id,
        'classe_id' => $this->classe->id,
        'matiere_id' => $otherMatiere->id,
        'volume_horaire_reel' => 2,
        'is_active' => true,
    ]);

    // L'enseignant 1 essaie de tirer le relevé de l'enseignant 2
    $this->actingAs($this->teacherUser);

    $response = $this->get(route('administration.notes.releves.pdf', [
        'attribution_id' => $otherAttribution->id,
        'annee_id' => $this->annee->id,
    ]));

    // Doit être interdit (403)
    $response->assertForbidden();
});
