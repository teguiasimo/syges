<?php

namespace App\Models;

use App\Concerns\BelongsToEtablissement;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Modèle représentant la configuration d'une Cote d'Acquisition APC personnalisée.
 *
 * Permet aux établissements qui le souhaitent de configurer des cotes alphabétiques (A, B, C, D)
 * ou de conserver/adapter le barème officiel camerounais (CTBA, CBA, CMA, CNA).
 *
 * @property int $id
 * @property int $etablissement_id
 * @property string $code_cote
 * @property string $libelle
 * @property string|null $libelle_en
 * @property float $borne_minimale
 * @property float $borne_maximale
 * @property string $couleur
 * @property int $ordre
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Etablissement $etablissement
 */
class CoteConfiguration extends Model
{
    use BelongsToEtablissement, HasFactory;

    /**
     * Nom explicite de la table.
     *
     * @var string
     */
    protected $table = 'cotes_configurations';

    /**
     * Attributs assignables en masse.
     *
     * @var list<string>
     */
    protected $fillable = [
        'etablissement_id',
        'code_cote',
        'libelle',
        'libelle_en',
        'borne_minimale',
        'borne_maximale',
        'couleur',
        'ordre',
    ];

    /**
     * Casts des attributs.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'borne_minimale' => 'decimal:2',
        'borne_maximale' => 'decimal:2',
        'ordre' => 'integer',
    ];

    /**
     * Relation vers l'établissement.
     */
    public function etablissement(): BelongsTo
    {
        return $this->belongsTo(Etablissement::class);
    }

    /**
     * Cache statique en mémoire (durée de la requête PHP) des configurations de cotes par établissement.
     * Élimine le N+1 massif (jusqu'à 221 requêtes SQL identiques sur `/dashboard` et `/statistiques`).
     *
     * @var array<int, array<int, array{borne_minimale: float, borne_maximale: float, code_cote: string}>>
     */
    protected static array $configsByEtablissement = [];

    /**
     * Invalide automatiquement le cache statique lors de toute création, modification ou suppression.
     */
    protected static function booted(): void
    {
        static::saved(static function (self $config): void {
            unset(self::$configsByEtablissement[(int) $config->etablissement_id]);
        });

        static::deleted(static function (self $config): void {
            unset(self::$configsByEtablissement[(int) $config->etablissement_id]);
        });
    }

    /**
     * Réinitialise manuellement le cache en mémoire des configurations de cotes (utile dans les tests).
     *
     * @param  int|null  $etablissementId  Établissement ciblé ou null pour tout vider
     */
    public static function clearCache(?int $etablissementId = null): void
    {
        if ($etablissementId === null) {
            self::$configsByEtablissement = [];

            return;
        }

        unset(self::$configsByEtablissement[$etablissementId]);
    }

    /**
     * Détermine la cote APC d'une note selon les règles de l'établissement ou le barème national MINESEC.
     * Charge les tranches d'un établissement une seule fois par requête HTTP (0 requête N+1).
     *
     * @param  float  $note  Note sur 20
     * @param  int|null  $etablissementId  Identifiant de l'établissement concerné
     * @return string Code de la cote (ex: CTBA, CBA, A, B+)
     */
    public static function determinerCote(float $note, ?int $etablissementId = null): string
    {
        if ($etablissementId) {
            if (! array_key_exists($etablissementId, self::$configsByEtablissement)) {
                self::$configsByEtablissement[$etablissementId] = self::query()
                    ->where('etablissement_id', $etablissementId)
                    ->orderByDesc('borne_minimale')
                    ->get(['borne_minimale', 'borne_maximale', 'code_cote'])
                    ->map(static fn (self $c): array => [
                        'borne_minimale' => (float) $c->borne_minimale,
                        'borne_maximale' => (float) $c->borne_maximale,
                        'code_cote' => (string) $c->code_cote,
                    ])
                    ->all();
            }

            foreach (self::$configsByEtablissement[$etablissementId] as $config) {
                if ($config['borne_minimale'] <= $note && $config['borne_maximale'] >= $note) {
                    return $config['code_cote'];
                }
            }
        }

        // Repli par défaut sur la norme officielle MINESEC
        return Note::determinerCoteAPC($note);
    }
}
