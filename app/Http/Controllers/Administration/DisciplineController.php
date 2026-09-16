<?php

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use App\Models\AnneeScolaire;
use App\Models\Assiduite;
use App\Models\Classe;
use App\Models\DisciplineBulletin;
use App\Models\Etablissement;
use App\Models\Inscription;
use App\Models\Matiere;
use App\Models\Periode;
use App\Models\SanctionDisciplinaire;
use App\Services\DisciplineService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Contrôleur principal pour le Module 12 : Absences, Retards & Discipline (Gesco V1.1).
 *
 * Coordonne la vie scolaire et disciplinaire :
 * - Suivi journalier des absences et retards horodatés.
 * - Justification a posteriori avec archivage de motif.
 * - Registre officiel des sanctions disciplinaires.
 * - Synchronisation automatique avec la synthèse des bulletins scolaires (Module 10).
 * - Contrôle de déchéance des mentions honorifiques pour absentéisme.
 */
class DisciplineController extends Controller
{
    /**
     * Affiche l'espace de gestion de l'assiduité et de la discipline.
     *
     * @param Request $request
     * @return InertiaResponse
     */
    public function index(Request $request): InertiaResponse
    {
        abort_if(! $request->user()?->can('discipline.view') && ! $request->user()?->hasRole('super-admin'), 403);

        $currentAnnee = AnneeScolaire::where('is_current', true)->first()
            ?? AnneeScolaire::latest()->first();

        $anneeId = $request->integer('annee_scolaire_id', $currentAnnee?->id ?? 0);
        $etablissements = Etablissement::with('institution')->get();
        $currentEtablissement = $request->has('etablissement_id')
            ? Etablissement::find($request->integer('etablissement_id'))
            : $etablissements->first();

        $etablissementId = $currentEtablissement?->id ?? 0;

        // Périodes / Trimestres de l'année
        $periodes = Periode::where('annee_scolaire_id', $anneeId)
            ->orderBy('ordre')
            ->get();

        $currentPeriode = $request->has('periode_id')
            ? Periode::find($request->integer('periode_id'))
            : $periodes->first();

        $periodeId = $currentPeriode?->id ?? 0;

        // Classes de l'établissement
        $classes = Classe::whereHas('niveau.cycle.section', fn ($q) => $q->where('etablissement_id', $etablissementId))
            ->orderBy('nom')
            ->get();

        $classeId = $request->integer('classe_id', 0);

        // 1. Liste des événements d'assiduité (Absences et Retards)
        $assiduitesQuery = Assiduite::with([
            'inscription.eleve',
            'inscription.classe',
            'matiere',
            'signalant',
        ])
            ->whereHas('inscription.classe.niveau.cycle.section', fn ($q) => $q->where('etablissement_id', $etablissementId))
            ->where('periode_id', $periodeId)
            ->latest('date_evenement');

        if ($classeId > 0) {
            $assiduitesQuery->whereHas('inscription', fn ($q) => $q->where('classe_id', $classeId));
        }

        if ($request->filled('type_evenement')) {
            $assiduitesQuery->where('type_evenement', $request->input('type_evenement'));
        }

        if ($request->filled('statut_justification')) {
            $assiduitesQuery->where('statut_justification', $request->input('statut_justification'));
        }

        $assiduites = $assiduitesQuery->take(100)->get();

        // 2. Registre des sanctions disciplinaires
        $sanctionsQuery = SanctionDisciplinaire::with([
            'inscription.eleve',
            'inscription.classe',
        ])
            ->whereHas('inscription.classe.niveau.cycle.section', fn ($q) => $q->where('etablissement_id', $etablissementId))
            ->where('periode_id', $periodeId)
            ->latest('date_decision');

        if ($classeId > 0) {
            $sanctionsQuery->whereHas('inscription', fn ($q) => $q->where('classe_id', $classeId));
        }

        $sanctions = $sanctionsQuery->get();

        // 3. Synthèse disciplinaire agrégée pour les bulletins
        $synthesesQuery = DisciplineBulletin::with([
            'inscription.eleve',
            'inscription.classe',
        ])
            ->where('periode_id', $periodeId)
            ->whereHas('inscription.classe.niveau.cycle.section', fn ($q) => $q->where('etablissement_id', $etablissementId));

        if ($classeId > 0) {
            $synthesesQuery->whereHas('inscription', fn ($q) => $q->where('classe_id', $classeId));
        }

        $syntheses = $synthesesQuery->get();

        // Matières pour le formulaire d'absence
        $matieres = Matiere::orderBy('nom')->get();

        // Élèves inscrits pour les sélecteurs
        $inscriptions = Inscription::with(['eleve', 'classe'])
            ->where('annee_scolaire_id', $anneeId)
            ->whereHas('classe.niveau.cycle.section', fn ($q) => $q->where('etablissement_id', $etablissementId))
            ->get();

        return Inertia::render('administration/discipline/index', [
            'assiduites' => $assiduites,
            'sanctions' => $sanctions,
            'syntheses' => $syntheses,
            'periodes' => $periodes,
            'currentPeriode' => $currentPeriode,
            'classes' => $classes,
            'matieres' => $matieres,
            'inscriptions' => $inscriptions,
            'etablissements' => $etablissements,
            'currentEtablissement' => $currentEtablissement,
            'anneesScolaires' => AnneeScolaire::orderBy('code', 'desc')->get(),
            'currentAnnee' => $currentAnnee,
            'filters' => $request->only(['classe_id', 'periode_id', 'type_evenement', 'statut_justification', 'tab']),
        ]);
    }

    /**
     * Enregistre un événement d'assiduité (absence ou retard).
     *
     * @param Request $request
     * @param DisciplineService $disciplineService
     * @return RedirectResponse
     */
    public function storeAssiduite(Request $request, DisciplineService $disciplineService): RedirectResponse
    {
        abort_if(! $request->user()?->can('discipline.manage') && ! $request->user()?->hasRole('super-admin'), 403);

        $validated = $request->validate([
            'inscription_id' => 'required|exists:inscriptions,id',
            'periode_id' => 'required|exists:periodes,id',
            'type_evenement' => 'required|in:absence,retard',
            'date_evenement' => 'required|date',
            'nombre_heures' => 'required|numeric|min:0.5|max:12',
            'matiere_id' => 'nullable|exists:matieres,id',
            'statut_justification' => 'required|in:justifiee,non_justifiee',
            'motif_justification' => 'nullable|string|max:500',
        ]);

        $disciplineService->enregistrerAssiduite($validated, $request->user());

        $typeLib = $validated['type_evenement'] === 'absence' ? 'Absence' : 'Retard';

        return back()->with('success', "{$typeLib} enregistré(e) avec succès. Synthèse du bulletin actualisée.");
    }

    /**
     * Justifie une absence ou un retard.
     *
     * @param Request $request
     * @param Assiduite $assiduite
     * @param DisciplineService $disciplineService
     * @return RedirectResponse
     */
    public function justifierAssiduite(Request $request, Assiduite $assiduite, DisciplineService $disciplineService): RedirectResponse
    {
        abort_if(! $request->user()?->can('discipline.manage') && ! $request->user()?->hasRole('super-admin'), 403);

        $validated = $request->validate([
            'motif_justification' => 'required|string|min:3|max:500',
        ]);

        $disciplineService->justifierAssiduite($assiduite, $validated['motif_justification']);

        return back()->with('success', "L'absence a été marquée comme justifiée avec succès.");
    }

    /**
     * Supprime un enregistrement d'assiduité et recalcule le bulletin.
     *
     * @param Assiduite $assiduite
     * @param DisciplineService $disciplineService
     * @return RedirectResponse
     */
    public function destroyAssiduite(Assiduite $assiduite, DisciplineService $disciplineService): RedirectResponse
    {
        abort_if(! request()->user()?->can('discipline.manage') && ! request()->user()?->hasRole('super-admin'), 403);

        $inscriptionId = $assiduite->inscription_id;
        $periodeId = $assiduite->periode_id;

        $assiduite->delete();

        $disciplineService->actualiserSyntheseBulletin($inscriptionId, $periodeId);

        return back()->with('success', "Événement d'assiduité retiré avec succès.");
    }

    /**
     * Prononce et enregistre une sanction disciplinaire officielle.
     *
     * @param Request $request
     * @param DisciplineService $disciplineService
     * @return RedirectResponse
     */
    public function storeSanction(Request $request, DisciplineService $disciplineService): RedirectResponse
    {
        abort_if(! $request->user()?->can('discipline.sanction') && ! $request->user()?->hasRole('super-admin'), 403);

        $validated = $request->validate([
            'inscription_id' => 'required|exists:inscriptions,id',
            'periode_id' => 'required|exists:periodes,id',
            'date_decision' => 'required|date',
            'type_sanction' => 'required|in:avertissement,blame,retenue,exclusion_temporaire,exclusion_definitive',
            'motif' => 'required|string|min:5|max:1000',
            'nombre_jours_ou_heures' => 'required|integer|min:1|max:30',
            'decide_par' => 'required|string|max:100',
        ]);

        $disciplineService->enregistrerSanction($validated);

        return back()->with('success', "Sanction disciplinaire enregistrée. Synthèse du bulletin actualisée.");
    }

    /**
     * Supprime une sanction disciplinaire.
     *
     * @param SanctionDisciplinaire $sanction
     * @param DisciplineService $disciplineService
     * @return RedirectResponse
     */
    public function destroySanction(SanctionDisciplinaire $sanction, DisciplineService $disciplineService): RedirectResponse
    {
        abort_if(! request()->user()?->can('discipline.sanction') && ! request()->user()?->hasRole('super-admin'), 403);

        $inscriptionId = $sanction->inscription_id;
        $periodeId = $sanction->periode_id;

        $sanction->delete();

        $disciplineService->actualiserSyntheseBulletin($inscriptionId, $periodeId);

        return back()->with('success', "Sanction disciplinaire annulée.");
    }
}
