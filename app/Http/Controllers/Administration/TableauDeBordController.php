<?php

namespace App\Http\Controllers\Administration;

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
        $etablissements = Etablissement::with('institution')->get();
        $currentEtablissement = $request->has('etablissement_id')
            ? Etablissement::find($request->integer('etablissement_id'))
            : $etablissements->first();

        $etablissementId = $currentEtablissement?->id ?? 0;

        $annees = AnneeScolaire::orderByDesc('date_debut')->get();
        $anneeActive = AnneeScolaire::current() ?? $annees->first();
        $anneeId = $request->integer('annee_id', $anneeActive?->id ?? 0);

        // 1. Statistiques Démographiques (15.1)
        $demographie = $statistiqueService->getStatistiquesDemographiques($etablissementId, $anneeId);

        // 2. Statistiques Pédagogiques & Décrochage (15.3)
        $pedagogie = $statistiqueService->getStatistiquesPedagogiques($etablissementId, $anneeId);

        // 3. Statistiques Financières (15.3)
        $finances = $statistiqueService->getStatistiquesFinancieres($etablissementId, $anneeId);

        return Inertia::render('dashboard', [
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

        $etablissements = Etablissement::with('institution')->get();
        $currentEtablissement = $request->has('etablissement_id')
            ? Etablissement::find($request->integer('etablissement_id'))
            : $etablissements->first();

        $etablissementId = $currentEtablissement?->id ?? 0;

        $annees = AnneeScolaire::orderByDesc('date_debut')->get();
        $anneeActive = AnneeScolaire::current() ?? $annees->first();
        $anneeId = $request->integer('annee_id', $anneeActive?->id ?? 0);

        $classes = Classe::whereHas('niveau.cycle.section', fn ($q) => $q->where('etablissement_id', $etablissementId))
            ->orderBy('nom')
            ->get();

        $selectedClasseId = $request->integer('classe_id') ?: null;

        // Données complètes
        $demographie = $statistiqueService->getStatistiquesDemographiques($etablissementId, $anneeId);
        $pedagogie = $statistiqueService->getStatistiquesPedagogiques($etablissementId, $anneeId);
        $finances = $statistiqueService->getStatistiquesFinancieres($etablissementId, $anneeId);
        $listesOfficielles = $statistiqueService->getListesOfficiellesExamens($etablissementId, $selectedClasseId, $anneeId);

        return Inertia::render('administration/statistiques/index', [
            'etablissements' => $etablissements,
            'currentEtablissement' => $currentEtablissement,
            'annees' => $annees,
            'currentAnneeId' => $anneeId,
            'classes' => $classes,
            'selectedClasseId' => $selectedClasseId,
            'demographie' => $demographie,
            'pedagogie' => $pedagogie,
            'finances' => $finances,
            'listesOfficielles' => $listesOfficielles,
            'filters' => $request->only(['tab', 'classe_id']),
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

        $etablissement = Etablissement::with('institution')->findOrFail($request->integer('etablissement_id'));
        $classeId = $request->integer('classe_id') ?: null;
        $classe = $classeId ? Classe::find($classeId) : null;
        $annee = AnneeScolaire::find($request->integer('annee_id')) ?? AnneeScolaire::current();

        $eleves = $statistiqueService->getListesOfficiellesExamens($etablissement->id, $classeId, $annee?->id);

        $pdf = Pdf::loadView('pdf.bordereau_officiel', [
            'etablissement' => $etablissement,
            'classe' => $classe,
            'annee' => $annee,
            'eleves' => $eleves,
            'dateGeneration' => now()->format('d/m/Y H:i'),
        ])->setPaper('a4', 'landscape');

        $nomFichier = 'Bordereau_Officiel_' . ($classe ? $classe->code : 'Etablissement') . '_' . date('Ymd_His') . '.pdf';

        return $pdf->stream($nomFichier);
    }
}
