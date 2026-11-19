<?php

namespace App\Http\Controllers\Administration;

use App\Concerns\ScopesEtablissement;
use App\Http\Controllers\Controller;
use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\Etablissement;
use App\Models\Periode;
use App\Services\StatistiqueConseilService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Contrôleur exécutif pour le Tableau de Bord et les Statistiques Scolaires (Module 15 Gesco V1.1).
 *
 * Coordonne :
 * - Le tableau de bord exécutif d'accueil (/dashboard) avec KPIs consolidés : effectifs, parité, réussite, finances.
 * - Le portail de statistiques officielles réglementaires (/administration/statistiques) MINESEC / MINEDUB.
 * - L'édition et l'export des bordereaux officiels d'examens avec matricule ministériel.
 */
class TableauDeBordController extends Controller implements HasMiddleware
{
    use ScopesEtablissement;
    /**
     * Définition de la matrice des middlewares d'autorisation pour le module Statistiques.
     *
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:statistiques.view', only: ['statistiquesAvancees', 'exportBordereau']),
        ];
    }

    /**
     * Affiche le tableau de bord exécutif multi-indicateurs.
     *
     * @param Request $request
     * @param StatistiqueConseilService $statistiqueService
     * @return InertiaResponse
     */
    public function index(Request $request, StatistiqueConseilService $statistiqueService): InertiaResponse
    {
        $user = $request->user();
        $isSuperAdmin = $user ? $user->isSuperAdmin() : false;

        /**
         * Principe Zero-Trust : Un utilisateur qui n'a aucun rôle ou dont le rôle ne possède aucune permission
         * ne doit pas avoir accès aux indicateurs stratégiques de l'établissement (effectifs, finances, décrochage).
         */
        $hasAccess = $isSuperAdmin || ($user && $user->getAllPermissions()->isNotEmpty());

        if (! $hasAccess) {
            return Inertia::render('dashboard', [
                'hasAccess' => false,
                'supportsAllEtablissements' => false,
                'isAllEtablissements' => false,
                'etablissements' => [],
                'currentEtablissement' => null,
                'annees' => [],
                'currentAnneeId' => 0,
                'demographie' => [
                    'effectif_total' => 0,
                    'repartition_statut' => ['nouveaux' => 0, 'redoublants' => 0, 'taux_redoublants' => 0],
                    'parite' => ['filles' => 0, 'garcons' => 0, 'taux_filles' => 0],
                    'repartition_cycle' => [],
                    'repartition_cycles' => [],
                    'densite' => [
                        'nombre_classes' => 0,
                        'moyenne_par_classe' => 0,
                        'classes_surchargees' => 0,
                        'details' => [],
                    ],
                ],
                'pedagogie' => [
                    'taux_reussite_global' => 0,
                    'total_evalues' => 0,
                    'total_admis' => 0,
                    'distribution_mentions' => [],
                    'decrochage' => [],
                    'eleves_decrochage' => [],
                    'total_decrochage' => 0,
                ],
                'finances' => [
                    'total_facture' => 0,
                    'total_recouvre' => 0,
                    'total_creances' => 0,
                    'taux_recouvrement' => 0,
                ],
            ]);
        }

        $context = $this->resolveEtablissementContext($request, ['institution']);
        $etablissements = $context['etablissements'];
        $currentEtablissement = $context['selectedEtablissement'];
        $etablissementId = $currentEtablissement?->id ?? 0;

        $annees = AnneeScolaire::orderByDesc('date_debut')->get();
        $anneeActive = AnneeScolaire::current() ?? $annees->first();
        $anneeId = $request->integer('annee_id', $anneeActive?->id ?? 0);

        // Détection du profil Enseignant exclusif (sans prérogatives administratives ou financières)
        $isTeacher = $user && $user->hasRole('enseignant')
            && ! $user->hasRole(['super-admin', 'admin', 'directeur-etudes', 'censeur', 'surveillant-general', 'intendant'])
            && ! $user->can('finances.view')
            && ! $user->can('statistiques.view');

        if ($isTeacher) {
            $personnel = $user->personnel ?? ($user->email ? \App\Models\Personnel::where('email', $user->email)->first() : null);

            $attributions = $personnel
                ? \App\Models\Attribution::with(['classe', 'matiere'])
                    ->where('personnel_id', $personnel->id)
                    ->where('annee_scolaire_id', $anneeId)
                    ->where('is_active', true)
                    ->get()
                : collect([]);

            $classeIds = $attributions->pluck('classe_id')->unique()->values();
            $totalEleves = \App\Models\Inscription::whereIn('classe_id', $classeIds)
                ->where('annee_scolaire_id', $anneeId)
                ->where('statut', 'validee')
                ->count();
            $totalHeures = (float) $attributions->sum('volume_horaire_reel');

            $today = now()->toDateString();
            // Détection de l'évaluation académique en cours (par date courante ou statut actif)
            $currentEvaluation = \App\Models\Evaluation::whereHas('periode', fn ($q) => $q->where('etablissement_id', $etablissementId)->where('annee_scolaire_id', $anneeId))
                ->where(function ($query) use ($today) {
                    $query->where(function ($sub) use ($today) {
                        $sub->whereDate('date_debut', '<=', $today)
                            ->whereDate('date_fin', '>=', $today);
                    })->orWhere('statut', 'en_cours');
                })
                ->orderBy('ordre', 'asc')
                ->first();

            // Si aucune évaluation n'est active aujourd'hui, cibler la prochaine à venir ou la première de l'année
            if (! $currentEvaluation) {
                $currentEvaluation = \App\Models\Evaluation::whereHas('periode', fn ($q) => $q->where('etablissement_id', $etablissementId)->where('annee_scolaire_id', $anneeId))
                    ->whereDate('date_fin', '>=', $today)
                    ->orderBy('ordre', 'asc')
                    ->first()
                    ?? \App\Models\Evaluation::whereHas('periode', fn ($q) => $q->where('etablissement_id', $etablissementId)->where('annee_scolaire_id', $anneeId))
                        ->orderBy('ordre', 'asc')
                        ->first();
            }

            $attributionsData = $attributions->map(function ($attr) use ($currentEvaluation, $anneeId) {
                $effectifClasse = \App\Models\Inscription::where('classe_id', $attr->classe_id)
                    ->where('annee_scolaire_id', $anneeId)
                    ->where('statut', 'validee')
                    ->count();

                $notesSaisies = $currentEvaluation
                    ? \App\Models\Note::where('attribution_id', $attr->id)
                        ->where('evaluation_id', $currentEvaluation->id)
                        ->whereNotNull('valeur_note')
                        ->count()
                    : 0;

                return [
                    'id' => $attr->id,
                    'classeNom' => $attr->classe?->nom ?? '-',
                    'matiereNom' => $attr->matiere?->nom ?? '-',
                    'volumeHoraire' => (float) $attr->volume_horaire_reel,
                    'effectif' => $effectifClasse,
                    'notesSaisies' => $notesSaisies,
                    'pourcentageSaisie' => $effectifClasse > 0 ? round(($notesSaisies / $effectifClasse) * 100) : 0,
                ];
            });

            return Inertia::render('dashboard', [
                'hasAccess' => true,
                'supportsAllEtablissements' => false,
                'isAllEtablissements' => false,
                'isEnseignantDashboard' => true,
                'etablissements' => $etablissements,
                'currentEtablissement' => $currentEtablissement,
                'annees' => $annees,
                'currentAnneeId' => $anneeId,
                'enseignantStats' => [
                    'nom' => $personnel ? ($personnel->nom . ' ' . $personnel->prenom) : $user->name,
                    'matricule' => $personnel?->matricule ?? '-',
                    'departement' => $personnel?->departement?->nom ?? 'Général',
                    'totalClasses' => $classeIds->count(),
                    'totalEleves' => $totalEleves,
                    'totalHeures' => $totalHeures,
                    'evaluationActiveNom' => $currentEvaluation?->nom ?? 'Séquence en cours',
                    'attributions' => $attributionsData,
                ],
                'demographie' => null,
                'pedagogie' => null,
                'finances' => null,
            ]);
        }

        $scope = $this->resolveStatsScope($request, $context);
        $targetIds = $scope['targetIds'];

        // 1. Statistiques Démographiques (15.1)
        $demographie = $statistiqueService->getStatistiquesDemographiques($targetIds, $anneeId);

        // 2. Statistiques Pédagogiques & Décrochage (15.3)
        $pedagogie = $statistiqueService->getStatistiquesPedagogiques($targetIds, $anneeId);

        // 3. Statistiques Financières (15.3)
        $finances = $statistiqueService->getStatistiquesFinancieres($targetIds, $anneeId);

        return Inertia::render('dashboard', [
            'hasAccess' => true,
            'isEnseignantDashboard' => false,
            'supportsAllEtablissements' => $scope['supportsAllEtablissements'],
            'isAllEtablissements' => $scope['isAllEtablissements'],
            'etablissements' => $etablissements,
            'currentEtablissement' => $currentEtablissement,
            'annees' => $annees,
            'currentAnneeId' => $anneeId,
            'demographie' => $demographie,
            'pedagogie' => $pedagogie,
            'finances' => $finances,
        ]);
    }

    /**
     * Affiche l'espace des statistiques avancées et rapports réglementaires.
     *
     * @param Request $request
     * @param StatistiqueConseilService $statistiqueService
     * @return InertiaResponse
     */
    public function statistiquesAvancees(Request $request, StatistiqueConseilService $statistiqueService): InertiaResponse
    {
        abort_if(! $request->user()?->can('statistiques.view') && ! $request->user()?->hasRole('super-admin'), 403);

        $context = $this->resolveEtablissementContext($request, ['institution']);
        $etablissements = $context['etablissements'];
        $currentEtablissement = $context['selectedEtablissement'];
        $scope = $this->resolveStatsScope($request, $context);
        $targetIds = $scope['targetIds'];

        $annees = AnneeScolaire::orderByDesc('date_debut')->get();
        $anneeActive = AnneeScolaire::current() ?? $annees->first();
        $anneeId = $request->integer('annee_id', $anneeActive?->id ?? 0);

        $classes = Classe::with('niveau.cycle.section.etablissement')
            ->whereHas('niveau.cycle.section', fn ($q) => $q->whereIn('etablissement_id', $targetIds))
            ->orderBy('nom')
            ->get();

        // Sécurisation multi-campus : réinitialiser classe_id si la classe n'appartient pas au périmètre actif
        $requestedClasseId = $request->integer('classe_id') ?: null;
        $selectedClasseId = ($requestedClasseId && $classes->contains('id', $requestedClasseId))
            ? $requestedClasseId
            : null;

        // Disambiguiser les noms de classes homonymes lorsque plusieurs établissements sont agrégés
        $nameOccurrences = $scope['isAllEtablissements'] ? $classes->countBy('nom') : collect();
        $classesOptions = $classes->map(function ($cls) use ($scope, $nameOccurrences) {
            $etabCode = $cls->niveau?->cycle?->section?->etablissement?->code;
            $nom = ($scope['isAllEtablissements'] && ($nameOccurrences->get($cls->nom, 0) > 1) && $etabCode)
                ? "{$cls->nom} ({$etabCode})"
                : $cls->nom;

            return [
                'id' => $cls->id,
                'nom' => $nom,
            ];
        })->values();

        // Données complètes sur le(s) campus sélectionné(s)
        $demographie = $statistiqueService->getStatistiquesDemographiques($targetIds, $anneeId);
        $pedagogie = $statistiqueService->getStatistiquesPedagogiques($targetIds, $anneeId);
        $finances = $statistiqueService->getStatistiquesFinancieres($targetIds, $anneeId);
        $listesOfficielles = $statistiqueService->getListesOfficiellesExamens($targetIds, $selectedClasseId, $anneeId);

        return Inertia::render('administration/statistiques/index', [
            'supportsAllEtablissements' => $scope['supportsAllEtablissements'],
            'isAllEtablissements' => $scope['isAllEtablissements'],
            'etablissements' => $etablissements,
            'currentEtablissement' => $currentEtablissement,
            'annees' => $annees,
            'currentAnneeId' => $anneeId,
            'classes' => $classesOptions,
            'selectedClasseId' => $selectedClasseId,
            'demographie' => $demographie,
            'pedagogie' => $pedagogie,
            'finances' => $finances,
            'listesOfficielles' => $listesOfficielles,
            'filters' => [
                ...$request->only(['tab']),
                'classe_id' => $selectedClasseId,
            ],
        ]);
    }

    /**
     * Génère et télécharge le bordereau officiel d'examen ministériel (Gesco 15.1).
     *
     * @param Request $request
     * @param StatistiqueConseilService $statistiqueService
     * @return Response
     */
    public function exportBordereau(Request $request, StatistiqueConseilService $statistiqueService): Response
    {
        abort_if(! $request->user()?->can('statistiques.view') && ! $request->user()?->hasRole('super-admin'), 403);

        $context = $this->resolveEtablissementContext($request, ['institution']);
        $scope = $this->resolveStatsScope($request, $context);
        $targetIds = $scope['targetIds'];
        $etablissement = $context['selectedEtablissement'] ?? Etablissement::with('institution')->findOrFail($context['etablissementId']);

        $classeId = $request->integer('classe_id') ?: null;
        // Restreint la classe sélectionnée aux campus autorisés du périmètre actif
        $classe = $classeId
            ? Classe::whereHas('niveau.cycle.section', fn ($q) => $q->whereIn('etablissement_id', $targetIds))->find($classeId)
            : null;
        $annee = AnneeScolaire::find($request->integer('annee_id')) ?? AnneeScolaire::current();

        $eleves = $statistiqueService->getListesOfficiellesExamens($targetIds, $classe?->id, $annee?->id);

        $pdf = Pdf::loadView('pdf.bordereau_officiel', [
            'etablissement' => $etablissement,
            'isAllEtablissements' => $scope['isAllEtablissements'],
            'classe' => $classe,
            'annee' => $annee,
            'eleves' => $eleves,
            'dateGeneration' => now()->format('d/m/Y H:i'),
        ])->setPaper('a4', 'landscape');

        $prefixePortee = $classe ? $classe->code : ($scope['isAllEtablissements'] ? 'Tous_Etablissements' : 'Etablissement');
        $nomFichier = 'Bordereau_Officiel_' . $prefixePortee . '_' . date('Ymd_His') . '.pdf';

        return $pdf->stream($nomFichier);
    }

    /**
     * Détermine le périmètre effectif (campus unique ou « Tous les établissements ») pour le tableau de bord et les statistiques.
     *
     * @param Request $request Requête HTTP entrante.
     * @param array<string, mixed> $context Contexte résolu par `resolveEtablissementContext`.
     * @return array{
     *     supportsAllEtablissements: bool,
     *     isAllEtablissements: bool,
     *     targetIds: array<int, int>
     * }
     */
    protected function resolveStatsScope(Request $request, array $context): array
    {
        $etablissements = $context['etablissements'];
        $canAccessAll = (bool) ($context['canAccessAll'] ?? $context['isSuperAdmin'] ?? false);
        $supportsAll = $canAccessAll && $etablissements->count() > 1;
        $requestedId = $request->query('etablissement_id') ?? $request->input('etablissement_id');

        $isAll = false;
        if ($supportsAll) {
            if ($requestedId !== null && $requestedId !== '') {
                $isAll = ($requestedId === 'all');
                if ($request->hasSession()) {
                    $request->session()->put('current_etablissement_scope', $isAll ? 'all' : 'single');
                }
            } elseif ($request->hasSession()) {
                $isAll = $request->session()->get('current_etablissement_scope') === 'all';
            }
        }

        $targetIds = $isAll
            ? $etablissements->pluck('id')->map(fn ($id) => (int) $id)->values()->all()
            : ($context['selectedEtablissement'] ? [(int) $context['selectedEtablissement']->id] : []);

        return [
            'supportsAllEtablissements' => $supportsAll,
            'isAllEtablissements' => $isAll,
            'targetIds' => $targetIds,
        ];
    }
}
