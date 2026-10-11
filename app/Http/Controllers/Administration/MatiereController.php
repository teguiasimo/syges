<?php

namespace App\Http\Controllers\Administration;

use App\Concerns\ScopesEtablissement;
use App\Http\Controllers\Controller;
use App\Models\Etablissement;
use App\Models\GroupeMatiere;
use App\Models\Matiere;
use App\Models\MatiereNiveau;
use App\Models\Niveau;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Contrôleur d'administration du Catalogue des Matières et de la Grille des Coefficients.
 *
 * Implémente le Module 4 du cahier des charges Gesco :
 * - 4.1 Groupes de Matières (Blocs Pédagogiques ordonnés pour la maquette officielle du bulletin MINESEC/MINEDUB).
 * - 4.2 Catalogue Général des Matières (Discipline, codes officiels, bilinguisme FR/EN).
 * - 4.3 Configuration des Coefficients (Poids académique par Niveau + Série, note max, note éliminatoire, facultatif/bonus).
 */
class MatiereController extends Controller implements HasMiddleware
{
    use ScopesEtablissement;
    /**
     * Définition de la matrice des middlewares d'autorisation pour le module Matières & Coefficients.
     *
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:matieres.view', only: ['index']),
            new Middleware('can:groupes_matieres.create|matieres.manage', only: ['storeGroupe']),
            new Middleware('can:groupes_matieres.edit|matieres.manage', only: ['updateGroupe']),
            new Middleware('can:groupes_matieres.delete|matieres.manage', only: ['destroyGroupe']),
            new Middleware('can:matieres.create|matieres.manage', only: ['storeMatiere', 'seedStandards']),
            new Middleware('can:matieres.edit|matieres.manage', only: ['updateMatiere']),
            new Middleware('can:matieres.delete|matieres.manage', only: ['destroyMatiere']),
            new Middleware('can:coefficients.edit|matieres.manage', only: ['storeCoefficient', 'updateCoefficient', 'destroyCoefficient']),
            new Middleware('can:coefficients.duplicate|coefficients.edit|matieres.manage', only: ['duplicateCoefficients']),
        ];
    }
    /**
     * Affiche l'interface de gestion des matières, groupes et coefficients.
     * Permet le filtrage contextuel par Établissement scolaire maître et Niveau d'étude.
     *
     * @param Request $request Requête HTTP entrante avec filtres optionnels (etablissement_id, niveau_id, serie).
     * @return Response Vue Inertia avec les données du catalogue et de la grille.
     */
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('matieres.view'), 403, 'Accès non autorisé au catalogue des matières.');

        // 1. Récupération hermétique des campus autorisés et de l'établissement actif
        $context = $this->resolveEtablissementContext($request);
        $etablissements = $context['etablissements'];
        $selectedEtablissement = $context['selectedEtablissement'];
        $selectedEtablissementId = $selectedEtablissement?->id;

        // 2. Groupes de matières (blocs pédagogiques) rattachés à l'établissement ou standards/partagés
        $groupesQuery = GroupeMatiere::withCount('matiereNiveaux')
            ->with('etablissement:id,nom,code')
            ->orderBy('ordre');

        if ($selectedEtablissementId) {
            $groupesQuery->where(function ($q) use ($selectedEtablissementId) {
                $q->where('etablissement_id', $selectedEtablissementId)
                    ->orWhereNull('etablissement_id');
            });
        }

        $groupes = $groupesQuery->get();

        // 3. Catalogue des disciplines disponibles pour cet établissement (tronc commun + spécifiques)
        $matieresQuery = Matiere::withCount('matiereNiveaux')
            ->with('etablissement:id,nom,code')
            ->orderBy('code');

        if ($selectedEtablissementId) {
            $matieresQuery->where(function ($q) use ($selectedEtablissementId) {
                $q->where('etablissement_id', $selectedEtablissementId)
                    ->orWhereNull('etablissement_id');
            });
        }

        $matieres = $matieresQuery->get();

        // 4. Niveaux scolaires appartenant à cet établissement pour le sélecteur
        $niveauxQuery = Niveau::with(['cycle.section.etablissement', 'etablissement'])
            ->withCount('matiereNiveaux')
            ->orderBy('ordre');

        if ($selectedEtablissementId) {
            $niveauxQuery->where(function ($q) use ($selectedEtablissementId) {
                $q->where('etablissement_id', $selectedEtablissementId)
                    ->orWhereHas('cycle.section', function ($sq) use ($selectedEtablissementId) {
                        $sq->where('etablissement_id', $selectedEtablissementId);
                    });
            });
        }

        $niveaux = $niveauxQuery->get();

        // Niveau sélectionné (par défaut le premier niveau disponible dans l'établissement actif)
        $selectedNiveauId = $request->query('niveau_id');
        $selectedNiveau = $selectedNiveauId ? $niveaux->firstWhere('id', (int) $selectedNiveauId) : $niveaux->first();

        if (! $selectedNiveau && $niveaux->isNotEmpty()) {
            $selectedNiveau = $niveaux->first();
        }

        // 5. Séries disponibles pour ce niveau (extraites des classes du niveau ou des coefficients déjà paramétrés)
        // Les mentions génériques comme 'GENERAL' ou 'TRONC COMMUN' sont assimilées au tronc commun universel ('Toutes').
        $seriesDisponibles = collect(['Toutes']);
        if ($selectedNiveau) {
            $seriesFromClasses = $selectedNiveau->classes()->whereNotNull('serie')->pluck('serie')->unique()->values();
            $seriesFromCoefficients = MatiereNiveau::where('niveau_id', $selectedNiveau->id)->pluck('serie')->unique()->values();

            // Liste des termes considérés comme synonymes du tronc commun sans filière différenciée
            $genericSeries = ['GENERAL', 'Général', 'General', 'TRONC COMMUN', 'Tronc Commun', 'TC', 'Toutes', 'TOUTES', ''];
            
            // Extraction des véritables filières spécialisées (ex: 'C', 'D', 'A4', 'TI'...)
            $distinctSeries = $seriesFromClasses->merge($seriesFromCoefficients)
                ->reject(fn($s) => in_array(trim((string) $s), $genericSeries, true))
                ->unique()
                ->values();

            // Si le niveau possède des filières spécialisées distinctes, on les propose à côté de 'Toutes'
            if ($distinctSeries->isNotEmpty()) {
                $seriesDisponibles = collect(['Toutes'])->merge($distinctSeries)->values();
            }
        }

        // Récupération de la série demandée, avec repli automatique sur 'Toutes' si la série est générique ou introuvable
        $selectedSerie = $request->query('serie', 'Toutes');
        if (! $seriesDisponibles->contains($selectedSerie)) {
            $selectedSerie = 'Toutes';
        }

        // 6. Coefficients configurés pour le niveau sélectionné
        $coefficients = collect();
        if ($selectedNiveau) {
            $query = MatiereNiveau::with(['matiere', 'groupeMatiere'])
                ->where('niveau_id', $selectedNiveau->id);

            if ($selectedSerie && $selectedSerie !== 'Toutes') {
                $query->where(function ($q) use ($selectedSerie) {
                    $q->where('serie', $selectedSerie)->orWhere('serie', 'Toutes');
                });
            }

            $coefficients = $query->get()->sort(function ($a, $b) use ($selectedSerie) {
                $ordreA = $a->groupeMatiere ? $a->groupeMatiere->getOrdrePourSerie($selectedSerie) : 99;
                $ordreB = $b->groupeMatiere ? $b->groupeMatiere->getOrdrePourSerie($selectedSerie) : 99;
                if ($ordreA === $ordreB) {
                    return strcmp($a->matiere?->code ?? '', $b->matiere?->code ?? '');
                }
                return $ordreA <=> $ordreB;
            })->values();
        }

        $user = $request->user();
        $isSuperAdmin = $user ? ($user->hasRole('super-admin') || $user->email === 'admin@gmail.com') : false;
        $canManage = $user ? ($isSuperAdmin || $user->hasRole('admin') || $user->can('matieres.manage')) : false;

        $can = [
            'matieres_view' => $isSuperAdmin || $user?->can('matieres.view'),
            'matieres_manage' => $canManage,
            'matieres_create' => $canManage || $user?->can('matieres.create'),
            'matieres_edit' => $canManage || $user?->can('matieres.edit'),
            'matieres_delete' => $isSuperAdmin || $user?->can('matieres.delete'),
            'groupes_create' => $canManage || $user?->can('groupes_matieres.create'),
            'groupes_edit' => $canManage || $user?->can('groupes_matieres.edit'),
            'groupes_delete' => $isSuperAdmin || $user?->can('groupes_matieres.delete'),
            'coefficients_edit' => $canManage || $user?->can('coefficients.edit'),
            'coefficients_duplicate' => $canManage || $user?->can('coefficients.duplicate') || $user?->can('coefficients.edit'),
        ];

        return Inertia::render('administration/matieres/index', [
            'etablissements' => $etablissements,
            'selectedEtablissement' => $selectedEtablissement,
            'selectedEtablissementId' => $selectedEtablissementId,
            'groupes' => $groupes,
            'matieres' => $matieres,
            'niveaux' => $niveaux,
            'selectedNiveau' => $selectedNiveau,
            'seriesDisponibles' => $seriesDisponibles,
            'selectedSerie' => $selectedSerie,
            'coefficients' => $coefficients,
            'can' => $can,
        ]);
    }

    // =========================================================================
    // SECTION 1 : CRUD GROUPES DE MATIÈRES (BLOCS PÉDAGOGIQUES)
    // =========================================================================

    /**
     * Enregistre un nouveau groupe de matières (bloc pédagogique).
     * Associe le bloc à l'établissement actif ou en paramètre avec unicité locale.
     *
     * @param Request $request Requête HTTP avec données validées.
     * @return RedirectResponse Redirection avec message de confirmation.
     */
    public function storeGroupe(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('matieres.manage'), 403, 'Permission requise : matieres.manage');

        $etablissementId = $request->input('etablissement_id');

        $validated = $request->validate([
            'etablissement_id' => ['nullable', 'exists:etablissements,id'],
            'code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('groupes_matieres', 'code')->where('etablissement_id', $etablissementId),
            ],
            'nom' => ['required', 'string', 'max:120'],
            'nom_en' => ['nullable', 'string', 'max:120'],
            'couleur' => ['nullable', 'string', 'max:30'],
            'ordre' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        GroupeMatiere::create($validated);

        return back()->with('success', 'Bloc pédagogique créé avec succès.');
    }

    /**
     * Met à jour un groupe de matières existant.
     *
     * @param Request $request Requête HTTP avec données validées.
     * @param GroupeMatiere $groupe Instance du groupe à modifier.
     * @return RedirectResponse Redirection avec message de confirmation.
     */
    public function updateGroupe(Request $request, GroupeMatiere $groupe): RedirectResponse
    {
        abort_unless($request->user()->can('matieres.manage'), 403, 'Permission requise : matieres.manage');

        $etablissementId = $request->has('etablissement_id') ? $request->input('etablissement_id') : $groupe->etablissement_id;

        $validated = $request->validate([
            'etablissement_id' => ['nullable', 'exists:etablissements,id'],
            'code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('groupes_matieres', 'code')
                    ->where('etablissement_id', $etablissementId)
                    ->ignore($groupe->id),
            ],
            'nom' => ['required', 'string', 'max:120'],
            'nom_en' => ['nullable', 'string', 'max:120'],
            'couleur' => ['nullable', 'string', 'max:30'],
            'ordre' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $groupe->update($validated);

        return back()->with('success', 'Bloc pédagogique mis à jour avec succès.');
    }

    /**
     * Supprime un groupe de matières s'il n'est rattaché à aucun coefficient.
     *
     * @param Request $request Requête HTTP.
     * @param GroupeMatiere $groupe Instance du groupe à supprimer.
     * @return RedirectResponse Redirection avec statut de l'opération.
     */
    public function destroyGroupe(Request $request, GroupeMatiere $groupe): RedirectResponse
    {
        abort_unless($request->user()->can('matieres.manage'), 403, 'Permission requise : matieres.manage');

        if ($groupe->matiereNiveaux()->exists()) {
            return back()->with('error', 'Impossible de supprimer ce bloc car des coefficients de matières lui sont actuellement associés.');
        }

        $groupe->delete();

        return back()->with('success', 'Bloc pédagogique supprimé avec succès.');
    }

    // =========================================================================
    // SECTION 2 : CRUD CATALOGUE GÉNÉRAL DES MATIÈRES
    // =========================================================================

    /**
     * Enregistre une nouvelle discipline dans le catalogue général (spécifique ou tronc commun).
     *
     * @param Request $request Requête HTTP avec données validées.
     * @return RedirectResponse Redirection avec message de confirmation.
     */
    public function storeMatiere(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('matieres.manage'), 403, 'Permission requise : matieres.manage');

        $etablissementId = $request->input('etablissement_id');

        $validated = $request->validate([
            'etablissement_id' => ['nullable', 'exists:etablissements,id'],
            'code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('matieres', 'code')->where('etablissement_id', $etablissementId),
            ],
            'nom' => ['required', 'string', 'max:120'],
            'nom_en' => ['nullable', 'string', 'max:120'],
            'libelle_bulletin' => ['nullable', 'string', 'max:100'],
            'libelle_bulletin_en' => ['nullable', 'string', 'max:100'],
            'is_active' => ['boolean'],
        ]);

        if (empty($validated['libelle_bulletin'])) {
            $validated['libelle_bulletin'] = $validated['nom'];
        }

        if (empty($validated['libelle_bulletin_en']) && ! empty($validated['nom_en'])) {
            $validated['libelle_bulletin_en'] = $validated['nom_en'];
        }

        Matiere::create($validated);

        return back()->with('success', 'Matière ajoutée au catalogue avec succès.');
    }

    /**
     * Met à jour les informations d'une discipline du catalogue.
     *
     * @param Request $request Requête HTTP avec données validées.
     * @param Matiere $matiere Instance de la matière à modifier.
     * @return RedirectResponse Redirection avec message de confirmation.
     */
    public function updateMatiere(Request $request, Matiere $matiere): RedirectResponse
    {
        abort_unless($request->user()->can('matieres.manage'), 403, 'Permission requise : matieres.manage');

        $etablissementId = $request->has('etablissement_id') ? $request->input('etablissement_id') : $matiere->etablissement_id;

        $validated = $request->validate([
            'etablissement_id' => ['nullable', 'exists:etablissements,id'],
            'code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('matieres', 'code')
                    ->where('etablissement_id', $etablissementId)
                    ->ignore($matiere->id),
            ],
            'nom' => ['required', 'string', 'max:120'],
            'nom_en' => ['nullable', 'string', 'max:120'],
            'libelle_bulletin' => ['nullable', 'string', 'max:100'],
            'libelle_bulletin_en' => ['nullable', 'string', 'max:100'],
            'is_active' => ['boolean'],
        ]);

        if (empty($validated['libelle_bulletin'])) {
            $validated['libelle_bulletin'] = $validated['nom'];
        }

        if (empty($validated['libelle_bulletin_en']) && ! empty($validated['nom_en'])) {
            $validated['libelle_bulletin_en'] = $validated['nom_en'];
        }

        $matiere->update($validated);

        return back()->with('success', 'Matière mise à jour avec succès.');
    }

    /**
     * Supprime une matière du catalogue si elle n'est configurée dans aucun niveau.
     *
     * @param Request $request Requête HTTP.
     * @param Matiere $matiere Instance de la matière à supprimer.
     * @return RedirectResponse Redirection avec statut de l'opération.
     */
    public function destroyMatiere(Request $request, Matiere $matiere): RedirectResponse
    {
        abort_unless($request->user()->can('matieres.manage'), 403, 'Permission requise : matieres.manage');

        if ($matiere->matiereNiveaux()->exists()) {
            return back()->with('error', 'Impossible de supprimer cette matière car des coefficients de niveaux lui sont actuellement associés.');
        }

        $matiere->delete();

        return back()->with('success', 'Matière supprimée du catalogue avec succès.');
    }

    // =========================================================================
    // SECTION 3 : CRUD CONFIGURATION DES COEFFICIENTS PAR NIVEAU & SÉRIE
    // =========================================================================

    /**
     * Duplique la grille de coefficients depuis un niveau source vers un niveau cible (Option 1 : Compléter les matières manquantes).
     *
     * Conserve fidèlement et sans écrasement les matières et pondérations déjà configurées pour le niveau cible,
     * et insère uniquement les matières présentes dans le niveau source qui n'existent pas encore dans le niveau cible.
     *
     * @param Request $request Requête HTTP contenant source_niveau_id, target_niveau_id, target_serie et scope.
     * @return RedirectResponse Redirection avec message récapitulatif de l'opération.
     */
    public function duplicateCoefficients(Request $request): RedirectResponse
    {
        $user = $request->user();
        $isAuthorized = $user && (
            $user->hasRole('super-admin') ||
            $user->email === 'admin@gmail.com' ||
            $user->hasRole('admin') ||
            $user->can('coefficients.duplicate') ||
            $user->can('coefficients.edit') ||
            $user->can('matieres.manage')
        );
        abort_unless($isAuthorized, 403, 'Permission requise : coefficients.duplicate');

        $validated = $request->validate([
            'source_niveau_id' => ['required', 'integer', 'exists:niveaux,id', 'different:target_niveau_id'],
            'target_niveau_id' => ['required', 'integer', 'exists:niveaux,id'],
            'target_serie' => ['nullable', 'string', 'max:50'],
            'scope' => ['nullable', 'string', 'in:active_serie,all_series'],
        ]);

        $sourceNiveau = Niveau::with('cycle.section')->findOrFail($validated['source_niveau_id']);
        $targetNiveau = Niveau::with('cycle.section')->findOrFail($validated['target_niveau_id']);

        // Résolution hermétique du contexte multi-tenant (SaaS)
        $context = $this->resolveEtablissementContext($request);
        $effectiveEtabId = $context['etablissementId'];
        $isSuperAdmin = $context['isSuperAdmin'];

        $sourceEtabId = $sourceNiveau->etablissement_id ?? $sourceNiveau->cycle?->section?->etablissement_id;
        $targetEtabId = $targetNiveau->etablissement_id ?? $targetNiveau->cycle?->section?->etablissement_id;

        // 1. Cloisonnement strict SaaS : Interdiction formelle de dupliquer entre deux campus distincts
        if ($sourceEtabId && $targetEtabId && (int) $sourceEtabId !== (int) $targetEtabId) {
            abort(403, 'Cloisonnement SaaS : La duplication de grille ne peut s\'effectuer qu\'au sein d\'un même établissement scolaire.');
        }

        // 2. Cloisonnement local : Un utilisateur assigné à un campus ne peut opérer hors de son établissement
        if (! $isSuperAdmin && $effectiveEtabId) {
            if ((int) $targetEtabId !== (int) $effectiveEtabId || (int) $sourceEtabId !== (int) $effectiveEtabId) {
                abort(403, 'Accès non autorisé : vous ne pouvez dupliquer que les grilles de votre propre établissement scolaire.');
            }
        }

        $targetSerie = $validated['target_serie'] ?? 'Toutes';
        $genericSeries = ['GENERAL', 'GÉNÉRAL', 'GENERAL', 'TRONC COMMUN', 'TC', 'TOUTES', ''];
        $serieInput = strtoupper(trim((string) $targetSerie));
        $normalizedTargetSerie = in_array($serieInput, $genericSeries, true) ? 'Toutes' : ($targetSerie ?: 'Toutes');

        $scope = $validated['scope'] ?? 'all_series';

        // 1. Récupération des coefficients configurés sur le niveau source
        $sourceQuery = MatiereNiveau::where('niveau_id', $sourceNiveau->id);

        if ($scope === 'active_serie' && $normalizedTargetSerie !== 'Toutes') {
            // Filtrer les matières source spécifiques à la filière active ou issues du tronc commun universel ('Toutes')
            $sourceQuery->where(function ($q) use ($normalizedTargetSerie) {
                $q->where('serie', $normalizedTargetSerie)
                  ->orWhere('serie', 'Toutes');
            });
        }

        $sourceCoefficients = $sourceQuery->get();

        if ($sourceCoefficients->isEmpty()) {
            return back()->with('error', "Le niveau source « {$sourceNiveau->nom} » ne contient aucune matière configurée à dupliquer.");
        }

        // 2. Traitement transactionnel de la copie selon le Mode Option 1 (Non destructif)
        $insertedCount = 0;
        $retainedCount = 0;

        DB::transaction(function () use (
            $sourceCoefficients,
            $targetNiveau,
            $normalizedTargetSerie,
            $scope,
            &$insertedCount,
            &$retainedCount
        ) {
            foreach ($sourceCoefficients as $coeff) {
                // Détermination de la série sur le niveau cible
                $destSerie = ($scope === 'active_serie' && $coeff->serie !== 'Toutes')
                    ? $normalizedTargetSerie
                    : ($coeff->serie ?: 'Toutes');

                // Vérifier si cette matière existe déjà pour ce niveau et cette série
                $exists = MatiereNiveau::where('niveau_id', $targetNiveau->id)
                    ->where('matiere_id', $coeff->matiere_id)
                    ->where('serie', $destSerie)
                    ->exists();

                if ($exists) {
                    // Option 1 (Recommandée) : Ne jamais écraser l'existant, on conserve intact
                    $retainedCount++;
                    continue;
                }

                // Insertion de la matière manquante avec ses coefficients et paramètres d'évaluation
                MatiereNiveau::create([
                    'niveau_id' => $targetNiveau->id,
                    'matiere_id' => $coeff->matiere_id,
                    'groupe_matiere_id' => $coeff->groupe_matiere_id,
                    'serie' => $destSerie,
                    'coefficient' => $coeff->coefficient,
                    'note_max' => $coeff->note_max,
                    'note_eliminatoire' => $coeff->note_eliminatoire,
                    'is_facultative' => $coeff->is_facultative,
                    'is_comptabilisee' => $coeff->is_comptabilisee,
                ]);

                $insertedCount++;
            }
        });

        // 3. Message récapitulatif adapté
        if ($insertedCount === 0 && $retainedCount > 0) {
            return back()->with('info', "Toutes les matières du niveau source « {$sourceNiveau->nom} » sont déjà configurées sur « {$targetNiveau->nom} » ({$retainedCount} matière(s) conservée(s) sans modification).");
        }

        $message = "Duplication réussie : {$insertedCount} matière(s) ajoutée(s) à la grille de « {$targetNiveau->nom} » depuis « {$sourceNiveau->nom} »";
        if ($retainedCount > 0) {
            $message .= " ({$retainedCount} matière(s) déjà existante(s) conservée(s) sans écrasement)";
        }
        $message .= '.';

        return back()->with('success', $message);
    }

    /**
     * Associe une matière à un niveau avec son coefficient et ses paramètres d'évaluation.
     *
     * @param Request $request Requête HTTP avec données validées.
     * @return RedirectResponse Redirection avec message de confirmation.
     */
    public function storeCoefficient(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('matieres.manage'), 403, 'Permission requise : matieres.manage');

        $validated = $request->validate([
            'niveau_id' => ['required', 'exists:niveaux,id'],
            'matiere_id' => ['required', 'exists:matieres,id'],
            'groupe_matiere_id' => ['required', 'exists:groupes_matieres,id'],
            'serie' => ['nullable', 'string', 'max:50'],
            'coefficient' => ['required', 'numeric', 'min:0.25', 'max:20'],
            'note_max' => ['required', 'numeric', 'min:1', 'max:100'],
            'note_eliminatoire' => ['nullable', 'numeric', 'min:0', 'max:20'],
            'is_facultative' => ['boolean'],
            'is_comptabilisee' => ['boolean'],
        ]);

        // Vérification hermétique de cloisonnement SaaS (Multi-Tenant)
        $niveau = Niveau::with('cycle.section')->findOrFail($validated['niveau_id']);
        $context = $this->resolveEtablissementContext($request);
        $effectiveEtabId = $context['etablissementId'];
        $isSuperAdmin = $context['isSuperAdmin'];
        $niveauEtabId = $niveau->etablissement_id ?? $niveau->cycle?->section?->etablissement_id;

        if (! $isSuperAdmin && $effectiveEtabId && (int) $niveauEtabId !== (int) $effectiveEtabId) {
            abort(403, 'Accès non autorisé : vous ne pouvez modifier que les coefficients de votre propre établissement scolaire.');
        }

        // Standardisation de la série : les valeurs génériques sont automatiquement normalisées en 'Toutes'
        $genericSeries = ['GENERAL', 'GÉNÉRAL', 'GENERAL', 'TRONC COMMUN', 'TC', 'TOUTES', ''];
        $serieInput = strtoupper(trim((string) ($validated['serie'] ?? '')));
        $validated['serie'] = in_array($serieInput, $genericSeries, true) ? 'Toutes' : ($validated['serie'] ?: 'Toutes');

        // Unicité de la matière pour ce niveau et cette série
        $exists = MatiereNiveau::where('niveau_id', $validated['niveau_id'])
            ->where('matiere_id', $validated['matiere_id'])
            ->where('serie', $validated['serie'])
            ->exists();

        if ($exists) {
            return back()->withErrors(['matiere_id' => 'Cette matière est déjà configurée pour ce niveau et cette série.']);
        }

        MatiereNiveau::create($validated);

        return back()->with('success', 'Discipline ajoutée à la grille du niveau avec succès.');
    }

    /**
     * Met à jour les coefficients et règles d'évaluation d'une matière dans un niveau.
     *
     * @param Request $request Requête HTTP avec données validées.
     * @param MatiereNiveau $coefficient Instance de l'association à modifier.
     * @return RedirectResponse Redirection avec message de confirmation.
     */
    public function updateCoefficient(Request $request, MatiereNiveau $coefficient): RedirectResponse
    {
        abort_unless($request->user()->can('matieres.manage'), 403, 'Permission requise : matieres.manage');

        // Vérification hermétique de cloisonnement SaaS (Multi-Tenant)
        $context = $this->resolveEtablissementContext($request);
        $effectiveEtabId = $context['etablissementId'];
        $isSuperAdmin = $context['isSuperAdmin'];
        $niveau = $coefficient->niveau()->with('cycle.section')->first();
        $niveauEtabId = $niveau?->etablissement_id ?? $niveau?->cycle?->section?->etablissement_id;

        if (! $isSuperAdmin && $effectiveEtabId && (int) $niveauEtabId !== (int) $effectiveEtabId) {
            abort(403, 'Accès non autorisé : vous ne pouvez modifier que les coefficients de votre propre établissement scolaire.');
        }

        $validated = $request->validate([
            'groupe_matiere_id' => ['required', 'exists:groupes_matieres,id'],
            'serie' => ['nullable', 'string', 'max:50'],
            'coefficient' => ['required', 'numeric', 'min:0.25', 'max:20'],
            'note_max' => ['required', 'numeric', 'min:1', 'max:100'],
            'note_eliminatoire' => ['nullable', 'numeric', 'min:0', 'max:20'],
            'is_facultative' => ['boolean'],
            'is_comptabilisee' => ['boolean'],
        ]);

        // Standardisation de la série à la mise à jour
        $genericSeries = ['GENERAL', 'GÉNÉRAL', 'GENERAL', 'TRONC COMMUN', 'TC', 'TOUTES', ''];
        $serieInput = strtoupper(trim((string) ($validated['serie'] ?? '')));
        $validated['serie'] = in_array($serieInput, $genericSeries, true) ? 'Toutes' : ($validated['serie'] ?: 'Toutes');

        $coefficient->update($validated);

        return back()->with('success', 'Coefficient mis à jour avec succès.');
    }

    /**
     * Supprime une matière de la grille d'évaluation d'un niveau.
     *
     * @param Request $request Requête HTTP.
     * @param MatiereNiveau $coefficient Instance de l'association à supprimer.
     * @return RedirectResponse Redirection avec message de confirmation.
     */
    public function destroyCoefficient(Request $request, MatiereNiveau $coefficient): RedirectResponse
    {
        abort_unless($request->user()->can('matieres.manage'), 403, 'Permission requise : matieres.manage');

        // Vérification hermétique de cloisonnement SaaS (Multi-Tenant)
        $context = $this->resolveEtablissementContext($request);
        $effectiveEtabId = $context['etablissementId'];
        $isSuperAdmin = $context['isSuperAdmin'];
        $niveau = $coefficient->niveau()->with('cycle.section')->first();
        $niveauEtabId = $niveau?->etablissement_id ?? $niveau?->cycle?->section?->etablissement_id;

        if (! $isSuperAdmin && $effectiveEtabId && (int) $niveauEtabId !== (int) $effectiveEtabId) {
            abort(403, 'Accès non autorisé : vous ne pouvez modifier que les coefficients de votre propre établissement scolaire.');
        }

        $coefficient->delete();

        return back()->with('success', 'Matière retirée de la grille d\'évaluation.');
    }

    /**
     * Initialise les matières et blocs standards officiels adaptés à l'établissement.
     *
     * @param Request $request Requête HTTP.
     * @return RedirectResponse Redirection avec message de succès.
     */
    public function seedStandards(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('matieres.manage'), 403, 'Permission requise : matieres.manage');

        $etablissementId = $request->input('etablissement_id') ?? $request->query('etablissement_id');
        $etablissement = $etablissementId ? Etablissement::find($etablissementId) : Etablissement::first();
        $targetEtabId = $etablissement?->id;

        // 1. Blocs pédagogiques adaptés selon l'ordre d'enseignement et la langue de l'établissement
        $isPrimary = $etablissement && in_array($etablissement->ordre_enseignement, ['Maternelle', 'Primaire']);
        $isTechnical = $etablissement && $etablissement->ordre_enseignement === 'Secondaire_Technique';

        if ($isPrimary) {
            $blocs = [
                ['code' => 'DOM-LANG', 'nom' => 'Domaine 1 : Langues et Communication', 'couleur' => 'blue', 'ordre' => 1],
                ['code' => 'DOM-SCI', 'nom' => 'Domaine 2 : Sciences et Technologies', 'couleur' => 'emerald', 'ordre' => 2],
                ['code' => 'DOM-HUM', 'nom' => 'Domaine 3 : Sciences Humaines et Sociales', 'couleur' => 'amber', 'ordre' => 3],
                ['code' => 'DOM-DEV', 'nom' => 'Domaine 4 : Développement Personnel & Pratique', 'couleur' => 'purple', 'ordre' => 4],
            ];
        } elseif ($isTechnical) {
            $blocs = [
                ['code' => 'BLOC-GEN', 'nom' => 'Pôle Enseignements Généraux', 'couleur' => 'blue', 'ordre' => 1],
                ['code' => 'BLOC-SCI', 'nom' => 'Pôle Enseignements Scientifiques & Technologiques', 'couleur' => 'emerald', 'ordre' => 2],
                ['code' => 'BLOC-PRO', 'nom' => 'Pôle Professionnel & Pratiques d\'Atelier', 'couleur' => 'amber', 'ordre' => 3],
            ];
        } else {
            // Secondaire Général : 3 blocs fondamentaux (Scientifiques, Littéraires, Complémentaires)
            $blocs = [
                ['code' => 'BLOC-SCI', 'nom' => 'Scientifiques', 'couleur' => 'blue', 'ordre' => 1],
                ['code' => 'BLOC-LIT', 'nom' => 'Littéraires', 'couleur' => 'emerald', 'ordre' => 2],
                ['code' => 'BLOC-COMP', 'nom' => 'Complémentaires', 'couleur' => 'amber', 'ordre' => 3],
            ];
        }

        // Migration douce : si l'ancien bloc BLOC-DEV existe sans coefficients associés, le migrer vers BLOC-COMP
        $oldDevBloc = GroupeMatiere::where('etablissement_id', $targetEtabId)
            ->where('code', 'BLOC-DEV')
            ->first();
        if ($oldDevBloc && ! $oldDevBloc->matiereNiveaux()->exists()) {
            $oldDevBloc->update([
                'code' => 'BLOC-COMP',
                'nom' => 'Complémentaires',
                'couleur' => 'amber',
                'ordre' => 3,
            ]);
        }

        foreach ($blocs as $b) {
            GroupeMatiere::updateOrCreate(
                [
                    'etablissement_id' => $targetEtabId,
                    'code' => $b['code'],
                ],
                [
                    'nom' => $b['nom'],
                    'couleur' => $b['couleur'],
                    'ordre' => $b['ordre'],
                ]
            );
        }

        // 2. Référentiel officiel exhaustif des disciplines scolaires (Cameroun - MINESEC)
        // Couvre l'Enseignement Secondaire Général (1er et 2nd cycles - Arrêté N° 92/22/MINESEC),
        // l'Enseignement Secondaire Technique (Tertiaire/STT/ESCOM et Industriel : COME, MARE, MACO, ELEQ, Menuiserie),
        // ainsi que le Sous-système Anglophone (Secondary Education : Forms 1 to 5, Lower & Upper Sixth).
        $standards = [
            // =========================================================================
            // A. ENSEIGNEMENT SECONDAIRE GÉNÉRAL (ESG) - LANGUES & LITTÉRATURES
            // =========================================================================
            [
                'code' => 'FRAN',
                'nom' => 'Langue Française',
                'nom_en' => 'French Language',
                'libelle_bulletin' => 'Français',
                'libelle_bulletin_en' => 'French',
            ],
            // Sous-disciplines du Français pour le 1er cycle (6ème à 3ème)
            [
                'code' => 'FRAN-ORTHO',
                'nom' => 'Orthographe',
                'nom_en' => 'Spelling',
                'libelle_bulletin' => 'Orthographe',
                'libelle_bulletin_en' => 'Spelling',
            ],
            [
                'code' => 'FRAN-TEXTE',
                'nom' => 'Étude de texte',
                'nom_en' => 'Text Study',
                'libelle_bulletin' => 'Étude de texte',
                'libelle_bulletin_en' => 'Text Study',
            ],
            [
                'code' => 'FRAN-ORAL',
                'nom' => 'Expression orale',
                'nom_en' => 'Oral Expression',
                'libelle_bulletin' => 'Expression orale',
                'libelle_bulletin_en' => 'Oral Expression',
            ],
            [
                'code' => 'FRAN-ECRIT',
                'nom' => 'Expression écrite',
                'nom_en' => 'Written Expression',
                'libelle_bulletin' => 'Expression écrite',
                'libelle_bulletin_en' => 'Written Expression',
            ],
            [
                'code' => 'LIT',
                'nom' => 'Littérature',
                'nom_en' => 'Literature',
                'libelle_bulletin' => 'Littérature',
                'libelle_bulletin_en' => 'Literature',
            ],
            [
                'code' => 'ANG',
                'nom' => 'Anglais',
                'nom_en' => 'English Language',
                'libelle_bulletin' => 'Anglais',
                'libelle_bulletin_en' => 'English',
            ],
            [
                'code' => 'INT-ENG',
                'nom' => 'Intensive English',
                'nom_en' => 'Intensive English',
                'libelle_bulletin' => 'Intensive English',
                'libelle_bulletin_en' => 'Intensive English',
            ],
            [
                'code' => 'ALL',
                'nom' => 'Langue Vivante II : Allemand',
                'nom_en' => 'German',
                'libelle_bulletin' => 'Allemand',
                'libelle_bulletin_en' => 'German',
            ],
            [
                'code' => 'ESP',
                'nom' => 'Langue Vivante II : Espagnol',
                'nom_en' => 'Spanish',
                'libelle_bulletin' => 'Espagnol',
                'libelle_bulletin_en' => 'Spanish',
            ],
            [
                'code' => 'ITAL',
                'nom' => 'Langue Vivante II : Italien',
                'nom_en' => 'Italian',
                'libelle_bulletin' => 'Italien',
                'libelle_bulletin_en' => 'Italian',
            ],
            [
                'code' => 'CHIN',
                'nom' => 'Langue Vivante II : Chinois',
                'nom_en' => 'Chinese',
                'libelle_bulletin' => 'Chinois',
                'libelle_bulletin_en' => 'Chinese',
            ],
            [
                'code' => 'ARAB',
                'nom' => 'Langue Vivante II : Arabe',
                'nom_en' => 'Arabic',
                'libelle_bulletin' => 'Arabe',
                'libelle_bulletin_en' => 'Arabic',
            ],
            [
                'code' => 'LV3',
                'nom' => 'Langue Vivante III',
                'nom_en' => 'Modern Language III',
                'libelle_bulletin' => 'Langue Vivante III',
                'libelle_bulletin_en' => 'Language III',
            ],
            [
                'code' => 'LAT',
                'nom' => 'Langues Anciennes : Latin',
                'nom_en' => 'Latin',
                'libelle_bulletin' => 'Latin',
                'libelle_bulletin_en' => 'Latin',
            ],
            [
                'code' => 'GREC',
                'nom' => 'Langues Anciennes : Grec',
                'nom_en' => 'Ancient Greek',
                'libelle_bulletin' => 'Grec',
                'libelle_bulletin_en' => 'Greek',
            ],

            // =========================================================================
            // B. ENSEIGNEMENT SECONDAIRE GÉNÉRAL (ESG) - SCIENCES & TECHNOLOGIES
            // =========================================================================
            [
                'code' => 'MATH',
                'nom' => 'Mathématiques',
                'nom_en' => 'Mathematics',
                'libelle_bulletin' => 'Mathématiques',
                'libelle_bulletin_en' => 'Mathematics',
            ],
            [
                'code' => 'PHYS',
                'nom' => 'Physique',
                'nom_en' => 'Physics',
                'libelle_bulletin' => 'Physique',
                'libelle_bulletin_en' => 'Physics',
            ],
            [
                'code' => 'CHIM',
                'nom' => 'Chimie',
                'nom_en' => 'Chemistry',
                'libelle_bulletin' => 'Chimie',
                'libelle_bulletin_en' => 'Chemistry',
            ],
            [
                'code' => 'PCT',
                'nom' => 'Physique - Chimie - Technologie',
                'nom_en' => 'Physics - Chemistry - Technology',
                'libelle_bulletin' => 'Physique - Chimie',
                'libelle_bulletin_en' => 'Physics - Chemistry',
            ],
            [
                'code' => 'SVTEEHB',
                'nom' => 'Sciences de la Vie et de la Terre, Éducation à l\'Environnement, Hygiène et Biotechnologie',
                'nom_en' => 'Life and Earth Sciences (SVTEEHB)',
                'libelle_bulletin' => 'SVTEEHB',
                'libelle_bulletin_en' => 'SVTEEHB',
            ],
            [
                'code' => 'SVT',
                'nom' => 'Sciences de la Vie et de la Terre',
                'nom_en' => 'Life and Earth Sciences',
                'libelle_bulletin' => 'SVT',
                'libelle_bulletin_en' => 'Biology / Geology',
            ],
            [
                'code' => 'SCI',
                'nom' => 'Sciences',
                'nom_en' => 'General Science',
                'libelle_bulletin' => 'Sciences',
                'libelle_bulletin_en' => 'Science',
            ],
            [
                'code' => 'INFO',
                'nom' => 'Informatique fondamentale et TIC',
                'nom_en' => 'Computer Science & ICT',
                'libelle_bulletin' => 'Informatique',
                'libelle_bulletin_en' => 'Computer Science',
            ],
            // Série TI (Technologies de l'Information)
            [
                'code' => 'TI-ALGO',
                'nom' => 'Algorithmique et Programmation',
                'nom_en' => 'Algorithmics & Programming',
                'libelle_bulletin' => 'Algorithmique - Programmation',
                'libelle_bulletin_en' => 'Algorithmics',
            ],
            [
                'code' => 'TI-SI',
                'nom' => 'Systèmes d\'Information',
                'nom_en' => 'Information Systems',
                'libelle_bulletin' => 'Systèmes d\'Information',
                'libelle_bulletin_en' => 'Information Systems',
            ],
            [
                'code' => 'TI-MAINT',
                'nom' => 'Maintenance et Multimédia',
                'nom_en' => 'Maintenance & Multimedia',
                'libelle_bulletin' => 'Maintenance et Multimédia',
                'libelle_bulletin_en' => 'Maintenance & Multimedia',
            ],
            [
                'code' => 'TI-RESEAU',
                'nom' => 'Réseau, Internet et Sécurité Informatique',
                'nom_en' => 'Computer Network, Internet & Security',
                'libelle_bulletin' => 'Réseau & Sécurité Info',
                'libelle_bulletin_en' => 'Network & Security',
            ],
            // Série E (Mathématiques et Techniques)
            [
                'code' => 'E-DTSM',
                'nom' => 'Dessin et Technologie des Systèmes Mécaniques',
                'nom_en' => 'Drawing & Mechanical Systems Technology',
                'libelle_bulletin' => 'Dessin & Tech. Systèmes Méc.',
                'libelle_bulletin_en' => 'Technical Drawing',
            ],
            [
                'code' => 'E-FEM',
                'nom' => 'Fabrication des Éléments Mécaniques',
                'nom_en' => 'Mechanical Elements Manufacturing',
                'libelle_bulletin' => 'Fabrication Éléments Méc.',
                'libelle_bulletin_en' => 'Mechanical Manufacturing',
            ],

            // =========================================================================
            // C. ENSEIGNEMENT SECONDAIRE GÉNÉRAL (ESG) - SCIENCES HUMAINES
            // =========================================================================
            [
                'code' => 'PHILO',
                'nom' => 'Philosophie',
                'nom_en' => 'Philosophy',
                'libelle_bulletin' => 'Philosophie',
                'libelle_bulletin_en' => 'Philosophy',
            ],
            [
                'code' => 'HIST',
                'nom' => 'Histoire',
                'nom_en' => 'History',
                'libelle_bulletin' => 'Histoire',
                'libelle_bulletin_en' => 'History',
            ],
            [
                'code' => 'GEO',
                'nom' => 'Géographie',
                'nom_en' => 'Geography',
                'libelle_bulletin' => 'Géographie',
                'libelle_bulletin_en' => 'Geography',
            ],
            [
                'code' => 'HIST-GEO',
                'nom' => 'Histoire - Géographie',
                'nom_en' => 'History & Geography',
                'libelle_bulletin' => 'Histoire - Géographie',
                'libelle_bulletin_en' => 'History - Geography',
            ],
            [
                'code' => 'ECM',
                'nom' => 'Éducation à la Citoyenneté et à la Morale',
                'nom_en' => 'Citizenship Education & Morals',
                'libelle_bulletin' => 'Éduc. Citoyenneté & Morale',
                'libelle_bulletin_en' => 'Citizenship Education',
            ],

            // =========================================================================
            // D. DÉVELOPPEMENT PERSONNEL & TRANSVERSAL
            // =========================================================================
            [
                'code' => 'EPS',
                'nom' => 'Éducation Physique et Sportive',
                'nom_en' => 'Physical & Sports Education',
                'libelle_bulletin' => 'EPS',
                'libelle_bulletin_en' => 'Sport',
            ],
            [
                'code' => 'TM',
                'nom' => 'Travail Manuel & Économie Sociale',
                'nom_en' => 'Manual Labor & Handicraft',
                'libelle_bulletin' => 'Travail Manuel',
                'libelle_bulletin_en' => 'Manual Labor',
            ],
            [
                'code' => 'TM-EM',
                'nom' => 'Économie Ménagère',
                'nom_en' => 'Home Economics',
                'libelle_bulletin' => 'Économie Ménagère',
                'libelle_bulletin_en' => 'Home Economics',
            ],
            [
                'code' => 'EVA',
                'nom' => 'Éducation à la Vie et à l\'Amour',
                'nom_en' => 'Education for Life and Love',
                'libelle_bulletin' => 'Éduc. Vie & Amour',
                'libelle_bulletin_en' => 'Life & Love Education',
            ],
            [
                'code' => 'RELIGION',
                'nom' => 'Religion',
                'nom_en' => 'Religious Studies',
                'libelle_bulletin' => 'Religion',
                'libelle_bulletin_en' => 'Religion',
            ],
            [
                'code' => 'EDUC-RELIG',
                'nom' => 'Éducation religieuse',
                'nom_en' => 'Religious Education',
                'libelle_bulletin' => 'Éducation religieuse',
                'libelle_bulletin_en' => 'Religious Education',
            ],
            [
                'code' => 'ORIENT',
                'nom' => 'Orientation Scolaire',
                'nom_en' => 'School Guidance & Counseling',
                'libelle_bulletin' => 'Orientation Scolaire',
                'libelle_bulletin_en' => 'Guidance',
            ],

            // =========================================================================
            // E. ARTS & CULTURES NATIONALES
            // =========================================================================
            [
                'code' => 'LN',
                'nom' => 'Langues Nationales',
                'nom_en' => 'National Languages',
                'libelle_bulletin' => 'Langues Nationales',
                'libelle_bulletin_en' => 'National Languages',
            ],
            [
                'code' => 'CN',
                'nom' => 'Cultures Nationales',
                'nom_en' => 'National Cultures',
                'libelle_bulletin' => 'Cultures Nationales',
                'libelle_bulletin_en' => 'National Cultures',
            ],
            [
                'code' => 'LCN',
                'nom' => 'Langue et Culture Nationale',
                'nom_en' => 'National Language and Culture',
                'libelle_bulletin' => 'Langue & Culture Nat.',
                'libelle_bulletin_en' => 'LCN',
            ],
            [
                'code' => 'ARTS',
                'nom' => 'Éducation Artistique',
                'nom_en' => 'Artistic Education',
                'libelle_bulletin' => 'Éducation Artistique',
                'libelle_bulletin_en' => 'Arts',
            ],
            [
                'code' => 'MUSIQUE',
                'nom' => 'Musique et Dessin',
                'nom_en' => 'Music and Drawing',
                'libelle_bulletin' => 'Musique / Dessin',
                'libelle_bulletin_en' => 'Music / Drawing',
            ],

            // =========================================================================
            // F. ARTS CINÉMATOGRAPHIQUES (Série AC)
            // =========================================================================
            [
                'code' => 'AC-HIST',
                'nom' => 'Histoire du Cinéma',
                'nom_en' => 'Film History',
                'libelle_bulletin' => 'Histoire du Cinéma',
                'libelle_bulletin_en' => 'Cinema History',
            ],
            [
                'code' => 'AC-LANG',
                'nom' => 'Éléments de Langage et de Grammaire Cinématographiques',
                'nom_en' => 'Cinema Language & Grammar Elements',
                'libelle_bulletin' => 'Langage Cinématographique',
                'libelle_bulletin_en' => 'Cinema Language',
            ],
            [
                'code' => 'AC-OUTILS',
                'nom' => 'Outils et Métiers du Cinéma',
                'nom_en' => 'Cinema Tools & Professions',
                'libelle_bulletin' => 'Outils & Métiers du Cinéma',
                'libelle_bulletin_en' => 'Cinema Tools',
            ],
            [
                'code' => 'AC-GENRES',
                'nom' => 'Genres Cinématographiques',
                'nom_en' => 'Film Genres',
                'libelle_bulletin' => 'Genres Cinématographiques',
                'libelle_bulletin_en' => 'Film Genres',
            ],
            [
                'code' => 'AC-ANAL',
                'nom' => 'Analyse Filmique',
                'nom_en' => 'Film Analysis',
                'libelle_bulletin' => 'Analyse Filmique',
                'libelle_bulletin_en' => 'Film Analysis',
            ],
            [
                'code' => 'AC-ECO',
                'nom' => 'Économie du Cinéma',
                'nom_en' => 'Film Industry Economics',
                'libelle_bulletin' => 'Économie du Cinéma',
                'libelle_bulletin_en' => 'Cinema Economics',
            ],
            [
                'code' => 'AC-REAL',
                'nom' => 'Processus de Réalisation d\'un Film',
                'nom_en' => 'Film Directing & Production Process',
                'libelle_bulletin' => 'Processus Réalisation Film',
                'libelle_bulletin_en' => 'Film Production',
            ],
            [
                'code' => 'AC-PROJ',
                'nom' => 'Projet de Fin de Formation (Cinéma)',
                'nom_en' => 'Graduation Project (Cinema)',
                'libelle_bulletin' => 'Projet Fin Formation',
                'libelle_bulletin_en' => 'Graduation Project',
            ],
            [
                'code' => 'AC-SOCIO',
                'nom' => 'Sociologie du Cinéma',
                'nom_en' => 'Cinema Sociology',
                'libelle_bulletin' => 'Sociologie du Cinéma',
                'libelle_bulletin_en' => 'Cinema Sociology',
            ],

            // =========================================================================
            // G. ENSEIGNEMENT SECONDAIRE TECHNIQUE - GESTION, COMMERCE & TERTIAIRE
            // =========================================================================
            [
                'code' => 'ECO-GEN',
                'nom' => 'Économie Générale',
                'nom_en' => 'General Economics',
                'libelle_bulletin' => 'Économie Générale',
                'libelle_bulletin_en' => 'General Economics',
            ],
            [
                'code' => 'ECO-ORG',
                'nom' => 'Économie et Organisation des Entreprises',
                'nom_en' => 'Business Economics and Organization',
                'libelle_bulletin' => 'Économie & Organisation',
                'libelle_bulletin_en' => 'Business Organization',
            ],
            [
                'code' => 'DROIT',
                'nom' => 'Droit',
                'nom_en' => 'Law',
                'libelle_bulletin' => 'Droit',
                'libelle_bulletin_en' => 'Law',
            ],
            [
                'code' => 'MATH-APPLI',
                'nom' => 'Mathématiques Appliquées et Statistiques',
                'nom_en' => 'Applied Mathematics and Statistics',
                'libelle_bulletin' => 'Maths Appliquées',
                'libelle_bulletin_en' => 'Applied Maths',
            ],
            [
                'code' => 'MATH-COM',
                'nom' => 'Mathématiques Commerciales',
                'nom_en' => 'Business Mathematics',
                'libelle_bulletin' => 'Maths Commerciales',
                'libelle_bulletin_en' => 'Business Maths',
            ],
            [
                'code' => 'LEGIS-TRAV',
                'nom' => 'Législation du Travail',
                'nom_en' => 'Labor Law',
                'libelle_bulletin' => 'Législation du Travail',
                'libelle_bulletin_en' => 'Labor Law',
            ],
            [
                'code' => 'COMMERCE',
                'nom' => 'Commerce',
                'nom_en' => 'Commerce',
                'libelle_bulletin' => 'Commerce',
                'libelle_bulletin_en' => 'Commerce',
            ],
            [
                'code' => 'BUREAUTIQUE',
                'nom' => 'Bureautique',
                'nom_en' => 'Office Automation',
                'libelle_bulletin' => 'Bureautique',
                'libelle_bulletin_en' => 'Office Automation',
            ],
            [
                'code' => 'MOB',
                'nom' => 'Matériels et Outils Bureautiques',
                'nom_en' => 'Office Equipment and Tools',
                'libelle_bulletin' => 'Matériels Bureautiques',
                'libelle_bulletin_en' => 'Office Equipment',
            ],
            [
                'code' => 'OTA',
                'nom' => 'Organisation du Travail Administratif',
                'nom_en' => 'Administrative Work Organization',
                'libelle_bulletin' => 'Organisation Travail Admin.',
                'libelle_bulletin_en' => 'Work Organization',
            ],
            [
                'code' => 'REDACT-PRO',
                'nom' => 'Rédaction Commerciale et Professionnelle',
                'nom_en' => 'Business and Professional Writing',
                'libelle_bulletin' => 'Rédaction Professionnelle',
                'libelle_bulletin_en' => 'Business Writing',
            ],
            [
                'code' => 'PRISE-PAROLE',
                'nom' => 'Prise Rapide de la Parole / Sténographie',
                'nom_en' => 'Shorthand / Speech Recording',
                'libelle_bulletin' => 'Prise Rapide Parole',
                'libelle_bulletin_en' => 'Shorthand',
            ],
            [
                'code' => 'CGAO',
                'nom' => 'Comptabilité et Gestion Assistée par Ordinateur',
                'nom_en' => 'Computerized Accounting and Management',
                'libelle_bulletin' => 'Comptabilité Assistée Info',
                'libelle_bulletin_en' => 'Computer Accounting',
            ],
            [
                'code' => 'COMPTA-FIN',
                'nom' => 'Comptabilité Financière',
                'nom_en' => 'Financial Accounting',
                'libelle_bulletin' => 'Comptabilité Financière',
                'libelle_bulletin_en' => 'Financial Accounting',
            ],
            [
                'code' => 'COMPTA-ENTR',
                'nom' => 'Comptabilité d\'Entreprise',
                'nom_en' => 'Corporate Accounting',
                'libelle_bulletin' => 'Comptabilité d\'Entreprise',
                'libelle_bulletin_en' => 'Corporate Accounting',
            ],
            [
                'code' => 'COMPTA-MGT',
                'nom' => 'Comptabilité de Management',
                'nom_en' => 'Management Accounting',
                'libelle_bulletin' => 'Comptabilité Management',
                'libelle_bulletin_en' => 'Management Accounting',
            ],
            [
                'code' => 'FIN-ENTR',
                'nom' => 'Finance d\'Entreprise',
                'nom_en' => 'Corporate Finance',
                'libelle_bulletin' => 'Finance d\'Entreprise',
                'libelle_bulletin_en' => 'Corporate Finance',
            ],
            [
                'code' => 'GEST-INFO-FIN',
                'nom' => 'Gestion de l\'Information Financière',
                'nom_en' => 'Financial Information Management',
                'libelle_bulletin' => 'Gestion Info Financière',
                'libelle_bulletin_en' => 'Financial Info Mgt',
            ],
            [
                'code' => 'PRATIQUE-COMPTA',
                'nom' => 'Pratique Comptable Quotidienne',
                'nom_en' => 'Daily Accounting Practice',
                'libelle_bulletin' => 'Pratique Comptable',
                'libelle_bulletin_en' => 'Daily Accounting',
            ],
            [
                'code' => 'TRAVAUX-COMPTA',
                'nom' => 'Travaux Comptables de Fin d\'Exercice',
                'nom_en' => 'Year-End Accounting Operations',
                'libelle_bulletin' => 'Travaux Fin Exercice',
                'libelle_bulletin_en' => 'Year-End Accounting',
            ],
            [
                'code' => 'DOC-COM-COMPTA',
                'nom' => 'Documents Commerciaux et Comptabilité',
                'nom_en' => 'Commercial Documents and Accounting',
                'libelle_bulletin' => 'Documents Commerciaux',
                'libelle_bulletin_en' => 'Commercial Docs',
            ],
            [
                'code' => 'TRAV-APPLI',
                'nom' => 'Travaux d\'Application et Synthèse',
                'nom_en' => 'Practical Application and Synthesis',
                'libelle_bulletin' => 'Travaux d\'Application',
                'libelle_bulletin_en' => 'Practical Application',
            ],

            // =========================================================================
            // H. ENSEIGNEMENT TECHNIQUE - MODULES TRANSVERSAUX & MÉTIERS
            // =========================================================================
            [
                'code' => 'QHSE',
                'nom' => 'Santé, Sécurité et Environnement',
                'nom_en' => 'Health, Safety and Environment',
                'libelle_bulletin' => 'Santé, Sécurité, Environ.',
                'libelle_bulletin_en' => 'HSE',
            ],
            [
                'code' => 'ENTREPREN',
                'nom' => 'Entrepreneuriat',
                'nom_en' => 'Entrepreneurship',
                'libelle_bulletin' => 'Entrepreneuriat',
                'libelle_bulletin_en' => 'Entrepreneurship',
            ],
            [
                'code' => 'METIER-FORM',
                'nom' => 'Métier et Formation',
                'nom_en' => 'Trade and Training Orientation',
                'libelle_bulletin' => 'Métier et Formation',
                'libelle_bulletin_en' => 'Trade & Training',
            ],

            // =========================================================================
            // I. ENSEIGNEMENT TECHNIQUE INDUSTRIEL - SPÉCIALITÉ COME (COUPE & COUTURE)
            // =========================================================================
            [
                'code' => 'TECH-TEXTILE',
                'nom' => 'Technologie des Textiles',
                'nom_en' => 'Textile Technology',
                'libelle_bulletin' => 'Technologie des Textiles',
                'libelle_bulletin_en' => 'Textile Tech',
            ],
            [
                'code' => 'DESSIN-MODE',
                'nom' => 'Dessin de Mode',
                'nom_en' => 'Fashion Drawing',
                'libelle_bulletin' => 'Dessin de Mode',
                'libelle_bulletin_en' => 'Fashion Drawing',
            ],
            [
                'code' => 'COUPE',
                'nom' => 'Coupe Vêtement',
                'nom_en' => 'Pattern Cutting',
                'libelle_bulletin' => 'Coupe',
                'libelle_bulletin_en' => 'Cutting',
            ],
            [
                'code' => 'COUTURE',
                'nom' => 'Couture et Assemblage',
                'nom_en' => 'Sewing and Assembly',
                'libelle_bulletin' => 'Couture',
                'libelle_bulletin_en' => 'Sewing',
            ],

            // =========================================================================
            // J. ENSEIGNEMENT TECHNIQUE INDUSTRIEL - SPÉCIALITÉ MARE (MÉCANIQUE AUTO)
            // =========================================================================
            [
                'code' => 'DTCM',
                'nom' => 'Dessin et Technologie de Construction Mécanique',
                'nom_en' => 'Mechanical Construction Drawing and Technology',
                'libelle_bulletin' => 'Dessin Tech Construction',
                'libelle_bulletin_en' => 'Mech Drawing',
            ],
            [
                'code' => 'TECH-MECAN',
                'nom' => 'Technologie Mécanique',
                'nom_en' => 'Mechanical Technology',
                'libelle_bulletin' => 'Technologie Mécanique',
                'libelle_bulletin_en' => 'Mech Technology',
            ],
            [
                'code' => 'TECH-ELEC-MEC',
                'nom' => 'Technologie Électrique et Mécanique',
                'nom_en' => 'Electrical and Mechanical Technology',
                'libelle_bulletin' => 'Tech Électrique & Méc.',
                'libelle_bulletin_en' => 'Electro-Mech Tech',
            ],
            [
                'code' => 'TP-AJUSTAGE',
                'nom' => 'Travaux Pratiques d\'Ajustage',
                'nom_en' => 'Fitting Practical Work',
                'libelle_bulletin' => 'TP Ajustage',
                'libelle_bulletin_en' => 'Fitting Practice',
            ],
            [
                'code' => 'RECEPT-VEHIC',
                'nom' => 'Réception d\'un Véhicule',
                'nom_en' => 'Vehicle Reception and Diagnostics',
                'libelle_bulletin' => 'Réception Véhicule',
                'libelle_bulletin_en' => 'Vehicle Reception',
            ],
            [
                'code' => 'MOTEURS-COMB',
                'nom' => 'Moteurs à Combustion Interne',
                'nom_en' => 'Internal Combustion Engines',
                'libelle_bulletin' => 'Moteurs à Combustion',
                'libelle_bulletin_en' => 'Engines',
            ],
            [
                'code' => 'TRANSMISSIONS',
                'nom' => 'Les Transmissions Automobiles',
                'nom_en' => 'Automotive Transmissions',
                'libelle_bulletin' => 'Transmissions',
                'libelle_bulletin_en' => 'Transmissions',
            ],
            [
                'code' => 'FREINAGE-SECU',
                'nom' => 'Tenue de Route, Freinage et Sécurité',
                'nom_en' => 'Road Handling, Braking and Safety',
                'libelle_bulletin' => 'Freinage & Sécurité Auto',
                'libelle_bulletin_en' => 'Braking & Safety',
            ],
            [
                'code' => 'ELEC-AUTO',
                'nom' => 'Électricité et Électronique Automobile',
                'nom_en' => 'Automotive Electricity and Electronics',
                'libelle_bulletin' => 'Électricité & Électro Auto',
                'libelle_bulletin_en' => 'Auto Electricity',
            ],

            // =========================================================================
            // K. ENSEIGNEMENT TECHNIQUE INDUSTRIEL - SPÉCIALITÉ MACO (MAÇONNERIE / BTP)
            // =========================================================================
            [
                'code' => 'DEVIS-ESTIM',
                'nom' => 'Devis et Estimation',
                'nom_en' => 'Estimating and Costing',
                'libelle_bulletin' => 'Devis et Estimation',
                'libelle_bulletin_en' => 'Cost Estimation',
            ],
            [
                'code' => 'DESSIN-BAT',
                'nom' => 'Dessin Bâtiment',
                'nom_en' => 'Building Drawing',
                'libelle_bulletin' => 'Dessin Bâtiment',
                'libelle_bulletin_en' => 'Building Drawing',
            ],
            [
                'code' => 'CONNAIS-METIER',
                'nom' => 'Connaissance du Métier et des Matériaux',
                'nom_en' => 'Trade and Building Materials Knowledge',
                'libelle_bulletin' => 'Métier & Matériaux BTP',
                'libelle_bulletin_en' => 'Materials Knowledge',
            ],
            [
                'code' => 'OUTILLAGE-BAT',
                'nom' => 'Connaissance du Matériel et Outillage',
                'nom_en' => 'Tools and Equipment Knowledge',
                'libelle_bulletin' => 'Matériel & Outillage BTP',
                'libelle_bulletin_en' => 'Tools & Equipment',
            ],
            [
                'code' => 'PROCEDES-REAL',
                'nom' => 'Procédés et Réalisation',
                'nom_en' => 'Building Processes and Implementation',
                'libelle_bulletin' => 'Procédés & Réalisation',
                'libelle_bulletin_en' => 'Processes & Building',
            ],
            [
                'code' => 'MACONNERIE-ECHA',
                'nom' => 'Exécution de Maçonnerie et Échafaudages',
                'nom_en' => 'Masonry and Scaffolding Execution',
                'libelle_bulletin' => 'Maçonnerie & Échafaudages',
                'libelle_bulletin_en' => 'Masonry & Scaffolding',
            ],
            [
                'code' => 'OUVRAGES-TERRE',
                'nom' => 'Ouvrages en Terre',
                'nom_en' => 'Earthworks',
                'libelle_bulletin' => 'Ouvrages en Terre',
                'libelle_bulletin_en' => 'Earthworks',
            ],
            [
                'code' => 'COFFRAGE',
                'nom' => 'Exécution de Coffrage',
                'nom_en' => 'Formwork Execution',
                'libelle_bulletin' => 'Exécution de Coffrage',
                'libelle_bulletin_en' => 'Formwork',
            ],
            [
                'code' => 'FERRAILLAGE',
                'nom' => 'Exécution de Ferraillage',
                'nom_en' => 'Reinforcement Execution',
                'libelle_bulletin' => 'Exécution de Ferraillage',
                'libelle_bulletin_en' => 'Reinforcement',
            ],
            [
                'code' => 'BETON',
                'nom' => 'Mise en Œuvre du Béton',
                'nom_en' => 'Concrete Works',
                'libelle_bulletin' => 'Mise en Œuvre du Béton',
                'libelle_bulletin_en' => 'Concrete Works',
            ],
            [
                'code' => 'REVETEMENTS',
                'nom' => 'Exécution des Revêtements',
                'nom_en' => 'Finishing and Coating',
                'libelle_bulletin' => 'Exécution des Revêtements',
                'libelle_bulletin_en' => 'Coating & Finishing',
            ],

            // =========================================================================
            // L. ENSEIGNEMENT TECHNIQUE INDUSTRIEL - SPÉCIALITÉ ELEQ (ÉLECTROTECHNIQUE)
            // =========================================================================
            [
                'code' => 'CIRCUITS-ELEC',
                'nom' => 'Circuits Électriques',
                'nom_en' => 'Electric Circuits',
                'libelle_bulletin' => 'Circuits Électriques',
                'libelle_bulletin_en' => 'Electric Circuits',
            ],
            [
                'code' => 'MACHINES-ELEC',
                'nom' => 'Machines Électriques',
                'nom_en' => 'Electrical Machines',
                'libelle_bulletin' => 'Machines Électriques',
                'libelle_bulletin_en' => 'Electrical Machines',
            ],
            [
                'code' => 'TECH-EQUIP-ELEC',
                'nom' => 'Technologie des Équipements et Installations Électriques',
                'nom_en' => 'Electrical Equipment and Installations Technology',
                'libelle_bulletin' => 'Techno Équipements Élec.',
                'libelle_bulletin_en' => 'Electrical Equipment',
            ],
            [
                'code' => 'SCHEMAS-ELEC',
                'nom' => 'Schémas Électriques',
                'nom_en' => 'Electrical Diagrams',
                'libelle_bulletin' => 'Schémas Électriques',
                'libelle_bulletin_en' => 'Electrical Diagrams',
            ],
            [
                'code' => 'PROD-TRANS-DIST',
                'nom' => 'Production, Transport et Distribution de l\'Énergie',
                'nom_en' => 'Energy Production, Transmission and Distribution',
                'libelle_bulletin' => 'Prod. Transport Distrib.',
                'libelle_bulletin_en' => 'Energy Transmission',
            ],
            [
                'code' => 'DESSIN-TECH',
                'nom' => 'Dessin Technique Électrique',
                'nom_en' => 'Electrical Technical Drawing',
                'libelle_bulletin' => 'Dessin Technique Élec.',
                'libelle_bulletin_en' => 'Technical Drawing',
            ],
            [
                'code' => 'TP-ELEC',
                'nom' => 'Travaux Pratiques d\'Électricité',
                'nom_en' => 'Electrical Practical Works',
                'libelle_bulletin' => 'TP Électricité',
                'libelle_bulletin_en' => 'Electrical Practice',
            ],
            [
                'code' => 'INSTAL-RESID-COM',
                'nom' => 'Installations Électriques Résidentielles et Commerciales',
                'nom_en' => 'Residential and Commercial Electrical Installations',
                'libelle_bulletin' => 'Install. Élec. Résidentielles',
                'libelle_bulletin_en' => 'Residential Electrical',
            ],
            [
                'code' => 'INSTAL-INDUST',
                'nom' => 'Installations Électriques Industrielles',
                'nom_en' => 'Industrial Electrical Installations',
                'libelle_bulletin' => 'Install. Élec. Industrielles',
                'libelle_bulletin_en' => 'Industrial Electrical',
            ],
            [
                'code' => 'MESURES-ESSAIS',
                'nom' => 'Mesures et Essais Électriques',
                'nom_en' => 'Electrical Measurements and Testing',
                'libelle_bulletin' => 'Mesures & Essais Élec.',
                'libelle_bulletin_en' => 'Electrical Testing',
            ],

            // =========================================================================
            // M. ENSEIGNEMENT TECHNIQUE INDUSTRIEL - SPÉCIALITÉ MENUISERIE (BOIS)
            // =========================================================================
            [
                'code' => 'TECH-MATERIAUX',
                'nom' => 'Technologie des Matériaux Bois',
                'nom_en' => 'Wood Materials Technology',
                'libelle_bulletin' => 'Techno Matériaux Bois',
                'libelle_bulletin_en' => 'Wood Technology',
            ],
            [
                'code' => 'TECH-MENUISERIE',
                'nom' => 'Technologie Professionnelle Menuiserie',
                'nom_en' => 'Joinery Professional Technology',
                'libelle_bulletin' => 'Technologie Menuiserie',
                'libelle_bulletin_en' => 'Joinery Technology',
            ],
            [
                'code' => 'DESSIN-MENUIS',
                'nom' => 'Dessin de Menuiserie',
                'nom_en' => 'Joinery Drawing',
                'libelle_bulletin' => 'Dessin de Menuiserie',
                'libelle_bulletin_en' => 'Joinery Drawing',
            ],
            [
                'code' => 'EXEC-MENUISERIE',
                'nom' => 'Exécution de Menuiserie et Échafaudage',
                'nom_en' => 'Joinery and Scaffolding Execution',
                'libelle_bulletin' => 'Menuiserie & Échafaudage',
                'libelle_bulletin_en' => 'Joinery & Scaffolding',
            ],
            [
                'code' => 'FAB-OUVERTURES',
                'nom' => 'Fabrication et Installation des Ouvertures et Revêtements',
                'nom_en' => 'Manufacture and Installation of Openings and Coatings',
                'libelle_bulletin' => 'Ouvertures & Revêtements',
                'libelle_bulletin_en' => 'Openings & Coatings',
            ],
            [
                'code' => 'FAB-MEUBLES',
                'nom' => 'Fabrication et Installation des Meubles (TP)',
                'nom_en' => 'Furniture Manufacturing and Installation',
                'libelle_bulletin' => 'Fabrication Meubles (TP)',
                'libelle_bulletin_en' => 'Furniture Making',
            ],

            // =========================================================================
            // N. ANGLOPHONE SUBSYSTEM (SECONDARY GENERAL - FORMS 1 TO 5 & SIXTH FORMS)
            // =========================================================================
            [
                'code' => 'ENG-LANG',
                'nom' => 'English Language',
                'nom_en' => 'English Language',
                'libelle_bulletin' => 'English Language',
                'libelle_bulletin_en' => 'English Language',
            ],
            [
                'code' => 'ENG-LIT',
                'nom' => 'Literature in English',
                'nom_en' => 'Literature in English',
                'libelle_bulletin' => 'Literature in English',
                'libelle_bulletin_en' => 'Literature in English',
            ],
            [
                'code' => 'ENG-FREN',
                'nom' => 'French (Anglophone Section)',
                'nom_en' => 'French',
                'libelle_bulletin' => 'French',
                'libelle_bulletin_en' => 'French',
            ],
            [
                'code' => 'ENG-MATH',
                'nom' => 'Mathematics (Anglophone Section)',
                'nom_en' => 'Mathematics',
                'libelle_bulletin' => 'Mathematics',
                'libelle_bulletin_en' => 'Mathematics',
            ],
            [
                'code' => 'ENG-ADDMATH',
                'nom' => 'Additional Mathematics',
                'nom_en' => 'Additional Mathematics',
                'libelle_bulletin' => 'Additional Maths',
                'libelle_bulletin_en' => 'Additional Maths',
            ],
            [
                'code' => 'ENG-PHYS',
                'nom' => 'Physics (Anglophone Section)',
                'nom_en' => 'Physics',
                'libelle_bulletin' => 'Physics',
                'libelle_bulletin_en' => 'Physics',
            ],
            [
                'code' => 'ENG-CHEM',
                'nom' => 'Chemistry (Anglophone Section)',
                'nom_en' => 'Chemistry',
                'libelle_bulletin' => 'Chemistry',
                'libelle_bulletin_en' => 'Chemistry',
            ],
            [
                'code' => 'ENG-BIO',
                'nom' => 'Biology',
                'nom_en' => 'Biology',
                'libelle_bulletin' => 'Biology',
                'libelle_bulletin_en' => 'Biology',
            ],
            [
                'code' => 'ENG-HBIO',
                'nom' => 'Human Biology',
                'nom_en' => 'Human Biology',
                'libelle_bulletin' => 'Human Biology',
                'libelle_bulletin_en' => 'Human Biology',
            ],
            [
                'code' => 'ENG-GEOL',
                'nom' => 'Geology',
                'nom_en' => 'Geology',
                'libelle_bulletin' => 'Geology',
                'libelle_bulletin_en' => 'Geology',
            ],
            [
                'code' => 'ENG-HIST',
                'nom' => 'History (Anglophone Section)',
                'nom_en' => 'History',
                'libelle_bulletin' => 'History',
                'libelle_bulletin_en' => 'History',
            ],
            [
                'code' => 'ENG-GEOG',
                'nom' => 'Geography (Anglophone Section)',
                'nom_en' => 'Geography',
                'libelle_bulletin' => 'Geography',
                'libelle_bulletin_en' => 'Geography',
            ],
            [
                'code' => 'ENG-CITIZEN',
                'nom' => 'Citizenship Education (Anglophone Section)',
                'nom_en' => 'Citizenship Education',
                'libelle_bulletin' => 'Citizenship Education',
                'libelle_bulletin_en' => 'Citizenship Education',
            ],
            [
                'code' => 'ENG-LOGIC',
                'nom' => 'Logic',
                'nom_en' => 'Logic',
                'libelle_bulletin' => 'Logic',
                'libelle_bulletin_en' => 'Logic',
            ],
            [
                'code' => 'ENG-PHILOS',
                'nom' => 'Philosophy (Anglophone Section)',
                'nom_en' => 'Philosophy',
                'libelle_bulletin' => 'Philosophy',
                'libelle_bulletin_en' => 'Philosophy',
            ],
            [
                'code' => 'ENG-ECON',
                'nom' => 'Economics',
                'nom_en' => 'Economics',
                'libelle_bulletin' => 'Economics',
                'libelle_bulletin_en' => 'Economics',
            ],
            [
                'code' => 'ENG-COMM',
                'nom' => 'Commerce (Anglophone Section)',
                'nom_en' => 'Commerce',
                'libelle_bulletin' => 'Commerce',
                'libelle_bulletin_en' => 'Commerce',
            ],
            [
                'code' => 'ENG-CS-ICT',
                'nom' => 'Computer Science / ICT (Anglophone Section)',
                'nom_en' => 'Computer Science / ICT',
                'libelle_bulletin' => 'Computer Science / ICT',
                'libelle_bulletin_en' => 'Computer Science / ICT',
            ],
            [
                'code' => 'ENG-FOOD-NUT',
                'nom' => 'Food and Nutrition / Food Science',
                'nom_en' => 'Food & Nutrition / Food Science',
                'libelle_bulletin' => 'Food & Nutrition',
                'libelle_bulletin_en' => 'Food & Nutrition',
            ],
            [
                'code' => 'ENG-PE',
                'nom' => 'Physical Education (PE)',
                'nom_en' => 'Physical Education (PE)',
                'libelle_bulletin' => 'Physical Education (PE)',
                'libelle_bulletin_en' => 'Physical Education (PE)',
            ],
            [
                'code' => 'ENG-MAN-ARTS',
                'nom' => 'Manual Labour / Arts',
                'nom_en' => 'Manual Labour / Arts',
                'libelle_bulletin' => 'Manual Labour / Arts',
                'libelle_bulletin_en' => 'Manual Labour / Arts',
            ],
            [
                'code' => 'ENG-REL-KNOW',
                'nom' => 'Religious Knowledge',
                'nom_en' => 'Religious Knowledge',
                'libelle_bulletin' => 'Religious Knowledge',
                'libelle_bulletin_en' => 'Religious Knowledge',
            ],
            [
                'code' => 'ENG-SBE-FREN',
                'nom' => 'Special Bilingual Education French (SBE French)',
                'nom_en' => 'SBE French',
                'libelle_bulletin' => 'SBE French',
                'libelle_bulletin_en' => 'SBE French',
            ],
        ];

        foreach ($standards as $s) {
            Matiere::updateOrCreate(
                [
                    'code' => $s['code'],
                    'etablissement_id' => null,
                ],
                [
                    'nom' => $s['nom'],
                    'nom_en' => $s['nom_en'] ?? null,
                    'libelle_bulletin' => $s['libelle_bulletin'] ?? $s['nom'],
                    'libelle_bulletin_en' => $s['libelle_bulletin_en'] ?? null,
                    'is_active' => true,
                ]
            );
        }

        return back()->with('success', 'Catalogue officiel et blocs standards de l\'établissement initialisés avec succès.');
    }
}
