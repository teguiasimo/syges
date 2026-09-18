<?php

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use App\Models\AnneeScolaire;
use App\Models\Etablissement;
use App\Models\Evaluation;
use App\Models\Periode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Contrôleur d'administration des Années Scolaires, Périodes et Paramètres d'Évaluation.
 *
 * Implémente le Module 3 du cahier des charges Gesco :
 * - Cadre temporel global (Années scolaires, statut, indicateur d'année active).
 * - Découpage périodique par établissement (Trimestres et Séquences d'évaluation).
 * - Verrouillage temporel et manuel de la saisie des notes pour les enseignants par campus.
 */
class PeriodeEvaluationController extends Controller implements HasMiddleware
{
    /**
     * Définition de la matrice des middlewares d'autorisation pour le module Périodes & Évaluations.
     *
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:periodes.view', only: ['index']),
            new Middleware('can:periodes.manage', only: [
                'storeAnnee',
                'updateAnnee',
                'setCurrentAnnee',
                'updateModeCalcul',
                'generateStandardStructure',
                'storeTrimestre',
                'updateTrimestre',
                'destroyTrimestre',
                'storeEvaluation',
                'updateEvaluation',
                'destroyEvaluation',
            ]),
            new Middleware('can:periodes.lock', only: ['toggleLockEvaluation']),
        ];
    }

    /**
     * Affiche l'interface de gestion des périodes et évaluations par établissement.
     *
     * @param Request $request Requête HTTP entrante.
     * @return Response Vue Inertia avec les établissements, années, trimestres et séquences.
     */
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('periodes.view'), 403, 'Accès non autorisé au module Périodes.');

        // Récupération de tous les campus / établissements de l'institution
        $etablissements = Etablissement::orderBy('nom')->get();
        $etablissementId = $request->query('etablissement_id');
        $selectedEtablissement = $etablissementId ? Etablissement::find($etablissementId) : $etablissements->first();

        // Récupération des années scolaires
        $annees = AnneeScolaire::orderByDesc('date_debut')->get();
        $anneeId = $request->query('annee_id');
        $selectedAnnee = $anneeId ? AnneeScolaire::find($anneeId) : AnneeScolaire::current();

        if (! $selectedAnnee && $annees->isNotEmpty()) {
            $selectedAnnee = $annees->first();
        }

        // Chargement des périodes et évaluations rattachées à la fois à l'année et à l'établissement actif
        $periodes = collect();
        if ($selectedAnnee && $selectedEtablissement) {
            $periodes = Periode::where('annee_scolaire_id', $selectedAnnee->id)
                ->where('etablissement_id', $selectedEtablissement->id)
                ->with(['evaluations' => function ($query) {
                    $query->orderBy('ordre');
                }])
                ->orderBy('ordre')
                ->get();
        }

        return Inertia::render('administration/periodes/index', [
            'etablissements' => $etablissements,
            'selectedEtablissement' => $selectedEtablissement,
            'annees' => $annees,
            'selectedAnnee' => $selectedAnnee,
            'periodes' => $periodes,
            'can' => [
                'periodes_manage' => $request->user()->can('periodes.manage'),
                'periodes_lock' => $request->user()->can('periodes.lock'),
            ],
        ]);
    }

    /**
     * Enregistre une nouvelle année scolaire.
     *
     * @param Request $request Requête HTTP avec données validées.
     * @return RedirectResponse Redirection vers la vue avec message de succès.
     */
    public function storeAnnee(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('periodes.manage'), 403, 'Permission requise : periodes.manage');

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:30', 'unique:annees_scolaires,code'],
            'nom' => ['nullable', 'string', 'max:120'],
            'nom_en' => ['nullable', 'string', 'max:120'],
            'date_debut' => ['required', 'date'],
            'date_fin' => ['required', 'date', 'after:date_debut'],
            'statut' => ['required', 'string', 'in:en_preparation,en_cours,cloturee'],
            'mode_calcul_moyenne' => ['nullable', 'string', 'in:arithmetique_simple,ponderee_pourcentage'],
            'is_current' => ['boolean'],
            'auto_generate_structure' => ['boolean'],
            'etablissement_id' => ['nullable', 'exists:etablissements,id'],
        ]);

        if (empty($validated['nom'])) {
            $validated['nom'] = "Année Scolaire {$validated['code']}";
        }
        if (empty($validated['nom_en'])) {
            $validated['nom_en'] = "Academic Year {$validated['code']}";
        }

        if (empty($validated['mode_calcul_moyenne'])) {
            $validated['mode_calcul_moyenne'] = 'arithmetique_simple';
        }

        $isCurrent = $validated['is_current'] ?? false;
        if ($isCurrent) {
            AnneeScolaire::where('is_current', true)->update(['is_current' => false]);
        }

        $autoGenerate = $validated['auto_generate_structure'] ?? false;
        $etablissementId = $validated['etablissement_id'] ?? Etablissement::first()?->id;
        unset($validated['auto_generate_structure'], $validated['etablissement_id']);

        $annee = AnneeScolaire::create($validated);

        // Auto-génération de la structure camerounaise standard (3 Trimestres / 6 Séquences) pour l'établissement cible
        if ($autoGenerate) {
            $this->seedStandardPeriodes($annee, $etablissementId);
        }

        return redirect()->route('administration.periodes.index', [
            'annee_id' => $annee->id,
            'etablissement_id' => $etablissementId,
        ])->with('success', "L'année scolaire « {$annee->nom} » a été créée avec succès.");
    }

    /**
     * Met à jour une année scolaire existante.
     *
     * @param Request $request Requête HTTP.
     * @param AnneeScolaire $anneeScolaire Modèle d'année scolaire.
     * @return RedirectResponse Redirection.
     */
    public function updateAnnee(Request $request, AnneeScolaire $anneeScolaire): RedirectResponse
    {
        abort_unless($request->user()->can('periodes.manage'), 403, 'Permission requise : periodes.manage');

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:30', Rule::unique('annees_scolaires', 'code')->ignore($anneeScolaire->id)],
            'nom' => ['nullable', 'string', 'max:120'],
            'nom_en' => ['nullable', 'string', 'max:120'],
            'date_debut' => ['required', 'date'],
            'date_fin' => ['required', 'date', 'after:date_debut'],
            'statut' => ['required', 'string', 'in:en_preparation,en_cours,cloturee'],
            'mode_calcul_moyenne' => ['nullable', 'string', 'in:arithmetique_simple,ponderee_pourcentage'],
            'is_current' => ['boolean'],
        ]);

        if (empty($validated['nom'])) {
            $validated['nom'] = $anneeScolaire->nom ?: "Année Scolaire {$validated['code']}";
        }
        if (empty($validated['nom_en'])) {
            $validated['nom_en'] = $anneeScolaire->nom_en ?: "Academic Year {$validated['code']}";
        }

        if (empty($validated['mode_calcul_moyenne'])) {
            unset($validated['mode_calcul_moyenne']);
        }

        if (! empty($validated['is_current']) && ! $anneeScolaire->is_current) {
            AnneeScolaire::where('is_current', true)->update(['is_current' => false]);
        }

        $anneeScolaire->update($validated);

        return back()->with('success', "L'année scolaire « {$anneeScolaire->nom} » a été mise à jour.");
    }

    /**
     * Définit une année scolaire comme l'année active courante du système.
     *
     * @param Request $request Requête HTTP.
     * @param AnneeScolaire $anneeScolaire Modèle à activer.
     * @return RedirectResponse Redirection.
     */
    public function setCurrentAnnee(Request $request, AnneeScolaire $anneeScolaire): RedirectResponse
    {
        abort_unless($request->user()->can('periodes.manage'), 403, 'Permission requise : periodes.manage');

        AnneeScolaire::where('is_current', true)->update(['is_current' => false]);
        $anneeScolaire->is_current = true;
        $anneeScolaire->save();

        return back()->with('success', "L'année scolaire « {$anneeScolaire->nom} » est désormais l'année active par défaut.");
    }

    /**
     * Met à jour le mode de calcul de la moyenne trimestrielle de l'année scolaire.
     *
     * @param Request $request Requête HTTP.
     * @param AnneeScolaire $anneeScolaire Modèle d'année scolaire.
     * @return RedirectResponse Redirection.
     */
    public function updateModeCalcul(Request $request, AnneeScolaire $anneeScolaire): RedirectResponse
    {
        abort_unless($request->user()->can('periodes.manage'), 403, 'Permission requise : periodes.manage');

        $validated = $request->validate([
            'mode_calcul_moyenne' => ['required', 'string', 'in:arithmetique_simple,ponderee_pourcentage'],
        ]);

        $anneeScolaire->mode_calcul_moyenne = $validated['mode_calcul_moyenne'];
        $anneeScolaire->save();

        $modeLabel = $validated['mode_calcul_moyenne'] === 'arithmetique_simple'
            ? 'Moyenne Arithmétique Simple'
            : 'Moyenne Pondérée par Pourcentages';

        return back()->with('success', "Le mode de calcul de la moyenne trimestrielle a été configuré sur « {$modeLabel} ».");
    }

    /**
     * Génère la structure officielle camerounaise (3 trimestres, 6 séquences) pour un établissement et une année cible.
     *
     * @param Request $request Requête HTTP.
     * @param AnneeScolaire $anneeScolaire Année cible.
     * @return RedirectResponse Redirection.
     */
    public function generateStandardStructure(Request $request, AnneeScolaire $anneeScolaire): RedirectResponse
    {
        abort_unless($request->user()->can('periodes.manage'), 403, 'Permission requise : periodes.manage');

        $etablissementId = $request->input('etablissement_id') ?? Etablissement::first()?->id;

        $hasPeriodes = $anneeScolaire->periodes()
            ->when($etablissementId, fn ($q) => $q->where('etablissement_id', $etablissementId))
            ->exists();

        if ($hasPeriodes) {
            return back()->with('error', 'Cet établissement contient déjà des trimestres configurés pour cette année scolaire.');
        }

        $this->seedStandardPeriodes($anneeScolaire, $etablissementId);

        return back()->with('success', 'La structure officielle (3 Trimestres et 6 Séquences) a été générée avec succès pour cet établissement.');
    }

    /**
     * Enregistre un nouveau trimestre pour un établissement donné.
     *
     * @param Request $request Requête HTTP.
     * @return RedirectResponse Redirection.
     */
    public function storeTrimestre(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('periodes.manage'), 403, 'Permission requise : periodes.manage');

        $validated = $request->validate([
            'etablissement_id' => ['nullable', 'exists:etablissements,id'],
            'annee_scolaire_id' => ['required', 'exists:annees_scolaires,id'],
            'code' => ['required', 'string', 'max:30'],
            'nom' => ['required', 'string', 'max:100'],
            'nom_en' => ['nullable', 'string', 'max:100'],
            'ordre' => ['required', 'integer', 'min:1'],
            'date_debut' => ['nullable', 'date'],
            'date_fin' => ['nullable', 'date'],
            'statut' => ['required', 'string', 'in:a_venir,en_cours,cloturee'],
        ]);

        $etablissementId = $validated['etablissement_id'] ?? Etablissement::first()?->id;
        $validated['etablissement_id'] = $etablissementId;

        $exists = Periode::where('annee_scolaire_id', $validated['annee_scolaire_id'])
            ->where('etablissement_id', $etablissementId)
            ->where('code', $validated['code'])
            ->exists();

        if ($exists) {
            return back()->with('error', 'Un trimestre portant ce code existe déjà dans cet établissement pour cette année scolaire.');
        }

        Periode::create($validated);

        return back()->with('success', "Le trimestre « {$validated['nom']} » a été ajouté avec succès.");
    }

    /**
     * Modifie un trimestre existant.
     *
     * @param Request $request Requête HTTP.
     * @param Periode $periode Modèle de période.
     * @return RedirectResponse Redirection.
     */
    public function updateTrimestre(Request $request, Periode $periode): RedirectResponse
    {
        abort_unless($request->user()->can('periodes.manage'), 403, 'Permission requise : periodes.manage');

        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('periodes', 'code')
                    ->where('annee_scolaire_id', $periode->annee_scolaire_id)
                    ->where('etablissement_id', $periode->etablissement_id)
                    ->ignore($periode->id),
            ],
            'nom' => ['required', 'string', 'max:100'],
            'nom_en' => ['nullable', 'string', 'max:100'],
            'ordre' => ['required', 'integer', 'min:1'],
            'date_debut' => ['nullable', 'date'],
            'date_fin' => ['nullable', 'date'],
            'statut' => ['required', 'string', 'in:a_venir,en_cours,cloturee'],
        ]);

        $periode->update($validated);

        return back()->with('success', "Le trimestre « {$periode->nom} » a été mis à jour.");
    }

    /**
     * Supprime un trimestre s'il ne contient pas d'évaluations.
     *
     * @param Request $request Requête HTTP.
     * @param Periode $periode Modèle à supprimer.
     * @return RedirectResponse Redirection.
     */
    public function destroyTrimestre(Request $request, Periode $periode): RedirectResponse
    {
        abort_unless($request->user()->can('periodes.manage'), 403, 'Permission requise : periodes.manage');

        if ($periode->evaluations()->exists()) {
            return back()->with('error', 'Suppression impossible : ce trimestre contient encore des séquences d\'évaluation.');
        }

        $nom = $periode->nom;
        $periode->delete();

        return back()->with('success', "Le trimestre « {$nom} » a été supprimé.");
    }

    /**
     * Enregistre une nouvelle évaluation / séquence.
     *
     * @param Request $request Requête HTTP.
     * @return RedirectResponse Redirection.
     */
    public function storeEvaluation(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('periodes.manage'), 403, 'Permission requise : periodes.manage');

        $validated = $request->validate([
            'periode_id' => ['required', 'exists:periodes,id'],
            'code' => ['required', 'string', 'max:30'],
            'nom' => ['required', 'string', 'max:100'],
            'nom_en' => ['nullable', 'string', 'max:100'],
            'ordre' => ['required', 'integer', 'min:1'],
            'ponderation' => ['required', 'numeric', 'min:0', 'max:100'],
            'date_debut' => ['nullable', 'date'],
            'date_fin' => ['nullable', 'date'],
            'date_limite_saisie' => ['nullable', 'date'],
            'statut' => ['required', 'string', 'in:a_venir,ouverte,verrouillee,cloturee'],
        ]);

        $exists = Evaluation::where('periode_id', $validated['periode_id'])
            ->where('code', $validated['code'])
            ->exists();

        if ($exists) {
            return back()->with('error', 'Une évaluation avec ce code existe déjà dans ce trimestre.');
        }

        Evaluation::create($validated);

        return back()->with('success', "La séquence « {$validated['nom']} » a été ajoutée avec succès.");
    }

    /**
     * Modifie une évaluation existante.
     *
     * @param Request $request Requête HTTP.
     * @param Evaluation $evaluation Modèle d'évaluation.
     * @return RedirectResponse Redirection.
     */
    public function updateEvaluation(Request $request, Evaluation $evaluation): RedirectResponse
    {
        abort_unless($request->user()->can('periodes.manage'), 403, 'Permission requise : periodes.manage');

        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('evaluations', 'code')
                    ->where('periode_id', $evaluation->periode_id)
                    ->ignore($evaluation->id),
            ],
            'nom' => ['required', 'string', 'max:100'],
            'nom_en' => ['nullable', 'string', 'max:100'],
            'ordre' => ['required', 'integer', 'min:1'],
            'ponderation' => ['required', 'numeric', 'min:0', 'max:100'],
            'date_debut' => ['nullable', 'date'],
            'date_fin' => ['nullable', 'date'],
            'date_limite_saisie' => ['nullable', 'date'],
            'statut' => ['required', 'string', 'in:a_venir,ouverte,verrouillee,cloturee'],
        ]);

        $evaluation->update($validated);

        return back()->with('success', "La séquence « {$evaluation->nom} » a été mise à jour.");
    }

    /**
     * Supprime une évaluation / séquence.
     *
     * @param Request $request Requête HTTP.
     * @param Evaluation $evaluation Modèle à supprimer.
     * @return RedirectResponse Redirection.
     */
    public function destroyEvaluation(Request $request, Evaluation $evaluation): RedirectResponse
    {
        abort_unless($request->user()->can('periodes.manage'), 403, 'Permission requise : periodes.manage');

        $nom = $evaluation->nom;
        $evaluation->delete();

        return back()->with('success', "L'évaluation « {$nom} » a été supprimée avec succès.");
    }

    /**
     * Bascule le verrouillage manuel de la saisie des notes pour une évaluation.
     *
     * @param Request $request Requête HTTP.
     * @param Evaluation $evaluation Modèle d'évaluation.
     * @return RedirectResponse Redirection.
     */
    public function toggleLockEvaluation(Request $request, Evaluation $evaluation): RedirectResponse
    {
        abort_unless($request->user()->can('periodes.lock'), 403, 'Permission requise : periodes.lock');

        $evaluation->is_locked = ! $evaluation->is_locked;
        if ($evaluation->is_locked) {
            $evaluation->statut = 'verrouillee';
        } elseif ($evaluation->statut === 'verrouillee') {
            $evaluation->statut = 'ouverte';
        }
        $evaluation->save();

        $action = $evaluation->is_locked ? 'verrouillée' : 'déverrouillée';

        return back()->with('success', "La saisie des notes pour la « {$evaluation->nom} » est désormais {$action}.");
    }

    /**
     * Méthode d'aide privée : Génère 3 trimestres et 6 séquences standards pour un établissement.
     *
     * @param AnneeScolaire $annee L'année scolaire cible.
     * @param int|null $etablissementId Identifiant de l'établissement cible.
     */
    private function seedStandardPeriodes(AnneeScolaire $annee, ?int $etablissementId = null): void
    {
        $etablissementId = $etablissementId ?? Etablissement::first()?->id;
        $yearStart = $annee->date_debut ? $annee->date_debut->year : 2026;
        $yearEnd = $annee->date_fin ? $annee->date_fin->year : 2027;

        $trimestres = [
            [
                'code' => 'TRIM-1',
                'nom' => '1er Trimestre',
                'nom_en' => '1st Term',
                'ordre' => 1,
                'date_debut' => "{$yearStart}-09-01",
                'date_fin' => "{$yearStart}-11-30",
                'statut' => 'en_cours',
                'sequences' => [
                    ['code' => 'SEQ-1', 'nom' => 'Séquence 1', 'nom_en' => 'Sequence 1', 'ordre' => 1, 'ponderation' => 50.0, 'date_debut' => "{$yearStart}-09-01", 'date_fin' => "{$yearStart}-10-15", 'date_limite_saisie' => "{$yearStart}-10-22"],
                    ['code' => 'SEQ-2', 'nom' => 'Séquence 2', 'nom_en' => 'Sequence 2', 'ordre' => 2, 'ponderation' => 50.0, 'date_debut' => "{$yearStart}-10-16", 'date_fin' => "{$yearStart}-11-30", 'date_limite_saisie' => "{$yearStart}-12-05"],
                ],
            ],
            [
                'code' => 'TRIM-2',
                'nom' => '2ème Trimestre',
                'nom_en' => '2nd Term',
                'ordre' => 2,
                'date_debut' => "{$yearStart}-12-01",
                'date_fin' => "{$yearEnd}-03-15",
                'statut' => 'a_venir',
                'sequences' => [
                    ['code' => 'SEQ-3', 'nom' => 'Séquence 3', 'nom_en' => 'Sequence 3', 'ordre' => 3, 'ponderation' => 50.0, 'date_debut' => "{$yearStart}-12-01", 'date_fin' => "{$yearEnd}-01-31", 'date_limite_saisie' => "{$yearEnd}-02-07"],
                    ['code' => 'SEQ-4', 'nom' => 'Séquence 4', 'nom_en' => 'Sequence 4', 'ordre' => 4, 'ponderation' => 50.0, 'date_debut' => "{$yearEnd}-02-01", 'date_fin' => "{$yearEnd}-03-15", 'date_limite_saisie' => "{$yearEnd}-03-22"],
                ],
            ],
            [
                'code' => 'TRIM-3',
                'nom' => '3ème Trimestre',
                'nom_en' => '3rd Term',
                'ordre' => 3,
                'date_debut' => "{$yearEnd}-03-16",
                'date_fin' => "{$yearEnd}-06-30",
                'statut' => 'a_venir',
                'sequences' => [
                    ['code' => 'SEQ-5', 'nom' => 'Séquence 5', 'nom_en' => 'Sequence 5', 'ordre' => 5, 'ponderation' => 50.0, 'date_debut' => "{$yearEnd}-03-16", 'date_fin' => "{$yearEnd}-05-15", 'date_limite_saisie' => "{$yearEnd}-05-22"],
                    ['code' => 'SEQ-6', 'nom' => 'Séquence 6', 'nom_en' => 'Sequence 6', 'ordre' => 6, 'ponderation' => 50.0, 'date_debut' => "{$yearEnd}-05-16", 'date_fin' => "{$yearEnd}-06-30", 'date_limite_saisie' => "{$yearEnd}-07-05"],
                ],
            ],
        ];

        foreach ($trimestres as $tData) {
            $sequences = $tData['sequences'];
            unset($tData['sequences']);

            $tData['annee_scolaire_id'] = $annee->id;
            $tData['etablissement_id'] = $etablissementId;
            $trimestre = Periode::create($tData);

            foreach ($sequences as $sData) {
                $sData['periode_id'] = $trimestre->id;
                $sData['statut'] = 'a_venir';
                $sData['is_locked'] = false;
                Evaluation::create($sData);
            }
        }
    }
}
