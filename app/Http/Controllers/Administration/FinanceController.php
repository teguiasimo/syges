<?php

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\ClotureCaisse;
use App\Models\CompteFinancierEleve;
use App\Models\Etablissement;
use App\Models\FraisScolaire;
use App\Models\Niveau;
use App\Models\Personnel;
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
 * Contrôleur principal pour le Module 11 : Gestion de la Caisse & Paiements (Gesco V1.1).
 *
 * Coordonne les opérations financières de l'établissement :
 * - Guichet d'encaissement et émission des reçus officiels infalsifiables.
 * - Suivi des comptes courants scolaires des élèves et ardoises financières.
 * - Paramétrage de la nomenclature des frais et de la matrice tarifaire.
 * - Journal quotidien de caisse et clôture journalière des comptes.
 * - Activation/désactivation du verrou de solvabilité sur les bulletins.
 */
class FinanceController extends Controller implements HasMiddleware
{
    /**
     * Définition de la matrice des middlewares d'autorisation pour le module Finances & Caisse.
     *
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:finances.view', only: ['index', 'imprimerRecu']),
            new Middleware('can:finances.manage', only: [
                'storeVersement',
                'ajusterCompte',
                'storeTypeFrais',
                'destroyTypeFrais',
                'storeTarif',
                'destroyTarif',
                'toggleVerrouSolvabilite',
            ]),
            new Middleware('can:finances.annuler', only: ['annulerVersement']),
            new Middleware('can:finances.cloture', only: ['cloturerJournee']),
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

        $anneeId = $request->integer('annee_scolaire_id', $currentAnnee?->id ?? 0);
        $etablissements = Etablissement::with('institution')->get();
        $currentEtablissement = $request->has('etablissement_id')
            ? Etablissement::find($request->integer('etablissement_id'))
            : $etablissements->first();

        $etablissementId = $currentEtablissement?->id ?? 0;

        // 1. Liste des versements avec pagination et filtres
        $versementsQuery = VersementCaisse::with([
            'compteFinancier.eleve',
            'inscription.classe',
            'ventilations',
            'caissier',
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

        // 5. Journal du jour et état de la caisse
        $today = Carbon::today()->toDateString();
        $versementsDuJour = VersementCaisse::where('etablissement_id', $etablissementId)
            ->whereDate('date_versement', $today)
            ->where('statut', 'valide')
            ->get();

        $journalDuJour = [
            'date' => $today,
            'total_especes' => (float) $versementsDuJour->where('mode_paiement', 'especes')->sum('montant_total'),
            'total_cheques' => (float) $versementsDuJour->where('mode_paiement', 'cheque')->sum('montant_total'),
            'total_virements' => (float) $versementsDuJour->where('mode_paiement', 'virement')->sum('montant_total'),
            'total_mobile_money' => (float) $versementsDuJour->where('mode_paiement', 'mobile_money_manuel')->sum('montant_total'),
            'total_general' => (float) $versementsDuJour->sum('montant_total'),
            'nombre_transactions' => $versementsDuJour->count(),
            'est_cloture' => ClotureCaisse::where('etablissement_id', $etablissementId)
                ->where('date_cloture', $today)
                ->where('statut', 'cloturee')
                ->exists(),
        ];

        // 6. Historique des clôtures passées
        $historiqueClotures = ClotureCaisse::with(['caissier', 'verificateur'])
            ->where('etablissement_id', $etablissementId)
            ->latest('date_cloture')
            ->take(30)
            ->get();

        // 7. Données d'appui pour les formulaires
        $classes = Classe::whereHas('niveau.cycle.section', fn ($q) => $q->where('etablissement_id', $etablissementId))
            ->where('is_active', true)
            ->orderBy('nom')
            ->get();

        $niveaux = Niveau::whereHas('cycle.section', fn ($q) => $q->where('etablissement_id', $etablissementId))
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
        abort_if(! $request->user()?->can('finances.manage') && ! $request->user()?->hasRole('super-admin'), 403);

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
        abort_if(! $request->user()?->can('finances.annuler') && ! $request->user()?->hasRole('super-admin'), 403);

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
        abort_if(! $request->user()?->can('finances.cloture') && ! $request->user()?->hasRole('super-admin'), 403);

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
}
