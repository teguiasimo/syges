<?php

namespace Tests\Feature\Portail;

use App\Models\AnneeScolaire;
use App\Models\Attribution;
use App\Models\Classe;
use App\Models\CompteFinancierEleve;
use App\Models\Cycle;
use App\Models\DisciplineBulletin;
use App\Models\Eleve;
use App\Models\Etablissement;
use App\Models\Evaluation;
use App\Models\GroupeMatiere;
use App\Models\Inscription;
use App\Models\Institution;
use App\Models\Matiere;
use App\Models\MatiereNiveau;
use App\Models\Niveau;
use App\Models\Note;
use App\Models\Periode;
use App\Models\Personnel;
use App\Models\Section;
use App\Models\User;
use App\Models\VersementCaisse;
use App\Services\PortailParentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Tests fonctionnels pour le Module 16 : Portail Parents & Élèves (Gesco V1.1).
 *
 * Valide :
 * - Le contrôle d'accès au portail parents / élèves.
 * - La détection automatique multi-enfants par le numéro de téléphone principal du tuteur (16.1).
 * - Le basculement en un clic entre les profils des enfants (16.1).
 * - La consultation du suivi académique, de l'emploi du temps et de la discipline (16.2).
 * - L'application rigoureuse du verrou de solvabilité financière bloquant le bulletin PDF en cas d'impayé (11.5 & 16.3).
 * - L'autorisation de téléchargement du bulletin dès que le solde est réglé (16.3).
 * - Le téléchargement des reçus de caisse certifiés avec QR code de sécurité (16.2).
 */
class PortailParentTest extends TestCase
{
    use RefreshDatabase;

    public ?User $parentUser = null;
    public ?User $adminUser = null;
    public ?Etablissement $etablissement = null;
    public ?AnneeScolaire $anneeScolaire = null;
    public ?Classe $classe = null;
    public ?Inscription $inscription1 = null;
    public ?Inscription $inscription2 = null;
    public ?Eleve $enfant1 = null;
    public ?Eleve $enfant2 = null;
    public ?Periode $trimestre1 = null;
    public ?VersementCaisse $versement1 = null;

    /**
     * Configuration initiale du jeu de données pour les tests du portail.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        // 1. Rôles et Utilisateur Parent
        Role::firstOrCreate(['name' => 'parent', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);

        $this->parentUser = User::factory()->create([
            'name' => 'M. Ewane Roger',
            'login' => '699112233',
            'email' => 'ewane.roger@gmail.com',
            'is_active' => true,
        ]);
        $this->parentUser->assignRole('parent');

        $this->adminUser = User::factory()->create([
            'is_active' => true,
        ]);
        $this->adminUser->assignRole('super-admin');

        // 2. Établissement avec Verrou de Solvabilité Activé (Module 11.5)
        $institution = Institution::create([
            'nom' => 'Collège Polyvalent Bilingue',
            'code' => 'CPB',
            'statut' => 'actif',
        ]);

        $this->etablissement = Etablissement::create([
            'institution_id' => $institution->id,
            'nom' => 'Campus Deido',
            'code' => 'CPB-01',
            'email' => 'contact@cpb.cm',
            'telephone' => '237233445566',
            'statut' => 'actif',
            'verrou_solvabilite_actif' => true, // Verrou financier activé
            'mode_bulletin_defaut' => 'sans_competences',
            'type_calcul_trimestre' => 'arithmetique',
        ]);

        $this->anneeScolaire = AnneeScolaire::create([
            'nom' => '2026-2027',
            'code' => '2026-2027',
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-06-30',
            'statut' => 'ouverte',
            'is_current' => true,
        ]);

        $this->trimestre1 = Periode::create([
            'annee_scolaire_id' => $this->anneeScolaire->id,
            'nom' => '1er Trimestre',
            'code' => 'T1',
            'ordre' => 1,
            'date_debut' => '2026-09-01',
            'date_fin' => '2026-11-30',
            'is_active' => true,
        ]);

        $evaluation1 = Evaluation::create([
            'periode_id' => $this->trimestre1->id,
            'nom' => 'Séquence 1',
            'code' => 'SEQ1',
            'ordre' => 1,
            'pourcentage' => 100,
            'date_debut' => '2026-10-01',
            'date_fin' => '2026-10-15',
            'is_active' => true,
        ]);

        $section = Section::create([
            'etablissement_id' => $this->etablissement->id,
            'nom' => 'Section Francophone',
            'code' => 'FR',
        ]);

        $cycle = Cycle::create([
            'section_id' => $section->id,
            'nom' => 'Premier Cycle',
            'code' => 'C1',
            'ordre' => 1,
        ]);

        $niveau = Niveau::create([
            'cycle_id' => $cycle->id,
            'nom' => 'Quatrième',
            'code' => '4EME',
            'ordre' => 3,
        ]);

        $this->classe = Classe::create([
            'niveau_id' => $niveau->id,
            'nom' => '4ème Espagnol',
            'code' => '4ESP',
            'capacite' => 45,
            'is_active' => true,
        ]);

        $groupe = GroupeMatiere::create([
            'etablissement_id' => $this->etablissement->id,
            'nom' => 'Lettres & Langues',
            'code' => 'LANG',
            'ordre' => 1,
        ]);

        $matiereFrancais = Matiere::create([
            'nom' => 'Français',
            'code' => 'FRAN',
        ]);

        MatiereNiveau::create([
            'matiere_id' => $matiereFrancais->id,
            'niveau_id' => $niveau->id,
            'groupe_matiere_id' => $groupe->id,
            'coefficient' => 4,
            'note_maximale' => 20,
            'is_comptabilisee' => true,
        ]);

        $enseignant = Personnel::create([
            'etablissement_id' => $this->etablissement->id,
            'matricule' => 'ENS-042',
            'nom' => 'Ngono',
            'prenom' => 'Jeanne',
            'telephone' => '237677889900',
            'genre' => 'F',
            'fonction_principale' => 'Enseignant',
            'type_contrat' => 'Permanent',
            'statut' => 'Actif',
        ]);

        $attribution = Attribution::create([
            'annee_scolaire_id' => $this->anneeScolaire->id,
            'classe_id' => $this->classe->id,
            'matiere_id' => $matiereFrancais->id,
            'personnel_id' => $enseignant->id,
            'volume_horaire_hebdo' => 4,
            'is_active' => true,
        ]);

        // 3. Deux enfants rattachés au parent M. Ewane Roger (telephone_parent = 699112233)
        // Enfant 1 : Solde impayé (Insolvable -> Verrou actif)
        $this->enfant1 = Eleve::create([
            'matricule' => 'CPB-001',
            'nom' => 'Ewane',
            'prenom' => 'Junior',
            'sexe' => 'M',
            'date_naissance' => '2013-04-10',
            'lieu_naissance' => 'Douala',
            'telephone_parent' => '699112233',
            'email_parent' => 'ewane.roger@gmail.com',
            'nom_tuteur' => 'M. Ewane Roger',
        ]);

        $this->inscription1 = Inscription::create([
            'eleve_id' => $this->enfant1->id,
            'classe_id' => $this->classe->id,
            'annee_scolaire_id' => $this->anneeScolaire->id,
            'date_inscription' => '2026-09-02',
            'statut' => 'validee',
        ]);

        Note::create([
            'inscription_id' => $this->inscription1->id,
            'attribution_id' => $attribution->id,
            'evaluation_id' => $evaluation1->id,
            'valeur_note' => 14.5,
            'statut_presence' => 'present',
            'appreciation' => 'Bon travail d\'ensemble',
        ]);

        DisciplineBulletin::create([
            'inscription_id' => $this->inscription1->id,
            'periode_id' => $this->trimestre1->id,
            'absences_justifiees' => 2,
            'absences_non_justifiees' => 0,
        ]);

        $compte1 = CompteFinancierEleve::create([
            'inscription_id' => $this->inscription1->id,
            'eleve_id' => $this->enfant1->id,
            'etablissement_id' => $this->etablissement->id,
            'annee_scolaire_id' => $this->anneeScolaire->id,
            'debit_initial' => 150000,
            'remise_bourse' => 0,
            'montant_net_exigible' => 150000,
            'total_paye' => 50000,
            'solde_restant' => 100000, // Débiteur
            'statut_financier' => 'insolvable',
        ]);

        $this->versement1 = VersementCaisse::create([
            'compte_financier_id' => $compte1->id,
            'inscription_id' => $this->inscription1->id,
            'etablissement_id' => $this->etablissement->id,
            'personnel_id' => $enseignant->id,
            'reference_recu' => 'REC-2026-0001',
            'montant_total' => 50000,
            'mode_paiement' => 'especes',
            'date_versement' => '2026-09-05 10:00:00',
            'statut' => 'valide',
            'payeur_nom' => 'M. Ewane Roger',
            'payeur_telephone' => '699112233',
        ]);

        // Enfant 2 : Scolarité soldée (Solvable -> Bulletin débloqué)
        $this->enfant2 = Eleve::create([
            'matricule' => 'CPB-002',
            'nom' => 'Ewane',
            'prenom' => 'Grace',
            'sexe' => 'F',
            'date_naissance' => '2015-02-18',
            'lieu_naissance' => 'Douala',
            'telephone_parent' => '699112233',
            'email_parent' => 'ewane.roger@gmail.com',
            'nom_tuteur' => 'M. Ewane Roger',
        ]);

        $this->inscription2 = Inscription::create([
            'eleve_id' => $this->enfant2->id,
            'classe_id' => $this->classe->id,
            'annee_scolaire_id' => $this->anneeScolaire->id,
            'date_inscription' => '2026-09-02',
            'statut' => 'validee',
        ]);

        Note::create([
            'inscription_id' => $this->inscription2->id,
            'attribution_id' => $attribution->id,
            'evaluation_id' => $evaluation1->id,
            'valeur_note' => 16.0,
            'statut_presence' => 'present',
            'appreciation' => 'Très bon travail',
        ]);

        CompteFinancierEleve::create([
            'inscription_id' => $this->inscription2->id,
            'eleve_id' => $this->enfant2->id,
            'etablissement_id' => $this->etablissement->id,
            'annee_scolaire_id' => $this->anneeScolaire->id,
            'debit_initial' => 150000,
            'remise_bourse' => 0,
            'montant_net_exigible' => 150000,
            'total_paye' => 150000,
            'solde_restant' => 0, // Entièrement soldé
            'statut_financier' => 'solde',
        ]);
    }

    /**
     * Teste le contrôle d'accès : les invités sont redirigés vers le login.
     *
     * @return void
     */
    public function test_acces_portail_authentification_requise(): void
    {
        $response = $this->get(route('portail.index'));
        $response->assertRedirect(route('login'));

        $response = $this->actingAs($this->parentUser)->get(route('portail.index'));
        $response->assertStatus(200);
    }

    /**
     * Teste la détection automatique des enfants par le téléphone du parent (Gesco 16.1).
     *
     * @return void
     */
    public function test_detection_multi_enfants_par_telephone_parent(): void
    {
        /** @var PortailParentService $service */
        $service = app(PortailParentService::class);

        $enfants = $service->getEnfantsRattaches($this->parentUser);

        // M. Ewane Roger a 2 enfants rattachés à son numéro 699112233
        $this->assertCount(2, $enfants);
        $matricules = $enfants->pluck('matricule')->all();
        $this->assertContains('CPB-001', $matricules);
        $this->assertContains('CPB-002', $matricules);
    }

    /**
     * Teste le basculement d'enfant actif (Gesco 16.1).
     *
     * @return void
     */
    public function test_basculement_enfant_actif(): void
    {
        $response = $this->actingAs($this->parentUser)
            ->get(route('portail.select-enfant', $this->enfant2->id));

        $response->assertRedirect(route('portail.index', ['enfant_id' => $this->enfant2->id]));
        $response->assertSessionHas('portail_enfant_id', $this->enfant2->id);
    }

    /**
     * Teste la consultation du profil complet (notes, assiduité, finances) (Gesco 16.2).
     *
     * @return void
     */
    public function test_profil_complet_enfant_notes_et_finances(): void
    {
        /** @var PortailParentService $service */
        $service = app(PortailParentService::class);

        $profil = $service->getProfilCompletEnfant($this->enfant1, $this->anneeScolaire->id, $this->trimestre1->id);

        $this->assertEquals('CPB-001', $profil['eleve']['matricule']);
        $this->assertEquals('4ème Espagnol', $profil['classe']['nom']);

        // Notes obtenues : 14.50 en Français
        $this->assertEquals(14.5, $profil['academique']['moyenne_generale']);

        // Volet financier : 150 000 net, 50 000 payé, 100 000 restant
        $this->assertEquals(150000, $profil['finances']['montant_net_exigible']);
        $this->assertEquals(50000, $profil['finances']['total_paye']);
        $this->assertEquals(100000, $profil['finances']['solde_restant']);
    }

    /**
     * Teste le verrou de solvabilité financière bloquant le téléchargement du bulletin (Gesco 11.5 & 16.3).
     *
     * @return void
     */
    public function test_verrou_solvabilite_bloque_bulletin_si_solde_impaye(): void
    {
        /** @var PortailParentService $service */
        $service = app(PortailParentService::class);

        // 1. Profil de l'enfant 1 (débiteur de 100 000 FCFA avec verrou actif)
        $profil = $service->getProfilCompletEnfant($this->enfant1, $this->anneeScolaire->id, $this->trimestre1->id);

        $this->assertTrue($profil['verrou_solvabilite']['est_bloque']);
        $this->assertStringContainsString('Accès restreint. Veuillez régulariser le solde financier de Ewane Junior', $profil['verrou_solvabilite']['message']);

        // 2. Tentative de téléchargement du bulletin PDF -> Doit être rejetée (403 Forbidden)
        $response = $this->actingAs($this->parentUser)
            ->get(route('portail.bulletin.download', [
                'inscription' => $this->inscription1->id,
                'periode_id' => $this->trimestre1->id,
            ]));

        $response->assertStatus(403);
    }

    /**
     * Teste l'autorisation de téléchargement du bulletin lorsque l'élève est en règle financière (Gesco 16.3).
     *
     * @return void
     */
    public function test_bulletin_accessible_si_scolarite_soldee(): void
    {
        /** @var PortailParentService $service */
        $service = app(PortailParentService::class);

        // Profil de l'enfant 2 (soldé à 100%)
        $profil = $service->getProfilCompletEnfant($this->enfant2, $this->anneeScolaire->id, $this->trimestre1->id);

        $this->assertFalse($profil['verrou_solvabilite']['est_bloque']);
        $this->assertNull($profil['verrou_solvabilite']['message']);

        // Téléchargement du bulletin PDF autorisé
        $response = $this->actingAs($this->parentUser)
            ->get(route('portail.bulletin.download', [
                'inscription' => $this->inscription2->id,
                'periode_id' => $this->trimestre1->id,
            ]));

        $response->assertStatus(200);
        $this->assertEquals('application/pdf', $response->headers->get('content-type'));
    }

    /**
     * Teste l'accès au bulletin si le verrou de solvabilité est désactivé sur l'établissement (Gesco 11.5).
     *
     * @return void
     */
    public function test_bulletin_accessible_si_verrou_solvabilite_desactive(): void
    {
        // Désactiver le verrou sur l'établissement
        $this->etablissement->update(['verrou_solvabilite_actif' => false]);

        // Même avec 100 000 FCFA de dette, l'enfant 1 peut télécharger son bulletin
        $response = $this->actingAs($this->parentUser)
            ->get(route('portail.bulletin.download', [
                'inscription' => $this->inscription1->id,
                'periode_id' => $this->trimestre1->id,
            ]));

        $response->assertStatus(200);
        $this->assertEquals('application/pdf', $response->headers->get('content-type'));
    }

    /**
     * Teste le téléchargement du reçu de caisse certifié avec QR Code (Gesco 11.3 & 16.2).
     *
     * @return void
     */
    public function test_telechargement_recu_caisse_pdf(): void
    {
        $response = $this->actingAs($this->parentUser)
            ->get(route('portail.recu.download', $this->versement1->id));

        $response->assertStatus(200);
        $this->assertEquals('application/pdf', $response->headers->get('content-type'));
    }
}
