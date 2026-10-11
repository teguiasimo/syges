<?php

namespace App\Http\Controllers\Administration;

use App\Concerns\ScopesEtablissement;
use App\Http\Controllers\Controller;
use App\Models\AnneeScolaire;
use App\Models\BourseRemise;
use App\Models\Caisse;
use App\Models\Classe;
use App\Models\ClotureCaisse;
use App\Models\CompteFinancierEleve;
use App\Models\EcheanceTranche;
use App\Models\EleveBourseRemise;
use App\Models\Etablissement;
use App\Models\FraisScolaire;
use App\Models\ModeleEcheance;
use App\Models\Niveau;
use App\Models\PenaliteConfiguration;
use App\Models\PenaliteEleve;
use App\Models\Personnel;
use App\Models\ReglementDivers;
use App\Models\TypeFrais;
use App\Models\VersementCaisse;
use App\Services\FinanceService;
use App\Services\QrCodeService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use InvalidArgumentException;

/**
 * Contrôleur principal pour le Module 11 : Gestion de la Caisse & Paiements (Gesco V1.1 & Aurore).
 *
 * Coordonne les opérations financières de l'établissement :
 * - Guichet d'encaissement et émission des reçus officiels infalsifiables.
 * - Suivi des comptes courants scolaires des élèves et ardoises financières.
 * - Paramétrage de la nomenclature des frais et de la matrice tarifaire.
 * - Journal quotidien de caisse et clôture journalière des comptes.
 * - Activation/désactivation du verrou de solvabilité sur les bulletins.
 * - Caisses multiples et encaissements hors scolarité (Aurore §3.20, §4.2).
 * - Bourses d'études et remises automatiques (Aurore §3.19, §3.21, §3.25).
 * - Échéanciers calendaires et pénalités de retard (Aurore §3.17, §3.24).
 */
class FinanceController extends Controller implements HasMiddleware
{
    use ScopesEtablissement;
    /**
     * Définition de la matrice des middlewares d'autorisation pour le module Finances & Caisse.
     *
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:finances.view', only: ['index', 'imprimerRecu', 'imprimerRecuDivers']),
            new Middleware('can:versements.create|finances.manage', only: ['storeVersement', 'storeReglementDivers']),
            new Middleware('can:versements.cancel|finances.annuler|finances.manage', only: ['annulerVersement', 'annulerReglementDivers']),
            new Middleware('can:caisses.cloture|finances.cloture|finances.manage', only: ['cloturerJournee']),
            new Middleware('can:caisses.create|caisses.edit|finances.manage', only: ['storeCaisse', 'updateCaisse', 'destroyCaisse']),
            new Middleware('can:bourses.manage|finances.manage', only: ['storeBourseRemise', 'updateBourseRemise', 'destroyBourseRemise', 'attribuerBourseRemise', 'retirerBourseRemise']),
            new Middleware('can:echeances.manage|finances.manage', only: ['storeModeleEcheance', 'destroyModeleEcheance']),
            new Middleware('can:penalites.manage|finances.manage', only: ['storePenaliteConfiguration', 'appliquerPenalitesRetard', 'annulerPenaliteEleve']),
            new Middleware('can:finances.manage', only: [
                'ajusterCompte',
                'storeTypeFrais',
                'destroyTypeFrais',
                'storeTarif',
                'destroyTarif',
                'toggleVerrouSolvabilite',
            ]),
        ];
    }

    /**
     * Affiche l'espace de gestion financière et de caisse.
     *
     * @param Request $request
     * @return InertiaResponse
     */
    public function index(Request $request): InertiaResponse
    {
        abort_if(! $request->user()?->can('finances.view') && ! $request->user()?->hasRole('super-admin'), 403);

        $currentAnnee = AnneeScolaire::where('is_current', true)->first()
            ?? AnneeScolaire::latest()->first();
        $anneeId = $currentAnnee?->id;

        $context = $this->resolveEtablissementContext($request, ['institution']);
        $etablissements = $context['etablissements'];
        $currentEtablissement = $context['selectedEtablissement'];
        $etablissementId = $currentEtablissement?->id ?? 0;

        // 1. Liste des versements avec pagination et filtres
        $versementsQuery = VersementCaisse::with([
            'compteFinancier.eleve',
            'inscription.classe',
            'ventilations',
            'caissier',
            'caisse',
        ])
            ->where('etablissement_id', $etablissementId)
            ->latest('date_versement');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $versementsQuery->where(function ($q) use ($search) {
                $q->where('reference_recu', 'LIKE', "%{$search}%")
                    ->orWhere('payeur_nom', 'LIKE', "%{$search}%")
                    ->orWhereHas('compteFinancier.eleve', function ($eq) use ($search) {
                        $eq->where('nom', 'LIKE', "%{$search}%")
                            ->orWhere('prenom', 'LIKE', "%{$search}%")
                            ->orWhere('matricule', 'LIKE', "%{$search}%");
                    });
            });
        }

        if ($request->filled('mode_paiement')) {
            $versementsQuery->where('mode_paiement', $request->input('mode_paiement'));
        }

        if ($request->filled('statut')) {
            $versementsQuery->where('statut', $request->input('statut'));
        }

        $versements = $versementsQuery->take(100)->get();

        // 2. Liste des comptes financiers des élèves
        $comptesQuery = CompteFinancierEleve::with([
            'eleve',
            'inscription.classe',
            'boursesRemises.bourseRemise',
            'penalites',
        ])
            ->where('etablissement_id', $etablissementId)
            ->where('annee_scolaire_id', $anneeId);

        if ($request->filled('classe_id')) {
            $classeId = $request->integer('classe_id');
            $comptesQuery->whereHas('inscription', fn ($q) => $q->where('classe_id', $classeId));
        }

        if ($request->filled('statut_financier')) {
            $comptesQuery->where('statut_financier', $request->input('statut_financier'));
        }

        if ($request->filled('search_compte')) {
            $sc = $request->input('search_compte');
            $comptesQuery->whereHas('eleve', function ($q) use ($sc) {
                $q->where('nom', 'LIKE', "%{$sc}%")
                    ->orWhere('prenom', 'LIKE', "%{$sc}%")
                    ->orWhere('matricule', 'LIKE', "%{$sc}%");
            });
        }

        $comptes = $comptesQuery->get();

        // 3. Types de frais configurés pour l'établissement
        $typesFrais = TypeFrais::where('etablissement_id', $etablissementId)
            ->orderBy('obligatoire', 'desc')
            ->orderBy('libelle')
            ->get();

        // 4. Matrice tarifaire
        $tarifs = FraisScolaire::with(['niveau', 'typeFrais'])
            ->where('etablissement_id', $etablissementId)
            ->where('annee_scolaire_id', $anneeId)
            ->orderBy('niveau_id')
            ->orderBy('tranche_numero')
            ->get();

        // 5. Caisses de l'établissement (Aurore §3.20)
        $caisses = Caisse::with('responsable')
            ->where('etablissement_id', $etablissementId)
            ->orderByDesc('is_principale')
            ->orderBy('nom')
            ->get();

        // 6. Règlements divers hors scolarité (Aurore §4.2)
        $reglementsDivers = ReglementDivers::with(['caisse', 'caissier'])
            ->where('etablissement_id', $etablissementId)
            ->latest('date_reglement')
            ->take(100)
            ->get();

        // 7. Référentiel des bourses et remises (Aurore §3.19, §3.21, §3.25)
        $boursesRemises = BourseRemise::with(['niveau', 'typeFrais'])
            ->where('etablissement_id', $etablissementId)
            ->orderBy('type')
            ->orderBy('nom')
            ->get();

        // 8. Modèles d'échéances calendaires (Aurore §3.24)
        $modelesEcheances = ModeleEcheance::with('tranches')
            ->where('etablissement_id', $etablissementId)
            ->where('annee_scolaire_id', $anneeId)
            ->orderByDesc('is_default')
            ->get();

        // 9. Configurations des pénalités de retard (Aurore §3.17)
        $penalitesConfigurations = PenaliteConfiguration::where('etablissement_id', $etablissementId)->get();

        // 10. Journal du jour et état de la caisse
        $today = Carbon::today()->toDateString();
        $versementsDuJour = VersementCaisse::where('etablissement_id', $etablissementId)
            ->whereDate('date_versement', $today)
            ->where('statut', 'valide')
            ->get();

        $reglementsDiversDuJour = ReglementDivers::where('etablissement_id', $etablissementId)
            ->whereDate('date_reglement', $today)
            ->where('statut', 'valide')
            ->get();

        $totalGeneralDuJour = (float) $versementsDuJour->sum('montant_total') + (float) $reglementsDiversDuJour->sum('montant');

        $journalDuJour = [
            'date' => $today,
            'total_especes' => (float) $versementsDuJour->where('mode_paiement', 'especes')->sum('montant_total')
                + (float) $reglementsDiversDuJour->where('mode_paiement', 'especes')->sum('montant'),
            'total_cheques' => (float) $versementsDuJour->where('mode_paiement', 'cheque')->sum('montant_total')
                + (float) $reglementsDiversDuJour->where('mode_paiement', 'cheque')->sum('montant'),
            'total_virements' => (float) $versementsDuJour->where('mode_paiement', 'virement')->sum('montant_total')
                + (float) $reglementsDiversDuJour->where('mode_paiement', 'virement')->sum('montant'),
            'total_mobile_money' => (float) $versementsDuJour->where('mode_paiement', 'mobile_money_manuel')->sum('montant_total')
                + (float) $reglementsDiversDuJour->where('mode_paiement', 'mobile_money_manuel')->sum('montant'),
            'total_general' => $totalGeneralDuJour,
            'nombre_transactions' => $versementsDuJour->count() + $reglementsDiversDuJour->count(),
            'est_cloture' => ClotureCaisse::where('etablissement_id', $etablissementId)
                ->where('date_cloture', $today)
                ->where('statut', 'cloturee')
                ->exists(),
        ];

        // 11. Historique des clôtures passées
        $historiqueClotures = ClotureCaisse::with(['caissier', 'verificateur'])
            ->where('etablissement_id', $etablissementId)
            ->latest('date_cloture')
            ->take(30)
            ->get();

        // 12. Données d'appui pour les formulaires
        $classes = Classe::where('etablissement_id', $etablissementId)
            ->where('is_active', true)
            ->orderBy('nom')
            ->get();

        $niveaux = Niveau::where('etablissement_id', $etablissementId)
            ->orderBy('ordre')
            ->get();

        $caissiers = Personnel::where('etablissement_id', $etablissementId)
            ->actif()
            ->orderBy('nom')
            ->get();

        return Inertia::render('administration/finances/index', [
            'versements' => $versements,
            'comptes' => $comptes,
            'typesFrais' => $typesFrais,
            'tarifs' => $tarifs,
            'caisses' => $caisses,
            'reglementsDivers' => $reglementsDivers,
            'boursesRemises' => $boursesRemises,
            'modelesEcheances' => $modelesEcheances,
            'penalitesConfigurations' => $penalitesConfigurations,
            'journalDuJour' => $journalDuJour,
            'historiqueClotures' => $historiqueClotures,
            'classes' => $classes,
            'niveaux' => $niveaux,
            'caissiers' => $caissiers,
            'etablissements' => $etablissements,
            'currentEtablissement' => $currentEtablissement,
            'anneesScolaires' => AnneeScolaire::orderBy('code', 'desc')->get(),
            'currentAnnee' => $currentAnnee,
            'filters' => $request->only(['search', 'mode_paiement', 'statut', 'classe_id', 'statut_financier', 'tab']),
            'can' => [
                'view' => $request->user()?->hasRole('super-admin') || $request->user()?->can('finances.view'),
                'manage' => $request->user()?->hasRole('super-admin') || $request->user()?->can('finances.manage'),
                'versements_create' => $request->user()?->hasRole('super-admin') || $request->user()?->can('versements.create') || $request->user()?->can('finances.manage'),
                'versements_cancel' => $request->user()?->hasRole('super-admin') || $request->user()?->can('versements.cancel') || $request->user()?->can('finances.annuler') || $request->user()?->can('finances.manage'),
                'caisses_manage' => $request->user()?->hasRole('super-admin') || $request->user()?->can('caisses.create') || $request->user()?->can('caisses.edit') || $request->user()?->can('finances.manage'),
                'caisses_cloture' => $request->user()?->hasRole('super-admin') || $request->user()?->can('caisses.cloture') || $request->user()?->can('finances.cloture') || $request->user()?->can('finances.manage'),
                'bourses_manage' => $request->user()?->hasRole('super-admin') || $request->user()?->can('bourses.manage') || $request->user()?->can('finances.manage'),
                'echeances_manage' => $request->user()?->hasRole('super-admin') || $request->user()?->can('echeances.manage') || $request->user()?->can('finances.manage'),
                'penalites_manage' => $request->user()?->hasRole('super-admin') || $request->user()?->can('penalites.manage') || $request->user()?->can('finances.manage'),
            ],
        ]);
    }

    /**
     * Enregistre un versement physique effectué à la caisse.
     *
     * @param Request $request
     * @param FinanceService $financeService
     * @return RedirectResponse
     */
    public function storeVersement(Request $request, FinanceService $financeService): RedirectResponse
    {
        abort_if(! $request->user()?->can('versements.create') && ! $request->user()?->can('finances.manage') && ! $request->user()?->hasRole('super-admin'), 403);

        $validated = $request->validate([
            'compte_financier_id' => 'required|exists:comptes_financiers_eleves,id',
            'montant_total' => 'required|numeric|min:1',
            'mode_paiement' => 'required|in:especes,cheque,virement,mobile_money_manuel',
            'reference_paiement' => 'nullable|string|max:100',
            'payeur_nom' => 'nullable|string|max:150',
            'payeur_telephone' => 'nullable|string|max:50',
            'personnel_id' => 'nullable|exists:personnels,id',
            'observations' => 'nullable|string|max:500',
            'ventilations' => 'required|array|min:1',
            'ventilations.*.libelle' => 'required|string|max:150',
            'ventilations.*.montant' => 'required|numeric|min:1',
            'ventilations.*.type_frais_id' => 'nullable|exists:types_frais,id',
            'ventilations.*.frais_scolaire_id' => 'nullable|exists:frais_scolaires,id',
        ]);

        $caissier = null;
        if (! empty($validated['personnel_id'])) {
            $caissier = Personnel::find($validated['personnel_id']);
        }

        try {
            $versement = $financeService->enregistrerVersement($validated, $caissier, $request->user());

            return back()->with([
                'success' => "Versement de " . number_format($versement->montant_total, 0) . " FCFA enregistré avec succès. Reçu N° {$versement->reference_recu}.",
                'dernier_recu_id' => $versement->id,
            ]);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Génère et télécharge le reçu officiel PDF d'un versement avec son QR code de sécurité.
     *
     * @param VersementCaisse $versement
     * @param QrCodeService $qrCodeService
     * @return Response
     */
    public function imprimerRecu(VersementCaisse $versement, QrCodeService $qrCodeService): Response
    {
        abort_if(! request()->user()?->can('finances.view') && ! request()->user()?->hasRole('super-admin'), 403);

        $versement->load([
            'ventilations',
            'compteFinancier.eleve',
            'compteFinancier.anneeScolaire',
            'inscription.classe',
            'etablissement',
        ]);

        $compte = $versement->compteFinancier;
        $eleve = $compte->eleve;
        $inscription = $versement->inscription;
        $etablissement = $versement->etablissement;

        // Données d'intégrité encodées dans le QR code
        $qrData = sprintf(
            "SYGES-REC:%s|DATE:%s|ELEVE:%s (MAT:%s)|MONTANT:%s FCFA|MODE:%s|SOLDE:%s FCFA",
            $versement->reference_recu,
            $versement->date_versement->format('d/m/Y H:i'),
            $eleve->nom . ' ' . ($eleve->prenom ?? ''),
            $eleve->matricule,
            number_format($versement->montant_total, 0, ',', ' '),
            strtoupper($versement->mode_paiement),
            number_format($compte->solde_restant, 0, ',', ' ')
        );

        $qrCodeBase64 = $qrCodeService->generateBase64Svg($qrData, 90);

        $pdf = Pdf::loadView('finances.recu_caisse', compact('versement', 'compte', 'eleve', 'inscription', 'etablissement', 'qrCodeBase64'))
            ->setPaper('a5', 'landscape');

        return $pdf->stream("Recu_{$versement->reference_recu}.pdf");
    }

    /**
     * Annule un versement avec motif obligatoire et réajustement du compte élève.
     *
     * @param Request $request
     * @param VersementCaisse $versement
     * @param FinanceService $financeService
     * @return RedirectResponse
     */
    public function annulerVersement(Request $request, VersementCaisse $versement, FinanceService $financeService): RedirectResponse
    {
        abort_if(! $request->user()?->can('versements.cancel') && ! $request->user()?->can('finances.annuler') && ! $request->user()?->can('finances.manage') && ! $request->user()?->hasRole('super-admin'), 403);

        $request->validate([
            'motif_annulation' => 'required|string|min:5|max:500',
        ]);

        try {
            $financeService->annulerVersement($versement, $request->input('motif_annulation'), $request->user());

            return back()->with('success', "Le versement {$versement->reference_recu} a été annulé avec succès. Le solde de l'élève a été réajusté.");
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Clôture la caisse journalière de l'établissement pour la date sélectionnée.
     *
     * @param Request $request
     * @param FinanceService $financeService
     * @return RedirectResponse
     */
    public function cloturerJournee(Request $request, FinanceService $financeService): RedirectResponse
    {
        abort_if(! $request->user()?->can('caisses.cloture') && ! $request->user()?->can('finances.cloture') && ! $request->user()?->can('finances.manage') && ! $request->user()?->hasRole('super-admin'), 403);

        $validated = $request->validate([
            'etablissement_id' => 'required|exists:etablissements,id',
            'personnel_id' => 'nullable|exists:personnels,id',
            'date_cloture' => 'required|date',
            'observations' => 'nullable|string|max:500',
        ]);

        $financeService->cloturerCaisse(
            $validated['etablissement_id'],
            $validated['personnel_id'] ?? null,
            $validated['date_cloture'],
            $validated['observations'] ?? null,
            $request->user()
        );

        return back()->with('success', "La caisse du {$validated['date_cloture']} a été clôturée avec succès. Les écritures sont désormais verrouillées.");
    }

    /**
     * Ajuste la remise/bourse ou les arriérés antérieurs reportés sur le compte financier d'un élève.
     *
     * Permet au service comptable d'enregistrer manuellement une dette antérieure,
     * d'accorder une réduction financière ou de modifier les observations de l'élève.
     *
     * @param Request $request
     * @param CompteFinancierEleve $compte
     * @return RedirectResponse
     */
    public function ajusterCompte(Request $request, CompteFinancierEleve $compte): RedirectResponse
    {
        $validated = $request->validate([
            'remise_bourse' => 'nullable|numeric|min:0',
            'arrieres_anterieurs' => 'nullable|numeric|min:0',
            'observations' => 'nullable|string|max:500',
        ]);

        if (isset($validated['remise_bourse'])) {
            $compte->remise_bourse = (float) $validated['remise_bourse'];
        }
        if (isset($validated['arrieres_anterieurs'])) {
            $compte->arrieres_anterieurs = (float) $validated['arrieres_anterieurs'];
        }
        if (isset($validated['observations'])) {
            $compte->observations = $validated['observations'];
        }
        $compte->actualiserSolde();

        return back()->with('success', "Le compte financier de l'élève a été ajusté avec succès (Arriérés: " . number_format($compte->arrieres_anterieurs, 0, ',', ' ') . " FCFA, Remise: " . number_format($compte->remise_bourse, 0, ',', ' ') . " FCFA).");
    }

    /**
     * Crée ou met à jour un type de frais dans la nomenclature.
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function storeTypeFrais(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'etablissement_id' => 'required|exists:etablissements,id',
            'code' => 'required|string|max:50',
            'libelle' => 'required|string|max:150',
            'description' => 'nullable|string',
            'obligatoire' => 'required|boolean',
            'periodicite' => 'required|in:annuel,trimestriel,mensuel,ponctuel',
            'montant_defaut' => 'required|numeric|min:0',
        ]);

        TypeFrais::updateOrCreate(
            ['etablissement_id' => $validated['etablissement_id'], 'code' => strtoupper($validated['code'])],
            $validated
        );

        return back()->with('success', "Type de frais enregistré avec succès.");
    }

    /**
     * Supprime un type de frais.
     *
     * @param TypeFrais $typeFrais
     * @return RedirectResponse
     */
    public function destroyTypeFrais(TypeFrais $typeFrais): RedirectResponse
    {
        $typeFrais->delete();

        return back()->with('success', "Type de frais supprimé.");
    }

    /**
     * Enregistre un tarif dans la grille tarifaire.
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function storeTarif(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'annee_scolaire_id' => 'required|exists:annees_scolaires,id',
            'etablissement_id' => 'required|exists:etablissements,id',
            'niveau_id' => 'required|exists:niveaux,id',
            'serie' => 'nullable|string|max:50',
            'type_frais_id' => 'required|exists:types_frais,id',
            'tranche_numero' => 'required|integer|min:1|max:10',
            'libelle_tranche' => 'nullable|string|max:150',
            'montant' => 'required|numeric|min:0',
            'date_limite' => 'nullable|date',
        ]);

        FraisScolaire::create($validated);

        return back()->with('success', "Ligne de tarif enregistrée avec succès dans la matrice.");
    }

    /**
     * Supprime une ligne de tarif.
     *
     * @param FraisScolaire $fraisScolaire
     * @return RedirectResponse
     */
    public function destroyTarif(FraisScolaire $fraisScolaire): RedirectResponse
    {
        $fraisScolaire->delete();

        return back()->with('success', "Tarif retiré de la matrice.");
    }

    /**
     * Active ou désactive le verrou de solvabilité sur un établissement.
     *
     * @param Request $request
     * @param Etablissement $etablissement
     * @return RedirectResponse
     */
    public function toggleVerrouSolvabilite(Request $request, Etablissement $etablissement): RedirectResponse
    {
        $validated = $request->validate([
            'verrou_solvabilite_actif' => 'required|boolean',
        ]);

        $etablissement->update([
            'verrou_solvabilite_actif' => $validated['verrou_solvabilite_actif'],
        ]);

        $etat = $etablissement->verrou_solvabilite_actif ? 'activé' : 'désactivé';

        return back()->with('success', "Le verrou de solvabilité a été {$etat} pour cet établissement.");
    }

    /**
     * Crée une nouvelle caisse d'encaissement (Aurore §3.20).
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function storeCaisse(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'etablissement_id' => 'required|exists:etablissements,id',
            'code' => 'required|string|max:50',
            'nom' => 'required|string|max:150',
            'solde_initial' => 'nullable|numeric|min:0',
            'responsable_id' => 'nullable|exists:personnels,id',
            'is_principale' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'description' => 'nullable|string|max:500',
        ]);

        if (! empty($validated['is_principale'])) {
            Caisse::where('etablissement_id', $validated['etablissement_id'])
                ->update(['is_principale' => false]);
        }

        Caisse::create($validated);

        return back()->with('success', "Caisse « {$validated['nom']} » créée avec succès.");
    }

    /**
     * Met à jour les informations d'une caisse.
     *
     * @param Request $request
     * @param Caisse $caisse
     * @return RedirectResponse
     */
    public function updateCaisse(Request $request, Caisse $caisse): RedirectResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50',
            'nom' => 'required|string|max:150',
            'solde_initial' => 'nullable|numeric|min:0',
            'responsable_id' => 'nullable|exists:personnels,id',
            'is_principale' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'description' => 'nullable|string|max:500',
        ]);

        if (! empty($validated['is_principale']) && ! $caisse->is_principale) {
            Caisse::where('etablissement_id', $caisse->etablissement_id)
                ->update(['is_principale' => false]);
        }

        $caisse->update($validated);

        return back()->with('success', "Caisse « {$caisse->nom} » mise à jour avec succès.");
    }

    /**
     * Supprime une caisse (si aucun versement rattaché).
     *
     * @param Caisse $caisse
     * @return RedirectResponse
     */
    public function destroyCaisse(Caisse $caisse): RedirectResponse
    {
        if ($caisse->is_principale) {
            return back()->withErrors(['error' => "La caisse principale de l'établissement ne peut pas être supprimée."]);
        }

        if ($caisse->versements()->exists() || $caisse->reglementsDivers()->exists()) {
            return back()->withErrors(['error' => "Impossible de supprimer cette caisse car elle contient déjà des opérations d'encaissement."]);
        }

        $caisse->delete();

        return back()->with('success', "Caisse supprimée avec succès.");
    }

    /**
     * Enregistre un règlement divers hors scolarité (Aurore §4.2).
     *
     * @param Request $request
     * @param FinanceService $financeService
     * @return RedirectResponse
     */
    public function storeReglementDivers(Request $request, FinanceService $financeService): RedirectResponse
    {
        $validated = $request->validate([
            'caisse_id' => 'required|exists:caisses,id',
            'payeur_nom' => 'required|string|max:150',
            'payeur_telephone' => 'nullable|string|max:50',
            'categorie' => 'required|in:uniformes,cantine_libre,macaron,location,don,autre',
            'libelle' => 'required|string|max:255',
            'montant' => 'required|numeric|min:1',
            'mode_paiement' => 'required|in:especes,cheque,virement,mobile_money_manuel',
            'reference_paiement' => 'nullable|string|max:100',
            'personnel_id' => 'nullable|exists:personnels,id',
            'observations' => 'nullable|string|max:500',
        ]);

        $caissier = null;
        if (! empty($validated['personnel_id'])) {
            $caissier = Personnel::find($validated['personnel_id']);
        }

        try {
            $reglement = $financeService->enregistrerReglementDivers($validated, $caissier, $request->user());

            return back()->with([
                'success' => "Recette diverse de " . number_format($reglement->montant, 0) . " FCFA enregistrée (Reçu N° {$reglement->reference_recu}).",
                'dernier_recu_divers_id' => $reglement->id,
            ]);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Annule un règlement divers avec justification.
     *
     * @param Request $request
     * @param ReglementDivers $reglement
     * @param FinanceService $financeService
     * @return RedirectResponse
     */
    public function annulerReglementDivers(Request $request, ReglementDivers $reglement, FinanceService $financeService): RedirectResponse
    {
        $request->validate([
            'motif_annulation' => 'required|string|min:5|max:500',
        ]);

        try {
            $financeService->annulerReglementDivers($reglement, $request->input('motif_annulation'), $request->user());

            return back()->with('success', "Le règlement divers {$reglement->reference_recu} a été annulé avec succès.");
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Imprime le reçu d'un règlement divers avec QR code.
     *
     * @param ReglementDivers $reglement
     * @param QrCodeService $qrCodeService
     * @return Response
     */
    public function imprimerRecuDivers(ReglementDivers $reglement, QrCodeService $qrCodeService): Response
    {
        $reglement->load(['caisse', 'caissier', 'etablissement']);
        $etablissement = $reglement->etablissement;

        $qrData = sprintf(
            "SYGES-REC-DIV:%s|DATE:%s|PAYEUR:%s|MONTANT:%s FCFA|MOTIF:%s|MODE:%s",
            $reglement->reference_recu,
            $reglement->date_reglement->format('d/m/Y H:i'),
            $reglement->payeur_nom,
            number_format($reglement->montant, 0, ',', ' '),
            $reglement->libelle,
            strtoupper($reglement->mode_paiement)
        );

        $qrCodeBase64 = $qrCodeService->generateBase64Svg($qrData, 90);

        $pdf = Pdf::loadView('finances.recu_divers', compact('reglement', 'etablissement', 'qrCodeBase64'))
            ->setPaper('a5', 'landscape');

        return $pdf->stream("Recu_Divers_{$reglement->reference_recu}.pdf");
    }

    /**
     * Crée un modèle de bourse ou de remise (Aurore §3.19, §3.21, §3.25).
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function storeBourseRemise(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'etablissement_id' => 'required|exists:etablissements,id',
            'annee_scolaire_id' => 'nullable|exists:annees_scolaires,id',
            'code' => 'required|string|max:50',
            'nom' => 'required|string|max:150',
            'type' => 'required|in:bourse,remise',
            'categorie' => 'required|in:excellence,sociale,fratrie,personnel,autre',
            'mode_calcul' => 'required|in:pourcentage,montant_fixe',
            'valeur' => 'required|numeric|min:0.01',
            'niveau_id' => 'nullable|exists:niveaux,id',
            'type_frais_id' => 'nullable|exists:types_frais,id',
            'description' => 'nullable|string|max:500',
            'is_active' => 'nullable|boolean',
        ]);

        BourseRemise::create($validated);

        return back()->with('success', "Modèle de {$validated['type']} « {$validated['nom']} » créé avec succès.");
    }

    /**
     * Met à jour une bourse ou remise.
     *
     * @param Request $request
     * @param BourseRemise $bourseRemise
     * @return RedirectResponse
     */
    public function updateBourseRemise(Request $request, BourseRemise $bourseRemise): RedirectResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50',
            'nom' => 'required|string|max:150',
            'type' => 'required|in:bourse,remise',
            'categorie' => 'required|in:excellence,sociale,fratrie,personnel,autre',
            'mode_calcul' => 'required|in:pourcentage,montant_fixe',
            'valeur' => 'required|numeric|min:0.01',
            'niveau_id' => 'nullable|exists:niveaux,id',
            'type_frais_id' => 'nullable|exists:types_frais,id',
            'description' => 'nullable|string|max:500',
            'is_active' => 'nullable|boolean',
        ]);

        $bourseRemise->update($validated);

        return back()->with('success', "Modèle mis à jour avec succès.");
    }

    /**
     * Supprime un modèle de bourse ou remise.
     *
     * @param BourseRemise $bourseRemise
     * @return RedirectResponse
     */
    public function destroyBourseRemise(BourseRemise $bourseRemise): RedirectResponse
    {
        $bourseRemise->delete();

        return back()->with('success', "Modèle supprimé avec succès.");
    }

    /**
     * Attribue une bourse ou remise au compte d'un élève (Aurore §4.1.6).
     *
     * @param Request $request
     * @param FinanceService $financeService
     * @return RedirectResponse
     */
    public function attribuerBourseRemise(Request $request, FinanceService $financeService): RedirectResponse
    {
        $validated = $request->validate([
            'compte_financier_id' => 'required|exists:comptes_financiers_eleves,id',
            'bourse_remise_id' => 'required|exists:bourses_remises,id',
            'motif' => 'nullable|string|max:255',
        ]);

        $compte = CompteFinancierEleve::findOrFail($validated['compte_financier_id']);
        $bourseRemise = BourseRemise::findOrFail($validated['bourse_remise_id']);

        $attribution = $financeService->attribuerBourseRemise($compte, $bourseRemise, $validated['motif'] ?? null, $request->user());

        return back()->with('success', "{$bourseRemise->nom} de " . number_format($attribution->montant_calcule, 0) . " FCFA attribuée avec succès. L'ardoise de l'élève a été recalculée.");
    }

    /**
     * Retire une bourse ou remise accordée à un élève.
     *
     * @param EleveBourseRemise $attribution
     * @param FinanceService $financeService
     * @return RedirectResponse
     */
    public function retirerBourseRemise(EleveBourseRemise $attribution, FinanceService $financeService): RedirectResponse
    {
        $financeService->retirerBourseRemise($attribution);

        return back()->with('success', "Attribution de bourse/remise retirée avec succès.");
    }

    /**
     * Crée un modèle d'échéancier calendaire avec ses tranches (Aurore §3.24).
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function storeModeleEcheance(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'etablissement_id' => 'required|exists:etablissements,id',
            'annee_scolaire_id' => 'required|exists:annees_scolaires,id',
            'nom' => 'required|string|max:150',
            'description' => 'nullable|string|max:500',
            'is_default' => 'nullable|boolean',
            'tranches' => 'required|array|min:1',
            'tranches.*.tranche_numero' => 'required|integer|min:1',
            'tranches.*.libelle' => 'required|string|max:150',
            'tranches.*.date_limite' => 'required|date',
            'tranches.*.pourcentage_exigible' => 'required|numeric|min:0|max:100',
        ]);

        if (! empty($validated['is_default'])) {
            ModeleEcheance::where('etablissement_id', $validated['etablissement_id'])
                ->where('annee_scolaire_id', $validated['annee_scolaire_id'])
                ->update(['is_default' => false]);
        }

        $modele = ModeleEcheance::create([
            'etablissement_id' => $validated['etablissement_id'],
            'annee_scolaire_id' => $validated['annee_scolaire_id'],
            'nom' => $validated['nom'],
            'description' => $validated['description'] ?? null,
            'is_default' => $validated['is_default'] ?? false,
        ]);

        foreach ($validated['tranches'] as $tr) {
            EcheanceTranche::create([
                'modele_echeance_id' => $modele->id,
                'tranche_numero' => $tr['tranche_numero'],
                'libelle' => $tr['libelle'],
                'date_limite' => $tr['date_limite'],
                'pourcentage_exigible' => $tr['pourcentage_exigible'],
            ]);
        }

        return back()->with('success', "Modèle d'échéancier « {$modele->nom} » enregistré avec succès.");
    }

    /**
     * Supprime un modèle d'échéancier.
     *
     * @param ModeleEcheance $modeleEcheance
     * @return RedirectResponse
     */
    public function destroyModeleEcheance(ModeleEcheance $modeleEcheance): RedirectResponse
    {
        $modeleEcheance->delete();

        return back()->with('success', "Échéancier supprimé avec succès.");
    }

    /**
     * Enregistre ou met à jour la configuration des pénalités de retard (Aurore §3.17).
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function storePenaliteConfiguration(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'etablissement_id' => 'required|exists:etablissements,id',
            'nom' => 'required|string|max:150',
            'mode_calcul' => 'required|in:montant_fixe,pourcentage',
            'valeur' => 'required|numeric|min:1',
            'jours_grace' => 'required|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        PenaliteConfiguration::updateOrCreate(
            ['etablissement_id' => $validated['etablissement_id']],
            $validated
        );

        return back()->with('success', "Paramétrage des pénalités de retard enregistré avec succès.");
    }

    /**
     * Déclenche l'analyse des retards d'échéances et applique les pénalités.
     *
     * @param Request $request
     * @param FinanceService $financeService
     * @return RedirectResponse
     */
    public function appliquerPenalitesRetard(Request $request, FinanceService $financeService): RedirectResponse
    {
        $validated = $request->validate([
            'etablissement_id' => 'required|exists:etablissements,id',
            'annee_scolaire_id' => 'required|exists:annees_scolaires,id',
        ]);

        $res = $financeService->appliquerPenalitesAutomatiquesEcheances(
            $validated['etablissement_id'],
            $validated['annee_scolaire_id'],
            $request->user()
        );

        if ($res['penalites_appliquees'] > 0) {
            return back()->with('success', "{$res['penalites_appliquees']} pénalité(s) de retard appliquée(s) pour un montant total de " . number_format($res['total_montant'], 0) . " FCFA.");
        }

        return back()->with('info', "Aucun nouveau retard de paiement à pénaliser à cette date.");
    }

    /**
     * Annule une pénalité appliquée sur le compte d'un élève.
     *
     * @param PenaliteEleve $penalite
     * @param FinanceService $financeService
     * @return RedirectResponse
     */
    public function annulerPenaliteEleve(PenaliteEleve $penalite, FinanceService $financeService): RedirectResponse
    {
        $financeService->annulerPenalite($penalite);

        return back()->with('success', "Pénalité de retard annulée avec succès.");
    }
}
