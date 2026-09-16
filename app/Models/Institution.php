<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modèle représentant l'Institution scolaire (entité faîtière / siège).
 *
 * Centralise l'identité globale, les coordonnées officielles et les tutelles
 * administratives pour l'ensemble des campus et établissements rattachés.
 *
 * @property int $id
 * @property string $code
 * @property string|null $sigle
 * @property string $nom
 * @property string|null $nom_en
 * @property string|null $devise
 * @property string|null $devise_en
 * @property string|null $logo
 * @property string|null $email
 * @property string|null $telephone_1
 * @property string|null $telephone_2
 * @property string|null $boite_postale
 * @property string|null $site_web
 * @property string|null $region
 * @property string|null $region_en
 * @property string|null $departement
 * @property string|null $departement_en
 * @property string|null $arrondissement
 * @property string|null $arrondissement_en
 * @property string|null $ville
 * @property string|null $delegation_regionale
 * @property string|null $delegation_regionale_en
 * @property string|null $delegation_departementale
 * @property string|null $delegation_departementale_en
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Etablissement> $etablissements
 */
class Institution extends Model
{
    use HasFactory;

    /**
     * Les attributs assignables en masse.
     *
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'sigle',
        'nom',
        'nom_en',
        'devise',
        'devise_en',
        'logo',
        'email',
        'telephone_1',
        'telephone_2',
        'boite_postale',
        'site_web',
        'region',
        'region_en',
        'departement',
        'departement_en',
        'arrondissement',
        'arrondissement_en',
        'ville',
        'delegation_regionale',
        'delegation_regionale_en',
        'delegation_departementale',
        'delegation_departementale_en',
    ];

    /**
     * Relation un-à-plusieurs vers les établissements (campus) rattachés.
     *
     * @return HasMany
     */
    public function etablissements(): HasMany
    {
        return $this->hasMany(Etablissement::class);
    }
}
