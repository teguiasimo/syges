<?php

namespace App\Http\Controllers\Portail;

use App\Http\Controllers\Controller;
use App\Models\Eleve;
use App\Models\Inscription;
use App\Models\VersementCaisse;
use App\Scopes\EtablissementScope;
use App\Services\PortailParentService;
use App\Services\QrCodeService;
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
     * Résout l'inscription sans dépendre de `etablissement_id` sur le compte parent (souvent null),
     * vérifie que l'élève appartient bien au parent connecté ou à un profil autorisé,
     * puis contextualise le scope d'établissement sur le campus de l'inscription pour le calcul.
     *
     * @param Request $request Requête HTTP entrante
     * @param int $inscription Identifiant de l'inscription scolaire
     * @param PortailParentService $portailService Service du portail parents/élèves
     * @return Response Flux PDF du bulletin
     */
    public function telechargerBulletin(
        Request $request,
        int $inscription,
        PortailParentService $portailService
    ): Response {
        $inscriptionModel = Inscription::tousEtablissements()->findOrFail($inscription);

        if (! $portailService->userCanAccessEleve($request->user(), $inscriptionModel->eleve_id)) {
            abort(403, 'Accès non autorisé à ce bulletin scolaire.');
        }

        $periodeId = $request->integer('periode_id') ?: null;

        return EtablissementScope::withEtablissementOverride(
            $inscriptionModel->etablissement_id,
            fn (): Response => $portailService->telechargerBulletin($inscriptionModel, $periodeId, $request->user())
        );
    }

    /**
     * Télécharge le reçu de caisse numéroté et certifié par QR code (Gesco 11.3 & 16.2).
     * Utilise le format officiel A4 Portrait (2 exemplaires par page ou 1 exemplaire si tableau long).
     *
     * @param Request $request Requête HTTP entrante
     * @param int $versement Identifiant du versement caisse
     * @param QrCodeService $qrCodeService Générateur QR Code SVG Base64
     * @param PortailParentService $portailService Service du portail parents/élèves
     * @return Response Flux PDF du reçu officiel A4
     */
    public function telechargerRecu(
        Request $request,
        int $versement,
        QrCodeService $qrCodeService,
        PortailParentService $portailService
    ): Response {
        $versementModel = VersementCaisse::tousEtablissements()->findOrFail($versement);

        return EtablissementScope::withEtablissementOverride(
            $versementModel->etablissement_id,
            function () use ($request, $versementModel, $qrCodeService, $portailService): Response {
                $versement = $versementModel->load(['ventilations', 'compteFinancier.inscription.eleve']);
                $compte = $versement->compteFinancier;
                $inscription = $compte->inscription;
                $eleve = $inscription->eleve;

                if (! $portailService->userCanAccessEleve($request->user(), $eleve->id)) {
                    abort(403, 'Accès non autorisé à ce reçu de paiement.');
                }

                $etablissement = $compte->etablissement()->with('institution')->first();

                $qrData = sprintf(
                    'SYGES-REC:%s|DATE:%s|ELEVE:%s (MAT:%s)|MONTANT:%s FCFA|MODE:%s|SOLDE:%s FCFA',
                    $versement->reference_recu,
                    $versement->date_versement->format('d/m/Y H:i'),
                    $eleve->nom.' '.($eleve->prenom ?? ''),
                    $eleve->matricule,
                    number_format($versement->montant_total, 0, ',', ' '),
                    strtoupper($versement->mode_paiement),
                    number_format($compte->solde_restant, 0, ',', ' ')
                );

                $qrCodeBase64 = $qrCodeService->generateBase64Svg($qrData, 90);

                $pdf = Pdf::loadView('finances.recu_caisse', compact('versement', 'compte', 'eleve', 'inscription', 'etablissement', 'qrCodeBase64'))
                    ->setPaper('a4', 'portrait');

                return $pdf->stream("Recu_{$versement->reference_recu}.pdf");
            }
        );
    }
}
