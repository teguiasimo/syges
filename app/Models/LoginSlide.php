<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Modèle Eloquent représentant un slide configurable du carrousel de connexion.
 *
 * Chaque diapositive contient une photographie importée par l'administrateur,
 * un titre court et un message d'accompagnement affichés sur le panneau gauche de `/login`.
 *
 * @property int $id
 * @property string $titre
 * @property string|null $message
 * @property string $image_path
 * @property int $ordre
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class LoginSlide extends Model
{
    /**
     * Attributs assignables en masse.
     *
     * @var list<string>
     */
    protected $fillable = [
        'titre',
        'message',
        'image_path',
        'ordre',
        'is_active',
    ];

    /**
     * Conversions de types natives.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'ordre' => 'integer',
        'is_active' => 'boolean',
    ];
}
