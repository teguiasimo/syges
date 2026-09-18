<?php

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\Etablissement;
use App\Models\Inscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Contrôleur gérant le Module 5 : Élèves & Inscriptions de SYGES.
 * Implémente la Base Élève Globale pérenne, la gestion des inscriptions annuelles,
 * les réinscriptions, les transferts intra/inter établissements (Gesco V1.1)
 * et l'importation de fichiers CSV / Excel (format SAGEES Schooling).
 */
class EleveController extends Controller implements HasMiddleware
{
    /**
     * Définition de la matrice des middlewares d'autorisation pour le module Élèves & Inscriptions.
     *
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:eleves.view', only: ['index', 'searchAnciens']),
            new Middleware('can:eleves.create', only: ['store', 'importCsv']),
            new Middleware('can:eleves.edit', only: ['update']),
            new Middleware('can:eleves.delete', only: ['destroy']),
            new Middleware('can:inscriptions.manage', only: ['reinscrire', 'updateStatut']),
            new Middleware('can:inscriptions.transfer', only: ['transferer']),
        ];
    }

    /**
     * Affiche la liste des élèves avec filtrage dynamique et KPIs.
     *
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        $currentAnnee = AnneeScolaire::current();
        $selectedAnneeId = $request->integer('annee_scolaire_id') ?: $currentAnnee?->id;

        // Récupération de tous les établissements avec leur arborescence complète pour les filtres
        $etablissements = Etablissement::with([
            'sections.cycles.niveaux.classes' => function ($q) {
                $q->where('is_active', true)->orderBy('nom');
            },
        ])->orderBy('nom')->get();

        // Récupération de toutes les années scolaires
        $anneesScolaires = AnneeScolaire::orderByDesc('date_debut')->get();

        // Construction de la requête pour les inscriptions de l'année sélectionnée
        $inscriptionsQuery = Inscription::with([
            'eleve',
            'etablissement',
            'classe.niveau.cycle.section.etablissement',
        ]);

        if ($selectedAnneeId) {
            $inscriptionsQuery->where('annee_scolaire_id', $selectedAnneeId);
        }

        // Filtre par Établissement (partitionnement direct et instantané)
        if ($etablissementId = $request->integer('etablissement_id')) {
            $inscriptionsQuery->where('etablissement_id', $etablissementId);
        }

        // Filtre par Cycle
        if ($cycleId = $request->integer('cycle_id')) {
            $inscriptionsQuery->whereHas('classe.niveau', function ($q) use ($cycleId) {
                $q->where('cycle_id', $cycleId);
            });
        }

        // Filtre par Niveau
        if ($niveauId = $request->integer('niveau_id')) {
            $inscriptionsQuery->whereHas('classe', function ($q) use ($niveauId) {
                $q->where('niveau_id', $niveauId);
            });
        }

        // Filtre par Classe
        if ($classeId = $request->integer('classe_id')) {
            $inscriptionsQuery->where('classe_id', $classeId);
        }

        // Filtre par Statut d'inscription (validee, en_attente, suspendue, exclu, transfere)
        if ($statut = $request->input('statut')) {
            $inscriptionsQuery->where('statut', $statut);
        }

        // Filtre par Régime scolaire (nouveau vs redoublant)
        if ($request->has('is_redoublant') && $request->input('is_redoublant') !== null && $request->input('is_redoublant') !== '') {
            $inscriptionsQuery->where('is_redoublant', $request->boolean('is_redoublant'));
        }

        // Filtre par Sexe de l'élève
        if ($sexe = $request->input('sexe')) {
            $inscriptionsQuery->whereHas('eleve', function ($q) use ($sexe) {
                $q->where('sexe', $sexe);
            });
        }

        // Recherche textuelle par Nom, Prénom, Matricule, Téléphone
        if ($search = $request->input('search')) {
            $inscriptionsQuery->whereHas('eleve', function ($q) use ($search) {
                $q->search($search);
            });
        }

        // Liste finale ordonnée
        $inscriptions = $inscriptionsQuery->orderBy('classe_id')
            ->orderBy(
                Eleve::select('nom')
                    ->whereColumn('eleves.id', 'inscriptions.eleve_id')
            )
            ->get();

        // Calcul des statistiques globales pour l'année scolaire sélectionnée
        $statsBase = Inscription::where('annee_scolaire_id', $selectedAnneeId);
        if ($etablissementId) {
            $statsBase->where('etablissement_id', $etablissementId);
        }

        $totalInscrits = (clone $statsBase)->where('statut', 'validee')->count();
        $totalFilles = (clone $statsBase)->where('statut', 'validee')->whereHas('eleve', fn ($q) => $q->where('sexe', 'F'))->count();
        $totalGarcons = (clone $statsBase)->where('statut', 'validee')->whereHas('eleve', fn ($q) => $q->where('sexe', 'M'))->count();
        $totalRedoublants = (clone $statsBase)->where('statut', 'validee')->where('is_redoublant', true)->count();
        $totalEnAttente = (clone $statsBase)->where('statut', 'en_attente')->count();

        // Anciens élèves non inscrits pour l'année scolaire en cours (pour réinscription directe)
        $nonInscritsCount = Eleve::whereDoesntHave('inscriptions', function ($q) use ($selectedAnneeId) {
            $q->where('annee_scolaire_id', $selectedAnneeId);
        })->count();

        // Droits et permissions de l'utilisateur connecté
        $user = $request->user();
        $isSuperAdmin = $user?->is_super_admin ?? false;
        $can = [
            'eleves_create' => $isSuperAdmin || $user?->can('eleves.create'),
            'eleves_edit' => $isSuperAdmin || $user?->can('eleves.edit'),
            'eleves_delete' => $isSuperAdmin || $user?->can('eleves.delete'),
            'inscriptions_manage' => $isSuperAdmin || $user?->can('inscriptions.manage'),
            'inscriptions_transfer' => $isSuperAdmin || $user?->can('inscriptions.transfer'),
        ];

        return Inertia::render('administration/eleves/index', [
            'inscriptions' => $inscriptions,
            'anneesScolaires' => $anneesScolaires,
            'selectedAnneeId' => (int) $selectedAnneeId,
            'etablissements' => $etablissements,
            'stats' => [
                'totalInscrits' => $totalInscrits,
                'totalFilles' => $totalFilles,
                'totalGarcons' => $totalGarcons,
                'totalRedoublants' => $totalRedoublants,
                'totalEnAttente' => $totalEnAttente,
                'nonInscritsCount' => $nonInscritsCount,
            ],
            'filters' => [
                'etablissement_id' => $request->input('etablissement_id', ''),
                'cycle_id' => $request->input('cycle_id', ''),
                'niveau_id' => $request->input('niveau_id', ''),
                'classe_id' => $request->input('classe_id', ''),
                'statut' => $request->input('statut', ''),
                'is_redoublant' => $request->input('is_redoublant', ''),
                'sexe' => $request->input('sexe', ''),
                'search' => $request->input('search', ''),
            ],
            'can' => $can,
        ]);
    }

    /**
     * Enregistre un nouvel élève dans la Base Élève Globale et l'inscrit dans sa classe.
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            // Identifiants & État Civil
            'matricule' => ['nullable', 'string', 'max:50', 'unique:eleves,matricule'],
            'matricule_national' => ['nullable', 'string', 'max:50'],
            'nom' => ['required', 'string', 'max:150'],
            'prenom' => ['nullable', 'string', 'max:150'],
            'sexe' => ['required', 'in:M,F'],
            'date_naissance' => ['nullable', 'date'],
            'lieu_naissance' => ['nullable', 'string', 'max:150'],
            'nationalite' => ['nullable', 'string', 'max:80'],
            'photo' => ['nullable', 'image', 'max:1024'], // 1 Mo max

            // Filiation & Tuteurs
            'nom_pere' => ['nullable', 'string', 'max:150'],
            'nom_mere' => ['nullable', 'string', 'max:150'],
            'nom_tuteur' => ['nullable', 'string', 'max:150'],
            'telephone_parent' => ['required', 'string', 'max:50'],
            'email_parent' => ['nullable', 'email', 'max:150'],
            'profession_parent' => ['nullable', 'string', 'max:150'],
            'adresse' => ['nullable', 'string', 'max:255'],
            'ville' => ['nullable', 'string', 'max:100'],

            // Santé & Urgence
            'groupe_sanguin' => ['nullable', 'string', 'max:10'],
            'contact_urgence_nom' => ['nullable', 'string', 'max:150'],
            'contact_urgence_telephone' => ['nullable', 'string', 'max:50'],
            'allergies_conditions' => ['nullable', 'string'],

            // Affectation Scolaire Initiale
            'classe_id' => ['required', 'exists:classes,id'],
            'annee_scolaire_id' => ['required', 'exists:annees_scolaires,id'],
            'statut' => ['required', 'in:validee,en_attente'],
            'is_redoublant' => ['required', 'boolean'],
            'etablissement_provenance' => ['nullable', 'string', 'max:150'],
            'observations' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($validated, $request) {
            // Génération automatique du matricule interne unique s'il n'est pas fourni
            $matricule = ! empty($validated['matricule'])
                ? trim($validated['matricule'])
                : Eleve::generateNextMatricule();

            // Gestion de l'upload de photo
            $photoUrl = null;
            if ($request->hasFile('photo')) {
                $path = $request->file('photo')->store('photos_eleves', 'public');
                $photoUrl = Storage::url($path);
            }

            // Création de l'Élève dans la Base Globale
            $eleve = Eleve::create([
                'matricule' => $matricule,
                'matricule_national' => $validated['matricule_national'] ?? null,
                'nom' => strtoupper(trim($validated['nom'])),
                'prenom' => $validated['prenom'] ? trim($validated['prenom']) : null,
                'sexe' => $validated['sexe'],
                'date_naissance' => $validated['date_naissance'] ?? null,
                'lieu_naissance' => $validated['lieu_naissance'] ?? null,
                'nationalite' => $validated['nationalite'] ?: 'CAMEROUNAISE',
                'photo_url' => $photoUrl,
                'nom_pere' => $validated['nom_pere'] ?? null,
                'nom_mere' => $validated['nom_mere'] ?? null,
                'nom_tuteur' => $validated['nom_tuteur'] ?? null,
                'telephone_parent' => trim($validated['telephone_parent']),
                'email_parent' => $validated['email_parent'] ?? null,
                'profession_parent' => $validated['profession_parent'] ?? null,
                'adresse' => $validated['adresse'] ?? null,
                'ville' => $validated['ville'] ?? null,
                'groupe_sanguin' => $validated['groupe_sanguin'] ?? null,
                'contact_urgence_nom' => $validated['contact_urgence_nom'] ?? null,
                'contact_urgence_telephone' => $validated['contact_urgence_telephone'] ?? null,
                'allergies_conditions' => $validated['allergies_conditions'] ?? null,
                'is_active' => true,
            ]);

            // Résolution de l'établissement rattaché à la classe
            $classe = Classe::find($validated['classe_id']);
            $etablissementId = $classe?->etablissement_id;

            // Création de l'inscription scolaire avec son identifiant d'établissement
            Inscription::create([
                'eleve_id' => $eleve->id,
                'classe_id' => $validated['classe_id'],
                'etablissement_id' => $etablissementId,
                'annee_scolaire_id' => $validated['annee_scolaire_id'],
                'type_inscription' => 'nouveau',
                'is_redoublant' => $validated['is_redoublant'],
                'statut' => $validated['statut'],
                'date_inscription' => now()->toDateString(),
                'etablissement_provenance' => $validated['etablissement_provenance'] ?? null,
                'observations' => $validated['observations'] ?? null,
            ]);
        });

        return back()->with('success', 'Élève enregistré et inscrit avec succès !');
    }

    /**
     * Met à jour les informations d'un élève dans la Base Globale.
     *
     * @param Request $request
     * @param Eleve $eleve
     * @return RedirectResponse
     */
    public function update(Request $request, Eleve $eleve): RedirectResponse
    {
        $validated = $request->validate([
            'matricule' => ['required', 'string', 'max:50', 'unique:eleves,matricule,' . $eleve->id],
            'matricule_national' => ['nullable', 'string', 'max:50'],
            'nom' => ['required', 'string', 'max:150'],
            'prenom' => ['nullable', 'string', 'max:150'],
            'sexe' => ['required', 'in:M,F'],
            'date_naissance' => ['nullable', 'date'],
            'lieu_naissance' => ['nullable', 'string', 'max:150'],
            'nationalite' => ['nullable', 'string', 'max:80'],
            'photo' => ['nullable', 'image', 'max:1024'],
            'nom_pere' => ['nullable', 'string', 'max:150'],
            'nom_mere' => ['nullable', 'string', 'max:150'],
            'nom_tuteur' => ['nullable', 'string', 'max:150'],
            'telephone_parent' => ['required', 'string', 'max:50'],
            'email_parent' => ['nullable', 'email', 'max:150'],
            'profession_parent' => ['nullable', 'string', 'max:150'],
            'adresse' => ['nullable', 'string', 'max:255'],
            'ville' => ['nullable', 'string', 'max:100'],
            'groupe_sanguin' => ['nullable', 'string', 'max:10'],
            'contact_urgence_nom' => ['nullable', 'string', 'max:150'],
            'contact_urgence_telephone' => ['nullable', 'string', 'max:50'],
            'allergies_conditions' => ['nullable', 'string'],
        ]);

        if ($request->hasFile('photo')) {
            $path = $request->file('photo')->store('photos_eleves', 'public');
            $validated['photo_url'] = Storage::url($path);
        }

        $validated['nom'] = strtoupper(trim($validated['nom']));
        $validated['prenom'] = $validated['prenom'] ? trim($validated['prenom']) : null;

        $eleve->update($validated);

        return back()->with('success', 'Fiche de l\'élève mise à jour avec succès !');
    }

    /**
     * Supprime un élève et ses inscriptions (ou le désactive).
     *
     * @param Eleve $eleve
     * @return RedirectResponse
     */
    public function destroy(Eleve $eleve): RedirectResponse
    {
        $nom = $eleve->nom_complet;
        $eleve->delete();

        return back()->with('success', "L'élève {$nom} a été supprimé avec succès.");
    }

    /**
     * Réinscrit un ancien élève existant dans une nouvelle classe pour une année scolaire donnée.
     *
     * Initialise automatiquement son compte financier scolaire en intégrant optionnellement
     * les arriérés ou dettes antérieures reportées manuellement par le comptable ou la secrétaire.
     *
     * @param Request $request Requête HTTP contenant les paramètres d'affectation et les arriérés.
     * @return RedirectResponse Redirection avec message de succès ou d'erreur.
     */
    public function reinscrire(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'eleve_id' => ['required', 'exists:eleves,id'],
            'classe_id' => ['required', 'exists:classes,id'],
            'annee_scolaire_id' => ['required', 'exists:annees_scolaires,id'],
            'is_redoublant' => ['required', 'boolean'],
            'statut' => ['required', 'in:validee,en_attente'],
            'arrieres_anterieurs' => ['nullable', 'numeric', 'min:0'],
            'observations' => ['nullable', 'string'],
        ]);

        // Vérifie si l'élève n'a pas déjà une inscription pour cette année
        $existing = Inscription::where('eleve_id', $validated['eleve_id'])
            ->where('annee_scolaire_id', $validated['annee_scolaire_id'])
            ->whereIn('statut', ['validee', 'en_attente'])
            ->first();

        if ($existing) {
            return back()->with('error', 'Cet élève dispose déjà d\'une inscription active pour cette année scolaire.');
        }

        $classe = Classe::find($validated['classe_id']);
        $etablissementId = $classe?->etablissement_id;

        $inscription = Inscription::create([
            'eleve_id' => $validated['eleve_id'],
            'classe_id' => $validated['classe_id'],
            'etablissement_id' => $etablissementId,
            'annee_scolaire_id' => $validated['annee_scolaire_id'],
            'type_inscription' => 'reinscription',
            'is_redoublant' => $validated['is_redoublant'],
            'statut' => $validated['statut'],
            'date_inscription' => now()->toDateString(),
            'observations' => $validated['observations'] ?? null,
        ]);

        // Initialisation automatique du compte financier avec report des arriérés antérieurs
        $arrieres = (float) ($validated['arrieres_anterieurs'] ?? 0);
        app(\App\Services\FinanceService::class)->initialiserCompteEleve($inscription, 0, $arrieres);

        return back()->with('success', 'Réinscription effectuée avec succès !');
    }

    /**
     * Met à jour le statut d'une inscription (validee, suspendue, exclu, en_attente).
     *
     * @param Request $request
     * @param Inscription $inscription
     * @return RedirectResponse
     */
    public function updateStatut(Request $request, Inscription $inscription): RedirectResponse
    {
        $validated = $request->validate([
            'statut' => ['required', 'in:validee,en_attente,suspendue,exclu,transfere'],
            'motif_sortie' => ['nullable', 'string', 'max:255'],
        ]);

        $inscription->update($validated);

        return back()->with('success', 'Statut de l\'inscription actualisé avec succès !');
    }

    /**
     * Transfère un groupe d'élèves d'une classe source vers une classe cible (inter-classes ou intra-groupe).
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function transferer(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'inscription_ids' => ['required', 'array', 'min:1'],
            'inscription_ids.*' => ['required', 'exists:inscriptions,id'],
            'target_classe_id' => ['required', 'exists:classes,id'],
            'motif_transfert' => ['nullable', 'string', 'max:255'],
        ]);

        $targetClasse = Classe::with('niveau.cycle.section.etablissement')->findOrFail($validated['target_classe_id']);
        $targetEtablissementId = $targetClasse->etablissement_id ?? $targetClasse->niveau?->cycle?->section?->etablissement_id;
        $motif = $validated['motif_transfert'] ?: 'Transfert pédagogique/administratif';
        $transferredCount = 0;

        DB::transaction(function () use ($validated, $targetClasse, $targetEtablissementId, $motif, &$transferredCount) {
            $inscriptions = Inscription::with('classe.niveau.cycle.section.etablissement')
                ->whereIn('id', $validated['inscription_ids'])
                ->get();

            foreach ($inscriptions as $oldInscription) {
                // Même classe : aucune action requise
                if ($oldInscription->classe_id == $targetClasse->id) {
                    continue;
                }

                $sameEtablissement = ($oldInscription->etablissement_id === $targetEtablissementId);

                if ($sameEtablissement) {
                    // Transfert interne au même établissement : changement direct de classe avec conservation de la scolarité
                    $oldInscription->update([
                        'classe_id' => $targetClasse->id,
                        'etablissement_id' => $targetEtablissementId,
                        'observations' => trim(($oldInscription->observations ?? '') . "\nTransféré depuis {$oldInscription->classe?->nom} le " . now()->format('d/m/Y')),
                    ]);
                } else {
                    // Cadrage V1.1 : Transfert intra-groupe vers un autre établissement
                    // 1. Clôture de l'inscription active d'origine
                    $oldInscription->update([
                        'statut' => 'transfere',
                        'motif_sortie' => "Transféré vers {$targetClasse->nom} : {$motif}",
                    ]);

                    // 2. Création de la nouvelle inscription dans l'établissement cible
                    Inscription::create([
                        'eleve_id' => $oldInscription->eleve_id,
                        'classe_id' => $targetClasse->id,
                        'etablissement_id' => $targetEtablissementId,
                        'annee_scolaire_id' => $oldInscription->annee_scolaire_id,
                        'type_inscription' => 'nouveau',
                        'is_redoublant' => $oldInscription->is_redoublant,
                        'statut' => 'validee',
                        'date_inscription' => now()->toDateString(),
                        'etablissement_provenance' => $oldInscription->etablissement?->nom ?? $oldInscription->classe?->niveau?->cycle?->section?->etablissement?->nom ?? null,
                        'observations' => "Transfert intra-groupe : {$motif}",
                    ]);
                }

                $transferredCount++;
            }
        });

        return back()->with('success', "{$transferredCount} élève(s) transféré(s) avec succès vers la classe {$targetClasse->nom} !");
    }

    /**
     * Importe une liste d'élèves à partir d'un fichier CSV / TSV (conforme à ELEVE.csv et SAGEES).
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function importCsv(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'fichier' => ['required', 'file', 'mimes:csv,txt', 'max:5120'], // 5 Mo max
            'classe_id' => ['required', 'exists:classes,id'],
            'annee_scolaire_id' => ['required', 'exists:annees_scolaires,id'],
            'ignorer_doublons' => ['nullable', 'boolean'],
        ]);

        $file = $request->file('fichier');
        $content = $file->getContent();
        $lines = explode("\n", str_replace("\r", "", $content));

        $imported = 0;
        $ignored = 0;
        $ignorerDoublons = $request->boolean('ignorer_doublons', true);

        DB::transaction(function () use ($lines, $validated, $ignorerDoublons, &$imported, &$ignored) {
            foreach ($lines as $line) {
                // Nettoyage uniquement des retours chariot pour préserver les colonnes vides initiales (ex: TSV sans matricule)
                $line = rtrim($line, "\r\n");
                if (trim($line) === '') {
                    continue;
                }

                // Détecte le séparateur : tabulation ou point-virgule ou virgule
                $cols = str_contains($line, "\t")
                    ? explode("\t", $line)
                    : (str_contains($line, ';') ? explode(';', $line) : explode(',', $line));

                // Nettoyage des colonnes
                $cols = array_map(fn ($c) => trim(str_replace(['"', "'"], '', $c)), $cols);

                // Si c'est une ligne d'en-tête, on l'ignore
                if (isset($cols[0]) && in_array(strtolower($cols[0]), ['matricule', 'nom', 'code'])) {
                    continue;
                }

                // Correspondance des colonnes (format ELEVE.csv / SAGEES) :
                // Col 0: Matricule (optionnel ou string)
                // Col 1: Matricule national
                // Col 2: Nom
                // Col 3: Prénom
                // Col 4: Sexe
                // Col 5: Date naissance
                // Col 6: Lieu naissance
                // Col 7: Redoublant (O / N)
                // Col 8: Téléphone parent
                // Col 9: Adresse
                // Col 10: Nom parent / tuteur
                // Col 11: Nationalité

                $matricule = ! empty($cols[0]) && $cols[0] !== '\N' ? $cols[0] : null;
                $matriculeNat = ! empty($cols[1]) && $cols[1] !== '\N' ? $cols[1] : null;
                $nom = ! empty($cols[2]) ? $cols[2] : (! empty($cols[1]) ? $cols[1] : null);
                $prenom = ! empty($cols[3]) && $cols[3] !== '\N' ? $cols[3] : null;
                $sexe = ! empty($cols[4]) && in_array(strtoupper($cols[4]), ['M', 'F']) ? strtoupper($cols[4]) : 'M';
                $dateNaissRaw = ! empty($cols[5]) && $cols[5] !== '\N' ? $cols[5] : null;
                $lieuNaiss = ! empty($cols[6]) && $cols[6] !== '\N' ? $cols[6] : null;
                $isRedoublant = ! empty($cols[7]) && in_array(strtoupper($cols[7]), ['O', 'OUI', '1', 'TRUE']);
                $telParent = ! empty($cols[8]) && $cols[8] !== '\N' ? $cols[8] : '+237 600 00 00 00';
                $adresse = ! empty($cols[9]) && $cols[9] !== '\N' ? $cols[9] : null;
                $nomParent = ! empty($cols[10]) && $cols[10] !== '\N' ? $cols[10] : null;
                $nationalite = ! empty($cols[11]) && $cols[11] !== '\N' ? $cols[11] : 'CAMEROUNAISE';

                if (empty($nom)) {
                    $ignored++;
                    continue;
                }

                // Formatage de la date de naissance
                $dateNaissance = null;
                if ($dateNaissRaw) {
                    try {
                        if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $dateNaissRaw, $m)) {
                            $dateNaissance = sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
                        } else {
                            $dateNaissance = Carbon::parse($dateNaissRaw)->format('Y-m-d');
                        }
                    } catch (\Exception) {
                        $dateNaissance = null;
                    }
                }

                // Vérification des doublons par matricule ou (nom + prénom)
                if ($matricule && Eleve::where('matricule', $matricule)->exists()) {
                    if ($ignorerDoublons) {
                        $ignored++;
                        continue;
                    }
                    $matricule = Eleve::generateNextMatricule();
                }

                if (! $matricule) {
                    $matricule = Eleve::generateNextMatricule();
                }

                // Création de l'Élève
                $eleve = Eleve::create([
                    'matricule' => $matricule,
                    'matricule_national' => $matriculeNat,
                    'nom' => strtoupper($nom),
                    'prenom' => $prenom,
                    'sexe' => $sexe,
                    'date_naissance' => $dateNaissance,
                    'lieu_naissance' => $lieuNaiss,
                    'nationalite' => $nationalite,
                    'telephone_parent' => $telParent,
                    'nom_tuteur' => $nomParent,
                    'adresse' => $adresse,
                    'is_active' => true,
                ]);

                $targetClasse = Classe::find($validated['classe_id']);
                $etablissementId = $targetClasse?->etablissement_id;

                // Création de l'inscription avec son établissement
                Inscription::create([
                    'eleve_id' => $eleve->id,
                    'classe_id' => $validated['classe_id'],
                    'etablissement_id' => $etablissementId,
                    'annee_scolaire_id' => $validated['annee_scolaire_id'],
                    'type_inscription' => 'nouveau',
                    'is_redoublant' => $isRedoublant,
                    'statut' => 'validee',
                    'date_inscription' => now()->toDateString(),
                ]);

                $imported++;
            }
        });

        return back()->with('success', "Importation terminée : {$imported} élève(s) importé(s) et inscrit(s), {$ignored} ligne(s) ignorée(s).");
    }

    /**
     * Recherche d'anciens élèves non encore inscrits pour une année scolaire donnée.
     * Utilisé pour la réinscription rapide via autocomplétion avec affichage ergonomique
     * de la dernière classe fréquentée, de la décision du conseil et du reliquat financier antérieur.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function searchAnciens(Request $request): \Illuminate\Http\JsonResponse
    {
        $q = trim((string) $request->input('q', ''));
        $anneeId = $request->integer('annee_id');

        if (empty($q)) {
            return response()->json([]);
        }

        $eleves = Eleve::search($q)
            ->whereDoesntHave('inscriptions', function ($sub) use ($anneeId) {
                if ($anneeId) {
                    $sub->where('annee_scolaire_id', $anneeId)
                        ->whereIn('statut', ['validee', 'en_attente']);
                }
            })
            ->with([
                'inscriptions' => function ($iq) use ($anneeId) {
                    if ($anneeId) {
                        $iq->where('annee_scolaire_id', '!=', $anneeId);
                    }
                    $iq->with([
                        'classe.niveau.cycle.section.etablissement',
                        'anneeScolaire',
                        'compteFinancier',
                        'deliberations' => fn ($dq) => $dq->where('type_deliberation', 'annuelle'),
                    ])->latest('date_inscription');
                },
            ])
            ->take(15)
            ->get();

        $payload = $eleves->map(function ($eleve) {
            $derniereInscription = $eleve->inscriptions->first();
            $delib = $derniereInscription?->deliberations?->first();

            $statutRetenu = $delib?->statut_retenu ?? null;
            $isRedoublant = ($statutRetenu === 'redouble');
            $soldeRestantPrecedent = (float) ($derniereInscription?->compteFinancier?->solde_restant ?? 0);

            return [
                'id' => $eleve->id,
                'matricule' => $eleve->matricule,
                'matricule_national' => $eleve->matricule_national,
                'nom' => $eleve->nom,
                'prenom' => $eleve->prenom,
                'nom_complet' => $eleve->nom_complet,
                'sexe' => $eleve->sexe,
                'date_naissance' => $eleve->date_naissance?->format('Y-m-d'),
                'telephone_parent' => $eleve->telephone_parent,
                'derniere_classe' => $derniereInscription ? [
                    'classe_id' => $derniereInscription->classe_id,
                    'classe_nom' => $derniereInscription->classe?->nom ?? 'N/A',
                    'niveau_nom' => $derniereInscription->classe?->niveau?->nom ?? 'N/A',
                    'annee_nom' => $derniereInscription->anneeScolaire?->nom ?? $derniereInscription->anneeScolaire?->code ?? 'N/A',
                    'decision_conseil' => $delib?->decision_conseil ?? ($statutRetenu === 'promu' ? 'Promu en classe supérieure' : ($statutRetenu === 'redouble' ? 'Autorisé à redoubler' : null)),
                    'statut_retenu' => $statutRetenu,
                    'is_redoublant_preconise' => $isRedoublant,
                    'solde_impaye_precedent' => $soldeRestantPrecedent,
                ] : null,
            ];
        });

        return response()->json($payload);
    }
}
