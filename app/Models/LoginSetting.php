<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Modèle Eloquent stockant la configuration globale de l'écran de connexion (`/login`).
 *
 * Centralise le mode d'affichage du carrousel (slides par défaut SYGES vs slides personnalisés),
 * la cadence de rotation et les informations de branding/assistance affichées avant authentification.
 *
 * @property int $id
 * @property bool $use_custom_slides
 * @property int $rotation_interval_seconds
 * @property string|null $brand_name
 * @property string|null $brand_subtitle
 * @property string|null $logo_path
 * @property string|null $footer_left
 * @property string|null $footer_right
 * @property string|null $support_label
 * @property string|null $support_phone
 * @property string|null $support_email
 * @property bool $apply_branding_to_app
 * @property string|null $app_brand_name
 * @property string|null $app_brand_subtitle
 * @property string|null $app_logo_path
 * @property bool $sync_favicon
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class LoginSetting extends Model
{
    /**
     * Attributs assignables en masse.
     *
     * @var list<string>
     */
    protected $fillable = [
        'use_custom_slides',
        'rotation_interval_seconds',
        'brand_name',
        'brand_subtitle',
        'logo_path',
        'footer_left',
        'footer_right',
        'support_label',
        'support_phone',
        'support_email',
        'apply_branding_to_app',
        'app_brand_name',
        'app_brand_subtitle',
        'app_logo_path',
        'sync_favicon',
    ];

    /**
     * Conversions de types natives.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'use_custom_slides' => 'boolean',
        'rotation_interval_seconds' => 'integer',
        'apply_branding_to_app' => 'boolean',
        'sync_favicon' => 'boolean',
    ];
}
