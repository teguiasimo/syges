<?php

namespace App\Models;

use App\Concerns\BelongsToEtablissement;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Modèle ConfigurationNumerotation - Masques & Souches de Numérotation (Aurore §3.15).
 * 
 * Permet de définir les radicaux, préfixes et formats séquentiels personnalisés
 * pour les matricules d'élèves/personnel, les reçus de caisse et les certificats officiels.
 *
 * @property int $id
 * @property int $etablissement_id
 * @property string $type_document (matricule_eleve, matricule_personnel, recu_scolarite, recu_divers, certificat_scolarite, certificat_radiation)
 * @property string $prefixe
 * @property string $format_masque
 * @property int $compteur_actuel
 * @property string|null $annee_reference
 * @property bool $reinitialiser_annuellement
 */
class ConfigurationNumerotation extends Model
{
    use HasFactory, BelongsToEtablissement;

    /**
     * Nom de la table associée.
     *
     * @var string
     */
    protected $table = 'configurations_numerotation';

    /**
     * Les attributs assignables en masse.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'etablissement_id',
        'type_document',
        'prefixe',
        'format_masque',
        'compteur_actuel',
        'annee_reference',
        'reinitialiser_annuellement',
    ];

    /**
     * Les attributs typés nativement.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'compteur_actuel' => 'integer',
        'reinitialiser_annuellement' => 'boolean',
    ];

    /**
     * Génère de manière atomique et sécurisée le prochain numéro séquentiel selon le masque configuré.
     *
     * @param int $etablissementId Identifiant du campus
     * @param string $typeDocument Type de document visé
     * @param string|null $annee Année scolaire ou civile de référence (défaut année courante)
     * @return string Le numéro séquentiel formaté
     */
    public static function genererNumero(int $etablissementId, string $typeDocument, ?string $annee = null): string
    {
        $currentYear = $annee ?: date('Y');

        return DB::transaction(function () use ($etablissementId, $typeDocument, $currentYear) {
            /** @var ConfigurationNumerotation|null $config */
            $config = static::withoutGlobalScopes()
                ->where('etablissement_id', $etablissementId)
                ->where('type_document', $typeDocument)
                ->lockForUpdate()
                ->first();

            // Si aucune configuration spécifique, on initialise une configuration par défaut
            if (!$config) {
                $defaults = static::getDefaultsForType($typeDocument, $etablissementId);
                $config = static::create([
                    'etablissement_id' => $etablissementId,
                    'type_document' => $typeDocument,
                    'prefixe' => $defaults['prefixe'],
                    'format_masque' => $defaults['format_masque'],
                    'compteur_actuel' => 1,
                    'annee_reference' => $currentYear,
                    'reinitialiser_annuellement' => true,
                ]);
            } else {
                // Réinitialisation annuelle si activée et que l'année de référence a changé
                if ($config->reinitialiser_annuellement && $config->annee_reference !== $currentYear) {
                    $config->compteur_actuel = 1;
                    $config->annee_reference = $currentYear;
                } else {
                    $config->compteur_actuel++;
                }
                $config->save();
            }

            $compteur = $config->compteur_actuel;
            $prefixe = $config->prefixe;
            $masque = $config->format_masque;

            // Remplacement des variables du masque
            $numero = str_replace('{PREFIX}', $prefixe, $masque);
            $numero = str_replace('{ANNEE}', $currentYear, $numero);

            // Gestion des zéros de remplissage {NUM:X}
            if (preg_match('/\{NUM:(\d+)\}/', $numero, $matches)) {
                $padLength = (int) $matches[1];
                $formattedCount = str_pad((string) $compteur, $padLength, '0', STR_PAD_LEFT);
                $numero = str_replace($matches[0], $formattedCount, $numero);
            } else {
                $numero = str_replace('{NUM}', (string) $compteur, $numero);
            }

            return $numero;
        });
    }

    /**
     * Fournit les paramètres par défaut selon le type de pièce ou matricule.
     * Intègre dynamiquement le code officiel du campus si fourni.
     *
     * @param string $typeDocument
     * @param int|null $etablissementId
     * @return array{prefixe: string, format_masque: string}
     */
    public static function getDefaultsForType(string $typeDocument, ?int $etablissementId = null): array
    {
        $code = null;
        if ($etablissementId) {
            $etab = \App\Models\Etablissement::find($etablissementId);
            $code = $etab?->code ? strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $etab->code)) : null;
        }

        return match ($typeDocument) {
            'matricule_eleve' => [
                'prefixe' => $code ?: 'GS',
                'format_masque' => '{PREFIX}-{ANNEE}-{NUM:4}',
            ],
            'matricule_personnel' => [
                'prefixe' => $code ? "PERS-{$code}" : 'PERS',
                'format_masque' => '{PREFIX}-{ANNEE}-{NUM:4}',
            ],
            'recu_scolarite' => [
                'prefixe' => $code ? "REC-{$code}" : 'REC',
                'format_masque' => '{PREFIX}-{ANNEE}-{NUM:5}',
            ],
            'recu_divers' => [
                'prefixe' => $code ? "RD-{$code}" : 'RD',
                'format_masque' => '{PREFIX}-{ANNEE}-{NUM:5}',
            ],
            'certificat_scolarite' => [
                'prefixe' => $code ? "CS-{$code}" : 'CS',
                'format_masque' => '{PREFIX}/{ANNEE}/{NUM:4}',
            ],
            'certificat_radiation' => [
                'prefixe' => $code ? "CR-{$code}" : 'CR',
                'format_masque' => '{PREFIX}/{ANNEE}/{NUM:4}',
            ],
            default => [
                'prefixe' => $code ? "DOC-{$code}" : 'DOC',
                'format_masque' => '{PREFIX}-{ANNEE}-{NUM:4}',
            ],
        };
    }
}
