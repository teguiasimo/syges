<?php

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\Etablissement;
use App\Models\Inscription;
use App\Models\Institution;
use App\Models\User;
use App\Services\QrCodeService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
            new Middleware('can:cartes_scolaires.generate|eleves.manage', only: ['imprimerPlanche', 'imprimerUnitaire']),
        ];
    }

    /**
     * Affiche l'interface de gestion et prévisualisation des cartes scolaires.
     */
    public function index(Request $request): InertiaResponse
    {
        /** @var User|null $user */
        $user = Auth::user();
        $canAccessAll = $user ? $user->canAccessAllEtablissements() : false;
        $effectiveEtabId = $user?->getEffectiveEtablissementId();

        // Année scolaire active ou sélectionnée
        $anneeActive = AnneeScolaire::where('is_current', true)->first() ?? AnneeScolaire::latest()->first();
        $selectedAnneeId = (int) $request->input('annee_scolaire_id', $anneeActive?->id);

        // Récupération des établissements selon permissions
        $etablissementsQuery = Etablissement::with([
            'sections.cycles.niveaux.classes',
            'institution',
        ]);

        if (! $canAccessAll && $effectiveEtabId) {
            $etablissementsQuery->where('id', $effectiveEtabId);
        }

        $etablissements = $etablissementsQuery->get();
        $selectedEtabId = (int) ($request->input('etablissement_id') ?: ($request->session()->get('current_etablissement_id') ?: $etablissements->first()?->id));

        // Filtres hiérarchiques
        $classeId = $request->input('classe_id');
        $hasPhoto = $request->input('has_photo'); // 'all', 'with_photo', 'without_photo'
        $search = $request->input('search');

        // Récupération optimisée en une seule jointure SQL et projection légère (élimine la sérialisation
        // redondante des 20 accesseurs Etablissement/Institution sur chaque élève, réduisant le payload de >1 Mo à ~95 Ko)
        $anneeSelectionnee = AnneeScolaire::find($selectedAnneeId);
        $anneePayload = $anneeSelectionnee ? [
            'id' => $anneeSelectionnee->id,
            'nom' => $anneeSelectionnee->nom,
            'code' => $anneeSelectionnee->code,
        ] : null;

        $inscriptionsQuery = DB::table('inscriptions')
            ->join('eleves', 'eleves.id', '=', 'inscriptions.eleve_id')
            ->join('classes', 'classes.id', '=', 'inscriptions.classe_id')
            ->leftJoin('niveaux', 'niveaux.id', '=', 'classes.niveau_id')
            ->select([
                'inscriptions.id',
                'inscriptions.eleve_id',
                'inscriptions.classe_id',
                'inscriptions.etablissement_id',
                'inscriptions.annee_scolaire_id',
                'inscriptions.statut',
                'eleves.matricule',
                'eleves.nom as eleve_nom',
                'eleves.prenom as eleve_prenom',
                'eleves.sexe as eleve_sexe',
                'eleves.date_naissance as eleve_date_naissance',
                'eleves.lieu_naissance as eleve_lieu_naissance',
                'eleves.photo_url as eleve_photo_url',
                'classes.nom as classe_nom',
                'classes.code as classe_code',
                'niveaux.id as niveau_id',
                'niveaux.nom as niveau_nom',
            ])
            ->where('inscriptions.annee_scolaire_id', $selectedAnneeId)
            ->where('inscriptions.statut', 'validee');

        if ($selectedEtabId) {
            $inscriptionsQuery->where('inscriptions.etablissement_id', $selectedEtabId);
        }

        if ($classeId) {
            $inscriptionsQuery->where('inscriptions.classe_id', $classeId);
        }

        if ($hasPhoto === 'with_photo') {
            $inscriptionsQuery->whereNotNull('eleves.photo_url')->where('eleves.photo_url', '!=', '');
        } elseif ($hasPhoto === 'without_photo') {
            $inscriptionsQuery->where(function ($q) {
                $q->whereNull('eleves.photo_url')->orWhere('eleves.photo_url', '');
            });
        }

        if ($search) {
            $like = "%{$search}%";
            $inscriptionsQuery->where(function ($q) use ($like) {
                $q->where('eleves.nom', 'like', $like)
                    ->orWhere('eleves.prenom', 'like', $like)
                    ->orWhere('eleves.matricule', 'like', $like);
            });
        }

        $rawInscriptions = $inscriptionsQuery
            ->orderBy('inscriptions.classe_id')
            ->orderBy('eleves.nom')
            ->orderBy('eleves.prenom')
            ->get();

        $totalInscrits = $rawInscriptions->count();
        $totalAvecPhoto = 0;

        $inscriptions = [];
        foreach ($rawInscriptions as $row) {
            if (! empty($row->eleve_photo_url)) {
                $totalAvecPhoto++;
            }

            $inscriptions[] = [
                'id' => (int) $row->id,
                'eleve_id' => (int) $row->eleve_id,
                'classe_id' => (int) $row->classe_id,
                'etablissement_id' => $row->etablissement_id ? (int) $row->etablissement_id : null,
                'annee_scolaire_id' => (int) $row->annee_scolaire_id,
                'statut' => (string) $row->statut,
                'eleve' => [
                    'id' => (int) $row->eleve_id,
                    'matricule' => (string) $row->matricule,
                    'nom' => (string) $row->eleve_nom,
                    'prenom' => $row->eleve_prenom,
                    'nom_complet' => trim(((string) $row->eleve_nom).' '.((string) ($row->eleve_prenom ?? ''))),
                    'sexe' => $row->eleve_sexe,
                    'date_naissance' => $row->eleve_date_naissance,
                    'lieu_naissance' => $row->eleve_lieu_naissance,
                    'photo_url' => $row->eleve_photo_url,
                ],
                'classe' => [
                    'id' => (int) $row->classe_id,
                    'nom' => (string) $row->classe_nom,
                    'code' => $row->classe_code,
                    'niveau' => $row->niveau_id ? [
                        'id' => (int) $row->niveau_id,
                        'nom' => (string) $row->niveau_nom,
                    ] : null,
                ],
                'annee_scolaire' => $anneePayload,
            ];
        }

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
     */
    public function imprimerPlanche(Request $request, QrCodeService $qrCodeService): Response
    {
        $validated = $request->validate([
            'inscription_ids' => ['required', 'array', 'min:1', 'max:200'],
            'inscription_ids.*' => ['integer', 'exists:inscriptions,id'],
            'face' => ['required', 'string', 'in:recto,verso,recto_verso'],
        ]);

        /** @var User|null $user */
        $user = Auth::user();
        $canAccessAll = $user ? $user->canAccessAllEtablissements() : false;
        $effectiveEtabId = $user?->getEffectiveEtablissementId();

        $inscriptionsQuery = Inscription::with([
            'eleve',
            'classe.niveau.cycle.section',
            'etablissement.institution',
            'anneeScolaire',
        ])->whereIn('id', $validated['inscription_ids']);

        if (! $canAccessAll && $effectiveEtabId) {
            $inscriptionsQuery->where('etablissement_id', $effectiveEtabId);
        }

        $inscriptions = $inscriptionsQuery->get();

        if ($inscriptions->isEmpty()) {
            abort(404, 'Aucune inscription sélectionnée pour la génération des cartes.');
        }

        // Préparation unifiée des données de chaque carte avec QR Code et images Base64
        $cartes = $inscriptions->map(fn ($insc) => $this->preparerDonneesCarte($insc, $qrCodeService));

        // Découpage en planches de 10 cartes par feuille A4 portrait (2 colonnes x 5 lignes)
        $planches = $cartes->chunk(10);

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
            ->setOption('isRemoteEnabled', false);

        $filename = "Cartes_Scolaires_{$face}_".date('Ymd_His').'.pdf';

        return $pdf->stream($filename);
    }

    /**
     * Génère et télécharge le badge unitaire au format standard ISO CR-80 (85.6mm x 54mm).
     */
    public function imprimerUnitaire(Inscription $inscription, QrCodeService $qrCodeService): Response
    {
        /** @var User|null $user */
        $user = Auth::user();
        $canAccessAll = $user ? $user->canAccessAllEtablissements() : false;
        $effectiveEtabId = $user?->getEffectiveEtablissementId();
        if (! $canAccessAll && $effectiveEtabId && (int) $inscription->etablissement_id !== (int) $effectiveEtabId) {
            abort(403, 'Accès non autorisé : cet élève appartient à un autre établissement.');
        }

        $inscription->load([
            'eleve',
            'classe.niveau.cycle.section',
            'etablissement.institution',
            'anneeScolaire',
        ]);

        $carte = $this->preparerDonneesCarte($inscription, $qrCodeService);

        // Format CR-80 en points (85.6mm = 243pt, 53.98mm = 153pt)
        $customPaper = [0, 0, 243, 153];

        $pdf = Pdf::loadView('cartes.badge_unitaire', compact('carte'))
            ->setPaper($customPaper, 'landscape')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', false);

        return $pdf->stream("Badge_{$carte['matricule']}.pdf");
    }

    /**
     * Prépare et normalise l'ensemble des données d'une carte scolaire avec conversions Base64 fiables.
     *
     * @return array<string, mixed>
     */
    protected function preparerDonneesCarte(Inscription $inscription, QrCodeService $qrCodeService): array
    {
        $eleve = $inscription->eleve;
        $etab = $inscription->etablissement;
        $inst = $etab?->institution;

        // URL publique absolue pour le contrôle d'accès au portail
        $verificationUrl = route('cartes-scolaires.verifier', ['matricule' => $eleve->matricule]);

        // Génération du QR Code vectoriel en Data URI Base64
        $qrCodeBase64 = $qrCodeService->generateBase64Svg($verificationUrl, 120);

        // Nettoyage de l'année scolaire pour éviter le doublon d'intitulé "Année Scolaire Année Scolaire"
        $anneeNomBrut = $inscription->anneeScolaire?->nom ?? ($inscription->anneeScolaire?->code ?? '');
        $anneeNom = trim(preg_replace('/^Ann[eé]e\s+Scolaire\s*/iu', '', $anneeNomBrut));

        // Résolution sécurisée (anti-LFI) du logo et de la photo d'identité en Data URI Base64
        $logoBase64 = $etab?->getEffectiveLogoBase64();
        $logoUrl = $etab?->logo_url ?: ($etab?->effective_logo ?: ($etab?->logo ?: $inst?->logo));
        if (! $logoBase64 && ! empty($logoUrl)) {
            $logoBase64 = Institution::resolveImageBase64($logoUrl);
        }
        if (! $logoBase64) {
            $logoBase64 = Institution::resolveImageBase64('/logo.png');
        }

        // Résolution sécurisée de la photo d'identité en Data URI Base64
        $photoBase64 = ! empty($eleve->photo_url)
            ? Institution::resolveImageBase64($eleve->photo_url)
            : null;

        return [
            'inscription_id' => $inscription->id,
            'matricule' => $eleve->matricule,
            'matricule_national' => $eleve->matricule_national,
            'nom_complet' => mb_strtoupper($eleve->nom).' '.($eleve->prenom ?? ''),
            'date_naissance' => $eleve->date_naissance ? $eleve->date_naissance->format('d-m-Y') : '---',
            'lieu_naissance' => $eleve->lieu_naissance ?: '---',
            'sexe' => $eleve->sexe ?: '--',
            'classe_nom' => $inscription->classe?->nom ?? '---',
            'photo_url' => $eleve->photo_url,
            'photo_base64' => $photoBase64,
            'annee_nom' => $anneeNom,
            'telephone_direction' => $etab->telephone_1 ?: ($inst?->telephone_1 ?: '---'),
            'etablissement_nom' => $etab->nom,
            'etablissement_nom_en' => $etab->nom_en ?: $etab->nom,
            'adresse' => $etab->adresse ?: ($inst?->ville ?: 'Cameroun'),
            'boite_postale' => $inst?->boite_postale ?: '---',
            'devise' => $etab->devise ?: ($inst?->devise ?: 'Discipline - Travail - Succès'),
            'delegation_regionale' => $etab->delegation_regionale ?: ($inst?->delegation_regionale ?: 'DÉLÉGATION RÉGIONALE DU CENTRE'),
            'delegation_regionale_en' => $etab->delegation_regionale_en ?: ($inst?->delegation_regionale_en ?: 'REGIONAL DELEGATION FOR THE CENTRE'),
            'delegation_departementale' => $etab->delegation_departementale ?: ($inst?->delegation_departementale ?: 'DÉLÉGATION DÉPARTEMENTALE DU MFOUNDI'),
            'delegation_departementale_en' => $etab->delegation_departementale_en ?: ($inst?->delegation_departementale_en ?: 'DIVISIONAL DELEGATION FOR MFOUNDI'),
            'nom_chef' => $etab->nom_chef_etablissement ?: $etab->titre_chef,
            'titre_chef' => $etab->titre_chef ?: "Le Chef d'Établissement",
            'logo_url' => $logoUrl,
            'logo_base64' => $logoBase64,
            'qr_code' => $qrCodeBase64,
            'groupe_sanguin' => 'N/A',
            'contact_urgence' => $eleve->telephone_parent ?: '---',
        ];
    }

    /**
     * Page publique de contrôle d'authenticité accessible lors du scan du QR Code par smartphone.
     *
     * @return View
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
                ? 'Carte scolaire valide. Élève régulièrement inscrit(e).'
                : "Alerte : L'élève est enregistré mais n'a pas d'inscription active pour l'année scolaire en cours.",
            'eleve' => $eleve,
            'inscription' => $inscription,
        ]);
    }
}
