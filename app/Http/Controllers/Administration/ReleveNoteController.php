<?php

namespace App\Http\Controllers\Administration;

use App\Concerns\ScopesEtablissement;
use App\Http\Controllers\Controller;
use App\Models\AnneeScolaire;
use App\Models\Attribution;
use App\Models\Classe;
use App\Models\Evaluation;
use App\Models\Inscription;
use App\Models\Note;
use App\Models\Periode;
use App\Models\Personnel;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Contrôleur exécutif pour le Module Relevés de Notes & Fiches de Notation.
 *
 * Conforme au Cahier des Charges Gesco V1.1 (Section 10) et au principe du moindre privilège (Least Privilege) :
 * - Cloisonnement SaaS Multi-Tenant hermétique par campus (ScopesEtablissement).
 * - Cloisonnement strict Enseignant : un enseignant n'accède qu'à ses propres attributions (classes et matières).
 * - Permet à l'enseignant ou à la direction de visualiser la liste complète des élèves d'une classe attribuée.
 * - Génération et impression PDF officielle des fiches de notation (vierges pour report manuscrit ou pré-remplies).
 */
class ReleveNoteController extends Controller implements HasMiddleware
{
    use ScopesEtablissement;

    /**
     * Définition des middlewares d'autorisation granulaires Spatie.
     *
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:releves_notes.view', only: ['index']),
            new Middleware('can:releves_notes.print|can:releves_notes.view', only: ['exportPdf']),
        ];
    }

    /**
     * Affiche l'interface de consultation et de configuration des relevés de notes.
     *
     * @param Request $request La requête HTTP entrante.
     * @return InertiaResponse Vue Inertia avec les données contextuelles filtrées.
     */
    public function index(Request $request): InertiaResponse
    {
        $user = Auth::user();
        $isSuperAdmin = $user ? ($user->hasRole('super-admin') || $user->email === 'admin@gmail.com') : false;
        $isGestionnaire = $isSuperAdmin || $user->hasRole(['admin', 'directeur-etudes', 'censeur', 'surveillant-general'])
            || $user->can('notes.manage');

        // Récupération du contexte établissement (SaaS Multi-Tenant)
        $context = $this->resolveEtablissementContext($request, ['institution']);
        $etablissements = $context['etablissements'];
        $currentEtablissement = $context['selectedEtablissement'];
        $etablissementId = $context['etablissementId'] ?? 0;

        // Récupération du personnel associé si l'utilisateur est enseignant
        $personnel = $user->personnel ?? ($user->email ? Personnel::where('email', $user->email)->first() : null);

        // Années scolaires et sélection active
        $annees = AnneeScolaire::orderByDesc('date_debut')->get();
        $anneeActive = AnneeScolaire::current() ?? $annees->first();
        $anneeId = $request->integer('annee_id', $anneeActive?->id ?? 0);

        // Périodes d'évaluation du campus
        $periodes = Periode::with(['evaluations' => fn ($q) => $q->orderBy('ordre')])
            ->where('annee_scolaire_id', $anneeId)
            ->when($etablissementId, fn ($q) => $q->where('etablissement_id', $etablissementId))
            ->orderBy('date_debut')
            ->get();

        // Requête des attributions disponibles
        $attributionsQuery = Attribution::with(['classe.niveau', 'matiere', 'personnel'])
            ->where('annee_scolaire_id', $anneeId)
            ->where('is_active', true);

        if ($etablissementId) {
            $attributionsQuery->whereHas('classe', fn ($q) => $q->where('etablissement_id', $etablissementId));
        }

        // Si l'utilisateur est un simple enseignant, restreindre exclusivement à ses attributions
        if (! $isGestionnaire && $personnel) {
            $attributionsQuery->where('personnel_id', $personnel->id);
        }

        $attributions = $attributionsQuery->get();

        // Attribution sélectionnée
        $attributionId = $request->integer('attribution_id', $attributions->first()?->id ?? 0);
        $selectedAttribution = $attributions->firstWhere('id', $attributionId);

        // Évaluation ou Période sélectionnée
        $periodeId = $request->integer('periode_id', $periodes->first()?->id ?? 0);
        $evaluationId = $request->integer('evaluation_id', 0);
        $mode = $request->input('mode', 'rempli'); // 'rempli' ou 'vierge'

        $elevesData = [];
        $selectedEvaluations = collect([]);

        if ($selectedAttribution) {
            // Déterminer les évaluations concernées
            if ($evaluationId > 0) {
                $selectedEvaluations = Evaluation::where('id', $evaluationId)->get();
            } elseif ($periodeId > 0) {
                $periode = $periodes->firstWhere('id', $periodeId);
                $selectedEvaluations = $periode ? $periode->evaluations : collect([]);
            } else {
                $selectedEvaluations = $periodes->flatMap->evaluations;
            }

            // Récupération des inscriptions actives de la classe
            $inscriptions = Inscription::with(['eleve'])
                ->where('classe_id', $selectedAttribution->classe_id)
                ->where('annee_scolaire_id', $anneeId)
                ->where('statut', 'validee')
                ->when($etablissementId, fn ($q) => $q->where('etablissement_id', $etablissementId))
                ->get()
                ->sortBy(fn ($ins) => ($ins->eleve?->nom ?? '') . ' ' . ($ins->eleve?->prenom ?? ''))
                ->values();

            // Notes existantes si mode rempli
            $notesMap = collect([]);
            if ($mode === 'rempli' && $inscriptions->isNotEmpty()) {
                $notes = Note::where('attribution_id', $selectedAttribution->id)
                    ->whereIn('inscription_id', $inscriptions->pluck('id'))
                    ->when($selectedEvaluations->isNotEmpty(), fn ($q) => $q->whereIn('evaluation_id', $selectedEvaluations->pluck('id')))
                    ->get();

                // Regroupement par [inscription_id][evaluation_id]
                foreach ($notes as $note) {
                    $key = $note->inscription_id . '_' . $note->evaluation_id;
                    $notesMap->put($key, [
                        'valeur_note' => $note->valeur_note !== null ? (float) $note->valeur_note : null,
                        'statut_presence' => $note->statut_presence,
                        'appreciation' => $note->appreciation,
                        'is_verrouillee' => (bool) $note->is_verrouillee,
                    ]);
                }
            }

            // Construction de la liste formatée des élèves pour TanStack Table
            $elevesData = $inscriptions->map(function ($ins, $index) use ($notesMap, $selectedEvaluations) {
                $notesParEval = [];
                $somme = 0;
                $compteur = 0;

                foreach ($selectedEvaluations as $eval) {
                    $noteData = $notesMap->get($ins->id . '_' . $eval->id);
                    $notesParEval[$eval->id] = $noteData;

                    if ($noteData && $noteData['valeur_note'] !== null && $noteData['statut_presence'] === 'P') {
                        $somme += (float) $noteData['valeur_note'];
                        $compteur++;
                    }
                }

                $moyenne = $compteur > 0 ? round($somme / $compteur, 2) : null;

                return [
                    'numero' => $index + 1,
                    'inscription_id' => $ins->id,
                    'eleve_id' => $ins->eleve_id,
                    'matricule' => $ins->eleve?->matricule ?? '-',
                    'nom' => $ins->eleve?->nom ?? '',
                    'prenom' => $ins->eleve?->prenom ?? '',
                    'nom_complet' => trim(($ins->eleve?->nom ?? '') . ' ' . ($ins->eleve?->prenom ?? '')),
                    'sexe' => $ins->eleve?->sexe ?? 'M',
                    'date_naissance' => $ins->eleve?->date_naissance,
                    'notes' => $notesParEval,
                    'moyenne' => $moyenne,
                ];
            });
        }

        return Inertia::render('administration/notes/releves', [
            'etablissements' => $etablissements,
            'currentEtablissement' => $currentEtablissement,
            'annees' => $annees,
            'anneeActiveId' => (int) $anneeId,
            'periodes' => $periodes,
            'attributions' => $attributions,
            'selectedAttribution' => $selectedAttribution,
            'selectedEvaluations' => $selectedEvaluations,
            'eleves' => $elevesData,
            'isGestionnaire' => $isGestionnaire,
            'filters' => [
                'annee_id' => $anneeId,
                'attribution_id' => $attributionId,
                'periode_id' => $periodeId,
                'evaluation_id' => $evaluationId,
                'mode' => $mode,
            ],
        ]);
    }

    /**
     * Génère et télécharge le document PDF officiel du Relevé de Notes / Fiche de Notation.
     *
     * @param Request $request La requête HTTP contenant les filtres de génération.
     * @return Response Document PDF prêt à l'impression.
     */
    public function exportPdf(Request $request): Response
    {
        $user = Auth::user();
        $isSuperAdmin = $user ? ($user->hasRole('super-admin') || $user->email === 'admin@gmail.com') : false;
        $isGestionnaire = $isSuperAdmin || $user->hasRole(['admin', 'directeur-etudes', 'censeur', 'surveillant-general'])
            || $user->can('notes.manage');

        $request->validate([
            'attribution_id' => ['required', 'exists:attributions,id'],
            'annee_id' => ['required', 'exists:annees_scolaires,id'],
            'mode' => ['nullable', 'in:rempli,vierge'],
        ]);

        $attributionId = (int) $request->input('attribution_id');
        $anneeId = (int) $request->input('annee_id');
        $periodeId = (int) $request->input('periode_id', 0);
        $evaluationId = (int) $request->input('evaluation_id', 0);
        $mode = $request->input('mode', 'rempli');

        // Récupération de l'attribution avec vérification des droits
        $attribution = Attribution::with(['classe.niveau', 'matiere', 'personnel'])
            ->findOrFail($attributionId);

        // Cloisonnement Enseignant : vérifier que l'enseignant est bien le titulaire du cours
        if (! $isGestionnaire) {
            $personnel = $user->personnel ?? ($user->email ? Personnel::where('email', $user->email)->first() : null);
            if (! $personnel || (int) $attribution->personnel_id !== (int) $personnel->id) {
                abort(403, 'Accès interdit : vous ne pouvez exporter que les relevés de notes de vos propres cours.');
            }
        }

        // Contexte établissement
        $context = $this->resolveEtablissementContext($request, ['institution']);
        $etablissement = $attribution->classe?->etablissement ?? $context['selectedEtablissement'];
        $etablissementId = $etablissement?->id;

        // Année scolaire
        $anneeScolaire = AnneeScolaire::findOrFail($anneeId);

        // Évaluations ciblées
        if ($evaluationId > 0) {
            $evaluations = Evaluation::with('periode')->where('id', $evaluationId)->get();
            $titrePeriode = $evaluations->first()?->nom ?? 'Évaluation';
        } elseif ($periodeId > 0) {
            $periode = Periode::with(['evaluations' => fn ($q) => $q->orderBy('ordre')])->findOrFail($periodeId);
            $evaluations = $periode->evaluations;
            $titrePeriode = $periode->nom;
        } else {
            $evaluations = Evaluation::whereHas('periode', fn ($q) => $q->where('annee_scolaire_id', $anneeId)->when($etablissementId, fn ($sq) => $sq->where('etablissement_id', $etablissementId)))
                ->orderBy('ordre')
                ->get();
            $titrePeriode = 'Année Complète';
        }

        // Inscriptions actives
        $inscriptions = Inscription::with(['eleve'])
            ->where('classe_id', $attribution->classe_id)
            ->where('annee_scolaire_id', $anneeId)
            ->where('statut', 'validee')
            ->when($etablissementId, fn ($q) => $q->where('etablissement_id', $etablissementId))
            ->get()
            ->sortBy(fn ($ins) => ($ins->eleve?->nom ?? '') . ' ' . ($ins->eleve?->prenom ?? ''))
            ->values();

        // Récupération des notes si mode rempli
        $notesMap = collect([]);
        if ($mode === 'rempli' && $inscriptions->isNotEmpty() && $evaluations->isNotEmpty()) {
            $notes = Note::where('attribution_id', $attribution->id)
                ->whereIn('inscription_id', $inscriptions->pluck('id'))
                ->whereIn('evaluation_id', $evaluations->pluck('id'))
                ->get();

            foreach ($notes as $note) {
                $notesMap->put($note->inscription_id . '_' . $note->evaluation_id, $note);
            }
        }

        // Construction des lignes élèves
        $eleves = $inscriptions->map(function ($ins, $index) use ($notesMap, $evaluations, $mode) {
            $notesList = [];
            $somme = 0;
            $compteur = 0;

            foreach ($evaluations as $eval) {
                if ($mode === 'rempli') {
                    $note = $notesMap->get($ins->id . '_' . $eval->id);
                    $valeur = $note?->valeur_note;
                    $presence = $note?->statut_presence ?? 'P';
                    $notesList[$eval->id] = [
                        'valeur' => $valeur !== null ? number_format((float) $valeur, 2) : ($presence !== 'P' ? $presence : '-'),
                        'presence' => $presence,
                    ];
                    if ($valeur !== null && $presence === 'P') {
                        $somme += (float) $valeur;
                        $compteur++;
                    }
                } else {
                    $notesList[$eval->id] = [
                        'valeur' => '',
                        'presence' => 'P',
                    ];
                }
            }

            $moyenne = ($compteur > 0) ? number_format($somme / $compteur, 2) : '-';

            return [
                'numero' => $index + 1,
                'matricule' => $ins->eleve?->matricule ?? '-',
                'nom_complet' => trim(($ins->eleve?->nom ?? '') . ' ' . ($ins->eleve?->prenom ?? '')),
                'sexe' => $ins->eleve?->sexe ?? 'M',
                'notes' => $notesList,
                'moyenne' => $moyenne,
            ];
        });

        $effectifTotal = $eleves->count();
        $effectifFilles = $eleves->where('sexe', 'F')->count();
        $effectifGarcons = $eleves->where('sexe', 'M')->count();

        $pdfData = [
            'etablissement' => $etablissement,
            'anneeScolaire' => $anneeScolaire,
            'attribution' => $attribution,
            'evaluations' => $evaluations,
            'titrePeriode' => $titrePeriode,
            'eleves' => $eleves,
            'mode' => $mode,
            'stats' => [
                'total' => $effectifTotal,
                'filles' => $effectifFilles,
                'garcons' => $effectifGarcons,
            ],
            'dateImpression' => now()->format('d-m-Y H:i'),
        ];

        $pdf = Pdf::loadView('pdf.releve-notes', $pdfData)
            ->setPaper('a4', 'landscape');

        $filename = sprintf(
            'Releve_Notes_%s_%s_%s.pdf',
            str_replace(' ', '_', $attribution->classe?->nom ?? 'Classe'),
            str_replace(' ', '_', $attribution->matiere?->nom ?? 'Matiere'),
            $mode
        );

        return $pdf->stream($filename);
    }
}
