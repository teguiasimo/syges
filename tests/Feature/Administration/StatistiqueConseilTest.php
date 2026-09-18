<?php

namespace Tests\Feature\Administration;

use App\Models\AnneeScolaire;
use App\Models\Attribution;
use App\Models\Classe;
use App\Models\CompteFinancierEleve;
use App\Models\Cycle;
use App\Models\DeliberationConseil;
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
use App\Services\StatistiqueConseilService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Tests fonctionnels pour le Module 15 : Tableau de Bord, Statistiques & Conseil de Classe (Gesco V1.1).
 *
 * Valide :
 * - Le contrôle d'accès RBAC (statistiques.view, deliberations.view, deliberations.manage, deliberations.valider).
 * - Le calcul rigoureux des statistiques démographiques, de parité et de densitométrie (Gesco 15.1).
 * - La détection précoce des élèves en situation de décrochage scolaire (Gesco 15.3).
 * - L'agrégation financière de direction (taux de recouvrement, créances) (Gesco 15.3).
 * - L'attribution automatique des mentions et leur déchéance d'office pour indiscipline (>= 15h) ou note éliminatoire (Gesco 15.2, 12.4 & 4.3).
 * - Le moteur de proposition de passage annuel (Promu / Redouble / Exclu) (Gesco 2.6 Étape A).
 * - La validation humaine obligatoire de promotion par la Direction (Gesco 2.6 Étape B).
 * - La génération des bordereaux officiels ministériels et des Procès-Verbaux de délibération.
 */
class StatistiqueConseilTest extends TestCase
{
    use RefreshDatabase;

    public ?User $unauthorizedUser = null;
    public ?User $directorUser = null;
    public ?User $adminUser = null;
    public ?Etablissement $etablissement = null;
    public ?AnneeScolaire $anneeScolaire = null;
    public ?Classe $classe = null;
    public ?Inscription $inscription1 = null;
    public ?Inscription $inscription2 = null;
    public ?Inscription $inscription3 = null;
    public ?Periode $trimestre1 = null;

    /**
     * Initialisation de l'environnement de test du Module 15.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        // 1. Permissions RBAC
        $permissions = [
            'statistiques.view',
            'deliberations.view',
            'deliberations.manage',
            'deliberations.valider',
        ];
        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $directorRole = Role::firstOrCreate(['name' => 'directeur-etudes', 'guard_name' => 'web']);
        $directorRole->syncPermissions($permissions);

        $this->unauthorizedUser = User::factory()->create();

        $this->directorUser = User::factory()->create();
        $this->directorUser->assignRole($directorRole);

        $superAdminRole = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole($superAdminRole);

        // 2. Structure académique
        $institution = Institution::create([
            'nom' => 'Lycée Classique de Yaoundé',
            'code' => 'LCY',
            'statut' => 'actif',
        ]);

        $this->etablissement = Etablissement::create([
            'institution_id' => $institution->id,
            'nom' => 'Campus Principal',
            'code' => 'LCY-01',
            'email' => 'direction@lycee-classique.cm',
            'telephone' => '237222001122',
            'statut' => 'actif',
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
            'nom' => 'Troisième',
            'code' => '3EME',
            'ordre' => 4,
        ]);

        $this->classe = Classe::create([
            'niveau_id' => $niveau->id,
            'nom' => '3ème Allemand 1',
            'code' => '3ALL1',
            'capacite' => 40,
            'is_active' => true,
        ]);

        // Matières et Attributions
        $groupe = GroupeMatiere::create([
            'etablissement_id' => $this->etablissement->id,
            'nom' => 'Sciences & Mathématiques',
            'code' => 'SCI_MATH',
            'ordre' => 1,
        ]);

        $matiereMath = Matiere::create([
            'nom' => 'Mathématiques',
            'code' => 'MATH',
        ]);

        MatiereNiveau::create([
            'matiere_id' => $matiereMath->id,
            'niveau_id' => $niveau->id,
            'groupe_matiere_id' => $groupe->id,
            'coefficient' => 4,
            'note_maximale' => 20,
            'note_eliminatoire' => 5.0,
            'is_comptabilisee' => true,
        ]);

        $enseignant = Personnel::create([
            'etablissement_id' => $this->etablissement->id,
            'matricule' => 'ENS-001',
            'nom' => 'Tchinda',
            'prenom' => 'Pierre',
            'telephone' => '237699887766',
            'genre' => 'M',
            'fonction_principale' => 'Enseignant',
            'type_contrat' => 'Permanent',
            'statut' => 'Actif',
        ]);

        $attributionMath = Attribution::create([
            'annee_scolaire_id' => $this->anneeScolaire->id,
            'classe_id' => $this->classe->id,
            'matiere_id' => $matiereMath->id,
            'personnel_id' => $enseignant->id,
            'volume_horaire_hebdo' => 4,
            'is_active' => true,
        ]);

        // 3. Trois profils d'élèves pour tester toutes les branches de délibération
        // Élève 1 : Excellent (Note 17/20, Félicitations méritées, Promu)
        $eleve1 = Eleve::create([
            'matricule' => 'LCY-2026-001',
            'matricule_national' => 'MINESEC-001',
            'nom' => 'Abena',
            'prenom' => 'Sophie',
            'sexe' => 'F',
            'date_naissance' => '2012-05-14',
            'lieu_naissance' => 'Yaoundé',
        ]);

        $this->inscription1 = Inscription::create([
            'eleve_id' => $eleve1->id,
            'classe_id' => $this->classe->id,
            'annee_scolaire_id' => $this->anneeScolaire->id,
            'date_inscription' => '2026-09-02',
            'statut' => 'validee',
            'is_redoublant' => false,
        ]);

        Note::create([
            'inscription_id' => $this->inscription1->id,
            'attribution_id' => $attributionMath->id,
            'evaluation_id' => $evaluation1->id,
            'valeur_note' => 17.0,
            'statut_presence' => 'present',
            'appreciation' => 'Excellent travail',
        ]);

        DisciplineBulletin::create([
            'inscription_id' => $this->inscription1->id,
            'periode_id' => $this->trimestre1->id,
            'absences_justifiees' => 2,
            'absences_non_justifiees' => 0,
        ]);

        CompteFinancierEleve::create([
            'inscription_id' => $this->inscription1->id,
            'eleve_id' => $eleve1->id,
            'etablissement_id' => $this->etablissement->id,
            'annee_scolaire_id' => $this->anneeScolaire->id,
            'debit_initial' => 120000,
            'remise_bourse' => 0,
            'montant_net_exigible' => 120000,
            'total_paye' => 120000,
            'solde_restant' => 0,
            'statut_financier' => 'solde',
        ]);

        // Élève 2 : Travailleur mais Indiscipliné (Note 15/20 -> Encouragements théoriques, MAIS 18h d'absences injustifiées -> Déchéance Module 12.4)
        $eleve2 = Eleve::create([
            'matricule' => 'LCY-2026-002',
            'matricule_national' => 'MINESEC-002',
            'nom' => 'Bello',
            'prenom' => 'Ibrahim',
            'sexe' => 'M',
            'date_naissance' => '2012-08-20',
            'lieu_naissance' => 'Garoua',
        ]);

        $this->inscription2 = Inscription::create([
            'eleve_id' => $eleve2->id,
            'classe_id' => $this->classe->id,
            'annee_scolaire_id' => $this->anneeScolaire->id,
            'date_inscription' => '2026-09-02',
            'statut' => 'validee',
            'is_redoublant' => true,
        ]);

        Note::create([
            'inscription_id' => $this->inscription2->id,
            'attribution_id' => $attributionMath->id,
            'evaluation_id' => $evaluation1->id,
            'valeur_note' => 15.0,
            'statut_presence' => 'present',
            'appreciation' => 'Bon travail',
        ]);

        DisciplineBulletin::create([
            'inscription_id' => $this->inscription2->id,
            'periode_id' => $this->trimestre1->id,
            'absences_justifiees' => 0,
            'absences_non_justifiees' => 18, // >= 15h : Déchéance automatique de mention
        ]);

        CompteFinancierEleve::create([
            'inscription_id' => $this->inscription2->id,
            'eleve_id' => $eleve2->id,
            'etablissement_id' => $this->etablissement->id,
            'annee_scolaire_id' => $this->anneeScolaire->id,
            'debit_initial' => 120000,
            'remise_bourse' => 0,
            'montant_net_exigible' => 120000,
            'total_paye' => 60000,
            'solde_restant' => 60000,
            'statut_financier' => 'insolvable',
        ]);

        // Élève 3 : Décrocheur (Note 6/20, En échec, Exclusion proposée si annuel)
        $eleve3 = Eleve::create([
            'matricule' => 'LCY-2026-003',
            'matricule_national' => 'MINESEC-003',
            'nom' => 'Kamdem',
            'prenom' => 'Cédric',
            'sexe' => 'M',
            'date_naissance' => '2011-11-12',
            'lieu_naissance' => 'Bafoussam',
        ]);

        $this->inscription3 = Inscription::create([
            'eleve_id' => $eleve3->id,
            'classe_id' => $this->classe->id,
            'annee_scolaire_id' => $this->anneeScolaire->id,
            'date_inscription' => '2026-09-02',
            'statut' => 'validee',
            'is_redoublant' => false,
        ]);

        Note::create([
            'inscription_id' => $this->inscription3->id,
            'attribution_id' => $attributionMath->id,
            'evaluation_id' => $evaluation1->id,
            'valeur_note' => 6.0,
            'statut_presence' => 'present',
            'appreciation' => 'Insuffisant',
        ]);

        DisciplineBulletin::create([
            'inscription_id' => $this->inscription3->id,
            'periode_id' => $this->trimestre1->id,
            'absences_justifiees' => 0,
            'absences_non_justifiees' => 4,
        ]);

        CompteFinancierEleve::create([
            'inscription_id' => $this->inscription3->id,
            'eleve_id' => $eleve3->id,
            'etablissement_id' => $this->etablissement->id,
            'annee_scolaire_id' => $this->anneeScolaire->id,
            'debit_initial' => 120000,
            'remise_bourse' => 0,
            'montant_net_exigible' => 120000,
            'total_paye' => 40000,
            'solde_restant' => 80000,
            'statut_financier' => 'insolvable',
        ]);
    }

    /**
     * Teste les contrôles d'accès RBAC pour les délibérations et statistiques.
     *
     * @return void
     */
    public function test_controle_acces_rbac_statistiques_et_deliberations(): void
    {
        // 1. Accès interdit aux utilisateurs non autorisés
        $res = $this->actingAs($this->unauthorizedUser)->get(route('administration.statistiques.index'));
        $res->assertStatus(403);

        $res = $this->actingAs($this->unauthorizedUser)->get(route('administration.deliberations.index'));
        $res->assertStatus(403);

        // 2. Accès autorisé au Directeur des études
        $res = $this->actingAs($this->directorUser)->get(route('administration.statistiques.index'));
        $res->assertStatus(200);

        $res = $this->actingAs($this->directorUser)->get(route('administration.deliberations.index'));
        $res->assertStatus(200);

        // 3. Tableau de bord exécutif accessible aux utilisateurs connectés
        $res = $this->actingAs($this->directorUser)->get(route('dashboard'));
        $res->assertStatus(200);
    }

    /**
     * Teste le calcul précis des KPIs démographiques et d'effectifs (Gesco 15.1).
     *
     * @return void
     */
    public function test_kpis_demographiques_et_parite(): void
    {
        /** @var StatistiqueConseilService $service */
        $service = app(StatistiqueConseilService::class);

        $stats = $service->getStatistiquesDemographiques($this->etablissement->id, $this->anneeScolaire->id);

        // Total 3 élèves inscrits
        $this->assertEquals(3, $stats['effectif_total']);

        // Parité : 1 Fille (33.3%) et 2 Garçons (66.7%)
        $this->assertEquals(1, $stats['parite']['filles']);
        $this->assertEquals(2, $stats['parite']['garcons']);
        $this->assertEquals(33.3, $stats['parite']['taux_filles']);
        $this->assertEquals(66.7, $stats['parite']['taux_garcons']);

        // Statut : 2 nouveaux, 1 redoublant (33.3%)
        $this->assertEquals(2, $stats['repartition_statut']['nouveaux']);
        $this->assertEquals(1, $stats['repartition_statut']['redoublants']);
        $this->assertEquals(33.3, $stats['repartition_statut']['taux_redoublants']);

        // Densité : 1 classe avec 3 élèves (moyenne 3.0, capacité 40, aucune surcharge)
        $this->assertEquals(1, $stats['densite']['nombre_classes']);
        $this->assertEquals(3.0, $stats['densite']['moyenne_par_classe']);
        $this->assertEquals(0, $stats['densite']['classes_surchargees']);
    }

    /**
     * Teste la détection précoce du risque de décrochage scolaire (Gesco 15.3).
     *
     * @return void
     */
    public function test_detection_eleves_en_decrochage_scolaire(): void
    {
        /** @var StatistiqueConseilService $service */
        $service = app(StatistiqueConseilService::class);

        $pedagogie = $service->getStatistiquesPedagogiques(
            $this->etablissement->id,
            $this->anneeScolaire->id,
            $this->trimestre1->id
        );

        // Taux de réussite : 2 élèves sur 3 ont >= 10/20 (Abena 17/20 et Bello 15/20) -> 66.7%
        $this->assertEquals(66.7, $pedagogie['taux_reussite_global']);

        // Élèves en décrochage :
        // - Bello Ibrahim : Moyenne 15.0 mais Absences NJ = 18h (>= 15h)
        // - Kamdem Cédric : Moyenne 6.0 (< 8.5)
        $this->assertEquals(2, $pedagogie['total_decrochage']);
        $matriculesDecrochage = collect($pedagogie['eleves_decrochage'])->pluck('matricule')->all();
        $this->assertContains('LCY-2026-002', $matriculesDecrochage);
        $this->assertContains('LCY-2026-003', $matriculesDecrochage);
    }

    /**
     * Teste la synthèse financière consolidée pour la Direction (Gesco 15.3).
     *
     * @return void
     */
    public function test_synthese_financiere_direction(): void
    {
        /** @var StatistiqueConseilService $service */
        $service = app(StatistiqueConseilService::class);

        $finances = $service->getStatistiquesFinancieres($this->etablissement->id, $this->anneeScolaire->id);

        // 3 élèves à 120 000 FCFA chacun = 360 000 FCFA
        $this->assertEquals(360000, $finances['total_facture']);

        // Payé : 120 000 + 60 000 + 40 000 = 220 000 FCFA
        $this->assertEquals(220000, $finances['total_recouvre']);

        // Créances : 0 + 60 000 + 80 000 = 140 000 FCFA
        $this->assertEquals(140000, $finances['total_creances']);

        // Taux de recouvrement : 220 000 / 360 000 = 61.1%
        $this->assertEquals(61.1, $finances['taux_recouvrement']);

        // 1 compte soldé, 2 débiteurs
        $this->assertEquals(1, $finances['nombre_soldes']);
        $this->assertEquals(2, $finances['nombre_debiteurs']);
    }

    /**
     * Teste l'attribution des mentions d'honneur et la déchéance d'office pour indiscipline (Gesco 15.2 & 12.4).
     *
     * @return void
     */
    public function test_attribution_mentions_et_decheance_indiscipline(): void
    {
        /** @var StatistiqueConseilService $service */
        $service = app(StatistiqueConseilService::class);

        $deliberation = $service->preparerDeliberation($this->classe, $this->trimestre1);

        $eleves = collect($deliberation['eleves'])->keyBy('matricule');

        // Élève 1 (Abena Sophie) : Moyenne 17.0, Absences 0h -> Félicitations validées
        $el1 = $eleves->get('LCY-2026-001');
        $this->assertEquals(17.0, $el1['moyenne_obtenue']);
        $this->assertEquals('felicitations', $el1['mention_theorique']);
        $this->assertFalse($el1['mention_bloquee']);
        $this->assertEquals('felicitations', $el1['mention_obtenue']);

        // Élève 2 (Bello Ibrahim) : Moyenne 15.0 -> Encouragements théoriques, MAIS Absences 18h (>=15h)
        // Règle Gesco 12.4 : DÉCHÉANCE AUTOMATIQUE DES MENTIONS
        $el2 = $eleves->get('LCY-2026-002');
        $this->assertEquals(15.0, $el2['moyenne_obtenue']);
        $this->assertEquals('encouragements', $el2['mention_theorique']);
        $this->assertTrue($el2['mention_bloquee']);
        $this->assertEquals('absences_excessives', $el2['motif_blocage_mention']);
        $this->assertEquals('aucune', $el2['mention_obtenue']);
    }

    /**
     * Teste la proposition automatique de passage (Étape A) et la validation humaine obligatoire (Étape B) (Gesco 2.6).
     *
     * @return void
     */
    public function test_moteur_promotion_et_validation_humaine_obligatoire(): void
    {
        // 1. Étape A : Proposition automatique de fin d'année
        /** @var StatistiqueConseilService $service */
        $service = app(StatistiqueConseilService::class);

        $delibAnnuelle = $service->preparerDeliberation($this->classe, null, $this->anneeScolaire);
        $eleves = collect($delibAnnuelle['eleves'])->keyBy('matricule');

        // Abena Sophie (17/20) -> Promue
        $this->assertEquals('promu', $eleves->get('LCY-2026-001')['statut_propose']);

        // Bello Ibrahim (15/20) -> Promu
        $this->assertEquals('promu', $eleves->get('LCY-2026-002')['statut_propose']);

        // Kamdem Cédric (6/20) -> Exclu
        $this->assertEquals('exclu', $eleves->get('LCY-2026-003')['statut_propose']);

        // 2. Enregistrement des délibérations en session
        $res = $this->actingAs($this->directorUser)
            ->post(route('administration.deliberations.sauvegarder'), [
                'classe_id' => $this->classe->id,
                'annee_id' => $this->anneeScolaire->id,
                'lignes' => $delibAnnuelle['eleves'],
            ]);

        $res->assertRedirect();
        $res->assertSessionHas('success');

        // Les délibérations sont stockées mais NON VALIDÉES (est_valide = false)
        $this->assertDatabaseHas('deliberations_conseils', [
            'classe_id' => $this->classe->id,
            'inscription_id' => $this->inscription1->id,
            'type_deliberation' => 'annuelle',
            'est_valide' => false,
        ]);

        // 3. Étape B : Validation humaine obligatoire par la Direction
        $decisionsValidation = [
            [
                'inscription_id' => $this->inscription1->id,
                'statut_retenu' => 'promu',
                'appreciation_globale' => 'Félicitations du conseil, admise en 2nde C',
            ],
            [
                'inscription_id' => $this->inscription2->id,
                'statut_retenu' => 'promu',
                'appreciation_globale' => 'Admis, surveiller l\'assiduité',
            ],
            [
                'inscription_id' => $this->inscription3->id,
                'statut_retenu' => 'redouble', // Le vote du conseil accorde une chance de redoublement
                'appreciation_globale' => 'Autorisé exceptionnellement à redoubler la 3ème',
            ],
        ];

        $res = $this->actingAs($this->directorUser)
            ->post(route('administration.deliberations.valider-promotion'), [
                'classe_id' => $this->classe->id,
                'annee_id' => $this->anneeScolaire->id,
                'decisions' => $decisionsValidation,
            ]);

        $res->assertRedirect();
        $res->assertSessionHas('success');

        // Vérification de la validation scellée
        $this->assertDatabaseHas('deliberations_conseils', [
            'inscription_id' => $this->inscription1->id,
            'type_deliberation' => 'annuelle',
            'statut_retenu' => 'promu',
            'est_valide' => true,
            'valide_par_user_id' => $this->directorUser->id,
        ]);

        // Vérification que le vote du conseil a primé sur la proposition pour l'élève 3 (redouble au lieu d'exclu)
        $this->assertDatabaseHas('deliberations_conseils', [
            'inscription_id' => $this->inscription3->id,
            'type_deliberation' => 'annuelle',
            'statut_propose' => 'exclu',
            'statut_retenu' => 'redouble',
            'est_valide' => true,
        ]);
    }

    /**
     * Teste la génération du Procès-Verbal officiel en PDF (Gesco 15.2).
     *
     * @return void
     */
    public function test_generation_pv_conseil_classe_pdf(): void
    {
        $response = $this->actingAs($this->directorUser)
            ->get(route('administration.deliberations.pv-pdf', [
                'classe_id' => $this->classe->id,
                'annee_id' => $this->anneeScolaire->id,
                'periode_id' => $this->trimestre1->id,
            ]));

        $response->assertStatus(200);
        $this->assertEquals('application/pdf', $response->headers->get('content-type'));
    }

    /**
     * Teste la génération du bordereau officiel d'examens ministériels en PDF (Gesco 15.1).
     *
     * @return void
     */
    public function test_generation_bordereau_examens_pdf(): void
    {
        $response = $this->actingAs($this->directorUser)
            ->get(route('administration.statistiques.bordereau', [
                'etablissement_id' => $this->etablissement->id,
                'annee_id' => $this->anneeScolaire->id,
                'classe_id' => $this->classe->id,
            ]));

        $response->assertStatus(200);
        $this->assertEquals('application/pdf', $response->headers->get('content-type'));
    }

    /**
     * Teste la préparation de délibération pour une classe sans notes ni élèves évalués.
     * Vérifie que la structure retournée est complète et empêche les erreurs de page blanche côté React.
     *
     * @return void
     */
    public function test_preparer_deliberation_classe_sans_eleves_retourne_structure_complete(): void
    {
        /** @var StatistiqueConseilService $service */
        $service = app(StatistiqueConseilService::class);

        // Création d'une nouvelle classe vide sans aucune note
        $classeVide = Classe::create([
            'niveau_id' => $this->classe->niveau_id,
            'code' => 'VIDE-1',
            'nom' => 'Classe Vide Test',
            'capacite_max' => 30,
            'is_active' => true,
        ]);

        $deliberation = $service->preparerDeliberation($classeVide, null, $this->anneeScolaire);

        $this->assertIsArray($deliberation);
        $this->assertArrayHasKey('classe', $deliberation);
        $this->assertEquals($classeVide->id, $deliberation['classe']['id']);
        $this->assertEquals('Classe Vide Test', $deliberation['classe']['nom']);
        $this->assertEquals('Non assigné', $deliberation['classe']['professeur_principal']);
        $this->assertArrayHasKey('eleves', $deliberation);
        $this->assertEmpty($deliberation['eleves']);
        $this->assertArrayHasKey('statistiques_classe', $deliberation);
        $this->assertArrayHasKey('annee_scolaire', $deliberation);
        $this->assertEquals($this->anneeScolaire->id, $deliberation['annee_scolaire']['id']);
        $this->assertFalse($deliberation['toutes_validees']);
    }
}

