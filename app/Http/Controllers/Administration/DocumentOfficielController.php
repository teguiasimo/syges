<?php

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\ConfigurationNumerotation;
use App\Models\Etablissement;
use App\Models\Evaluation;
use App\Models\Inscription;
use App\Models\Niveau;
use App\Models\Note;
use App\Models\Periode;
use App\Models\Personnel;
use App\Services\BulletinCalculationService;
use App\Services\QrCodeService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/**
 * Contrôleur de la Bibliothèque d'États Imprimables & Documents Officiels (Aurore §6.1 & Gesco V1.1).
 * 
 * Génère les documents administratifs normalisés infalsifiables :
 * 1. Certificats de scolarité officiels avec QR Code d'authenticité.
 * 2. Certificats de radiation / transfert avec quitus financier.
 * 3. Fiches de babillard d'affichage public des notes par classe.
 * 4. Fiches de situation financière individuelle globale de l'élève.
 */
class DocumentOfficielController extends Controller
{
    /**
     * Génère et télécharge le Certificat de Scolarité officiel (Aurore §6.1).
     *
     * @param Request $request
     * @param Inscription $inscription
     * @param QrCodeService $qrCodeService
     * @return Response
     */
    public function certificatScolarite(
        Request $request,
        Inscription $inscription,
        QrCodeService $qrCodeService
    ): Response {
        $inscription->load([
            'eleve',
            'classe',
            'anneeScolaire',
            'etablissement',
        ]);

        $etablissement = $inscription->etablissement ?? Auth::user()->etablissement;
        $etablissement?->loadMissing('institution');
        $eleve = $inscription->eleve;

        // Génération du numéro de certificat selon le masque configuré
        $numeroCertificat = ConfigurationNumerotation::genererNumero(
            $etablissement->id,
            'certificat_scolarite',
            date('Y')
        );

        // Données d'intégrité pour le QR Code anti-fraude
        $qrData = sprintf(
            "SYGES-CERT-SCO|ETAB:%d|MAT:%s|NOM:%s|CLASSE:%s|ANNEE:%s|NUM:%s",
            $etablissement->id,
            $eleve->matricule,
            $eleve->nom,
            $inscription->classe->nom,
            $inscription->anneeScolaire->code,
            $numeroCertificat
        );
        $qrCodeBase64 = $qrCodeService->generateBase64Svg($qrData, 90);

        $pdf = Pdf::loadView('documents.certificat_scolarite', [
            'inscription' => $inscription,
            'eleve' => $eleve,
            'classe' => $inscription->classe,
            'annee' => $inscription->anneeScolaire,
            'etablissement' => $etablissement,
            'numeroCertificat' => $numeroCertificat,
            'qrCodeBase64' => $qrCodeBase64,
            // Formatage de la date de délivrance au standard jj-mm-aaaa
            'dateDelivrance' => now()->format('d-m-Y'),
        ])->setPaper('a4', 'portrait');

        $fileName = sprintf(
            'Certificat_Scolarite_%s_%s.pdf',
            $eleve->matricule,
            now()->format('Ymd')
        );

        return $pdf->stream($fileName);
    }

    /**
     * Génère et télécharge le Certificat de Radiation / Transfert officiel (Aurore §6.1).
     *
     * @param Request $request
     * @param Inscription $inscription
     * @param QrCodeService $qrCodeService
     * @return Response
     */
    public function certificatRadiation(
        Request $request,
        Inscription $inscription,
        QrCodeService $qrCodeService
    ): Response {
        $inscription->load([
            'eleve',
            'classe',
            'anneeScolaire',
            'etablissement',
            'compteFinancier',
        ]);

        $etablissement = $inscription->etablissement ?? Auth::user()->etablissement;
        $etablissement?->loadMissing('institution');
        $eleve = $inscription->eleve;

        // Numéro de certificat de radiation
        $numeroRadiation = ConfigurationNumerotation::genererNumero(
            $etablissement->id,
            'certificat_radiation',
            date('Y')
        );

        // Données d'intégrité pour le QR Code
        $qrData = sprintf(
            "SYGES-CERT-RAD|ETAB:%d|MAT:%s|NOM:%s|NUM:%s",
            $etablissement->id,
            $eleve->matricule,
            $eleve->nom,
            $numeroRadiation
        );
        $qrCodeBase64 = $qrCodeService->generateBase64Svg($qrData, 90);

        // Statut de solvabilité pour le quitus
        $solde = (float) ($inscription->compteFinancier?->solde ?? 0);
        $estSolvable = $solde <= 0;

        $pdf = Pdf::loadView('documents.certificat_radiation', [
            'inscription' => $inscription,
            'eleve' => $eleve,
            'classe' => $inscription->classe,
            'annee' => $inscription->anneeScolaire,
            'etablissement' => $etablissement,
            'numeroRadiation' => $numeroRadiation,
            'qrCodeBase64' => $qrCodeBase64,
            'estSolvable' => $estSolvable,
            'solde' => $solde,
            'destination' => $request->input('destination', 'Tout établissement sollicité'),
            'motif' => $request->input('motif', $inscription->motif_sortie ?? 'Changement de résidence'),
            'dateRadiation' => now()->format('d/m/Y'),
        ])->setPaper('a4', 'portrait');

        $fileName = sprintf(
            'Certificat_Radiation_%s_%s.pdf',
            $eleve->matricule,
            now()->format('Ymd')
        );

        return $pdf->stream($fileName);
    }

    /**
     * Génère et télécharge la Fiche Officielle de Babillard des Notes (Aurore §6.1).
     *
     * @param Request $request
     * @param Classe $classe
     * @param Periode|null $periode
     * @param BulletinCalculationService $calculationService
     * @return Response
     */
    public function babillard(
        Request $request,
        Classe $classe,
        ?Periode $periode = null,
        ?BulletinCalculationService $calculationService = null
    ): Response {
        $etablissement = Auth::user()?->etablissement ?? Etablissement::first();
        $etablissement?->loadMissing('institution');
        $classe->load('niveau');

        // Récupération des inscriptions actives de la classe
        $inscriptions = Inscription::with('eleve')
            ->where('classe_id', $classe->id)
            ->where('statut', 'validee')
            ->orderBy('id')
            ->get();

        // Calcul ou extraction des moyennes par élève
        $resultats = [];
        foreach ($inscriptions as $insc) {
            // Moyenne calculée sur les notes de la période
            $notesQuery = Note::where('inscription_id', $insc->id);
            if ($periode) {
                $evalIds = Evaluation::where('periode_id', $periode->id)->pluck('id');
                $notesQuery->whereIn('evaluation_id', $evalIds);
            }
            $notes = $notesQuery->get();

            $totalPoints = 0;
            $totalCoeff = 0;
            foreach ($notes as $n) {
                $totalPoints += (float) ($n->note * $n->coefficient);
                $totalCoeff += (float) $n->coefficient;
            }

            $moyenne = $totalCoeff > 0 ? round($totalPoints / $totalCoeff, 2) : 0;

            $resultats[] = [
                'matricule' => $insc->eleve->matricule,
                'nom' => $insc->eleve->nom,
                'prenom' => $insc->eleve->prenom,
                'sexe' => $insc->eleve->sexe,
                'moyenne' => $moyenne,
                'total_points' => $totalPoints,
                'mention' => self::getMention($moyenne),
            ];
        }

        // Tri décroissant pour classement
        usort($resultats, fn($a, $b) => $b['moyenne'] <=> $a['moyenne']);

        // Attribution des rangs
        foreach ($resultats as $idx => &$res) {
            $res['rang'] = $idx + 1;
        }
        unset($res);

        // Statistiques globales de classe
        $nbEleves = count($resultats);
        $nbAdmis = count(array_filter($resultats, fn($r) => $r['moyenne'] >= 10));
        $tauxReussite = $nbEleves > 0 ? round(($nbAdmis / $nbEleves) * 100, 1) : 0;
        $moyennesList = array_column($resultats, 'moyenne');
        $moyenneClasse = $nbEleves > 0 ? round(array_sum($moyennesList) / $nbEleves, 2) : 0;
        $maxMoyenne = !empty($moyennesList) ? max($moyennesList) : 0;
        $minMoyenne = !empty($moyennesList) ? min($moyennesList) : 0;

        $pdf = Pdf::loadView('documents.babillard_classe', [
            'classe' => $classe,
            'periode' => $periode,
            'etablissement' => $etablissement,
            'resultats' => $resultats,
            'stats' => [
                'nb_eleves' => $nbEleves,
                'nb_admis' => $nbAdmis,
                'taux_reussite' => $tauxReussite,
                'moyenne_classe' => $moyenneClasse,
                'max_moyenne' => $maxMoyenne,
                'min_moyenne' => $minMoyenne,
            ],
            'dateGeneration' => now()->format('d/m/Y H:i'),
        ])->setPaper('a4', 'landscape');

        $fileName = sprintf(
            'Babillard_%s_%s.pdf',
            $classe->code ?? $classe->nom,
            now()->format('Ymd')
        );

        return $pdf->stream($fileName);
    }

    /**
     * Génère et télécharge la Fiche de Situation Financière Individuelle de l'élève (Aurore §6.1).
     *
     * @param Request $request
     * @param Inscription $inscription
     * @return Response
     */
    public function situationFinanciereEleve(Request $request, Inscription $inscription): Response
    {
        $inscription->load([
            'eleve',
            'classe',
            'anneeScolaire',
            'etablissement',
            'compteFinancier.versements.caisse',
            'compteFinancier.versements.modePaiement',
            'compteFinancier.boursesRemises.bourseRemise',
            'compteFinancier.penalites.penaliteConfiguration',
        ]);

        $compte = $inscription->compteFinancier;
        if (!$compte) {
            /** @var \App\Services\FinanceService $financeService */
            $financeService = app(\App\Services\FinanceService::class);
            $compte = $financeService->initialiserCompteEleve($inscription);
            $compte->load([
                'versements.caisse',
                'versements.modePaiement',
                'boursesRemises.bourseRemise',
                'penalites.penaliteConfiguration',
            ]);
        }

        $eleve = $inscription->eleve;
        $etablissement = $inscription->etablissement ?? Auth::user()->etablissement;
        $etablissement?->loadMissing('institution');

        // Actualisation du solde net
        $compte?->actualiserSolde();

        $pdf = Pdf::loadView('documents.fiche_financiere_eleve', [
            'inscription' => $inscription,
            'eleve' => $eleve,
            'compte' => $compte,
            'classe' => $inscription->classe,
            'annee' => $inscription->anneeScolaire,
            'etablissement' => $etablissement,
            'dateEdition' => now()->format('d-m-Y H:i'),
        ])->setPaper('a4', 'portrait');

        $fileName = sprintf(
            'Situation_Financiere_%s_%s.pdf',
            $eleve->matricule,
            now()->format('Ymd')
        );

        return $pdf->stream($fileName);
    }

    /**
     * Renvoie la mention officielle selon la moyenne obtenue (/20).
     *
     * @param float $moyenne
     * @return string
     */
    private static function getMention(float $moyenne): string
    {
        return match (true) {
            $moyenne >= 18 => 'Excellent',
            $moyenne >= 16 => 'Très Bien',
            $moyenne >= 14 => 'Bien',
            $moyenne >= 12 => 'Assez Bien',
            $moyenne >= 10 => 'Passable',
            $moyenne >= 8 => 'Insuffisant',
            $moyenne >= 6 => 'Faible',
            default => 'Très Faible',
        };
    }

    /**
     * Génère et télécharge la Fiche d'Appel officielle de la classe (A4 Paysage).
     *
     * @param Request $request
     * @param Classe $classe
     * @return Response
     */
    public function ficheAppel(Request $request, Classe $classe): Response
    {
        $classe->load([
            'etablissement',
            'niveau.cycle.section',
            'professeurPrincipal',
        ]);

        $etablissement = $classe->etablissement ?? Auth::user()->etablissement;
        $etablissement?->loadMissing('institution');
        $anneeId = $request->input('annee_id');

        $inscriptionsQuery = Inscription::with('eleve')
            ->where('classe_id', $classe->id)
            ->whereIn('statut', ['validee', 'confirmee', 'active', 'en_attente']);

        if ($anneeId) {
            $inscriptionsQuery->where('annee_scolaire_id', $anneeId);
        }

        $inscriptions = $inscriptionsQuery->get()
            ->sortBy(fn($i) => mb_strtoupper($i->eleve->nom . ' ' . $i->eleve->prenom))
            ->values();

        $annee = $anneeId ? AnneeScolaire::find($anneeId) : ($classe->inscriptions()->latest()->first()?->anneeScolaire ?? AnneeScolaire::where('is_current', true)->first());

        $pdf = Pdf::loadView('documents.fiche_appel', [
            'classe' => $classe,
            'etablissement' => $etablissement,
            'annee' => $annee,
            'inscriptions' => $inscriptions,
            'mois' => $request->input('mois', now()->translatedFormat('F Y')),
            'dateGeneration' => now()->format('d-m-Y'),
        ])->setPaper('a4', 'landscape');

        $fileName = sprintf(
            'Fiche_Appel_%s_%s.pdf',
            $classe->code ?? $classe->nom,
            now()->format('Ymd')
        );

        return $pdf->stream($fileName);
    }

    /**
     * Génère et télécharge la Liste des Élèves de la classe (Provisoire / Définitive) (A4 Portrait).
     *
     * @param Request $request
     * @param Classe $classe
     * @return Response
     */
    public function listeEleves(Request $request, Classe $classe): Response
    {
        $classe->load([
            'etablissement',
            'niveau.cycle.section',
            'professeurPrincipal',
        ]);

        $etablissement = $classe->etablissement ?? Auth::user()->etablissement;
        $etablissement?->loadMissing('institution');
        $type = $request->input('type', 'definitive');
        $anneeId = $request->input('annee_id');

        $inscriptionsQuery = Inscription::with(['eleve', 'anneeScolaire'])
            ->where('classe_id', $classe->id);

        if ($type === 'definitive') {
            $inscriptionsQuery->whereIn('statut', ['validee', 'confirmee', 'active']);
        }

        if ($anneeId) {
            $inscriptionsQuery->where('annee_scolaire_id', $anneeId);
        }

        $inscriptions = $inscriptionsQuery->get()
            ->sortBy(fn($i) => mb_strtoupper($i->eleve->nom . ' ' . $i->eleve->prenom))
            ->values();

        $annee = $anneeId ? AnneeScolaire::find($anneeId) : ($classe->inscriptions()->latest()->first()?->anneeScolaire ?? AnneeScolaire::where('is_current', true)->first());

        $total = $inscriptions->count();
        $filles = $inscriptions->filter(fn($i) => $i->eleve->sexe === 'F')->count();
        $garcons = $inscriptions->filter(fn($i) => $i->eleve->sexe === 'M')->count();
        $nouveaux = $inscriptions->filter(fn($i) => !$i->is_redoublant)->count();
        $redoublants = $inscriptions->filter(fn($i) => (bool) $i->is_redoublant)->count();

        $pdf = Pdf::loadView('documents.liste_eleves', [
            'classe' => $classe,
            'etablissement' => $etablissement,
            'annee' => $annee,
            'inscriptions' => $inscriptions,
            'type' => $type,
            'stats' => [
                'total' => $total,
                'filles' => $filles,
                'garcons' => $garcons,
                'nouveaux' => $nouveaux,
                'redoublants' => $redoublants,
                'taux_filles' => $total > 0 ? round(($filles / $total) * 100, 1) : 0,
            ],
            'dateGeneration' => now()->format('d-m-Y'),
        ])->setPaper('a4', 'portrait');

        $fileName = sprintf(
            'Liste_Eleves_%s_%s_%s.pdf',
            ucfirst($type),
            $classe->code ?? $classe->nom,
            now()->format('Ymd')
        );

        return $pdf->stream($fileName);
    }

    /**
     * Génère et télécharge la Liste Officielle des Élèves pour l'ensemble d'un Niveau scolaire (A4 Portrait).
     *
     * @param Request $request Requête HTTP avec filtres type (definitive/provisoire) et annee_id.
     * @param Niveau $niveau Niveau scolaire sélectionné (ex: 6e, 5e, 4e, 3e).
     * @return Response Document PDF en flux de téléchargement.
     */
    public function listeElevesNiveau(Request $request, Niveau $niveau): Response
    {
        $niveau->load([
            'cycle.section',
            'classes' => fn ($q) => $q->orderBy('nom'),
        ]);

        $etabId = $request->input('etablissement_id');
        $etablissement = $etabId
            ? Etablissement::find($etabId)
            : ($niveau->etablissement ?? (Auth::user()?->etablissement ?? Etablissement::first()));
        $etablissement?->loadMissing('institution');

        $type = $request->input('type', 'definitive');
        $anneeId = $request->input('annee_id');

        $classeIds = $niveau->classes->pluck('id');

        $inscriptionsQuery = Inscription::with(['eleve', 'classe', 'anneeScolaire'])
            ->whereIn('classe_id', $classeIds);

        if ($type === 'definitive') {
            $inscriptionsQuery->whereIn('statut', ['validee', 'confirmee', 'active']);
        }

        if ($anneeId) {
            $inscriptionsQuery->where('annee_scolaire_id', $anneeId);
        }

        $inscriptions = $inscriptionsQuery->get()
            ->sortBy(function ($i) {
                return ($i->classe?->nom ?? '') . ' ' . mb_strtoupper(($i->eleve?->nom ?? '') . ' ' . ($i->eleve?->prenom ?? ''));
            })
            ->values();

        $annee = $anneeId
            ? AnneeScolaire::find($anneeId)
            : AnneeScolaire::where('is_current', true)->first();

        $total = $inscriptions->count();
        $filles = $inscriptions->filter(fn ($i) => $i->eleve?->sexe === 'F')->count();
        $garcons = $inscriptions->filter(fn ($i) => $i->eleve?->sexe === 'M')->count();
        $nouveaux = $inscriptions->filter(fn ($i) => ! $i->is_redoublant)->count();
        $redoublants = $inscriptions->filter(fn ($i) => (bool) $i->is_redoublant)->count();

        // Répartition par classe du niveau
        $classesStats = $niveau->classes->map(function ($cls) use ($inscriptions) {
            $count = $inscriptions->where('classe_id', $cls->id)->count();
            return [
                'nom' => $cls->nom,
                'total' => $count,
            ];
        });

        $pdf = Pdf::loadView('documents.liste_eleves_niveau', [
            'niveau' => $niveau,
            'etablissement' => $etablissement,
            'annee' => $annee,
            'inscriptions' => $inscriptions,
            'type' => $type,
            'classesStats' => $classesStats,
            'stats' => [
                'total' => $total,
                'filles' => $filles,
                'garcons' => $garcons,
                'nouveaux' => $nouveaux,
                'redoublants' => $redoublants,
                'taux_filles' => $total > 0 ? round(($filles / $total) * 100, 1) : 0,
            ],
            'dateGeneration' => now()->format('d-m-Y'),
        ])->setPaper('a4', 'portrait');

        $fileName = sprintf(
            'Liste_Eleves_Niveau_%s_%s_%s.pdf',
            ucfirst($type),
            \Illuminate\Support\Str::slug($niveau->nom),
            now()->format('Ymd')
        );

        return $pdf->stream($fileName);
    }

    /**
     * Génère et télécharge la Liste Officielle des Classes de l'Établissement (A4 Portrait).
     *
     * @param Request $request
     * @return Response
     */
    public function listeClasses(Request $request): Response
    {
        $etabId = $request->input('etablissement_id');
        $etablissement = $etabId ? Etablissement::find($etabId) : (Auth::user()?->etablissement ?? Etablissement::first());
        $etablissement?->loadMissing('institution');
        $anneeId = $request->input('annee_id');
        $annee = $anneeId ? AnneeScolaire::find($anneeId) : AnneeScolaire::where('is_current', true)->first();

        $classesQuery = Classe::query();
        if ($etablissement) {
            $classesQuery->where('etablissement_id', $etablissement->id);
        }

        $classes = $classesQuery
            ->with([
                'niveau.cycle.section',
                'professeurPrincipal',
                'inscriptions' => function ($q) use ($annee) {
                    if ($annee) {
                        $q->where('annee_scolaire_id', $annee->id);
                    }
                    $q->whereIn('statut', ['validee', 'confirmee', 'active']);
                },
                'inscriptions.eleve',
            ])
            ->orderBy('niveau_id')
            ->orderBy('nom')
            ->get();

        $pdf = Pdf::loadView('documents.liste_classes', [
            'etablissement' => $etablissement,
            'annee' => $annee,
            'classes' => $classes,
            'dateGeneration' => now()->format('d-m-Y'),
        ])->setPaper('a4', 'portrait');

        $fileName = sprintf(
            'Liste_Classes_%s_%s.pdf',
            $etablissement?->sigle ?? 'Etab',
            now()->format('Ymd')
        );

        return $pdf->stream($fileName);
    }

    /**
     * Génère et télécharge la Liste Officielle du Personnel de l'Établissement (A4 Paysage).
     *
     * @param Request $request Requête HTTP contenant optionnellement un etablissement_id.
     * @return Response Fichier PDF streamé.
     */
    public function listePersonnel(Request $request): Response
    {
        $etabId = $request->input('etablissement_id');
        $etablissement = $etabId ? Etablissement::find($etabId) : (Auth::user()?->etablissement ?? Etablissement::first());
        $etablissement?->loadMissing('institution');
        $annee = AnneeScolaire::where('is_current', true)->first();

        $personnelsQuery = Personnel::query();
        if ($etablissement) {
            $personnelsQuery->where('etablissement_id', $etablissement->id);
        }

        $personnels = $personnelsQuery
            ->with(['departement', 'user'])
            ->orderBy('nom')
            ->orderBy('prenom')
            ->get();

        $total = $personnels->count();
        $hommes = $personnels->filter(fn($p) => $p->sexe === 'M')->count();
        $femmes = $personnels->filter(fn($p) => $p->sexe === 'F')->count();
        $enseignants = $personnels->filter(fn($p) => (bool) $p->is_enseignant)->count();
        $administratifs = $total - $enseignants;

        $pdf = Pdf::loadView('documents.liste_personnel', [
            'etablissement' => $etablissement,
            'annee' => $annee,
            'personnels' => $personnels,
            'stats' => [
                'total' => $total,
                'hommes' => $hommes,
                'femmes' => $femmes,
                'enseignants' => $enseignants,
                'administratifs' => $administratifs,
            ],
            'dateGeneration' => now()->format('d-m-Y'),
        ])->setPaper('a4', 'landscape');

        $fileName = sprintf(
            'Liste_Personnel_%s_%s.pdf',
            $etablissement?->sigle ?? 'Etab',
            now()->format('Ymd')
        );

        return $pdf->stream($fileName);
    }
}

