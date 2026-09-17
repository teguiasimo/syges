<?php

namespace Tests\Feature\Administration;

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\CompteFinancierEleve;
use App\Models\Cycle;
use App\Models\Eleve;
use App\Models\Etablissement;
use App\Models\HistoriqueSms;
use App\Models\Inscription;
use App\Models\Institution;
use App\Models\Niveau;
use App\Models\Section;
use App\Models\SmsCampagne;
use App\Models\SmsConfiguration;
use App\Models\User;
use App\Services\SmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Tests fonctionnels pour le Module 14 : Communication & Notifications SMS (Gesco V1.1).
 *
 * Valide :
 * - Le contrôle d'accès RBAC (communication.view, communication.send, communication.manage).
 * - L'algorithme de conversion GSM 7-bit et calcul des segments/crédits (Gesco 14.4).
 * - La normalisation internationale des numéros au format E.164 (Gesco 14.2).
 * - L'émission de SMS unitaires directs et le débit instantané des crédits (Gesco 14.2).
 * - L'émission des notifications automatiques événementielles (absences, sanctions, rappels financiers).
 * - Les campagnes de masse avec ciblage multi-critères (classe, parents débiteurs/insolvables).
 * - La gestion de la configuration de la passerelle télécom et le rechargement de crédits (Gesco 14.1).
 */
class CommunicationSmsTest extends TestCase
{
    use RefreshDatabase;

    public ?User $unauthorizedUser = null;
    protected User $operatorUser;
    public ?User $adminUser = null;
    public ?Etablissement $etablissement = null;
    public ?AnneeScolaire $anneeScolaire = null;
    public ?Classe $classe = null;
    public ?Inscription $inscription1 = null;
    public ?Inscription $inscription2 = null;

    /**
     * Initialise l'environnement de test pour les communications SMS.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        // 1. Initialisation des permissions RBAC
        foreach (['communication.view', 'communication.send', 'communication.manage'] as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $operatorRole = Role::firstOrCreate(['name' => 'surveillant-general', 'guard_name' => 'web']);
        $operatorRole->syncPermissions(['communication.view', 'communication.send']);

        $adminRole = Role::firstOrCreate(['name' => 'directeur', 'guard_name' => 'web']);
        $adminRole->syncPermissions(['communication.view', 'communication.send', 'communication.manage']);

        // 2. Utilisateurs de test
        $this->unauthorizedUser = User::factory()->create();

        $this->operatorUser = User::factory()->create();
        $this->operatorUser->assignRole($operatorRole);

        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole($adminRole);

        // 3. Structure pédagogique
        $institution = Institution::create([
            'nom' => 'Collège Bilingue Saint-Exupéry',
            'code' => 'CBS',
            'statut' => 'actif',
        ]);

        $this->etablissement = Etablissement::create([
            'institution_id' => $institution->id,
            'nom' => 'Campus Principal',
            'code' => 'CP-01',
            'email' => 'direction@saint-ex.com',
            'telephone' => '237222334455',
            'statut' => 'actif',
        ]);

        $this->anneeScolaire = AnneeScolaire::create([
            'nom' => '2026-2027',
            'code' => '2026-2027',
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-06-30',
            'statut' => 'ouverte',
            'is_current' => true,
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
            'nom' => 'Sixième',
            'code' => '6EME',
            'ordre' => 1,
        ]);

        $this->classe = Classe::create([
            'niveau_id' => $niveau->id,
            'nom' => '6ème A',
            'code' => '6A',
            'capacite' => 45,
        ]);

        // 4. Élèves et Inscriptions avec comptes financiers
        $eleve1 = Eleve::create([
            'matricule' => 'ELV-001',
            'nom' => 'Kouam',
            'prenom' => 'Armel',
            'sexe' => 'M',
            'date_naissance' => '2014-04-12',
            'lieu_naissance' => 'Douala',
            'telephone_parent' => '699001122',
            'nom_parent' => 'M. Kouam Thomas',
        ]);

        $this->inscription1 = Inscription::create([
            'eleve_id' => $eleve1->id,
            'classe_id' => $this->classe->id,
            'annee_scolaire_id' => $this->anneeScolaire->id,
            'date_inscription' => '2026-09-02',
            'statut' => 'validee',
        ]);

        CompteFinancierEleve::create([
            'inscription_id' => $this->inscription1->id,
            'eleve_id' => $eleve1->id,
            'etablissement_id' => $this->etablissement->id,
            'annee_scolaire_id' => $this->anneeScolaire->id,
            'debit_initial' => 150000,
            'remise_bourse' => 0,
            'montant_net_exigible' => 150000,
            'total_paye' => 50000,
            'solde_restant' => 100000, // Débiteur
            'statut_financier' => 'insolvable',
        ]);

        $eleve2 = Eleve::create([
            'matricule' => 'ELV-002',
            'nom' => 'Ngo Bikoi',
            'prenom' => 'Danielle',
            'sexe' => 'F',
            'date_naissance' => '2014-07-25',
            'lieu_naissance' => 'Yaounde',
            'telephone_parent' => '+237677998877',
            'nom_parent' => 'Mme Ngo Chantal',
        ]);

        $this->inscription2 = Inscription::create([
            'eleve_id' => $eleve2->id,
            'classe_id' => $this->classe->id,
            'annee_scolaire_id' => $this->anneeScolaire->id,
            'date_inscription' => '2026-09-02',
            'statut' => 'validee',
        ]);

        CompteFinancierEleve::create([
            'inscription_id' => $this->inscription2->id,
            'eleve_id' => $eleve2->id,
            'etablissement_id' => $this->etablissement->id,
            'annee_scolaire_id' => $this->anneeScolaire->id,
            'debit_initial' => 150000,
            'remise_bourse' => 0,
            'montant_net_exigible' => 150000,
            'total_paye' => 150000,
            'solde_restant' => 0, // En règle
            'statut_financier' => 'solde',
        ]);
    }

    /**
     * Teste le contrôle d'accès RBAC sur l'espace de communication.
     *
     * @return void
     */
    public function test_controle_acces_rbac_communication(): void
    {
        // 1. Utilisateur non autorisé
        $response = $this->actingAs($this->unauthorizedUser)
            ->get(route('administration.communication.index'));
        $response->assertStatus(403);

        // 2. Utilisateur avec permission view
        $response = $this->actingAs($this->operatorUser)
            ->get(route('administration.communication.index'));
        $response->assertStatus(200);

        // 3. Utilisateur sans droit d'envoi tente de lancer un SMS
        $response = $this->actingAs($this->unauthorizedUser)
            ->post(route('administration.communication.sms-direct.store'), [
                'etablissement_id' => $this->etablissement->id,
                'telephone' => '699112233',
                'message' => 'Test',
            ]);
        $response->assertStatus(403);

        // 4. Utilisateur sans droit de gestion tente de modifier la passerelle
        $response = $this->actingAs($this->operatorUser)
            ->put(route('administration.communication.config.update'), [
                'etablissement_id' => $this->etablissement->id,
                'fournisseur' => 'orange',
                'sender_id' => 'MYCOLLEGE',
                'seuil_alerte_solde' => 10,
                'is_active' => true,
            ]);
        $response->assertStatus(403);
    }

    /**
     * Teste le nettoyage et la conversion stricte GSM 7-bit (Gesco 14.4).
     *
     * @return void
     */
    public function test_conversion_gsm7_et_calcul_segments(): void
    {
        /** @var SmsService $smsService */
        $smsService = app(SmsService::class);

        // A. Vérification de l'élimination des caractères accentués
        $texteBrut = "Élève absent à l'école le 15/09 : reçu à l'accueil avec assiduité & ponctualité — cœur d'or.";
        $texteNettoye = $smsService->sanitiserGsm7($texteBrut);

        $this->assertEquals("Eleve absent a l'ecole le 15/09 : recu a l'accueil avec assiduite & ponctualite - coeur d'or.", $texteNettoye);

        // B. Vérification du calcul des segments SMS
        $court = "Bonjour parent, reunion de rentree prevue ce vendredi a 15h.";
        $this->assertEquals(1, $smsService->calculerSegments($court));

        // Message de plus de 160 caractères
        $long = str_repeat("A", 161);
        $this->assertEquals(2, $smsService->calculerSegments($long));

        $tresLong = str_repeat("B", 310); // 310 car. / 153 car. par segment UDH = 3 segments
        $this->assertEquals(3, $smsService->calculerSegments($tresLong));
    }

    /**
     * Teste la normalisation E.164 des numéros de téléphone pour l'Afrique Centrale.
     *
     * @return void
     */
    public function test_normalisation_telephone_e164(): void
    {
        /** @var SmsService $smsService */
        $smsService = app(SmsService::class);

        // Format local 9 chiffres camerounais
        $this->assertEquals('+237699112233', $smsService->normaliserNumero('699112233'));

        // Format avec préfixe 00
        $this->assertEquals('+237699112233', $smsService->normaliserNumero('00237699112233'));

        // Format déjà internationalisé
        $this->assertEquals('+237699112233', $smsService->normaliserNumero('+237699112233'));

        // Numéro avec espaces ou tirets
        $this->assertEquals('+237677998877', $smsService->normaliserNumero('677 99 88 77'));
    }

    /**
     * Teste l'envoi d'un SMS direct unitaire avec débit des crédits.
     *
     * @return void
     */
    public function test_envoi_sms_direct_et_debit_credits(): void
    {
        $response = $this->actingAs($this->operatorUser)
            ->post(route('administration.communication.sms-direct.store'), [
                'etablissement_id' => $this->etablissement->id,
                'telephone' => '699001122',
                'destinataire_nom' => 'M. Kouam Thomas',
                'message' => 'Bonjour M. Kouam, merci de contacter la vie scolaire.',
                'type' => 'personnalise',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Vérification de la traçabilité
        $this->assertDatabaseHas('historique_sms', [
            'etablissement_id' => $this->etablissement->id,
            'destinataire_telephone' => '+237699001122',
            'destinataire_nom' => 'M. Kouam Thomas',
            'type_notification' => 'personnalise',
            'statut_distribution' => 'envoye',
            'credits_consommes' => 1,
        ]);

        // Vérification du débit du solde
        $config = SmsConfiguration::where('etablissement_id', $this->etablissement->id)->first();
        $this->assertEquals(99, $config->solde_credits); // Initial = 100 - 1 = 99
    }

    /**
     * Teste le déclenchement des notifications automatiques événementielles.
     *
     * @return void
     */
    public function test_notification_automatique_evenementielle(): void
    {
        /** @var SmsService $smsService */
        $smsService = app(SmsService::class);

        // 1. Alerte absence
        $smsAbsence = $smsService->envoyerNotificationAutomatique('alerte_absence', $this->inscription1, [
            'date' => '16/09',
            'heures' => '2',
        ]);

        $this->assertNotNull($smsAbsence);
        $this->assertEquals('alerte_absence', $smsAbsence->type_notification);
        $this->assertStringContainsString('absent', $smsAbsence->message);

        // 2. Rappel paiement scolarité
        $smsRappel = $smsService->envoyerNotificationAutomatique('rappel_paiement', $this->inscription1, [
            'solde' => 100000,
        ]);

        $this->assertNotNull($smsRappel);
        $this->assertEquals('rappel_paiement', $smsRappel->type_notification);
        $this->assertStringContainsString('100 000 FCFA', $smsRappel->message);
    }

    /**
     * Teste la diffusion d'une campagne de masse ciblée sur les parents insolvables (Gesco 14.2.B).
     *
     * @return void
     */
    public function test_campagne_masse_cible_insolvables(): void
    {
        $response = $this->actingAs($this->operatorUser)
            ->post(route('administration.communication.campagnes.store'), [
                'etablissement_id' => $this->etablissement->id,
                'titre' => 'Rappel Urgent Echeance Tranche 1',
                'message' => 'Chers parents, merci de solder la premiere tranche de scolarite avant ce vendredi.',
                'cible_type' => 'insolvables',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Seul l'élève 1 a un solde restant > 0
        $this->assertDatabaseHas('sms_campagnes', [
            'etablissement_id' => $this->etablissement->id,
            'titre' => 'Rappel Urgent Echeance Tranche 1',
            'cible_type' => 'insolvables',
            'nombre_destinataires' => 1,
            'total_credits' => 1,
            'statut' => 'termine',
        ]);

        // Un message dans l'historique a été créé
        $this->assertDatabaseHas('historique_sms', [
            'etablissement_id' => $this->etablissement->id,
            'destinataire_telephone' => '+237699001122',
            'type_notification' => 'campagne_masse',
        ]);
    }

    /**
     * Teste le rejet d'une campagne si le solde de crédits est insuffisant.
     *
     * @return void
     */
    public function test_rejet_campagne_solde_insuffisant(): void
    {
        // Forcer le solde à 0
        $config = SmsConfiguration::firstOrCreate(
            ['etablissement_id' => $this->etablissement->id],
            ['solde_credits' => 0]
        );
        $config->update(['solde_credits' => 0]);

        $response = $this->actingAs($this->operatorUser)
            ->from(route('administration.communication.index'))
            ->post(route('administration.communication.campagnes.store'), [
                'etablissement_id' => $this->etablissement->id,
                'titre' => 'Alerte Generale',
                'message' => 'Message sans solde suffisant.',
                'cible_type' => 'tous',
            ]);

        $response->assertRedirect(route('administration.communication.index'));
        $response->assertSessionHasErrors(['solde']);
    }

    /**
     * Teste la mise à jour de la passerelle SMS et le rechargement du solde (Gesco 14.1).
     *
     * @return void
     */
    public function test_configuration_passerelle_et_rechargement_solde(): void
    {
        // 1. Mise à jour de la configuration de la passerelle
        $response = $this->actingAs($this->adminUser)
            ->put(route('administration.communication.config.update'), [
                'etablissement_id' => $this->etablissement->id,
                'fournisseur' => 'orange',
                'sender_id' => 'ST-EXUPERY',
                'api_url' => 'https://api.orange.com/sms/v1',
                'api_key' => 'key_orange_secret_123',
                'seuil_alerte_solde' => 25,
                'is_active' => true,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('sms_configurations', [
            'etablissement_id' => $this->etablissement->id,
            'fournisseur' => 'orange',
            'sender_id' => 'ST-EXUPERY',
            'seuil_alerte_solde' => 25,
        ]);

        // 2. Rechargement du solde de crédits
        $response = $this->actingAs($this->adminUser)
            ->post(route('administration.communication.recharger'), [
                'etablissement_id' => $this->etablissement->id,
                'credits' => 500,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $config = SmsConfiguration::where('etablissement_id', $this->etablissement->id)->first();
        $this->assertEquals(600, $config->solde_credits); // 100 init + 500 = 600
    }
}
