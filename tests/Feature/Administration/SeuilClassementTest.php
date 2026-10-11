<?php

/** @var \Tests\TestCase $this */

use App\Models\AnneeScolaire;
use App\Models\Attribution;
use App\Models\Classe;
use App\Models\Cycle;
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
use App\Services\BulletinCalculationService;
use Spatie\Permission\Models\Role;
use function Pest\Laravel\actingAs;

/**
 * Tests de fonctionnalités pour l'Éligibilité au Classement par Quota Minimal de Coefficients (MINESEC / Gesco).
 *
 * Vérifie :
 * - Qu'un élève ayant composé dans un nombre insuffisant de matières (< seuil de coefficients) est marqué "NC" (Non Classé).
 * - Qu'un élève non classé (NC) ne vole pas la 1ère place d'un élève assidu ayant composé dans toutes les matières.
 * - La prise en compte du mode pourcentage (ex: 60% des coefficients de la classe) et du mode valeur fixe.
 * - L'enregistrement des paramètres de seuil dans la configuration de l'établissement.
 */

beforeEach(function () {
    /** @var \Tests\TestCase $this */
    $this->withoutVite();

    Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);

    $this->adminUser = User::factory()->create();
    $this->adminUser->assignRole('super-admin');

    $this->institution = Institution::create([
        'code' => 'INST-SEUIL',
        'nom' => 'Délégation Régionale Ouest',
        'ville' => 'Bafoussam',
    ]);

    $this->etablissement = Etablissement::create([
        'institution_id' => $this->institution->id,
        'code' => 'LYCEE-SEUIL',
        'nom' => 'Lycée Classique de Bafoussam',
        'statut_juridique' => 'Public',
        'ordre_enseignement' => 'Secondaire_General',
        'seuil_classement_actif' => true,
        'mode_seuil_classement' => 'pourcentage',
        'seuil_classement_valeur' => 60.00, // 60% requis pour être classé
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
        'nom' => 'Seconde',
        'code' => '2NDE',
        'ordre' => 5,
    ]);

    $this->classe = Classe::create([
        'niveau_id' => $this->niveau->id,
        'nom' => 'Seconde C',
        'code' => '2C',
        'serie' => 'C',
    ]);

    $this->periode = Periode::create([
        'etablissement_id' => $this->etablissement->id,
        'annee_scolaire_id' => $this->annee->id,
        'nom' => 'Premier Trimestre',
        'code' => 'T1',
        'ordre' => 1,
        'date_debut' => '2026-09-01',
        'date_fin' => '2026-11-30',
    ]);

    $this->evaluation1 = Evaluation::create([
        'periode_id' => $this->periode->id,
        'nom' => 'Évaluation 1',
        'code' => 'EV1',
        'ordre' => 1,
        'ponderation' => 1.0,
        'date_evaluation' => '2026-10-15',
    ]);

    $this->groupe = GroupeMatiere::create([
        'etablissement_id' => $this->etablissement->id,
        'code' => 'SCI',
        'nom' => 'Matières Scientifiques',
        'ordre' => 1,
    ]);

    // Création de 3 matières : Maths (coeff 5), Physique (coeff 4), SVT (coeff 3) => Total = 12
    $this->matMaths = Matiere::create([
        'institution_id' => $this->institution->id,
        'nom' => 'Mathématiques',
        'code' => 'MATH',
        'coefficient_defaut' => 5,
    ]);
    MatiereNiveau::create([
        'niveau_id' => $this->niveau->id,
        'matiere_id' => $this->matMaths->id,
        'groupe_matiere_id' => $this->groupe->id,
        'coefficient' => 5.0,
        'serie' => 'C',
    ]);

    $this->matPhys = Matiere::create([
        'institution_id' => $this->institution->id,
        'nom' => 'Physique-Chimie',
        'code' => 'PHYS',
        'coefficient_defaut' => 4,
    ]);
    MatiereNiveau::create([
        'niveau_id' => $this->niveau->id,
        'matiere_id' => $this->matPhys->id,
        'groupe_matiere_id' => $this->groupe->id,
        'coefficient' => 4.0,
        'serie' => 'C',
    ]);

    $this->matSvt = Matiere::create([
        'institution_id' => $this->institution->id,
        'nom' => 'Sciences de la Vie et de la Terre',
        'code' => 'SVT',
        'coefficient_defaut' => 3,
    ]);
    MatiereNiveau::create([
        'niveau_id' => $this->niveau->id,
        'matiere_id' => $this->matSvt->id,
        'groupe_matiere_id' => $this->groupe->id,
        'coefficient' => 3.0,
        'serie' => 'C',
    ]);

    $this->prof = Personnel::create([
        'etablissement_id' => $this->etablissement->id,
        'user_id' => $this->adminUser->id,
        'matricule' => 'ENS001',
        'nom' => 'FOTSO',
        'prenom' => 'Jean',
        'sexe' => 'M',
        'telephone' => '670000000',
        'fonction_principale' => 'Enseignant',
        'type_contrat' => 'permanent',
        'date_embauche' => '2020-09-01',
        'statut' => 'actif',
        'is_enseignant' => true,
    ]);

    $this->attrMaths = Attribution::create([
        'classe_id' => $this->classe->id,
        'matiere_id' => $this->matMaths->id,
        'personnel_id' => $this->prof->id,
        'annee_scolaire_id' => $this->annee->id,
        'is_active' => true,
    ]);

    $this->attrPhys = Attribution::create([
        'classe_id' => $this->classe->id,
        'matiere_id' => $this->matPhys->id,
        'personnel_id' => $this->prof->id,
        'annee_scolaire_id' => $this->annee->id,
        'is_active' => true,
    ]);

    $this->attrSvt = Attribution::create([
        'classe_id' => $this->classe->id,
        'matiere_id' => $this->matSvt->id,
        'personnel_id' => $this->prof->id,
        'annee_scolaire_id' => $this->annee->id,
        'is_active' => true,
    ]);

    // Élève A : Assidu (fait toutes les matières, moyenne = 14)
    $this->eleveAssidu = Eleve::create([
        'matricule' => '26SEUIL01',
        'nom' => 'KAMGA',
        'prenom' => 'Paul',
        'date_naissance' => '2009-02-14',
        'lieu_naissance' => 'Bafoussam',
        'sexe' => 'M',
        'statut' => 'actif',
    ]);
    $this->inscAssidu = Inscription::create([
        'etablissement_id' => $this->etablissement->id,
        'eleve_id' => $this->eleveAssidu->id,
        'classe_id' => $this->classe->id,
        'annee_scolaire_id' => $this->annee->id,
        'date_inscription' => '2026-09-02',
        'statut' => 'validee',
    ]);

    // Élève B : Absent / Partiel (ne fait que SVT coeff 3 avec 18/20, absent en Maths et Phys)
    $this->eleveAbsent = Eleve::create([
        'matricule' => '26SEUIL02',
        'nom' => 'TAGNE',
        'prenom' => 'Lucas',
        'date_naissance' => '2009-05-18',
        'lieu_naissance' => 'Bafoussam',
        'sexe' => 'M',
        'statut' => 'actif',
    ]);
    $this->inscAbsent = Inscription::create([
        'etablissement_id' => $this->etablissement->id,
        'eleve_id' => $this->eleveAbsent->id,
        'classe_id' => $this->classe->id,
        'annee_scolaire_id' => $this->annee->id,
        'date_inscription' => '2026-09-02',
        'statut' => 'validee',
    ]);
});

test('un eleve ayant un volume de coefficients inferieur au seuil est Non Classe (NC) et ne vole pas le 1er rang', function () {
    // Saisie des notes pour Élève Assidu (14 en Maths, 14 en Phys, 14 en SVT => Moyenne 14.00, Coeffs = 12)
    Note::create([
        'inscription_id' => $this->inscAssidu->id,
        'attribution_id' => $this->attrMaths->id,
        'evaluation_id' => $this->evaluation1->id,
        'valeur_note' => 14.0,
        'statut_presence' => 'present',
    ]);
    Note::create([
        'inscription_id' => $this->inscAssidu->id,
        'attribution_id' => $this->attrPhys->id,
        'evaluation_id' => $this->evaluation1->id,
        'valeur_note' => 14.0,
        'statut_presence' => 'present',
    ]);
    Note::create([
        'inscription_id' => $this->inscAssidu->id,
        'attribution_id' => $this->attrSvt->id,
        'evaluation_id' => $this->evaluation1->id,
        'valeur_note' => 14.0,
        'statut_presence' => 'present',
    ]);

    // Saisie des notes pour Élève Absent (ne fait que SVT avec 18.00 => Moyenne 18.00, mais Coeffs = 3 sur 12 = 25% < 60%)
    Note::create([
        'inscription_id' => $this->inscAbsent->id,
        'attribution_id' => $this->attrSvt->id,
        'evaluation_id' => $this->evaluation1->id,
        'valeur_note' => 18.0,
        'statut_presence' => 'present',
    ]);

    $service = app(BulletinCalculationService::class);
    $resultat = $service->calculerTrimestre($this->classe, $this->periode);

    $eleves = collect($resultat['eleves']);
    $dataAssidu = $eleves->firstWhere('inscription_id', $this->inscAssidu->id);
    $dataAbsent = $eleves->firstWhere('inscription_id', $this->inscAbsent->id);

    // Vérifications sur l'élève absent
    expect($dataAbsent['moyenne_generale'])->toBe(18.0);
    expect($dataAbsent['total_coefficients'])->toBe(3.0);
    expect($dataAbsent['is_eligible_classement'])->toBeFalse();
    expect($dataAbsent['rang_general'])->toBe('NC');
    expect($dataAbsent['motif_non_classe'])->toContain('Coefficients insuffisants');

    // Vérifications sur l'élève assidu (doit être classé 1er en titre !)
    expect($dataAssidu['moyenne_generale'])->toBe(14.0);
    expect($dataAssidu['total_coefficients'])->toBe(12.0);
    expect($dataAssidu['is_eligible_classement'])->toBeTrue();
    expect($dataAssidu['rang_general'])->toBe('1er');

    // Vérification des statistiques de classe
    $stats = $resultat['stats_classe'];
    expect($stats['effectif_classe'])->toBe(1);
    expect($stats['effectif_non_classe'])->toBe(1);
    expect($stats['seuil_coefficients_classement'])->toBe(7.2); // 60% de 12
});

test('si le filtre de classement est desactive, tous les eleves ayant une moyenne sont classes selon la note brute', function () {
    // Désactivation du seuil de classement
    $this->etablissement->update([
        'seuil_classement_actif' => false,
    ]);

    Note::create([
        'inscription_id' => $this->inscAssidu->id,
        'attribution_id' => $this->attrMaths->id,
        'evaluation_id' => $this->evaluation1->id,
        'valeur_note' => 14.0,
        'statut_presence' => 'present',
    ]);
    Note::create([
        'inscription_id' => $this->inscAbsent->id,
        'attribution_id' => $this->attrSvt->id,
        'evaluation_id' => $this->evaluation1->id,
        'valeur_note' => 18.0,
        'statut_presence' => 'present',
    ]);

    $service = app(BulletinCalculationService::class);
    $resultat = $service->calculerTrimestre($this->classe, $this->periode);

    $eleves = collect($resultat['eleves']);
    $dataAssidu = $eleves->firstWhere('inscription_id', $this->inscAssidu->id);
    $dataAbsent = $eleves->firstWhere('inscription_id', $this->inscAbsent->id);

    // Quand désactivé : l'élève à 18/20 est classé 1er, l'élève à 14/20 est 2e
    expect($dataAbsent['is_eligible_classement'])->toBeTrue();
    expect($dataAbsent['rang_general'])->toBe('1er');
    expect($dataAssidu['rang_general'])->toBe('2e');
});

test('on peut configurer le seuil de classement via les parametres de etablissement', function () {
    actingAs($this->adminUser);

    $response = $this->put(route('administration.etablissements.settings.update', $this->etablissement), [
        'code' => $this->etablissement->code,
        'nom' => $this->etablissement->nom,
        'statut_juridique' => $this->etablissement->statut_juridique,
        'ordre_enseignement' => $this->etablissement->ordre_enseignement,
        'mode_bulletin_defaut' => 'sans_competences',
        'type_calcul_trimestre' => 'arithmetique',
        'seuil_absences_decheance_mentions' => 40,
        'verrou_solvabilite_actif' => false,
        'bonus_paiement_anticipe_actif' => false,
        'seuil_classement_actif' => true,
        'mode_seuil_classement' => 'valeur_fixe',
        'seuil_classement_valeur' => 15.0,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('etablissements', [
        'id' => $this->etablissement->id,
        'seuil_classement_actif' => true,
        'mode_seuil_classement' => 'valeur_fixe',
        'seuil_classement_valeur' => 15.0,
    ]);
});
