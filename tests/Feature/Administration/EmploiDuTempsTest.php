<?php

namespace Tests\Feature\Administration;

use App\Models\AnneeScolaire;
use App\Models\Attribution;
use App\Models\Classe;
use App\Models\Cycle;
use App\Models\Etablissement;
use App\Models\Institution;
use App\Models\Matiere;
use App\Models\Niveau;
use App\Models\Personnel;
use App\Models\Salle;
use App\Models\SeanceEmploiTemps;
use App\Models\Section;
use App\Models\User;
use App\Services\EmploiDuTempsService;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Tests fonctionnels pour le Module 13 : Emplois du Temps & Gestion des Salles (Gesco V1.1).
 *
 * Valide :
 * - Le contrôle d'accès RBAC (emplois_du_temps.view, emplois_du_temps.manage).
 * - La gestion du répertoire des locaux et salles d'enseignement.
 * - La planification d'un cours hebdomadaire sans collision.
 * - Le moteur anti-conflits systématique :
 *   * Conflit Enseignant (même enseignant sur 2 cours simultanés).
 *   * Conflit Salle (même local pour 2 cours en chevauchement).
 *   * Conflit Classe (même classe avec 2 matières en même temps).
 * - Les annulations de cours avec motif (Gesco 13.4).
 * - L'affectation d'enseignants remplaçants avec contrôle d'agenda (Gesco 13.4).
 * - Le décompte RH automatique des heures effectives pour la paie / vacations (Gesco 13.5).
 */
class EmploiDuTempsTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    protected User $unauthorizedUser;
    protected Salle $salle1;
    protected Salle $salle2;
    protected Classe $classe2;
    protected Personnel $enseignant2;
    protected Attribution $attribution1;
    protected Attribution $attribution2;
    protected Attribution $attribution3;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Permissions
        foreach (['emplois_du_temps.view', 'emplois_du_temps.manage'] as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $adminRole->syncPermissions(Permission::all());

        $this->adminUser = User::factory()->create(['is_active' => true]);
        $this->adminUser->assignRole($adminRole);

        $this->unauthorizedUser = User::factory()->create(['is_active' => true]);

        // 2. Structure académique
        $this->institution = Institution::firstOrCreate(
            ['code' => 'INST-EDT'],
            ['nom' => 'Institution Emploi du Temps', 'sigle' => 'IEDT']
        );

        $this->etablissement = Etablissement::firstOrCreate(
            ['code' => 'ETAB-EDT'],
            [
                'institution_id' => $this->institution->id,
                'nom' => 'Collège Emploi du Temps',
                'statut_juridique' => 'Public',
                'ordre_enseignement' => 'Secondaire General',
            ]
        );

        $this->section = Section::firstOrCreate(
            ['code' => 'SEC-FR-EDT', 'etablissement_id' => $this->etablissement->id],
            ['nom' => 'Section Francophone']
        );

        $this->cycle = Cycle::firstOrCreate(
            ['code' => 'CYC-1-EDT', 'section_id' => $this->section->id],
            ['nom' => 'Premier Cycle', 'ordre' => 1]
        );

        $this->niveau = Niveau::firstOrCreate(
            ['code' => 'NIV-6-EDT', 'cycle_id' => $this->cycle->id],
            ['nom' => '6ème EDT', 'ordre' => 1]
        );

        $this->annee = AnneeScolaire::firstOrCreate(
            ['code' => '2026-2027'],
            [
                'nom' => 'Année 2026-2027',
                'date_debut' => '2026-09-01',
                'date_fin' => '2027-06-30',
                'statut' => 'en_cours',
                'is_current' => true,
            ]
        );

        $this->classe = Classe::firstOrCreate(
            ['code' => '6M1-EDT', 'niveau_id' => $this->niveau->id],
            ['nom' => '6ème M1 EDT', 'capacite_max' => 50, 'is_active' => true]
        );

        $this->classe2 = Classe::firstOrCreate(
            ['code' => '6M2-EDT', 'niveau_id' => $this->niveau->id],
            ['nom' => '6ème M2 EDT', 'capacite_max' => 50, 'is_active' => true]
        );

        $this->matiere = Matiere::firstOrCreate(
            ['code' => 'MATH-EDT'],
            ['nom' => 'Mathématiques', 'code_matiere' => 'MATH']
        );

        $matiere2 = Matiere::firstOrCreate(
            ['code' => 'FRAN-EDT'],
            ['nom' => 'Français', 'code_matiere' => 'FRAN']
        );

        // Enseignants
        $this->enseignant = Personnel::firstOrCreate(
            ['matricule' => 'ENS-EDT-01', 'etablissement_id' => $this->etablissement->id],
            ['nom' => 'FOTSO', 'prenom' => 'Jean', 'sexe' => 'M', 'telephone' => '+237699112233', 'statut_activite' => 'actif']
        );

        $this->enseignant2 = Personnel::firstOrCreate(
            ['matricule' => 'ENS-EDT-02', 'etablissement_id' => $this->etablissement->id],
            ['nom' => 'NGUEMA', 'prenom' => 'Marc', 'sexe' => 'M', 'telephone' => '+237699445566', 'statut_activite' => 'actif']
        );

        // Attributions
        $this->attribution1 = Attribution::firstOrCreate(
            ['annee_scolaire_id' => $this->annee->id, 'classe_id' => $this->classe->id, 'matiere_id' => $this->matiere->id],
            ['personnel_id' => $this->enseignant->id, 'volume_horaire_reel' => 4]
        );

        $this->attribution2 = Attribution::firstOrCreate(
            ['annee_scolaire_id' => $this->annee->id, 'classe_id' => $this->classe2->id, 'matiere_id' => $this->matiere->id],
            ['personnel_id' => $this->enseignant->id, 'volume_horaire_reel' => 4]
        );

        $this->attribution3 = Attribution::firstOrCreate(
            ['annee_scolaire_id' => $this->annee->id, 'classe_id' => $this->classe->id, 'matiere_id' => $matiere2->id],
            ['personnel_id' => $this->enseignant2->id, 'volume_horaire_reel' => 4]
        );

        // Salles
        $this->salle1 = Salle::firstOrCreate(
            ['etablissement_id' => $this->etablissement->id, 'code' => 'S101'],
            ['nom' => 'Salle 101', 'type_salle' => 'standard', 'capacite_places' => 50, 'statut' => 'disponible']
        );

        $this->salle2 = Salle::firstOrCreate(
            ['etablissement_id' => $this->etablissement->id, 'code' => 'S102'],
            ['nom' => 'Salle 102', 'type_salle' => 'standard', 'capacite_places' => 50, 'statut' => 'disponible']
        );
    }

    /**
     * Vérifie le refus d'accès sans permission (403).
     */
    public function test_acces_refuse_sans_permission_emploi_du_temps(): void
    {
        $response = $this->actingAs($this->unauthorizedUser)
            ->get(route('administration.emplois-du-temps.index'));

        $response->assertStatus(403);
    }

    /**
     * Vérifie l'affichage de l'index des emplois du temps avec ses props Inertia.
     */
    public function test_administrateur_peut_acceder_espace_emplois_du_temps(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('administration.emplois-du-temps.index', [
                'etablissement_id' => $this->etablissement->id,
            ]));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('administration/emplois-du-temps/index')
            ->has('seances')
            ->has('classes')
            ->has('salles')
            ->has('enseignants')
            ->has('decomptesRH')
        );
    }

    /**
     * Vérifie la création d'une salle physique.
     */
    public function test_creation_salle_physique(): void
    {
        $postData = [
            'etablissement_id' => $this->etablissement->id,
            'nom' => 'Laboratoire de Physique',
            'code' => 'LAB-PHY',
            'type_salle' => 'laboratoire',
            'capacite_places' => 40,
            'statut' => 'disponible',
            'description' => 'Équipé de paillasses et disjoncteurs individuels',
        ];

        $response = $this->actingAs($this->adminUser)
            ->post(route('administration.emplois-du-temps.salles.store'), $postData);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('salles', [
            'code' => 'LAB-PHY',
            'type_salle' => 'laboratoire',
            'capacite_places' => 40,
        ]);
    }

    /**
     * Vérifie la planification d'un cours hebdomadaire sans collision.
     */
    public function test_planification_cours_sans_conflit(): void
    {
        $postData = [
            'annee_scolaire_id' => $this->annee->id,
            'classe_id' => $this->classe->id,
            'attribution_id' => $this->attribution1->id,
            'salle_id' => $this->salle1->id,
            'jour_semaine' => 'lundi',
            'heure_debut' => '08:00',
            'heure_fin' => '10:00',
        ];

        $response = $this->actingAs($this->adminUser)
            ->post(route('administration.emplois-du-temps.seances.store'), $postData);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('seances_emploi_temps', [
            'classe_id' => $this->classe->id,
            'attribution_id' => $this->attribution1->id,
            'salle_id' => $this->salle1->id,
            'jour_semaine' => 'lundi',
            'statut' => 'actif',
        ]);
    }

    /**
     * Vérifie la détection anti-conflit Enseignant (Gesco 13.3).
     */
    public function test_detection_conflit_enseignant_simultane(): void
    {
        // 1. Séance existante : Prof FOTSO en 6ème M1 le lundi de 08:00 à 10:00
        SeanceEmploiTemps::create([
            'annee_scolaire_id' => $this->annee->id,
            'classe_id' => $this->classe->id,
            'attribution_id' => $this->attribution1->id,
            'salle_id' => $this->salle1->id,
            'jour_semaine' => 'lundi',
            'heure_debut' => '08:00',
            'heure_fin' => '10:00',
            'statut' => 'actif',
        ]);

        // 2. Tentative de planifier le même Prof FOTSO en 6ème M2 le lundi de 09:00 à 11:00 (chevauchement !)
        $postData = [
            'annee_scolaire_id' => $this->annee->id,
            'classe_id' => $this->classe2->id,
            'attribution_id' => $this->attribution2->id,
            'salle_id' => $this->salle2->id,
            'jour_semaine' => 'lundi',
            'heure_debut' => '09:00',
            'heure_fin' => '11:00',
        ];

        $response = $this->actingAs($this->adminUser)
            ->post(route('administration.emplois-du-temps.seances.store'), $postData);

        $response->assertSessionHasErrors('attribution_id');
    }

    /**
     * Vérifie la détection anti-conflit Salle (Gesco 13.3).
     */
    public function test_detection_conflit_salle_simultanee(): void
    {
        // 1. Salle 101 occupée par la 6ème M1 le mardi de 10:00 à 12:00
        SeanceEmploiTemps::create([
            'annee_scolaire_id' => $this->annee->id,
            'classe_id' => $this->classe->id,
            'attribution_id' => $this->attribution1->id,
            'salle_id' => $this->salle1->id,
            'jour_semaine' => 'mardi',
            'heure_debut' => '10:00',
            'heure_fin' => '12:00',
            'statut' => 'actif',
        ]);

        // 2. Tentative d'utiliser la même Salle 101 par Prof NGUEMA pour la 6ème M2 le mardi de 11:00 à 13:00
        $postData = [
            'annee_scolaire_id' => $this->annee->id,
            'classe_id' => $this->classe2->id,
            'attribution_id' => $this->attribution3->id,
            'salle_id' => $this->salle1->id, // Même salle !
            'jour_semaine' => 'mardi',
            'heure_debut' => '11:00',
            'heure_fin' => '13:00',
        ];

        $response = $this->actingAs($this->adminUser)
            ->post(route('administration.emplois-du-temps.seances.store'), $postData);

        $response->assertSessionHasErrors('salle_id');
    }

    /**
     * Vérifie la détection anti-conflit Classe (Gesco 13.3).
     */
    public function test_detection_conflit_classe_simultanee(): void
    {
        // 1. 6ème M1 en Maths le mercredi de 08:00 à 10:00
        SeanceEmploiTemps::create([
            'annee_scolaire_id' => $this->annee->id,
            'classe_id' => $this->classe->id,
            'attribution_id' => $this->attribution1->id,
            'salle_id' => $this->salle1->id,
            'jour_semaine' => 'mercredi',
            'heure_debut' => '08:00',
            'heure_fin' => '10:00',
            'statut' => 'actif',
        ]);

        // 2. Tentative de planifier du Français avec Prof NGUEMA dans une autre salle pour la MÊME classe sur le même créneau
        $postData = [
            'annee_scolaire_id' => $this->annee->id,
            'classe_id' => $this->classe->id,
            'attribution_id' => $this->attribution3->id,
            'salle_id' => $this->salle2->id,
            'jour_semaine' => 'mercredi',
            'heure_debut' => '08:30',
            'heure_fin' => '10:30',
        ];

        $response = $this->actingAs($this->adminUser)
            ->post(route('administration.emplois-du-temps.seances.store'), $postData);

        $response->assertSessionHasErrors('classe_id');
    }

    /**
     * Vérifie l'annulation motivée d'une séance (Gesco 13.4).
     */
    public function test_annulation_motivee_d_une_seance(): void
    {
        $seance = SeanceEmploiTemps::create([
            'annee_scolaire_id' => $this->annee->id,
            'classe_id' => $this->classe->id,
            'attribution_id' => $this->attribution1->id,
            'salle_id' => $this->salle1->id,
            'jour_semaine' => 'jeudi',
            'heure_debut' => '14:00',
            'heure_fin' => '16:00',
            'statut' => 'actif',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->post(route('administration.emplois-du-temps.seances.annuler', $seance), [
                'motif_annulation' => 'Enseignant convoqué à un séminaire pédagogique MINESEC',
            ]);

        $response->assertSessionHasNoErrors();
        $seance->refresh();

        $this->assertEquals('annule', $seance->statut);
        $this->assertStringContainsString('séminaire pédagogique', $seance->motif_annulation);
    }

    /**
     * Vérifie l'affectation d'un remplaçant et le décompte des heures RH (Gesco 13.4 & 13.5).
     */
    public function test_remplacement_enseignant_et_decompte_rh(): void
    {
        // 1. Séance de 2 heures le vendredi de 08:00 à 10:00 (FOTSO titulaire)
        $seance = SeanceEmploiTemps::create([
            'annee_scolaire_id' => $this->annee->id,
            'classe_id' => $this->classe->id,
            'attribution_id' => $this->attribution1->id,
            'salle_id' => $this->salle1->id,
            'jour_semaine' => 'vendredi',
            'heure_debut' => '08:00',
            'heure_fin' => '10:00',
            'statut' => 'actif',
        ]);

        // 2. Affectation de Prof NGUEMA comme remplaçant
        $response = $this->actingAs($this->adminUser)
            ->post(route('administration.emplois-du-temps.seances.remplacer', $seance), [
                'remplace_par_personnel_id' => $this->enseignant2->id,
                'motif_annulation' => 'Remplacement d’urgence',
            ]);

        $response->assertSessionHasNoErrors();
        $seance->refresh();

        $this->assertEquals('remplace', $seance->statut);
        $this->assertEquals($this->enseignant2->id, $seance->remplace_par_personnel_id);

        // 3. Vérification du décompte RH (Gesco 13.5)
        $service = app(EmploiDuTempsService::class);
        $decompteTitulaire = $service->calculerDecompteHeuresEnseignant($this->annee->id, $this->enseignant->id);
        $decompteRemplacant = $service->calculerDecompteHeuresEnseignant($this->annee->id, $this->enseignant2->id);

        // Titulaire : cours remplacé compté comme heure non dispensée personnellement
        $this->assertEquals(2.0, $decompteTitulaire['heures_annulees']);

        // Remplaçant : 2h de remplacement ajoutées à son crédit effectif
        $this->assertEquals(2.0, $decompteRemplacant['heures_remplacement']);
        $this->assertEquals(2.0, $decompteRemplacant['total_heures_effectives']);
    }
}
