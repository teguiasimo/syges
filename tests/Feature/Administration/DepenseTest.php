<?php

/** @var \Tests\TestCase $this */

use App\Models\AnneeScolaire;
use App\Models\Caisse;
use App\Models\CategorieDepense;
use App\Models\Classe;
use App\Models\CompteFinancierEleve;
use App\Models\Cycle;
use App\Models\Depense;
use App\Models\Eleve;
use App\Models\Etablissement;
use App\Models\Inscription;
use App\Models\Institution;
use App\Models\Niveau;
use App\Models\ReglementDivers;
use App\Models\Section;
use App\Models\User;
use App\Models\VersementCaisse;
use Spatie\Permission\Models\Role;
use function Pest\Laravel\actingAs;

/**
 * Tests de fonctionnalités pour la Gestion des Dépenses, Charges & Fiches de Décharge (Phase 1).
 *
 * Vérifie :
 * - L'affichage de l'écran des dépenses, filtres budgétaires et KPIs de rentabilité/solde net.
 * - L'enregistrement d'une dépense avec génération automatique du numéro de pièce.
 * - La création d'une nouvelle catégorie de dépense.
 * - La mise à jour et la suppression d'une dépense.
 * - L'impression de la fiche de décharge / bon de décaissement (PDF A5 Paysage).
 * - L'impression du bilan financier entrées/sorties de l'exercice (PDF A4 Portrait).
 */

beforeEach(function () {
    /** @var \Tests\TestCase $this */
    $this->withoutVite();

    Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);

    $this->adminUser = User::factory()->create();
    $this->adminUser->assignRole('super-admin');

    $this->institution = Institution::create([
        'code' => 'INST-FINANCE',
        'nom' => 'Délégation Régionale Centre',
        'ville' => 'Yaoundé',
    ]);

    $this->etablissement = Etablissement::create([
        'institution_id' => $this->institution->id,
        'code' => 'LYCEE-FINANCE',
        'nom' => 'Lycée Bilingue Test',
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

    $this->caisse = Caisse::create([
        'etablissement_id' => $this->etablissement->id,
        'code' => 'CAISSE-PRIN',
        'nom' => 'Caisse Principale',
        'solde_initial' => 150000,
        'is_principale' => true,
        'is_active' => true,
    ]);

    $this->categorie = CategorieDepense::create([
        'etablissement_id' => $this->etablissement->id,
        'code' => 'FOURN-BUR',
        'nom' => 'Fournitures de Bureau & Pédagogie',
        'description' => 'Achat rames de papier, craies, marqueurs',
        'couleur' => '#3b82f6',
        'is_active' => true,
    ]);
});

test("l'écran des dépenses et du bilan financier s'affiche avec les statistiques de caisse", function () {
    actingAs($this->adminUser);

    // Création d'une dépense
    Depense::create([
        'etablissement_id' => $this->etablissement->id,
        'annee_scolaire_id' => $this->annee->id,
        'caisse_id' => $this->caisse->id,
        'categorie_depense_id' => $this->categorie->id,
        'numero_piece' => 'DEP-2026/0001',
        'beneficiaire' => 'Librairie Papeterie Centrale',
        'montant' => 45000,
        'date_depense' => '2026-09-15',
        'motif' => 'Achat de 10 cartons de rames de papier',
        'mode_reglement' => 'especes',
        'statut' => 'validee',
        'created_by' => $this->adminUser->id,
    ]);

    // Création d'une recette diverse
    ReglementDivers::create([
        'etablissement_id' => $this->etablissement->id,
        'caisse_id' => $this->caisse->id,
        'reference_recu' => 'REC-DIV-2026-0001',
        'date_reglement' => '2026-09-10',
        'payeur_nom' => 'Parent Visiteur',
        'categorie' => 'uniformes',
        'libelle' => 'Achat écusson et uniforme',
        'montant' => 15000,
        'mode_paiement' => 'especes',
        'statut' => 'valide',
    ]);

    $response = $this->get(route('administration.finances.depenses.index', [
        'etablissement_id' => $this->etablissement->id,
        'annee_scolaire_id' => $this->annee->id,
    ]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('administration/finances/depenses/index')
        ->has('depenses', 1)
        ->has('categories')
        ->has('caisses')
        ->where('stats.total_depenses', 45000)
        ->where('stats.nb_depenses', 1)
        ->where('stats.total_divers', 15000)
        ->where('stats.total_recettes', 15000)
        ->where('stats.solde_net', -30000)
    );
});

test('on peut enregistrer une nouvelle dépense avec attribution automatique du numéro de pièce', function () {
    actingAs($this->adminUser);

    $payload = [
        'etablissement_id' => $this->etablissement->id,
        'annee_scolaire_id' => $this->annee->id,
        'caisse_id' => $this->caisse->id,
        'categorie_depense_id' => $this->categorie->id,
        'beneficiaire' => 'Société Camerounaise de Maintenance',
        'montant' => 125000,
        'date_depense' => '2026-09-20',
        'motif' => 'Réparation du photocopieur administratif',
        'mode_reglement' => 'cheque',
        'reference_paiement' => 'CHQ-88902',
        'statut' => 'validee',
    ];

    $response = $this->post(route('administration.finances.depenses.store'), $payload);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('depenses', [
        'etablissement_id' => $this->etablissement->id,
        'beneficiaire' => 'Société Camerounaise de Maintenance',
        'montant' => 125000,
        'mode_reglement' => 'cheque',
    ]);

    $created = Depense::where('beneficiaire', 'Société Camerounaise de Maintenance')->first();
    expect($created)->not->toBeNull();
    expect($created->numero_piece)->toStartWith('DEP-');
});

test('on peut créer une catégorie budgétaire personnalisée', function () {
    actingAs($this->adminUser);

    $payload = [
        'etablissement_id' => $this->etablissement->id,
        'code' => 'CARBURANT',
        'nom' => 'Carburant et Transport',
        'description' => 'Frais de déplacement et groupe électrogène',
        'couleur' => '#f59e0b',
    ];

    $response = $this->post(route('administration.finances.depenses.categories.store'), $payload);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('categories_depenses', [
        'code' => 'CARBURANT',
        'nom' => 'Carburant et Transport',
    ]);
});

test('on peut modifier et supprimer une dépense', function () {
    actingAs($this->adminUser);

    $depense = Depense::create([
        'etablissement_id' => $this->etablissement->id,
        'annee_scolaire_id' => $this->annee->id,
        'caisse_id' => $this->caisse->id,
        'categorie_depense_id' => $this->categorie->id,
        'numero_piece' => 'DEP-2026/0002',
        'beneficiaire' => 'Fournisseur A',
        'montant' => 30000,
        'date_depense' => '2026-09-18',
        'motif' => 'Achat lampes néon',
        'mode_reglement' => 'especes',
        'statut' => 'validee',
        'created_by' => $this->adminUser->id,
    ]);

    // Modification
    $responseUpdate = $this->put(route('administration.finances.depenses.update', $depense), [
        'caisse_id' => $this->caisse->id,
        'categorie_depense_id' => $this->categorie->id,
        'beneficiaire' => 'Fournisseur A (Rectifié)',
        'montant' => 35000,
        'date_depense' => '2026-09-18',
        'motif' => 'Achat lampes néon et disjoncteur',
        'mode_reglement' => 'especes',
        'statut' => 'validee',
    ]);

    $responseUpdate->assertRedirect();
    $this->assertDatabaseHas('depenses', [
        'id' => $depense->id,
        'beneficiaire' => 'Fournisseur A (Rectifié)',
        'montant' => 35000,
    ]);

    // Suppression
    $responseDelete = $this->delete(route('administration.finances.depenses.destroy', $depense));
    $responseDelete->assertRedirect();
    $this->assertDatabaseMissing('depenses', [
        'id' => $depense->id,
    ]);
});

test("on peut générer la fiche de décharge / bon de décaissement en PDF", function () {
    actingAs($this->adminUser);

    $depense = Depense::create([
        'etablissement_id' => $this->etablissement->id,
        'annee_scolaire_id' => $this->annee->id,
        'caisse_id' => $this->caisse->id,
        'categorie_depense_id' => $this->categorie->id,
        'numero_piece' => 'DEP-2026/0003',
        'beneficiaire' => 'M. Tagne Artisan Peintre',
        'montant' => 75000,
        'date_depense' => '2026-09-19',
        'motif' => 'Travaux de peinture des salles de classe TC',
        'mode_reglement' => 'especes',
        'statut' => 'validee',
        'created_by' => $this->adminUser->id,
    ]);

    $response = $this->get(route('administration.finances.depenses.decharge-pdf', $depense));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toBe('application/pdf');
    expect($response->headers->get('content-disposition'))->toContain('DECHARGE_DEP_2026_0003.pdf');
});

test("on peut générer le bilan financier Entrées/Sorties en PDF", function () {
    actingAs($this->adminUser);

    // Une dépense
    Depense::create([
        'etablissement_id' => $this->etablissement->id,
        'annee_scolaire_id' => $this->annee->id,
        'caisse_id' => $this->caisse->id,
        'categorie_depense_id' => $this->categorie->id,
        'numero_piece' => 'DEP-2026/0004',
        'beneficiaire' => 'Eneo Cameroun',
        'montant' => 80000,
        'date_depense' => '2026-09-20',
        'motif' => 'Facture électricité administrative',
        'mode_reglement' => 'especes',
        'statut' => 'validee',
        'created_by' => $this->adminUser->id,
    ]);

    $response = $this->get(route('administration.finances.bilan-pdf', [
        'etablissement_id' => $this->etablissement->id,
        'annee_scolaire_id' => $this->annee->id,
    ]));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toBe('application/pdf');
    expect($response->headers->get('content-disposition'))->toContain('BILAN_FINANCIER_2026-2027.pdf');
});

test("les statistiques du bilan calculent correctement le total des versements de caisse via montant_total", function () {
    actingAs($this->adminUser);

    // Structure académique pour l'élève
    $section = Section::create([
        'etablissement_id' => $this->etablissement->id,
        'nom' => 'Section Francophone',
        'code' => 'SEC-FR-DEP',
    ]);

    $cycle = Cycle::create([
        'section_id' => $section->id,
        'nom' => '1er Cycle',
        'code' => 'CYC-1-DEP',
        'ordre' => 1,
    ]);

    $niveau = Niveau::create([
        'cycle_id' => $cycle->id,
        'nom' => '6ème',
        'code' => 'NIV-6-DEP',
        'ordre' => 1,
    ]);

    $classe = Classe::create([
        'etablissement_id' => $this->etablissement->id,
        'niveau_id' => $niveau->id,
        'nom' => '6ème A',
        'code' => '6A-DEP',
        'capacite_max' => 50,
    ]);

    $eleve = Eleve::create([
        'matricule' => 'ELEVE-DEP-001',
        'nom' => 'MBIA',
        'prenom' => 'Samuel',
        'sexe' => 'M',
        'date_naissance' => '2014-01-15',
    ]);

    $inscription = Inscription::create([
        'eleve_id' => $eleve->id,
        'annee_scolaire_id' => $this->annee->id,
        'classe_id' => $classe->id,
        'statut' => 'validee',
    ]);

    $compte = CompteFinancierEleve::create([
        'eleve_id' => $eleve->id,
        'annee_scolaire_id' => $this->annee->id,
        'inscription_id' => $inscription->id,
        'etablissement_id' => $this->etablissement->id,
        'debit_initial' => 100000,
        'montant_net_exigible' => 100000,
        'total_paye' => 50000,
        'solde_restant' => 50000,
        'statut_financier' => 'en_regle_tranche_1',
    ]);

    // Versement de caisse scolaire avec montant_total
    VersementCaisse::create([
        'reference_recu' => 'REC-2026-DEP-0001',
        'compte_financier_id' => $compte->id,
        'inscription_id' => $inscription->id,
        'etablissement_id' => $this->etablissement->id,
        'caisse_id' => $this->caisse->id,
        'date_versement' => '2026-09-12 10:00:00',
        'montant_total' => 50000,
        'mode_paiement' => 'especes',
        'statut' => 'valide',
    ]);

    $response = $this->get(route('administration.finances.depenses.index', [
        'etablissement_id' => $this->etablissement->id,
        'annee_scolaire_id' => $this->annee->id,
    ]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('administration/finances/depenses/index')
        ->where('stats.total_versements', 50000)
    );
});
