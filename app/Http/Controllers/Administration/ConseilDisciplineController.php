<?php

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use App\Models\AnneeScolaire;
use App\Models\ConseilDiscipline;
use App\Models\DossierConseilDiscipline;
use App\Models\Etablissement;
use App\Models\HeureConsigne;
use App\Models\Inscription;
use App\Models\SanctionDisciplinaire;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Contrôleur de gestion du Conseil de Discipline et des Heures de Consigne (Phase 5).
 *
 * Implémente le cadre légal MINESEC pour les établissements secondaires :
 * - Planification et tenue des sessions formelles de Conseil de Discipline.
 * - Émission des lettres de convocation officielles avec accusé de réception parents.
 * - Saisie des plaidoiries, délibérations et sanctions majeures (exclusions, blâmes, consignes).
 * - Édition du Procès-Verbal (PV) officiel de Conseil de Discipline.
 * - Registre d'assiduité des heures de retenue et consignes du samedi.
 */
class ConseilDisciplineController extends Controller implements HasMiddleware
{
    /**
     * Définition de la matrice des middlewares d'autorisation pour le Conseil de Discipline & Consignes.
     *
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:conseil_discipline.view|discipline.view', only: ['index']),
            new Middleware('can:conseil_discipline.create|discipline.manage', only: ['store', 'storeDossier']),
            new Middleware('can:conseil_discipline.edit|discipline.manage', only: ['update', 'updateDossier']),
            new Middleware('can:conseil_discipline.delete|discipline.manage', only: ['destroy', 'destroyDossier']),
            new Middleware('can:consignes.pointage|discipline.manage', only: ['storeHeureConsigne', 'updateHeureConsigne', 'destroyHeureConsigne']),
            new Middleware('can:conseil_discipline.print|conseil_discipline.view|discipline.view', only: ['imprimerConvocation', 'imprimerPv']),
        ];
    }
    /**
     * Affiche l'espace de gestion du Conseil de Discipline et des Heures de Consigne.
     *
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        $user = Auth::user();
        $isSuperAdmin = $user ? ($user->hasRole('super-admin') || $user->email === 'admin@gmail.com') : false;

        $etablissementsQuery = Etablissement::with([
            'institution',
            'sections.cycles.niveaux.classes',
        ]);

        if (! $isSuperAdmin && $user?->personnel?->etablissement_id) {
            $etablissementsQuery->where('id', $user->personnel->etablissement_id);
        }

        $etablissements = $etablissementsQuery->get();
        $selectedEtabId = (int) $request->input('etablissement_id', $etablissements->first()?->id);

        $anneeActive = AnneeScolaire::where('is_current', true)->first() ?? AnneeScolaire::latest()->first();
        $selectedAnneeId = (int) $request->input('annee_scolaire_id', $anneeActive?->id);

        $statut = $request->input('statut');
        $search = $request->input('search');

        // Sessions de Conseil de Discipline
        $sessionsQuery = ConseilDiscipline::with([
            'dossiers.eleve',
            'dossiers.inscription.classe.niveau',
        ])
        ->where('etablissement_id', $selectedEtabId)
        ->where('annee_scolaire_id', $selectedAnneeId);

        if ($statut) {
            $sessionsQuery->where('statut', $statut);
        }

        if ($search) {
            $sessionsQuery->where(function ($q) use ($search) {
                $q->where('numero_session', 'like', "%{$search}%")
                  ->orWhere('president_seance', 'like', "%{$search}%")
                  ->orWhere('observations_generales', 'like', "%{$search}%")
                  ->orWhereHas('dossiers.eleve', function ($sq) use ($search) {
                      $sq->where('nom', 'like', "%{$search}%")
                         ->orWhere('prenom', 'like', "%{$search}%")
                         ->orWhere('matricule', 'like', "%{$search}%");
                  });
            });
        }

        $sessions = $sessionsQuery->latest('date_conseil')->get();

        // Heures de consigne enregistrées
        $consignes = HeureConsigne::with([
            'eleve',
            'inscription.classe.niveau',
        ])
        ->where('etablissement_id', $selectedEtabId)
        ->where('annee_scolaire_id', $selectedAnneeId)
        ->latest('date_consigne')
        ->get();

        // Statistiques
        $stats = [
            'total_sessions' => ConseilDiscipline::where('etablissement_id', $selectedEtabId)->where('annee_scolaire_id', $selectedAnneeId)->count(),
            'total_dossiers' => DossierConseilDiscipline::whereHas('conseilDiscipline', function ($q) use ($selectedEtabId, $selectedAnneeId) {
                $q->where('etablissement_id', $selectedEtabId)->where('annee_scolaire_id', $selectedAnneeId);
            })->count(),
            'total_exclusions' => DossierConseilDiscipline::whereHas('conseilDiscipline', function ($q) use ($selectedEtabId, $selectedAnneeId) {
                $q->where('etablissement_id', $selectedEtabId)->where('annee_scolaire_id', $selectedAnneeId);
            })->whereIn('decision_finale', ['exclusion_temporaire', 'exclusion_definitive'])->count(),
            'total_heures_consigne' => (int) HeureConsigne::where('etablissement_id', $selectedEtabId)->where('annee_scolaire_id', $selectedAnneeId)->sum('nombre_heures'),
        ];

        // Inscriptions actives pour sélection
        $inscriptions = Inscription::with(['eleve', 'classe.niveau'])
            ->where('etablissement_id', $selectedEtabId)
            ->where('annee_scolaire_id', $selectedAnneeId)
            ->where('statut', 'validee')
            ->get()
            ->sortBy(fn ($i) => $i->eleve->nom . ' ' . $i->eleve->prenom)
            ->values();

        return Inertia::render('administration/discipline/conseil/index', [
            'sessions' => $sessions,
            'consignes' => $consignes,
            'stats' => $stats,
            'inscriptions' => $inscriptions,
            'etablissements' => $etablissements,
            'selectedEtabId' => $selectedEtabId,
            'selectedAnneeId' => $selectedAnneeId,
            'anneesScolaires' => AnneeScolaire::orderByDesc('is_current')->orderByDesc('date_debut')->get(),
            'filters' => [
                'etablissement_id' => (string) $selectedEtabId,
                'annee_scolaire_id' => (string) $selectedAnneeId,
                'statut' => (string) $statut,
                'search' => $search ?: '',
            ],
            'can' => [
                'view' => $isSuperAdmin || $user?->can('conseil_discipline.view') || $user?->can('discipline.view'),
                'create' => $isSuperAdmin || $user?->can('conseil_discipline.create') || $user?->can('discipline.manage'),
                'edit' => $isSuperAdmin || $user?->can('conseil_discipline.edit') || $user?->can('discipline.manage'),
                'delete' => $isSuperAdmin || $user?->can('conseil_discipline.delete'),
                'print' => $isSuperAdmin || $user?->can('conseil_discipline.print') || $user?->can('discipline.view'),
                'consignes_pointage' => $isSuperAdmin || $user?->can('consignes.pointage') || $user?->can('discipline.manage'),
            ],
        ]);
    }

    /**
     * Crée une nouvelle session de Conseil de Discipline.
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function storeSession(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'etablissement_id' => ['required', 'exists:etablissements,id'],
            'annee_scolaire_id' => ['required', 'exists:annees_scolaires,id'],
            'numero_session' => ['required', 'string', 'max:50'],
            'date_conseil' => ['required', 'date'],
            'lieu' => ['required', 'string', 'max:100'],
            'president_seance' => ['required', 'string', 'max:150'],
            'rapporteur' => ['required', 'string', 'max:150'],
            'membres_presents' => ['nullable', 'string', 'max:1000'],
            'observations_generales' => ['nullable', 'string', 'max:1000'],
            'statut' => ['required', 'string', 'in:planifie,en_cours,tenu,annule'],
        ]);

        $validated['created_by'] = Auth::id();

        ConseilDiscipline::create($validated);

        return back()->with('success', 'Session de Conseil de Discipline créée avec succès.');
    }

    /**
     * Met à jour une session de Conseil de Discipline.
     *
     * @param Request $request
     * @param ConseilDiscipline $conseil
     * @return RedirectResponse
     */
    public function updateSession(Request $request, ConseilDiscipline $conseil): RedirectResponse
    {
        $validated = $request->validate([
            'numero_session' => ['required', 'string', 'max:50'],
            'date_conseil' => ['required', 'date'],
            'lieu' => ['required', 'string', 'max:100'],
            'president_seance' => ['required', 'string', 'max:150'],
            'rapporteur' => ['required', 'string', 'max:150'],
            'membres_presents' => ['nullable', 'string', 'max:1000'],
            'observations_generales' => ['nullable', 'string', 'max:1000'],
            'statut' => ['required', 'string', 'in:planifie,en_cours,tenu,annule'],
        ]);

        $conseil->update($validated);

        return back()->with('success', 'Session de Conseil de Discipline mise à jour.');
    }

    /**
     * Supprime une session de Conseil de Discipline.
     *
     * @param ConseilDiscipline $conseil
     * @return RedirectResponse
     */
    public function destroySession(ConseilDiscipline $conseil): RedirectResponse
    {
        $conseil->delete();

        return back()->with('success', 'Session de Conseil de Discipline supprimée.');
    }

    /**
     * Ajoute un dossier d'élève traduit devant une session de CD.
     *
     * @param Request $request
     * @param ConseilDiscipline $conseil
     * @return RedirectResponse
     */
    public function storeDossier(Request $request, ConseilDiscipline $conseil): RedirectResponse
    {
        $validated = $request->validate([
            'inscription_id' => ['required', 'exists:inscriptions,id'],
            'motifs_renvoi' => ['required', 'string', 'max:2000'],
            'plaidoirie_eleve' => ['nullable', 'string', 'max:2000'],
            'decision_finale' => ['required', 'string', 'in:en_attente,acquittement,avertissement,blame,consigne,exclusion_temporaire,exclusion_definitive'],
            'nombre_jours_exclusion' => ['nullable', 'integer', 'min:1', 'max:30'],
            'nombre_heures_consigne' => ['nullable', 'integer', 'min:1', 'max:20'],
            'date_convocation' => ['nullable', 'date'],
            'parent_present' => ['boolean'],
            'is_convocation_envoyee' => ['boolean'],
        ]);

        $inscription = Inscription::findOrFail($validated['inscription_id']);
        $validated['eleve_id'] = $inscription->eleve_id;
        $validated['conseil_discipline_id'] = $conseil->id;
        $validated['parent_present'] = $request->boolean('parent_present', false);
        $validated['is_convocation_envoyee'] = $request->boolean('is_convocation_envoyee', true);

        $dossier = DossierConseilDiscipline::create($validated);

        // Si la décision est une exclusion définitive, marquer le statut de l'inscription comme 'exclu'
        if ($validated['decision_finale'] === 'exclusion_definitive') {
            $inscription->update(['statut' => 'exclu']);
        }

        // Si des heures de consigne ont été prononcées, les consigner automatiquement
        if ($validated['decision_finale'] === 'consigne' && ! empty($validated['nombre_heures_consigne'])) {
            HeureConsigne::create([
                'etablissement_id' => $conseil->etablissement_id,
                'annee_scolaire_id' => $conseil->annee_scolaire_id,
                'inscription_id' => $inscription->id,
                'eleve_id' => $inscription->eleve_id,
                'date_consigne' => now()->next('Saturday')->format('Y-m-d'),
                'nombre_heures' => $validated['nombre_heures_consigne'],
                'motif' => "Sanction CD ({$conseil->numero_session}) : " . $validated['motifs_renvoi'],
                'statut' => 'programmee',
                'created_by' => Auth::id(),
            ]);
        }

        return back()->with('success', 'Dossier élève enregistré pour la session.');
    }

    /**
     * Met à jour un dossier d'élève traduit en CD.
     *
     * @param Request $request
     * @param DossierConseilDiscipline $dossier
     * @return RedirectResponse
     */
    public function updateDossier(Request $request, DossierConseilDiscipline $dossier): RedirectResponse
    {
        $validated = $request->validate([
            'motifs_renvoi' => ['required', 'string', 'max:2000'],
            'plaidoirie_eleve' => ['nullable', 'string', 'max:2000'],
            'decision_finale' => ['required', 'string', 'in:en_attente,acquittement,avertissement,blame,consigne,exclusion_temporaire,exclusion_definitive'],
            'nombre_jours_exclusion' => ['nullable', 'integer', 'min:1', 'max:30'],
            'nombre_heures_consigne' => ['nullable', 'integer', 'min:1', 'max:20'],
            'date_convocation' => ['nullable', 'date'],
            'parent_present' => ['boolean'],
            'is_convocation_envoyee' => ['boolean'],
        ]);

        $validated['parent_present'] = $request->boolean('parent_present', false);
        $validated['is_convocation_envoyee'] = $request->boolean('is_convocation_envoyee', false);

        $dossier->update($validated);

        if ($validated['decision_finale'] === 'exclusion_definitive') {
            $dossier->inscription->update(['statut' => 'exclu']);
        }

        return back()->with('success', 'Dossier mis à jour.');
    }

    /**
     * Supprime un dossier de comparution.
     *
     * @param DossierConseilDiscipline $dossier
     * @return RedirectResponse
     */
    public function destroyDossier(DossierConseilDiscipline $dossier): RedirectResponse
    {
        $dossier->delete();

        return back()->with('success', 'Dossier élève retiré du conseil.');
    }

    /**
     * Enregistre une séance d'heures de consigne / retenue du samedi.
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function storeHeureConsigne(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'etablissement_id' => ['required', 'exists:etablissements,id'],
            'annee_scolaire_id' => ['required', 'exists:annees_scolaires,id'],
            'inscription_id' => ['required', 'exists:inscriptions,id'],
            'date_consigne' => ['required', 'date'],
            'heure_debut' => ['nullable', 'string'],
            'heure_fin' => ['nullable', 'string'],
            'nombre_heures' => ['required', 'integer', 'min:1', 'max:12'],
            'motif' => ['required', 'string', 'max:500'],
            'travail_assigne' => ['nullable', 'string', 'max:500'],
            'surveillant_responsable' => ['nullable', 'string', 'max:150'],
            'statut' => ['required', 'string', 'in:programmee,effectuee,non_effectuee,reportee'],
        ]);

        $inscription = Inscription::findOrFail($validated['inscription_id']);
        $validated['eleve_id'] = $inscription->eleve_id;
        $validated['created_by'] = Auth::id();

        HeureConsigne::create($validated);

        return back()->with('success', 'Séance de consigne programmée avec succès.');
    }

    /**
     * Met à jour une séance de consigne (ex: marquer comme effectuée).
     *
     * @param Request $request
     * @param HeureConsigne $consigne
     * @return RedirectResponse
     */
    public function updateHeureConsigne(Request $request, HeureConsigne $consigne): RedirectResponse
    {
        $validated = $request->validate([
            'statut' => ['required', 'string', 'in:programmee,effectuee,non_effectuee,reportee'],
            'observations' => ['nullable', 'string', 'max:500'],
        ]);

        $consigne->update($validated);

        return back()->with('success', 'Statut de la consigne mis à jour.');
    }

    /**
     * Supprime une consigne.
     *
     * @param HeureConsigne $consigne
     * @return RedirectResponse
     */
    public function destroyHeureConsigne(HeureConsigne $consigne): RedirectResponse
    {
        $consigne->delete();

        return back()->with('success', 'Séance de consigne supprimée.');
    }

    /**
     * Génère la Lettre Officielle de Convocation au Conseil de Discipline avec coupon réponse parents.
     *
     * @param DossierConseilDiscipline $dossier
     * @return HttpResponse
     */
    public function imprimerConvocation(DossierConseilDiscipline $dossier): HttpResponse
    {
        $dossier->load([
            'conseilDiscipline.etablissement.institution',
            'conseilDiscipline.anneeScolaire',
            'eleve',
            'inscription.classe',
        ]);

        $etablissement = $dossier->conseilDiscipline->etablissement;
        $conseil = $dossier->conseilDiscipline;

        $pdf = Pdf::loadView('discipline.convocation_cd', [
            'etablissement' => $etablissement,
            'institution' => $etablissement->institution,
            'conseil' => $conseil,
            'dossier' => $dossier,
            'date_emission' => now()->format('d/m/Y'),
        ])->setPaper('a4', 'portrait');

        $filename = "CONVOCATION_CD_{$dossier->eleve->matricule}.pdf";

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Génère le Procès-Verbal Officiel des Délibérations du Conseil de Discipline (PV de CD).
     *
     * @param ConseilDiscipline $conseil
     * @return HttpResponse
     */
    public function imprimerPv(ConseilDiscipline $conseil): HttpResponse
    {
        $conseil->load([
            'etablissement.institution',
            'anneeScolaire',
            'dossiers.eleve',
            'dossiers.inscription.classe',
        ]);

        $pdf = Pdf::loadView('discipline.pv_conseil_discipline', [
            'etablissement' => $conseil->etablissement,
            'institution' => $conseil->etablissement->institution,
            'conseil' => $conseil,
            'date_generation' => now()->format('d/m/Y'),
        ])->setPaper('a4', 'portrait');

        $cleanSession = preg_replace('/[^A-Za-z0-9]/', '_', $conseil->numero_session);
        $filename = "PV_CONSEIL_DISCIPLINE_{$cleanSession}.pdf";

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$filename}\"",
        ]);
    }
}
