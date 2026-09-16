<?php

namespace App\Http\Controllers\Portail;

use App\Http\Controllers\Controller;
use App\Models\Eleve;
use App\Models\Inscription;
use App\Models\VersementCaisse;
use App\Services\PortailParentService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Contrôleur pour le Portail Parents & Élèves (Module 16 Gesco V1.1).
 *
 * Fonctionnalités :
 * - Authentification unique par téléphone parent (16.1).
 * - Tableau de bord multi-enfants avec basculement en un clic (16.1).
 * - Consultation en lecture seule des notes, de l'emploi du temps, de la discipline et des finances (16.2).
 * - Verrou visuel financier strict bloquant le téléchargement du bulletin en cas d'impayé (16.3).
 * - Téléchargement des reçus de caisse certifiés avec QR code de sécurité (16.2).
 */
class PortailParentController extends Controller
{
    /**
     * Affiche l'espace parent avec la liste des enfants et le profil actif.
     *
     * @param Request $request
     * @param PortailParentService $portailService
     * @return InertiaResponse
     */
    public function index(Request $request, PortailParentService $portailService): InertiaResponse
    {
        $user = $request->user();

        // 1. Récupération des enfants rattachés au compte
        $enfants = $portailService->getEnfantsRattaches($user);

        // 2. Sélection de l'enfant actif
        $selectedEnfantId = $request->integer('enfant_id') ?: $request->session()->get('portail_enfant_id');
        $enfantActif = $selectedEnfantId
            ? $enfants->firstWhere('id', $selectedEnfantId)
            : $enfants->first();

        // 3. Récupération du profil complet de l'enfant
        $profilEnfant = null;
        if ($enfantActif) {
            $anneeId = $request->integer('annee_id');
            $periodeId = $request->integer('periode_id');
            $profilEnfant = $portailService->getProfilCompletEnfant($enfantActif, $anneeId ?: null, $periodeId ?: null);
        }

        return Inertia::render('portail/index', [
            'enfants' => $enfants,
            'enfantActif' => $enfantActif,
            'profil' => $profilEnfant,
            'filters' => $request->only(['periode_id', 'tab']),
        ]);
    }

    /**
     * Bascule l'enfant sélectionné pour le parent (Gesco 16.1).
     *
     * @param Request $request
     * @param Eleve $eleve
     * @return RedirectResponse
     */
    public function selectEnfant(Request $request, Eleve $eleve): RedirectResponse
    {
        $request->session()->put('portail_enfant_id', $eleve->id);

        return redirect()->route('portail.index', ['enfant_id' => $eleve->id]);
    }

    /**
     * Télécharge le bulletin de notes avec contrôle strict du verrou de solvabilité (Gesco 11.5 & 16.3).
     *
     * @param Request $request
     * @param Inscription $inscription
     * @param PortailParentService $portailService
     * @return Response
     */
    public function telechargerBulletin(
        Request $request,
        Inscription $inscription,
        PortailParentService $portailService
    ): Response {
        $periodeId = $request->integer('periode_id') ?: null;

        return $portailService->telechargerBulletin($inscription, $periodeId, $request->user());
    }

    /**
     * Télécharge le reçu de caisse numéroté et certifié par QR code (Gesco 11.3 & 16.2).
     *
     * @param Request $request
     * @param VersementCaisse $versement
     * @return Response
     */
    public function telechargerRecu(
        Request $request,
        VersementCaisse $versement,
        \App\Services\QrCodeService $qrCodeService
    ): Response {
        $compte = $versement->compteFinancier;
        $inscription = $compte->inscription;
        $eleve = $inscription->eleve;
        $etablissement = $compte->etablissement()->with('institution')->first();

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
}
