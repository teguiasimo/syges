<?php

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use App\Models\AnneeScolaire;
use App\Models\Attribution;
use App\Models\Classe;
use App\Models\Etablissement;
use App\Models\Matiere;
use App\Models\MatiereNiveau;
use App\Models\Personnel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Contrôleur de gestion du Module 9 : Attribution des Classes & Matières (Répartition pédagogique).
 * Conforme au Cahier des Charges Gesco V1.1 (Sections 9.1, 9.2 et 2.7.5).
 * 
 * Assure la passerelle opérationnelle gérée par le Censeur / Directeur des Études :
 * - Répartition des enseignements par classe et par discipline pour l'année scolaire active.
 * - Suivi et calcul en temps réel de la charge horaire des enseignants (sous-charge, équilibré, surcharge/vacations).
 * - Désignation des professeurs principaux de chaque classe.
 * - Provisioning automatique des droits de consultation et de saisie des notes (Module 10).
 */
class AttributionController extends Controller implements HasMiddleware
{
    /**
     * Définition de la matrice des middlewares d'autorisation pour le module Attributions.
     *
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:attributions.view', only: ['index']),
            new Middleware('can:attributions.manage', only: [
                'store',
                'update',
                'setProfesseurPrincipal',
                'batchAssign',
            ]),
            new Middleware('can:attributions.delete', only: ['destroy']),
        ];
    }

    /**
     * Affiche l'interface de répartition pédagogique avec double vue (par Classe et par Enseignant).
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Inertia\Response
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $isSuperAdmin = $user?->is_super_admin ?? false;

        abort_unless(
            $isSuperAdmin || $user?->can('attributions.view'),
            403,
            'Accès non autorisé : vous ne disposez pas de la permission de consulter les attributions.'
        );

        // 1. Années scolaires et sélection active
        $anneesScolaires = AnneeScolaire::orderByDesc('is_current')->orderByDesc('date_debut')->get();
        $selectedAnneeId = (int) ($request->query('annee_scolaire_id') ?? ($anneesScolaires->firstWhere('is_current', true)?->id ?? $anneesScolaires->first()?->id ?? 1));

        // 2. Filtres
        $etablissementId = $request->query('etablissement_id');
        $classeId = $request->query('classe_id');

        // 3. Établissements pour le filtrage
        $etablissements = Etablissement::select('id', 'nom', 'code')->orderBy('nom')->get();

        // 4. Récupération des classes avec niveau, professeur principal et attributions pour l'année active
        $classesQuery = Classe::query()
            ->with([
                'niveau.cycle.section.etablissement:id,nom,code',
                'professeurPrincipal:id,nom,prenom,matricule,telephone',
                'attributions' => function ($q) use ($selectedAnneeId) {
                    $q->where('annee_scolaire_id', $selectedAnneeId)
                        ->with(['personnel:id,nom,prenom,matricule,telephone,specialite', 'matiere:id,code,nom,libelle_bulletin']);
                },
            ])
            ->where('is_active', true)
            ->orderBy('nom');

        if ($etablissementId) {
            $classesQuery->whereHas('niveau.cycle.section', function ($q) use ($etablissementId) {
                $q->where('etablissement_id', $etablissementId);
            });
        }

        if ($classeId) {
            $classesQuery->where('id', $classeId);
        }

        $classes = $classesQuery->get();

        // 5. Matrice des coefficients par niveau pour identifier les matières requises par classe
        $matiereNiveaux = MatiereNiveau::with(['matiere:id,code,nom,libelle_bulletin', 'groupeMatiere:id,code,nom,ordre'])
            ->get();

        // 6. Corps enseignant avec calcul de la charge horaire pour l'année active
        $enseignants = Personnel::query()
            ->where(function ($q) {
                $q->where('is_enseignant', true)
                    ->orWhere('fonction_principale', 'Enseignant');
            })
            ->where('statut', 'Actif')
            ->with([
                'departement:id,nom,code',
                'etablissement:id,nom,code',
                'attributions' => function ($q) use ($selectedAnneeId) {
                    $q->where('annee_scolaire_id', $selectedAnneeId)
                        ->with(['classe:id,nom,code', 'matiere:id,code,nom']);
                },
            ])
            ->orderBy('nom')
            ->get()
            ->map(function (Personnel $ens) use ($selectedAnneeId) {
                $heuresContractuelles = (float) $ens->volume_horaire_hebdo;
                $heuresAttribuees = (float) $ens->attributions->sum('volume_horaire_reel');
                $ecart = $heuresAttribuees - $heuresContractuelles;

                $statutCharge = 'equilibre';
                if ($heuresAttribuees < $heuresContractuelles) {
                    $statutCharge = 'sous_charge';
                } elseif ($heuresAttribuees > $heuresContractuelles) {
                    $statutCharge = 'surcharge';
                }

                return [
                    'id' => $ens->id,
                    'matricule' => $ens->matricule,
                    'nom' => $ens->nom,
                    'prenom' => $ens->prenom,
                    'nom_complet' => $ens->nom_complet,
                    'telephone' => $ens->telephone,
                    'specialite' => $ens->specialite,
                    'departement' => $ens->departement,
                    'etablissement' => $ens->etablissement,
                    'volume_horaire_hebdo' => $heuresContractuelles,
                    'heures_attribuees' => $heuresAttribuees,
                    'ecart' => $ecart,
                    'statut_charge' => $statutCharge,
                    'taux_vacation' => $ens->taux_horaire_vacation,
                    'attributions' => $ens->attributions,
                ];
            });

        // 7. Catalogue des matières pour le sélecteur d'attribution
        $matieres = Matiere::where('is_active', true)->orderBy('nom')->get();

        // 8. Synthèse statistique globale KPI
        $totalClasses = $classes->count();
        $totalAttributions = Attribution::where('annee_scolaire_id', $selectedAnneeId)->count();
        $enseignantsMobilises = $enseignants->where('heures_attribuees', '>', 0)->count();
        $enseignantsSurcharge = $enseignants->where('statut_charge', 'surcharge')->count();
        $enseignantsSousCharge = $enseignants->where('statut_charge', 'sous_charge')->count();
        $heuresTotalesReelles = $enseignants->sum('heures_attribuees');

        // Calcul du taux moyen de couverture des cours
        $totalMatieresRequises = 0;
        $totalMatieresPourvues = 0;
        foreach ($classes as $classe) {
            $matieresRequisesPourClasse = $matiereNiveaux->filter(function ($mn) use ($classe) {
                return $mn->niveau_id === $classe->niveau_id &&
                    ($mn->serie === 'Toutes' || empty($mn->serie) || $mn->serie === $classe->serie);
            })->pluck('matiere_id')->unique();

            $nbRequises = $matieresRequisesPourClasse->count();
            $totalMatieresRequises += $nbRequises;

            $matieresPourvuesIds = $classe->attributions->pluck('matiere_id')->unique();
            $totalMatieresPourvues += $matieresPourvuesIds->intersect($matieresRequisesPourClasse)->count();
        }

        $tauxCouverture = $totalMatieresRequises > 0 
            ? round(($totalMatieresPourvues / $totalMatieresRequises) * 100, 1) 
            : 100.0;

        $stats = [
            'totalClasses' => $totalClasses,
            'totalAttributions' => $totalAttributions,
            'enseignantsMobilises' => $enseignantsMobilises,
            'totalEnseignants' => $enseignants->count(),
            'enseignantsSurcharge' => $enseignantsSurcharge,
            'enseignantsSousCharge' => $enseignantsSousCharge,
            'heuresTotalesReelles' => $heuresTotalesReelles,
            'tauxCouverture' => $tauxCouverture,
        ];

        $can = [
            'manage' => $isSuperAdmin || $user?->can('attributions.manage'),
            'delete' => $isSuperAdmin || $user?->can('attributions.delete'),
        ];

        return Inertia::render('administration/attributions/index', [
            'anneesScolaires' => $anneesScolaires,
            'selectedAnneeId' => $selectedAnneeId,
            'etablissements' => $etablissements,
            'classes' => $classes,
            'enseignants' => $enseignants,
            'matieres' => $matieres,
            'matiereNiveaux' => $matiereNiveaux,
            'stats' => $stats,
            'can' => $can,
            'filters' => [
                'annee_scolaire_id' => $selectedAnneeId,
                'etablissement_id' => $etablissementId ?? '',
                'classe_id' => $classeId ?? '',
            ],
        ]);
    }

    /**
     * Enregistre une attribution individuelle (Enseignant <-> Classe <-> Matière).
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless(
            $user?->is_super_admin || $user?->can('attributions.manage'),
            403,
            'Accès non autorisé : vous ne disposez pas de la permission d\'affecter un cours.'
        );

        $validated = $request->validate([
            'annee_scolaire_id' => ['required', 'exists:annees_scolaires,id'],
            'classe_id' => ['required', 'exists:classes,id'],
            'matiere_id' => ['required', 'exists:matieres,id'],
            'personnel_id' => ['required', 'exists:personnels,id'],
            'volume_horaire_reel' => ['required', 'numeric', 'min:0', 'max:50'],
            'is_titulaire' => ['boolean'],
        ]);

        // Vérification de la contrainte d'unicité (Gesco 9.1)
        $existing = Attribution::where('annee_scolaire_id', $validated['annee_scolaire_id'])
            ->where('classe_id', $validated['classe_id'])
            ->where('matiere_id', $validated['matiere_id'])
            ->first();

        if ($existing) {
            // Mise à jour de l'enseignant si l'attribution existe déjà
            $existing->update([
                'personnel_id' => $validated['personnel_id'],
                'volume_horaire_reel' => $validated['volume_horaire_reel'],
                'is_titulaire' => $validated['is_titulaire'] ?? true,
                'is_active' => true,
            ]);

            return back()->with('success', 'Attribution du cours mise à jour avec succès.');
        }

        Attribution::create([
            'annee_scolaire_id' => $validated['annee_scolaire_id'],
            'classe_id' => $validated['classe_id'],
            'matiere_id' => $validated['matiere_id'],
            'personnel_id' => $validated['personnel_id'],
            'volume_horaire_reel' => $validated['volume_horaire_reel'],
            'is_titulaire' => $validated['is_titulaire'] ?? true,
            'is_active' => true,
        ]);

        return back()->with('success', 'Enseignant affecté au cours avec succès.');
    }

    /**
     * Met à jour une attribution existante.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Attribution  $attribution
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, Attribution $attribution): RedirectResponse
    {
        $user = $request->user();
        abort_unless(
            $user?->is_super_admin || $user?->can('attributions.manage'),
            403,
            'Accès non autorisé : vous ne disposez pas de la permission de modifier cette attribution.'
        );

        $validated = $request->validate([
            'personnel_id' => ['required', 'exists:personnels,id'],
            'volume_horaire_reel' => ['required', 'numeric', 'min:0', 'max:50'],
            'is_titulaire' => ['boolean'],
            'is_active' => ['boolean'],
        ]);

        $attribution->update($validated);

        return back()->with('success', 'Attribution mise à jour avec succès.');
    }

    /**
     * Révoque / supprime une attribution de cours.
     *
     * @param  \App\Models\Attribution  $attribution
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Attribution $attribution): RedirectResponse
    {
        $user = request()->user();
        abort_unless(
            $user?->is_super_admin || $user?->can('attributions.delete'),
            403,
            'Accès non autorisé : vous ne disposez pas de la permission de révoquer cette attribution.'
        );

        $attribution->delete();

        return back()->with('success', 'Attribution de cours révoquée.');
    }

    /**
     * Désigne ou met à jour le Professeur Principal de la classe (Gesco Section 2.7.5).
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Classe  $classe
     * @return \Illuminate\Http\RedirectResponse
     */
    public function setProfesseurPrincipal(Request $request, Classe $classe): RedirectResponse
    {
        $user = $request->user();
        abort_unless(
            $user?->is_super_admin || $user?->can('attributions.manage'),
            403,
            'Accès non autorisé : vous ne disposez pas de la permission de désigner le professeur principal.'
        );

        $validated = $request->validate([
            'professeur_principal_id' => ['nullable', 'exists:personnels,id'],
        ]);

        $classe->update(['professeur_principal_id' => $validated['professeur_principal_id'] ?? null]);

        $profNom = $classe->professeurPrincipal ? $classe->professeurPrincipal->nom_complet : 'aucun';

        return back()->with('success', "Professeur Principal de la classe {$classe->nom} défini : {$profNom}.");
    }

    /**
     * Affectation en masse des matières d'une classe (Matrice rapide par classe).
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Classe  $classe
     * @return \Illuminate\Http\RedirectResponse
     */
    public function batchAssign(Request $request, Classe $classe): RedirectResponse
    {
        $user = $request->user();
        abort_unless(
            $user?->is_super_admin || $user?->can('attributions.manage'),
            403,
            'Accès non autorisé pour affecter les matières en lot.'
        );

        $validated = $request->validate([
            'annee_scolaire_id' => ['required', 'exists:annees_scolaires,id'],
            'assignments' => ['required', 'array'],
            'assignments.*.matiere_id' => ['required', 'exists:matieres,id'],
            'assignments.*.personnel_id' => ['nullable', 'exists:personnels,id'],
            'assignments.*.volume_horaire_reel' => ['required', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($classe, $validated) {
            $anneeId = $validated['annee_scolaire_id'];

            foreach ($validated['assignments'] as $item) {
                $matiereId = $item['matiere_id'];
                $personnelId = $item['personnel_id'] ?? null;
                $volumeHoraire = $item['volume_horaire_reel'];

                if (! empty($personnelId)) {
                    // Crée ou met à jour l'attribution
                    Attribution::updateOrCreate(
                        [
                            'annee_scolaire_id' => $anneeId,
                            'classe_id' => $classe->id,
                            'matiere_id' => $matiereId,
                        ],
                        [
                            'personnel_id' => $personnelId,
                            'volume_horaire_reel' => $volumeHoraire,
                            'is_titulaire' => true,
                            'is_active' => true,
                        ]
                    );
                } else {
                    // Si aucun personnel sélectionné, on retire l'attribution existante si présente
                    Attribution::where('annee_scolaire_id', $anneeId)
                        ->where('classe_id', $classe->id)
                        ->where('matiere_id', $matiereId)
                        ->delete();
                }
            }
        });

        return back()->with('success', "Grille de répartition de la classe {$classe->nom} mise à jour avec succès.");
    }
}
