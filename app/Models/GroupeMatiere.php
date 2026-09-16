<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modèle représentant un Groupe de Matières (Bloc Pédagogique).
 *
 * Regroupe les disciplines par blocs homogènes pour la présentation structurée
 * des bulletins scolaires (conformes aux normes MINESEC/MINEDUB) et le calcul
 * des moyennes sectorielles (Module 4.1 Gesco).
 *
 * @property int $id
 * @property string $code
 * @property string $nom
 * @property string|null $nom_en
 * @property string|null $couleur
 * @property int $ordre
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\MatiereNiveau> $matiereNiveaux
 */
class GroupeMatiere extends Model
{
    use HasFactory;

    /**
     * Nom explicite de la table plurielle.
     *
     * @var string
     */
    protected $table = 'groupes_matieres';

    /**
     * Les attributs assignables en masse.
     *
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'nom',
        'nom_en',
        'couleur',
        'ordre',
    ];

    /**
     * Typages des attributs.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'ordre' => 'integer',
    ];

    /**
     * Relation un-à-plusieurs vers les coefficients/matières rattachés à ce groupe.
     *
     * @return HasMany
     */
    public function matiereNiveaux(): HasMany
    {
        return $this->hasMany(MatiereNiveau::class, 'groupe_matiere_id');
    }
}
