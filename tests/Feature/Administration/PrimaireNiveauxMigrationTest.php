<?php

use App\Models\AnneeScolaire;
use App\Models\Attribution;
use App\Models\BourseRemise;
use App\Models\Classe;
use App\Models\CompteFinancierEleve;
use App\Models\Cycle;
use App\Models\DispenseFinanciere;
use App\Models\Eleve;
use App\Models\EleveBourseRemise;
use App\Models\Etablissement;
use App\Models\Evaluation;
use App\Models\FraisScolaire;
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
use App\Models\SmsCampagne;
use App\Models\SouscriptionFraisOptionnel;
use App\Models\TypeFrais;
use App\Models\VentilationVersement;
use App\Models\VersementCaisse;
use App\Services\BulletinCalculationService;
use Database\Seeders\EspoirComplexeSeeder;

use function Pest\Laravel\seed;

/**
 * Tests d'intégration de la rectification des niveaux pédagogiques du Primaire (ESP-PRI).
 *
 * Vérifie :
 * - La consolidation idempotente des 12 niveaux unitaires historiques en 6 niveaux officiels (NIV-1..3, LVL-1..3) sans perte financière ni académique.
 * - La préservation stricte des frais ciblés non redondants (ex. examen CM2) et des encaissements déjà saisis.
 * - L'intégrité intacte de la Maternelle (`ESP-MAT`).
 * - La résolution exacte des mentions de promotion/redoublement sur bulletins annuels au Primaire regroupé sans régression pour le Secondaire.
 * - La conformité du seeder `EspoirComplexeSeeder`.
 */

/**
 * Crée la structure complète pré-migration du Primaire ESPOIR (12 niveaux) et de la Maternelle témoin.
 *
 * @return array<string, mixed> Références aux entités et identifiants clés créés
 */
function creerStructureHistoriqueEspoirPrimaire(): array
{
    $institution = Institution::create([
        'code' => 'INST-ESP-TEST',
        'nom' => 'Complexe Scolaire Test Espoir',
        'sigle' => 'CSTE',
    ]);

    $annee = AnneeScolaire::create([
        'code' => '2026-2027',
        'nom' => 'Année Scolaire 2026-2027',
        'date_debut' => '2026-09-01',
        'date_fin' => '2027-06-30',
        'statut' => 'en_cours',
        'is_current' => true,
    ]);

    // Établissement témoin : Maternelle (doit demeurer rigoureusement inchangée)
    $maternelle = Etablissement::create([
        'institution_id' => $institution->id,
        'code' => 'ESP-MAT',
        'nom' => 'École Maternelle ESPOIR',
        'statut_juridique' => 'Prive Laique',
        'ordre_enseignement' => 'Maternelle',
    ]);

    $secMatFr = Section::create([
        'etablissement_id' => $maternelle->id,
        'code' => 'FR',
        'nom' => 'Section Francophone Maternelle',
    ]);

    $cycMatFr = Cycle::create([
        'etablissement_id' => $maternelle->id,
        'section_id' => $secMatFr->id,
        'code' => 'CYC-MAT-FR',
        'nom' => 'Cycle Maternel FR',
        'ordre' => 1,
    ]);

    $nivPs = Niveau::create([
        'etablissement_id' => $maternelle->id,
        'cycle_id' => $cycMatFr->id,
        'code' => 'PS',
        'nom' => 'Petite Section',
        'ordre' => 1,
    ]);

    $nivGs = Niveau::create([
        'etablissement_id' => $maternelle->id,
        'cycle_id' => $cycMatFr->id,
        'code' => 'GS',
        'nom' => 'Grande Section',
        'ordre' => 2,
    ]);

    // Établissement cible : Primaire (structure historique erronée à 12 niveaux)
    $primaire = Etablissement::create([
        'institution_id' => $institution->id,
        'code' => 'ESP-PRI',
        'nom' => 'École Primaire ESPOIR',
        'statut_juridique' => 'Prive Laique',
        'ordre_enseignement' => 'Primaire',
    ]);

    $secPriFr = Section::create([
        'etablissement_id' => $primaire->id,
        'code' => 'FR',
        'nom' => 'Section Francophone Primaire',
    ]);

    $secPriEn = Section::create([
        'etablissement_id' => $primaire->id,
        'code' => 'EN',
        'nom' => 'Section Anglophone Primaire',
    ]);

    $cycPriFr = Cycle::create([
        'etablissement_id' => $primaire->id,
        'section_id' => $secPriFr->id,
        'code' => 'CYC-PRI-FR',
        'nom' => 'Primaire Francophone',
        'ordre' => 1,
    ]);

    $cycPriEn = Cycle::create([
        'etablissement_id' => $primaire->id,
        'section_id' => $secPriEn->id,
        'code' => 'CYC-PRI-EN',
        'nom' => 'Primaire Anglophone',
        'ordre' => 2,
    ]);

    $niveauxFrSpec = [
        ['code' => 'SIL', 'nom' => 'SIL', 'ordre' => 1],
        ['code' => 'CP', 'nom' => 'CP', 'ordre' => 2],
        ['code' => 'CE1', 'nom' => 'CE1', 'ordre' => 3],
        ['code' => 'CE2', 'nom' => 'CE2', 'ordre' => 4],
        ['code' => 'CM1', 'nom' => 'CM1', 'ordre' => 5],
        ['code' => 'CM2', 'nom' => 'CM2', 'ordre' => 6],
    ];

    $niveauxEnSpec = [
        ['code' => 'CLASS-1', 'nom' => 'Class 1', 'ordre' => 7],
        ['code' => 'CLASS-2', 'nom' => 'Class 2', 'ordre' => 8],
        ['code' => 'CLASS-3', 'nom' => 'Class 3', 'ordre' => 9],
        ['code' => 'CLASS-4', 'nom' => 'Class 4', 'ordre' => 10],
        ['code' => 'CLASS-5', 'nom' => 'Class 5', 'ordre' => 11],
        ['code' => 'CLASS-6', 'nom' => 'Class 6', 'ordre' => 12],
    ];

    $niveaux = [];
    $classes = [];

    foreach ($niveauxFrSpec as $spec) {
        $niv = Niveau::create([
            'etablissement_id' => $primaire->id,
            'cycle_id' => $cycPriFr->id,
            'code' => $spec['code'],
            'nom' => $spec['nom'],
            'ordre' => $spec['ordre'],
        ]);
        $niveaux[$spec['code']] = $niv;
        $classes[$spec['code']] = Classe::create([
            'niveau_id' => $niv->id,
            'code' => $spec['code'],
            'nom' => $spec['nom'],
            'capacite_max' => 60,
            'is_active' => true,
        ]);
    }

    foreach ($niveauxEnSpec as $spec) {
        $niv = Niveau::create([
            'etablissement_id' => $primaire->id,
            'cycle_id' => $cycPriEn->id,
            'code' => $spec['code'],
            'nom' => $spec['nom'],
            'ordre' => $spec['ordre'],
        ]);
        $niveaux[$spec['code']] = $niv;
        $classes[$spec['code']] = Classe::create([
            'niveau_id' => $niv->id,
            'code' => $spec['code'],
            'nom' => $spec['nom'],
            'capacite_max' => 60,
            'is_active' => true,
        ]);
    }

    return compact(
        'institution',
        'annee',
        'maternelle',
        'nivPs',
        'nivGs',
        'primaire',
        'cycPriFr',
        'cycPriEn',
        'niveaux',
        'classes'
    );
}

test('migration regroupe les 12 niveaux du primaire espoir en 6 niveaux officiels sans perte et reste idempotente', function () {
    $ctx = creerStructureHistoriqueEspoirPrimaire();
    $primaire = $ctx['primaire'];
    $annee = $ctx['annee'];
    $niveaux = $ctx['niveaux'];
    $classes = $ctx['classes'];

    // Types de frais : obligatoires communs + frais ciblé CEP uniquement sur CM2 + frais optionnel
    $typeInsc = TypeFrais::create([
        'etablissement_id' => $primaire->id,
        'code' => 'INSC-PRI',
        'libelle' => 'Inscription',
        'obligatoire' => true,
        'periodicite' => 'annuel',
        'montant_defaut' => 10000,
    ]);

    $typeT1 = TypeFrais::create([
        'etablissement_id' => $primaire->id,
        'code' => 'TR1-PRI',
        'libelle' => '1ère Tranche',
        'obligatoire' => true,
        'periodicite' => 'tranche',
        'montant_defaut' => 35000,
    ]);

    $typeExamCep = TypeFrais::create([
        'etablissement_id' => $primaire->id,
        'code' => 'EXAM-CEP',
        'libelle' => 'Frais Examen CEP',
        'obligatoire' => false,
        'periodicite' => 'annuel',
        'montant_defaut' => 5000,
    ]);

    $typeOptBus = TypeFrais::create([
        'etablissement_id' => $primaire->id,
        'code' => 'BUS-PRI',
        'libelle' => 'Transport Bus',
        'obligatoire' => false,
        'periodicite' => 'annuel',
        'montant_defaut' => 15000,
    ]);

    // Création des tarifs redondants sur tous les anciens niveaux
    $fraisParCode = [];
    foreach ($niveaux as $code => $niv) {
        $fraisParCode[$code]['INSC'] = FraisScolaire::create([
            'annee_scolaire_id' => $annee->id,
            'etablissement_id' => $primaire->id,
            'niveau_id' => $niv->id,
            'type_frais_id' => $typeInsc->id,
            'tranche_numero' => 1,
            'libelle_tranche' => 'Inscription',
            'montant' => 10000,
        ]);
        $fraisParCode[$code]['T1'] = FraisScolaire::create([
            'annee_scolaire_id' => $annee->id,
            'etablissement_id' => $primaire->id,
            'niveau_id' => $niv->id,
            'type_frais_id' => $typeT1->id,
            'tranche_numero' => 2,
            'libelle_tranche' => '1ère Tranche',
            'montant' => 35000,
        ]);
    }

    // Frais ciblé présent uniquement sur CM2 (absorbé par Niveau 3) -> doit survivre
    $fraisExamCm2 = FraisScolaire::create([
        'annee_scolaire_id' => $annee->id,
        'etablissement_id' => $primaire->id,
        'niveau_id' => $niveaux['CM2']->id,
        'type_frais_id' => $typeExamCep->id,
        'tranche_numero' => 4,
        'libelle_tranche' => 'Examen CEP CM2',
        'montant' => 5000,
    ]);

    // Frais optionnel dupliqué SIL & CP
    $fraisBusSil = FraisScolaire::create([
        'annee_scolaire_id' => $annee->id,
        'etablissement_id' => $primaire->id,
        'niveau_id' => $niveaux['SIL']->id,
        'type_frais_id' => $typeOptBus->id,
        'tranche_numero' => 5,
        'libelle_tranche' => 'Transport Bus',
        'montant' => 15000,
    ]);
    $fraisBusCp = FraisScolaire::create([
        'annee_scolaire_id' => $annee->id,
        'etablissement_id' => $primaire->id,
        'niveau_id' => $niveaux['CP']->id,
        'type_frais_id' => $typeOptBus->id,
        'tranche_numero' => 5,
        'libelle_tranche' => 'Transport Bus',
        'montant' => 15000,
    ]);

    // Élève en CP ayant payé sur le frais du CP (futur absorbé) + remise ciblée + dispense + bus
    $eleveCp = Eleve::create([
        'etablissement_id' => $primaire->id,
        'matricule' => 'ESP-CP-001',
        'nom' => 'TCHOUMI',
        'prenom' => 'Léna',
        'sexe' => 'F',
        'date_naissance' => '2019-03-11',
        'telephone_parent' => '677001122',
    ]);
    $inscrCp = Inscription::create([
        'eleve_id' => $eleveCp->id,
        'classe_id' => $classes['CP']->id,
        'annee_scolaire_id' => $annee->id,
        'statut' => 'validee',
    ]);
    $compteCp = CompteFinancierEleve::create([
        'inscription_id' => $inscrCp->id,
        'eleve_id' => $eleveCp->id,
        'etablissement_id' => $primaire->id,
        'annee_scolaire_id' => $annee->id,
        'debit_initial' => 75000,
        'montant_net_exigible' => 72000,
        'total_paye' => 45000,
        'solde_restant' => 27000,
        'statut_financier' => 'en_regle_tranche_1',
    ]);

    $versementCp = VersementCaisse::create([
        'reference_recu' => 'REC-CP-0001',
        'compte_financier_id' => $compteCp->id,
        'inscription_id' => $inscrCp->id,
        'etablissement_id' => $primaire->id,
        'date_versement' => '2026-09-10 09:00:00',
        'montant_total' => 45000,
        'mode_paiement' => 'especes',
        'statut' => 'valide',
    ]);

    $ventInscCp = VentilationVersement::create([
        'versement_id' => $versementCp->id,
        'type_frais_id' => $typeInsc->id,
        'frais_scolaire_id' => $fraisParCode['CP']['INSC']->id,
        'libelle' => 'Inscription',
        'montant' => 10000,
    ]);
    $ventT1Cp = VentilationVersement::create([
        'versement_id' => $versementCp->id,
        'type_frais_id' => $typeT1->id,
        'frais_scolaire_id' => $fraisParCode['CP']['T1']->id,
        'libelle' => '1ère Tranche',
        'montant' => 35000,
    ]);

    $bourseCp = BourseRemise::create([
        'etablissement_id' => $primaire->id,
        'annee_scolaire_id' => $annee->id,
        'code' => 'REM-CP',
        'nom' => 'Remise CP',
        'type' => 'remise',
        'mode_calcul' => 'montant_fixe',
        'valeur' => 3000,
        'niveau_id' => $niveaux['CP']->id,
        'is_active' => true,
    ]);

    $eleveBourseCp = EleveBourseRemise::create([
        'compte_financier_id' => $compteCp->id,
        'bourse_remise_id' => $bourseCp->id,
        'montant_calcule' => 3000,
        'cible_imputation' => 'frais_specifique',
        'frais_scolaire_id' => $fraisParCode['CP']['T1']->id,
        'date_attribution' => '2026-09-10',
    ]);

    // Cas critique d'unicité : compte avec dispense sur SIL ET sur CP (même tranche cible après fusion)
    $dispenseSilDup = DispenseFinanciere::create([
        'compte_financier_id' => $compteCp->id,
        'frais_scolaire_id' => $fraisParCode['SIL']['INSC']->id,
        'type_dispense' => 'partielle',
        'montant_dispense' => 2000,
        'motif' => 'Test collision dispense SIL',
        'date_dispense' => '2026-09-10',
    ]);
    $dispenseCpDup = DispenseFinanciere::create([
        'compte_financier_id' => $compteCp->id,
        'frais_scolaire_id' => $fraisParCode['CP']['INSC']->id,
        'type_dispense' => 'partielle',
        'montant_dispense' => 2000,
        'motif' => 'Test collision dispense CP',
        'date_dispense' => '2026-09-10',
    ]);
    // Dispense non conflictuelle uniquement sur CP T1
    $dispenseCpUnique = DispenseFinanciere::create([
        'compte_financier_id' => $compteCp->id,
        'frais_scolaire_id' => $fraisParCode['CP']['T1']->id,
        'type_dispense' => 'partielle',
        'montant_dispense' => 5000,
        'motif' => 'Dispense T1 CP',
        'date_dispense' => '2026-09-10',
    ]);

    $souscriptionCp = SouscriptionFraisOptionnel::create([
        'compte_financier_id' => $compteCp->id,
        'frais_scolaire_id' => $fraisBusCp->id,
        'montant' => 15000,
        'date_souscription' => '2026-09-10',
        'statut' => 'actif',
    ]);

    // Programme matières avec collision `(niveau_id, matiere_id, serie)` entre SIL et CP
    $groupe = GroupeMatiere::create(['code' => 'GRP-PRI', 'nom' => 'Base', 'ordre' => 1]);
    $matiereFr = Matiere::create(['code' => 'FR-PRI', 'nom' => 'Français']);
    $matiereAng = Matiere::create(['code' => 'ANG-PRI', 'nom' => 'Anglais']);

    MatiereNiveau::create([
        'niveau_id' => $niveaux['SIL']->id,
        'matiere_id' => $matiereFr->id,
        'groupe_matiere_id' => $groupe->id,
        'serie' => 'Toutes',
        'coefficient' => 2,
        'note_max' => 20,
    ]);
    MatiereNiveau::create([
        'niveau_id' => $niveaux['CP']->id,
        'matiere_id' => $matiereFr->id,
        'groupe_matiere_id' => $groupe->id,
        'serie' => 'Toutes',
        'coefficient' => 2,
        'note_max' => 20,
    ]);
    MatiereNiveau::create([
        'niveau_id' => $niveaux['CP']->id,
        'matiere_id' => $matiereAng->id,
        'groupe_matiere_id' => $groupe->id,
        'serie' => 'Toutes',
        'coefficient' => 1,
        'note_max' => 20,
    ]);

    $campagneCp = SmsCampagne::create([
        'etablissement_id' => $primaire->id,
        'titre' => 'Rappel CP',
        'message_brut' => 'Test rappel',
        'message_sanitise' => 'Test rappel',
        'cible_type' => 'niveau',
        'cible_id' => $niveaux['CP']->id,
        'statut' => 'termine',
    ]);

    // Exécution de la migration deux fois consécutives (test de stricte idempotence)
    $migration = require database_path('migrations/2026_11_14_020000_regroup_primary_niveaux_for_espoir.php');
    $migration->up();
    $migration->up();

    // 1. Maternelle totalement inchangée
    $matNiveaux = Niveau::where('etablissement_id', $ctx['maternelle']->id)->orderBy('ordre')->get();
    expect($matNiveaux)->toHaveCount(2)
        ->and($matNiveaux->pluck('code')->all())->toBe(['PS', 'GS']);

    // 2. Primaire réduit exactement à 6 niveaux officiels (NIV-1..3 + LVL-1..3)
    $priNiveauxFr = Niveau::where('cycle_id', $ctx['cycPriFr']->id)->orderBy('ordre')->get();
    $priNiveauxEn = Niveau::where('cycle_id', $ctx['cycPriEn']->id)->orderBy('ordre')->get();

    expect($priNiveauxFr)->toHaveCount(3)
        ->and($priNiveauxFr->pluck('code')->all())->toBe(['NIV-1', 'NIV-2', 'NIV-3'])
        ->and($priNiveauxFr->pluck('nom')->all())->toBe(['Niveau 1', 'Niveau 2', 'Niveau 3'])
        ->and($priNiveauxEn)->toHaveCount(3)
        ->and($priNiveauxEn->pluck('code')->all())->toBe(['LVL-1', 'LVL-2', 'LVL-3'])
        ->and($priNiveauxEn->pluck('nom')->all())->toBe(['Level 1', 'Level 2', 'Level 3']);

    $niv1 = $priNiveauxFr->firstWhere('code', 'NIV-1');
    $niv2 = $priNiveauxFr->firstWhere('code', 'NIV-2');
    $niv3 = $priNiveauxFr->firstWhere('code', 'NIV-3');
    $lvl1 = $priNiveauxEn->firstWhere('code', 'LVL-1');
    $lvl2 = $priNiveauxEn->firstWhere('code', 'LVL-2');
    $lvl3 = $priNiveauxEn->firstWhere('code', 'LVL-3');

    // Les IDs des 1ers paliers ont été conservés
    expect($niv1->id)->toBe($niveaux['SIL']->id)
        ->and($niv2->id)->toBe($niveaux['CE1']->id)
        ->and($niv3->id)->toBe($niveaux['CM1']->id)
        ->and($lvl1->id)->toBe($niveaux['CLASS-1']->id)
        ->and($lvl2->id)->toBe($niveaux['CLASS-3']->id)
        ->and($lvl3->id)->toBe($niveaux['CLASS-5']->id);

    // 3. Toutes les 12 classes intactes et correctement rattachées
    expect(Classe::whereIn('niveau_id', [$niv1->id, $niv2->id, $niv3->id, $lvl1->id, $lvl2->id, $lvl3->id])->count())->toBe(12)
        ->and($classes['SIL']->fresh()->niveau_id)->toBe($niv1->id)
        ->and($classes['CP']->fresh()->niveau_id)->toBe($niv1->id)
        ->and($classes['CE1']->fresh()->niveau_id)->toBe($niv2->id)
        ->and($classes['CE2']->fresh()->niveau_id)->toBe($niv2->id)
        ->and($classes['CM1']->fresh()->niveau_id)->toBe($niv3->id)
        ->and($classes['CM2']->fresh()->niveau_id)->toBe($niv3->id)
        ->and($classes['CLASS-1']->fresh()->niveau_id)->toBe($lvl1->id)
        ->and($classes['CLASS-2']->fresh()->niveau_id)->toBe($lvl1->id);

    // 4. Ventilations, bourses, dispenses et souscriptions remappées vers les frais conservés de NIV-1 sans perte
    expect($ventInscCp->fresh()->frais_scolaire_id)->toBe($fraisParCode['SIL']['INSC']->id)
        ->and($ventT1Cp->fresh()->frais_scolaire_id)->toBe($fraisParCode['SIL']['T1']->id)
        ->and($eleveBourseCp->fresh()->frais_scolaire_id)->toBe($fraisParCode['SIL']['T1']->id)
        ->and($dispenseCpUnique->fresh()->frais_scolaire_id)->toBe($fraisParCode['SIL']['T1']->id)
        ->and($souscriptionCp->fresh()->frais_scolaire_id)->toBe($fraisBusSil->id)
        ->and(DispenseFinanciere::where('compte_financier_id', $compteCp->id)->where('frais_scolaire_id', $fraisParCode['SIL']['INSC']->id)->count())->toBe(1)
        ->and(DispenseFinanciere::find($dispenseCpDup->id))->toBeNull()
        ->and(DispenseFinanciere::find($dispenseSilDup->id))->not->toBeNull();

    // Le frais ciblé CEP initialement posé sur CM2 survit sur NIV-3
    expect($fraisExamCm2->fresh()->niveau_id)->toBe($niv3->id)
        ->and(FraisScolaire::find($fraisParCode['CP']['INSC']->id))->toBeNull();

    // 5. BourseRemise, SmsCampagne et MatiereNiveau consolidés sans doublon
    expect($bourseCp->fresh()->niveau_id)->toBe($niv1->id)
        ->and($campagneCp->fresh()->cible_id)->toBe($niv1->id)
        ->and(MatiereNiveau::where('niveau_id', $niv1->id)->count())->toBe(2);

    // Vérifier qu'aucun enregistrement financier n'est orphelin
    expect((int) CompteFinancierEleve::find($compteCp->id)->total_paye)->toBe(45000)
        ->and(VersementCaisse::where('compte_financier_id', $compteCp->id)->count())->toBe(1);
});

test('bulletin annuel resout la promotion exacte au primaire regroupe tout en preservant le secondaire', function () {
    $institution = Institution::create([
        'code' => 'INST-PROMO',
        'nom' => 'Institution Promo',
        'sigle' => 'IP',
    ]);

    $annee = AnneeScolaire::create([
        'code' => '2026-2027',
        'nom' => '2026-2027',
        'date_debut' => '2026-09-01',
        'date_fin' => '2027-06-30',
        'statut' => 'en_cours',
        'is_current' => true,
    ]);

    // A. Primaire regroupé bilingue (FR + EN dans le même établissement pour tester le non-basculement inter-sections)
    $pri = Etablissement::create([
        'institution_id' => $institution->id,
        'code' => 'PRI-BILING',
        'nom' => 'École Primaire Bilingue',
        'statut_juridique' => 'Prive Laique',
        'ordre_enseignement' => 'Primaire',
        'mode_bulletin_defaut' => 'sans_competences',
    ]);

    $secFr = Section::create(['etablissement_id' => $pri->id, 'code' => 'FR', 'nom' => 'FR']);
    $secEn = Section::create(['etablissement_id' => $pri->id, 'code' => 'EN', 'nom' => 'EN']);
    $cycFr = Cycle::create(['etablissement_id' => $pri->id, 'section_id' => $secFr->id, 'code' => 'CYC-FR', 'nom' => 'Primaire FR', 'ordre' => 1]);
    $cycEn = Cycle::create(['etablissement_id' => $pri->id, 'section_id' => $secEn->id, 'code' => 'CYC-EN', 'nom' => 'Primaire EN', 'ordre' => 2]);

    $niv1 = Niveau::create(['etablissement_id' => $pri->id, 'cycle_id' => $cycFr->id, 'code' => 'NIV-1', 'nom' => 'Niveau 1', 'ordre' => 1]);
    $niv3 = Niveau::create(['etablissement_id' => $pri->id, 'cycle_id' => $cycFr->id, 'code' => 'NIV-3', 'nom' => 'Niveau 3', 'ordre' => 3]);
    // Ordre=4 volontairement supérieur à 3 dans le même établissement pour vérifier la non-fuite CM2 -> Level 1
    $lvl1 = Niveau::create(['etablissement_id' => $pri->id, 'cycle_id' => $cycEn->id, 'code' => 'LVL-1', 'nom' => 'Level 1', 'ordre' => 4]);

    $clsSil = Classe::create(['niveau_id' => $niv1->id, 'code' => 'SIL', 'nom' => 'SIL']);
    $clsCp = Classe::create(['niveau_id' => $niv1->id, 'code' => 'CP', 'nom' => 'CP']);
    $clsCm1 = Classe::create(['niveau_id' => $niv3->id, 'code' => 'CM1', 'nom' => 'CM1']);
    $clsCm2 = Classe::create(['niveau_id' => $niv3->id, 'code' => 'CM2', 'nom' => 'CM2']);
    $clsClass1 = Classe::create(['niveau_id' => $lvl1->id, 'code' => 'CLASS-1', 'nom' => 'Class 1']);
    Classe::create(['niveau_id' => $lvl1->id, 'code' => 'CLASS-2', 'nom' => 'Class 2']);

    $perPri = Periode::create([
        'annee_scolaire_id' => $annee->id,
        'etablissement_id' => $pri->id,
        'code' => 'T1-PRI',
        'nom' => 'Trimestre 1',
        'ordre' => 1,
        'date_debut' => '2026-09-01',
        'date_fin' => '2026-12-15',
    ]);
    $evalPri = Evaluation::create([
        'periode_id' => $perPri->id,
        'code' => 'SEQ1-PRI',
        'nom' => 'Séquence 1',
        'ordre' => 1,
        'ponderation' => 100,
        'statut' => 'ouverte',
    ]);

    $grp = GroupeMatiere::create(['code' => 'GRP-T', 'nom' => 'Général', 'ordre' => 1]);
    $mat = Matiere::create(['code' => 'MATH-T', 'nom' => 'Mathématiques']);
    foreach ([$niv1, $niv3, $lvl1] as $n) {
        MatiereNiveau::create([
            'niveau_id' => $n->id,
            'matiere_id' => $mat->id,
            'groupe_matiere_id' => $grp->id,
            'serie' => 'Toutes',
            'coefficient' => 2,
            'note_max' => 20,
            'is_comptabilisee' => true,
        ]);
    }

    $ensPri = Personnel::create([
        'etablissement_id' => $pri->id,
        'matricule' => 'ENS-PRI-1',
        'nom' => 'MBARGA',
        'prenom' => 'Paul',
        'sexe' => 'M',
        'telephone' => '+237 699 00 00 01',
        'fonction_principale' => 'Enseignant',
        'statut' => 'Actif',
    ]);

    $inscrireEtNoter = function (Etablissement $etab, Classe $cls, Evaluation $eval, string $matricule, float $valeur) use ($annee, $mat, $ensPri): int {
        $eleve = Eleve::create([
            'etablissement_id' => $etab->id,
            'matricule' => $matricule,
            'nom' => 'ELEVE',
            'prenom' => $matricule,
            'sexe' => 'M',
            'date_naissance' => '2018-01-01',
        ]);
        $inscr = Inscription::create([
            'eleve_id' => $eleve->id,
            'classe_id' => $cls->id,
            'annee_scolaire_id' => $annee->id,
            'statut' => 'validee',
        ]);
        $attr = Attribution::firstOrCreate(
            ['annee_scolaire_id' => $annee->id, 'classe_id' => $cls->id, 'matiere_id' => $mat->id],
            ['personnel_id' => $ensPri->id, 'volume_horaire_reel' => 5, 'is_active' => true]
        );
        Note::create([
            'inscription_id' => $inscr->id,
            'attribution_id' => $attr->id,
            'evaluation_id' => $eval->id,
            'valeur_note' => $valeur,
            'statut_presence' => 'present',
        ]);

        return $inscr->id;
    };

    $silAdmisId = $inscrireEtNoter($pri, $clsSil, $evalPri, 'SIL-OK', 14.0);
    $silRedoubleId = $inscrireEtNoter($pri, $clsSil, $evalPri, 'SIL-KO', 9.0);
    $cpAdmisId = $inscrireEtNoter($pri, $clsCp, $evalPri, 'CP-OK', 13.5);
    $cm1AdmisId = $inscrireEtNoter($pri, $clsCm1, $evalPri, 'CM1-OK', 12.0);
    $cm2AdmisId = $inscrireEtNoter($pri, $clsCm2, $evalPri, 'CM2-OK', 15.0);
    $class1AdmisId = $inscrireEtNoter($pri, $clsClass1, $evalPri, 'C1-OK', 14.5);

    // B. Secondaire mono-niveau (doit conserver à 100 % sa philosophie historique Sixième -> Cinquième)
    $sec = Etablissement::create([
        'institution_id' => $institution->id,
        'code' => 'LYC-SEC',
        'nom' => 'Lycée Test Secondaire',
        'statut_juridique' => 'Public',
        'ordre_enseignement' => 'Secondaire General',
        'mode_bulletin_defaut' => 'sans_competences',
    ]);
    $secS = Section::create(['etablissement_id' => $sec->id, 'code' => 'FR-SEC', 'nom' => 'FR']);
    $cycS = Cycle::create(['etablissement_id' => $sec->id, 'section_id' => $secS->id, 'code' => 'C1-SEC', 'nom' => '1er Cycle', 'ordre' => 1]);
    $niv6e = Niveau::create(['etablissement_id' => $sec->id, 'cycle_id' => $cycS->id, 'code' => '6EME', 'nom' => 'Sixième', 'ordre' => 1]);
    Niveau::create(['etablissement_id' => $sec->id, 'cycle_id' => $cycS->id, 'code' => '5EME', 'nom' => 'Cinquième', 'ordre' => 2]);
    $cls6eA = Classe::create(['niveau_id' => $niv6e->id, 'code' => '6EME-A', 'nom' => '6ème A']);

    MatiereNiveau::create([
        'niveau_id' => $niv6e->id,
        'matiere_id' => $mat->id,
        'groupe_matiere_id' => $grp->id,
        'serie' => 'Toutes',
        'coefficient' => 4,
        'note_max' => 20,
        'is_comptabilisee' => true,
    ]);

    $perSec = Periode::create([
        'annee_scolaire_id' => $annee->id,
        'etablissement_id' => $sec->id,
        'code' => 'T1-SEC',
        'nom' => 'Trimestre 1',
        'ordre' => 1,
        'date_debut' => '2026-09-01',
        'date_fin' => '2026-12-15',
    ]);
    $evalSec = Evaluation::create([
        'periode_id' => $perSec->id,
        'code' => 'SEQ1-SEC',
        'nom' => 'Séquence 1',
        'ordre' => 1,
        'ponderation' => 100,
        'statut' => 'ouverte',
    ]);

    $sixAdmisId = $inscrireEtNoter($sec, $cls6eA, $evalSec, '6E-OK', 13.0);
    $sixRedoubleId = $inscrireEtNoter($sec, $cls6eA, $evalSec, '6E-KO', 9.0);

    $service = app(BulletinCalculationService::class);

    $resSil = collect($service->calculerAnnuel($clsSil, $annee)['eleves']);
    $resCp = collect($service->calculerAnnuel($clsCp, $annee)['eleves']);
    $resCm1 = collect($service->calculerAnnuel($clsCm1, $annee)['eleves']);
    $resCm2 = collect($service->calculerAnnuel($clsCm2, $annee)['eleves']);
    $resC1 = collect($service->calculerAnnuel($clsClass1, $annee)['eleves']);
    $res6e = collect($service->calculerAnnuel($cls6eA, $annee)['eleves']);

    expect($resSil->firstWhere('inscription_id', $silAdmisId)['decision_finale'])->toBe('Promu(e) en classe de CP.')
        ->and($resSil->firstWhere('inscription_id', $silRedoubleId)['decision_finale'])->toBe('Redouble la classe de SIL.')
        ->and($resCp->firstWhere('inscription_id', $cpAdmisId)['decision_finale'])->toBe('Promu(e) en classe de CE1.')
        ->and($resCm1->firstWhere('inscription_id', $cm1AdmisId)['decision_finale'])->toBe('Promu(e) en classe de CM2.')
        ->and($resCm2->firstWhere('inscription_id', $cm2AdmisId)['decision_finale'])->toBe('Promu(e) en classe de CLASSE SUPÉRIEURE.')
        ->and($resC1->firstWhere('inscription_id', $class1AdmisId)['decision_finale'])->toBe('Promu(e) en classe de CLASS 2.')
        ->and($res6e->firstWhere('inscription_id', $sixAdmisId)['decision_finale'])->toBe('Promu(e) en classe de Cinquième.')
        ->and($res6e->firstWhere('inscription_id', $sixRedoubleId)['decision_finale'])->toBe('Redouble la classe de Sixième.');
});

test('seeder espoir cree la structure primaire officielle a 6 niveaux sans casser la maternelle', function () {
    seed(EspoirComplexeSeeder::class);

    $maternelle = Etablissement::where('code', 'ESP-MAT')->first();
    $primaire = Etablissement::where('code', 'ESP-PRI')->first();

    expect($maternelle)->not->toBeNull()
        ->and($primaire)->not->toBeNull();

    $niveauxMat = Niveau::where('etablissement_id', $maternelle->id)->count();
    $niveauxPri = Niveau::where('etablissement_id', $primaire->id)->orderBy('cycle_id')->orderBy('ordre')->get();
    $classesPri = Classe::whereIn('niveau_id', $niveauxPri->pluck('id'))->count();
    $fraisPri = FraisScolaire::where('etablissement_id', $primaire->id)->count();

    expect($niveauxMat)->toBe(7)
        ->and($niveauxPri)->toHaveCount(6)
        ->and($niveauxPri->pluck('code')->sort()->values()->all())->toBe(['LVL-1', 'LVL-2', 'LVL-3', 'NIV-1', 'NIV-2', 'NIV-3'])
        ->and($classesPri)->toBe(12)
        ->and($fraisPri)->toBe(18);
});
