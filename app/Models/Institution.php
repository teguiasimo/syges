<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

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
 * @property string|null $logo_filigrane
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
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Etablissement> $etablissements
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
        'logo_filigrane',
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
     * Invalide automatiquement le cache mémoire de repli des établissements lors de toute modification de l'institution.
     */
    protected static function booted(): void
    {
        static::saved(fn () => Etablissement::clearFallbackCache());
        static::deleted(fn () => Etablissement::clearFallbackCache());
    }

    /**
     * Relation un-à-plusieurs vers les établissements (campus) rattachés.
     */
    public function etablissements(): HasMany
    {
        return $this->hasMany(Etablissement::class);
    }

    /**
     * Encode une image locale (public/ ou storage/app/public/) en Data URI Base64 pour DomPDF.
     *
     * Résout de manière uniforme les chemins publics (`/storage/...`, `/logo.png`) et les chemins
     * relatifs sur le disque `public`, garantissant un rendu fiable avec ou sans lien symbolique.
     *
     * @param  string|null  $path  Chemin ou URL relative de l'image.
     * @return string|null Data URI Base64 prête pour l'attribut HTML `src`, ou null si introuvable.
     */
    public static function resolveImageBase64(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }

        // Supporte le cas où une Data URI est déjà fournie
        if (str_starts_with($path, 'data:image/')) {
            return $path;
        }

        if (str_contains($path, '..')) {
            return null;
        }

        $trimmed = ltrim($path, '/\\');
        $storageRelative = preg_replace('#^/?storage/#i', '', $trimmed);

        $allowedExtensions = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
        $allowedRoots = array_filter([
            realpath(public_path()),
            realpath(storage_path('app/public')),
        ]);

        $candidates = array_unique(array_filter([
            public_path($trimmed),
            storage_path('app/public/'.$storageRelative),
        ]));

        foreach ($candidates as $candidate) {
            if ($candidate && file_exists($candidate) && is_file($candidate)) {
                $realCandidate = realpath($candidate);
                if ($realCandidate === false) {
                    continue;
                }

                // Vérification anti-LFI (Path Traversal) : le fichier résolu doit résider dans public/ ou storage/app/public/
                $isInsideAllowedRoot = false;
                foreach ($allowedRoots as $root) {
                    if (str_starts_with($realCandidate, $root.DIRECTORY_SEPARATOR) || $realCandidate === $root) {
                        $isInsideAllowedRoot = true;
                        break;
                    }
                }
                if (! $isInsideAllowedRoot) {
                    continue;
                }

                $ext = strtolower(pathinfo($realCandidate, PATHINFO_EXTENSION));
                if (! in_array($ext, $allowedExtensions, true)) {
                    continue;
                }

                $mime = match ($ext) {
                    'jpg', 'jpeg' => 'image/jpeg',
                    'gif' => 'image/gif',
                    'webp' => 'image/webp',
                    default => 'image/png',
                };
                $content = @file_get_contents($realCandidate);
                if ($content !== false) {
                    return 'data:'.$mime.';base64,'.base64_encode($content);
                }
            }
        }

        return null;
    }

    /**
     * Retourne le filigrane officiel de l'institution encodé en Base64 pour les PDF consolidés.
     *
     * @return string|null Data URI Base64 du filigrane de l'institution ou null.
     */
    public function getEffectiveFiligraneBase64(): ?string
    {
        return self::resolveImageBase64($this->logo_filigrane);
    }
}
