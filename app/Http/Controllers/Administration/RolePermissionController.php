<?php

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Contrôleur de gestion des rôles et des permissions granulaires de l'application SYGES.
 *
 * Fournit les opérations CRUD complètes sur les profils d'accès (rôles)
 * et permet la synchronisation granulaire des permissions organisées par module scolaire
 * selon le principe du moindre privilège (Least Privilege / Zero Trust).
 */
class RolePermissionController extends Controller implements HasMiddleware
{
    /**
     * Définition de la matrice des middlewares d'autorisation pour le module Rôles & Permissions.
     *
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:roles.view', only: ['index']),
            new Middleware('can:roles.create', only: ['store']),
            new Middleware('can:roles.edit', only: ['update']),
            new Middleware('can:roles.delete', only: ['destroy']),
        ];
    }

    /**
     * Dictionnaire des libellés conviviaux pour les rôles prédéfinis du système.
     *
     * @var array<string, string>
     */
    protected array $roleLabels = [
        'super-admin' => 'Super Administrateur',
        'admin' => 'Administrateur',
        'directeur-etudes' => 'Directeur des Études / Proviseur',
        'censeur' => 'Censeur des Études',
        'surveillant-general' => 'Surveillant Général',
        'intendant' => 'Intendant / Comptable',
        'enseignant' => 'Enseignant',
        'secretaire' => 'Secrétaire',
    ];

    /**
     * Structure des modules thématiques regroupant l'ensemble des permissions scolaires.
     *
     * @var array<string, array{id: string, name: string, description: string, icon: string}>
     */
    protected array $modules = [
        'institution' => [
            'id' => 'institution',
            'name' => 'Institution & Établissements',
            'description' => 'Gestion de l\'institution tutelle, création, réglages et suppression des campus scolaires.',
            'icon' => 'School',
        ],
        'structures' => [
            'id' => 'structures',
            'name' => 'Structures Pédagogiques & Classes',
            'description' => 'Arborescence des sections linguistiques, cycles, niveaux d\'études, séries et classes.',
            'icon' => 'Layers',
        ],
        'periodes' => [
            'id' => 'periodes',
            'name' => 'Années Scolaires & Évaluations',
            'description' => 'Années scolaires, trimestres, séquences d\'évaluation, modes de calcul et verrouillage.',
            'icon' => 'CalendarRange',
        ],
        'matieres' => [
            'id' => 'matieres',
            'name' => 'Matières & Coefficients',
            'description' => 'Catalogue des disciplines, blocs de matières et grille des coefficients par série.',
            'icon' => 'BookOpen',
        ],
        'eleves' => [
            'id' => 'eleves',
            'name' => 'Élèves, Inscriptions & Cartes',
            'description' => 'Répertoire des apprenants, réinscriptions, transferts et tirage des badges d\'identité.',
            'icon' => 'GraduationCap',
        ],
        'personnels' => [
            'id' => 'personnels',
            'name' => 'Personnel & Départements RH',
            'description' => 'Registre des enseignants et administratifs, statuts, fiches RH et départements.',
            'icon' => 'Briefcase',
        ],
        'attributions' => [
            'id' => 'attributions',
            'name' => 'Attribution des Enseignements',
            'description' => 'Affectation des cours aux professeurs, charges horaires et professeurs principaux.',
            'icon' => 'Network',
        ],
        'evaluations' => [
            'id' => 'evaluations',
            'name' => 'Notes & Évaluations',
            'description' => 'Grilles de saisie des notes, importations, verrouillage et autorisations de modification.',
            'icon' => 'FileSpreadsheet',
        ],
        'bulletins' => [
            'id' => 'bulletins',
            'name' => 'Bulletins Scolaires',
            'description' => 'Calcul des moyennes, rangs, cotes alphabétiques et génération des bulletins PDF.',
            'icon' => 'Award',
        ],
        'finances' => [
            'id' => 'finances',
            'name' => 'Finances, Caisses & Versements',
            'description' => 'Gestion de la scolarité, encaissements, reçus de caisse, bourses et échéanciers.',
            'icon' => 'Receipt',
        ],
        'depenses' => [
            'id' => 'depenses',
            'name' => 'Dépenses & Décharges de Caisse',
            'description' => 'Décaissements, bons de décharge certifiés, centres de coûts et Bilan Entrées/Sorties.',
            'icon' => 'Wallet',
        ],
        'discipline' => [
            'id' => 'discipline',
            'name' => 'Discipline & Assiduité',
            'description' => 'Pointage des absences, retards, motifs de justification et sanctions scolaires.',
            'icon' => 'Scale',
        ],
        'conseil_discipline' => [
            'id' => 'conseil_discipline',
            'name' => 'Conseil de Discipline & Consignes',
            'description' => 'Sessions formelles de CD, convocations parents émargées, PV de délibération et retenues.',
            'icon' => 'Gavel',
        ],
        'dispenses' => [
            'id' => 'dispenses',
            'name' => 'Dispenses & Dérogations Médicales',
            'description' => 'Certificats médicaux, dispense d\'EPS, neutralisation des coefficients et PV d\'homologation.',
            'icon' => 'HeartHandshake',
        ],
        'competences_apc' => [
            'id' => 'competences_apc',
            'name' => 'Pédagogie par Compétences (APC)',
            'description' => 'Compétences périodiques ministérielles, duplication inter-classes et matrice des cotes.',
            'icon' => 'Sparkles',
        ],
        'progression' => [
            'id' => 'progression',
            'name' => 'Cahier de Texte & Progression',
            'description' => 'Séances de cours réalisées, visas d\'inspection administrative et suivi du programme.',
            'icon' => 'Clock',
        ],
        'emplois_du_temps' => [
            'id' => 'emplois_du_temps',
            'name' => 'Emplois du Temps & Salles',
            'description' => 'Grilles horaires hebdomadaires, planning des classes/profs et gestion des salles.',
            'icon' => 'Calendar',
        ],
        'communication' => [
            'id' => 'communication',
            'name' => 'Communication & SMS',
            'description' => 'Envoi de SMS individuels ou groupés aux parents et paramétrage de la passerelle.',
            'icon' => 'MessageSquare',
        ],
        'deliberations' => [
            'id' => 'deliberations',
            'name' => 'Conseils de Classe & Statistiques',
            'description' => 'Tableaux de bord d\'établissement, synthèses de délibérations et validation des mentions.',
            'icon' => 'BarChart',
        ],
        'archivage' => [
            'id' => 'archivage',
            'name' => 'Archivage, Clôture & Reconduction',
            'description' => 'Archivage annuel, duplication des structures pour la rentrée et formats de numérotation.',
            'icon' => 'Archive',
        ],
        'users' => [
            'id' => 'users',
            'name' => 'Utilisateurs & Profils',
            'description' => 'Gestion des comptes d\'accès utilisateurs, identifiants et verrouillage.',
            'icon' => 'Users',
        ],
        'roles' => [
            'id' => 'roles',
            'name' => 'Rôles & Permissions Système',
            'description' => 'Matrice des privilèges de sécurité et profils d\'accès applicatifs.',
            'icon' => 'ShieldCheck',
        ],
    ];

    /**
     * Dictionnaire exhaustif des permissions granulaires avec module parent, libellé et description.
     *
     * @var array<string, array{module: string, label: string, description: string}>
     */
    protected array $permissionDefinitions = [
        // ==========================================
        // 1. Utilisateurs & Profils
        // ==========================================
        'users.view' => [
            'module' => 'users',
            'label' => 'Consulter les utilisateurs',
            'description' => 'Visualiser les comptes d\'accès, identifiants et états d\'activation.',
        ],
        'users.create' => [
            'module' => 'users',
            'label' => 'Créer des utilisateurs',
            'description' => 'Générer de nouveaux comptes d\'accès applicatifs avec mot de passe initial.',
        ],
        'users.edit' => [
            'module' => 'users',
            'label' => 'Modifier les utilisateurs',
            'description' => 'Mettre à jour les identifiants, emails et rôles des comptes utilisateurs.',
        ],
        'users.block' => [
            'module' => 'users',
            'label' => 'Bloquer / Activer des utilisateurs',
            'description' => 'Suspendre ou rétablir l\'accès au système pour un utilisateur.',
        ],

        // ==========================================
        // 2. Rôles & Sécurité
        // ==========================================
        'roles.view' => [
            'module' => 'roles',
            'label' => 'Consulter les rôles',
            'description' => 'Voir la liste des profils d\'accès et la matrice des privilèges associés.',
        ],
        'roles.create' => [
            'module' => 'roles',
            'label' => 'Créer des rôles',
            'description' => 'Créer un nouveau profil de sécurité personnalisé.',
        ],
        'roles.edit' => [
            'module' => 'roles',
            'label' => 'Modifier les rôles',
            'description' => 'Modifier le libellé et synchroniser les permissions assignées à un rôle.',
        ],
        'roles.delete' => [
            'module' => 'roles',
            'label' => 'Supprimer des rôles',
            'description' => 'Supprimer un profil personnalisé inutilisé (les rôles système restent protégés).',
        ],

        // ==========================================
        // 3. Institution & Établissements
        // ==========================================
        'institutions.view' => [
            'module' => 'institution',
            'label' => 'Consulter l\'institution',
            'description' => 'Visualiser les coordonnées de l\'organisme de tutelle et des campus.',
        ],
        'institutions.edit' => [
            'module' => 'institution',
            'label' => 'Modifier l\'institution',
            'description' => 'Mettre à jour le nom officiel, le sigle, le logo et les contacts de l\'institution.',
        ],
        'etablissements.view' => [
            'module' => 'institution',
            'label' => 'Consulter les établissements',
            'description' => 'Voir la liste des campus scolaires et leurs caractéristiques.',
        ],
        'etablissements.create' => [
            'module' => 'institution',
            'label' => 'Créer un établissement',
            'description' => 'Ajouter un nouveau campus scolaire rattaché à l\'institution.',
        ],
        'etablissements.edit' => [
            'module' => 'institution',
            'label' => 'Modifier un établissement',
            'description' => 'Mettre à jour le nom, l\'adresse, le décret et les contacts d\'un établissement.',
        ],
        'etablissements.delete' => [
            'module' => 'institution',
            'label' => 'Supprimer un établissement',
            'description' => 'Supprimer définitivement un campus scolaire (action irréversible).',
        ],
        'etablissements.settings' => [
            'module' => 'institution',
            'label' => 'Configurer les règles de l\'établissement',
            'description' => 'Modifier les règles pédagogiques (seuil de classement MINESEC, déchéance) et financières.',
        ],
        'etablissements.manage' => [
            'module' => 'institution',
            'label' => 'Gestion globale établissement (Hérité)',
            'description' => 'Droit étendu de gestion administrative de l\'établissement.',
        ],

        // ==========================================
        // 4. Structures Pédagogiques & Classes
        // ==========================================
        'sections.view' => [
            'module' => 'structures',
            'label' => 'Consulter les sections',
            'description' => 'Voir les sections linguistiques (ex: Francophone, Anglophone).',
        ],
        'sections.create' => [
            'module' => 'structures',
            'label' => 'Créer une section',
            'description' => 'Ajouter une nouvelle section linguistique au campus.',
        ],
        'sections.edit' => [
            'module' => 'structures',
            'label' => 'Modifier une section',
            'description' => 'Renommer ou ajuster le code d\'une section linguistique.',
        ],
        'sections.delete' => [
            'module' => 'structures',
            'label' => 'Supprimer une section',
            'description' => 'Supprimer une section linguistique si elle ne contient aucun cycle.',
        ],
        'cycles.view' => [
            'module' => 'structures',
            'label' => 'Consulter les cycles',
            'description' => 'Voir les cycles scolaires (ex: 1er Cycle, 2nd Cycle).',
        ],
        'cycles.create' => [
            'module' => 'structures',
            'label' => 'Créer un cycle',
            'description' => 'Ajouter un nouveau cycle scolaire.',
        ],
        'cycles.edit' => [
            'module' => 'structures',
            'label' => 'Modifier un cycle',
            'description' => 'Mettre à jour l\'intitulé et l\'ordre pédagogique du cycle.',
        ],
        'cycles.delete' => [
            'module' => 'structures',
            'label' => 'Supprimer un cycle',
            'description' => 'Supprimer un cycle sans niveaux associés.',
        ],
        'niveaux.view' => [
            'module' => 'structures',
            'label' => 'Consulter les niveaux',
            'description' => 'Voir les niveaux d\'étude (ex: 6e, 5e, 1ère, Tle).',
        ],
        'niveaux.create' => [
            'module' => 'structures',
            'label' => 'Créer un niveau',
            'description' => 'Créer un nouveau niveau d\'étude dans un cycle.',
        ],
        'niveaux.edit' => [
            'module' => 'structures',
            'label' => 'Modifier un niveau',
            'description' => 'Mettre à jour le libellé et l\'ordre de progression d\'un niveau.',
        ],
        'niveaux.delete' => [
            'module' => 'structures',
            'label' => 'Supprimer un niveau',
            'description' => 'Supprimer un niveau d\'étude s\'il ne comporte aucune classe.',
        ],
        'classes.view' => [
            'module' => 'structures',
            'label' => 'Consulter les classes',
            'description' => 'Voir la liste des divisions et salles de classe configurées.',
        ],
        'classes.create' => [
            'module' => 'structures',
            'label' => 'Créer une classe',
            'description' => 'Ouvrir une nouvelle classe (ex: 2nde C 1) avec sa capacité.',
        ],
        'classes.edit' => [
            'module' => 'structures',
            'label' => 'Modifier une classe',
            'description' => 'Modifier le nom, la série ou les informations d\'une classe.',
        ],
        'classes.delete' => [
            'module' => 'structures',
            'label' => 'Supprimer une classe',
            'description' => 'Supprimer une classe ne contenant aucune inscription d\'élève.',
        ],
        'classes.manage' => [
            'module' => 'structures',
            'label' => 'Gestion globale des classes (Hérité)',
            'description' => 'Droit étendu de gestion des classes.',
        ],
        'series.view' => [
            'module' => 'structures',
            'label' => 'Consulter les séries',
            'description' => 'Voir les séries et filières scolaires (A, C, D, TI, etc.).',
        ],
        'series.create' => [
            'module' => 'structures',
            'label' => 'Créer une série',
            'description' => 'Enregistrer une nouvelle série ou spécialité d\'enseignement.',
        ],
        'series.edit' => [
            'module' => 'structures',
            'label' => 'Modifier une série',
            'description' => 'Mettre à jour l\'intitulé et le code officiel d\'une série.',
        ],
        'series.delete' => [
            'module' => 'structures',
            'label' => 'Supprimer une série',
            'description' => 'Supprimer une série non utilisée.',
        ],
        'structures.manage' => [
            'module' => 'structures',
            'label' => 'Gestion arborescence globale (Hérité)',
            'description' => 'Droit étendu sur toute l\'arborescence pédagogique.',
        ],

        // ==========================================
        // 5. Périodes & Paramètres d'Évaluation
        // ==========================================
        'annees.view' => [
            'module' => 'periodes',
            'label' => 'Consulter les années scolaires',
            'description' => 'Voir les années scolaires configurées et leurs dates d\'exercice.',
        ],
        'annees.create' => [
            'module' => 'periodes',
            'label' => 'Créer une année scolaire',
            'description' => 'Initialiser une nouvelle année scolaire dans l\'établissement.',
        ],
        'annees.edit' => [
            'module' => 'periodes',
            'label' => 'Modifier une année scolaire',
            'description' => 'Ajuster les dates de rentrée et de clôture d\'une année scolaire.',
        ],
        'annees.set_current' => [
            'module' => 'periodes',
            'label' => 'Définir l\'année active',
            'description' => 'Basculer l\'année courante en cours de fonctionnement sur la plateforme.',
        ],
        'periodes.view' => [
            'module' => 'periodes',
            'label' => 'Consulter les trimestres / semestres',
            'description' => 'Visualiser les périodes de découpage de l\'année scolaire.',
        ],
        'periodes.create' => [
            'module' => 'periodes',
            'label' => 'Créer une période',
            'description' => 'Ajouter un trimestre ou semestre avec ses dates limites.',
        ],
        'periodes.edit' => [
            'module' => 'periodes',
            'label' => 'Modifier une période',
            'description' => 'Modifier les libellés et dates d\'un trimestre ou semestre.',
        ],
        'periodes.delete' => [
            'module' => 'periodes',
            'label' => 'Supprimer une période',
            'description' => 'Supprimer une période sans notes enregistrées.',
        ],
        'periodes.lock' => [
            'module' => 'periodes',
            'label' => 'Verrouiller / Clôturer une période',
            'description' => 'Fermer définitivement la saisie pour l\'ensemble d\'un trimestre.',
        ],
        'periodes.manage' => [
            'module' => 'periodes',
            'label' => 'Gérer les périodes (Hérité)',
            'description' => 'Droit étendu sur les années et périodes.',
        ],
        'evaluations.view' => [
            'module' => 'periodes',
            'label' => 'Consulter les évaluations / séquences',
            'description' => 'Voir le calendrier des séquences d\'évaluation programmées.',
        ],
        'evaluations.create' => [
            'module' => 'periodes',
            'label' => 'Créer une séquence d\'évaluation',
            'description' => 'Programmer une nouvelle séquence d\'évaluation (ex: Éval 1, Éval 2).',
        ],
        'evaluations.edit' => [
            'module' => 'periodes',
            'label' => 'Modifier une évaluation',
            'description' => 'Ajuster le nom, la date ou la pondération d\'une séquence.',
        ],
        'evaluations.delete' => [
            'module' => 'periodes',
            'label' => 'Supprimer une évaluation',
            'description' => 'Supprimer une évaluation sans notes associées.',
        ],
        'evaluations.lock' => [
            'module' => 'periodes',
            'label' => 'Verrouiller / Déverrouiller une évaluation',
            'description' => 'Bloquer ou autoriser la saisie des notes pour une séquence.',
        ],

        // ==========================================
        // 6. Matières & Coefficients
        // ==========================================
        'matieres.view' => [
            'module' => 'matieres',
            'label' => 'Consulter les matières',
            'description' => 'Voir la liste des disciplines enseignées dans l\'établissement.',
        ],
        'matieres.create' => [
            'module' => 'matieres',
            'label' => 'Créer une matière',
            'description' => 'Ajouter une nouvelle discipline au catalogue officiel.',
        ],
        'matieres.edit' => [
            'module' => 'matieres',
            'label' => 'Modifier une matière',
            'description' => 'Mettre à jour l\'intitulé, le code court ou le coefficient par défaut.',
        ],
        'matieres.delete' => [
            'module' => 'matieres',
            'label' => 'Supprimer une matière',
            'description' => 'Supprimer une matière du catalogue si elle n\'est pas attribuée.',
        ],
        'matieres.manage' => [
            'module' => 'matieres',
            'label' => 'Gérer les matières (Hérité)',
            'description' => 'Droit étendu sur le catalogue des matières.',
        ],
        'groupes_matieres.view' => [
            'module' => 'matieres',
            'label' => 'Consulter les blocs de matières',
            'description' => 'Voir les regroupements pédagogiques (ex: Groupe 1 - Sciences).',
        ],
        'groupes_matieres.create' => [
            'module' => 'matieres',
            'label' => 'Créer un bloc de matières',
            'description' => 'Créer un nouveau groupe de matières pour les bulletins.',
        ],
        'groupes_matieres.edit' => [
            'module' => 'matieres',
            'label' => 'Modifier un bloc de matières',
            'description' => 'Modifier l\'ordre d\'affichage et l\'intitulé d\'un groupe de matières.',
        ],
        'groupes_matieres.delete' => [
            'module' => 'matieres',
            'label' => 'Supprimer un bloc de matières',
            'description' => 'Supprimer un groupe de matières vide.',
        ],
        'coefficients.view' => [
            'module' => 'matieres',
            'label' => 'Consulter les coefficients',
            'description' => 'Visualiser la matrice des coefficients par niveau et série.',
        ],
        'coefficients.edit' => [
            'module' => 'matieres',
            'label' => 'Configurer la grille des coefficients',
            'description' => 'Paramétrer les coefficients officiels appliqués aux calculs de bulletins.',
        ],
        'coefficients.duplicate' => [
            'module' => 'matieres',
            'label' => 'Dupliquer la grille de coefficients',
            'description' => 'Cloner ou compléter la grille de coefficients d\'un niveau vers un autre niveau d\'enseignement.',
        ],

        // ==========================================
        // 7. Élèves, Inscriptions & Cartes
        // ==========================================
        'eleves.view' => [
            'module' => 'eleves',
            'label' => 'Consulter les fiches élèves',
            'description' => 'Visualiser le registre des élèves, dossiers individuels et historiques.',
        ],
        'eleves.create' => [
            'module' => 'eleves',
            'label' => 'Inscrire un nouvel élève',
            'description' => 'Créer un dossier élève et effectuer sa première inscription scolaire.',
        ],
        'eleves.edit' => [
            'module' => 'eleves',
            'label' => 'Modifier les informations d\'un élève',
            'description' => 'Mettre à jour l\'état-civil, la photo, le tuteur et les contacts d\'urgence.',
        ],
        'eleves.delete' => [
            'module' => 'eleves',
            'label' => 'Supprimer / Archiver un élève',
            'description' => 'Supprimer définitivement la fiche d\'un élève ou annuler son dossier.',
        ],
        'eleves.import' => [
            'module' => 'eleves',
            'label' => 'Importer des élèves en lot',
            'description' => 'Téléverser et intégrer massivement des listes d\'élèves par fichier CSV / Excel.',
        ],
        'inscriptions.reinscrire' => [
            'module' => 'eleves',
            'label' => 'Réinscrire un élève',
            'description' => 'Reconduire un élève existant dans sa classe supérieure pour la rentrée.',
        ],
        'inscriptions.transfer' => [
            'module' => 'eleves',
            'label' => 'Transférer un élève',
            'description' => 'Transférer un élève vers une autre classe de l\'établissement.',
        ],
        'inscriptions.delete' => [
            'module' => 'eleves',
            'label' => 'Annuler une inscription',
            'description' => 'Annuler l\'inscription d\'un élève pour l\'année scolaire en cours.',
        ],
        'inscriptions.manage' => [
            'module' => 'eleves',
            'label' => 'Gestion globale inscriptions (Hérité)',
            'description' => 'Droit étendu de gestion des inscriptions.',
        ],
        'cartes_scolaires.view' => [
            'module' => 'eleves',
            'label' => 'Consulter les cartes d\'identité scolaires',
            'description' => 'Voir le répertoire des badges élèves et prévisualiser les cartes.',
        ],
        'cartes_scolaires.generate' => [
            'module' => 'eleves',
            'label' => 'Imprimer les cartes & badges PDF',
            'description' => 'Générer les planches A4 massicotées et les badges PVC unitaires avec QR code.',
        ],

        // ==========================================
        // 8. Personnel & Départements RH
        // ==========================================
        'personnels.view' => [
            'module' => 'personnels',
            'label' => 'Consulter le personnel & RH',
            'description' => 'Visualiser le registre des employés, contrats et qualifications.',
        ],
        'personnels.create' => [
            'module' => 'personnels',
            'label' => 'Créer un membre du personnel',
            'description' => 'Enregistrer un nouvel enseignant ou agent administratif avec son compte d\'accès.',
        ],
        'personnels.edit' => [
            'module' => 'personnels',
            'label' => 'Modifier une fiche de personnel',
            'description' => 'Mettre à jour les données professionnelles, téléphone, diplômes et contrat.',
        ],
        'personnels.delete' => [
            'module' => 'personnels',
            'label' => 'Supprimer un membre du personnel',
            'description' => 'Supprimer la fiche d\'un employé du registre du personnel.',
        ],
        'personnels.toggle_status' => [
            'module' => 'personnels',
            'label' => 'Activer / Suspendre un employé',
            'description' => 'Changer le statut actif / suspendu / en congé d\'un membre du personnel.',
        ],
        'personnels.manage' => [
            'module' => 'personnels',
            'label' => 'Gérer le personnel (Hérité)',
            'description' => 'Droit étendu sur le personnel.',
        ],
        'departements.view' => [
            'module' => 'personnels',
            'label' => 'Consulter les départements pédagogiques',
            'description' => 'Voir la liste des départements d\'enseignement et leurs animateurs.',
        ],
        'departements.create' => [
            'module' => 'personnels',
            'label' => 'Créer un département pédagogique',
            'description' => 'Ajouter un nouveau département disciplinaire dans l\'établissement.',
        ],
        'departements.edit' => [
            'module' => 'personnels',
            'label' => 'Modifier un département pédagogique',
            'description' => 'Modifier l\'intitulé et désigner le chef / animateur pédagogique.',
        ],
        'departements.delete' => [
            'module' => 'personnels',
            'label' => 'Supprimer un département',
            'description' => 'Supprimer un département pédagogique vide.',
        ],
        'departements.manage' => [
            'module' => 'personnels',
            'label' => 'Gérer départements (Hérité)',
            'description' => 'Droit étendu sur les départements.',
        ],

        // ==========================================
        // 9. Attribution des Enseignements
        // ==========================================
        'attributions.view' => [
            'module' => 'attributions',
            'label' => 'Consulter les attributions de cours',
            'description' => 'Voir la ventilation des cours et charges horaires des enseignants.',
        ],
        'attributions.create' => [
            'module' => 'attributions',
            'label' => 'Affecter un enseignant à une classe',
            'description' => 'Assigner un cours à un enseignant avec sa charge horaire hebdomadaire.',
        ],
        'attributions.edit' => [
            'module' => 'attributions',
            'label' => 'Modifier une attribution de cours',
            'description' => 'Ajuster les horaires ou réassigner la matière à un autre enseignant.',
        ],
        'attributions.delete' => [
            'module' => 'attributions',
            'label' => 'Révoquer une attribution',
            'description' => 'Dissocier l\'enseignant d\'une classe ou matière.',
        ],
        'attributions.prof_principal' => [
            'module' => 'attributions',
            'label' => 'Désigner le professeur principal',
            'description' => 'Nommer l\'enseignant responsable principal d\'une division scolaire.',
        ],
        'attributions.manage' => [
            'module' => 'attributions',
            'label' => 'Gérer attributions (Hérité)',
            'description' => 'Droit étendu sur les attributions pédagogiques.',
        ],

        // ==========================================
        // 10. Notes & Évaluations
        // ==========================================
        'notes.view' => [
            'module' => 'evaluations',
            'label' => 'Consulter les grilles de notes',
            'description' => 'Visualiser les notes d\'évaluation, moyennes de classe et statistiques.',
        ],
        'notes.create' => [
            'module' => 'evaluations',
            'label' => 'Saisir et modifier les notes',
            'description' => 'Enregistrer les notes de devoirs, appréciations et présences à l\'épreuve.',
        ],
        'notes.lock' => [
            'module' => 'evaluations',
            'label' => 'Verrouiller la saisie des notes',
            'description' => 'Figer les notes d\'une classe pour empêcher toute modification ultérieure.',
        ],
        'notes.unlock' => [
            'module' => 'evaluations',
            'label' => 'Déverrouiller exceptionnellement les notes',
            'description' => 'Autoriser la réouverture exceptionnelle de la saisie après date limite.',
        ],
        'notes.manage' => [
            'module' => 'evaluations',
            'label' => 'Gérer les notes (Hérité)',
            'description' => 'Droit étendu sur les grilles de notation.',
        ],

        // ==========================================
        // 11. Bulletins Scolaires
        // ==========================================
        'bulletins.view' => [
            'module' => 'bulletins',
            'label' => 'Consulter les synthèses de bulletins',
            'description' => 'Voir le tableau d\'honneur, moyennes générales et rangs de la classe.',
        ],
        'bulletins.generate' => [
            'module' => 'bulletins',
            'label' => 'Générer & imprimer les bulletins PDF',
            'description' => 'Télécharger les bulletins individuels scellés ou le cahier global de classe.',
        ],
        'bulletins.lock' => [
            'module' => 'bulletins',
            'label' => 'Sceller définitivement les bulletins',
            'description' => 'Apposer le sceau numérique définitif interdisant tout recalcul.',
        ],

        // ==========================================
        // 12. Finances, Caisses & Versements
        // ==========================================
        'finances.view' => [
            'module' => 'finances',
            'label' => 'Consulter l\'état financier & ardoises',
            'description' => 'Visualiser les comptes d\'élèves, restes à payer, échéanciers et journaux.',
        ],
        'versements.create' => [
            'module' => 'finances',
            'label' => 'Encaisser un versement de scolarité',
            'description' => 'Enregistrer un paiement physique au guichet et imprimer le reçu officiel numéroté.',
        ],
        'versements.cancel' => [
            'module' => 'finances',
            'label' => 'Annuler un versement de caisse',
            'description' => 'Annuler un reçu erroné avec saisie obligatoire d\'un motif d\'annulation.',
        ],
        'caisses.view' => [
            'module' => 'finances',
            'label' => 'Consulter les journaux de caisse',
            'description' => 'Suivre les mouvements de caisse et les encaissements en temps réel.',
        ],
        'caisses.create' => [
            'module' => 'finances',
            'label' => 'Créer une caisse auxiliaire',
            'description' => 'Ouvrir un nouveau point d\'encaissement ou caisse secondaire.',
        ],
        'caisses.edit' => [
            'module' => 'finances',
            'label' => 'Modifier une caisse',
            'description' => 'Ajuster les paramètres et l\'affectation du caissier responsable.',
        ],
        'caisses.cloture' => [
            'module' => 'finances',
            'label' => 'Effectuer la clôture journalière',
            'description' => 'Clôturer la journée comptable et bloquer les nouvelles écritures sur cette date.',
        ],
        'bourses.manage' => [
            'module' => 'finances',
            'label' => 'Gérer les bourses et remises',
            'description' => 'Créer des modèles d\'exonération et attribuer des bourses aux élèves méritants.',
        ],
        'echeances.manage' => [
            'module' => 'finances',
            'label' => 'Paramétrer les tranches & échéanciers',
            'description' => 'Définir les dates limites et les montants des tranches de paiement de scolarité.',
        ],
        'penalites.manage' => [
            'module' => 'finances',
            'label' => 'Configurer & appliquer les pénalités',
            'description' => 'Appliquer des majorations pour retard de paiement après date limite.',
        ],
        'finances.manage' => [
            'module' => 'finances',
            'label' => 'Gérer finances globales (Hérité)',
            'description' => 'Droit étendu de gestion financière.',
        ],
        'finances.cloture' => [
            'module' => 'finances',
            'label' => 'Clôture de caisse (Hérité)',
            'description' => 'Droit étendu de clôture de caisse.',
        ],
        'finances.annuler' => [
            'module' => 'finances',
            'label' => 'Annulation financière (Hérité)',
            'description' => 'Droit étendu d\'annulation de paiement.',
        ],

        // ==========================================
        // 13. Dépenses & Décharges de Caisse
        // ==========================================
        'depenses.view' => [
            'module' => 'depenses',
            'label' => 'Consulter les dépenses & décaissements',
            'description' => 'Voir le livre des sorties de caisse et les montants décaissés.',
        ],
        'depenses.create' => [
            'module' => 'depenses',
            'label' => 'Enregistrer une dépense & décharge',
            'description' => 'Créer un décaissement de caisse et imprimer la fiche de décharge certifiée (A5).',
        ],
        'depenses.edit' => [
            'module' => 'depenses',
            'label' => 'Modifier une dépense',
            'description' => 'Corriger le libellé, la catégorie ou la référence d\'une sortie de caisse.',
        ],
        'depenses.delete' => [
            'module' => 'depenses',
            'label' => 'Supprimer / Annuler une dépense',
            'description' => 'Annuler un décaissement et réajuster le solde réel de caisse.',
        ],
        'depenses.bilan' => [
            'module' => 'depenses',
            'label' => 'Exporter le Bilan Entrées/Sorties',
            'description' => 'Générer le rapport synthétique officiel Recettes vs Dépenses (PDF A4).',
        ],
        'categories_depenses.manage' => [
            'module' => 'depenses',
            'label' => 'Gérer les catégories budgétaires',
            'description' => 'Créer et administrer les centres de coûts et postes de charges.',
        ],

        // ==========================================
        // 14. Discipline & Assiduité
        // ==========================================
        'discipline.view' => [
            'module' => 'discipline',
            'label' => 'Consulter le registre de discipline',
            'description' => 'Visualiser les bilans d\'assiduité, absences cumulées et sanctions prononcées.',
        ],
        'discipline.assiduite_create' => [
            'module' => 'discipline',
            'label' => 'Pointer des absences et retards',
            'description' => 'Enregistrer des heures d\'absence, retards et justificatifs médicaux.',
        ],
        'discipline.assiduite_delete' => [
            'module' => 'discipline',
            'label' => 'Supprimer un enregistrement d\'assiduité',
            'description' => 'Annuler une absence ou un retard saisi par erreur.',
        ],
        'discipline.sanction_create' => [
            'module' => 'discipline',
            'label' => 'Prononcer une sanction disciplinaire',
            'description' => 'Enregistrer un avertissement, blâme, exclusion temporaire ou corvée.',
        ],
        'discipline.sanction_delete' => [
            'module' => 'discipline',
            'label' => 'Annuler une sanction disciplinaire',
            'description' => 'Lever ou effacer une sanction disciplinaire d\'un élève.',
        ],
        'discipline.manage' => [
            'module' => 'discipline',
            'label' => 'Gérer la discipline (Hérité)',
            'description' => 'Droit étendu sur le registre disciplinaire.',
        ],
        'discipline.sanction' => [
            'module' => 'discipline',
            'label' => 'Prononcer sanctions (Hérité)',
            'description' => 'Droit étendu de sanction.',
        ],

        // ==========================================
        // 15. Conseil de Discipline & Consignes
        // ==========================================
        'conseil_discipline.view' => [
            'module' => 'conseil_discipline',
            'label' => 'Consulter les sessions de CD',
            'description' => 'Voir l\'historique des conseils de discipline et dossiers traduits.',
        ],
        'conseil_discipline.create' => [
            'module' => 'conseil_discipline',
            'label' => 'Convoquer une session de CD',
            'description' => 'Planifier une réunion du Conseil de Discipline et traduire des élèves.',
        ],
        'conseil_discipline.edit' => [
            'module' => 'conseil_discipline',
            'label' => 'Délibérer & enregistrer les résolutions',
            'description' => 'Consigner les délibérations, votes et sanctions officielles votées par le conseil.',
        ],
        'conseil_discipline.delete' => [
            'module' => 'conseil_discipline',
            'label' => 'Supprimer une session de CD',
            'description' => 'Supprimer une session de conseil de discipline archivée.',
        ],
        'conseil_discipline.print' => [
            'module' => 'conseil_discipline',
            'label' => 'Imprimer convocations & PV de CD',
            'description' => 'Éditer les convocations officielles avec accusé de réception et le PV officiel.',
        ],
        'consignes.view' => [
            'module' => 'conseil_discipline',
            'label' => 'Consulter les heures de consigne',
            'description' => 'Voir le planning des retenues et consignes du samedi matin.',
        ],
        'consignes.pointage' => [
            'module' => 'conseil_discipline',
            'label' => 'Pointer la présence en consigne',
            'description' => 'Valider l\'exécution ou le refus de présence d\'un élève retenu le samedi.',
        ],

        // ==========================================
        // 16. Dispenses & Dérogations Médicales
        // ==========================================
        'dispenses.view' => [
            'module' => 'dispenses',
            'label' => 'Consulter les dispenses médicales',
            'description' => 'Voir la liste des élèves dispensés d\'EPS ou autres disciplines.',
        ],
        'dispenses.create' => [
            'module' => 'dispenses',
            'label' => 'Enregistrer une dispense médicale',
            'description' => 'Créer une dispense avec certificat médical, médecin traitant et période.',
        ],
        'dispenses.edit' => [
            'module' => 'dispenses',
            'label' => 'Modifier une dispense médicale',
            'description' => 'Prolonger la durée ou corriger les mentions d\'un certificat médical.',
        ],
        'dispenses.delete' => [
            'module' => 'dispenses',
            'label' => 'Supprimer une dispense',
            'description' => 'Annuler une dispense médicale pour rétablir la notation de l\'élève.',
        ],
        'dispenses.homologuer' => [
            'module' => 'dispenses',
            'label' => 'Homologuer officiellement la dispense',
            'description' => 'Valider le visa du chef d\'établissement actant la neutralisation du coefficient.',
        ],
        'dispenses.print_pv' => [
            'module' => 'dispenses',
            'label' => 'Imprimer le PV officiel des dispenses',
            'description' => 'Télécharger le Procès-Verbal officiel MINESEC récapitulatif (PDF A4 Paysage).',
        ],

        // ==========================================
        // 17. Pédagogie par Compétences (APC)
        // ==========================================
        'competences_apc.view' => [
            'module' => 'competences_apc',
            'label' => 'Consulter les compétences APC',
            'description' => 'Visualiser les compétences périodiques déclarées par matière et trimestre.',
        ],
        'competences_apc.create' => [
            'module' => 'competences_apc',
            'label' => 'Déclarer des compétences périodiques',
            'description' => 'Saisir les compétences attendues pour le trimestre en cours.',
        ],
        'competences_apc.edit' => [
            'module' => 'competences_apc',
            'label' => 'Modifier une compétence périodique',
            'description' => 'Reformuler l\'intitulé ou corriger la matière rattachée.',
        ],
        'competences_apc.delete' => [
            'module' => 'competences_apc',
            'label' => 'Supprimer une compétence',
            'description' => 'Supprimer une compétence périodique.',
        ],
        'competences_apc.duplicate' => [
            'module' => 'competences_apc',
            'label' => 'Dupliquer les compétences inter-classes',
            'description' => 'Propager en un clic les compétences d\'une classe source vers d\'autres classes cibles.',
        ],
        'cotes_apc.manage' => [
            'module' => 'competences_apc',
            'label' => 'Configurer la matrice des cotes ministérielles',
            'description' => 'Paramétrer les intervalles des cotes (Expert A+, Acquis A, En cours ECA, Non acquis NA).',
        ],

        // ==========================================
        // 18. Cahier de Texte & Progression
        // ==========================================
        'progression.view' => [
            'module' => 'progression',
            'label' => 'Consulter le cahier de texte & progression',
            'description' => 'Voir les séances enseignées, chapitres abordés et taux de couverture du programme.',
        ],
        'progression.create' => [
            'module' => 'progression',
            'label' => 'Enregistrer une séance de cours',
            'description' => 'Consigner le titre de la leçon, objectifs, devoirs donnés et heures exécutées.',
        ],
        'progression.edit' => [
            'module' => 'progression',
            'label' => 'Modifier une séance de cours',
            'description' => 'Mettre à jour les observations ou détails d\'une leçon enregistrée.',
        ],
        'progression.delete' => [
            'module' => 'progression',
            'label' => 'Supprimer une séance de cours',
            'description' => 'Supprimer une séance consignée par erreur.',
        ],
        'progression.viser' => [
            'module' => 'progression',
            'label' => 'Apposer le visa administratif / inspection',
            'description' => 'Viser officiellement la progression pédagogique (Censeur / Directeur des Études).',
        ],
        'progression.print' => [
            'module' => 'progression',
            'label' => 'Imprimer la fiche officielle de progression',
            'description' => 'Générer l\'état officiel d\'avancement du programme pour l\'inspection (PDF).',
        ],

        // ==========================================
        // 19. Emplois du Temps & Salles
        // ==========================================
        'emplois_du_temps.view' => [
            'module' => 'emplois_du_temps',
            'label' => 'Consulter les emplois du temps',
            'description' => 'Visualiser les plannings par classe, enseignant ou salle.',
        ],
        'emplois_du_temps.create' => [
            'module' => 'emplois_du_temps',
            'label' => 'Planifier un cours hebdomadaire',
            'description' => 'Positionner un créneau d\'enseignement dans la grille horaire.',
        ],
        'emplois_du_temps.edit' => [
            'module' => 'emplois_du_temps',
            'label' => 'Modifier un créneau ou remplacement',
            'description' => 'Ajuster l\'horaire, remplacer un professeur ou déplacer une salle de cours.',
        ],
        'emplois_du_temps.delete' => [
            'module' => 'emplois_du_temps',
            'label' => 'Supprimer un créneau d\'enseignement',
            'description' => 'Annuler un créneau de cours récurrent.',
        ],
        'emplois_du_temps.manage' => [
            'module' => 'emplois_du_temps',
            'label' => 'Gérer emplois du temps (Hérité)',
            'description' => 'Droit étendu de gestion horaire.',
        ],
        'salles.view' => [
            'module' => 'emplois_du_temps',
            'label' => 'Consulter les salles de classe',
            'description' => 'Voir le parc des salles, capacités et équipements.',
        ],
        'salles.create' => [
            'module' => 'emplois_du_temps',
            'label' => 'Créer une salle de cours',
            'description' => 'Ajouter une nouvelle salle ou laboratoire dans le système.',
        ],
        'salles.edit' => [
            'module' => 'emplois_du_temps',
            'label' => 'Modifier une salle de cours',
            'description' => 'Mettre à jour la capacité d\'accueil ou le nom d\'une salle.',
        ],
        'salles.delete' => [
            'module' => 'emplois_du_temps',
            'label' => 'Supprimer une salle',
            'description' => 'Supprimer une salle non affectée à un emploi du temps.',
        ],

        // ==========================================
        // 20. Communication & SMS
        // ==========================================
        'communication.view' => [
            'module' => 'communication',
            'label' => 'Consulter l\'historique SMS & solde',
            'description' => 'Voir les journaux de diffusion, accusés de réception et solde d\'unités SMS.',
        ],
        'communication.send' => [
            'module' => 'communication',
            'label' => 'Expédier des SMS aux parents',
            'description' => 'Rédiger et envoyer des SMS individuels ou campagnes de diffusion par classe.',
        ],
        'communication.settings' => [
            'module' => 'communication',
            'label' => 'Configurer la passerelle SMS',
            'description' => 'Paramétrer les clés API, nom d\'expéditeur officiel et recharger les unités.',
        ],
        'communication.manage' => [
            'module' => 'communication',
            'label' => 'Gérer communication (Hérité)',
            'description' => 'Droit étendu sur la passerelle de communication.',
        ],

        // ==========================================
        // 21. Conseils de Classe & Statistiques
        // ==========================================
        'statistiques.view' => [
            'module' => 'deliberations',
            'label' => 'Consulter les statistiques d\'établissement',
            'description' => 'Visualiser les taux de réussite, pourcentages d\'admis et classements généraux.',
        ],
        'deliberations.view' => [
            'module' => 'deliberations',
            'label' => 'Consulter les délibérations de conseil',
            'description' => 'Voir la synthèse des bilans périodiques avant décision finale.',
        ],
        'deliberations.saisir' => [
            'module' => 'deliberations',
            'label' => 'Saisir les avis du conseil de classe',
            'description' => 'Attribuer les mentions d\'honneur, encouragements, avertissements et décisions d\'orientation.',
        ],
        'deliberations.valider' => [
            'module' => 'deliberations',
            'label' => 'Valider & clôturer le conseil de classe',
            'description' => 'Entériner définitivement les décisions du conseil de classe et figer le procès-verbal.',
        ],
        'deliberations.manage' => [
            'module' => 'deliberations',
            'label' => 'Gérer délibérations (Hérité)',
            'description' => 'Droit étendu sur les délibérations scolaires.',
        ],

        // ==========================================
        // 22. Archivage, Clôture & Reconduction
        // ==========================================
        'archivage.view' => [
            'module' => 'archivage',
            'label' => 'Consulter les archives historiques',
            'description' => 'Voir les états annuels archivés et les registres légaux numérotés.',
        ],
        'archivage.archiver' => [
            'module' => 'archivage',
            'label' => 'Déclencher la clôture & archivage annuel',
            'description' => 'Générer l\'archive scellée de l\'année écoulée et figer toutes ses données.',
        ],
        'archivage.dupliquer' => [
            'module' => 'archivage',
            'label' => 'Reconduire les structures scolaires',
            'description' => 'Dupliquer automatiquement les classes, matières et coefficients pour la nouvelle rentrée.',
        ],
        'archivage.numerotation' => [
            'module' => 'archivage',
            'label' => 'Paramétrer les règles de numérotation',
            'description' => 'Définir les masques et compteurs de matricules élèves, reçus et pièces comptables.',
        ],
    ];

    /**
     * Affiche la liste des rôles avec leurs statistiques et l'ensemble des permissions structurées par module.
     *
     * @param Request $request La requête HTTP entrante.
     * @return Response La vue Inertia avec la liste des rôles, la structure des modules et les droits d'action.
     */
    public function index(Request $request): Response
    {
        // Contrôle d'habilitation : consultation des rôles & permissions
        abort_unless($request->user()->can('roles.view'), 403, 'Accès non autorisé : vous ne disposez pas de la permission requise pour consulter les rôles.');

        // 1. Récupération des rôles existants avec le comptage des utilisateurs associés
        $roles = Role::with('permissions')
            ->withCount('users')
            ->orderBy('id')
            ->get()
            ->map(function (Role $role) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'displayName' => $this->roleLabels[$role->name] ?? Str::headline($role->name),
                    'isSystem' => in_array($role->name, ['super-admin', 'admin']),
                    'isSuperAdmin' => $role->name === 'super-admin',
                    'usersCount' => $role->users_count,
                    'permissionsCount' => $role->permissions->count(),
                    'permissions' => $role->permissions->pluck('name')->toArray(),
                    'createdAt' => $role->created_at?->format('d-m-Y'),
                ];
            });

        // 2. Récupération de l'ensemble des permissions et organisation par module
        $allPermissions = Permission::orderBy('name')->get();
        $groupedModules = [];

        foreach ($this->modules as $moduleId => $moduleData) {
            $modulePermissions = [];

            foreach ($allPermissions as $permission) {
                $definition = $this->permissionDefinitions[$permission->name] ?? null;

                // Cas 1 : Définition explicite rattachée à ce module
                if ($definition && $definition['module'] === $moduleId) {
                    $modulePermissions[] = [
                        'id' => $permission->id,
                        'name' => $permission->name,
                        'label' => $definition['label'],
                        'description' => $definition['description'],
                    ];
                }
                // Cas 2 : Détection dynamique si la permission commence par l'identifiant du module et n'est pas encore définie
                elseif (! $definition) {
                    $prefix = explode('.', $permission->name)[0] ?? '';
                    if ($prefix === $moduleId) {
                        $modulePermissions[] = [
                            'id' => $permission->id,
                            'name' => $permission->name,
                            'label' => ucwords(str_replace(['.', '_'], ' ', $permission->name)),
                            'description' => "Permission d'action « {$permission->name} ».",
                        ];
                    }
                }
            }

            // N'inclure que les modules qui ont au moins une permission
            if (! empty($modulePermissions)) {
                $groupedModules[] = [
                    ...$moduleData,
                    'permissions' => $modulePermissions,
                ];
            }
        }

        return Inertia::render('administration/roles/index', [
            'roles' => $roles,
            'modules' => $groupedModules,
            'totalPermissions' => $allPermissions->count(),
            'can' => [
                'create' => $request->user()->can('roles.create'),
                'edit' => $request->user()->can('roles.edit'),
                'delete' => $request->user()->can('roles.delete'),
            ],
        ]);
    }

    /**
     * Enregistre un nouveau rôle personnalisé et lui associe ses permissions.
     *
     * @param Request $request La requête HTTP contenant le nom et les permissions sélectionnées.
     * @return RedirectResponse Redirection vers la liste des rôles avec un message flash.
     */
    public function store(Request $request): RedirectResponse
    {
        // Contrôle d'habilitation : création de rôle
        abort_unless($request->user()->can('roles.create'), 403, 'Accès non autorisé : vous ne disposez pas de la permission requise pour créer un rôle.');

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'min:2',
                'max:50',
                'regex:/^[a-zA-Z0-9\-_ ]+$/',
                'unique:roles,name',
            ],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ], [
            'name.required' => 'Le nom du rôle est obligatoire.',
            'name.unique' => 'Ce nom de rôle existe déjà.',
            'name.regex' => 'Le nom contient des caractères non autorisés (lettres, chiffres, tirets et espaces uniquement).',
        ]);

        // Génération d'un nom de rôle normalisé (slug) ou conservation d'un format propre
        $roleSlug = Str::slug($validated['name']);

        // Vérification de la disponibilité du slug si différent du nom saisi
        if (Role::where('name', $roleSlug)->exists()) {
            return back()->withErrors(['name' => 'Un rôle avec cet identifiant existe déjà.'])->withInput();
        }

        // Création du rôle Spatie
        $role = Role::create([
            'name' => $roleSlug,
            'guard_name' => 'web',
        ]);

        // Synchronisation des permissions accordées
        if (! empty($validated['permissions'])) {
            $role->syncPermissions($validated['permissions']);
        }

        return redirect()->route('administration.roles.index')
            ->with('success', "Le rôle « {$validated['name']} » a été créé avec succès.");
    }

    /**
     * Met à jour le nom et les permissions d'un rôle existant.
     * Protège le rôle Super Administrateur contre les modifications critiques.
     *
     * @param Request $request La requête HTTP de mise à jour.
     * @param Role $role Le rôle à mettre à jour.
     * @return RedirectResponse Redirection vers la liste des rôles avec message de confirmation.
     */
    public function update(Request $request, Role $role): RedirectResponse
    {
        // Contrôle d'habilitation : modification de rôle
        abort_unless($request->user()->can('roles.edit'), 403, 'Accès non autorisé : vous ne disposez pas de la permission requise pour modifier les permissions de ce rôle.');

        $isSuperAdmin = $role->name === 'super-admin';

        $validated = $request->validate([
            'name' => [
                'sometimes',
                'required',
                'string',
                'min:2',
                'max:50',
                'regex:/^[a-zA-Z0-9\-_ ]+$/',
                Rule::unique('roles', 'name')->ignore($role->id),
            ],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ], [
            'name.required' => 'Le nom du rôle est obligatoire.',
            'name.unique' => 'Ce nom de rôle est déjà utilisé.',
            'name.regex' => 'Le nom contient des caractères non autorisés.',
        ]);

        // Mise à jour du nom du rôle si fourni
        if (isset($validated['name'])) {
            // Protection : Le rôle super-admin ne peut jamais être renommé
            if ($isSuperAdmin && Str::slug($validated['name']) !== 'super-admin') {
                return back()->withErrors(['name' => 'Le rôle Super Administrateur est protégé et ne peut pas être renommé.']);
            }

            if (! $isSuperAdmin) {
                $role->name = Str::slug($validated['name']);
                $role->save();
            }
        }

        // Synchronisation des permissions (le super-admin conserve toujours toutes les permissions)
        if ($isSuperAdmin) {
            $role->syncPermissions(Permission::all());
        } else {
            $role->syncPermissions($validated['permissions'] ?? []);
        }

        $displayName = $this->roleLabels[$role->name] ?? $role->name;

        return redirect()->route('administration.roles.index')
            ->with('success', "Les permissions du rôle « {$displayName} » ont été enregistrées avec succès.");
    }

    /**
     * Supprime un rôle personnalisé.
     * Empêche la suppression des rôles système et des rôles actuellement assignés à des utilisateurs.
     *
     * @param Request $request La requête HTTP entrante.
     * @param Role $role Le rôle à supprimer.
     * @return RedirectResponse Redirection avec message de succès ou d'erreur.
     */
    public function destroy(Request $request, Role $role): RedirectResponse
    {
        // Contrôle d'habilitation : suppression de rôle
        abort_unless($request->user()->can('roles.delete'), 403, 'Accès non autorisé : vous ne disposez pas de la permission requise pour supprimer un rôle.');

        // 1. Interdiction stricte de supprimer les rôles fondamentaux du système
        if (in_array($role->name, ['super-admin', 'admin'])) {
            return back()->with('error', "Le rôle système « {$role->name} » est protégé et ne peut pas être supprimé.");
        }

        // 2. Vérification qu'aucun utilisateur n'est actuellement rattaché à ce rôle
        $assignedUsersCount = $role->users()->count();
        if ($assignedUsersCount > 0) {
            return back()->with('error', "Impossible de supprimer ce rôle car il est actuellement attribué à {$assignedUsersCount} utilisateur(s).");
        }

        $roleName = $role->name;
        $role->delete();

        return redirect()->route('administration.roles.index')
            ->with('success', "Le rôle « {$roleName} » a été supprimé avec succès.");
    }
}
