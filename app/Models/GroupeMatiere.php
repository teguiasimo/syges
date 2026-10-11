<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modèle représentant un Groupe de Matières (Bloc Pédagogique).
 *
 * Regroupe les disciplines par blocs homogènes pour la présentation structurée
 * des bulletins scolaires (conformes aux normes MINESEC/MINEDUB) et le calcul
 * des moyennes sectorielles (Module 4.1 Gesco).
 * Rattaché à un établissement scolaire spécifique (ou standard/partagé si null).
 *
 * @property int $id
 * @property int|null $etablissement_id
 * @property string $code
 * @property string $nom
 * @property string|null $nom_en
 * @property string|null $couleur
 * @property int $ordre
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Etablissement|null $etablissement
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
        'etablissement_id',
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
        'etablissement_id' => 'integer',
        'ordre' => 'integer',
    ];

    /**
     * Relation plusieurs-à-un vers l'Établissement scolaire propriétaire.
     *
     * @return BelongsTo
     */
    public function etablissement(): BelongsTo
    {
        return $this->belongsTo(Etablissement::class, 'etablissement_id');
    }

    /**
     * Relation un-à-plusieurs vers les coefficients/matières rattachés à ce groupe.
     *
     * @return HasMany
     */
    public function matiereNiveaux(): HasMany
    {
        return $this->hasMany(MatiereNiveau::class, 'groupe_matiere_id');
    }

    /**
     * Filtre les blocs pédagogiques rattachés à un établissement spécifique ou communs.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query Requête Eloquent.
     * @param int|null $etablissementId Identifiant de l'établissement.
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForEtablissement($query, ?int $etablissementId)
    {
        if (! $etablissementId) {
            return $query;
        }

        return $query->where(function ($q) use ($etablissementId) {
            $q->where('etablissement_id', $etablissementId)
                ->orWhereNull('etablissement_id');
        });
    }

    /**
     * Détermine l'ordre d'affichage contextuel du groupe selon la dominante de la série.
     * Pour les séries littéraires (ex: A4, A, Littéraire, Allemand, Espagnol), le bloc littéraire
     * est priorisé en 1ère position, suivi du bloc scientifique.
     * Pour les séries scientifiques (ex: C, D, TI, Sciences), le bloc scientifique passe en 1ère position.
     *
     * @param string|null $serie Nom ou code de la série (ex: 'A4 ALL', 'C', 'D', 'TI').
     * @return int Numéro d'ordre contextuel.
     */
    public function getOrdrePourSerie(?string $serie = null): int
    {
        if (empty($serie) || in_array(strtoupper(trim($serie)), ['GENERAL', 'GÉNÉRAL', 'TOUTES', 'TC', 'TRONC COMMUN'], true)) {
            return (int) $this->ordre;
        }

        $serieUpper = strtoupper(trim($serie));
        $codeUpper = strtoupper(trim($this->code ?? ''));
        $nomUpper = strtoupper(trim($this->nom ?? ''));

        // Détection de la dominante littéraire (séries A4, A, ALL, ESP, L, etc.)
        $isSerieLitteraire = preg_match('/\b(A|A4|A1|L|LIT|ALLEMAND|ESPAGNOL|ALL|ESP|LETTRES|PHILOSOPHIE)\b/i', $serieUpper)
            || str_contains($serieUpper, 'A4')
            || str_contains($serieUpper, 'LIT')
            || str_contains($serieUpper, 'ALLEMAND')
            || str_contains($serieUpper, 'ESPAGNOL');

        // Détection de la dominante scientifique / technique (séries C, D, TI, E, S, etc.)
        $isSerieScientifique = preg_match('/\b(C|D|TI|E|S|SCI|SCIENTIFIQUE|TECHNIQUE)\b/i', $serieUpper)
            || str_contains($serieUpper, 'TI');

        // Identification de la typologie du groupe de matières
        $isGroupeLitteraire = str_contains($codeUpper, 'LIT') || str_contains($nomUpper, 'LITT');
        $isGroupeScientifique = str_contains($codeUpper, 'SCI') || str_contains($nomUpper, 'SCIENT');
        $isGroupeComplementaire = str_contains($codeUpper, 'COMP') || str_contains($codeUpper, 'DEV') || str_contains($nomUpper, 'COMPL');

        if ($isSerieLitteraire) {
            if ($isGroupeLitteraire) {
                return 1;
            }
            if ($isGroupeScientifique) {
                return 2;
            }
            if ($isGroupeComplementaire) {
                return 3;
            }
        } elseif ($isSerieScientifique) {
            if ($isGroupeScientifique) {
                return 1;
            }
            if ($isGroupeLitteraire) {
                return 2;
            }
            if ($isGroupeComplementaire) {
                return 3;
            }
        }

        return (int) $this->ordre;
    }
}

