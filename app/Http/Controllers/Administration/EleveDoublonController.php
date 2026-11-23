<?php

namespace App\Http\Controllers\Administration;

use App\Concerns\ScopesEtablissement;
use App\Http\Controllers\Controller;
use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\EleveDoublonIgnore;
use App\Models\EleveFusionAudit;
use App\Models\Inscription;
use App\Services\EleveDoublonService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Contrôleur dédié à la prévention, la détection et la fusion des doublons élèves dans SYGES.
 *
 * Expose :
 * - Un endpoint JSON temps réel (`checkCreation`) appelé lors de l'inscription d'un nouvel élève.
 * - La page Inertia « Doublons & Fusion » (`index`) réservée aux profils habilités `eleves.merge`.
 * - La prévisualisation comparative + conflits de notes (`preview`) et la fusion atomique (`merge`).
 * - La gestion des paires légitimes ignorées (jumeaux / vrais homonymes : `ignore`, `unignore`).
 */
class EleveDoublonController extends Controller implements HasMiddleware
{
    use ScopesEtablissement;

    /**
     * Définition des middlewares d'autorisation par action.
     *
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:eleves.create|eleves.manage', only: ['checkCreation']),
            new Middleware('can:eleves.merge', only: ['index', 'preview', 'merge', 'ignore', 'unignore']),
        ];
    }

    /**
     * Affiche la page dédiée « Doublons & Fusion » avec :
     * - Les paires suspectes détectées automatiquement sur le périmètre actif,
     * - La liste des élèves disponibles pour lancer une fusion manuelle de deux dossiers quelconques,
     * - Les paires marquées comme non-doublons (jumeaux/homonymes),
     * - Le journal d'audit des fusions déjà exécutées.
     *
     * @param  Request  $request  Requête HTTP entrante.
     * @param  EleveDoublonService  $doublonService  Service métier anti-doublon.
     * @return Response Page Inertia `administration/eleves/doublons`.
     */
    public function index(Request $request, EleveDoublonService $doublonService): Response
    {
        $currentAnnee = AnneeScolaire::current();
        $selectedAnneeId = $request->integer('annee_scolaire_id') ?: ($currentAnnee?->id ?? 0);
        $anneesScolaires = AnneeScolaire::orderByDesc('date_debut')->get(['id', 'code', 'nom', 'is_current']);

        $context = $this->resolveEtablissementContext($request);
        $isSuperAdmin = (bool) $context['isSuperAdmin'];
        $canAccessAll = (bool) ($context['canAccessAll'] ?? $isSuperAdmin);
        $accessibleEtabIds = $context['etablissements']->pluck('id')->map(fn ($id) => (int) $id)->all();

        $rawEtabParam = $request->query('etablissement_id');
        if (! $canAccessAll) {
            $effectiveEtabId = $context['etablissementId'] ? (int) $context['etablissementId'] : null;
        } elseif ($rawEtabParam === 'all') {
            $effectiveEtabId = null;
        } elseif ($rawEtabParam !== null && $rawEtabParam !== '' && is_numeric($rawEtabParam)) {
            $effectiveEtabId = (int) $rawEtabParam;
        } else {
            // Respecte le campus actif en session (ou vue consolidée si aucun campus sélectionné)
            $sessionVal = $request->hasSession() ? $request->session()->get('current_etablissement_id') : null;
            $effectiveEtabId = ($sessionVal && is_numeric($sessionVal)) ? (int) $sessionVal : null;
        }

        // 1. Scan automatique des paires suspectes
        $suspects = $selectedAnneeId > 0
            ? $doublonService->scannerDoublons($effectiveEtabId, $selectedAnneeId, $accessibleEtabIds)
            : [];

        // 2. Liste légère des élèves du périmètre pour permettre aussi une fusion manuelle libre
        $elevesQuery = Inscription::withoutGlobalScopes()
            ->with(['eleve:id,matricule,nom,prenom', 'classe:id,nom', 'etablissement:id,code,nom'])
            ->where('annee_scolaire_id', $selectedAnneeId);
        if ($effectiveEtabId) {
            $elevesQuery->where('etablissement_id', $effectiveEtabId);
        } elseif (! empty($accessibleEtabIds)) {
            $elevesQuery->whereIn('etablissement_id', $accessibleEtabIds);
        }
        $elevesOptions = $elevesQuery->get()
            ->unique('eleve_id')
            ->map(fn (Inscription $insc) => [
                'id' => $insc->eleve_id,
                'matricule' => $insc->eleve?->matricule ?? '',
                'nom_complet' => $insc->eleve?->nom_complet ?? '',
                'classe_nom' => $insc->classe?->nom ?? 'Non assignée',
                'etablissement_code' => $insc->etablissement?->code ?? '',
            ])
            ->sortBy('nom_complet')
            ->values();

        // 3. Paires ignorées (jumeaux / homonymes légitimes)
        $ignoresQuery = EleveDoublonIgnore::with([
            'eleveMin:id,matricule,nom,prenom,date_naissance',
            'eleveMax:id,matricule,nom,prenom,date_naissance',
            'ignoredBy:id,name',
        ])->latest();
        $ignores = $ignoresQuery->get()->map(fn (EleveDoublonIgnore $ig) => [
            'id' => $ig->id,
            'motif' => $ig->motif,
            'created_at' => $ig->created_at?->toISOString(),
            'ignored_by' => $ig->ignoredBy?->name ?? 'Administrateur',
            'eleve_min' => $ig->eleveMin ? [
                'id' => $ig->eleveMin->id,
                'matricule' => $ig->eleveMin->matricule,
                'nom_complet' => $ig->eleveMin->nom_complet,
            ] : null,
            'eleve_max' => $ig->eleveMax ? [
                'id' => $ig->eleveMax->id,
                'matricule' => $ig->eleveMax->matricule,
                'nom_complet' => $ig->eleveMax->nom_complet,
            ] : null,
        ])->filter(fn ($row) => $row['eleve_min'] && $row['eleve_max'])->values();

        // 4. Journal d'audit des fusions déjà réalisées
        $auditsQuery = EleveFusionAudit::with([
            'masterEleve:id,matricule,nom,prenom',
            'mergedBy:id,name',
            'etablissement:id,code,nom',
        ])->latest();
        if ($effectiveEtabId) {
            $auditsQuery->where('etablissement_id', $effectiveEtabId);
        } elseif (! empty($accessibleEtabIds)) {
            $auditsQuery->where(function ($q) use ($accessibleEtabIds) {
                $q->whereIn('etablissement_id', $accessibleEtabIds)->orWhereNull('etablissement_id');
            });
        }
        $audits = $auditsQuery->limit(100)->get()->map(fn (EleveFusionAudit $a) => [
            'id' => $a->id,
            'master_eleve_id' => $a->master_eleve_id,
            'master_matricule' => $a->master_matricule,
            'master_nom_complet' => $a->masterEleve?->nom_complet ?? $a->master_matricule,
            'absorbed_matricule' => $a->absorbed_matricule,
            'absorbed_nom_complet' => $a->absorbed_nom_complet,
            'etablissement_code' => $a->etablissement?->code ?? '',
            'merged_by' => $a->mergedBy?->name ?? 'Administrateur',
            'montant_versements_transferes' => (float) $a->montant_versements_transferes,
            'nb_versements_transferes' => (int) $a->nb_versements_transferes,
            'nb_notes_transferees' => (int) $a->nb_notes_transferees,
            'nb_conflits_notes_resolus' => (int) $a->nb_conflits_notes_resolus,
            'created_at' => $a->created_at?->toISOString(),
        ]);

        return Inertia::render('administration/eleves/doublons', [
            'suspects' => $suspects,
            'elevesOptions' => $elevesOptions,
            'ignores' => $ignores,
            'audits' => $audits,
            'anneesScolaires' => $anneesScolaires,
            'selectedAnneeId' => (int) $selectedAnneeId,
            'etablissements' => $context['etablissements']->map(fn ($e) => [
                'id' => $e->id,
                'code' => $e->code,
                'nom' => $e->nom,
            ])->values(),
            'selectedEtablissementId' => $effectiveEtabId ? (string) $effectiveEtabId : 'all',
            'canAccessAll' => $canAccessAll,
        ]);
    }

    /**
     * Endpoint JSON temps réel appelé depuis le formulaire « Nouvel Élève »
     * pour alerter immédiatement la secrétaire si un dossier similaire existe déjà.
     *
     * @param  Request  $request  Champs saisis dans le formulaire.
     * @param  EleveDoublonService  $doublonService  Service de détection.
     * @return JsonResponse Liste des dossiers potentiellement en doublon.
     */
    public function checkCreation(Request $request, EleveDoublonService $doublonService): JsonResponse
    {
        $validated = $request->validate([
            'nom' => ['nullable', 'string', 'max:150'],
            'prenom' => ['nullable', 'string', 'max:150'],
            'date_naissance' => ['nullable', 'string', 'max:20'],
            'telephone_parent' => ['nullable', 'string', 'max:50'],
            'classe_id' => ['nullable', 'integer'],
            'annee_scolaire_id' => ['nullable', 'integer'],
        ]);

        $context = $this->resolveEtablissementContext($request);
        $canAccessAll = (bool) ($context['canAccessAll'] ?? $context['isSuperAdmin']);
        $etablissementId = null;

        if (! $canAccessAll) {
            $etablissementId = $context['etablissementId'] ? (int) $context['etablissementId'] : null;
        } elseif (! empty($validated['classe_id'])) {
            $classe = Classe::with('niveau.cycle.section')->find($validated['classe_id']);
            $etablissementId = (int) ($classe?->etablissement_id ?? $classe?->niveau?->cycle?->section?->etablissement_id) ?: null;
        }

        $matches = $doublonService->detecterSimilairesPourCreation($validated, $etablissementId);

        return response()->json([
            'matches' => $matches,
        ]);
    }

    /**
     * Retourne la prévisualisation comparative détaillée entre le dossier Maître et le dossier Doublon,
     * incluant tous les conflits de notes éventuels à arbitrer avant fusion.
     *
     * @param  Request  $request  Requête contenant `master_id` et `duplicate_id`.
     * @param  EleveDoublonService  $doublonService  Service de prévisualisation.
     * @return JsonResponse Données comparatives structurées.
     */
    public function preview(Request $request, EleveDoublonService $doublonService): JsonResponse
    {
        $validated = $request->validate([
            'master_id' => ['required', 'integer', 'exists:eleves,id'],
            'duplicate_id' => ['required', 'integer', 'exists:eleves,id', 'different:master_id'],
        ]);

        $master = Eleve::findOrFail($validated['master_id']);
        $duplicate = Eleve::findOrFail($validated['duplicate_id']);

        $this->assertEtablissementAccessForPair($request, $master, $duplicate);

        return response()->json($doublonService->previsualiserFusion($master, $duplicate));
    }

    /**
     * Exécute la fusion atomique du dossier Doublon dans le dossier Maître.
     *
     * @param  Request  $request  Requête contenant les IDs, choix d'identité et arbitrages de conflits de notes.
     * @param  EleveDoublonService  $doublonService  Service de fusion transactionnelle.
     * @return RedirectResponse Redirection avec notification de confirmation détaillée.
     */
    public function merge(Request $request, EleveDoublonService $doublonService): RedirectResponse
    {
        $validated = $request->validate([
            'master_id' => ['required', 'integer', 'exists:eleves,id'],
            'duplicate_id' => ['required', 'integer', 'exists:eleves,id', 'different:master_id'],
            'identity_choices' => ['nullable', 'array'],
            'identity_choices.*' => ['in:master,duplicate'],
            'note_resolutions' => ['nullable', 'array'],
            'note_resolutions.*' => ['in:master,duplicate'],
        ]);

        $master = Eleve::findOrFail($validated['master_id']);
        $duplicate = Eleve::findOrFail($validated['duplicate_id']);

        $this->assertEtablissementAccessForPair($request, $master, $duplicate);

        $audit = $doublonService->fusionner($master, $duplicate, $request->user(), [
            'identity_choices' => $validated['identity_choices'] ?? [],
            'note_resolutions' => $validated['note_resolutions'] ?? [],
        ]);

        $message = sprintf(
            'Fusion réussie : %s absorbé dans %s (%d versement(s) / %s FCFA et %d note(s) transférés).',
            $audit->absorbed_matricule,
            $audit->master_matricule,
            $audit->nb_versements_transferes,
            number_format($audit->montant_versements_transferes, 0, ',', ' '),
            $audit->nb_notes_transferees
        );

        return back()->with('success', $message);
    }

    /**
     * Marque une paire d'élèves comme « Non doublons » (ex. jumeaux ou vrais homonymes).
     *
     * @param  Request  $request  Requête contenant `eleve_a_id`, `eleve_b_id` et un motif optionnel.
     * @return RedirectResponse Redirection avec confirmation.
     */
    public function ignore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'eleve_a_id' => ['required', 'integer', 'exists:eleves,id'],
            'eleve_b_id' => ['required', 'integer', 'exists:eleves,id', 'different:eleve_a_id'],
            'motif' => ['nullable', 'string', 'max:150'],
        ]);

        $eleveA = Eleve::findOrFail($validated['eleve_a_id']);
        $eleveB = Eleve::findOrFail($validated['eleve_b_id']);
        $this->assertEtablissementAccessForPair($request, $eleveA, $eleveB);

        $minId = min($eleveA->id, $eleveB->id);
        $maxId = max($eleveA->id, $eleveB->id);

        EleveDoublonIgnore::firstOrCreate(
            ['eleve_min_id' => $minId, 'eleve_max_id' => $maxId],
            [
                'motif' => ! empty($validated['motif']) ? trim($validated['motif']) : 'Jumeaux / Homonymes légitimes',
                'ignored_by_user_id' => $request->user()?->id,
            ]
        );

        return back()->with('success', 'Paire marquée comme non-doublon (jumeaux / homonymes).');
    }

    /**
     * Retire une paire de la liste des faux positifs ignorés.
     *
     * @param  EleveDoublonIgnore  $ignore  Enregistrement d'ignorance à supprimer.
     * @return RedirectResponse Redirection avec confirmation.
     */
    public function unignore(Request $request, EleveDoublonIgnore $ignore): RedirectResponse
    {
        $eleveA = Eleve::find($ignore->eleve_min_id);
        $eleveB = Eleve::find($ignore->eleve_max_id);
        if ($eleveA && $eleveB) {
            $this->assertEtablissementAccessForPair($request, $eleveA, $eleveB);
        }

        $ignore->delete();

        return back()->with('success', 'La paire a été rétablie dans le contrôle des doublons.');
    }

    /**
     * Vérifie le cloisonnement multi-établissement avant d'autoriser une action sur deux dossiers élèves.
     *
     * @param  Request  $request  Requête HTTP courante.
     * @param  Eleve  $eleveA  Premier élève concerné.
     * @param  Eleve  $eleveB  Second élève concerné.
     */
    private function assertEtablissementAccessForPair(Request $request, Eleve $eleveA, Eleve $eleveB): void
    {
        $context = $this->resolveEtablissementContext($request);
        if ($context['canAccessAll'] ?? $context['isSuperAdmin']) {
            return;
        }

        $scopedEtabId = (int) ($context['etablissementId'] ?? 0);
        if ($scopedEtabId <= 0) {
            abort(403, 'Aucun établissement assigné à votre compte.');
        }

        foreach ([$eleveA, $eleveB] as $el) {
            $belongs = Inscription::withoutGlobalScopes()
                ->where('eleve_id', $el->id)
                ->where('etablissement_id', $scopedEtabId)
                ->exists();
            if (! $belongs) {
                abort(403, "Action non autorisée : l'un des dossiers élèves appartient à un autre établissement.");
            }
        }
    }
}
