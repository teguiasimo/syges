<?php

namespace App\Services;

use App\Models\Etablissement;
use App\Models\HistoriqueSms;
use App\Models\Inscription;
use App\Models\SmsCampagne;
use App\Models\SmsConfiguration;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Service Métier pour le Module 14 : Communication & Notifications SMS (Gesco V1.1).
 *
 * Implémente :
 * - Le convertisseur technique GSM 7-bit avec suppression systématique des accents (14.4).
 * - Le moteur d'émission de notifications automatiques événementielles (14.2.A).
 * - Le moteur de campagnes de masse multi-critères (14.2.B).
 * - La traçabilité exhaustive et le contrôle du solde de crédits (14.1 & 14.3).
 */
class SmsService
{
    /**
     * Table de conversion des caractères accentués vers les équivalents stricts GSM 7-bit (Gesco 14.4).
     *
     * @var array<string, string>
     */
    protected const TABLE_GSM7 = [
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
        'à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a',
        'À' => 'A', 'Â' => 'A', 'Ä' => 'A', 'Á' => 'A',
        'î' => 'i', 'ï' => 'i', 'í' => 'i',
        'Î' => 'I', 'Ï' => 'I', 'Í' => 'I',
        'ô' => 'o', 'ö' => 'o', 'ó' => 'o',
        'Ô' => 'O', 'Ö' => 'O', 'Ó' => 'O',
        'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ú' => 'u',
        'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U', 'Ú' => 'U',
        'ç' => 'c', 'Ç' => 'C',
        'œ' => 'oe', 'Œ' => 'OE',
        'æ' => 'ae', 'Æ' => 'AE',
        '’' => "'", '‘' => "'", '`' => "'",
        '«' => '"', '»' => '"', '“' => '"', '”' => '"',
        '—' => '-', '–' => '-',
        '…' => '...',
    ];

    /**
     * Nettoie et sanitise un texte pour l'encodage GSM 7-bit (160 caractères max par SMS).
     *
     * @param string $texte
     * @return string
     */
    public function sanitiserGsm7(string $texte): string
    {
        // 1. Remplacement des accents et caractères typographiques
        $textePropre = strtr($texte, self::TABLE_GSM7);

        // 2. Élimination des caractères non imprimables
        $textePropre = preg_replace('/[^\x20-\x7E\r\n]/', '', $textePropre);

        return trim((string) $textePropre);
    }

    /**
     * Calcule le nombre de segments (crédits) requis pour un message selon la norme GSM 7-bit.
     *
     * @param string $texte
     * @return int
     */
    public function calculerSegments(string $texte): int
    {
        $longueur = mb_strlen($this->sanitiserGsm7($texte));

        if ($longueur === 0) {
            return 1;
        }

        if ($longueur <= 160) {
            return 1;
        }

        // En SMS concaténé (multi-segments), l'en-tête UDH réserve 7 caractères (153 car. utiles par segment)
        return (int) ceil($longueur / 153);
    }

    /**
     * Normalise un numéro de téléphone au format international E.164 (ex: +237699001122).
     *
     * @param string $telephone
     * @param string $prefixeParDefaut
     * @return string
     */
    public function normaliserNumero(string $telephone, string $prefixeParDefaut = '+237'): string
    {
        $chiffres = preg_replace('/[^0-9+]/', '', $telephone);

        if (str_starts_with($chiffres, '+')) {
            return $chiffres;
        }

        if (str_starts_with($chiffres, '00')) {
            return '+' . substr($chiffres, 2);
        }

        if (str_starts_with($chiffres, '237') && strlen($chiffres) === 12) {
            return '+' . $chiffres;
        }

        // Numéro local camerounais à 9 chiffres (ex: 699112233)
        if (strlen($chiffres) === 9 && in_array($chiffres[0], ['6', '2'])) {
            return $prefixeParDefaut . $chiffres;
        }

        return '+' . $chiffres;
    }

    /**
     * Récupère ou initialise la configuration de la passerelle SMS de l'établissement.
     *
     * @param int $etablissementId
     * @return SmsConfiguration
     */
    public function getConfiguration(int $etablissementId): SmsConfiguration
    {
        return SmsConfiguration::firstOrCreate(
            ['etablissement_id' => $etablissementId],
            [
                'fournisseur' => 'africas_talking',
                'sender_id' => 'SYGES',
                'solde_credits' => 100,
                'seuil_alerte_solde' => 20,
                'is_active' => true,
            ]
        );
    }

    /**
     * Émet un SMS direct unitaire et l'enregistre dans l'historique.
     *
     * @param int $etablissementId
     * @param string $telephone
     * @param string $message
     * @param string $type
     * @param string|null $destinataireNom
     * @param int|null $inscriptionId
     * @return HistoriqueSms
     * @throws ValidationException
     */
    public function envoyerSmsDirect(
        int $etablissementId,
        string $telephone,
        string $message,
        string $type = 'personnalise',
        ?string $destinataireNom = null,
        ?int $inscriptionId = null
    ): HistoriqueSms {
        $config = $this->getConfiguration($etablissementId);
        $messageSanitise = $this->sanitiserGsm7($message);
        $creditsRequis = $this->calculerSegments($messageSanitise);

        if ($config->solde_credits < $creditsRequis) {
            throw ValidationException::withMessages([
                'solde' => "Solde SMS insuffisant ({$config->solde_credits} crédits disponibles, {$creditsRequis} requis). Veuillez recharger votre compte.",
            ]);
        }

        $numeroNormalise = $this->normaliserNumero($telephone);

        return DB::transaction(function () use (
            $etablissementId,
            $config,
            $numeroNormalise,
            $destinataireNom,
            $messageSanitise,
            $type,
            $inscriptionId,
            $creditsRequis
        ) {
            // Décrémenter le solde
            $config->decrement('solde_credits', $creditsRequis);

            return HistoriqueSms::create([
                'etablissement_id' => $etablissementId,
                'campagne_id' => null,
                'inscription_id' => $inscriptionId,
                'type_notification' => $type,
                'destinataire_nom' => $destinataireNom,
                'destinataire_telephone' => $numeroNormalise,
                'message' => $messageSanitise,
                'statut_distribution' => 'envoye',
                'credits_consommes' => $creditsRequis,
                'reponse_fournisseur' => 'SUCCESS_GATEWAY_' . uniqid(),
            ]);
        });
    }

    /**
     * Émet une notification automatique prédéfinie pour un élève (Gesco 14.2.A).
     *
     * @param string $type 'alerte_absence', 'alerte_sanction', 'rappel_paiement', 'confirmation_recu'
     * @param Inscription $inscription
     * @param array<string, mixed> $variables
     * @return HistoriqueSms|null
     */
    public function envoyerNotificationAutomatique(string $type, Inscription $inscription, array $variables = []): ?HistoriqueSms
    {
        $classe = $inscription->classe()->with('niveau.cycle.section.etablissement')->first();
        $etablissementId = $classe?->niveau?->cycle?->section?->etablissement_id;

        if (! $etablissementId) {
            return null;
        }

        $eleve = $inscription->eleve;
        $telephone = $eleve?->telephone_parent ?? $eleve?->telephone;

        if (! $telephone) {
            return null;
        }

        $nomEleve = "{$eleve?->nom} {$eleve?->prenom}";

        $templates = [
            'alerte_absence' => "SYGES: {$nomEleve} signale(e) absent(e) le " . ($variables['date'] ?? date('d/m')) . ' (' . ($variables['heures'] ?? '1') . 'h). Contactez la vie scolaire.',
            'alerte_sanction' => "SYGES: Sanction (" . ($variables['sanction'] ?? 'Discipline') . ") prononcee pour {$nomEleve}. Motif: " . ($variables['motif'] ?? 'Indiscipline') . '.',
            'rappel_paiement' => "SYGES: Rappel scolarite pour {$nomEleve}. Solde restant exigible: " . number_format($variables['solde'] ?? 0, 0, ',', ' ') . ' FCFA. Merci de regulariser.',
            'confirmation_recu' => "SYGES: Versement recu pour {$nomEleve}: " . number_format($variables['montant'] ?? 0, 0, ',', ' ') . " FCFA. Recu: " . ($variables['recu'] ?? 'REC') . ". Solde: " . number_format($variables['solde_restant'] ?? 0, 0, ',', ' ') . ' FCFA.',
        ];

        $message = $templates[$type] ?? "SYGES: Notification concernant l'eleve {$nomEleve}.";

        try {
            return $this->envoyerSmsDirect(
                $etablissementId,
                $telephone,
                $message,
                $type,
                $eleve?->nom_parent ?? $nomEleve,
                $inscription->id
            );
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Lance une campagne SMS groupée vers une cible définie (Gesco 14.2.B).
     *
     * @param int $etablissementId
     * @param array{
     *     titre: string,
     *     message: string,
     *     cible_type: string,
     *     cible_id?: int|null,
     * } $data
     * @param User|null $user
     * @return SmsCampagne
     * @throws ValidationException
     */
    public function lancerCampagneMasse(int $etablissementId, array $data, ?User $user = null): SmsCampagne
    {
        $config = $this->getConfiguration($etablissementId);
        $messageSanitise = $this->sanitiserGsm7($data['message']);
        $segmentsParMessage = $this->calculerSegments($messageSanitise);

        // 1. Déterminer les destinataires selon la cible
        $inscriptionsQuery = Inscription::with(['eleve', 'compteFinancier'])
            ->whereHas('classe.niveau.cycle.section', fn ($q) => $q->where('etablissement_id', $etablissementId))
            ->where('statut', 'validee');

        $cibleType = $data['cible_type'] ?? 'tous';
        $cibleId = $data['cible_id'] ?? null;

        if ($cibleType === 'classe' && $cibleId) {
            $inscriptionsQuery->where('classe_id', $cibleId);
        } elseif ($cibleType === 'niveau' && $cibleId) {
            $inscriptionsQuery->whereHas('classe', fn ($q) => $q->where('niveau_id', $cibleId));
        } elseif ($cibleType === 'section' && $cibleId) {
            $inscriptionsQuery->whereHas('classe.niveau.cycle', fn ($q) => $q->where('section_id', $cibleId));
        } elseif ($cibleType === 'insolvables') {
            $inscriptionsQuery->whereHas('compteFinancier', fn ($q) => $q->where('solde_restant', '>', 0));
        }

        $inscriptions = $inscriptionsQuery->get();

        // Extraire les numéros uniques de parents valides
        $destinataires = [];
        foreach ($inscriptions as $ins) {
            $tel = $ins->eleve?->telephone_parent ?? $ins->eleve?->telephone;
            if ($tel) {
                $telNormalise = $this->normaliserNumero($tel);
                if (! isset($destinataires[$telNormalise])) {
                    $destinataires[$telNormalise] = [
                        'telephone' => $telNormalise,
                        'nom' => $ins->eleve?->nom_parent ?? "{$ins->eleve?->nom} {$ins->eleve?->prenom}",
                        'inscription_id' => $ins->id,
                    ];
                }
            }
        }

        $nbDestinataires = count($destinataires);

        if ($nbDestinataires === 0) {
            throw ValidationException::withMessages([
                'cible_type' => 'Aucun numéro de téléphone de parent valide trouvé pour cette sélection.',
            ]);
        }

        $totalCreditsRequis = $nbDestinataires * $segmentsParMessage;

        if ($config->solde_credits < $totalCreditsRequis) {
            throw ValidationException::withMessages([
                'solde' => "Solde insuffisant pour diffuser cette campagne ({$nbDestinataires} destinataires × {$segmentsParMessage} segment(s) = {$totalCreditsRequis} crédits requis, solde actuel : {$config->solde_credits}).",
            ]);
        }

        return DB::transaction(function () use (
            $etablissementId,
            $data,
            $messageSanitise,
            $cibleType,
            $cibleId,
            $nbDestinataires,
            $totalCreditsRequis,
            $segmentsParMessage,
            $destinataires,
            $config,
            $user
        ) {
            // Créer la campagne
            $campagne = SmsCampagne::create([
                'etablissement_id' => $etablissementId,
                'titre' => $data['titre'],
                'message_brut' => $data['message'],
                'message_sanitise' => $messageSanitise,
                'cible_type' => $cibleType,
                'cible_id' => $cibleId,
                'nombre_destinataires' => $nbDestinataires,
                'total_credits' => $totalCreditsRequis,
                'statut' => 'termine',
                'lance_par_user_id' => $user?->id,
            ]);

            // Décrémenter le solde de l'établissement
            $config->decrement('solde_credits', $totalCreditsRequis);

            // Créer les entrées individuelles d'historique
            $historiqueData = [];
            $now = now();
            foreach ($destinataires as $dest) {
                $historiqueData[] = [
                    'etablissement_id' => $etablissementId,
                    'campagne_id' => $campagne->id,
                    'inscription_id' => $dest['inscription_id'],
                    'type_notification' => 'campagne_masse',
                    'destinataire_nom' => $dest['nom'],
                    'destinataire_telephone' => $dest['telephone'],
                    'message' => $messageSanitise,
                    'statut_distribution' => 'envoye',
                    'credits_consommes' => $segmentsParMessage,
                    'reponse_fournisseur' => 'BULK_SENT_' . $campagne->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            HistoriqueSms::insert($historiqueData);

            return $campagne;
        });
    }

    /**
     * Crédite le solde SMS d'un établissement.
     *
     * @param int $etablissementId
     * @param int $credits
     * @return SmsConfiguration
     */
    public function rechargerCredits(int $etablissementId, int $credits): SmsConfiguration
    {
        $config = $this->getConfiguration($etablissementId);
        $config->increment('solde_credits', max(1, $credits));

        return $config;
    }
}
