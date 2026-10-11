<?php

namespace App\Http\Controllers\Administration;

use App\Concerns\ScopesEtablissement;
use App\Http\Controllers\Controller;
use App\Models\AnneeScolaire;
use App\Models\Attribution;
use App\Models\Classe;
use App\Models\Etablissement;
use App\Models\Personnel;
use App\Models\Salle;
use App\Models\SeanceEmploiTemps;
use App\Services\EmploiDuTempsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Contrôleur principal pour le Module 13 : Emplois du Temps & Gestion des Salles (Gesco V1.1).
 *
 * Coordonne :
 * - La consultation multi-vues de l'emploi du temps (par classe, par salle physique, par enseignant).
 * - La programmation des cours avec détection préventive des collisions horaires.
 * - Le référentiel des locaux et gestion des capacités d'accueil.
 * - Les alertes de remplacement et d'annulation.
 * - Les décomptes horaires RH des enseignants.
 *
 * Utilise ScopesEtablissement pour cloisonner hermétiquement les plannings par campus.
 */
class EmploiDuTempsController extends Controller implements HasMiddleware
{
    use ScopesEtablissement;

    /**
     * Définition de la matrice des middlewares d'autorisation pour le module Emplois du Temps & Salles.
     *
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:emplois_du_temps.view', only: ['index']),
            new Middleware('can:emplois_du_temps.manage', only: [
                'storeSeance',
                'updateSeance',
                'destroySeance',
                'annulerSeance',
                'remplacerSeance',
                'retablirSeance',
                'storeSalle',
                'updateSalle',
                'destroySalle',
            ]),
        ];
    }

    /**
     * Affiche l'espace Emplois du Temps & Gestion des Salles.
     *
     * @param Request $request
     * @param EmploiDuTempsService $service
     * @return InertiaResponse
     */
    public function index(Request $request, EmploiDuTempsService $service): InertiaResponse
    {
        abort_if(! $request->user()?->can('emplois_du_temps.view') && ! $request->user()?->hasRole('super-admin'), 403);

        $currentAnnee = AnneeScolaire::where('is_current', true)->first()
            ?? AnneeScolaire::latest()->first();

        $anneeId = $request->integer('annee_scolaire_id', $currentAnnee?->id ?? 0);

        // Résolution hermétique du campus selon les prérogatives de l'utilisateur
        $context = $this->resolveEtablissementContext($request, ['institution']);
        $etablissements = $context['etablissements'];
        $currentEtablissement = $context['selectedEtablissement'];
        $etablissementId = $context['etablissementId'] ?? 0;

        // Classes de l'établissement
        $classes = Classe::whereHas('niveau.cycle.section', fn ($q) => $q->where('etablissement_id', $etablissementId))
            ->orderBy('nom')
            ->get();

        $classeId = $request->integer('classe_id', $classes->first()?->id ?? 0);

        // Salles de l'établissement
        $salles = Salle::where('etablissement_id', $etablissementId)
            ->withCount('seancesEmploiTemps')
            ->orderBy('nom')
            ->get();

        $salleId = $request->integer('salle_id', 0);

        // Enseignants de l'établissement
        $enseignants = Personnel::where('etablissement_id', $etablissementId)
            ->actif()
            ->orderBy('nom')
            ->get();

        $personnelId = $request->integer('personnel_id', 0);

        // Séances hebdomadaires selon les filtres
        $seancesQuery = SeanceEmploiTemps::with([
            'classe',
            'attribution.matiere',
            'attribution.personnel',
            'salle',
            'remplacant',
        ])
            ->where('annee_scolaire_id', $anneeId)
            ->whereHas('classe.niveau.cycle.section', fn ($q) => $q->where('etablissement_id', $etablissementId))
            ->orderByRaw("CASE jour_semaine WHEN 'lundi' THEN 1 WHEN 'mardi' THEN 2 WHEN 'mercredi' THEN 3 WHEN 'jeudi' THEN 4 WHEN 'vendredi' THEN 5 WHEN 'samedi' THEN 6 ELSE 7 END")
            ->orderBy('heure_debut');

        $user = $request->user();
        $isGestionnaire = $user?->hasRole(['super-admin', 'admin', 'directeur-etudes', 'censeur', 'surveillant-general'])
            || $user?->can('emplois_du_temps.manage');

        if (! $isGestionnaire) {
            // Un enseignant ne consulte que son propre emploi du temps
            $personnel = $user->personnel ?? ($user->email ? Personnel::where('email', $user->email)->first() : null);
            $personnelId = $personnel?->id ?? -1;

            $seancesQuery->where(function ($q) use ($personnelId) {
                $q->whereHas('attribution', fn ($sq) => $sq->where('personnel_id', $personnelId))
                  ->orWhere('remplace_par_personnel_id', $personnelId);
            });

            // Ne lister que ses propres classes et son propre profil
            $classes = Classe::whereHas('attributions', fn ($q) => $q->where('personnel_id', $personnelId)->where('annee_scolaire_id', $anneeId))
                ->orderBy('nom')
                ->get();
            $enseignants = $personnel ? collect([$personnel]) : collect([]);
            $salles = collect([]);
            $decomptesRH = $personnel ? [$personnel->id => $service->calculerDecompteHeuresEnseignant($anneeId, $personnel->id)] : [];
        } else {
            if ($classeId > 0) {
                $seancesQuery->where('classe_id', $classeId);
            }

            if ($salleId > 0) {
                $seancesQuery->where('salle_id', $salleId);
            }

            if ($personnelId > 0) {
                $seancesQuery->where(function ($q) use ($personnelId) {
                    $q->whereHas('attribution', fn ($sq) => $sq->where('personnel_id', $personnelId))
                      ->orWhere('remplace_par_personnel_id', $personnelId);
                });
            }

            // Décomptes RH pour les gestionnaires
            $decomptesRH = [];
            foreach ($enseignants as $ens) {
                $decomptesRH[$ens->id] = $service->calculerDecompteHeuresEnseignant($anneeId, $ens->id);
            }
        }

        $seances = $seancesQuery->get();

        // Attributions pour le formulaire d'ajout (cours disponibles dans la classe sélectionnée)
        $attributionsQuery = Attribution::with(['matiere', 'personnel'])
            ->where('annee_scolaire_id', $anneeId);

        if ($classeId > 0) {
            $attributionsQuery->where('classe_id', $classeId);
        }

        $attributions = $isGestionnaire ? $attributionsQuery->get() : collect([]);

        return Inertia::render('administration/emplois-du-temps/index', [
            'seances' => $seances,
            'classes' => $classes,
            'currentClasse' => Classe::find($classeId),
            'salles' => $salles,
            'currentSalle' => Salle::find($salleId),
            'enseignants' => $enseignants,
            'currentEnseignant' => Personnel::find($personnelId),
            'attributions' => $attributions,
            'decomptesRH' => $decomptesRH,
            'etablissements' => $etablissements,
            'currentEtablissement' => $currentEtablissement,
            'anneesScolaires' => AnneeScolaire::orderBy('code', 'desc')->get(),
            'currentAnnee' => $currentAnnee,
            'filters' => $request->only(['classe_id', 'salle_id', 'personnel_id', 'tab']),
        ]);
    }

    /**
     * Planifie une nouvelle séance dans la grille hebdomadaire (Gesco 13.2, 13.3).
     *
     * @param Request $request
     * @param EmploiDuTempsService $service
     * @return RedirectResponse
     */
    public function storeSeance(Request $request, EmploiDuTempsService $service): RedirectResponse
    {
        abort_if(! $request->user()?->can('emplois_du_temps.manage') && ! $request->user()?->hasRole('super-admin'), 403);

        $validated = $request->validate([
            'annee_scolaire_id' => 'required|exists:annees_scolaires,id',
            'classe_id' => 'required|exists:classes,id',
            'attribution_id' => 'required|exists:attributions,id',
            'salle_id' => 'nullable|exists:salles,id',
            'jour_semaine' => 'required|in:lundi,mardi,mercredi,jeudi,vendredi,samedi',
            'heure_debut' => 'required|date_format:H:i',
            'heure_fin' => 'required|date_format:H:i|after:heure_debut',
        ]);

        $classe = Classe::findOrFail($validated['classe_id']);
        $context = $this->resolveEtablissementContext($request);
        if (! $context['isSuperAdmin'] && (int) $classe->etablissement_id !== (int) $context['etablissementId']) {
            abort(403, 'Action non autorisée sur une classe d\'un autre établissement.');
        }

        $service->planifierSeance($validated);

        return back()->with('success', "Cours planifié avec succès dans l'emploi du temps (aucun conflit détecté).");
    }

    /**
     * Modifie une séance existante avec contrôle anti-conflits.
     *
     * @param Request $request
     * @param SeanceEmploiTemps $seance
     * @param EmploiDuTempsService $service
     * @return RedirectResponse
     */
    public function updateSeance(Request $request, SeanceEmploiTemps $seance, EmploiDuTempsService $service): RedirectResponse
    {
        abort_if(! $request->user()?->can('emplois_du_temps.manage') && ! $request->user()?->hasRole('super-admin'), 403);

        $seance->load('classe');
        $context = $this->resolveEtablissementContext($request);
        if (! $context['isSuperAdmin'] && (int) $seance->classe?->etablissement_id !== (int) $context['etablissementId']) {
            abort(403, 'Action non autorisée sur une séance d\'un autre établissement.');
        }

        $validated = $request->validate([
            'attribution_id' => 'sometimes|exists:attributions,id',
            'salle_id' => 'nullable|exists:salles,id',
            'jour_semaine' => 'sometimes|in:lundi,mardi,mercredi,jeudi,vendredi,samedi',
            'heure_debut' => 'sometimes|date_format:H:i',
            'heure_fin' => 'sometimes|date_format:H:i',
        ]);

        $service->modifierSeance($seance, $validated);

        return back()->with('success', "Séance d'emploi du temps modifiée avec succès.");
    }

    /**
     * Supprime définitivement une séance de l'emploi du temps.
     *
     * @param SeanceEmploiTemps $seance
     * @return RedirectResponse
     */
    public function destroySeance(SeanceEmploiTemps $seance): RedirectResponse
    {
        abort_if(! request()->user()?->can('emplois_du_temps.manage') && ! request()->user()?->hasRole('super-admin'), 403);

        $seance->load('classe');
        $context = $this->resolveEtablissementContext(request());
        if (! $context['isSuperAdmin'] && (int) $seance->classe?->etablissement_id !== (int) $context['etablissementId']) {
            abort(403, 'Action non autorisée sur une séance d\'un autre établissement.');
        }

        $seance->delete();

        return back()->with('success', "Séance retirée de la grille hebdomadaire.");
    }

    /**
     * Annule une séance pour absence de l'enseignant ou fermeture (Gesco 13.4).
     *
     * @param Request $request
     * @param SeanceEmploiTemps $seance
     * @param EmploiDuTempsService $service
     * @return RedirectResponse
     */
    public function annulerSeance(Request $request, SeanceEmploiTemps $seance, EmploiDuTempsService $service): RedirectResponse
    {
        abort_if(! $request->user()?->can('emplois_du_temps.manage') && ! $request->user()?->hasRole('super-admin'), 403);

        $seance->load('classe');
        $context = $this->resolveEtablissementContext($request);
        if (! $context['isSuperAdmin'] && (int) $seance->classe?->etablissement_id !== (int) $context['etablissementId']) {
            abort(403, 'Action non autorisée sur une séance d\'un autre établissement.');
        }

        $validated = $request->validate([
            'motif_annulation' => 'required|string|min:3|max:500',
        ]);

        $service->annulerSeance($seance, $validated['motif_annulation']);

        return back()->with('success', "Séance marquée comme annulée. Les emplois du temps ont été mis à jour.");
    }

    /**
     * Affecte un remplaçant sur un créneau (Gesco 13.4).
     *
     * @param Request $request
     * @param SeanceEmploiTemps $seance
     * @param EmploiDuTempsService $service
     * @return RedirectResponse
     */
    public function remplacerSeance(Request $request, SeanceEmploiTemps $seance, EmploiDuTempsService $service): RedirectResponse
    {
        abort_if(! $request->user()?->can('emplois_du_temps.manage') && ! $request->user()?->hasRole('super-admin'), 403);

        $seance->load('classe');
        $context = $this->resolveEtablissementContext($request);
        if (! $context['isSuperAdmin'] && (int) $seance->classe?->etablissement_id !== (int) $context['etablissementId']) {
            abort(403, 'Action non autorisée sur une séance d\'un autre établissement.');
        }

        $validated = $request->validate([
            'remplace_par_personnel_id' => 'required|exists:personnels,id',
            'motif_annulation' => 'nullable|string|max:500',
        ]);

        $service->affecterRemplacant($seance, $validated['remplace_par_personnel_id'], $validated['motif_annulation'] ?? null);

        return back()->with('success', "Enseignant remplaçant affecté avec succès.");
    }

    /**
     * Rétablit le statut actif normal d'une séance.
     *
     * @param SeanceEmploiTemps $seance
     * @param EmploiDuTempsService $service
     * @return RedirectResponse
     */
    public function retablirSeance(SeanceEmploiTemps $seance, EmploiDuTempsService $service): RedirectResponse
    {
        abort_if(! request()->user()?->can('emplois_du_temps.manage') && ! request()->user()?->hasRole('super-admin'), 403);

        $seance->load('classe');
        $context = $this->resolveEtablissementContext(request());
        if (! $context['isSuperAdmin'] && (int) $seance->classe?->etablissement_id !== (int) $context['etablissementId']) {
            abort(403, 'Action non autorisée sur une séance d\'un autre établissement.');
        }

        $service->retablirSeance($seance);

        return back()->with('success', "Séance rétablie avec succès.");
    }

    /**
     * Enregistre une nouvelle salle physique (Gesco 13.1).
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function storeSalle(Request $request): RedirectResponse
    {
        abort_if(! $request->user()?->can('emplois_du_temps.manage') && ! $request->user()?->hasRole('super-admin'), 403);

        $validated = $request->validate([
            'etablissement_id' => 'required|exists:etablissements,id',
            'nom' => 'required|string|max:100',
            'code' => 'required|string|max:50',
            'type_salle' => 'required|in:standard,laboratoire,informatique,sport,polyvalente',
            'capacite_places' => 'required|integer|min:5|max:500',
            'statut' => 'required|in:disponible,indisponible',
            'description' => 'nullable|string|max:1000',
        ]);

        $context = $this->resolveEtablissementContext($request);
        if (! $context['isSuperAdmin']) {
            $validated['etablissement_id'] = $context['etablissementId'];
        }

        Salle::create($validated);

        return back()->with('success', "Salle pédagogique enregistrée avec succès.");
    }

    /**
     * Modifie une salle physique existante.
     *
     * @param Request $request
     * @param Salle $salle
     * @return RedirectResponse
     */
    public function updateSalle(Request $request, Salle $salle): RedirectResponse
    {
        abort_if(! $request->user()?->can('emplois_du_temps.manage') && ! $request->user()?->hasRole('super-admin'), 403);

        $context = $this->resolveEtablissementContext($request);
        if (! $context['isSuperAdmin'] && (int) $salle->etablissement_id !== (int) $context['etablissementId']) {
            abort(403, 'Action non autorisée sur une salle d\'un autre établissement.');
        }

        $validated = $request->validate([
            'nom' => 'required|string|max:100',
            'code' => 'required|string|max:50',
            'type_salle' => 'required|in:standard,laboratoire,informatique,sport,polyvalente',
            'capacite_places' => 'required|integer|min:5|max:500',
            'statut' => 'required|in:disponible,indisponible',
            'description' => 'nullable|string|max:1000',
        ]);

        $salle->update($validated);

        return back()->with('success', "Caractéristiques de la salle actualisées.");
    }

    /**
     * Supprime une salle physique.
     *
     * @param Salle $salle
     * @return RedirectResponse
     */
    public function destroySalle(Salle $salle): RedirectResponse
    {
        abort_if(! request()->user()?->can('emplois_du_temps.manage') && ! request()->user()?->hasRole('super-admin'), 403);

        $context = $this->resolveEtablissementContext(request());
        if (! $context['isSuperAdmin'] && (int) $salle->etablissement_id !== (int) $context['etablissementId']) {
            abort(403, 'Action non autorisée sur une salle d\'un autre établissement.');
        }

        $salle->delete();

        return back()->with('success', "Salle retirée du répertoire.");
    }
}
