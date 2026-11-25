<?php

namespace App\Http\Controllers\Administration;

use App\Concerns\ScopesEtablissement;
use App\Http\Controllers\Controller;
use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\CompteFinancierEleve;
use App\Models\Eleve;
use App\Models\Inscription;
use App\Models\VentilationVersement;
use App\Models\VersementCaisse;
use App\Services\FinanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Contrôleur gérant le Module 5 : Élèves & Inscriptions de SYGES.
 * Implémente la Base Élève Globale pérenne, la gestion des inscriptions annuelles,
 * les réinscriptions, les transferts intra/inter établissements (Gesco V1.1)
 * et l'importation de fichiers CSV / Excel (format SAGEES Schooling).
 *
 * Utilise le trait ScopesEtablissement pour assurer le cloisonnement strict des données
 * d'élèves selon le campus auquel est rattaché l'utilisateur connecté.
 */
class EleveController extends Controller implements HasMiddleware
{
    use ScopesEtablissement;

    /**
     * Définition de la matrice des middlewares d'autorisation pour le module Élèves & Inscriptions.
     *
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:eleves.view', only: ['index', 'searchAnciens', 'exportCsv']),
            new Middleware('can:eleves.create|eleves.manage', only: ['create', 'store']),
            new Middleware('can:eleves.import|eleves.create|eleves.manage', only: ['importCsv']),
            new Middleware('can:eleves.edit|eleves.manage', only: ['update']),
            new Middleware('can:eleves.delete', only: ['destroy']),
            new Middleware('can:inscriptions.reinscrire|inscriptions.manage', only: ['reinscrire', 'reinscrireLot', 'reinscriptionPage', 'getClasseElevesForReinscription']),
            new Middleware('can:inscriptions.manage', only: ['updateStatut']),
            new Middleware('can:inscriptions.transfer|inscriptions.manage', only: ['transferer']),
        ];
    }

    /**
     * Affiche le formulaire de création d'un nouvel élève avec inscription initiale.
     *
     * @param  Request  $request  Requête HTTP entrante
     * @return Response Réponse Inertia pour la page de création
     */
    public function create(Request $request): Response
    {
        $currentAnnee = AnneeScolaire::current();
        $selectedAnneeId = $request->integer('annee_scolaire_id') ?: $currentAnnee?->id;

        // Résolution hermétique du contexte multi-établissement selon les droits de l'utilisateur
        $context = $this->resolveEtablissementContext($request, [
            'sections.cycles.niveaux.classes' => function ($q) {
                $q->where('is_active', true)->orderBy('nom');
            },
        ]);

        return Inertia::render('administration/eleves/create', [
            'etablissements' => $context['etablissements'],
            'anneesScolaires' => AnneeScolaire::orderByDesc('date_debut')->get(),
            'selectedAnneeId' => $selectedAnneeId,
        ]);
    }

    /**
     * Affiche la page dédiée à la réinscription des anciens élèves (collective ou individuelle).
     *
     * Offre un espace de travail ergonomique et étendu permettant :
     * - La promotion collective par classe (avec choix d'année source et destination, avis du conseil, arriérés, sélection personnalisée d'élèves).
     * - La réinscription individuelle rapide avec recherche dynamique et conservation de l'historique financier.
     *
     * @param  Request  $request  Requête HTTP entrante avec filtres éventuels (annee_destination_id, annee_source_id)
     * @return Response Réponse Inertia vers la vue dédiée
     */
    public function reinscriptionPage(Request $request): Response
    {
        $currentAnnee = AnneeScolaire::current();
        $selectedAnneeId = $request->integer('annee_destination_id') ?: ($request->integer('annee_scolaire_id') ?: $currentAnnee?->id);

        $anneesScolaires = AnneeScolaire::orderByDesc('date_debut')->get();

        // Déterminer l'année source par défaut (l'année précédant chronologiquement l'année sélectionnée)
        $sourceAnneeId = $request->integer('annee_source_id');
        if (! $sourceAnneeId && $selectedAnneeId) {
            $selectedAnnee = $anneesScolaires->firstWhere('id', $selectedAnneeId);
            if ($selectedAnnee) {
                $prevAnnee = $anneesScolaires->first(function ($a) use ($selectedAnnee) {
                    return $a->date_debut < $selectedAnnee->date_debut;
                });
                $sourceAnneeId = $prevAnnee?->id ?? $selectedAnneeId;
            }
        }

        // Contexte multi-établissement avec arborescence complète pour les classes cibles et sources
        $context = $this->resolveEtablissementContext($request, [
            'sections.cycles.niveaux.classes' => function ($q) {
                $q->where('is_active', true)->orderBy('nom');
            },
        ]);

        $user = $request->user();
        $isSuperAdmin = $context['isSuperAdmin'];
        $can = [
            'inscriptions_reinscrire' => $isSuperAdmin || $user?->can('inscriptions.reinscrire') || $user?->can('inscriptions.manage'),
            'eleves_view' => $isSuperAdmin || $user?->can('eleves.view'),
            'eleves_manage' => $isSuperAdmin || $user?->can('eleves.manage'),
        ];

        return Inertia::render('administration/eleves/reinscription', [
            'etablissements' => $context['etablissements'],
            'anneesScolaires' => $anneesScolaires,
            'selectedAnneeId' => (int) $selectedAnneeId,
            'sourceAnneeId' => (int) $sourceAnneeId,
            'can' => $can,
        ]);
    }

    /**
     * Affiche la liste des élèves avec filtrage dynamique et KPIs.
     */
    public function index(Request $request): Response
    {
        $currentAnnee = AnneeScolaire::current();
        $selectedAnneeId = $request->integer('annee_scolaire_id') ?: $currentAnnee?->id;

        // Résolution hermétique du contexte multi-établissement selon les droits de l'utilisateur
        $context = $this->resolveEtablissementContext($request, [
            'sections.cycles.niveaux.classes' => function ($q) {
                $q->where('is_active', true)->orderBy('nom');
            },
        ]);
        $etablissements = $context['etablissements'];
        $scopedEtablissementId = $context['etablissementId'];
        $isSuperAdmin = $context['isSuperAdmin'];
        $canAccessAll = (bool) ($context['canAccessAll'] ?? $isSuperAdmin);
        $scope = $this->resolveElevesScope($request, $context);
        $effectiveEtablissementId = $scope['effectiveEtablissementId'];

        // Récupération de toutes les années scolaires
        $anneesScolaires = AnneeScolaire::orderByDesc('date_debut')->get();

        // Projection SQL directe ultra-légère : évite l'hydratation et la sérialisation récursive
        // de ~3 500 modèles Eloquent imbriqués sur les bases de +500 élèves tout en préservant
        // 100 % du contrat JSON attendu par TanStack Table, les tiroirs et modales d'édition.
        $inscriptionsQuery = DB::table('inscriptions')
            ->join('eleves', 'eleves.id', '=', 'inscriptions.eleve_id')
            ->leftJoin('etablissements as insc_etab', 'insc_etab.id', '=', 'inscriptions.etablissement_id')
            ->leftJoin('classes', 'classes.id', '=', 'inscriptions.classe_id')
            ->leftJoin('niveaux', 'niveaux.id', '=', 'classes.niveau_id')
            ->leftJoin('cycles', 'cycles.id', '=', 'niveaux.cycle_id')
            ->leftJoin('sections', 'sections.id', '=', 'cycles.section_id')
            ->leftJoin('etablissements as sec_etab', 'sec_etab.id', '=', 'sections.etablissement_id')
            ->select([
                'inscriptions.id',
                'inscriptions.eleve_id',
                'inscriptions.classe_id',
                'inscriptions.etablissement_id',
                'inscriptions.annee_scolaire_id',
                'inscriptions.type_inscription',
                'inscriptions.is_redoublant',
                'inscriptions.statut',
                'inscriptions.date_inscription',
                'inscriptions.motif_sortie',
                'inscriptions.etablissement_provenance',
                'inscriptions.observations',
                'eleves.matricule',
                'eleves.matricule_national',
                'eleves.nom as eleve_nom',
                'eleves.prenom as eleve_prenom',
                'eleves.sexe as eleve_sexe',
                'eleves.date_naissance as eleve_date_naissance',
                'eleves.lieu_naissance as eleve_lieu_naissance',
                'eleves.nationalite as eleve_nationalite',
                'eleves.photo_url as eleve_photo_url',
                'eleves.nom_pere as eleve_nom_pere',
                'eleves.nom_mere as eleve_nom_mere',
                'eleves.nom_tuteur as eleve_nom_tuteur',
                'eleves.telephone_parent as eleve_telephone_parent',
                'eleves.email_parent as eleve_email_parent',
                'eleves.adresse as eleve_adresse',
                'eleves.is_active as eleve_is_active',
                'insc_etab.institution_id as insc_etab_institution_id',
                'insc_etab.code as insc_etab_code',
                'insc_etab.nom as insc_etab_nom',
                'classes.etablissement_id as classe_etablissement_id',
                'classes.niveau_id as classe_niveau_id',
                'classes.code as classe_code',
                'classes.nom as classe_nom',
                'classes.serie as classe_serie',
                'classes.capacite_max as classe_capacite_max',
                'classes.is_active as classe_is_active',
                'niveaux.cycle_id as niveau_cycle_id',
                'niveaux.code as niveau_code',
                'niveaux.nom as niveau_nom',
                'cycles.section_id as cycle_section_id',
                'cycles.code as cycle_code',
                'cycles.nom as cycle_nom',
                'sections.etablissement_id as section_etablissement_id',
                'sections.code as section_code',
                'sections.nom as section_nom',
                'sec_etab.id as sec_etab_id',
                'sec_etab.institution_id as sec_etab_institution_id',
                'sec_etab.code as sec_etab_code',
                'sec_etab.nom as sec_etab_nom',
            ]);

        if ($selectedAnneeId) {
            $inscriptionsQuery->where('inscriptions.annee_scolaire_id', $selectedAnneeId);
        }

        // Cloisonnement d'établissement synchronisé avec le sélecteur global d'en-tête :
        // - Utilisateur local : forcé sur son campus assigné.
        // - Profil transversal : filtré sur le campus actif en session (ou URL), ou tous les campus en mode consolidé.
        if (! $canAccessAll) {
            if ($scopedEtablissementId) {
                $inscriptionsQuery->where('inscriptions.etablissement_id', $scopedEtablissementId);
            }
        } elseif ($effectiveEtablissementId) {
            $inscriptionsQuery->where('inscriptions.etablissement_id', $effectiveEtablissementId);
        }

        // Filtre par Cycle
        if ($cycleId = $request->integer('cycle_id')) {
            $inscriptionsQuery->where('niveaux.cycle_id', $cycleId);
        }

        // Filtre par Niveau
        if ($niveauId = $request->integer('niveau_id')) {
            $inscriptionsQuery->where('classes.niveau_id', $niveauId);
        }

        // Filtre par Classe
        if ($classeId = $request->integer('classe_id')) {
            $inscriptionsQuery->where('inscriptions.classe_id', $classeId);
        }

        // Filtre par Statut d'inscription (validee, en_attente, suspendue, exclu, transfere)
        if ($statut = $request->input('statut')) {
            $inscriptionsQuery->where('inscriptions.statut', $statut);
        }

        // Filtre par Régime scolaire (nouveau vs redoublant)
        if ($request->has('is_redoublant') && $request->input('is_redoublant') !== null && $request->input('is_redoublant') !== '') {
            $inscriptionsQuery->where('inscriptions.is_redoublant', $request->boolean('is_redoublant'));
        }

        // Filtre par Sexe de l'élève via la jointure directe (supporte 'M', 'F' et 'ND' pour Non défini / À compléter)
        if ($sexe = $request->input('sexe')) {
            if ($sexe === 'ND') {
                $inscriptionsQuery->where(function ($q) {
                    $q->whereNull('eleves.sexe')->orWhere('eleves.sexe', '');
                });
            } else {
                $inscriptionsQuery->where('eleves.sexe', $sexe);
            }
        }

        // Recherche textuelle par Nom, Prénom, Matricule, Téléphone ou ancien matricule fusionné
        if ($search = $request->input('search')) {
            $term = '%'.trim((string) $search).'%';
            $inscriptionsQuery->where(function ($q) use ($term) {
                $q->where('eleves.nom', 'like', $term)
                    ->orWhere('eleves.prenom', 'like', $term)
                    ->orWhere('eleves.matricule', 'like', $term)
                    ->orWhere('eleves.matricule_national', 'like', $term)
                    ->orWhere('eleves.telephone_parent', 'like', $term)
                    ->orWhereExists(function ($fq) use ($term) {
                        $fq->selectRaw('1')
                            ->from('eleves_fusions_audit')
                            ->whereColumn('eleves_fusions_audit.master_eleve_id', 'eleves.id')
                            ->where(function ($sub) use ($term) {
                                $sub->where('eleves_fusions_audit.absorbed_matricule', 'like', $term)
                                    ->orWhere('eleves_fusions_audit.absorbed_nom_complet', 'like', $term);
                            });
                    });
            });
        }

        // Liste finale ordonnée par classe puis par ordre alphabétique direct sur la table jointe `eleves`
        $rawInscriptions = $inscriptionsQuery
            ->orderBy('inscriptions.classe_id')
            ->orderBy('eleves.nom')
            ->orderBy('eleves.prenom')
            ->get();

        // Formatage en tableaux PHP plats (supprime la surcouche Eloquent lors de la sérialisation JSON Inertia)
        $inscriptions = [];
        foreach ($rawInscriptions as $row) {
            $etabDirect = $row->etablissement_id ? [
                'id' => (int) $row->etablissement_id,
                'institution_id' => $row->insc_etab_institution_id ? (int) $row->insc_etab_institution_id : null,
                'code' => $row->insc_etab_code,
                'nom' => $row->insc_etab_nom,
                'sigle' => $row->insc_etab_code,
            ] : null;

            $secEtab = $row->sec_etab_id ? [
                'id' => (int) $row->sec_etab_id,
                'institution_id' => $row->sec_etab_institution_id ? (int) $row->sec_etab_institution_id : null,
                'code' => $row->sec_etab_code,
                'nom' => $row->sec_etab_nom,
                'sigle' => $row->sec_etab_code,
            ] : null;

            $inscriptions[] = [
                'id' => (int) $row->id,
                'eleve_id' => (int) $row->eleve_id,
                'classe_id' => (int) $row->classe_id,
                'etablissement_id' => $row->etablissement_id ? (int) $row->etablissement_id : null,
                'annee_scolaire_id' => (int) $row->annee_scolaire_id,
                'type_inscription' => $row->type_inscription,
                'is_redoublant' => (bool) $row->is_redoublant,
                'statut' => (string) $row->statut,
                'date_inscription' => $row->date_inscription,
                'motif_sortie' => $row->motif_sortie,
                'etablissement_provenance' => $row->etablissement_provenance,
                'observations' => $row->observations,
                'eleve' => [
                    'id' => (int) $row->eleve_id,
                    'matricule' => (string) $row->matricule,
                    'matricule_national' => $row->matricule_national,
                    'nom' => (string) $row->eleve_nom,
                    'prenom' => $row->eleve_prenom,
                    'nom_complet' => trim(((string) $row->eleve_nom).' '.((string) ($row->eleve_prenom ?? ''))),
                    'sexe' => $row->eleve_sexe,
                    'date_naissance' => $row->eleve_date_naissance,
                    'lieu_naissance' => $row->eleve_lieu_naissance,
                    'nationalite' => $row->eleve_nationalite,
                    'photo_url' => $row->eleve_photo_url,
                    'nom_pere' => $row->eleve_nom_pere,
                    'nom_mere' => $row->eleve_nom_mere,
                    'nom_tuteur' => $row->eleve_nom_tuteur,
                    'telephone_parent' => $row->eleve_telephone_parent,
                    'email_parent' => $row->eleve_email_parent,
                    'adresse' => $row->eleve_adresse,
                    'is_active' => (bool) $row->eleve_is_active,
                ],
                'etablissement' => $etabDirect,
                'classe' => $row->classe_id ? [
                    'id' => (int) $row->classe_id,
                    'etablissement_id' => $row->classe_etablissement_id ? (int) $row->classe_etablissement_id : null,
                    'niveau_id' => $row->classe_niveau_id ? (int) $row->classe_niveau_id : null,
                    'code' => $row->classe_code,
                    'nom' => $row->classe_nom,
                    'serie' => $row->classe_serie,
                    'capacite_max' => $row->classe_capacite_max !== null ? (int) $row->classe_capacite_max : null,
                    'is_active' => (bool) $row->classe_is_active,
                    'niveau' => $row->classe_niveau_id ? [
                        'id' => (int) $row->classe_niveau_id,
                        'cycle_id' => $row->niveau_cycle_id ? (int) $row->niveau_cycle_id : null,
                        'code' => $row->niveau_code,
                        'nom' => $row->niveau_nom,
                        'cycle' => $row->niveau_cycle_id ? [
                            'id' => (int) $row->niveau_cycle_id,
                            'section_id' => $row->cycle_section_id ? (int) $row->cycle_section_id : null,
                            'code' => $row->cycle_code,
                            'nom' => $row->cycle_nom,
                            'section' => $row->cycle_section_id ? [
                                'id' => (int) $row->cycle_section_id,
                                'etablissement_id' => $row->section_etablissement_id ? (int) $row->section_etablissement_id : null,
                                'code' => $row->section_code,
                                'nom' => $row->section_nom,
                                'etablissement' => $secEtab,
                            ] : null,
                        ] : null,
                    ] : null,
                ] : null,
            ];
        }

        // Calcul agrégé en une seule requête SQL des 6 indicateurs KPI d'inscriptions pour l'année sélectionnée
        $statsBase = Inscription::query()
            ->join('eleves', 'eleves.id', '=', 'inscriptions.eleve_id')
            ->where('inscriptions.annee_scolaire_id', $selectedAnneeId);

        if (! $canAccessAll) {
            if ($scopedEtablissementId) {
                $statsBase->where('inscriptions.etablissement_id', $scopedEtablissementId);
            }
        } elseif ($effectiveEtablissementId) {
            $statsBase->where('inscriptions.etablissement_id', $effectiveEtablissementId);
        }

        $statsAggregate = $statsBase->selectRaw("
            COALESCE(SUM(CASE WHEN inscriptions.statut = 'validee' THEN 1 ELSE 0 END), 0) as total_inscrits,
            COALESCE(SUM(CASE WHEN inscriptions.statut = 'validee' AND eleves.sexe = 'F' THEN 1 ELSE 0 END), 0) as total_filles,
            COALESCE(SUM(CASE WHEN inscriptions.statut = 'validee' AND eleves.sexe = 'M' THEN 1 ELSE 0 END), 0) as total_garcons,
            COALESCE(SUM(CASE WHEN inscriptions.statut = 'validee' AND (eleves.sexe IS NULL OR eleves.sexe = '') THEN 1 ELSE 0 END), 0) as total_non_definis,
            COALESCE(SUM(CASE WHEN inscriptions.statut = 'validee' AND inscriptions.is_redoublant = 1 THEN 1 ELSE 0 END), 0) as total_redoublants,
            COALESCE(SUM(CASE WHEN inscriptions.statut = 'en_attente' THEN 1 ELSE 0 END), 0) as total_en_attente
        ")->first();

        $totalInscrits = (int) ($statsAggregate->total_inscrits ?? 0);
        $totalFilles = (int) ($statsAggregate->total_filles ?? 0);
        $totalGarcons = (int) ($statsAggregate->total_garcons ?? 0);
        $totalNonDefinis = (int) ($statsAggregate->total_non_definis ?? 0);
        $totalRedoublants = (int) ($statsAggregate->total_redoublants ?? 0);
        $totalEnAttente = (int) ($statsAggregate->total_en_attente ?? 0);

        // Anciens élèves non inscrits pour l'année scolaire en cours (pour réinscription directe)
        $nonInscritsCount = Eleve::whereDoesntHave('inscriptions', function ($q) use ($selectedAnneeId) {
            $q->where('annee_scolaire_id', $selectedAnneeId);
        })->count();

        // Droits et permissions granulaires et rétrocompatibles de l'utilisateur connecté
        $user = $request->user();
        $can = [
            'eleves_create' => $isSuperAdmin || $user?->can('eleves.create') || $user?->can('eleves.manage'),
            'eleves_edit' => $isSuperAdmin || $user?->can('eleves.edit') || $user?->can('eleves.manage'),
            'eleves_delete' => $isSuperAdmin || $user?->can('eleves.delete'),
            'eleves_import' => $isSuperAdmin || $user?->can('eleves.import') || $user?->can('eleves.create') || $user?->can('eleves.manage'),
            'eleves_merge' => $isSuperAdmin || $user?->can('eleves.merge'),
            'inscriptions_manage' => $isSuperAdmin || $user?->can('inscriptions.manage'),
            'inscriptions_reinscrire' => $isSuperAdmin || $user?->can('inscriptions.reinscrire') || $user?->can('inscriptions.manage'),
            'inscriptions_transfer' => $isSuperAdmin || $user?->can('inscriptions.transfer') || $user?->can('inscriptions.manage'),
        ];

        return Inertia::render('administration/eleves/index', [
            'inscriptions' => $inscriptions,
            'anneesScolaires' => $anneesScolaires,
            'selectedAnneeId' => (int) $selectedAnneeId,
            'etablissements' => $etablissements,
            'supportsAllEtablissements' => $scope['supportsAllEtablissements'],
            'isAllEtablissements' => $scope['isAllEtablissements'],
            'stats' => [
                'totalInscrits' => $totalInscrits,
                'totalFilles' => $totalFilles,
                'totalGarcons' => $totalGarcons,
                'totalNonDefinis' => $totalNonDefinis,
                'totalRedoublants' => $totalRedoublants,
                'totalEnAttente' => $totalEnAttente,
                'nonInscritsCount' => $nonInscritsCount,
            ],
            'filters' => [
                'etablissement_id' => $effectiveEtablissementId ? (string) $effectiveEtablissementId : '',
                'cycle_id' => $request->input('cycle_id', ''),
                'niveau_id' => $request->input('niveau_id', ''),
                'classe_id' => $request->input('classe_id', ''),
                'statut' => $request->input('statut', ''),
                'is_redoublant' => $request->input('is_redoublant', ''),
                'sexe' => $request->input('sexe', ''),
                'search' => $request->input('search', ''),
            ],
            'can' => $can,
        ]);
    }

    /**
     * Enregistre un nouvel élève dans la Base Élève Globale et l'inscrit dans sa classe.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            // Identifiants & État Civil
            'matricule' => ['nullable', 'string', 'max:50', 'unique:eleves,matricule'],
            'matricule_national' => ['nullable', 'string', 'max:50'],
            'nom' => ['required', 'string', 'max:150'],
            'prenom' => ['nullable', 'string', 'max:150'],
            'sexe' => ['nullable', 'in:M,F'],
            'date_naissance' => ['nullable', 'date'],
            'lieu_naissance' => ['nullable', 'string', 'max:150'],
            'nationalite' => ['nullable', 'string', 'max:80'],
            'photo' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp,gif', 'max:5120'], // 5 Mo max

            // Filiation & Tuteurs
            'nom_pere' => ['nullable', 'string', 'max:150'],
            'nom_mere' => ['nullable', 'string', 'max:150'],
            'nom_tuteur' => ['nullable', 'string', 'max:150'],
            'telephone_parent' => ['nullable', 'string', 'max:50'],
            'email_parent' => ['nullable', 'email', 'max:150'],
            'adresse' => ['nullable', 'string', 'max:255'],

            // Affectation Scolaire Initiale
            'classe_id' => ['required', 'exists:classes,id'],
            'annee_scolaire_id' => ['required', 'exists:annees_scolaires,id'],
            'statut' => ['required', 'in:validee,en_attente'],
            'is_redoublant' => ['required', 'boolean'],
            'etablissement_provenance' => ['nullable', 'string', 'max:150'],
            'observations' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($validated, $request) {
            // Résolution préalable du campus d'accueil pour contrôler le périmètre et structurer le matricule
            $classe = Classe::with('niveau.cycle.section')->findOrFail($validated['classe_id']);
            $etablissementId = (int) ($classe->etablissement_id ?? $classe->niveau?->cycle?->section?->etablissement_id);

            $context = $this->resolveEtablissementContext($request);
            if (! ($context['canAccessAll'] ?? $context['isSuperAdmin']) && $etablissementId !== (int) $context['etablissementId']) {
                abort(403, 'Action non autorisée sur une classe d\'un autre établissement.');
            }

            // Génération systématique selon la nomenclature structurée : {CODE_ETAB}-{YYYY}-{0001}
            $matricule = Eleve::generateNextMatricule(
                (int) $validated['annee_scolaire_id'],
                null,
                $etablissementId ?: null
            );

            // Gestion et optimisation de l'upload de photo sur le disque public
            $photoUrl = $request->hasFile('photo')
                ? $this->storeElevePhoto($request->file('photo'))
                : null;

            // Création de l'Élève dans la Base Globale (champs alignés avec la vue create.tsx)
            $eleve = Eleve::create([
                'matricule' => $matricule,
                'matricule_national' => $validated['matricule_national'] ?? null,
                'nom' => strtoupper(trim($validated['nom'])),
                'prenom' => ! empty($validated['prenom']) ? trim($validated['prenom']) : null,
                'sexe' => ! empty($validated['sexe']) ? $validated['sexe'] : null,
                'date_naissance' => $validated['date_naissance'] ?? null,
                'lieu_naissance' => $validated['lieu_naissance'] ?? null,
                'nationalite' => ! empty($validated['nationalite']) ? trim((string) $validated['nationalite']) : 'CAMEROUNAISE',
                'photo_url' => $photoUrl,
                'nom_pere' => $validated['nom_pere'] ?? null,
                'nom_mere' => $validated['nom_mere'] ?? null,
                'nom_tuteur' => $validated['nom_tuteur'] ?? null,
                'telephone_parent' => ! empty($validated['telephone_parent']) ? trim($validated['telephone_parent']) : null,
                'email_parent' => $validated['email_parent'] ?? null,
                'adresse' => $validated['adresse'] ?? null,
                'is_active' => true,
            ]);

            // Création de l'inscription scolaire avec son identifiant d'établissement
            $inscription = Inscription::create([
                'eleve_id' => $eleve->id,
                'classe_id' => $validated['classe_id'],
                'etablissement_id' => $etablissementId,
                'annee_scolaire_id' => $validated['annee_scolaire_id'],
                'type_inscription' => 'nouveau',
                'is_redoublant' => $validated['is_redoublant'],
                'statut' => $validated['statut'],
                'date_inscription' => now()->toDateString(),
                'etablissement_provenance' => $validated['etablissement_provenance'] ?? null,
                'observations' => $validated['observations'] ?? null,
            ]);

            // Initialisation automatique du compte financier de l'élève selon la grille tarifaire de son niveau
            app(FinanceService::class)->initialiserCompteEleve($inscription);
        });

        // Redirection selon l'action demandée (Enregistrer & Nouveau ou Enregistrer & Retourner)
        if ($request->input('_action') === 'save_and_new') {
            return redirect()->route('administration.eleves.create')->with('success', 'Élève enregistré et inscrit avec succès ! Vous pouvez saisir un nouvel élève.');
        }

        return redirect()->route('administration.eleves.index')->with('success', 'Élève enregistré et inscrit avec succès !');
    }

    /**
     * Met à jour les informations d'un élève dans la Base Globale.
     *
     * Le matricule interne de l'élève est strictement immuable (lecture seule) après sa création.
     */
    public function update(Request $request, Eleve $eleve): RedirectResponse
    {
        // Cloisonnement multi-campus : un utilisateur local ne peut modifier qu'un élève inscrit dans son établissement
        $context = $this->resolveEtablissementContext($request);
        if (! ($context['canAccessAll'] ?? $context['isSuperAdmin']) && $context['etablissementId']) {
            $belongsToCampus = Inscription::withoutGlobalScopes()
                ->where('eleve_id', $eleve->id)
                ->where('etablissement_id', (int) $context['etablissementId'])
                ->exists();
            abort_unless($belongsToCampus, 403, 'Action non autorisée sur un élève appartenant à un autre établissement.');
        }

        $validated = $request->validate([
            'matricule_national' => ['nullable', 'string', 'max:50'],
            'nom' => ['required', 'string', 'max:150'],
            'prenom' => ['nullable', 'string', 'max:150'],
            'sexe' => ['nullable', 'in:M,F'],
            'date_naissance' => ['nullable', 'date'],
            'lieu_naissance' => ['nullable', 'string', 'max:150'],
            'nationalite' => ['nullable', 'string', 'max:80'],
            'photo' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp,gif', 'max:5120'],
            'remove_photo' => ['nullable', 'boolean'],
            'nom_pere' => ['nullable', 'string', 'max:150'],
            'nom_mere' => ['nullable', 'string', 'max:150'],
            'nom_tuteur' => ['nullable', 'string', 'max:150'],
            'telephone_parent' => ['nullable', 'string', 'max:50'],
            'email_parent' => ['nullable', 'email', 'max:150'],
            'adresse' => ['nullable', 'string', 'max:255'],
            'is_redoublant' => ['nullable'],
            'inscription_id' => ['nullable', 'integer', 'exists:inscriptions,id'],
        ]);

        // Gestion du remplacement ou de la suppression de la photo d'identité
        $removePhoto = filter_var($request->input('remove_photo', false), FILTER_VALIDATE_BOOLEAN);
        $oldPhotoUrl = $eleve->photo_url;

        if ($request->hasFile('photo')) {
            $validated['photo_url'] = $this->storeElevePhoto($request->file('photo'));
            $this->deleteElevePhoto($oldPhotoUrl);
        } elseif ($removePhoto) {
            $validated['photo_url'] = null;
            $this->deleteElevePhoto($oldPhotoUrl);
        }

        $validated['nom'] = strtoupper(trim($validated['nom']));
        $validated['prenom'] = ! empty($validated['prenom']) ? trim($validated['prenom']) : null;
        $validated['sexe'] = ! empty($validated['sexe']) ? $validated['sexe'] : null;
        $validated['telephone_parent'] = ! empty($validated['telephone_parent']) ? trim($validated['telephone_parent']) : null;

        // Mise à jour éventuelle du statut / régime scolaire (redoublant / nouveau) sur l'inscription
        if ($request->has('is_redoublant')) {
            $isRedoublant = filter_var($request->input('is_redoublant'), FILTER_VALIDATE_BOOLEAN);
            $inscription = null;

            if ($request->filled('inscription_id')) {
                $inscription = Inscription::where('id', $request->input('inscription_id'))
                    ->where('eleve_id', $eleve->id)
                    ->first();
            }

            if (! $inscription) {
                $inscription = $eleve->inscriptions()->latest('id')->first();
            }

            if ($inscription) {
                $inscription->update(['is_redoublant' => $isRedoublant]);
            }
        }

        // Nettoyer les champs non persistés directement sur le modèle Eleve avant la mise à jour
        unset($validated['photo'], $validated['remove_photo'], $validated['is_redoublant'], $validated['inscription_id']);

        $eleve->update($validated);

        return back()->with('success', 'Fiche de l\'élève mise à jour avec succès !');
    }

    /**
     * Supprime définitivement un élève uniquement s'il ne possède aucune opération active liée
     * (versement valide, bourse/dispense/option active, note, assiduité, sanction, consigne, délibération...).
     *
     * Autorise la suppression si d'éventuels versements saisis par erreur ont préalablement été
     * annulés (`statut = 'annule'`), en purgeant proprement ces écritures annulées dans la transaction
     * tout en conservant une trace complète dans le journal d'audit applicatif.
     *
     * @param  Request  $request  Requête HTTP entrante pour l'identification de l'opérateur et du périmètre campus.
     * @param  Eleve  $eleve  Élève ciblé pour la suppression.
     * @return RedirectResponse Redirection avec confirmation ou motif explicite de blocage.
     */
    public function destroy(Request $request, Eleve $eleve): RedirectResponse
    {
        $context = $this->resolveEtablissementContext($request);
        if (! ($context['canAccessAll'] ?? $context['isSuperAdmin'])) {
            $eleveEtabIds = Inscription::withoutGlobalScopes()
                ->where('eleve_id', $eleve->id)
                ->pluck('etablissement_id')
                ->filter()
                ->unique();

            if ($eleveEtabIds->isNotEmpty() && ! $eleveEtabIds->contains((int) $context['etablissementId'])) {
                abort(403, 'Action non autorisée sur un élève appartenant à un autre établissement.');
            }
        }

        $nom = $eleve->nom_complet;
        $matricule = $eleve->matricule;
        $blockers = $eleve->getDeletionBlockers();

        if (! empty($blockers)) {
            $message = "Suppression impossible : l'élève « {$nom} » est encore lié à des opérations actives (".implode(', ', $blockers)."). Veuillez d'abord annuler ou retirer ces éléments (ex: annuler le versement en Caisse) avant de supprimer la fiche.";

            return back()->with('error', $message)->withErrors(['error' => $message]);
        }

        $recusAnnulesPurges = [];

        DB::transaction(function () use ($eleve, &$recusAnnulesPurges) {
            $inscriptionIds = Inscription::withoutGlobalScopes()
                ->where('eleve_id', $eleve->id)
                ->pluck('id');

            $compteIds = CompteFinancierEleve::withoutGlobalScopes()
                ->where('eleve_id', $eleve->id)
                ->pluck('id');

            // Journaliser puis purger proprement les éventuels versements déjà annulés (`statut = 'annule'`)
            if ($inscriptionIds->isNotEmpty() || $compteIds->isNotEmpty()) {
                $versementsAnnules = VersementCaisse::withoutGlobalScopes()
                    ->withTrashed()
                    ->where(function ($q) use ($inscriptionIds, $compteIds) {
                        if ($inscriptionIds->isNotEmpty()) {
                            $q->whereIn('inscription_id', $inscriptionIds);
                        }
                        if ($compteIds->isNotEmpty()) {
                            $q->orWhereIn('compte_financier_id', $compteIds);
                        }
                    })
                    ->get();

                if ($versementsAnnules->isNotEmpty()) {
                    $recusAnnulesPurges = $versementsAnnules->map(fn (VersementCaisse $v) => [
                        'id' => $v->id,
                        'reference_recu' => $v->reference_recu,
                        'montant_total' => (float) $v->montant_total,
                        'statut' => $v->statut,
                        'motif_annulation' => $v->motif_annulation,
                        'annule_le' => $v->annule_le?->toIso8601String(),
                    ])->values()->all();

                    VentilationVersement::whereIn('versement_id', $versementsAnnules->pluck('id'))->delete();
                    VersementCaisse::withoutGlobalScopes()
                        ->withTrashed()
                        ->whereIn('id', $versementsAnnules->pluck('id'))
                        ->forceDelete();
                }
            }

            CompteFinancierEleve::withoutGlobalScopes()
                ->where('eleve_id', $eleve->id)
                ->delete();
            Inscription::withoutGlobalScopes()
                ->where('eleve_id', $eleve->id)
                ->delete();
            $eleve->delete();
        });

        // Supprimer la photo stockée sur le disque public uniquement après la validation effective de la transaction
        $this->deleteElevePhoto($eleve->photo_url);

        Log::warning('Suppression définitive élève exécutée', [
            'eleve_id' => $eleve->id,
            'matricule' => $matricule,
            'nom_complet' => $nom,
            'operateur_user_id' => $request->user()?->id,
            'operateur_nom' => $request->user()?->name,
            'recus_annules_purges' => $recusAnnulesPurges,
        ]);

        return back()->with('success', "L'élève {$nom} ({$matricule}) a été supprimé avec succès.");
    }

    /**
     * Optimise et enregistre la photo d'identité d'un élève sur le disque public.
     * Redimensionne les portraits volumineux (max 800 px) en JPEG standard afin d'assurer
     * un affichage rapide dans l'index, les fiches et les impressions PDF (cartes/bulletins).
     *
     * @param  UploadedFile  $file  Fichier image envoyé depuis le formulaire.
     * @return string Chemin web relatif normalisé (ex: `/storage/photos_eleves/fichier.jpg`).
     */
    private function storeElevePhoto(UploadedFile $file): string
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: '');

        if ($extension !== 'gif' && function_exists('imagecreatefromstring')) {
            $rawContent = @file_get_contents($file->getRealPath());
            $image = $rawContent !== false ? @imagecreatefromstring($rawContent) : false;

            if ($image !== false) {
                // Redresser les photos prises au smartphone selon leur métadonnée EXIF d'orientation
                if (in_array($extension, ['jpg', 'jpeg'], true) && function_exists('exif_read_data')) {
                    $exif = @exif_read_data($file->getRealPath());
                    $orientation = (int) ($exif['Orientation'] ?? 1);
                    $image = match ($orientation) {
                        3 => imagerotate($image, 180, 0) ?: $image,
                        6 => imagerotate($image, -90, 0) ?: $image,
                        8 => imagerotate($image, 90, 0) ?: $image,
                        default => $image,
                    };
                }

                $width = imagesx($image);
                $height = imagesy($image);
                $maxDimension = 800;

                if ($width > $maxDimension || $height > $maxDimension || $extension === 'webp' || $file->getSize() > 512000) {
                    $scale = min(1.0, $maxDimension / max(1, $width, $height));
                    $targetWidth = max(1, (int) round($width * $scale));
                    $targetHeight = max(1, (int) round($height * $scale));

                    $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
                    if ($canvas !== false) {
                        $white = imagecolorallocate($canvas, 255, 255, 255);
                        imagefill($canvas, 0, 0, $white);
                        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

                        ob_start();
                        imagejpeg($canvas, null, 85);
                        $encoded = ob_get_clean();

                        unset($canvas, $image);

                        if (is_string($encoded) && $encoded !== '') {
                            $relativePath = 'photos_eleves/'.pathinfo($file->hashName(), PATHINFO_FILENAME).'.jpg';
                            Storage::disk('public')->put($relativePath, $encoded);

                            return '/storage/'.ltrim($relativePath, '/');
                        }
                    }
                }

                unset($image);
            }
        }

        $path = $file->store('photos_eleves', 'public');

        return '/storage/'.ltrim($path, '/');
    }

    /**
     * Supprime physiquement l'ancienne photo d'un élève sur le disque public si elle est gérée dans `photos_eleves`.
     *
     * @param  string|null  $photoUrl  Chemin ou URL stockée sur le modèle Eleve.
     */
    private function deleteElevePhoto(?string $photoUrl): void
    {
        $relativePath = $this->resolveElevePhotoRelativePath($photoUrl);
        if ($relativePath !== null && Storage::disk('public')->exists($relativePath)) {
            Storage::disk('public')->delete($relativePath);
        }
    }

    /**
     * Extrait le chemin relatif sur le disque `public` à partir d'une URL ou d'un chemin de photo élève.
     *
     * @param  string|null  $photoUrl  Valeur brute issue de `eleves.photo_url`.
     * @return string|null Chemin relatif commençant par `photos_eleves/`, ou null si hors périmètre.
     */
    private function resolveElevePhotoRelativePath(?string $photoUrl): ?string
    {
        if (empty($photoUrl)) {
            return null;
        }

        $path = parse_url($photoUrl, PHP_URL_PATH) ?: $photoUrl;
        $normalized = ltrim($path, '/');

        if (str_starts_with($normalized, 'storage/')) {
            $normalized = substr($normalized, strlen('storage/'));
        }

        return str_starts_with($normalized, 'photos_eleves/') ? $normalized : null;
    }

    /**
     * Réinscrit un ancien élève existant dans une nouvelle classe pour une année scolaire donnée.
     *
     * Initialise automatiquement son compte financier scolaire en intégrant optionnellement
     * les arriérés ou dettes antérieures reportées manuellement par le comptable ou la secrétaire.
     *
     * @param  Request  $request  Requête HTTP contenant les paramètres d'affectation et les arriérés.
     * @return RedirectResponse Redirection avec message de succès ou d'erreur.
     */
    public function reinscrire(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'eleve_id' => ['required', 'exists:eleves,id'],
            'classe_id' => ['required', 'exists:classes,id'],
            'annee_scolaire_id' => ['required', 'exists:annees_scolaires,id'],
            'is_redoublant' => ['required', 'boolean'],
            'statut' => ['required', 'in:validee,en_attente'],
            'arrieres_anterieurs' => ['nullable', 'numeric', 'min:0'],
            'observations' => ['nullable', 'string'],
        ]);

        // Vérifie tous campus confondus si l'élève n'a pas déjà une inscription active pour cette année scolaire
        $existing = Inscription::withoutGlobalScopes()
            ->where('eleve_id', $validated['eleve_id'])
            ->where('annee_scolaire_id', $validated['annee_scolaire_id'])
            ->whereIn('statut', ['validee', 'en_attente'])
            ->first();

        if ($existing) {
            return back()->with('error', 'Cet élève dispose déjà d\'une inscription active pour cette année scolaire.');
        }

        $classe = Classe::findOrFail($validated['classe_id']);
        $etablissementId = $classe->etablissement_id;

        $context = $this->resolveEtablissementContext($request);
        if (! ($context['canAccessAll'] ?? $context['isSuperAdmin'])) {
            if ((int) $etablissementId !== (int) $context['etablissementId']) {
                abort(403, 'Action non autorisée sur une classe d\'un autre établissement.');
            }
            $belongsToCampus = Inscription::withoutGlobalScopes()
                ->where('eleve_id', $validated['eleve_id'])
                ->where('etablissement_id', (int) $context['etablissementId'])
                ->exists();
            abort_unless($belongsToCampus, 403, 'Action non autorisée : cet élève appartient à un autre établissement.');
        }

        $inscription = Inscription::create([
            'eleve_id' => $validated['eleve_id'],
            'classe_id' => $validated['classe_id'],
            'etablissement_id' => $etablissementId,
            'annee_scolaire_id' => $validated['annee_scolaire_id'],
            'type_inscription' => 'reinscription',
            'is_redoublant' => $validated['is_redoublant'],
            'statut' => $validated['statut'],
            'date_inscription' => now()->toDateString(),
            'observations' => $validated['observations'] ?? null,
        ]);

        // Initialisation automatique du compte financier avec report des arriérés antérieurs
        $arrieres = (float) ($validated['arrieres_anterieurs'] ?? 0);
        app(FinanceService::class)->initialiserCompteEleve($inscription, 0, $arrieres);

        $eleveNom = $inscription->eleve?->nom_complet ?: 'L\'élève';

        if ($request->input('_action') === 'save_and_new') {
            return redirect()->route('administration.eleves.reinscription', ['tab' => 'individuel'])->with('success', "{$eleveNom} a été réinscrit avec succès ! Vous pouvez procéder à la réinscription suivante.");
        }

        return redirect()->route('administration.eleves.index')->with('success', "{$eleveNom} a été réinscrit avec succès dans la classe {$classe->nom} !");
    }

    /**
     * Met à jour le statut d'une inscription (validee, suspendue, exclu, en_attente).
     */
    public function updateStatut(Request $request, Inscription $inscription): RedirectResponse
    {
        $context = $this->resolveEtablissementContext($request);
        if (! ($context['canAccessAll'] ?? $context['isSuperAdmin']) && (int) $inscription->etablissement_id !== (int) $context['etablissementId']) {
            abort(403, 'Action non autorisée sur une inscription d\'un autre établissement.');
        }

        $validated = $request->validate([
            'statut' => ['required', 'in:validee,en_attente,suspendue,exclu,transfere'],
            'motif_sortie' => ['nullable', 'string', 'max:255'],
        ]);

        $inscription->update($validated);

        return back()->with('success', 'Statut de l\'inscription actualisé avec succès !');
    }

    /**
     * Transfère un groupe d'élèves d'une classe source vers une classe cible (changement de classe
     * au sein du même établissement ou transfert inter-campus avec comptes financiers séparés).
     *
     * @param  Request  $request  Requête HTTP contenant `inscription_ids`, `target_classe_id` et `motif_transfert`.
     * @return RedirectResponse Redirection avec notification de succès.
     */
    public function transferer(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'inscription_ids' => ['required', 'array', 'min:1'],
            'inscription_ids.*' => ['required', 'exists:inscriptions,id'],
            'target_classe_id' => ['required', 'exists:classes,id'],
            'motif_transfert' => ['nullable', 'string', 'max:255'],
        ]);

        $targetClasse = Classe::with('niveau.cycle.section.etablissement')->findOrFail($validated['target_classe_id']);
        $targetEtablissementId = (int) ($targetClasse->etablissement_id ?? $targetClasse->niveau?->cycle?->section?->etablissement_id);
        $context = $this->resolveEtablissementContext($request);

        // Si accès restreint à un campus, vérifier que toutes les inscriptions sources appartiennent bien au campus assigné
        if (! ($context['canAccessAll'] ?? $context['isSuperAdmin'])) {
            $unauthorizedSources = Inscription::whereIn('id', $validated['inscription_ids'])
                ->where('etablissement_id', '!=', $context['etablissementId'])
                ->exists();
            if ($unauthorizedSources) {
                abort(403, 'Action non autorisée sur des inscriptions d\'un autre établissement.');
            }
        }

        $motif = $validated['motif_transfert'] ?: 'Transfert pédagogique/administratif';
        $transferredCount = 0;

        DB::transaction(function () use ($validated, $targetClasse, $targetEtablissementId, $motif, &$transferredCount) {
            $financeService = app(FinanceService::class);
            $inscriptions = Inscription::with('classe.niveau.cycle.section.etablissement')
                ->whereIn('id', $validated['inscription_ids'])
                ->get();

            foreach ($inscriptions as $oldInscription) {
                // Même classe : aucune action requise
                if ((int) $oldInscription->classe_id === (int) $targetClasse->id) {
                    continue;
                }

                $sourceEtablissementId = (int) (
                    $oldInscription->etablissement_id
                    ?? $oldInscription->classe?->etablissement_id
                    ?? $oldInscription->classe?->niveau?->cycle?->section?->etablissement_id
                );
                $sameEtablissement = ($sourceEtablissementId === $targetEtablissementId);

                if ($sameEtablissement) {
                    // Changement de classe dans le même établissement : conservation de l'inscription et de l'historique,
                    // puis recalcul automatique du compte financier selon le tarif de la classe cible (paiements et remises conservés).
                    $sourceClasseNom = $oldInscription->classe?->nom;
                    $oldInscription->update([
                        'classe_id' => $targetClasse->id,
                        'etablissement_id' => $targetEtablissementId,
                        'observations' => trim(($oldInscription->observations ?? '')."\nTransféré depuis {$sourceClasseNom} le ".now()->format('d/m/Y')),
                    ]);
                    $oldInscription->unsetRelations();
                    $financeService->initialiserCompteEleve($oldInscription);
                } else {
                    // Transfert inter-campus : archivage du dossier source (scolarité & paiements)
                    // et création d'une nouvelle inscription avec compte financier séparé sur le campus cible.
                    $sourceEtabNom = $oldInscription->etablissement?->nom
                        ?? $oldInscription->classe?->niveau?->cycle?->section?->etablissement?->nom
                        ?? null;

                    $oldInscription->update([
                        'statut' => 'transfere',
                        'motif_sortie' => "Transféré vers {$targetClasse->nom} : {$motif}",
                    ]);

                    $newInscription = Inscription::create([
                        'eleve_id' => $oldInscription->eleve_id,
                        'classe_id' => $targetClasse->id,
                        'etablissement_id' => $targetEtablissementId,
                        'annee_scolaire_id' => $oldInscription->annee_scolaire_id,
                        'type_inscription' => 'nouveau',
                        'is_redoublant' => $oldInscription->is_redoublant,
                        'statut' => 'validee',
                        'date_inscription' => now()->toDateString(),
                        'etablissement_provenance' => $sourceEtabNom,
                        'observations' => "Transfert inter-campus : {$motif}",
                    ]);

                    $financeService->initialiserCompteEleve($newInscription);
                }

                $transferredCount++;
            }
        });

        return back()->with('success', "{$transferredCount} élève(s) transféré(s) avec succès vers la classe {$targetClasse->nom} !");
    }

    /**
     * Importe une liste d'élèves à partir d'un fichier CSV / TSV (conforme à ELEVE.csv et SAGEES).
     */
    public function importCsv(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'fichier' => ['required', 'file', 'mimes:csv,txt', 'max:5120'], // 5 Mo max
            'classe_id' => ['required', 'exists:classes,id'],
            'annee_scolaire_id' => ['required', 'exists:annees_scolaires,id'],
            'ignorer_doublons' => ['nullable', 'boolean'],
        ]);

        $targetClasse = Classe::with('niveau.cycle.section')->findOrFail($validated['classe_id']);
        $etablissementId = (int) ($targetClasse->etablissement_id ?? $targetClasse->niveau?->cycle?->section?->etablissement_id);

        $context = $this->resolveEtablissementContext($request);
        if (! ($context['canAccessAll'] ?? $context['isSuperAdmin']) && $etablissementId !== (int) $context['etablissementId']) {
            abort(403, 'Action non autorisée sur une classe d\'un autre établissement.');
        }

        $file = $request->file('fichier');
        // Nettoyage de l'éventuel BOM UTF-8 (courant dans les fichiers générés par Excel)
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $file->getContent());
        $lines = explode("\n", str_replace("\r", '', $content));

        $imported = 0;
        $ignored = 0;
        $ignorerDoublons = $request->boolean('ignorer_doublons', true);

        DB::transaction(function () use ($lines, $validated, $etablissementId, $ignorerDoublons, &$imported, &$ignored) {
            foreach ($lines as $line) {
                // Nettoyage uniquement des retours chariot pour préserver les colonnes vides initiales (ex: TSV sans matricule)
                $line = rtrim($line, "\r\n");
                if (trim($line) === '') {
                    continue;
                }

                // Détecte le séparateur : tabulation ou point-virgule ou virgule
                $cols = str_contains($line, "\t")
                    ? explode("\t", $line)
                    : (str_contains($line, ';') ? explode(';', $line) : explode(',', $line));

                // Nettoyage des colonnes
                $cols = array_map(fn ($c) => trim(str_replace(['"', "'"], '', $c)), $cols);

                // Si c'est une ligne d'en-tête, on l'ignore
                if (isset($cols[0]) && in_array(strtolower($cols[0]), ['matricule', 'nom', 'code'])) {
                    continue;
                }

                // Correspondance des colonnes (format ELEVE.csv / SAGEES) :
                // Col 0: Matricule (optionnel ou string)
                // Col 1: Matricule national
                // Col 2: Nom
                // Col 3: Prénom
                // Col 4: Sexe
                // Col 5: Date naissance
                // Col 6: Lieu naissance
                // Col 7: Redoublant (O / N)
                // Col 8: Téléphone parent
                // Col 9: Adresse
                // Col 10: Nom parent / tuteur
                // Col 11: Nationalité

                $matricule = ! empty($cols[0]) && $cols[0] !== '\N' ? $cols[0] : null;
                $matriculeNat = ! empty($cols[1]) && $cols[1] !== '\N' ? $cols[1] : null;
                $nom = ! empty($cols[2]) ? $cols[2] : (! empty($cols[1]) ? $cols[1] : null);
                $prenom = ! empty($cols[3]) && $cols[3] !== '\N' ? $cols[3] : null;
                $sexe = ! empty($cols[4]) && in_array(strtoupper($cols[4]), ['M', 'F']) ? strtoupper($cols[4]) : null;
                $dateNaissRaw = ! empty($cols[5]) && $cols[5] !== '\N' ? $cols[5] : null;
                $lieuNaiss = ! empty($cols[6]) && $cols[6] !== '\N' ? $cols[6] : null;
                $isRedoublant = ! empty($cols[7]) && in_array(strtoupper($cols[7]), ['O', 'OUI', '1', 'TRUE']);
                $telParent = ! empty($cols[8]) && $cols[8] !== '\N' ? $cols[8] : null;
                $adresse = ! empty($cols[9]) && $cols[9] !== '\N' ? $cols[9] : null;
                $nomParent = ! empty($cols[10]) && $cols[10] !== '\N' ? $cols[10] : null;
                $nationalite = ! empty($cols[11]) && $cols[11] !== '\N' ? $cols[11] : 'CAMEROUNAISE';

                if (empty($nom)) {
                    $ignored++;

                    continue;
                }

                // Formatage de la date de naissance
                $dateNaissance = null;
                if ($dateNaissRaw) {
                    try {
                        if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $dateNaissRaw, $m)) {
                            $dateNaissance = sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
                        } else {
                            $dateNaissance = Carbon::parse($dateNaissRaw)->format('Y-m-d');
                        }
                    } catch (\Exception) {
                        $dateNaissance = null;
                    }
                }

                // Vérification des doublons par matricule ou génération selon la nomenclature {CODE_ETAB}-{YYYY}-{0001}
                if ($matricule && Eleve::where('matricule', $matricule)->exists()) {
                    if ($ignorerDoublons) {
                        $ignored++;

                        continue;
                    }
                    $matricule = Eleve::generateNextMatricule(
                        (int) $validated['annee_scolaire_id'],
                        null,
                        $etablissementId ?: null
                    );
                }

                if (! $matricule) {
                    $matricule = Eleve::generateNextMatricule(
                        (int) $validated['annee_scolaire_id'],
                        null,
                        $etablissementId ?: null
                    );
                }

                // Création de l'Élève
                $eleve = Eleve::create([
                    'matricule' => $matricule,
                    'matricule_national' => $matriculeNat,
                    'nom' => strtoupper($nom),
                    'prenom' => $prenom,
                    'sexe' => $sexe,
                    'date_naissance' => $dateNaissance,
                    'lieu_naissance' => $lieuNaiss,
                    'nationalite' => $nationalite,
                    'telephone_parent' => $telParent,
                    'nom_tuteur' => $nomParent,
                    'adresse' => $adresse,
                    'is_active' => true,
                ]);

                // Création de l'inscription avec son établissement
                $inscription = Inscription::create([
                    'eleve_id' => $eleve->id,
                    'classe_id' => $validated['classe_id'],
                    'etablissement_id' => $etablissementId,
                    'annee_scolaire_id' => $validated['annee_scolaire_id'],
                    'type_inscription' => 'nouveau',
                    'is_redoublant' => $isRedoublant,
                    'statut' => 'validee',
                    'date_inscription' => now()->toDateString(),
                ]);

                // Initialisation immédiate du compte financier élève lors de l'import CSV
                if ($etablissementId) {
                    app(FinanceService::class)->initialiserCompteEleve($inscription);
                }

                $imported++;
            }
        });

        return back()->with('success', "Importation terminée : {$imported} élève(s) importé(s) et inscrit(s), {$ignored} ligne(s) ignorée(s).");
    }

    /**
     * Recherche d'anciens élèves non encore inscrits pour une année scolaire donnée.
     * Utilisé pour la réinscription rapide via autocomplétion avec affichage ergonomique
     * de la dernière classe fréquentée, de la décision du conseil et du reliquat financier antérieur.
     */
    public function searchAnciens(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));
        $anneeId = $request->integer('annee_id');

        if (empty($q)) {
            return response()->json([]);
        }

        $context = $this->resolveEtablissementContext($request);
        $canAccessAll = (bool) ($context['canAccessAll'] ?? $context['isSuperAdmin']);
        $scopedEtabId = $context['etablissementId'] ? (int) $context['etablissementId'] : null;

        $elevesQuery = Eleve::search($q)
            ->whereDoesntHave('inscriptions', function ($sub) use ($anneeId) {
                $sub->withoutGlobalScopes();
                if ($anneeId) {
                    $sub->where('annee_scolaire_id', $anneeId)
                        ->whereIn('statut', ['validee', 'en_attente']);
                }
            });

        // Cloisonnement multi-campus : un utilisateur local ne recherche que parmi les anciens élèves de son campus
        if (! $canAccessAll && $scopedEtabId) {
            $elevesQuery->whereHas('inscriptions', function ($iq) use ($scopedEtabId) {
                $iq->withoutGlobalScopes()->where('etablissement_id', $scopedEtabId);
            });
        }

        $eleves = $elevesQuery
            ->with([
                'inscriptions' => function ($iq) use ($anneeId) {
                    if ($anneeId) {
                        $iq->where('annee_scolaire_id', '!=', $anneeId);
                    }
                    $iq->with([
                        'classe.niveau.cycle.section.etablissement',
                        'anneeScolaire',
                        'compteFinancier',
                        'deliberations' => fn ($dq) => $dq->where('type_deliberation', 'annuelle'),
                    ])->latest('date_inscription');
                },
            ])
            ->take(15)
            ->get();

        $payload = $eleves->map(function ($eleve) {
            $derniereInscription = $eleve->inscriptions->first();
            $delib = $derniereInscription?->deliberations?->first();

            $statutRetenu = $delib?->statut_retenu ?? null;
            $isRedoublant = ($statutRetenu === 'redouble');
            $soldeRestantPrecedent = (float) ($derniereInscription?->compteFinancier?->solde_restant ?? 0);

            return [
                'id' => $eleve->id,
                'matricule' => $eleve->matricule,
                'matricule_national' => $eleve->matricule_national,
                'nom' => $eleve->nom,
                'prenom' => $eleve->prenom,
                'nom_complet' => $eleve->nom_complet,
                'sexe' => $eleve->sexe,
                'date_naissance' => $eleve->date_naissance?->format('Y-m-d'),
                'telephone_parent' => $eleve->telephone_parent,
                'derniere_classe' => $derniereInscription ? [
                    'classe_id' => $derniereInscription->classe_id,
                    'classe_nom' => $derniereInscription->classe?->nom ?? 'N/A',
                    'niveau_nom' => $derniereInscription->classe?->niveau?->nom ?? 'N/A',
                    'annee_nom' => $derniereInscription->anneeScolaire?->nom ?? $derniereInscription->anneeScolaire?->code ?? 'N/A',
                    'decision_conseil' => $delib?->decision_conseil ?? ($statutRetenu === 'promu' ? 'Promu en classe supérieure' : ($statutRetenu === 'redouble' ? 'Autorisé à redoubler' : null)),
                    'statut_retenu' => $statutRetenu,
                    'is_redoublant_preconise' => $isRedoublant,
                    'solde_impaye_precedent' => $soldeRestantPrecedent,
                ] : null,
            ];
        });

        return response()->json($payload);
    }

    /**
     * Exporte la liste des élèves inscrits au format CSV standard (compatible Excel avec point-virgule et UTF-8 BOM).
     *
     * @param  Request  $request  Requête HTTP entrante avec filtres d'inscription.
     * @return StreamedResponse Téléchargement du fichier CSV.
     */
    public function exportCsv(Request $request)
    {
        abort_unless($request->user()->can('eleves.view'), 403, 'Accès non autorisé.');

        $selectedAnneeId = $request->integer('annee_scolaire_id') ?: AnneeScolaire::current()?->id;
        $context = $this->resolveEtablissementContext($request);
        $scopedEtablissementId = $context['etablissementId'];
        $canAccessAll = (bool) ($context['canAccessAll'] ?? $context['isSuperAdmin']);
        $scope = $this->resolveElevesScope($request, $context);
        $effectiveEtablissementId = $scope['effectiveEtablissementId'];

        $inscriptionsQuery = Inscription::with([
            'eleve',
            'etablissement',
            'classe.niveau',
        ]);

        if ($selectedAnneeId) {
            $inscriptionsQuery->where('annee_scolaire_id', $selectedAnneeId);
        }

        if (! $canAccessAll) {
            if ($scopedEtablissementId) {
                $inscriptionsQuery->where('etablissement_id', $scopedEtablissementId);
            }
        } elseif ($effectiveEtablissementId) {
            $inscriptionsQuery->where('etablissement_id', $effectiveEtablissementId);
        }

        if ($classeId = $request->integer('classe_id')) {
            $inscriptionsQuery->where('classe_id', $classeId);
        }

        if ($statut = $request->input('statut')) {
            $inscriptionsQuery->where('statut', $statut);
        }

        if ($sexe = $request->input('sexe')) {
            $inscriptionsQuery->whereHas('eleve', function ($q) use ($sexe) {
                if ($sexe === 'ND') {
                    $q->whereNull('sexe')->orWhere('sexe', '');
                } else {
                    $q->where('sexe', $sexe);
                }
            });
        }

        if ($search = $request->input('search')) {
            $inscriptionsQuery->whereHas('eleve', function ($q) use ($search) {
                $q->search($search);
            });
        }

        $inscriptions = $inscriptionsQuery->orderBy('classe_id')
            ->orderBy(
                Eleve::select('nom')->whereColumn('eleves.id', 'inscriptions.eleve_id')
            )
            ->get();

        $annee = $selectedAnneeId ? AnneeScolaire::find($selectedAnneeId) : null;
        $anneeCode = $annee ? str_replace(['/', '\\', ' '], '_', $annee->code) : date('Y');
        $filename = "export_eleves_{$anneeCode}_".date('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($inscriptions) {
            $handle = fopen('php://output', 'w');
            // Inclusion du BOM UTF-8 (\xEF\xBB\xBF) pour compatibilité immédiate avec Microsoft Excel sous Windows
            fwrite($handle, "\xEF\xBB\xBF");

            // En-têtes conformes au modèle officiel (délimiteur point-virgule)
            fputcsv($handle, [
                'Matricule',
                'MatriculeNational',
                'Nom',
                'Prenom',
                'Sexe',
                'DateNaissance',
                'LieuNaissance',
                'Classe',
                'Redoublant',
                'Statut',
                'TelephoneParent',
                'Adresse',
                'NomTuteur',
                'Nationalite',
            ], ';');

            // Neutralise les préfixes de formules tableur (=, @, +, -) contre l'injection CSV (CSV Formula Injection)
            $sanitizeCsvCell = static function (mixed $value): string {
                $str = (string) ($value ?? '');
                if ($str !== '' && preg_match('/^[=@]|^[+\-](?![0-9\s])/', $str) === 1) {
                    return "'".$str;
                }

                return $str;
            };

            foreach ($inscriptions as $insc) {
                $el = $insc->eleve;
                fputcsv($handle, array_map($sanitizeCsvCell, [
                    $el->matricule ?? '',
                    $el->matricule_national ?? '',
                    $el->nom,
                    $el->prenom ?? '',
                    $el->sexe,
                    $el->date_naissance ? Carbon::parse($el->date_naissance)->format('d/m/Y') : '',
                    $el->lieu_naissance ?? '',
                    $insc->classe?->nom ?? '',
                    $insc->is_redoublant ? 'OUI' : 'NON',
                    $insc->statut,
                    $el->telephone_parent ?? '',
                    $el->adresse ?? '',
                    $el->nom_tuteur ?? $el->nom_pere ?? $el->nom_mere ?? '',
                    $el->nationalite ?? 'CAMEROUNAISE',
                ]), ';');
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Récupère la liste des élèves d'une classe source (souvent de l'année précédente)
     * pour alimenter le tableau de réinscription collective en lot.
     * Indique pour chaque élève sa décision de fin d'année, son statut préconisé,
     * ses dettes antérieures, et s'il est déjà inscrit dans la nouvelle année scolaire.
     *
     * @param  Request  $request  Requête HTTP entrante (classe_id, annee_destination_id).
     * @return JsonResponse Liste structurée des élèves de la classe source.
     */
    public function getClasseElevesForReinscription(Request $request): JsonResponse
    {
        $classeId = $request->integer('classe_id');
        $anneeSourceId = $request->integer('annee_source_id');
        $anneeDestinationId = $request->integer('annee_destination_id') ?: AnneeScolaire::current()?->id;

        if (! $classeId) {
            return response()->json([]);
        }

        $query = Inscription::where('classe_id', $classeId)
            ->whereIn('statut', ['validee', 'en_attente']);

        if ($anneeSourceId) {
            $query->where('annee_scolaire_id', $anneeSourceId);
        }

        $inscriptions = $query->with([
            'eleve',
            'compteFinancier',
            'deliberations' => fn ($dq) => $dq->where('type_deliberation', 'annuelle'),
        ])->get();

        $eleveIds = $inscriptions->pluck('eleve_id')->filter();

        // Récupération des inscriptions déjà actives pour l'année scolaire de destination
        $dejaInscrits = Inscription::whereIn('eleve_id', $eleveIds)
            ->where('annee_scolaire_id', $anneeDestinationId)
            ->whereIn('statut', ['validee', 'en_attente'])
            ->with('classe:id,nom')
            ->get()
            ->keyBy('eleve_id');

        $payload = $inscriptions->map(function ($insc) use ($dejaInscrits) {
            $el = $insc->eleve;
            if (! $el) {
                return null;
            }

            $delib = $insc->deliberations->first();
            $statutRetenu = $delib?->statut_retenu ?? null;
            $isRedoublant = ($statutRetenu === 'redouble');
            $soldeRestant = (float) ($insc->compteFinancier?->solde_restant ?? 0);
            $existInsc = $dejaInscrits->get($el->id);

            return [
                'eleve_id' => $el->id,
                'matricule' => $el->matricule,
                'matricule_national' => $el->matricule_national,
                'nom' => $el->nom,
                'prenom' => $el->prenom,
                'nom_complet' => $el->nom_complet,
                'sexe' => $el->sexe,
                'date_naissance' => $el->date_naissance?->format('Y-m-d'),
                'telephone_parent' => $el->telephone_parent,
                'deja_inscrit' => $existInsc !== null,
                'classe_actuelle_nom' => $existInsc?->classe?->nom ?? null,
                'decision_conseil' => $delib?->decision_conseil ?? ($statutRetenu === 'promu' ? 'Promu en classe supérieure' : ($statutRetenu === 'redouble' ? 'Autorisé à redoubler' : null)),
                'statut_retenu' => $statutRetenu,
                'is_redoublant_preconise' => $isRedoublant,
                'solde_impaye_precedent' => $soldeRestant,
            ];
        })->filter()->values();

        return response()->json($payload);
    }

    /**
     * Effectue la réinscription collective (en lot) d'une sélection d'élèves vers une classe cible.
     * Utilisé pour la rentrée scolaire afin de promouvoir une cohorte entière en un clic.
     *
     * @param  Request  $request  Requête HTTP avec la liste des élèves, la classe cible et l'année.
     * @return RedirectResponse Redirection avec message de succès ou d'erreur.
     */
    public function reinscrireLot(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'annee_scolaire_id' => ['required', 'exists:annees_scolaires,id'],
            'target_classe_id' => ['required', 'exists:classes,id'],
            'eleves' => ['required', 'array', 'min:1'],
            'eleves.*.eleve_id' => ['required', 'exists:eleves,id'],
            'eleves.*.is_redoublant' => ['nullable', 'boolean'],
            'eleves.*.arrieres_anterieurs' => ['nullable', 'numeric', 'min:0'],
            'statut' => ['nullable', 'in:validee,en_attente'],
        ]);

        $targetClasse = Classe::findOrFail($validated['target_classe_id']);
        $etablissementId = $targetClasse->etablissement_id;

        $context = $this->resolveEtablissementContext($request);
        if (! ($context['canAccessAll'] ?? $context['isSuperAdmin']) && (int) $etablissementId !== (int) $context['etablissementId']) {
            abort(403, 'Action non autorisée sur une classe d\'un autre établissement.');
        }

        $statut = $validated['statut'] ?? 'validee';
        $count = 0;
        $financeService = app(FinanceService::class);

        DB::transaction(function () use ($validated, $targetClasse, $etablissementId, $statut, $financeService, &$count) {
            foreach ($validated['eleves'] as $item) {
                $eleveId = $item['eleve_id'];
                $isRedoublant = (bool) ($item['is_redoublant'] ?? false);
                $arrieres = (float) ($item['arrieres_anterieurs'] ?? 0);

                // Ne pas réinscrire un élève déjà actif sur la nouvelle année (tous campus confondus)
                $existing = Inscription::withoutGlobalScopes()
                    ->where('eleve_id', $eleveId)
                    ->where('annee_scolaire_id', $validated['annee_scolaire_id'])
                    ->whereIn('statut', ['validee', 'en_attente'])
                    ->exists();

                if ($existing) {
                    continue;
                }

                $inscription = Inscription::create([
                    'eleve_id' => $eleveId,
                    'classe_id' => $targetClasse->id,
                    'etablissement_id' => $etablissementId,
                    'annee_scolaire_id' => $validated['annee_scolaire_id'],
                    'type_inscription' => 'reinscription',
                    'is_redoublant' => $isRedoublant,
                    'statut' => $statut,
                    'date_inscription' => now()->toDateString(),
                    'observations' => 'Réinscription collective en lot (promotion de rentrée scolaire)',
                ]);

                // Initialisation du compte financier avec report des arriérés si applicable
                $financeService->initialiserCompteEleve($inscription, 0, $arrieres);
                $count++;
            }
        });

        $message = "{$count} élève(s) réinscrit(s) avec succès dans la classe {$targetClasse->nom} pour la nouvelle année scolaire !";

        if ($request->input('_action') === 'stay') {
            return back()->with('success', $message);
        }

        return redirect()->route('administration.eleves.index')->with('success', $message);
    }

    /**
     * Détermine le périmètre d'établissement effectif pour la liste et l'export des élèves.
     *
     * Synchronise la page avec le sélecteur global d'en-tête (`EtablissementSwitcher`) via la session
     * (`current_etablissement_scope` et `current_etablissement_id`), tout en acceptant un paramètre
     * explicite `?etablissement_id=` dans l'URL.
     *
     * @param  Request  $request  Requête HTTP entrante.
     * @param  array<string, mixed>  $context  Contexte résolu par `resolveEtablissementContext`.
     * @return array{
     *     supportsAllEtablissements: bool,
     *     isAllEtablissements: bool,
     *     effectiveEtablissementId: int|null
     * }
     */
    private function resolveElevesScope(Request $request, array $context): array
    {
        $etablissements = $context['etablissements'];
        $canAccessAll = (bool) ($context['canAccessAll'] ?? $context['isSuperAdmin'] ?? false);
        $scopedEtablissementId = $context['etablissementId'] ? (int) $context['etablissementId'] : null;

        if (! $canAccessAll) {
            return [
                'supportsAllEtablissements' => false,
                'isAllEtablissements' => false,
                'effectiveEtablissementId' => $scopedEtablissementId,
            ];
        }

        $supportsAll = $etablissements->count() > 1;
        $requestedId = $request->query('etablissement_id') ?? $request->input('etablissement_id');

        if ($requestedId === 'all') {
            if ($request->hasSession()) {
                $request->session()->put('current_etablissement_scope', 'all');
            }

            return [
                'supportsAllEtablissements' => $supportsAll,
                'isAllEtablissements' => true,
                'effectiveEtablissementId' => null,
            ];
        }

        if ($requestedId !== null && $requestedId !== '' && is_numeric($requestedId)) {
            $effectiveId = (int) $requestedId;
            if ($request->hasSession()) {
                $request->session()->put('current_etablissement_id', $effectiveId);
                $request->session()->put('current_etablissement_scope', 'single');
            }

            return [
                'supportsAllEtablissements' => $supportsAll,
                'isAllEtablissements' => false,
                'effectiveEtablissementId' => $effectiveId,
            ];
        }

        $sessionScope = $request->hasSession() ? $request->session()->get('current_etablissement_scope') : null;
        $sessionEtabId = $request->hasSession() ? $request->session()->get('current_etablissement_id') : null;

        if ($sessionScope === 'single' && $sessionEtabId && is_numeric($sessionEtabId)) {
            return [
                'supportsAllEtablissements' => $supportsAll,
                'isAllEtablissements' => false,
                'effectiveEtablissementId' => (int) $sessionEtabId,
            ];
        }

        return [
            'supportsAllEtablissements' => $supportsAll,
            'isAllEtablissements' => true,
            'effectiveEtablissementId' => null,
        ];
    }
}
