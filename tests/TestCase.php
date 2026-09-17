<?php

namespace Tests;

use AllowDynamicProperties;
use App\Models\AnneeScolaire;
use App\Models\Attribution;
use App\Models\Classe;
use App\Models\Cycle;
use App\Models\Eleve;
use App\Models\Etablissement;
use App\Models\Evaluation;
use App\Models\FraisScolaire;
use App\Models\GroupeMatiere;
use App\Models\Inscription;
use App\Models\Institution;
use App\Models\Matiere;
use App\Models\Niveau;
use App\Models\Periode;
use App\Models\Personnel;
use App\Models\Section;
use App\Models\TypeFrais;
use App\Models\User;
use App\Models\VersementCaisse;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

/**
 * Classe de base pour tous les tests d'intégration et de fonctionnalités de SYGES.
 * Déclare explicitement les propriétés dynamiques récurrentes utilisées par les suites Pest
 * afin de satisfaire l'analyse statique Intelephense / PHPStan.
 *
 * @property User|null $adminUser
 * @property User|null $unauthorizedUser
 * @property User|null $directorUser
 * @property User|null $parentUser
 * @property AnneeScolaire|null $annee
 * @property AnneeScolaire|null $anneeScolaire
 * @property Classe|null $classe
 * @property Institution|null $institution
 * @property Etablissement|null $etablissement
 * @property Section|null $section
 * @property Cycle|null $cycle
 * @property Niveau|null $niveau
 * @property Matiere|null $matiere
 * @property Matiere|null $matiere2
 * @property GroupeMatiere|null $groupe
 * @property GroupeMatiere|null $groupeMatiere
 * @property Personnel|null $enseignant
 * @property Personnel|null $caissier
 * @property Periode|null $periode
 * @property Periode|null $trimestre1
 * @property Evaluation|null $seq1
 * @property Evaluation|null $seq2
 * @property Inscription|null $inscription
 * @property Inscription|null $inscription1
 * @property Inscription|null $inscription2
 * @property Inscription|null $inscription3
 * @property Eleve|null $eleve1
 * @property Eleve|null $eleve2
 * @property Eleve|null $enfant1
 * @property Eleve|null $enfant2
 * @property Attribution|null $attributionMath
 * @property Attribution|null $attributionFran
 * @property TypeFrais|null $typeFrais
 * @property FraisScolaire|null $fraisScolaire
 * @property VersementCaisse|null $versement1
 */
#[AllowDynamicProperties]
abstract class TestCase extends BaseTestCase
{
    /**
     * Administrateur avec profil super-admin utilisé pour authentifier les requêtes.
     *
     * @var User|null
     */
    public ?User $adminUser = null;

    /**
     * Utilisateur sans autorisations spécifiques pour tester les accès interdits (403 Forbidden).
     *
     * @var User|null
     */
    public ?User $unauthorizedUser = null;

    /**
     * Utilisateur avec profil directeur des études pour tester les accès pédagogiques et délibérations.
     *
     * @var User|null
     */
    public ?User $directorUser = null;

    /**
     * Utilisateur avec profil parent d'élève pour tester le portail parent.
     *
     * @var User|null
     */
    public ?User $parentUser = null;

    /**
     * Année scolaire active utilisée dans les contextes de tests académiques.
     *
     * @var AnneeScolaire|null
     */
    public ?AnneeScolaire $annee = null;

    /**
     * Alias d'année scolaire pour les tests d'attribution.
     *
     * @var AnneeScolaire|null
     */
    public ?AnneeScolaire $anneeScolaire = null;

    /**
     * Classe pédagogique de référence pour les inscriptions et matières.
     *
     * @var Classe|null
     */
    public ?Classe $classe = null;

    /**
     * Institution scolaire principale de référence.
     *
     * @var Institution|null
     */
    public ?Institution $institution = null;

    /**
     * Établissement scolaire de référence rattaché à l'institution.
     *
     * @var Etablissement|null
     */
    public ?Etablissement $etablissement = null;

    /**
     * Section linguistique ou pédagogique (ex: Francophone / Anglophone).
     *
     * @var Section|null
     */
    public ?Section $section = null;

    /**
     * Cycle d'enseignement (ex: Premier Cycle, Second Cycle).
     *
     * @var Cycle|null
     */
    public ?Cycle $cycle = null;

    /**
     * Niveau d'enseignement associé aux cycles et classes (ex: 6ème, 5ème).
     *
     * @var Niveau|null
     */
    public ?Niveau $niveau = null;

    /**
     * Matière principale d'enseignement pour les tests d'attribution et de notes.
     *
     * @var Matiere|null
     */
    public ?Matiere $matiere = null;

    /**
     * Seconde matière d'enseignement pour les tests de répartition.
     *
     * @var Matiere|null
     */
    public ?Matiere $matiere2 = null;

    /**
     * Groupe de matières pédagogique (ex: Sciences, Littéraire).
     *
     * @var GroupeMatiere|null
     */
    public ?GroupeMatiere $groupeMatiere = null;

    /**
     * Groupe de matières de test (alias court utilisé dans certains tests d'administration).
     *
     * @var GroupeMatiere|null
     */
    public ?GroupeMatiere $groupe = null;

    /**
     * Personnel enseignant utilisé pour les affectations pédagogiques.
     *
     * @var Personnel|null
     */
    public ?Personnel $enseignant = null;

    /**
     * Personnel avec profil caissier pour les tests financiers.
     *
     * @var Personnel|null
     */
    public ?Personnel $caissier = null;

    /**
     * Période trimestrielle pour les tests de bulletins.
     *
     * @var Periode|null
     */
    public ?Periode $periode = null;

    /**
     * Période trimestrielle 1 pour les tests de statistiques et portail.
     *
     * @var Periode|null
     */
    public ?Periode $trimestre1 = null;

    /**
     * Première séquence d'évaluation.
     *
     * @var Evaluation|null
     */
    public ?Evaluation $seq1 = null;

    /**
     * Seconde séquence d'évaluation.
     *
     * @var Evaluation|null
     */
    public ?Evaluation $seq2 = null;

    /**
     * Inscription d'élève générique de référence.
     *
     * @var Inscription|null
     */
    public ?Inscription $inscription = null;

    /**
     * Première inscription d'élève de référence.
     *
     * @var Inscription|null
     */
    public ?Inscription $inscription1 = null;

    /**
     * Seconde inscription d'élève de référence.
     *
     * @var Inscription|null
     */
    public ?Inscription $inscription2 = null;

    /**
     * Troisième inscription d'élève de référence.
     *
     * @var Inscription|null
     */
    public ?Inscription $inscription3 = null;

    /**
     * Élève de référence 1.
     *
     * @var Eleve|null
     */
    public ?Eleve $eleve1 = null;

    /**
     * Élève de référence 2.
     *
     * @var Eleve|null
     */
    public ?Eleve $eleve2 = null;

    /**
     * Premier enfant rattaché pour les tests du portail parent.
     *
     * @var Eleve|null
     */
    public ?Eleve $enfant1 = null;

    /**
     * Second enfant rattaché pour les tests du portail parent.
     *
     * @var Eleve|null
     */
    public ?Eleve $enfant2 = null;

    /**
     * Attribution de mathématiques.
     *
     * @var Attribution|null
     */
    public ?Attribution $attributionMath = null;

    /**
     * Attribution de français.
     *
     * @var Attribution|null
     */
    public ?Attribution $attributionFran = null;

    /**
     * Type de frais scolaire pour les tests financiers.
     *
     * @var TypeFrais|null
     */
    public ?TypeFrais $typeFrais = null;

    /**
     * Frais scolaire configuré pour une classe ou un établissement.
     *
     * @var FraisScolaire|null
     */
    public ?FraisScolaire $fraisScolaire = null;

    /**
     * Versement effectué en caisse pour les tests de solvabilité et reçus.
     *
     * @var VersementCaisse|null
     */
    public ?VersementCaisse $versement1 = null;

    /**
     * Ignore le test courant si la fonctionnalité Fortify requise n'est pas activée.
     *
     * @param string $feature Nom de la fonctionnalité Fortify
     * @param string|null $message Motif facultatif d'exclusion
     * @return void
     */
    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
