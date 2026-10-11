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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Contrôleur pour la Gestion des Délibérations du Conseil de Classe et PV (Module 15 & Module 2.6).
 *
 * Fonctionnalités :
 * - Consultation de la grille de délibération trimestrielle ou annuelle d'une classe.
 * - Saisie des appréciations globales du conseil et ajustement des mentions/décisions.
 * - Validation humaine obligatoire par la Direction (Censeur / Proviseur - Module 2.6 Étape B).
 * - Édition du Procès-Verbal (PV) officiel du conseil de classe scellé pour signature.
 *
 * Utilise ScopesEtablissement pour cloisonner strictement les délibérations par campus.
 */
class ConseilClasseController extends Controller implements HasMiddleware
{
    use ScopesEtablissement;

    /**
     * Définition de la matrice des middlewares d'autorisation pour le module Délibérations & Conseil de Classe.
     *
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:deliberations.view', only: ['index', 'exportPv']),
            new Middleware('can:deliberations.saisir|deliberations.manage', only: ['sauvegarder']),
            new Middleware('can:deliberations.valider|deliberations.manage', only: ['validerPromotion']),
        ];
    }

    /**
     * Affiche l'interface de délibération du conseil de classe.
     *
     * @param Request $request
     * @param StatistiqueConseilService $statistiqueService
     * @return InertiaResponse
     */
    public function index(Request $request, StatistiqueConseilService $statistiqueService): InertiaResponse
    {
        abort_if(! $request->user()?->can('deliberations.view') && ! $request->user()?->hasRole('super-admin'), 403);

        // Résolution hermétique du campus selon les prérogatives de l'utilisateur
        $context = $this->resolveEtablissementContext($request, ['institution']);
        $etablissements = $context['etablissements'];
        $currentEtablissement = $context['selectedEtablissement'];
        $etablissementId = $context['etablissementId'] ?? 0;

        $annees = AnneeScolaire::orderByDesc('date_debut')->get();
        $anneeActive = AnneeScolaire::current() ?? $annees->first();
        $anneeId = $request->integer('annee_id', $anneeActive?->id ?? 0);
        $annee = AnneeScolaire::find($anneeId) ?? $anneeActive;

        $classes = Classe::whereHas('niveau.cycle.section', fn ($q) => $q->where('etablissement_id', $etablissementId))
            ->with(['niveau'])
            ->orderBy('nom')
            ->get();

        $selectedClasseId = $request->integer('classe_id') ?: $classes->first()?->id;
        $classe = $selectedClasseId ? Classe::with(['niveau.cycle.section.etablissement', 'professeurPrincipal'])->find($selectedClasseId) : null;

        $periodes = Periode::where('annee_scolaire_id', $anneeId)
            ->orderBy('ordre')
            ->get();

        $typeSession = $request->input('type', 'annuel'); // 'trimestre' ou 'annuel'
        $selectedPeriodeId = $request->integer('periode_id');
        $periode = ($typeSession === 'trimestre' && $selectedPeriodeId) ? Periode::find($selectedPeriodeId) : null;

        $deliberationData = null;
        if ($classe && $annee) {
            $deliberationData = $statistiqueService->preparerDeliberation($classe, $periode, $annee);
        }

        return Inertia::render('administration/deliberations/index', [
            'etablissements' => $etablissements,
            'currentEtablissement' => $currentEtablissement,
            'annees' => $annees,
            'currentAnneeId' => $anneeId,
            'classes' => $classes,
            'selectedClasseId' => $selectedClasseId,
            'periodes' => $periodes,
            'selectedPeriodeId' => $selectedPeriodeId,
            'typeSession' => $typeSession,
            'deliberationData' => $deliberationData,
            'canValider' => $request->user()?->can('deliberations.valider') || $request->user()?->can('deliberations.manage') || $request->user()?->hasRole('super-admin'),
            'canManage' => $request->user()?->can('deliberations.saisir') || $request->user()?->can('deliberations.manage') || $request->user()?->hasRole('super-admin'),
            'canSaisir' => $request->user()?->can('deliberations.saisir') || $request->user()?->can('deliberations.manage') || $request->user()?->hasRole('super-admin'),
        ]);
    }

    /**
     * Enregistre les délibérations et décisions votées par le conseil de classe (Brouillon).
     *
     * @param Request $request
     * @param StatistiqueConseilService $statistiqueService
     * @return RedirectResponse
     */
    public function sauvegarder(Request $request, StatistiqueConseilService $statistiqueService): RedirectResponse
    {
        abort_if(! $request->user()?->can('deliberations.saisir') && ! $request->user()?->can('deliberations.manage') && ! $request->user()?->hasRole('super-admin'), 403);

        $validated = $request->validate([
            'classe_id' => 'required|exists:classes,id',
            'annee_id' => 'required|exists:annees_scolaires,id',
            'periode_id' => 'nullable|exists:periodes,id',
            'lignes' => 'required|array|min:1',
            'lignes.*.inscription_id' => 'required|exists:inscriptions,id',
            'lignes.*.moyenne_obtenue' => 'required|numeric',
            'lignes.*.rang' => 'nullable',
            'lignes.*.mention_obtenue' => 'required|in:aucune,tableau_honneur,encouragements,felicitations',
            'lignes.*.mention_bloquee' => 'boolean',
            'lignes.*.motif_blocage_mention' => 'nullable|string',
            'lignes.*.statut_propose' => 'required|in:promu,redouble,exclu,en_attente',
            'lignes.*.statut_retenu' => 'required|in:promu,redouble,exclu,en_attente',
            'lignes.*.appreciation_globale' => 'nullable|string|max:500',
            'lignes.*.decision_conseil' => 'nullable|string|max:150',
            'lignes.*.observations' => 'nullable|string|max:500',
        ]);

        $classe = Classe::with('niveau.cycle.section.etablissement')->findOrFail($validated['classe_id']);
        $context = $this->resolveEtablissementContext($request);
        if (! $context['isSuperAdmin'] && (int) $classe->etablissement_id !== (int) $context['etablissementId']) {
            abort(403, 'Accès non autorisé à une classe d\'un autre établissement.');
        }

        $annee = AnneeScolaire::findOrFail($validated['annee_id']);
        $periode = ! empty($validated['periode_id']) ? Periode::find($validated['periode_id']) : null;

        $count = $statistiqueService->sauvegarderDeliberations(
            $classe,
            $periode,
            $annee,
            $validated['lignes'],
            $request->user()
        );

        return back()->with('success', "{$count} délibérations enregistrées avec succès pour la classe {$classe->nom}.");
    }

    /**
     * Valide définitivement la promotion des élèves de fin d'année (Gesco 2.6 Étape B - Validation humaine obligatoire).
     *
     * @param Request $request
     * @param StatistiqueConseilService $statistiqueService
     * @return RedirectResponse
     */
    public function validerPromotion(Request $request, StatistiqueConseilService $statistiqueService): RedirectResponse
    {
        abort_if(! $request->user()?->can('deliberations.valider') && ! $request->user()?->hasRole('super-admin'), 403);

        $validated = $request->validate([
            'classe_id' => 'required|exists:classes,id',
            'annee_id' => 'required|exists:annees_scolaires,id',
            'decisions' => 'required|array|min:1',
            'decisions.*.inscription_id' => 'required|exists:inscriptions,id',
            'decisions.*.statut_retenu' => 'required|in:promu,redouble,exclu',
            'decisions.*.appreciation_globale' => 'nullable|string',
        ]);

        $classe = Classe::with('niveau.cycle.section.etablissement')->findOrFail($validated['classe_id']);
        $context = $this->resolveEtablissementContext($request);
        if (! $context['isSuperAdmin'] && (int) $classe->etablissement_id !== (int) $context['etablissementId']) {
            abort(403, 'Accès non autorisé à une classe d\'un autre établissement.');
        }

        $annee = AnneeScolaire::findOrFail($validated['annee_id']);

        $count = $statistiqueService->validerPromotions(
            $classe,
            $annee,
            $validated['decisions'],
            $request->user()
        );

        return back()->with('success', "Promotion validée officiellement pour les {$count} élèves de la classe {$classe->nom}. Statuts de fin d'année scellés.");
    }

    /**
     * Génère le Procès-Verbal officiel scellé du Conseil de Classe en PDF (Gesco 15.2).
     *
     * @param Request $request
     * @param StatistiqueConseilService $statistiqueService
     * @return Response
     */
    public function exportPv(Request $request, StatistiqueConseilService $statistiqueService): Response
    {
        abort_if(! $request->user()?->can('deliberations.view') && ! $request->user()?->hasRole('super-admin'), 403);

        $classe = Classe::with(['niveau.cycle.section.etablissement.institution', 'professeurPrincipal'])->findOrFail($request->integer('classe_id'));
        $context = $this->resolveEtablissementContext($request);
        if (! $context['isSuperAdmin'] && (int) $classe->etablissement_id !== (int) $context['etablissementId']) {
            abort(403, 'Accès non autorisé à une classe d\'un autre établissement.');
        }

        $annee = AnneeScolaire::findOrFail($request->integer('annee_id'));
        $periodeId = $request->integer('periode_id');
        $periode = $periodeId ? Periode::find($periodeId) : null;

        $deliberation = $statistiqueService->preparerDeliberation($classe, $periode, $annee);

        $pdf = Pdf::loadView('pdf.pv_conseil_classe', [
            'classe' => $classe,
            'annee' => $annee,
            'periode' => $periode,
            'deliberation' => $deliberation,
            'dateGeneration' => now()->format('d/m/Y H:i'),
        ])->setPaper('a4', 'landscape');

        $sessionSlug = $periode ? 'Trimestre_' . $periode->ordre : 'Bilan_Annuel';
        $nomFichier = 'PV_Conseil_' . $classe->code . '_' . $sessionSlug . '_' . date('Ymd_His') . '.pdf';

        return $pdf->stream($nomFichier);
    }
}
