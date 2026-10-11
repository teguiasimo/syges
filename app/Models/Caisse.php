<?php

namespace App\Models;

use App\Concerns\BelongsToEtablissement;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modèle représentant une Caisse d'établissement (Module 11 - Gestion Financière Avancée).
 *
 * Permet de gérer les caisses multiples de l'établissement :
 * - Caisse Principale (encaissement scolarité et inscription)
 * - Caisse Cantine / Activités périscolaires
 * - Caisses annexes d'établissement
 *
 * @property int $id Identifiant unique de la caisse
 * @property int $etablissement_id Établissement de rattachement
 * @property string $code Code abrégé unique (ex: 'CAISSE-PRIN')
 * @property string $nom Libellé de la caisse
 * @property float $solde_initial Solde d'ouverture initial
 * @property int|null $responsable_id Personnel responsable de la caisse
 * @property bool $is_principale Indique s'il s'agit de la caisse principale
 * @property bool $is_active État d'activité de la caisse
 * @property string|null $description Description ou affectation de la caisse
 */
class Caisse extends Model
{
    use HasFactory, BelongsToEtablissement;

    /**
     * Nom de la table associée.
     *
     * @var string
     */
    protected $table = 'caisses';

    /**
     * Attributs assignables en masse.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'etablissement_id',
        'code',
        'nom',
        'solde_initial',
        'responsable_id',
        'is_principale',
        'is_active',
        'description',
    ];

    /**
     * Casts des attributs.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'solde_initial' => 'float',
        'is_principale' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Établissement propriétaire de la caisse.
     *
     * @return BelongsTo<Etablissement, Caisse>
     */
    public function etablissement(): BelongsTo
    {
        return $this->belongsTo(Etablissement::class);
    }

    /**
     * Personnel assigné comme responsable de la caisse.
     *
     * @return BelongsTo<Personnel, Caisse>
     */
    public function responsable(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'responsable_id');
    }

    /**
     * Versements de scolarité associés à cette caisse.
     *
     * @return HasMany<VersementCaisse>
     */
    public function versements(): HasMany
    {
        return $this->hasMany(VersementCaisse::class, 'caisse_id');
    }

    /**
     * Règlements divers (hors scolarité) encaissés dans cette caisse.
     *
     * @return HasMany<ReglementDivers>
     */
    public function reglementsDivers(): HasMany
    {
        return $this->hasMany(ReglementDivers::class, 'caisse_id');
    }

    /**
     * Clôtures journalières associées à cette caisse.
     *
     * @return HasMany<ClotureCaisse>
     */
    public function clotures(): HasMany
    {
        return $this->hasMany(ClotureCaisse::class, 'caisse_id');
    }
}
