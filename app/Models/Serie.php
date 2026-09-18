<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modèle représentant une Série pédagogique d'un établissement.
 *
 * Contient uniquement le nom de la série (ex: GENERAL, C, D, E, TI, ALLEMAND, ESPAGNOL, A4 ALL, A4 ESP).
 *
 * @property int $id Identifiant unique de la série
 * @property int $etablissement_id Établissement de rattachement
 * @property string $nom Nom unique de la série
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Etablissement $etablissement Établissement parent
 */
class Serie extends Model
{
    use HasFactory;

    /**
     * Liste des séries préconfigurées par défaut pour chaque établissement.
     *
     * @var list<string>
     */
    public const DEFAULT_SERIES = [
        'GENERAL',
        'C',
        'D',
        'E',
        'TI',
        'ALLEMAND',
        'ESPAGNOL',
        'A4 ALL',
        'A4 ESP',
    ];

    /**
     * Les attributs assignables en masse.
     *
     * @var list<string>
     */
    protected $fillable = [
        'etablissement_id',
        'nom',
    ];

    /**
     * Relation plusieurs-à-un vers l'Établissement propriétaire.
     *
     * @return BelongsTo<\App\Models\Etablissement, \App\Models\Serie>
     */
    public function etablissement(): BelongsTo
    {
        return $this->belongsTo(Etablissement::class);
    }

    /**
     * Assure la présence des séries par défaut pour un établissement donné.
     *
     * @param int $etablissementId Identifiant de l'établissement.
     * @return void
     */
    public static function seedDefaultsForEtablissement(int $etablissementId): void
    {
        foreach (self::DEFAULT_SERIES as $nom) {
            self::firstOrCreate([
                'etablissement_id' => $etablissementId,
                'nom' => $nom,
            ]);
        }
    }
}
