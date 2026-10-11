<?php

namespace App\Models;

use App\Concerns\BelongsToEtablissement;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Etablissement $etablissement
 */
class CoteConfiguration extends Model
{
    use HasFactory, BelongsToEtablissement;

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
     *
     * @return BelongsTo
     */
    public function etablissement(): BelongsTo
    {
        return $this->belongsTo(Etablissement::class);
    }

    /**
     * Détermine la cote APC d'une note selon les règles de l'établissement ou le barème national MINESEC.
     *
     * @param float $note Note sur 20
     * @param int|null $etablissementId Identifiant de l'établissement concerné
     * @return string Code de la cote (ex: CTBA, CBA, A, B+)
     */
    public static function determinerCote(float $note, ?int $etablissementId = null): string
    {
        if ($etablissementId) {
            $config = self::where('etablissement_id', $etablissementId)
                ->where('borne_minimale', '<=', $note)
                ->where('borne_maximale', '>=', $note)
                ->orderByDesc('borne_minimale')
                ->first();

            if ($config) {
                return $config->code_cote;
            }
        }

        // Repli par défaut sur la norme officielle MINESEC
        return Note::determinerCoteAPC($note);
    }
}
