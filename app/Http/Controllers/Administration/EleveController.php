<?php

namespace App\Http\Controllers\Administration;

use App\Concerns\ScopesEtablissement;
use App\Http\Controllers\Controller;
use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\Inscription;
use App\Services\FinanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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
            new Middleware('can:inscriptions.reinscrire|inscriptions.manage', only: ['reinscrire', 'reinscrireLot', 'reinscriptionPage']),
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

        // Récupération de toutes les années scolaires
        $anneesScolaires = AnneeScolaire::orderByDesc('date_debut')->get();

        // Construction de la requête pour les inscriptions de l'année sélectionnée
        $inscriptionsQuery = Inscription::with([
            'eleve',
            'etablissement',
            'classe.niveau.cycle.section.etablissement',
        ]);

        if ($selectedAnneeId) {
            $inscriptionsQuery->where('annee_scolaire_id', $selectedAnneeId);
        }

        // Cloisonnement strict d'établissement :
        // Pour un utilisateur local (non super-admin), forcé sur son campus assigné.
        // Pour un super-administrateur, filtre optionnel selon la sélection.
        if (! $isSuperAdmin) {
            if ($scopedEtablissementId) {
                $inscriptionsQuery->where('etablissement_id', $scopedEtablissementId);
            }
        } elseif ($etablissementId = $request->integer('etablissement_id')) {
            $inscriptionsQuery->where('etablissement_id', $etablissementId);
        }

        // Filtre par Cycle
        if ($cycleId = $request->integer('cycle_id')) {
            $inscriptionsQuery->whereHas('classe.niveau', function ($q) use ($cycleId) {
                $q->where('cycle_id', $cycleId);
            });
        }

        // Filtre par Niveau
        if ($niveauId = $request->integer('niveau_id')) {
            $inscriptionsQuery->whereHas('classe', function ($q) use ($niveauId) {
                $q->where('niveau_id', $niveauId);
            });
        }

        // Filtre par Classe
        if ($classeId = $request->integer('classe_id')) {
            $inscriptionsQuery->where('classe_id', $classeId);
        }

        // Filtre par Statut d'inscription (validee, en_attente, suspendue, exclu, transfere)
        if ($statut = $request->input('statut')) {
            $inscriptionsQuery->where('statut', $statut);
        }

        // Filtre par Régime scolaire (nouveau vs redoublant)
        if ($request->has('is_redoublant') && $request->input('is_redoublant') !== null && $request->input('is_redoublant') !== '') {
            $inscriptionsQuery->where('is_redoublant', $request->boolean('is_redoublant'));
        }

        // Filtre par Sexe de l'élève
        if ($sexe = $request->input('sexe')) {
            $inscriptionsQuery->whereHas('eleve', function ($q) use ($sexe) {
                $q->where('sexe', $sexe);
            });
        }

        // Recherche textuelle par Nom, Prénom, Matricule, Téléphone
        if ($search = $request->input('search')) {
            $inscriptionsQuery->whereHas('eleve', function ($q) use ($search) {
                $q->search($search);
            });
        }

        // Liste finale ordonnée
        $inscriptions = $inscriptionsQuery->orderBy('classe_id')
            ->orderBy(
                Eleve::select('nom')
                    ->whereColumn('eleves.id', 'inscriptions.eleve_id')
            )
            ->get();

        // Calcul des statistiques globales pour l'année scolaire sélectionnée
        $statsBase = Inscription::where('annee_scolaire_id', $selectedAnneeId);
        if (! $isSuperAdmin) {
            if ($scopedEtablissementId) {
                $statsBase->where('etablissement_id', $scopedEtablissementId);
            }
        } elseif ($etablissementId = $request->integer('etablissement_id')) {
            $statsBase->where('etablissement_id', $etablissementId);
        }

        $totalInscrits = (clone $statsBase)->where('statut', 'validee')->count();
        $totalFilles = (clone $statsBase)->where('statut', 'validee')->whereHas('eleve', fn ($q) => $q->where('sexe', 'F'))->count();
        $totalGarcons = (clone $statsBase)->where('statut', 'validee')->whereHas('eleve', fn ($q) => $q->where('sexe', 'M'))->count();
        $totalRedoublants = (clone $statsBase)->where('statut', 'validee')->where('is_redoublant', true)->count();
        $totalEnAttente = (clone $statsBase)->where('statut', 'en_attente')->count();

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
            'inscriptions_manage' => $isSuperAdmin || $user?->can('inscriptions.manage'),
            'inscriptions_reinscrire' => $isSuperAdmin || $user?->can('inscriptions.reinscrire') || $user?->can('inscriptions.manage'),
            'inscriptions_transfer' => $isSuperAdmin || $user?->can('inscriptions.transfer') || $user?->can('inscriptions.manage'),
        ];

        return Inertia::render('administration/eleves/index', [
            'inscriptions' => $inscriptions,
            'anneesScolaires' => $anneesScolaires,
            'selectedAnneeId' => (int) $selectedAnneeId,
            'etablissements' => $etablissements,
            'stats' => [
                'totalInscrits' => $totalInscrits,
                'totalFilles' => $totalFilles,
                'totalGarcons' => $totalGarcons,
                'totalRedoublants' => $totalRedoublants,
                'totalEnAttente' => $totalEnAttente,
                'nonInscritsCount' => $nonInscritsCount,
            ],
            'filters' => [
                'etablissement_id' => ! $isSuperAdmin ? (string) $scopedEtablissementId : $request->input('etablissement_id', ''),
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
            'sexe' => ['required', 'in:M,F'],
            'date_naissance' => ['nullable', 'date'],
            'lieu_naissance' => ['nullable', 'string', 'max:150'],
            'nationalite' => ['nullable', 'string', 'max:80'],
            'photo' => ['nullable', 'image', 'max:1024'], // 1 Mo max

            // Filiation & Tuteurs
            'nom_pere' => ['nullable', 'string', 'max:150'],
            'nom_mere' => ['nullable', 'string', 'max:150'],
            'nom_tuteur' => ['nullable', 'string', 'max:150'],
            'telephone_parent' => ['required', 'string', 'max:50'],
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
            // Génération automatique du matricule interne unique s'il n'est pas fourni
            $matricule = ! empty($validated['matricule'])
                ? trim($validated['matricule'])
                : Eleve::generateNextMatricule($validated['annee_scolaire_id'] ?? null);

            // Gestion de l'upload de photo
            $photoUrl = null;
            if ($request->hasFile('photo')) {
                $path = $request->file('photo')->store('photos_eleves', 'public');
                $photoUrl = Storage::url($path);
            }

            // Création de l'Élève dans la Base Globale (champs alignés avec la vue create.tsx)
            $eleve = Eleve::create([
                'matricule' => $matricule,
                'matricule_national' => $validated['matricule_national'] ?? null,
                'nom' => strtoupper(trim($validated['nom'])),
                'prenom' => $validated['prenom'] ? trim($validated['prenom']) : null,
                'sexe' => $validated['sexe'],
                'date_naissance' => $validated['date_naissance'] ?? null,
                'lieu_naissance' => $validated['lieu_naissance'] ?? null,
                'nationalite' => $validated['nationalite'] ?: 'CAMEROUNAISE',
                'photo_url' => $photoUrl,
                'nom_pere' => $validated['nom_pere'] ?? null,
                'nom_mere' => $validated['nom_mere'] ?? null,
                'nom_tuteur' => $validated['nom_tuteur'] ?? null,
                'telephone_parent' => trim($validated['telephone_parent']),
                'email_parent' => $validated['email_parent'] ?? null,
                'adresse' => $validated['adresse'] ?? null,
                'is_active' => true,
            ]);

            // Résolution de l'établissement rattaché à la classe
            $classe = Classe::findOrFail($validated['classe_id']);
            $etablissementId = $classe->etablissement_id;

            $context = $this->resolveEtablissementContext($request);
            if (! $context['isSuperAdmin'] && (int) $etablissementId !== (int) $context['etablissementId']) {
                abort(403, 'Action non autorisée sur une classe d\'un autre établissement.');
            }

            // Création de l'inscription scolaire avec son identifiant d'établissement
            Inscription::create([
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
        });

        // Redirection selon l'action demandée (Enregistrer & Nouveau ou Enregistrer & Retourner)
        if ($request->input('_action') === 'save_and_new') {
            return redirect()->route('administration.eleves.create')->with('success', 'Élève enregistré et inscrit avec succès ! Vous pouvez saisir un nouvel élève.');
        }

        return redirect()->route('administration.eleves.index')->with('success', 'Élève enregistré et inscrit avec succès !');
    }

    /**
     * Met à jour les informations d'un élève dans la Base Globale.
     */
    public function update(Request $request, Eleve $eleve): RedirectResponse
    {
        $validated = $request->validate([
            'matricule' => ['required', 'string', 'max:50', 'unique:eleves,matricule,'.$eleve->id],
            'matricule_national' => ['nullable', 'string', 'max:50'],
            'nom' => ['required', 'string', 'max:150'],
            'prenom' => ['nullable', 'string', 'max:150'],
            'sexe' => ['required', 'in:M,F'],
            'date_naissance' => ['nullable', 'date'],
            'lieu_naissance' => ['nullable', 'string', 'max:150'],
            'nationalite' => ['nullable', 'string', 'max:80'],
            'photo' => ['nullable', 'image', 'max:1024'],
            'nom_pere' => ['nullable', 'string', 'max:150'],
            'nom_mere' => ['nullable', 'string', 'max:150'],
            'nom_tuteur' => ['nullable', 'string', 'max:150'],
            'telephone_parent' => ['required', 'string', 'max:50'],
            'email_parent' => ['nullable', 'email', 'max:150'],
            'adresse' => ['nullable', 'string', 'max:255'],
        ]);

        if ($request->hasFile('photo')) {
            $path = $request->file('photo')->store('photos_eleves', 'public');
            $validated['photo_url'] = Storage::url($path);
        }

        $validated['nom'] = strtoupper(trim($validated['nom']));
        $validated['prenom'] = $validated['prenom'] ? trim($validated['prenom']) : null;

        $eleve->update($validated);

        return back()->with('success', 'Fiche de l\'élève mise à jour avec succès !');
    }

    /**
     * Supprime un élève et ses inscriptions (ou le désactive).
     */
    public function destroy(Eleve $eleve): RedirectResponse
    {
        $nom = $eleve->nom_complet;
        $eleve->delete();

        return back()->with('success', "L'élève {$nom} a été supprimé avec succès.");
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

        // Vérifie si l'élève n'a pas déjà une inscription pour cette année
        $existing = Inscription::where('eleve_id', $validated['eleve_id'])
            ->where('annee_scolaire_id', $validated['annee_scolaire_id'])
            ->whereIn('statut', ['validee', 'en_attente'])
            ->first();

        if ($existing) {
            return back()->with('error', 'Cet élève dispose déjà d\'une inscription active pour cette année scolaire.');
        }

        $classe = Classe::findOrFail($validated['classe_id']);
        $etablissementId = $classe->etablissement_id;

        $context = $this->resolveEtablissementContext($request);
        if (! $context['isSuperAdmin'] && (int) $etablissementId !== (int) $context['etablissementId']) {
            abort(403, 'Action non autorisée sur une classe d\'un autre établissement.');
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
        if (! $context['isSuperAdmin'] && (int) $inscription->etablissement_id !== (int) $context['etablissementId']) {
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
     * Transfère un groupe d'élèves d'une classe source vers une classe cible (inter-classes ou intra-groupe).
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
        $targetEtablissementId = $targetClasse->etablissement_id ?? $targetClasse->niveau?->cycle?->section?->etablissement_id;
        $context = $this->resolveEtablissementContext($request);

        // Si non super-admin, vérifier que toutes les inscriptions sources appartiennent bien à son campus assigné
        if (! $context['isSuperAdmin']) {
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
            $inscriptions = Inscription::with('classe.niveau.cycle.section.etablissement')
                ->whereIn('id', $validated['inscription_ids'])
                ->get();

            foreach ($inscriptions as $oldInscription) {
                // Même classe : aucune action requise
                if ($oldInscription->classe_id == $targetClasse->id) {
                    continue;
                }

                $sameEtablissement = ($oldInscription->etablissement_id === $targetEtablissementId);

                if ($sameEtablissement) {
                    // Transfert interne au même établissement : changement direct de classe avec conservation de la scolarité
                    $oldInscription->update([
                        'classe_id' => $targetClasse->id,
                        'etablissement_id' => $targetEtablissementId,
                        'observations' => trim(($oldInscription->observations ?? '')."\nTransféré depuis {$oldInscription->classe?->nom} le ".now()->format('d/m/Y')),
                    ]);
                } else {
                    // Cadrage V1.1 : Transfert intra-groupe vers un autre établissement
                    // 1. Clôture de l'inscription active d'origine
                    $oldInscription->update([
                        'statut' => 'transfere',
                        'motif_sortie' => "Transféré vers {$targetClasse->nom} : {$motif}",
                    ]);

                    // 2. Création de la nouvelle inscription dans l'établissement cible
                    Inscription::create([
                        'eleve_id' => $oldInscription->eleve_id,
                        'classe_id' => $targetClasse->id,
                        'etablissement_id' => $targetEtablissementId,
                        'annee_scolaire_id' => $oldInscription->annee_scolaire_id,
                        'type_inscription' => 'nouveau',
                        'is_redoublant' => $oldInscription->is_redoublant,
                        'statut' => 'validee',
                        'date_inscription' => now()->toDateString(),
                        'etablissement_provenance' => $oldInscription->etablissement?->nom ?? $oldInscription->classe?->niveau?->cycle?->section?->etablissement?->nom ?? null,
                        'observations' => "Transfert intra-groupe : {$motif}",
                    ]);
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

        $targetClasse = Classe::findOrFail($validated['classe_id']);
        $context = $this->resolveEtablissementContext($request);
        if (! $context['isSuperAdmin'] && (int) $targetClasse->etablissement_id !== (int) $context['etablissementId']) {
            abort(403, 'Action non autorisée sur une classe d\'un autre établissement.');
        }

        $file = $request->file('fichier');
        // Nettoyage de l'éventuel BOM UTF-8 (courant dans les fichiers générés par Excel)
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $file->getContent());
        $lines = explode("\n", str_replace("\r", '', $content));

        $imported = 0;
        $ignored = 0;
        $ignorerDoublons = $request->boolean('ignorer_doublons', true);

        DB::transaction(function () use ($lines, $validated, $ignorerDoublons, &$imported, &$ignored) {
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
                $sexe = ! empty($cols[4]) && in_array(strtoupper($cols[4]), ['M', 'F']) ? strtoupper($cols[4]) : 'M';
                $dateNaissRaw = ! empty($cols[5]) && $cols[5] !== '\N' ? $cols[5] : null;
                $lieuNaiss = ! empty($cols[6]) && $cols[6] !== '\N' ? $cols[6] : null;
                $isRedoublant = ! empty($cols[7]) && in_array(strtoupper($cols[7]), ['O', 'OUI', '1', 'TRUE']);
                $telParent = ! empty($cols[8]) && $cols[8] !== '\N' ? $cols[8] : '+237 600 00 00 00';
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

                // Vérification des doublons par matricule ou (nom + prénom)
                if ($matricule && Eleve::where('matricule', $matricule)->exists()) {
                    if ($ignorerDoublons) {
                        $ignored++;

                        continue;
                    }
                    $matricule = Eleve::generateNextMatricule($validated['annee_scolaire_id'] ?? null);
                }

                if (! $matricule) {
                    $matricule = Eleve::generateNextMatricule($validated['annee_scolaire_id'] ?? null);
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

                $targetClasse = Classe::find($validated['classe_id']);
                $etablissementId = $targetClasse?->etablissement_id;

                // Création de l'inscription avec son établissement
                Inscription::create([
                    'eleve_id' => $eleve->id,
                    'classe_id' => $validated['classe_id'],
                    'etablissement_id' => $etablissementId,
                    'annee_scolaire_id' => $validated['annee_scolaire_id'],
                    'type_inscription' => 'nouveau',
                    'is_redoublant' => $isRedoublant,
                    'statut' => 'validee',
                    'date_inscription' => now()->toDateString(),
                ]);

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

        $eleves = Eleve::search($q)
            ->whereDoesntHave('inscriptions', function ($sub) use ($anneeId) {
                if ($anneeId) {
                    $sub->where('annee_scolaire_id', $anneeId)
                        ->whereIn('statut', ['validee', 'en_attente']);
                }
            })
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
        $isSuperAdmin = $context['isSuperAdmin'];

        $inscriptionsQuery = Inscription::with([
            'eleve',
            'etablissement',
            'classe.niveau',
        ]);

        if ($selectedAnneeId) {
            $inscriptionsQuery->where('annee_scolaire_id', $selectedAnneeId);
        }

        if (! $isSuperAdmin) {
            if ($scopedEtablissementId) {
                $inscriptionsQuery->where('etablissement_id', $scopedEtablissementId);
            }
        } elseif ($etablissementId = $request->integer('etablissement_id')) {
            $inscriptionsQuery->where('etablissement_id', $etablissementId);
        }

        if ($classeId = $request->integer('classe_id')) {
            $inscriptionsQuery->where('classe_id', $classeId);
        }

        if ($statut = $request->input('statut')) {
            $inscriptionsQuery->where('statut', $statut);
        }

        if ($sexe = $request->input('sexe')) {
            $inscriptionsQuery->whereHas('eleve', function ($q) use ($sexe) {
                $q->where('sexe', $sexe);
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

            foreach ($inscriptions as $insc) {
                $el = $insc->eleve;
                fputcsv($handle, [
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
                ], ';');
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
        if (! $context['isSuperAdmin'] && (int) $etablissementId !== (int) $context['etablissementId']) {
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

                // Ne pas réinscrire un élève déjà actif sur la nouvelle année
                $existing = Inscription::where('eleve_id', $eleveId)
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
}
