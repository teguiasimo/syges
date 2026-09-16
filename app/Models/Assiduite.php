<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modèle représentant un Événement d'Assiduité : Absence ou Retard (Gesco V1.1 - Module 12.1).
 *
 * Enregistre au quotidien les absences et retards horodatés avec la matière concernée,
 * la durée en heures, le statut de justification et les éventuels justificatifs médicaux scannés.
 *
 * @property int $id Identifiant unique de l'assiduité
 * @property int $inscription_id Inscription scolaire de l'élève
 * @property int $periode_id Trimestre / Période concernée
 * @property string $type_evenement Type : 'absence' ou 'retard'
 * @property string $date_evenement Date de l'événement (Y-m-d)
 * @property float $nombre_heures Volume horaire de l'absence ou retard
 * @property int|null $matiere_id Matière durant laquelle l'absence est survenue
 * @property string $statut_justification 'justifiee' ou 'non_justifiee'
 * @property string|null $motif_justification Explication ou motif de la justification
 * @property string|null $justificatif_path Chemin du fichier scanné (certificat médical, mot des parents)
 * @property int|null $signale_par Utilisateur ayant signalé l'événement
 */
class Assiduite extends Model
{
    use HasFactory;

    /**
     * Nom de la table associée dans la base de données.
     *
     * @var string
     */
    protected $table = 'assiduites';

    /**
     * Les attributs assignables en masse.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'inscription_id',
        'periode_id',
        'type_evenement',
        'date_evenement',
        'nombre_heures',
        'matiere_id',
        'statut_justification',
        'motif_justification',
        'justificatif_path',
        'signale_par',
    ];

    /**
     * Les attributs convertis dans des types natifs.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'date_evenement' => 'date',
        'nombre_heures' => 'float',
    ];

    /**
     * Relation vers l'inscription de l'élève.
     *
     * @return BelongsTo<Inscription, Assiduite>
     */
    public function inscription(): BelongsTo
    {
        return $this->belongsTo(Inscription::class);
    }

    /**
     * Relation vers la période scolaire (trimestre).
     *
     * @return BelongsTo<Periode, Assiduite>
     */
    public function periode(): BelongsTo
    {
        return $this->belongsTo(Periode::class);
    }

    /**
     * Relation vers la matière d'enseignement concernée.
     *
     * @return BelongsTo<Matiere, Assiduite>
     */
    public function matiere(): BelongsTo
    {
        return $this->belongsTo(Matiere::class);
    }

    /**
     * Relation vers l'utilisateur ayant signalé l'événement.
     *
     * @return BelongsTo<User, Assiduite>
     */
    public function signalant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signale_par');
    }
}
