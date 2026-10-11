<?php

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\DisciplineBulletin;
use App\Models\Evaluation;
use App\Models\Inscription;
use App\Models\Periode;
use App\Services\BulletinCalculationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Contrôleur pour la Gestion et la Génération des Bulletins Scolaires (Module 10 Gesco V1.1).
 *
 * Prend en charge les 3 vagues de bulletins officiels :
 * 1. Bulletins Séquentiels (par évaluation / séquence individuelle).
 * 2. Bulletins Trimestriels (bilan de chaque trimestre avec synthèse des évaluations).
 * 3. Bulletins Annuels (bilan complet de fin d'année, MGA, et décision du conseil de classe).
 *
 * Inclut également la configurabilité du format (avec compétences APC ou format standard).
 */
class BulletinController extends Controller implements HasMiddleware
{
    /**
     * Définition de la matrice des middlewares d'autorisation pour le module Bulletins Scolaires.
     *
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:bulletins.view', only: ['index', 'previsualiser']),
            new Middleware('can:bulletins.generate', only: ['telechargerPdf', 'telechargerClassePdf', 'sauvegarderDiscipline']),
        ];
    }

    /**
     * Service de calcul des résultats pédagogiques.
     */
    protected BulletinCalculationService $calculationService;

    /**
     * Constructeur injectant le service de calcul.
     */
    public function __construct(BulletinCalculationService $calculationService)
    {
        $this->calculationService = $calculationService;
    }

    /**
     * Centre de consultation et de gestion des bulletins.
     */
    public function index(Request $request): InertiaResponse
    {
        $annees = AnneeScolaire::orderByDesc('date_debut')->get();
        $anneeActive = AnneeScolaire::current() ?? $annees->first();
        $anneeId = $request->query('annee_id', $anneeActive?->id);

        $user = $request->user();
        $isSuperAdmin = $user ? ($user->hasRole('super-admin') || $user->email === 'admin@gmail.com') : false;
        $userPersonnel = $user?->personnel ?? ($user?->email ? \App\Models\Personnel::where('email', $user->email)->first() : null);

        $allEtablissements = \App\Models\Etablissement::select('id', 'nom', 'code', 'ordre_enseignement')->orderBy('nom')->get();

        if (! $isSuperAdmin && $userPersonnel?->etablissement_id) {
            $etablissementId = (int) $userPersonnel->etablissement_id;
            $etablissements = $allEtablissements->where('id', $etablissementId)->values();
        } else {
            $etablissementId = $request->query('etablissement_id')
                ? (int) $request->query('etablissement_id')
                : ($request->session()->get('current_etablissement_id') ?: $allEtablissements->first()?->id);
            $etablissements = $allEtablissements;
        }

        $periodesQuery = Periode::with(['evaluations' => fn ($q) => $q->orderBy('ordre')])
            ->where('annee_scolaire_id', $anneeId)
            ->orderBy('date_debut');

        $classesQuery = Classe::with(['niveau'])
            ->where('is_active', true)
            ->orderBy('nom');

        if ($etablissementId) {
            $periodesQuery->where('etablissement_id', $etablissementId);
            $classesQuery->where('etablissement_id', $etablissementId);
        }

        // Pour un enseignant (non-gestionnaire) : restreindre aux classes où il a des attributions
        if (! $isSuperAdmin && $userPersonnel && $user->hasRole('enseignant') && ! $user->hasRole(['directeur-etudes', 'admin', 'censeur'])) {
            $classesEnseignantIds = \App\Models\Attribution::where('personnel_id', $userPersonnel->id)
                ->where('annee_scolaire_id', $anneeId)
                ->pluck('classe_id');
            $classesQuery->whereIn('id', $classesEnseignantIds);
        }

        $periodes = $periodesQuery->get();
        $classes = $classesQuery->get();

        $typeBulletin = $request->query('type_bulletin', 'sequentiel');
        $classeId = $request->query('classe_id');
        $periodeId = $request->query('periode_id');
        $evaluationId = $request->query('evaluation_id');
        $modeBulletin = $request->query('mode_bulletin');

        // Pré-sélection logique de la 1ère séquence (Évaluation 1) ou du 1er trimestre si non spécifié
        if ($typeBulletin === 'sequentiel' && ! $evaluationId) {
            $firstEval = $periodes->flatMap->evaluations->first();
            $evaluationId = $firstEval ? (string) $firstEval->id : null;
        } elseif ($typeBulletin === 'trimestriel' && ! $periodeId) {
            $firstPeriode = $periodes->first();
            $periodeId = $firstPeriode ? (string) $firstPeriode->id : null;
        }

        $donneesClasse = null;

        if ($classeId) {
            $classe = Classe::with(['etablissement.institution', 'niveau.cycle.section', 'professeurPrincipal'])
                ->findOrFail($classeId);

            // Le format officiel du bulletin est strictement dicté par la configuration globale de l'établissement
            $etablissement = $classe->etablissement ?? $classe->niveau?->cycle?->section?->etablissement;
            $modeBulletin = $etablissement?->mode_bulletin_defaut ?? 'sans_competences';

            if ($typeBulletin === 'sequentiel' && $evaluationId) {
                $evaluation = Evaluation::with('periode.anneeScolaire')->findOrFail($evaluationId);
                $donneesClasse = $this->calculationService->calculerSequentiel($classe, $evaluation, $modeBulletin);
            } elseif ($typeBulletin === 'annuel' && $anneeId) {
                $annee = AnneeScolaire::findOrFail($anneeId);
                $donneesClasse = $this->calculationService->calculerAnnuel($classe, $annee, $modeBulletin);
            } elseif ($typeBulletin === 'trimestriel' && $periodeId) {
                $periode = Periode::with('anneeScolaire')->findOrFail($periodeId);
                $donneesClasse = $this->calculationService->calculerTrimestre($classe, $periode, null, $modeBulletin);
            }
        }

        return Inertia::render('administration/bulletins/index', [
            'annees' => $annees,
            'anneeActiveId' => (int) $anneeId,
            'etablissements' => $etablissements,
            'periodes' => $periodes,
            'classes' => $classes,
            'donneesClasse' => $donneesClasse,
            'filters' => [
                'type_bulletin' => $typeBulletin,
                'annee_id' => $anneeId,
                'etablissement_id' => $etablissementId,
                'classe_id' => $classeId,
                'periode_id' => $periodeId,
                'evaluation_id' => $evaluationId,
                'mode_bulletin' => $modeBulletin ?? 'sans_competences',
            ],
        ]);
    }

    /**
     * Prévisualise le bulletin officiel scellé d'un élève dans un nouvel onglet (affichage plein écran sans téléchargement forcé).
     *
     * @return Response|View
     */
    public function previsualiser(Request $request, Inscription $inscription, ?Periode $periode = null)
    {
        $classe = $inscription->classe()->with(['niveau.cycle.section.etablissement.institution', 'professeurPrincipal'])->firstOrFail();
        $mode = $request->query('mode', $classe->niveau->cycle->section->etablissement->mode_bulletin_defaut ?? 'sans_competences');
        $type = $request->query('type_bulletin', 'sequentiel');

        if ($type === 'sequentiel') {
            $evaluationId = $request->query('evaluation_id');
            $evaluation = Evaluation::with('periode.anneeScolaire')->findOrFail($evaluationId);
            $resultat = $this->calculationService->calculerSequentiel($classe, $evaluation, $mode);
            $eleveData = collect($resultat['eleves'])->firstWhere('inscription_id', $inscription->id);

            abort_if(! $eleveData, 404, 'Données introuvables pour cet élève.');

            $viewData = [
                'eleve' => $eleveData,
                'classe' => $resultat['classe'],
                'evaluation' => $resultat['evaluation'],
                'periode' => $resultat['periode'],
                'annee_scolaire' => $resultat['annee_scolaire'],
                'stats_classe' => $resultat['stats_classe'],
                'mode_bulletin' => $mode,
            ];
            $viewName = 'bulletins.bulletin_sequentiel';
            $nomFichier = 'Apercu_Bulletin_Seq_'.str_replace(['/', ' '], '_', $eleveData['matricule']).'_'.$evaluation->code.'.pdf';
        } elseif ($type === 'annuel') {
            $anneeId = $request->query('annee_id', $inscription->annee_scolaire_id);
            $annee = AnneeScolaire::findOrFail($anneeId);
            $resultat = $this->calculationService->calculerAnnuel($classe, $annee, $mode);
            $eleveData = collect($resultat['eleves'])->firstWhere('inscription_id', $inscription->id);

            abort_if(! $eleveData, 404, 'Données introuvables pour cet élève.');

            $viewData = [
                'eleve' => $eleveData,
                'classe' => $resultat['classe'],
                'periodes' => $resultat['periodes'],
                'annee_scolaire' => $resultat['annee_scolaire'],
                'stats_classe' => $resultat['stats_classe'],
                'mode_bulletin' => $mode,
            ];
            $viewName = 'bulletins.bulletin_annuel';
            $safeAnneeCode = str_replace(['/', '\\', ' '], '-', $annee->code);
            $nomFichier = 'Apercu_Bulletin_Annuel_'.str_replace(['/', ' '], '_', $eleveData['matricule']).'_'.$safeAnneeCode.'.pdf';
        } else {
            // Trimestriel par défaut
            $periode = $periode ?? Periode::with('anneeScolaire')->findOrFail($request->query('periode_id'));
            $resultat = $this->calculationService->calculerTrimestre($classe, $periode, null, $mode);
            $eleveData = collect($resultat['eleves'])->firstWhere('inscription_id', $inscription->id);

            abort_if(! $eleveData, 404, 'Données introuvables pour cet élève.');

            $viewData = [
                'eleve' => $eleveData,
                'classe' => $resultat['classe'],
                'periode' => $resultat['periode'],
                'annee_scolaire' => $resultat['annee_scolaire'],
                'stats_classe' => $resultat['stats_classe'],
                'mode_bulletin' => $mode,
            ];
            $viewName = 'bulletins.bulletin_trimestriel';
            $nomFichier = 'Apercu_Bulletin_Trim_'.str_replace(['/', ' '], '_', $eleveData['matricule']).'_'.$periode->code.'.pdf';
        }

        // Si le format HTML brut est expressément requis
        if ($request->query('format') === 'html') {
            return view($viewName, $viewData);
        }

        // Flux PDF direct en mode inline : affichage pleine page natif dans le nouvel onglet sans téléchargement forcé
        $pdf = Pdf::loadView($viewName, $viewData);
        $pdf->setPaper('a4', 'portrait')
            ->setOption('isRemoteEnabled', true)
            ->setOption('isHtml5ParserEnabled', true);

        return $pdf->stream($nomFichier, ['Attachment' => false]);
    }

    /**
     * Télécharge le bulletin officiel scellé d'un élève au format PDF.
     */
    public function telechargerPdf(Request $request, Inscription $inscription, ?Periode $periode = null): Response
    {
        $classe = $inscription->classe()->with(['niveau.cycle.section.etablissement.institution', 'professeurPrincipal'])->firstOrFail();
        $mode = $request->query('mode', $classe->niveau->cycle->section->etablissement->mode_bulletin_defaut ?? 'sans_competences');
        $type = $request->query('type_bulletin', 'sequentiel');

        if ($type === 'sequentiel') {
            $evaluationId = $request->query('evaluation_id');
            $evaluation = Evaluation::with('periode.anneeScolaire')->findOrFail($evaluationId);
            $resultat = $this->calculationService->calculerSequentiel($classe, $evaluation, $mode);
            $eleveData = collect($resultat['eleves'])->firstWhere('inscription_id', $inscription->id);
            abort_if(! $eleveData, 404, 'Données introuvables pour cet élève.');

            $viewData = [
                'eleve' => $eleveData,
                'classe' => $resultat['classe'],
                'evaluation' => $resultat['evaluation'],
                'periode' => $resultat['periode'],
                'annee_scolaire' => $resultat['annee_scolaire'],
                'stats_classe' => $resultat['stats_classe'],
                'mode_bulletin' => $mode,
            ];
            $viewName = 'bulletins.bulletin_sequentiel';
            $nomFichier = 'Bulletin_Seq_'.str_replace(['/', ' '], '_', $eleveData['matricule']).'_'.$evaluation->code.'.pdf';
        } elseif ($type === 'annuel') {
            $anneeId = $request->query('annee_id', $inscription->annee_scolaire_id);
            $annee = AnneeScolaire::findOrFail($anneeId);
            $resultat = $this->calculationService->calculerAnnuel($classe, $annee, $mode);
            $eleveData = collect($resultat['eleves'])->firstWhere('inscription_id', $inscription->id);
            abort_if(! $eleveData, 404, 'Données introuvables pour cet élève.');

            $viewData = [
                'eleve' => $eleveData,
                'classe' => $resultat['classe'],
                'periodes' => $resultat['periodes'],
                'annee_scolaire' => $resultat['annee_scolaire'],
                'stats_classe' => $resultat['stats_classe'],
                'mode_bulletin' => $mode,
            ];
            $viewName = 'bulletins.bulletin_annuel';
            $safeAnneeCode = str_replace(['/', '\\', ' '], '-', $annee->code);
            $nomFichier = 'Bulletin_Annuel_'.str_replace(['/', ' '], '_', $eleveData['matricule']).'_'.$safeAnneeCode.'.pdf';
        } else {
            $periode = $periode ?? Periode::with('anneeScolaire')->findOrFail($request->query('periode_id'));
            $resultat = $this->calculationService->calculerTrimestre($classe, $periode, null, $mode);
            $eleveData = collect($resultat['eleves'])->firstWhere('inscription_id', $inscription->id);
            abort_if(! $eleveData, 404, 'Données introuvables pour cet élève.');

            $viewData = [
                'eleve' => $eleveData,
                'classe' => $resultat['classe'],
                'periode' => $resultat['periode'],
                'annee_scolaire' => $resultat['annee_scolaire'],
                'stats_classe' => $resultat['stats_classe'],
                'mode_bulletin' => $mode,
            ];
            $viewName = 'bulletins.bulletin_trimestriel';
            $nomFichier = 'Bulletin_Trim_'.str_replace(['/', ' '], '_', $eleveData['matricule']).'_'.$periode->code.'.pdf';
        }

        $pdf = Pdf::loadView($viewName, $viewData);
        $pdf->setPaper('a4', 'portrait')
            ->setOption('isRemoteEnabled', true)
            ->setOption('isHtml5ParserEnabled', true);

        return $pdf->download($nomFichier);
    }

    /**
     * Télécharge l'ensemble des bulletins de la classe dans un seul fichier PDF (Cahier de bulletins).
     */
    public function telechargerClassePdf(Request $request, Classe $classe, ?Periode $periode = null): Response
    {
        $classeWithRel = Classe::with(['niveau.cycle.section.etablissement.institution', 'professeurPrincipal'])
            ->findOrFail($classe->id);

        $mode = $request->query('mode', $classeWithRel->niveau->cycle->section->etablissement->mode_bulletin_defaut ?? 'sans_competences');
        $type = $request->query('type_bulletin', 'sequentiel');

        if ($type === 'sequentiel') {
            $evaluationId = $request->query('evaluation_id');
            $evaluation = Evaluation::with('periode.anneeScolaire')->findOrFail($evaluationId);
            $resultat = $this->calculationService->calculerSequentiel($classeWithRel, $evaluation, $mode);
            $nomFichier = 'Cahier_Bulletins_Seq_'.$classe->code.'_'.$evaluation->code.'.pdf';
            $viewData = [
                'eleves' => $resultat['eleves'],
                'classe' => $resultat['classe'],
                'evaluation' => $resultat['evaluation'],
                'periode' => $resultat['periode'],
                'annee_scolaire' => $resultat['annee_scolaire'],
                'stats_classe' => $resultat['stats_classe'],
                'mode_bulletin' => $mode,
                'type_bulletin' => 'sequentiel',
            ];
        } elseif ($type === 'annuel') {
            $anneeId = $request->query('annee_id');
            $annee = AnneeScolaire::findOrFail($anneeId);
            $safeClasseCode = str_replace(['/', '\\', ' '], '-', $classe->code);
            $safeAnneeCode = str_replace(['/', '\\', ' '], '-', $annee->code);
            $nomFichier = 'Cahier_Bulletins_Annuel_'.$safeClasseCode.'_'.$safeAnneeCode.'.pdf';
            $viewData = [
                'eleves' => $resultat['eleves'],
                'classe' => $resultat['classe'],
                'periodes' => $resultat['periodes'],
                'annee_scolaire' => $resultat['annee_scolaire'],
                'stats_classe' => $resultat['stats_classe'],
                'mode_bulletin' => $mode,
                'type_bulletin' => 'annuel',
            ];
        } else {
            $periode = $periode ?? Periode::with('anneeScolaire')->findOrFail($request->query('periode_id'));
            $resultat = $this->calculationService->calculerTrimestre($classeWithRel, $periode, null, $mode);
            $nomFichier = 'Cahier_Bulletins_Trim_'.$classe->code.'_'.$periode->code.'.pdf';
            $viewData = [
                'eleves' => $resultat['eleves'],
                'classe' => $resultat['classe'],
                'periode' => $resultat['periode'],
                'annee_scolaire' => $resultat['annee_scolaire'],
                'stats_classe' => $resultat['stats_classe'],
                'mode_bulletin' => $mode,
                'type_bulletin' => 'trimestriel',
            ];
        }

        $pdf = Pdf::loadView('bulletins.bulletin_classe_bundle', $viewData);
        $pdf->setPaper('a4', 'portrait')
            ->setOption('isRemoteEnabled', true)
            ->setOption('isHtml5ParserEnabled', true);

        return $pdf->download($nomFichier);
    }

    /**
     * Enregistre ou met à jour les données disciplinaires d'un élève pour le bulletin.
     */
    public function sauvegarderDiscipline(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'inscription_id' => 'required|exists:inscriptions,id',
            'periode_id' => 'required|exists:periodes,id',
            'absences_justifiees' => 'nullable|integer|min:0',
            'absences_non_justifiees' => 'nullable|integer|min:0',
            'retards' => 'nullable|integer|min:0',
            'consignes' => 'nullable|integer|min:0',
            'avertissements_conduite' => 'nullable|integer|min:0',
            'blames_conduite' => 'nullable|integer|min:0',
            'exclusions_jours' => 'nullable|integer|min:0',
            'decision_conseil' => 'nullable|string',
        ]);

        DisciplineBulletin::updateOrCreate(
            [
                'inscription_id' => $validated['inscription_id'],
                'periode_id' => $validated['periode_id'],
            ],
            [
                'absences_justifiees' => $validated['absences_justifiees'] ?? 0,
                'absences_non_justifiees' => $validated['absences_non_justifiees'] ?? 0,
                'retards' => $validated['retards'] ?? 0,
                'consignes' => $validated['consignes'] ?? 0,
                'avertissements_conduite' => $validated['avertissements_conduite'] ?? 0,
                'blames_conduite' => $validated['blames_conduite'] ?? 0,
                'exclusions_jours' => $validated['exclusions_jours'] ?? 0,
                'decision_conseil' => $validated['decision_conseil'] ?? null,
            ]
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Données disciplinaires enregistrées avec succès.',
            ]);
        }

        return redirect()->back()->with('success', 'Données disciplinaires enregistrées avec succès.');
    }
}
