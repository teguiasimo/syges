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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Contrôleur pour la Gestion de la Saisie des Notes d'Évaluation (Module 10 Gesco V1.1).
 *
 * Assure :
 * - Le contrôle d'accès strict : les enseignants n'accèdent qu'à leurs classes et matières attribuées.
 * - La saisie tabulaire rapide avec statuts de présence, appréciations et compétences (APC).
 * - Le verrouillage temporel et manuel anti-fraude.
 * - Le déverrouillage autorisé pour les censeurs et administrateurs.
 */
class NoteController extends Controller implements HasMiddleware
{
    use ScopesEtablissement;
    /**
     * Définition de la matrice des middlewares d'autorisation pour le module Notes & Évaluations.
     *
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:notes.view', only: ['index', 'getGrille']),
            new Middleware('can:notes.create|notes.manage|notes.view', only: ['sauvegarder']),
            new Middleware('can:notes.lock|evaluations.lock|notes.manage', only: ['verrouiller']),
            new Middleware('can:notes.unlock|notes.lock|evaluations.lock|notes.manage', only: ['deverrouiller']),
        ];
    }

    /**
     * Affiche l'interface de saisie des notes (Portail Enseignant & Censeur).
     */
    public function index(Request $request): InertiaResponse
    {
        $user = Auth::user();
        $isSuperAdmin = $user ? ($user->hasRole('super-admin') || $user->email === 'admin@gmail.com') : false;
        $isGestionnaire = $user->hasRole(['super-admin', 'Directeur / Promoteur', 'Censeur', 'directeur-etudes', 'admin'])
            || $user->can('notes.lock');

        // Récupération du personnel associé à l'utilisateur connecté s'il est enseignant
        $personnel = $user->personnel ?? ($user->email ? Personnel::where('email', $user->email)->first() : null);

        // 1. Années scolaires et période active (nettoyage de l'intitulé pour affichage concis '2026/2027')
        $annees = AnneeScolaire::orderByDesc('date_debut')->get()->map(function ($annee) {
            $nomEpure = $annee->session ?: (preg_match('/\b(\d{4}[\/\-]\d{4})\b/', $annee->nom, $m) ? $m[1] : trim(preg_replace('/^\s*Ann[ée]e\s+scolaire\s*[:\-]?\s*/ui', '', $annee->nom)));
            $annee->nom = $nomEpure;
            return $annee;
        });
        $anneeActive = AnneeScolaire::current() ?? $annees->first();
        $anneeId = $request->query('annee_id', $anneeActive?->id);

        $allEtablissements = \App\Models\Etablissement::select('id', 'nom', 'code')->orderBy('nom')->get();

        if (! $isSuperAdmin && $personnel?->etablissement_id) {
            $etablissementId = (int) $personnel->etablissement_id;
            $etablissements = $allEtablissements->where('id', $etablissementId)->values();
        } else {
            $etablissementId = $request->query('etablissement_id')
                ? (int) $request->query('etablissement_id')
                : ($request->session()->get('current_etablissement_id') ?: $allEtablissements->first()?->id);
            $etablissements = $allEtablissements;
        }

        // 2. Périodes et évaluations
        $periodesQuery = Periode::with(['evaluations' => fn ($q) => $q->orderBy('ordre')])
            ->where('annee_scolaire_id', $anneeId)
            ->orderBy('date_debut');

        if ($etablissementId) {
            $periodesQuery->where('etablissement_id', $etablissementId);
        }

        $periodes = $periodesQuery->get();

        // 3. Attributions autorisées pour l'utilisateur
        $attributionsQuery = Attribution::with(['classe.niveau', 'matiere', 'personnel'])
            ->where('annee_scolaire_id', $anneeId)
            ->where('is_active', true);

        if ($etablissementId) {
            $attributionsQuery->whereHas('classe', fn ($q) => $q->where('etablissement_id', $etablissementId));
        }

        if (! $isGestionnaire && $personnel) {
            // Un enseignant ne voit que ses propres attributions (Gesco 10.1)
            $attributionsQuery->where('personnel_id', $personnel->id);
        }

        $attributions = $attributionsQuery->get();

        // Classes uniques représentées dans les attributions autorisées
        $classes = $attributions->pluck('classe')->unique('id')->values();

        return Inertia::render('administration/notes/index', [
            'annees' => $annees,
            'anneeActiveId' => (int) $anneeId,
            'etablissements' => $etablissements,
            'periodes' => $periodes,
            'classes' => $classes,
            'attributions' => $attributions,
            'isGestionnaire' => $isGestionnaire,
            'filters' => [
                'annee_id' => $anneeId,
                'etablissement_id' => $etablissementId,
                'classe_id' => $request->query('classe_id'),
                'attribution_id' => $request->query('attribution_id'),
                'evaluation_id' => $request->query('evaluation_id'),
            ],
        ]);
    }

    /**
     * Récupère la grille complète des élèves et des notes pour une attribution et une évaluation.
     */
    public function getGrille(Request $request): JsonResponse
    {
        $request->validate([
            'attribution_id' => 'required|exists:attributions,id',
            'evaluation_id' => 'required|exists:evaluations,id',
        ]);

        $user = Auth::user();
        $isGestionnaire = $user->hasRole(['super-admin', 'Directeur / Promoteur', 'Censeur'])
            || $user->can('notes.lock');

        $attribution = Attribution::with(['classe.niveau', 'matiere', 'personnel'])
            ->findOrFail($request->attribution_id);

        $evaluation = Evaluation::with('periode')->findOrFail($request->evaluation_id);

        // Cloisonnement SaaS Multi-Tenant : vérifier l'appartenance de la classe à l'établissement courant
        $context = $this->resolveEtablissementContext($request);
        if (! $context['isSuperAdmin']) {
            $classe = Classe::withoutGlobalScopes()->find($attribution->classe_id);
            if (! $classe || (int) $classe->etablissement_id !== (int) $context['etablissementId']) {
                return response()->json([
                    'message' => 'Accès refusé : ce cours n\'appartient pas à votre établissement.',
                ], 403);
            }
        }

        // Vérification des droits d'accès
        if (! $isGestionnaire) {
            $personnel = $user->personnel ?? ($user->email ? Personnel::where('email', $user->email)->first() : null);

            if (! $personnel || $attribution->personnel_id !== $personnel->id) {
                return response()->json([
                    'message' => 'Accès refusé : vous n\'êtes pas l\'enseignant attribué à ce cours.',
                ], 403);
            }
        }

        // Récupération des élèves inscrits et validés dans cette classe
        $inscriptions = Inscription::with(['eleve'])
            ->where('classe_id', $attribution->classe_id)
            ->where('annee_scolaire_id', $attribution->annee_scolaire_id)
            ->where('statut', 'validee')
            ->get()
            ->sortBy(fn ($i) => $i->eleve->nom.' '.$i->eleve->prenom)
            ->values();

        // Récupération des notes existantes
        $notesExistantes = Note::where('attribution_id', $attribution->id)
            ->where('evaluation_id', $evaluation->id)
            ->whereIn('inscription_id', $inscriptions->pluck('id'))
            ->get()
            ->keyBy('inscription_id');

        // Vérification de l'état de verrouillage
        $isLocked = $evaluation->isLockedForGrading();
        if (! $isLocked && $notesExistantes->isNotEmpty()) {
            // Si toutes les notes enregistrées sont verrouillées individuellement
            $isLocked = $notesExistantes->every(fn ($n) => $n->is_verrouillee);
        }

        // Assemblage de la grille
        $grille = $inscriptions->map(function ($insc) use ($notesExistantes) {
            $note = $notesExistantes->get($insc->id);

            return [
                'inscription_id' => $insc->id,
                'matricule' => $insc->eleve->matricule,
                'nom_complet' => $insc->eleve->nom.' '.$insc->eleve->prenom,
                'photo_url' => $insc->eleve->photo_url,
                'valeur_note' => $note && $note->valeur_note !== null ? (float) $note->valeur_note : null,
                'statut_presence' => $note ? $note->statut_presence : 'present',
                'appreciation' => $note ? $note->appreciation : '',
                'competence_evaluee' => $note ? $note->competence_evaluee : '',
                'cote' => $note ? $note->cote : null,
                'is_verrouillee' => $note ? (bool) $note->is_verrouillee : false,
            ];
        });

        return response()->json([
            'attribution' => $attribution,
            'evaluation' => $evaluation,
            'is_locked' => $isLocked,
            'grille' => $grille,
        ]);
    }

    /**
     * Enregistre en masse les notes saisies pour une classe, matière et évaluation.
     * Vérifie hermétiquement le verrouillage global de l'évaluation ainsi que le verrouillage individuel de la grille.
     *
     * @param Request $request Requête HTTP contenant les notes.
     * @return RedirectResponse|JsonResponse Réponse de redirection ou JSON.
     */
    public function sauvegarder(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'attribution_id' => 'required|exists:attributions,id',
            'evaluation_id' => 'required|exists:evaluations,id',
            'notes' => 'required|array',
            'notes.*.inscription_id' => 'required|exists:inscriptions,id',
            'notes.*.valeur_note' => 'nullable|numeric|min:0|max:20',
            'notes.*.statut_presence' => 'required|in:present,absent_justifie,absent_non_justifie,non_evalue',
            'notes.*.appreciation' => 'nullable|string|max:255',
            'notes.*.competence_evaluee' => 'nullable|string',
            'notes.*.cote' => 'nullable|in:CTBA,CBA,CMA,CNA',
        ]);

        $user = Auth::user();
        $isGestionnaire = $user->hasRole(['super-admin', 'Directeur / Promoteur', 'Censeur'])
            || $user->can('notes.lock');

        $attribution = Attribution::with('classe')->findOrFail($validated['attribution_id']);
        $evaluation = Evaluation::findOrFail($validated['evaluation_id']);

        // Cloisonnement SaaS Multi-Tenant : vérifier l'appartenance du cours à l'établissement courant
        $context = $this->resolveEtablissementContext($request);
        if (! $context['isSuperAdmin']) {
            $classe = Classe::withoutGlobalScopes()->find($attribution->classe_id);
            if (! $classe || (int) $classe->etablissement_id !== (int) $context['etablissementId']) {
                abort(403, 'Action non autorisée : ce cours n\'appartient pas à votre établissement.');
            }
        }

        // Contrôle d'accès et vérifications de verrouillage pour les non-gestionnaires
        if (! $isGestionnaire) {
            $personnel = $user->personnel ?? ($user->email ? Personnel::where('email', $user->email)->first() : null);

            if (! $personnel || $attribution->personnel_id !== $personnel->id) {
                if ($request->wantsJson()) {
                    return response()->json(['message' => 'Action non autorisée sur ce cours.'], 403);
                }
                abort(403, 'Action non autorisée sur ce cours.');
            }

            // 1. Verrouillage au niveau de l'évaluation (manuel ou date limite dépassée)
            if ($evaluation->isLockedForGrading()) {
                if ($request->wantsJson()) {
                    return response()->json([
                        'message' => 'La saisie des notes pour cette session d\'évaluation est verrouillée.',
                    ], 403);
                }
                abort(403, 'La saisie des notes pour cette session d\'évaluation est close.');
            }

            // 2. Verrouillage au niveau de la grille spécifique du cours
            $alreadyLocked = Note::where('attribution_id', $validated['attribution_id'])
                ->where('evaluation_id', $validated['evaluation_id'])
                ->where('is_verrouillee', true)
                ->exists();

            if ($alreadyLocked) {
                if ($request->wantsJson()) {
                    return response()->json([
                        'message' => 'Cette grille de notes a été verrouillée et ne peut plus être modifiée.',
                    ], 403);
                }
                abort(403, 'Cette grille de notes a été verrouillée et ne peut plus être modifiée.');
            }
        }

        DB::transaction(function () use ($validated, $user) {
            foreach ($validated['notes'] as $ligne) {
                $valNote = $ligne['valeur_note'];
                $statutPres = $ligne['statut_presence'];

                // Si absent ou non évalué, la note numérique est nulle
                if ($statutPres !== 'present') {
                    $valNote = null;
                }

                // Calcul automatique de la cote APC si note renseignée
                $cote = $ligne['cote'] ?? null;
                if ($valNote !== null && empty($cote)) {
                    $cote = Note::determinerCoteAPC((float) $valNote);
                } elseif ($valNote === null) {
                    $cote = null;
                }

                $existingNote = Note::where('inscription_id', $ligne['inscription_id'])
                    ->where('attribution_id', $validated['attribution_id'])
                    ->where('evaluation_id', $validated['evaluation_id'])
                    ->first();

                Note::updateOrCreate(
                    [
                        'inscription_id' => $ligne['inscription_id'],
                        'attribution_id' => $validated['attribution_id'],
                        'evaluation_id' => $validated['evaluation_id'],
                    ],
                    [
                        'valeur_note' => $valNote,
                        'statut_presence' => $statutPres,
                        'appreciation' => $ligne['appreciation'] ?? null,
                        'competence_evaluee' => $ligne['competence_evaluee'] ?? null,
                        'cote' => $cote,
                        'modifie_par_user_id' => $user->id,
                        'saisie_par_user_id' => $existingNote ? $existingNote->saisie_par_user_id : $user->id,
                    ]
                );
            }
        });

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Notes enregistrées avec succès.',
            ]);
        }

        return redirect()->back()->with('success', 'Notes enregistrées avec succès.');
    }

    /**
     * Verrouille définitivement la saisie des notes pour cette session (grille de cours).
     * Accessible à l'enseignant titulaire du cours ainsi qu'aux gestionnaires pédagogiques.
     *
     * @param Request $request Requête HTTP.
     * @return RedirectResponse|JsonResponse Redirection ou confirmation JSON.
     */
    public function verrouiller(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'attribution_id' => 'required|exists:attributions,id',
            'evaluation_id' => 'required|exists:evaluations,id',
        ]);

        $user = Auth::user();
        $isGestionnaire = $user->hasRole(['super-admin', 'Directeur / Promoteur', 'Censeur'])
            || $user->can('notes.lock');

        $attribution = Attribution::with('classe')->findOrFail($validated['attribution_id']);

        // Cloisonnement SaaS Multi-Tenant
        $context = $this->resolveEtablissementContext($request);
        if (! $context['isSuperAdmin']) {
            $classe = Classe::withoutGlobalScopes()->find($attribution->classe_id);
            if (! $classe || (int) $classe->etablissement_id !== (int) $context['etablissementId']) {
                abort(403, 'Action non autorisée : ce cours n\'appartient pas à votre établissement.');
            }
        }

        // Vérification des droits : seul l'enseignant du cours ou la direction peut verrouiller
        if (! $isGestionnaire) {
            $personnel = $user->personnel ?? ($user->email ? Personnel::where('email', $user->email)->first() : null);

            if (! $personnel || $attribution->personnel_id !== $personnel->id) {
                if ($request->wantsJson()) {
                    return response()->json(['message' => 'Action non autorisée sur ce cours.'], 403);
                }
                abort(403, 'Action non autorisée sur ce cours.');
            }
        }

        Note::where('attribution_id', $validated['attribution_id'])
            ->where('evaluation_id', $validated['evaluation_id'])
            ->update(['is_verrouillee' => true]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Grille de notes verrouillée avec succès.',
            ]);
        }

        return redirect()->back()->with('success', 'Grille de notes verrouillée avec succès.');
    }

    /**
     * Déverrouille exceptionnellement la saisie des notes (réservé Censeur / Super-Admin).
     */
    public function deverrouiller(Request $request): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        if (! $user || (! $user->can('notes.unlock') && ! $user->can('notes.lock') && ! $user->can('evaluations.lock') && ! $user->hasRole('super-admin'))) {
            abort(403, 'Action non autorisée : permission de déverrouillage requise.');
        }

        $validated = $request->validate([
            'attribution_id' => 'required|exists:attributions,id',
            'evaluation_id' => 'required|exists:evaluations,id',
        ]);

        $attribution = Attribution::with('classe')->findOrFail($validated['attribution_id']);

        // Cloisonnement SaaS Multi-Tenant
        $context = $this->resolveEtablissementContext($request);
        if (! $context['isSuperAdmin']) {
            $classe = Classe::withoutGlobalScopes()->find($attribution->classe_id);
            if (! $classe || (int) $classe->etablissement_id !== (int) $context['etablissementId']) {
                abort(403, 'Action non autorisée : ce cours n\'appartient pas à votre établissement.');
            }
        }

        Note::where('attribution_id', $validated['attribution_id'])
            ->where('evaluation_id', $validated['evaluation_id'])
            ->update(['is_verrouillee' => false]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Grille de notes déverrouillée.',
            ]);
        }

        return redirect()->back()->with('success', 'Grille de notes déverrouillée.');
    }
}
