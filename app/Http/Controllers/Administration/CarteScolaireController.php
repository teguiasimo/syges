<?php

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\Etablissement;
use App\Models\Inscription;
use App\Services\QrCodeService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Contrôleur de gestion, prévisualisation et génération des Cartes d'Identité Scolaires (Phase 2).
 * 
 * Conçu selon les normes officielles camerounaises et inspiré du spécimen scanné réel
 * du Collège Bilingue St Abraham de Foumban :
 * - Recto : En-tête bilingue officiel, logo, drapeau tricolore, photo numérique, classe, cachet.
 * - Verso : Coordonnées complètes, grand logo, devise et QR Code dynamique de contrôle.
 * - Impression industrielle par planches A4 de 8 cartes prêtes pour massicot.
 */
class CarteScolaireController extends Controller implements HasMiddleware
{
    /**
     * Définition de la matrice des middlewares d'autorisation pour les Cartes Scolaires.
     *
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:cartes_scolaires.view|eleves.view', only: ['index']),
            new Middleware('can:cartes_scolaires.generate|eleves.manage', only: ['imprimerPlanche', 'imprimerIndividuel']),
        ];
    }
    /**
     * Affiche l'interface de gestion et prévisualisation des cartes scolaires.
     *
     * @param Request $request
     * @return InertiaResponse
     */
    public function index(Request $request): InertiaResponse
    {
        $user = Auth::user();
        $isSuperAdmin = $user ? ($user->hasRole('super-admin') || $user->email === 'admin@gmail.com') : false;

        // Année scolaire active ou sélectionnée
        $anneeActive = AnneeScolaire::where('is_current', true)->first() ?? AnneeScolaire::latest()->first();
        $selectedAnneeId = (int) $request->input('annee_scolaire_id', $anneeActive?->id);

        // Récupération des établissements selon permissions
        $etablissementsQuery = Etablissement::with([
            'sections.cycles.niveaux.classes',
            'institution',
        ]);

        if (! $isSuperAdmin && $user?->personnel?->etablissement_id) {
            $etablissementsQuery->where('id', $user->personnel->etablissement_id);
        }

        $etablissements = $etablissementsQuery->get();
        $selectedEtabId = (int) $request->input('etablissement_id', $etablissements->first()?->id);

        // Filtres hiérarchiques
        $classeId = $request->input('classe_id');
        $hasPhoto = $request->input('has_photo'); // 'all', 'with_photo', 'without_photo'
        $search = $request->input('search');

        // Récupération des inscriptions
        $inscriptionsQuery = Inscription::with([
            'eleve',
            'classe.niveau.cycle.section',
            'etablissement.institution',
            'anneeScolaire',
        ])
        ->where('annee_scolaire_id', $selectedAnneeId)
        ->where('statut', 'validee');

        if ($selectedEtabId) {
            $inscriptionsQuery->where('etablissement_id', $selectedEtabId);
        }

        if ($classeId) {
            $inscriptionsQuery->where('classe_id', $classeId);
        }

        if ($hasPhoto === 'with_photo') {
            $inscriptionsQuery->whereHas('eleve', function ($q) {
                $q->whereNotNull('photo_url')->where('photo_url', '!=', '');
            });
        } elseif ($hasPhoto === 'without_photo') {
            $inscriptionsQuery->whereHas('eleve', function ($q) {
                $q->whereNull('photo_url')->orWhere('photo_url', '');
            });
        }

        if ($search) {
            $inscriptionsQuery->whereHas('eleve', function ($q) use ($search) {
                $q->where('nom', 'like', "%{$search}%")
                  ->orWhere('prenom', 'like', "%{$search}%")
                  ->orWhere('matricule', 'like', "%{$search}%");
            });
        }

        $inscriptions = $inscriptionsQuery->orderBy('classe_id')
            ->orderBy(
                Eleve::select('nom')->whereColumn('eleves.id', 'inscriptions.eleve_id')
            )
            ->get();

        // Statistiques globales de couverture de cartes
        $totalInscrits = $inscriptions->count();
        $totalAvecPhoto = $inscriptions->filter(fn ($i) => !empty($i->eleve->photo_url))->count();
        $totalSansPhoto = $totalInscrits - $totalAvecPhoto;

        return Inertia::render('administration/eleves/cartes-scolaires', [
            'inscriptions' => $inscriptions,
            'etablissements' => $etablissements,
            'selectedEtabId' => $selectedEtabId,
            'selectedAnneeId' => $selectedAnneeId,
            'anneesScolaires' => AnneeScolaire::orderByDesc('is_current')->orderByDesc('date_debut')->get(),
            'stats' => [
                'total_inscrits' => $totalInscrits,
                'total_avec_photo' => $totalAvecPhoto,
                'total_sans_photo' => $totalSansPhoto,
                'taux_couverture_photo' => $totalInscrits > 0 ? round(($totalAvecPhoto / $totalInscrits) * 100, 1) : 0,
            ],
            'filters' => [
                'etablissement_id' => (string) $selectedEtabId,
                'classe_id' => (string) $classeId,
                'has_photo' => $hasPhoto ?: 'all',
                'search' => $search ?: '',
            ],
        ]);
    }

    /**
     * Génère et télécharge une planche A4 (recto, verso ou recto-verso) de 8 cartes par page.
     *
     * @param Request $request
     * @param QrCodeService $qrCodeService
     * @return Response
     */
    public function imprimerPlanche(Request $request, QrCodeService $qrCodeService): Response
    {
        $validated = $request->validate([
            'inscription_ids' => ['required', 'array', 'min:1'],
            'inscription_ids.*' => ['integer', 'exists:inscriptions,id'],
            'face' => ['required', 'string', 'in:recto,verso,recto_verso'],
        ]);

        $inscriptions = Inscription::with([
            'eleve',
            'classe.niveau.cycle.section',
            'etablissement.institution',
            'anneeScolaire',
        ])
        ->whereIn('id', $validated['inscription_ids'])
        ->get();

        if ($inscriptions->isEmpty()) {
            abort(404, 'Aucune inscription sélectionnée pour la génération des cartes.');
        }

        // Préparation des données de chaque carte avec QR Code dynamique
        $cartes = $inscriptions->map(function ($insc) use ($qrCodeService) {
            $eleve = $insc->eleve;
            $etab = $insc->etablissement;
            $inst = $etab?->institution;

            // URL publique absolue pour le contrôle d'accès au portail
            $verificationUrl = route('cartes-scolaires.verifier', ['matricule' => $eleve->matricule]);

            // Génération du QR Code vectoriel en Data URI Base64
            $qrCodeBase64 = $qrCodeService->generateBase64Svg($verificationUrl, 100);

            return [
                'inscription_id' => $insc->id,
                'matricule' => $eleve->matricule,
                'nom_complet' => $eleve->nom . ' ' . ($eleve->prenom ?? ''),
                'date_naissance' => $eleve->date_naissance ? $eleve->date_naissance->format('d/m/Y') : '---',
                'lieu_naissance' => $eleve->lieu_naissance ?: '---',
                'sexe' => $eleve->sexe ?: 'M',
                'classe_nom' => $insc->classe->nom,
                'photo_url' => $eleve->photo_url,
                'annee_nom' => $insc->anneeScolaire->nom,
                'telephone_direction' => $etab->telephone_1 ?: ($inst?->telephone_1 ?: '---'),
                'etablissement_nom' => $etab->nom,
                'etablissement_nom_en' => $etab->nom_en ?: $etab->nom,
                'adresse' => $etab->adresse ?: ($inst?->ville ?: 'Cameroun'),
                'boite_postale' => $inst?->boite_postale ?: '---',
                'devise' => $inst?->devise ?: 'Respect - Travail - Réussite',
                'delegation_regionale' => $inst?->delegation_regionale ?: 'DÉLÉGATION RÉGIONALE DU CENTRE',
                'delegation_regionale_en' => $inst?->delegation_regionale_en ?: 'REGIONAL DELEGATION FOR THE CENTRE',
                'delegation_departementale' => $inst?->delegation_departementale ?: 'DÉLÉGATION DÉPARTEMENTALE DU MFOUNDI',
                'delegation_departementale_en' => $inst?->delegation_departementale_en ?: 'DIVISIONAL DELEGATION FOR MFOUNDI',
                'nom_chef' => $etab->nom_chef_etablissement ?: 'Le Chef d’Établissement',
                'logo_url' => $etab->logo ?: $inst?->logo,
                'qr_code' => $qrCodeBase64,
                'groupe_sanguin' => 'N/A',
                'contact_urgence' => $eleve->telephone_parent ?: '---',
            ];
        });

        // Découpage en planches de 8 cartes par feuille A4 portrait
        $planches = $cartes->chunk(8);

        $face = $validated['face'];
        $vue = match ($face) {
            'recto' => 'cartes.planche_recto',
            'verso' => 'cartes.planche_verso',
            'recto_verso' => 'cartes.planche_recto_verso',
        };

        $pdf = Pdf::loadView($vue, [
            'planches' => $planches,
            'face' => $face,
        ])
        ->setPaper('a4', 'portrait')
        ->setOption('isHtml5ParserEnabled', true)
        ->setOption('isRemoteEnabled', true);

        $filename = "Cartes_Scolaires_{$face}_" . date('Ymd_His') . ".pdf";

        return $pdf->stream($filename);
    }

    /**
     * Génère et télécharge le badge unitaire au format standard ISO CR-80 (85.6mm x 54mm).
     *
     * @param Inscription $inscription
     * @param QrCodeService $qrCodeService
     * @return Response
     */
    public function imprimerUnitaire(Inscription $inscription, QrCodeService $qrCodeService): Response
    {
        $inscription->load([
            'eleve',
            'classe.niveau.cycle.section',
            'etablissement.institution',
            'anneeScolaire',
        ]);

        $eleve = $inscription->eleve;
        $etab = $inscription->etablissement;
        $inst = $etab?->institution;

        $verificationUrl = route('cartes-scolaires.verifier', ['matricule' => $eleve->matricule]);
        $qrCodeBase64 = $qrCodeService->generateBase64Svg($verificationUrl, 100);

        $carte = [
            'matricule' => $eleve->matricule,
            'nom_complet' => $eleve->nom . ' ' . ($eleve->prenom ?? ''),
            'date_naissance' => $eleve->date_naissance ? $eleve->date_naissance->format('d/m/Y') : '---',
            'lieu_naissance' => $eleve->lieu_naissance ?: '---',
            'sexe' => $eleve->sexe ?: 'M',
            'classe_nom' => $inscription->classe->nom,
            'photo_url' => $eleve->photo_url,
            'annee_nom' => $inscription->anneeScolaire->nom,
            'telephone_direction' => $etab->telephone_1 ?: ($inst?->telephone_1 ?: '---'),
            'etablissement_nom' => $etab->nom,
            'etablissement_nom_en' => $etab->nom_en ?: $etab->nom,
            'adresse' => $etab->adresse ?: ($inst?->ville ?: 'Cameroun'),
            'boite_postale' => $inst?->boite_postale ?: '---',
            'devise' => $inst?->devise ?: 'Respect - Travail - Réussite',
            'delegation_regionale' => $inst?->delegation_regionale ?: 'DÉLÉGATION RÉGIONALE DU CENTRE',
            'delegation_regionale_en' => $inst?->delegation_regionale_en ?: 'REGIONAL DELEGATION FOR THE CENTRE',
            'delegation_departementale' => $inst?->delegation_departementale ?: 'DÉLÉGATION DÉPARTEMENTALE DU MFOUNDI',
            'delegation_departementale_en' => $inst?->delegation_departementale_en ?: 'DIVISIONAL DELEGATION FOR MFOUNDI',
            'nom_chef' => $etab->nom_chef_etablissement ?: 'Le Chef d’Établissement',
            'logo_url' => $etab->logo ?: $inst?->logo,
            'qr_code' => $qrCodeBase64,
            'groupe_sanguin' => 'N/A',
            'contact_urgence' => $eleve->telephone_parent ?: '---',
        ];

        // Format CR-80 en points (85.6mm = 242.6pt, 53.98mm = 153pt)
        $customPaper = [0, 0, 243, 153];

        $pdf = Pdf::loadView('cartes.badge_unitaire', compact('carte'))
            ->setPaper($customPaper, 'landscape')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', true);

        return $pdf->stream("Badge_{$eleve->matricule}.pdf");
    }

    /**
     * Page publique de contrôle d'authenticité accessible lors du scan du QR Code par smartphone.
     *
     * @param string $matricule
     * @param Request $request
     * @return \Illuminate\Contracts\View\View
     */
    public function verifier(string $matricule, Request $request)
    {
        $eleve = Eleve::where('matricule', $matricule)->first();

        if (! $eleve) {
            return view('cartes.verification_publique', [
                'valide' => false,
                'message' => "Aucun élève correspondant au matricule « {$matricule} » n'a été trouvé dans le système officiel SYGES.",
                'eleve' => null,
            ]);
        }

        // Récupération de la dernière inscription active
        $inscription = Inscription::with(['classe', 'etablissement.institution', 'anneeScolaire'])
            ->where('eleve_id', $eleve->id)
            ->where('statut', 'validee')
            ->latest('annee_scolaire_id')
            ->first();

        return view('cartes.verification_publique', [
            'valide' => (bool) $inscription,
            'message' => $inscription 
                ? "Carte scolaire valide. Élève régulièrement inscrit(e)." 
                : "Alerte : L'élève est enregistré mais n'a pas d'inscription active pour l'année scolaire en cours.",
            'eleve' => $eleve,
            'inscription' => $inscription,
        ]);
    }
}
