<?php

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use App\Models\Classe;
use App\Models\Etablissement;
use App\Models\HistoriqueSms;
use App\Models\Niveau;
use App\Models\Section;
use App\Models\SmsCampagne;
use App\Services\SmsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Contrôleur principal pour le Module 14 : Communication & Notifications SMS (Gesco V1.1).
 *
 * Coordonne :
 * - Le tableau de bord de communication et surveillance du solde de crédits SMS.
 * - La diffusion de campagnes SMS groupées avec ciblage par classe, niveau, section ou parents insolvables.
 * - L'envoi direct de SMS unitaires aux parents d'élèves.
 * - La traçabilité exhaustive et l'historique d'audit des messages émis.
 * - La configuration technique de la passerelle (Orange, MTN, Africa's Talking) et du Sender ID.
 */
class CommunicationSmsController extends Controller
{
    /**
     * Affiche l'espace de communication SMS et traçabilité.
     *
     * @param Request $request
     * @param SmsService $smsService
     * @return InertiaResponse
     */
    public function index(Request $request, SmsService $smsService): InertiaResponse
    {
        abort_if(! $request->user()?->can('communication.view') && ! $request->user()?->hasRole('super-admin'), 403);

        $etablissements = Etablissement::with('institution')->get();
        $currentEtablissement = $request->has('etablissement_id')
            ? Etablissement::find($request->integer('etablissement_id'))
            : $etablissements->first();

        $etablissementId = $currentEtablissement?->id ?? 0;

        // Configuration passerelle et solde actif
        $config = $smsService->getConfiguration($etablissementId);

        // Classes de l'établissement
        $classes = Classe::whereHas('niveau.cycle.section', fn ($q) => $q->where('etablissement_id', $etablissementId))
            ->orderBy('nom')
            ->get();

        // Niveaux de l'établissement
        $niveaux = Niveau::whereHas('cycle.section', fn ($q) => $q->where('etablissement_id', $etablissementId))
            ->orderBy('ordre')
            ->get();

        // Sections de l'établissement
        $sections = Section::where('etablissement_id', $etablissementId)
            ->orderBy('nom')
            ->get();

        // Campagnes de masse
        $campagnes = SmsCampagne::with('lanceur')
            ->where('etablissement_id', $etablissementId)
            ->latest()
            ->take(50)
            ->get();

        // Historique détaillé des messages individuels
        $historiqueQuery = HistoriqueSms::with(['campagne', 'inscription.eleve'])
            ->where('etablissement_id', $etablissementId)
            ->latest();

        if ($request->filled('type_notification')) {
            $historiqueQuery->where('type_notification', $request->input('type_notification'));
        }

        $historiqueSms = $historiqueQuery->take(100)->get();

        return Inertia::render('administration/communication/index', [
            'config' => $config,
            'campagnes' => $campagnes,
            'historiqueSms' => $historiqueSms,
            'classes' => $classes,
            'niveaux' => $niveaux,
            'sections' => $sections,
            'etablissements' => $etablissements,
            'currentEtablissement' => $currentEtablissement,
            'filters' => $request->only(['type_notification', 'tab']),
        ]);
    }

    /**
     * Diffuse une campagne SMS de masse après nettoyage GSM 7-bit et contrôle de solde (Gesco 14.2.B).
     *
     * @param Request $request
     * @param SmsService $smsService
     * @return RedirectResponse
     */
    public function storeCampagne(Request $request, SmsService $smsService): RedirectResponse
    {
        abort_if(! $request->user()?->can('communication.send') && ! $request->user()?->hasRole('super-admin'), 403);

        $validated = $request->validate([
            'etablissement_id' => 'required|exists:etablissements,id',
            'titre' => 'required|string|min:3|max:150',
            'message' => 'required|string|min:5|max:1000',
            'cible_type' => 'required|in:tous,section,niveau,classe,insolvables',
            'cible_id' => 'nullable|integer',
        ]);

        $campagne = $smsService->lancerCampagneMasse(
            (int) $validated['etablissement_id'],
            $validated,
            $request->user()
        );

        return back()->with('success', "Campagne SMS diffusée avec succès à {$campagne->nombre_destinataires} destinataires ({$campagne->total_credits} crédits consommés).");
    }

    /**
     * Envoie un SMS unitaire direct à un destinataire (Gesco 14.2).
     *
     * @param Request $request
     * @param SmsService $smsService
     * @return RedirectResponse
     */
    public function storeSmsDirect(Request $request, SmsService $smsService): RedirectResponse
    {
        abort_if(! $request->user()?->can('communication.send') && ! $request->user()?->hasRole('super-admin'), 403);

        $validated = $request->validate([
            'etablissement_id' => 'required|exists:etablissements,id',
            'telephone' => 'required|string|min:8|max:25',
            'message' => 'required|string|min:3|max:1000',
            'destinataire_nom' => 'nullable|string|max:150',
            'type' => 'sometimes|in:personnalise,alerte_absence,alerte_sanction,rappel_paiement',
        ]);

        $smsService->envoyerSmsDirect(
            (int) $validated['etablissement_id'],
            $validated['telephone'],
            $validated['message'],
            $validated['type'] ?? 'personnalise',
            $validated['destinataire_nom'] ?? null
        );

        return back()->with('success', "Message SMS expédié avec succès.");
    }

    /**
     * Met à jour les paramètres de la passerelle SMS et du Sender ID (Gesco 14.1).
     *
     * @param Request $request
     * @param SmsService $smsService
     * @return RedirectResponse
     */
    public function updateConfig(Request $request, SmsService $smsService): RedirectResponse
    {
        abort_if(! $request->user()?->can('communication.manage') && ! $request->user()?->hasRole('super-admin'), 403);

        $validated = $request->validate([
            'etablissement_id' => 'required|exists:etablissements,id',
            'fournisseur' => 'required|string|max:50',
            'sender_id' => 'required|string|min:2|max:11|regex:/^[A-Za-z0-9_-]+$/',
            'api_url' => 'nullable|url|max:255',
            'api_key' => 'nullable|string|max:1000',
            'api_secret' => 'nullable|string|max:1000',
            'seuil_alerte_solde' => 'required|integer|min:1|max:1000',
            'is_active' => 'required|boolean',
        ]);

        $config = $smsService->getConfiguration((int) $validated['etablissement_id']);
        $config->update($validated);

        return back()->with('success', "Configuration de la passerelle SMS enregistrée avec succès.");
    }

    /**
     * Crédite manuellement le solde de SMS de l'établissement (Gesco 14.1).
     *
     * @param Request $request
     * @param SmsService $smsService
     * @return RedirectResponse
     */
    public function rechargerCredits(Request $request, SmsService $smsService): RedirectResponse
    {
        abort_if(! $request->user()?->can('communication.manage') && ! $request->user()?->hasRole('super-admin'), 403);

        $validated = $request->validate([
            'etablissement_id' => 'required|exists:etablissements,id',
            'credits' => 'required|integer|min:10|max:100000',
        ]);

        $config = $smsService->rechargerCredits((int) $validated['etablissement_id'], (int) $validated['credits']);

        return back()->with('success', "Solde rechargé avec succès. Nouveau solde disponible : {$config->solde_credits} SMS.");
    }
}
