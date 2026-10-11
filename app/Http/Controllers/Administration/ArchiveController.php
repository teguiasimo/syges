<?php

namespace App\Http\Controllers\Administration;

use App\Concerns\ScopesEtablissement;
use App\Http\Controllers\Controller;
use App\Models\AnneeScolaire;
use App\Models\ArchiveAdministrative;
use App\Models\ConfigurationNumerotation;
use App\Models\Eleve;
use App\Models\Inscription;
use App\Models\Personnel;
use App\Services\ReconductionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Contrôleur du Centre d'Archivage Administratif et de la Reconduction Annuelle (Aurore §3.15, §3.16, §3.27.8, §3.28.3).
 * 
 * Permet l'archivage légal des élèves et personnels sortants avec motif et destination,
 * la gestion des masques de numérotation séquentiels (radicaux/souches),
 * et l'assistant de reconduction annuelle en 1 clic.
 */
class ArchiveController extends Controller implements HasMiddleware
{
    use ScopesEtablissement;

    /**
     * Définition de la matrice des middlewares d'autorisation pour l'Archivage & Reconduction.
     *
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:archivage.view|etablissements.manage', only: ['index']),
            new Middleware('can:archivage.archiver|etablissements.manage', only: ['store', 'restaurer']),
            new Middleware('can:archivage.numerotation|etablissements.manage', only: ['updateNumerotation']),
            new Middleware('can:archivage.dupliquer|etablissements.manage', only: ['dupliquerAnnee']),
        ];
    }
    /**
     * Affiche la vue principale du Centre d'Archivage et Paramètres de Numérotation.
     *
     * @param Request $request
     * @return InertiaResponse
     */
    public function index(Request $request): InertiaResponse
    {
        // Récupération de l'utilisateur authentifié pour la résolution des droits
        $user = $request->user();
        $context = $this->resolveEtablissementContext($request);
        $etablissementId = $context['etablissementId'];

        $typeEntite = $request->input('type_entite', 'all'); // 'eleve', 'personnel', 'all'
        $motif = $request->input('motif');
        $statut = $request->input('statut', 'archive'); // 'archive', 'restaure', 'all'
        $search = $request->input('search');

        // Requête principale des archives administratives
        $query = ArchiveAdministrative::with([
            'eleve:id,matricule,nom,prenom',
            'personnel:id,matricule,nom,prenom',
            'anneeScolaire:id,nom,code',
            'archivePar:id,name',
            'restaurePar:id,name',
        ])
        ->where('etablissement_id', $etablissementId);

        if ($typeEntite && in_array($typeEntite, ['eleve', 'personnel'])) {
            $query->where('type_entite', $typeEntite);
        }

        if ($motif) {
            $query->where('motif', $motif);
        }

        if ($statut && in_array($statut, ['archive', 'restaure'])) {
            $query->where('statut', $statut);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('matricule', 'like', "%{$search}%")
                  ->orWhere('nom_complet', 'like', "%{$search}%")
                  ->orWhere('destination', 'like', "%{$search}%")
                  ->orWhere('observations', 'like', "%{$search}%");
            });
        }

        $archives = $query->orderByDesc('date_archivage')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        // Statistiques globales d'archivage
        $totalArchives = ArchiveAdministrative::where('etablissement_id', $etablissementId)->where('statut', 'archive')->count();
        $totalElevesArchives = ArchiveAdministrative::where('etablissement_id', $etablissementId)->where('statut', 'archive')->where('type_entite', 'eleve')->count();
        $totalPersonnelsArchives = ArchiveAdministrative::where('etablissement_id', $etablissementId)->where('statut', 'archive')->where('type_entite', 'personnel')->count();

        // Liste des élèves actifs éligibles à l'archivage
        $elevesActifs = Eleve::where('is_active', true)
            ->orderBy('nom')
            ->limit(100)
            ->get(['id', 'matricule', 'nom', 'prenom']);

        // Liste des personnels actifs éligibles à l'archivage
        $personnelsActifs = Personnel::where('etablissement_id', $etablissementId)
            ->where('statut', 'Actif')
            ->orderBy('nom')
            ->get(['id', 'matricule', 'nom', 'prenom', 'fonction_principale']);

        // Masques et souches de numérotation configurés
        $configurationsNumerotation = ConfigurationNumerotation::where('etablissement_id', $etablissementId)
            ->get();

        // Si des types manquent, on initialise les défauts
        $typesDocs = [
            'matricule_eleve',
            'matricule_personnel',
            'recu_scolarite',
            'recu_divers',
            'certificat_scolarite',
            'certificat_radiation',
        ];
        foreach ($typesDocs as $td) {
            if (! $configurationsNumerotation->firstWhere('type_document', $td)) {
                $def = ConfigurationNumerotation::getDefaultsForType($td, $etablissementId);
                if ($etablissementId > 0 && \App\Models\Etablissement::where('id', $etablissementId)->exists()) {
                    $configurationsNumerotation->push(
                        ConfigurationNumerotation::create([
                            'etablissement_id' => $etablissementId,
                            'type_document' => $td,
                            'prefixe' => $def['prefixe'],
                            'format_masque' => $def['format_masque'],
                            'compteur_actuel' => 1,
                            'annee_reference' => date('Y'),
                        ])
                    );
                } else {
                    $defConfig = new ConfigurationNumerotation([
                        'etablissement_id' => $etablissementId > 0 ? $etablissementId : null,
                        'type_document' => $td,
                        'prefixe' => $def['prefixe'],
                        'format_masque' => $def['format_masque'],
                        'compteur_actuel' => 1,
                        'annee_reference' => date('Y'),
                    ]);
                    $defConfig->id = 0;
                    $configurationsNumerotation->push($defConfig);
                }
            }
        }

        // Années scolaires pour l'assistant de reconduction
        $annees = AnneeScolaire::orderByDesc('date_debut')->get(['id', 'nom', 'code', 'statut', 'is_current']);

        return Inertia::render('administration/archivage/index', [
            'archives' => $archives,
            'stats' => [
                'total_archives' => $totalArchives,
                'total_eleves' => $totalElevesArchives,
                'total_personnels' => $totalPersonnelsArchives,
            ],
            'filters' => [
                'type_entite' => $typeEntite,
                'motif' => $motif,
                'statut' => $statut,
                'search' => $search,
            ],
            'elevesActifs' => $elevesActifs,
            'personnelsActifs' => $personnelsActifs,
            'configurationsNumerotation' => $configurationsNumerotation,
            'annees' => $annees,
            'can' => [
                'view' => $user?->hasRole('super-admin') || $user?->can('archivage.view') || $user?->can('etablissements.manage'),
                'archiver' => $user?->hasRole('super-admin') || $user?->can('archivage.archiver') || $user?->can('etablissements.manage'),
                'numerotation' => $user?->hasRole('super-admin') || $user?->can('archivage.numerotation') || $user?->can('etablissements.manage'),
                'dupliquer' => $user?->hasRole('super-admin') || $user?->can('archivage.dupliquer') || $user?->can('etablissements.manage'),
            ],
        ]);
    }

    /**
     * Archive un élève ou un membre du personnel.
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function store(Request $request): RedirectResponse
    {
        // Résolution de l'établissement actif (compatible Super-Admin transversal et personnel local)
        $context = $this->resolveEtablissementContext($request);
        $etablissementId = $context['etablissementId'];

        $validated = $request->validate([
            'type_entite' => ['required', 'in:eleve,personnel'],
            'eleve_id' => ['required_if:type_entite,eleve', 'nullable', 'exists:eleves,id'],
            'personnel_id' => ['required_if:type_entite,personnel', 'nullable', 'exists:personnels,id'],
            'motif' => [
                'required',
                'in:diplome,transfert_radiation,exclusion_definitive,abandon,demission,retraite,fin_contrat,licenciement,mutation,deces,autre',
            ],
            'date_archivage' => ['required', 'date'],
            'destination' => ['nullable', 'string', 'max:255'],
            'observations' => ['nullable', 'string'],
        ]);

        $anneeActive = AnneeScolaire::where('is_current', true)->first()
            ?? AnneeScolaire::orderByDesc('date_debut')->first();

        if ($validated['type_entite'] === 'eleve') {
            $eleve = Eleve::findOrFail($validated['eleve_id']);

            // Désactiver l'élève des listes actives
            $eleve->update(['is_active' => false]);

            // Mettre à jour l'inscription en cours
            $derniereInscription = Inscription::with('classe')
                ->where('eleve_id', $eleve->id)
                ->orderByDesc('id')
                ->first();

            if ($derniereInscription) {
                $nouveauStatut = match ($validated['motif']) {
                    'transfert_radiation' => 'transfere',
                    'exclusion_definitive' => 'exclu',
                    default => 'suspendue',
                };
                $derniereInscription->update([
                    'statut' => $nouveauStatut,
                    'motif_sortie' => $validated['motif'],
                ]);
            }

            ArchiveAdministrative::create([
                'etablissement_id' => $etablissementId,
                'annee_scolaire_id' => $anneeActive?->id,
                'type_entite' => 'eleve',
                'eleve_id' => $eleve->id,
                'matricule' => $eleve->matricule,
                'nom_complet' => trim("{$eleve->nom} {$eleve->prenom}"),
                'derniere_classe_ou_fonction' => $derniereInscription?->classe?->nom ?? 'N/A',
                'motif' => $validated['motif'],
                'date_archivage' => $validated['date_archivage'],
                'destination' => $validated['destination'] ?? null,
                'observations' => $validated['observations'] ?? null,
                'statut' => 'archive',
                'archive_par_id' => Auth::id(),
            ]);

            $message = "L'élève « {$eleve->nom} {$eleve->prenom} » a été transféré aux archives avec succès.";
        } else {
            $personnel = Personnel::findOrFail($validated['personnel_id']);

            // Mettre à jour le statut du personnel
            $nouveauStatut = match ($validated['motif']) {
                'retraite' => 'Retraité',
                'demission' => 'Démissionnaire',
                'licenciement' => 'Licencié',
                default => 'En congé',
            };
            $personnel->update(['statut' => $nouveauStatut]);

            ArchiveAdministrative::create([
                'etablissement_id' => $etablissementId,
                'annee_scolaire_id' => $anneeActive?->id,
                'type_entite' => 'personnel',
                'personnel_id' => $personnel->id,
                'matricule' => $personnel->matricule,
                'nom_complet' => trim("{$personnel->nom} {$personnel->prenom}"),
                'derniere_classe_ou_fonction' => $personnel->fonction_principale,
                'motif' => $validated['motif'],
                'date_archivage' => $validated['date_archivage'],
                'destination' => $validated['destination'] ?? null,
                'observations' => $validated['observations'] ?? null,
                'statut' => 'archive',
                'archive_par_id' => Auth::id(),
            ]);

            $message = "Le personnel « {$personnel->nom} {$personnel->prenom} » a été archivé avec succès.";
        }

        return back()->with('success', $message);
    }

    /**
     * Restaure un élève ou un membre du personnel depuis les archives.
     *
     * @param ArchiveAdministrative $archive
     * @return RedirectResponse
     */
    public function restaurer(ArchiveAdministrative $archive): RedirectResponse
    {
        if ($archive->statut === 'restaure') {
            return back()->with('error', 'Cet enregistrement a déjà été restauré.');
        }

        if ($archive->type_entite === 'eleve' && $archive->eleve_id) {
            $eleve = Eleve::find($archive->eleve_id);
            if ($eleve) {
                $eleve->update(['is_active' => true]);
            }
        } elseif ($archive->type_entite === 'personnel' && $archive->personnel_id) {
            $personnel = Personnel::find($archive->personnel_id);
            if ($personnel) {
                $personnel->update(['statut' => 'Actif']);
            }
        }

        $archive->update([
            'statut' => 'restaure',
            'date_restauration' => now(),
            'restaure_par_id' => Auth::id(),
        ]);

        return back()->with('success', "Le dossier de « {$archive->nom_complet} » a été réintégré avec succès.");
    }

    /**
     * Met à jour un masque ou préfixe de numérotation.
     *
     * @param Request $request
     * @param ConfigurationNumerotation $configuration
     * @return RedirectResponse
     */
    public function updateNumerotation(Request $request, ConfigurationNumerotation $configuration): RedirectResponse
    {
        $validated = $request->validate([
            'prefixe' => ['required', 'string', 'max:20'],
            'format_masque' => ['required', 'string', 'max:80'],
            'compteur_actuel' => ['required', 'integer', 'min:1'],
            'reinitialiser_annuellement' => ['required', 'boolean'],
        ]);

        $configuration->update($validated);

        return back()->with('success', "Le masque de numérotation pour « {$configuration->type_document} » a été mis à jour.");
    }

    /**
     * Exécute l'assistant de duplication / reconduction annuelle (Aurore §3.16).
     *
     * @param Request $request
     * @param ReconductionService $service
     * @return RedirectResponse
     */
    public function dupliquerAnnee(Request $request, ReconductionService $service): RedirectResponse
    {
        // Résolution de l'établissement actif
        $context = $this->resolveEtablissementContext($request);
        $etablissementId = $context['etablissementId'];

        $validated = $request->validate([
            'source_annee_id' => ['required', 'exists:annees_scolaires,id'],
            'cible_annee_id' => ['required', 'different:source_annee_id', 'exists:annees_scolaires,id'],
            'options' => ['required', 'array'],
        ]);

        $result = $service->dupliquerConfiguration(
            $validated['source_annee_id'],
            $validated['cible_annee_id'],
            $validated['options'],
            $etablissementId
        );

        $details = sprintf(
            '%d période(s), %d évaluation(s), %d tarif(s) de frais, %d modèle(s) d\'échéance, %d bourse(s), %d attribution(s)',
            $result['periodes'],
            $result['evaluations'],
            $result['frais_scolaires'],
            $result['modeles_echeances'],
            $result['bourses_remises'],
            $result['attributions']
        );

        return back()->with('success', "Configuration annuelle dupliquée avec succès ! ({$details})");
    }
}
